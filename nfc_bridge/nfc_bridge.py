"""
NFC Bridge Service for ACR122U

Bridges the ACR122U USB NFC reader to the attendance web app via WebSocket.
- Reads NFC tags and sends verified student IDs to connected browsers
- Accepts write commands from browsers to program new tags

Requires: pyscard, websockets
Install:  pip install -r requirements.txt
Run:      python nfc_bridge.py
"""

import asyncio
import hashlib
import hmac
import json
import logging
import time
from concurrent.futures import ThreadPoolExecutor

from smartcard.System import readers
from smartcard.util import toHexString, toBytes
from smartcard.Exceptions import NoCardException, CardConnectionException

import websockets

import config

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s"
)
log = logging.getLogger("nfc_bridge")

# ---------------------------------------------------------------------------
# HMAC helpers
# ---------------------------------------------------------------------------

def sign_student_id(student_id: str) -> str:
    """Create HMAC-SHA256 signature for a student ID."""
    return hmac.new(
        config.HMAC_SECRET.encode(),
        student_id.encode(),
        hashlib.sha256
    ).hexdigest()


def make_payload(student_id: str) -> str:
    """Build the string that gets written to a tag: 'id:hmac'."""
    return f"{student_id}:{sign_student_id(student_id)}"


def verify_payload(payload: str) -> str | None:
    """Verify a tag payload. Returns student_id if valid, None otherwise."""
    if ":" not in payload:
        return None
    student_id, tag_hmac = payload.split(":", 1)
    expected = sign_student_id(student_id)
    if hmac.compare_digest(tag_hmac, expected):
        return student_id
    return None


# ---------------------------------------------------------------------------
# Low-level ACR122U / tag communication
# ---------------------------------------------------------------------------

# Known ATR tag-type byte (found via PC/SC RID pattern)
TAG_TYPE_NTAG = 0x44
TAG_TYPE_MIFARE_1K = 0x01
TAG_TYPE_MIFARE_4K = 0x02

TAG_TYPES = {
    TAG_TYPE_NTAG: "MIFARE Ultralight / NTAG",
    TAG_TYPE_MIFARE_1K: "MIFARE Classic 1K",
    TAG_TYPE_MIFARE_4K: "MIFARE Classic 4K",
    0x03: "MIFARE DESFire",
}

# MIFARE Classic default key A
MIFARE_DEFAULT_KEY = [0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF]

# MIFARE Classic 1K data blocks (skip block 0 = manufacturer, skip sector trailers 3,7,11...)
# Sector 1: blocks 4,5,6 (trailer=7)  Sector 2: blocks 8,9,10 (trailer=11)
MIFARE_DATA_BLOCKS = [4, 5, 6, 8, 9, 10]


def get_reader():
    """Return the first available PC/SC reader, or None."""
    r = readers()
    if r:
        return r[0]
    return None


def _get_tag_type_byte(atr):
    """
    Extract the tag type byte from a PC/SC contactless ATR.
    Searches for the PC/SC RID (A0 00 00 03 06), then reads
    the card type byte 2 positions after: <standard> <name_hi> <name_lo>.
    Returns the name_lo byte, or None if not found.
    """
    rid = [0xA0, 0x00, 0x00, 0x03, 0x06]
    for i in range(len(atr) - len(rid)):
        if atr[i:i + len(rid)] == rid:
            type_idx = i + len(rid) + 2  # skip standard + name_hi
            if type_idx < len(atr):
                return atr[type_idx]
    return None


def connect_to_card(reader):
    """
    Try to connect to a card on the reader.
    Returns (connection, type_byte) on success, or (None, None) on failure.
    type_byte is TAG_TYPE_NTAG, TAG_TYPE_MIFARE_1K, etc. or None if unknown.
    """
    try:
        connection = reader.createConnection()
        connection.connect()
        atr = connection.getATR()
        atr_str = toHexString(atr)
        type_byte = _get_tag_type_byte(atr)
        tag_type = TAG_TYPES.get(type_byte, f"unknown (0x{type_byte:02X})" if type_byte is not None else "unknown")
        log.info("Card detected — ATR: %s — Type: %s", atr_str, tag_type)
        return connection, type_byte
    except (NoCardException, CardConnectionException):
        return None, None


def _reset_rf_field(connection):
    """
    Toggle the ACR122U's RF antenna off and on to force the PN532
    to drop its current target and re-detect cards from scratch.
    Fixes stale state after failed communications (e.g. sending NTAG
    commands to a MIFARE Classic card, or vice versa).
    """
    try:
        # PN532 RFConfiguration: disable RF field
        connection.transmit([0xFF, 0x00, 0x00, 0x00, 0x04, 0xD4, 0x32, 0x01, 0x00])
        time.sleep(0.1)
        # PN532 RFConfiguration: enable RF field
        connection.transmit([0xFF, 0x00, 0x00, 0x00, 0x04, 0xD4, 0x32, 0x01, 0x01])
    except Exception:
        pass


def _ntag_transceive(connection, cmd_bytes):
    """
    Send a native NFC command to the tag via ACR122U direct transmit
    (PN532 InCommunicateThru).  Returns response data bytes on success,
    or None on failure.

    The ACR122U escape APDU is: FF 00 00 00 <Lc> D4 42 <native_cmd>
    PN532 responds with:        D5 43 <status> [data...]
    Status 0x00 = success.
    """
    inner = [0xD4, 0x42] + list(cmd_bytes)
    apdu = [0xFF, 0x00, 0x00, 0x00, len(inner)] + inner
    try:
        data, sw1, sw2 = connection.transmit(apdu)
    except CardConnectionException as e:
        log.error("Transmit error: %s", e)
        return None

    if sw1 != 0x90 or sw2 != 0x00:
        log.error("APDU error: SW=%02X%02X", sw1, sw2)
        return None

    # Expect D5 43 <status> [payload...]
    if len(data) < 3 or data[0] != 0xD5 or data[1] != 0x43:
        log.error("Unexpected PN532 response: %s", toHexString(data))
        return None

    if data[2] != 0x00:
        log.error("Tag command failed, PN532 status: 0x%02X", data[2])
        return None

    return data[3:]  # payload bytes (may be empty for WRITE)


def read_tag_data(connection, max_pages=20) -> str | None:
    """
    Read user data from an NTAG tag (pages 4+).
    Uses the native NTAG READ command (0x30) via PN532 InCommunicateThru.
    Each READ returns 16 bytes (4 pages).
    """
    raw_bytes = []
    page = 4
    end_page = 4 + max_pages

    while page < end_page:
        # NTAG READ: 0x30 <page> — returns 16 bytes (4 pages)
        resp = _ntag_transceive(connection, [0x30, page])
        if resp is None:
            break
        raw_bytes.extend(resp[:16])
        page += 4  # READ returns 4 pages at a time

    if not raw_bytes:
        return None

    # Find the null terminator or end of data
    try:
        end = raw_bytes.index(0x00)
        raw_bytes = raw_bytes[:end]
    except ValueError:
        pass  # no null terminator, use all bytes

    if not raw_bytes:
        return None

    try:
        return bytes(raw_bytes).decode("ascii").strip()
    except (UnicodeDecodeError, ValueError):
        return None


def write_tag_data(connection, payload: str) -> bool:
    """
    Write an ASCII string payload to an NTAG tag starting at page 4.
    Uses the native NTAG WRITE command (0xA2) via PN532 InCommunicateThru.
    Each WRITE sends 4 bytes (1 page).
    """
    data = list(payload.encode("ascii")) + [0x00]

    # Pad to multiple of 4 bytes (NTAG page size)
    while len(data) % 4 != 0:
        data.append(0x00)

    page = 4
    for i in range(0, len(data), 4):
        chunk = data[i:i + 4]
        # NTAG WRITE: 0xA2 <page> <b0> <b1> <b2> <b3>
        resp = _ntag_transceive(connection, [0xA2, page] + chunk)
        if resp is None:
            log.error("Write failed at page %d", page)
            return False
        log.debug("Wrote page %d OK", page)
        page += 1

    return True


# ---------------------------------------------------------------------------
# MIFARE Classic communication
# ---------------------------------------------------------------------------

def _mifare_load_key(connection, key_bytes=MIFARE_DEFAULT_KEY, key_slot=0x00):
    """
    Load an authentication key into the ACR122U key store.
    APDU: FF 82 00 <key_slot> 06 <6-byte key>
    """
    apdu = [0xFF, 0x82, 0x00, key_slot, 0x06] + list(key_bytes)
    try:
        data, sw1, sw2 = connection.transmit(apdu)
    except CardConnectionException as e:
        log.error("Load key transmit error: %s", e)
        return False
    if sw1 != 0x90 or sw2 != 0x00:
        log.error("Load key failed: SW=%02X%02X", sw1, sw2)
        return False
    return True


def _mifare_auth_block(connection, block, key_type=0x60, key_slot=0x00):
    """
    Authenticate a MIFARE Classic block using a loaded key.
    APDU: FF 86 00 00 05 01 00 <block> <key_type> <key_slot>
    key_type: 0x60 = Key A, 0x61 = Key B
    """
    apdu = [0xFF, 0x86, 0x00, 0x00, 0x05,
            0x01, 0x00, block, key_type, key_slot]
    try:
        data, sw1, sw2 = connection.transmit(apdu)
    except CardConnectionException as e:
        log.error("Auth block %d transmit error: %s", block, e)
        return False
    if sw1 != 0x90 or sw2 != 0x00:
        log.error("Auth block %d failed: SW=%02X%02X", block, sw1, sw2)
        return False
    return True


def _mifare_read_block(connection, block):
    """
    Read 16 bytes from a MIFARE Classic block (must be authenticated first).
    APDU: FF B0 00 <block> 10
    Returns 16 bytes on success, None on failure.
    """
    apdu = [0xFF, 0xB0, 0x00, block, 0x10]
    try:
        data, sw1, sw2 = connection.transmit(apdu)
    except CardConnectionException as e:
        log.error("Read block %d transmit error: %s", block, e)
        return None
    if sw1 != 0x90 or sw2 != 0x00:
        log.error("Read block %d failed: SW=%02X%02X", block, sw1, sw2)
        return None
    return list(data)


def _mifare_write_block(connection, block, data_bytes):
    """
    Write 16 bytes to a MIFARE Classic block (must be authenticated first).
    APDU: FF D6 00 <block> 10 <16 bytes>
    data_bytes must be exactly 16 bytes.
    """
    if len(data_bytes) != 16:
        log.error("Write block %d: data must be 16 bytes, got %d", block, len(data_bytes))
        return False
    apdu = [0xFF, 0xD6, 0x00, block, 0x10] + list(data_bytes)
    try:
        data, sw1, sw2 = connection.transmit(apdu)
    except CardConnectionException as e:
        log.error("Write block %d transmit error: %s", block, e)
        return False
    if sw1 != 0x90 or sw2 != 0x00:
        log.error("Write block %d failed: SW=%02X%02X", block, sw1, sw2)
        return False
    return True


def _sector_of_block(block):
    """Return the sector number for a given block (MIFARE Classic 1K)."""
    return block // 4


def read_tag_data_mifare(connection) -> str | None:
    """
    Read user data from a MIFARE Classic tag.
    Reads data blocks across sectors 1-2 (blocks 4,5,6,8,9,10).
    Each block is 16 bytes, giving 96 bytes total.
    """
    if not _mifare_load_key(connection):
        log.error("Failed to load MIFARE key")
        return None

    raw_bytes = []
    last_sector = -1

    for block in MIFARE_DATA_BLOCKS:
        sector = _sector_of_block(block)
        if sector != last_sector:
            if not _mifare_auth_block(connection, block):
                log.error("Failed to authenticate sector %d (block %d)", sector, block)
                return None
            last_sector = sector

        data = _mifare_read_block(connection, block)
        if data is None:
            log.error("Failed to read block %d", block)
            return None
        raw_bytes.extend(data)

    if not raw_bytes:
        return None

    # Find the null terminator
    try:
        end = raw_bytes.index(0x00)
        raw_bytes = raw_bytes[:end]
    except ValueError:
        pass

    if not raw_bytes:
        return None

    try:
        return bytes(raw_bytes).decode("ascii").strip()
    except (UnicodeDecodeError, ValueError):
        return None


def write_tag_data_mifare(connection, payload: str) -> bool:
    """
    Write an ASCII string payload to a MIFARE Classic tag.
    Writes across data blocks in sectors 1-2 (blocks 4,5,6,8,9,10).
    Max payload: 95 bytes (96 bytes minus null terminator).
    """
    data = list(payload.encode("ascii")) + [0x00]

    max_capacity = len(MIFARE_DATA_BLOCKS) * 16
    if len(data) > max_capacity:
        log.error("Payload too large for MIFARE Classic: %d bytes (max %d)",
                  len(data), max_capacity)
        return False

    # Pad to fill remaining blocks with zeros
    while len(data) % 16 != 0:
        data.append(0x00)

    if not _mifare_load_key(connection):
        log.error("Failed to load MIFARE key")
        return False

    last_sector = -1
    block_idx = 0

    for i in range(0, len(data), 16):
        if block_idx >= len(MIFARE_DATA_BLOCKS):
            break
        block = MIFARE_DATA_BLOCKS[block_idx]
        sector = _sector_of_block(block)

        if sector != last_sector:
            if not _mifare_auth_block(connection, block):
                log.error("Failed to authenticate sector %d (block %d)", sector, block)
                return False
            last_sector = sector

        chunk = data[i:i + 16]
        if not _mifare_write_block(connection, block, chunk):
            log.error("Write failed at block %d", block)
            return False
        log.debug("Wrote block %d OK", block)
        block_idx += 1

    return True


# ---------------------------------------------------------------------------
# Bridge state
# ---------------------------------------------------------------------------

class BridgeState:
    def __init__(self):
        self.clients: set[websockets.WebSocketServerProtocol] = set()
        self.write_pending: str | None = None   # student_id to write, or None
        self.last_scan_id: str | None = None
        self.last_scan_time: float = 0
        self.reader_connected: bool = False
        self.last_error_event: str | None = None
        self.last_error_time: float = 0

    def should_debounce(self, student_id: str) -> bool:
        now = time.time()
        if student_id == self.last_scan_id and (now - self.last_scan_time) < config.DEBOUNCE_SECONDS:
            return True
        self.last_scan_id = student_id
        self.last_scan_time = now
        return False

    def should_debounce_error(self, event: str) -> bool:
        """Suppress repeated error events from the same card sitting on the reader."""
        now = time.time()
        if event == self.last_error_event and (now - self.last_error_time) < config.DEBOUNCE_SECONDS:
            return True
        self.last_error_event = event
        self.last_error_time = now
        return False

    def clear_error(self):
        """Reset error debounce when a successful event occurs."""
        self.last_error_event = None


state = BridgeState()
executor = ThreadPoolExecutor(max_workers=1)


# ---------------------------------------------------------------------------
# WebSocket server
# ---------------------------------------------------------------------------

async def broadcast(message: dict):
    """Send a JSON message to all connected browser clients."""
    if not state.clients:
        return
    text = json.dumps(message)
    disconnected = set()
    for ws in state.clients:
        try:
            await ws.send(text)
        except websockets.ConnectionClosed:
            disconnected.add(ws)
    state.clients -= disconnected


async def handle_client(websocket):
    """Handle a single browser WebSocket connection."""
    state.clients.add(websocket)
    log.info("Browser connected (%d total)", len(state.clients))

    # Send current reader status
    await websocket.send(json.dumps({
        "type": "reader_status",
        "connected": state.reader_connected
    }))

    try:
        async for raw in websocket:
            try:
                msg = json.loads(raw)
            except json.JSONDecodeError:
                continue

            msg_type = msg.get("type")

            if msg_type == "write_tag":
                student_id = msg.get("student_id")
                if student_id:
                    state.write_pending = student_id
                    log.info("Write requested for student %s", student_id)

            elif msg_type == "cancel_write":
                state.write_pending = None
                log.info("Write cancelled")

    except websockets.ConnectionClosed:
        pass
    finally:
        state.clients.discard(websocket)
        log.info("Browser disconnected (%d remaining)", len(state.clients))


# ---------------------------------------------------------------------------
# NFC polling loop
# ---------------------------------------------------------------------------

def _is_mifare_classic(type_byte):
    """Check if the tag type is MIFARE Classic (1K or 4K)."""
    return type_byte in (TAG_TYPE_MIFARE_1K, TAG_TYPE_MIFARE_4K)


def poll_nfc_blocking():
    """
    Blocking function that runs one poll cycle.
    Returns a dict describing what happened, or None.
    """
    reader = get_reader()
    if reader is None:
        return {"event": "no_reader"}

    connection, type_byte = connect_to_card(reader)
    if connection is None:
        return {"event": "no_card"}

    is_mifare = _is_mifare_classic(type_byte)
    is_ntag = (type_byte == TAG_TYPE_NTAG)

    try:
        # --- Unsupported tag type ---
        if not is_mifare and not is_ntag:
            tag_name = TAG_TYPES.get(type_byte, "unknown")
            _reset_rf_field(connection)
            return {"event": "unsupported_tag", "tag_type": tag_name}

        # --- Write mode ---
        if state.write_pending is not None:
            student_id = state.write_pending
            payload = make_payload(student_id)
            if is_mifare:
                success = write_tag_data_mifare(connection, payload)
            else:
                success = write_tag_data(connection, payload)
            state.write_pending = None
            if not success:
                _reset_rf_field(connection)
            return {
                "event": "write_result",
                "success": success,
                "student_id": student_id
            }

        # --- Read mode ---
        if is_mifare:
            raw = read_tag_data_mifare(connection)
        else:
            raw = read_tag_data(connection)

        if raw is None:
            _reset_rf_field(connection)
            return {"event": "empty_tag"}

        student_id = verify_payload(raw)
        if student_id is None:
            return {"event": "invalid_tag", "raw": raw}

        return {"event": "valid_tag", "student_id": student_id}

    finally:
        try:
            connection.disconnect()
        except Exception:
            pass


async def nfc_poll_loop():
    """Async loop that polls the NFC reader and broadcasts events."""
    loop = asyncio.get_event_loop()
    was_connected = None

    while True:
        try:
            result = await loop.run_in_executor(executor, poll_nfc_blocking)
        except Exception as e:
            log.error("NFC poll error: %s", e)
            await asyncio.sleep(config.POLL_INTERVAL)
            continue

        if result is None:
            await asyncio.sleep(config.POLL_INTERVAL)
            continue

        event = result["event"]

        # Track reader connection status
        is_connected = event != "no_reader"
        if is_connected != was_connected:
            was_connected = is_connected
            state.reader_connected = is_connected
            await broadcast({"type": "reader_status", "connected": is_connected})
            if is_connected:
                log.info("NFC reader connected")
            else:
                log.warning("NFC reader not found")

        # Handle events
        if event == "write_result":
            state.clear_error()
            await broadcast({
                "type": "write_complete",
                "success": result["success"],
                "student_id": result.get("student_id", "")
            })
            if result["success"]:
                log.info("Tag written for student %s", result["student_id"])
            else:
                log.error("Tag write failed for student %s", result["student_id"])

        elif event == "valid_tag":
            state.clear_error()
            student_id = result["student_id"]
            if not state.should_debounce(student_id):
                await broadcast({"type": "tag_scan", "student_id": student_id})
                log.info("Tag scanned: student %s", student_id)

        elif event == "invalid_tag":
            if not state.should_debounce_error("invalid_tag"):
                await broadcast({"type": "error", "message": "Invalid or unsigned tag"})
                log.warning("Invalid tag data: %s", result.get("raw", ""))

        elif event == "unsupported_tag":
            if not state.should_debounce_error("unsupported_tag"):
                await broadcast({"type": "error", "message": f"Unsupported tag type: {result.get('tag_type', 'unknown')}"})
                log.warning("Unsupported tag type: %s", result.get("tag_type", "unknown"))

        elif event == "empty_tag":
            if not state.should_debounce_error("empty_tag"):
                log.debug("Empty or unreadable tag")

        await asyncio.sleep(config.POLL_INTERVAL)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

async def main():
    log.info("Starting NFC Bridge on ws://%s:%d", config.WS_HOST, config.WS_PORT)

    async with websockets.serve(handle_client, config.WS_HOST, config.WS_PORT):
        await nfc_poll_loop()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        log.info("Shutting down")
