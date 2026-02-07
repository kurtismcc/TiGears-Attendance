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
# Low-level ACR122U / NTAG communication
# ---------------------------------------------------------------------------

def get_reader():
    """Return the first available PC/SC reader, or None."""
    r = readers()
    if r:
        return r[0]
    return None


def connect_to_card(reader):
    """Try to connect to a card on the reader. Returns connection or None."""
    try:
        connection = reader.createConnection()
        connection.connect()
        return connection
    except (NoCardException, CardConnectionException):
        return None


def read_tag_data(connection, max_pages=20) -> str | None:
    """
    Read user data from an NTAG tag (pages 4+).
    Returns the decoded text payload, or None on failure.
    """
    raw_bytes = []
    for page in range(4, 4 + max_pages):
        # READ command: FF B0 00 <page> 04 (read 4 bytes from page)
        apdu = [0xFF, 0xB0, 0x00, page, 0x04]
        try:
            data, sw1, sw2 = connection.transmit(apdu)
        except CardConnectionException:
            break
        if sw1 != 0x90 or sw2 != 0x00:
            break
        raw_bytes.extend(data)

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
    Appends a null terminator. Returns True on success.
    """
    data = list(payload.encode("ascii")) + [0x00]

    # Pad to multiple of 4 bytes (NTAG page size)
    while len(data) % 4 != 0:
        data.append(0x00)

    page = 4
    for i in range(0, len(data), 4):
        chunk = data[i:i + 4]
        # WRITE command: FF D6 00 <page> 04 <4 bytes>
        apdu = [0xFF, 0xD6, 0x00, page, 0x04] + chunk
        try:
            resp, sw1, sw2 = connection.transmit(apdu)
        except CardConnectionException:
            log.error("Card connection lost during write at page %d", page)
            return False
        if sw1 != 0x90 or sw2 != 0x00:
            log.error("Write failed at page %d: SW=%02X%02X", page, sw1, sw2)
            return False
        page += 1

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

    def should_debounce(self, student_id: str) -> bool:
        now = time.time()
        if student_id == self.last_scan_id and (now - self.last_scan_time) < config.DEBOUNCE_SECONDS:
            return True
        self.last_scan_id = student_id
        self.last_scan_time = now
        return False


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

def poll_nfc_blocking():
    """
    Blocking function that runs one poll cycle.
    Returns a dict describing what happened, or None.
    """
    reader = get_reader()
    if reader is None:
        return {"event": "no_reader"}

    connection = connect_to_card(reader)
    if connection is None:
        return {"event": "no_card"}

    # --- Write mode ---
    if state.write_pending is not None:
        student_id = state.write_pending
        payload = make_payload(student_id)
        success = write_tag_data(connection, payload)
        state.write_pending = None
        try:
            connection.disconnect()
        except Exception:
            pass
        return {
            "event": "write_result",
            "success": success,
            "student_id": student_id
        }

    # --- Read mode ---
    raw = read_tag_data(connection)
    try:
        connection.disconnect()
    except Exception:
        pass

    if raw is None:
        return {"event": "empty_tag"}

    student_id = verify_payload(raw)
    if student_id is None:
        return {"event": "invalid_tag", "raw": raw}

    return {"event": "valid_tag", "student_id": student_id}


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
            student_id = result["student_id"]
            if not state.should_debounce(student_id):
                await broadcast({"type": "tag_scan", "student_id": student_id})
                log.info("Tag scanned: student %s", student_id)

        elif event == "invalid_tag":
            await broadcast({"type": "error", "message": "Invalid or unsigned tag"})
            log.warning("Invalid tag data: %s", result.get("raw", ""))

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
