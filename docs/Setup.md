# Setup Guide: MySQL and PHP on Windows

This guide will walk you through setting up MySQL and PHP on a Windows machine to run the Robotics Team Attendance System.

## Overview

We'll be using XAMPP, which is an easy-to-install Apache distribution containing MySQL and PHP. It's perfect for running this attendance system on a dedicated Windows machine.

---

## Step 1: Download and Install XAMPP

### Download XAMPP

1. Open your web browser and go to: https://www.apachefriends.org/
2. Click the **"Download"** button for Windows
3. Download the latest version (PHP 8.x recommended)
4. The file will be named something like `xampp-windows-x64-8.x.x-installer.exe`

### Install XAMPP

1. **Run the installer** (you may need administrator privileges)
2. If Windows Defender or antivirus warns you, click **"Yes"** or **"Allow"**
3. On the component selection screen, make sure these are checked:
   - ✓ Apache
   - ✓ MySQL
   - ✓ PHP
   - ✓ phpMyAdmin (optional but recommended for easy database management)
4. Choose installation directory (default is `C:\xampp`) - **Remember this location!**
5. Click **"Next"** through the remaining screens
6. Uncheck **"Learn more about Bitnami"** (not needed)
7. Click **"Finish"** to complete installation

---

## Step 2: Start MySQL and Apache

### Using XAMPP Control Panel

1. Open **XAMPP Control Panel** from your Start Menu or desktop
2. You should see a list of services (Apache, MySQL, FileZilla, etc.)
3. Click the **"Start"** button next to **Apache** - it should turn green
4. Click the **"Start"** button next to **MySQL** - it should turn green
5. If Windows Firewall prompts you, click **"Allow access"** for both services

### Making Services Start Automatically

To have Apache and MySQL start when Windows boots:

1. In XAMPP Control Panel, click the **"Config"** button (top right)
2. Check both:
   - ✓ Apache
   - ✓ MySQL
3. Click **"Save"**

Alternatively, you can install them as Windows services:
1. In XAMPP Control Panel, click the **"X"** button next to Apache
2. Click **"Yes"** when prompted to install as service
3. Repeat for MySQL

---

## Step 3: Set Up the Database

### Option A: Using Command Line

1. Open **Command Prompt** (Windows key + R, type `cmd`, press Enter)
2. Navigate to your project directory:
   ```cmd
   cd d:\VHSTigears\Attendance
   ```
3. Run the MySQL command line client:
   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root -p
   ```
4. When prompted for password, just press **Enter** (default is no password)
5. You should see `mysql>` prompt
6. Run the schema file:
   ```sql
   source backend/schema.sql
   ```
7. Verify the database was created:
   ```sql
   USE robotics_attendance;
   SHOW TABLES;
   SELECT * FROM students;
   ```
8. You should see the 5 sample students
9. Type `exit` to quit MySQL

### Option B: Using phpMyAdmin (GUI Method)

1. Open your web browser
2. Go to: http://localhost/phpmyadmin
3. Click **"Import"** tab at the top
4. Click **"Choose File"** button
5. Navigate to `d:\VHSTigears\Attendance\backend\schema.sql`
6. Click **"Go"** button at the bottom
7. You should see "Import has been successfully finished"
8. Click on **"robotics_attendance"** database on the left to verify it was created
9. Click on **"students"** table to see the sample data

---

## Step 4: Import Test Data (Optional)

The project includes test data with 35 Middle Earth characters to help you test the system before adding your real students.

### What's Included

- **File**: `backend/test_data.sql`
- **Contents**: 35 characters from J.R.R. Tolkien's Middle Earth with 8-digit random student IDs
- **Reference**: `backend/TestData.csv` contains the same data for easy ID lookup

The test data includes characters like Frodo Baggins, Gandalf the Grey, Gandalf the White, Aragorn, Legolas, and many more.

### Option A: Using Command Line

1. Open **Command Prompt** (Windows key + R, type `cmd`, press Enter)
2. Navigate to your project directory:
   ```cmd
   cd d:\VHSTigears\Attendance
   ```
3. Run the MySQL command line client:
   ```cmd
   C:\xampp\mysql\bin\mysql.exe -u root -p
   ```
4. When prompted for password, just press **Enter** (default is no password)
5. Select the database:
   ```sql
   USE robotics_attendance;
   ```
6. Run the test data file:
   ```sql
   source backend/test_data.sql
   ```
7. Verify the test data was imported:
   ```sql
   SELECT COUNT(*) FROM students;
   SELECT * FROM students LIMIT 5;
   ```
8. You should see 35 students, and the first few names should be Middle Earth characters
9. Type `exit` to quit MySQL

### Option B: Using phpMyAdmin (GUI Method)

1. Open your web browser
2. Go to: http://localhost/phpmyadmin
3. Click on **"robotics_attendance"** database on the left
4. Click the **"SQL"** tab at the top
5. Click **"Choose File"** or use the "Import files" section
6. Navigate to `d:\VHSTigears\Attendance\backend\test_data.sql`
7. Click **"Go"** button at the bottom
8. You should see a success message
9. Click on **"students"** table on the left
10. Click **"Browse"** tab to see all 35 Middle Earth characters

### Looking Up Student IDs

When testing with the test data, you'll need to know the student IDs. You can:

1. **Check the CSV file**: Open `backend/TestData.csv` in Excel, Notepad, or any text editor
2. **Query in phpMyAdmin**: Browse the students table to see names and IDs
3. **Print it out**: Print the CSV file and keep it near the kiosk for testing

**Note**: When you're ready to use the system with real students, you can clear this test data by running:
```sql
TRUNCATE TABLE attendance_log;
DELETE FROM students;
```
Then follow the instructions in "Adding Real Students" section below.

---

## Step 5: Configure the Application

### Update Database Configuration

1. Open File Explorer and navigate to `d:\VHSTigears\Attendance\backend\`
2. Right-click on `config.php` and open with Notepad or your preferred text editor
3. Update the database credentials (the defaults should work for XAMPP):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');      // Leave empty for XAMPP default
   define('DB_NAME', 'robotics_attendance');
   ```
4. Update the timezone if needed (find your timezone at https://www.php.net/manual/en/timezones.america.php):
   ```php
   date_default_timezone_set('America/New_York');  // Change to your timezone
   ```
5. Save and close the file

---

## Step 6: Deploy Project Files to Web Server

### Using the Deployment Script

The project includes a deployment script that makes it easy to copy files to your web server.

1. Open **Command Prompt** (Windows key + R, type `cmd`, press Enter)
2. Navigate to your project directory:
   ```cmd
   cd d:\VHSTigears\Attendance
   ```
3. Run the deployment script with your XAMPP htdocs path:
   ```cmd
   tools\deploy.bat C:\xampp\htdocs\attendance
   ```
4. The script will:
   - Create the destination directory if it doesn't exist (prompts for confirmation)
   - Copy `frontend\` and `backend\` directories to the destination
   - Display a success message when complete

**Example output:**
```
========================================
Robotics Attendance Deployment Script
========================================

Source: d:\VHSTigears\Attendance
Destination: C:\xampp\htdocs\attendance

Copying files...

[1/2] Copying frontend files...
   - frontend files copied successfully
[2/2] Copying backend files...
   - backend files copied successfully

========================================
Deployment completed successfully!
========================================
```

**Important Note:** Whenever you make changes to files in `d:\VHSTigears\Attendance\`, run the deploy script again to update the web server files. The script will overwrite old files with the updated versions.

---

## Step 7: Test the Application

### Access the Application

1. Open your web browser
2. Go to: http://localhost/attendance/frontend/
3. You should see the attendance system with 5 sample student names
4. Try clicking a student name
5. Click **"Sign In"**
6. You should see a success message

### Verify Database is Recording Attendance

Using phpMyAdmin:
1. Go to http://localhost/phpmyadmin
2. Click **"robotics_attendance"** database on the left
3. Click **"attendance_log"** table
4. Click **"Browse"** tab
5. You should see your test sign-in record with a timestamp

---

## Step 8: Configure for Touchscreen Kiosk

### Set Browser to Start on Boot

1. Press **Windows + R**, type `shell:startup`, press **Enter**
2. This opens your Startup folder
3. Right-click and create a new shortcut
4. Enter this as the location:
   ```
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk http://localhost/attendance/frontend/
   ```
   (Adjust the path if Chrome is installed elsewhere, or use Edge/Firefox)
5. Name the shortcut **"Attendance Kiosk"**
6. Click **"Finish"**

### Enable Touch Keyboard (if using touch screen without physical keyboard)

1. Right-click on the taskbar
2. Select **"Show touch keyboard button"**
3. A keyboard icon will appear in the system tray for when text input is needed

### Disable Sleep Mode

1. Open **Settings** (Windows key + I)
2. Go to **System** > **Power & sleep**
3. Set both **"Screen"** and **"Sleep"** to **"Never"**

---

## Troubleshooting

### Apache Won't Start
- **Error: Port 80 is already in use**
  - Another program (often Skype or IIS) is using port 80
  - Solution 1: Close the conflicting program
  - Solution 2: Change Apache's port:
    1. In XAMPP Control Panel, click **"Config"** button next to Apache
    2. Select **"httpd.conf"**
    3. Find `Listen 80` and change to `Listen 8080`
    4. Save and restart Apache
    5. Access site at http://localhost:8080/attendance/frontend/

### MySQL Won't Start
- **Error: Port 3306 is already in use**
  - Another MySQL instance or service is running
  - Solution: Open Windows Services (services.msc) and stop any MySQL services

### "Connection failed" Error
- Check that MySQL is running in XAMPP Control Panel
- Verify database credentials in `backend/config.php`
- Make sure the database was created (check phpMyAdmin)

### "No students found" Message
- The database wasn't created properly
- Re-run the schema.sql file (see Step 3)

### Page Shows PHP Code Instead of Running
- Apache is not running - check XAMPP Control Panel
- Files are not in the correct directory (`C:\xampp\htdocs\attendance\`)
- You're opening the file directly (file:// URL) instead of through the web server (http://localhost/)

### Attendance Not Recording
- Open browser's Developer Console (F12) and check for JavaScript errors
- Verify `backend/attendance.php` is accessible at http://localhost/attendance/backend/attendance.php
- Check that the database connection is working

---

## Adding Real Students

Once you've verified everything is working, you'll want to replace the sample students with your real team members.

### Remove Sample Students

1. Go to http://localhost/phpmyadmin
2. Select **"robotics_attendance"** database
3. Click **"students"** table
4. Click **"Browse"** tab
5. Check **"Check all"** at the bottom
6. Select **"Delete"** from the dropdown
7. Click **"Yes"** to confirm

### Add Your Students

Method 1: Using phpMyAdmin
1. Click **"Insert"** tab
2. Fill in the form:
   - **student_id**: Enter the student's ID (e.g., "2001")
   - **name**: Enter the student's full name (e.g., "Sarah Johnson")
3. Click **"Go"**
4. Repeat for each student

Method 2: Using SQL
1. Click **"SQL"** tab
2. Enter commands like:
   ```sql
   INSERT INTO students (student_id, name) VALUES
   ('2001', 'Sarah Johnson'),
   ('2002', 'Mike Chen'),
   ('2003', 'Emily Rodriguez');
   ```
3. Click **"Go"**

---

## Security Notes

**For a dedicated internal machine, the default XAMPP security is acceptable**, but if you want to add basic security:

### Set MySQL Root Password

1. Go to http://localhost/phpmyadmin
2. Click **"User accounts"** tab
3. Click **"Edit privileges"** for user **"root"**
4. Click **"Change password"**
5. Select **"Password"** radio button
6. Enter and confirm a password
7. Click **"Go"**
8. Update `backend/config.php` with the new password

### Restrict phpMyAdmin Access

1. Open `C:\xampp\phpMyAdmin\config.inc.php`
2. Find `$cfg['Servers'][$i]['auth_type'] = 'config';`
3. Change to `$cfg['Servers'][$i]['auth_type'] = 'cookie';`
4. Save the file
5. Now phpMyAdmin will require login

---

## Step 9: Set Up NFC Reader (Optional)

If you have an ACR122U USB NFC reader, you can let students tap NFC tags to sign in/out instantly instead of typing their ID on the keypad.

### Install Python

The NFC bridge service requires Python 3.10 or newer.

1. Go to: https://www.python.org/downloads/
2. Download the latest **Python 3.x** installer for Windows
3. **Important:** On the first installer screen, check **"Add python.exe to PATH"**
4. Click **"Install Now"**
5. After installation, verify by opening Command Prompt and running:
   ```cmd
   python --version
   ```
   You should see something like `Python 3.12.x`

### Install Python Dependencies

1. Open **Command Prompt**
2. Navigate to the project:
   ```cmd
   cd d:\VHSTigears\Attendance\nfc_bridge
   ```
3. Install the required packages:
   ```cmd
   pip install -r requirements.txt
   ```
   This installs `pyscard` (smart card communication) and `websockets` (WebSocket server).

**If `pyscard` fails to install**, you may need the Microsoft Visual C++ Build Tools:
1. Go to: https://visualstudio.microsoft.com/visual-cpp-build-tools/
2. Download and install **Build Tools for Visual Studio**
3. In the installer, select **"Desktop development with C++"**
4. After installation, try `pip install pyscard` again

### Configure the NFC Bridge

1. Open `d:\VHSTigears\Attendance\nfc_bridge\config.py` in a text editor
2. **Change the HMAC secret** to a random string of your choice:
   ```python
   HMAC_SECRET = "your-unique-random-secret-here"
   ```
   This secret signs the data written to NFC tags. Pick something long and random.
   **Warning:** If you change the secret later, all previously written tags will stop working.
3. The other settings can be left at their defaults

### Test the NFC Bridge

1. Plug in the ACR122U reader via USB
2. Open **Command Prompt** and run:
   ```cmd
   cd d:\VHSTigears\Attendance\nfc_bridge
   python nfc_bridge.py
   ```
3. You should see:
   ```
   Starting NFC Bridge on ws://localhost:8765
   NFC reader connected
   ```
4. Open the attendance page in your browser — the small dot in the top-right of the header should turn **green**
5. Press **Ctrl+C** in the Command Prompt to stop the bridge

### Writing NFC Tags

Once the bridge is running:

1. Tap a student's name on the attendance page
2. Enter their student ID on the keypad
3. A **"Write to Tag"** button appears next to Confirm
4. Click **"Write to Tag"**
5. Hold a blank NFC tag (NTAG213/215/216) against the ACR122U reader
6. You'll see a success message when the tag is written

After writing, students can simply tap their tag on the reader to sign in/out — no keypad needed.

### Auto-Start the NFC Bridge with XAMPP

To have the NFC bridge start automatically when XAMPP starts:

#### Method A: Add to Windows Startup (Simplest)

1. Press **Windows + R**, type `shell:startup`, press **Enter**
2. Copy `d:\VHSTigears\Attendance\nfc_bridge\start_bridge.bat` into this folder
3. The bridge will now start automatically when Windows boots (alongside XAMPP if you followed Step 2)

#### Method B: Modify the XAMPP Start Script

1. Open **Notepad as Administrator** (right-click Notepad → Run as administrator)
2. Open the file `C:\xampp\xampp_start.exe` — if that doesn't work, try `C:\xampp\xampp_control.exe`'s config
3. Instead, create a combined start script. Create a file `C:\xampp\start_all.bat`:
   ```batch
   @echo off
   echo Starting XAMPP services and NFC Bridge...

   REM Start Apache and MySQL via XAMPP
   start "" "C:\xampp\xampp_start.exe"

   REM Start NFC Bridge (in a minimized window)
   start /min "" "d:\VHSTigears\Attendance\nfc_bridge\start_bridge.bat"

   echo All services started.
   ```
4. Replace the startup shortcut or scheduled task to use `C:\xampp\start_all.bat` instead of `xampp_start.exe`

#### Method C: Install as a Windows Service (Advanced)

Using NSSM (Non-Sucking Service Manager):

1. Download NSSM from: https://nssm.cc/download
2. Extract and open Command Prompt as Administrator
3. Run:
   ```cmd
   nssm install NFCBridge
   ```
4. In the dialog that appears:
   - **Path**: `C:\Python312\python.exe` (adjust to your Python path — run `where python` to find it)
   - **Startup directory**: `d:\VHSTigears\Attendance\nfc_bridge`
   - **Arguments**: `nfc_bridge.py`
5. Click **"Install service"**
6. Start the service:
   ```cmd
   nssm start NFCBridge
   ```
7. The bridge will now auto-start with Windows and restart if it crashes

### NFC Troubleshooting

#### Green dot doesn't appear in header
- The NFC bridge is not running. Start it with `start_bridge.bat`
- Check the Command Prompt window for error messages

#### "NFC reader not found" in the bridge console
- Make sure the ACR122U is plugged in via USB
- Check Device Manager → Smart card readers — the ACR122U should appear
- Try unplugging and replugging the reader
- Install the ACR122U driver from: https://www.acs.com.hk/en/driver/3/acr122u-usb-nfc-reader/

#### Tags not reading / "Invalid or unsigned tag"
- The tag may have been written with a different HMAC secret
- The tag may be blank — it needs to be written first using the "Write to Tag" flow
- Try a different NFC tag — NTAG213, 215, or 216 are supported

#### "Failed to write tag"
- Make sure the tag is held flat against the reader and not moving
- The tag may be write-protected or damaged — try a different tag
- NTAG213 supports payloads up to ~144 bytes, which is sufficient for student IDs

#### `pyscard` import error
- Ensure the **PC/SC Smart Card Service** is running:
  1. Press **Windows + R**, type `services.msc`, press **Enter**
  2. Find **"Smart Card"** service
  3. Right-click → **Start** (and set Startup type to **Automatic**)

---

## Next Steps

- Test the system with a few students
- Add all team members to the database
- Set up the touchscreen kiosk in your lab/workshop
- (Optional) Set up NFC tags for faster sign-in/out

## Support

If you run into issues not covered here, check:
- XAMPP Documentation: https://www.apachefriends.org/faq_windows.html
- Project README.md for general information
- Error logs in `C:\xampp\apache\logs\error.log`
