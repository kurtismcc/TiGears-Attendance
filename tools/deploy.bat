@echo off
REM Deployment script for Robotics Team Attendance System
REM Usage: deploy.bat <destination_path>
REM Example: deploy.bat C:\xampp\htdocs\attendance

setlocal

REM Check if destination path is provided
if "%~1"=="" (
    echo ERROR: No destination path provided
    echo.
    echo Usage: deploy.bat ^<destination_path^>
    echo Example: deploy.bat C:\xampp\htdocs\attendance
    echo.
    exit /b 1
)

set DEST_PATH=%~1
set SCRIPT_DIR=%~dp0
set PROJECT_ROOT=%SCRIPT_DIR%..

echo ========================================
echo Robotics Attendance Deployment Script
echo ========================================
echo.
echo Source: %PROJECT_ROOT%
echo Destination: %DEST_PATH%
echo.

REM Check if destination exists
if not exist "%DEST_PATH%" (
    echo Destination directory does not exist.
    set /p CREATE="Create it? (Y/N): "
    if /i "!CREATE!"=="Y" (
        mkdir "%DEST_PATH%"
        if errorlevel 1 (
            echo ERROR: Failed to create destination directory
            exit /b 1
        )
        echo Created: %DEST_PATH%
    ) else (
        echo Deployment cancelled.
        exit /b 1
    )
)

echo.
echo Copying files...
echo.

REM Copy frontend directory
echo [1/3] Copying frontend files...
if exist "%DEST_PATH%\frontend" (
    rmdir /s /q "%DEST_PATH%\frontend"
)
xcopy "%PROJECT_ROOT%\frontend" "%DEST_PATH%\frontend\" /E /I /Y /Q
if errorlevel 1 (
    echo ERROR: Failed to copy frontend files
    exit /b 1
)
echo    - frontend files copied successfully

REM Copy backend directory
echo [2/3] Copying backend files...
if exist "%DEST_PATH%\backend" (
    rmdir /s /q "%DEST_PATH%\backend"
)
xcopy "%PROJECT_ROOT%\backend" "%DEST_PATH%\backend\" /E /I /Y /Q
if errorlevel 1 (
    echo ERROR: Failed to copy backend files
    exit /b 1
)
echo    - backend files copied successfully

REM Copy README if it exists
echo [3/3] Copying documentation...
if exist "%PROJECT_ROOT%\README.md" (
    copy "%PROJECT_ROOT%\README.md" "%DEST_PATH%\" /Y >nul
    echo    - README.md copied
)
if exist "%PROJECT_ROOT%\docs" (
    if exist "%DEST_PATH%\docs" (
        rmdir /s /q "%DEST_PATH%\docs"
    )
    xcopy "%PROJECT_ROOT%\docs" "%DEST_PATH%\docs\" /E /I /Y /Q >nul
    echo    - docs directory copied
)

echo.
echo ========================================
echo Deployment completed successfully!
echo ========================================
echo.
echo Next steps:
echo 1. Verify database configuration in: %DEST_PATH%\backend\config.php
echo 2. Ensure MySQL database is set up (run backend\schema.sql if needed)
echo 3. Access the application at: http://localhost/attendance/frontend/
echo    (adjust URL based on your web server configuration)
echo.

endlocal
exit /b 0
