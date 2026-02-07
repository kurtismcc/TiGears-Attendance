@echo off
echo Starting NFC Bridge Service...
echo Press Ctrl+C to stop.
echo.
cd /d "%~dp0"
python nfc_bridge.py
pause
