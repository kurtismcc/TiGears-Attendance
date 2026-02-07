# TiGears Robotics Attendance System

A touch-friendly attendance tracking system for robotics teams, built with PHP and MySQL. Features configurable attendance windows, automatic award tracking, and a kiosk-optimized interface.

## Features

- **Touch-optimized interface** for kiosk/tablet use with large buttons and numeric keypad
- **Student ID verification** - students enter their ID to confirm identity before signing in/out
- **NFC tag support** (optional) - students tap an NFC tag on an ACR122U reader for instant sign-in/out, with HMAC-signed payloads to prevent cloning
- **Configurable attendance windows** - define meeting days and times (e.g., Tue/Thu 2-4pm, Sat 9am-1pm)
- **Automatic award tracking** with three leaderboards:
  - **Consecutive Meetings** - longest streak of attended windows
  - **Attendance Score** - weighted points (on-time = 3pts, late = 2pts)
  - **Total Time** - cumulative hours during attendance windows
- **Grace period** for on-time arrivals (configurable, default 5 minutes)
- **Auto-refresh** every 5 minutes to update awards
- **Admin pages** for managing attendance windows and editing student records

## How Awards Work

Awards are calculated from a **transformed data structure** that:
- Generates all valid window occurrences between the earliest and latest attendance records
- Transforms raw sign-in/sign-out data into per-window attendance records
- Automatically signs out students at window end if they forgot to sign out
- Ignores sign-ins that carried over from the previous day
- Only counts completed windows (not in-progress ones)

This ensures accurate metrics even when students forget to sign out.

## Setup

See [docs/Setup.md](docs/Setup.md) for detailed installation instructions.

### Quick Start

1. Install XAMPP or similar (Apache + MySQL + PHP)
2. Copy files to your web root (e.g., `C:\xampp\htdocs\attendance\`)
3. Run `backend/schema.sql` to create the database
4. Edit `backend/config.php` with your database credentials
5. Access `http://localhost/attendance/frontend/`

## File Structure

```
├── backend/
│   ├── config.php           # Database credentials, timezone, grace period
│   ├── db.php               # Database connection
│   ├── schema.sql           # Database schema
│   ├── attendance.php       # Sign-in/sign-out API endpoint
│   ├── attendance_admin.php # CRUD API for attendance records
│   ├── windows.php          # CRUD API for attendance windows
│   ├── status_helper.php    # On-time/late status calculation
│   └── delete_pre_2026.sql  # Migration script to purge old data
│
├── frontend/
│   ├── index.php            # Main attendance page
│   ├── admin.php            # Manage attendance windows
│   ├── admin_student.php    # Edit student attendance records
│   ├── awards.php           # Award calculation functions
│   ├── window_transform.php # Window-based data transformation
│   ├── script.js            # Frontend JavaScript (+ NFC WebSocket client)
│   ├── style.css            # Touch-optimized styling
│   └── assets/              # Logo and background images
│
├── nfc_bridge/              # Optional: ACR122U NFC reader support
│   ├── nfc_bridge.py        # Python bridge service (WebSocket + PC/SC)
│   ├── config.py            # HMAC secret and WebSocket settings
│   ├── requirements.txt     # Python dependencies (pyscard, websockets)
│   └── start_bridge.bat     # Launcher script for Windows
│
└── docs/
    ├── Setup.md             # Installation guide (includes NFC setup)
    └── AddAwards.md         # Guide to adding new awards
```

## Database Schema

### Tables

- **students** - `student_id`, `name`, `created_at`
- **attendance_log** - `id`, `student_id`, `timestamp`, `action` (in/out)
- **attendance_windows** - `id`, `day_of_week` (0-6), `start_time`, `end_time`

## Usage

### For Students (Keypad)
1. Find your name in the roster
2. Tap your name
3. Enter your student ID on the keypad
4. Tap Confirm to sign in/out

### For Students (NFC Tag)
1. Tap your NFC tag on the ACR122U reader
2. You're signed in/out automatically — no keypad needed

### Writing NFC Tags
1. Tap your name in the roster
2. Enter your student ID on the keypad
3. Tap **"Write to Tag"** (appears when ID is correct and NFC reader is connected)
4. Hold a blank NFC tag against the reader

### For Admins
- Click the right logo to access the admin page
- **Manage Windows**: Add/remove attendance windows (days and times)
- **Edit Records**: View and modify individual student attendance records

## Configuration

Edit `backend/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'robotics_attendance');

date_default_timezone_set('America/Los_Angeles');

define('GRACE_PERIOD_MINUTES', 5);  // Minutes after window start to count as on-time
```

## Adding Students

```sql
INSERT INTO students (student_id, name) VALUES ('12345', 'Jane Doe');
```

Or import test data from `backend/student_data.sql` if available.
