# NFC Bridge Configuration

# HMAC secret key for signing tag payloads.
# Change this to a unique random string for your deployment.
# All tags written with one secret will not work if the secret is changed.
HMAC_SECRET = "change-me-to-a-random-secret-key"

# WebSocket server settings
WS_HOST = "localhost"
WS_PORT = 8765

# Seconds to ignore repeated reads of the same tag (prevents rapid-fire scans)
DEBOUNCE_SECONDS = 3

# NFC polling interval in seconds
POLL_INTERVAL = 0.5
