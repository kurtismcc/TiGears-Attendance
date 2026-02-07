<?php
require_once '../backend/db.php';
require_once 'awards.php';
require_once 'window_transform.php';

// Load transformed data for awards
// This handles: window generation, time capping at window end, ignoring carry-over sign-ins
$transformedData = loadAwardData($conn);

// Check if we're past today's attendance window
$today = date('Y-m-d');
$todayDayOfWeek = date('w'); // 0 = Sunday, 6 = Saturday
$now = time();

// Get today's window (if any)
$windowResult = $conn->query("SELECT start_time, end_time FROM attendance_windows WHERE day_of_week = $todayDayOfWeek LIMIT 1");
$todayWindow = $windowResult->fetch_assoc();
$isPastWindow = false;

if ($todayWindow) {
    $windowEnd = strtotime($today . ' ' . $todayWindow['end_time']);
    $isPastWindow = ($now > $windowEnd);
}

// Query to get all students with their TODAY's sign-in status
// A student is "signed in" only if their last action TODAY is 'in'
$sql = "
    SELECT
        s.student_id,
        s.name,
        today_log.last_action,
        today_log.last_timestamp
    FROM students s
    LEFT JOIN (
        SELECT
            student_id,
            action as last_action,
            timestamp as last_timestamp
        FROM attendance_log al1
        WHERE DATE(timestamp) = CURDATE()
          AND timestamp = (
            SELECT MAX(timestamp)
            FROM attendance_log al2
            WHERE al2.student_id = al1.student_id
              AND DATE(al2.timestamp) = CURDATE()
        )
    ) today_log ON s.student_id = today_log.student_id
    ORDER BY s.name ASC
";

$result = $conn->query($sql);

// Build list of all students with their sign-in status for today
$all_students = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Determine if currently signed in (only if they have a sign-in TODAY and last action is 'in')
        // If we're past the window, everyone shows as signed out
        $isSignedIn = false;
        if (!$isPastWindow && $row['last_action'] === 'in') {
            $isSignedIn = true;
        }
        $row['is_signed_in'] = $isSignedIn;
        $all_students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Auto-refresh every 5 minutes to update awards (computed for completed windows only) -->
    <meta http-equiv="refresh" content="300">
    <title>Robotics Team Attendance</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="assets/Logo.jpg" alt="TiGears Logo" class="header-logo">
            <h1>TiGears - Attendance Tracker</h1>
            <a href="admin.php"><img src="assets/Logo.jpg" alt="TiGears Logo" class="header-logo"></a>
            <span class="nfc-status disconnected" id="nfcStatus" title="NFC Reader Disconnected"></span>
        </div>
        <p class="instructions">Tap your name and then tap Sign In or Sign Out</p>

        <!-- Awards Section -->
        <div class="awards-section">
            <?php
            // Each box is populated by a function in awards.php
            // Using transformed data which properly handles:
            // - Students who forget to sign out (capped at window end)
            // - Sign-ins from previous day (ignored)
            // - Consecutive attendance based on window occurrences, not calendar days
            populateLeftBoxTransformed($transformedData);
            populateMiddleBoxTransformed($transformedData);
            populateRightBoxTransformed($transformedData);
            ?>
        </div>

        <div id="message" class="message"></div>

        <!-- Numeric Keypad for Student ID -->
        <div class="keypad-container" id="keypadContainer" style="display: none;">
            <h3 class="keypad-title">Enter Your Student ID</h3>

            <!-- Student standings display -->
            <div class="student-standings" id="studentStandings">
                <div class="standing-item">
                    <span class="standing-label">Consecutive</span>
                    <span class="standing-rank" id="standingConsecutiveRank">-</span>
                    <span class="standing-value" id="standingConsecutiveValue">-</span>
                </div>
                <div class="standing-item">
                    <span class="standing-label">Score</span>
                    <span class="standing-rank" id="standingScoreRank">-</span>
                    <span class="standing-value" id="standingScoreValue">-</span>
                </div>
                <div class="standing-item">
                    <span class="standing-label">Time</span>
                    <span class="standing-rank" id="standingTimeRank">-</span>
                    <span class="standing-value" id="standingTimeValue">-</span>
                </div>
            </div>

            <div class="keypad-display">
                <input type="text" id="studentIdInput" readonly placeholder="Enter ID">
                <button class="keypad-clear" id="clearBtn">Clear</button>
            </div>
            <div class="keypad-grid">
                <button class="keypad-btn" data-value="1">1</button>
                <button class="keypad-btn" data-value="2">2</button>
                <button class="keypad-btn" data-value="3">3</button>
                <button class="keypad-btn" data-value="4">4</button>
                <button class="keypad-btn" data-value="5">5</button>
                <button class="keypad-btn" data-value="6">6</button>
                <button class="keypad-btn" data-value="7">7</button>
                <button class="keypad-btn" data-value="8">8</button>
                <button class="keypad-btn" data-value="9">9</button>
                <button class="keypad-btn keypad-backspace" data-value="backspace">⌫</button>
                <button class="keypad-btn" data-value="0">0</button>
                <button class="keypad-btn keypad-empty"></button>
            </div>
            <div class="action-buttons">
                <button class="action-button confirm" id="confirmBtn">Confirm</button>
                <button class="action-button write-tag" id="writeTagBtn" style="display: none;">Write to Tag</button>
                <button class="action-button cancel" id="cancelBtn">Cancel</button>
            </div>
            <div class="nfc-waiting" id="nfcWaiting" style="display: none;">
                Hold tag to reader...
            </div>
        </div>

        <?php
        // Count signed in students for the header
        $signedInCount = 0;
        foreach ($all_students as $student) {
            if ($student['is_signed_in']) $signedInCount++;
        }
        ?>
        <div class="student-roster">
            <div class="roster-header">
                <h2 class="roster-title">Today's Attendance</h2>
                <span class="roster-count"><?php echo $signedInCount; ?> / <?php echo count($all_students); ?> signed in</span>
            </div>
            <div class="roster-grid">
                <?php
                if (count($all_students) > 0) {
                    foreach($all_students as $student) {
                        $statusClass = $student['is_signed_in'] ? 'signed-in' : 'signed-out';
                        $statusIcon = $student['is_signed_in'] ? '&#10004;' : '&#10008;';
                        $dataStatus = $student['is_signed_in'] ? 'in' : 'out';
                        echo '<button class="roster-item ' . $statusClass . '" data-student-id="' . htmlspecialchars($student['student_id']) . '" data-status="' . $dataStatus . '">';
                        echo '<span class="roster-icon">' . $statusIcon . '</span>';
                        echo '<span class="roster-name">' . htmlspecialchars($student['name']) . '</span>';
                        echo '</button>';
                    }
                } else {
                    echo '<p class="empty-list">No students registered</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
<?php
$conn->close();
?>
