<?php
require_once '../backend/db.php';

// Query to get all students with their last attendance status
$sql = "
    SELECT
        s.student_id,
        s.name,
        al.last_action,
        al.last_timestamp
    FROM students s
    LEFT JOIN (
        SELECT
            student_id,
            action as last_action,
            timestamp as last_timestamp
        FROM attendance_log al1
        WHERE timestamp = (
            SELECT MAX(timestamp)
            FROM attendance_log al2
            WHERE al2.student_id = al1.student_id
        )
    ) al ON s.student_id = al.student_id
    ORDER BY s.name ASC
";

$result = $conn->query($sql);

// Categorize students by status
$logged_in = [];
$logged_out = [];
$never_logged = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        if ($row['last_action'] === null) {
            $never_logged[] = $row;
        } elseif ($row['last_action'] === 'in') {
            $logged_in[] = $row;
        } else {
            $logged_out[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Robotics Team Attendance</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Robotics Team Attendance</h1>
        <p class="instructions">Tap your name and then tap Sign In or Sign Out</p>

        <div id="message" class="message"></div>

        <div class="action-buttons" id="actionButtons" style="display: none;">
            <button class="action-button sign-in" id="signInBtn">Sign In</button>
            <button class="action-button sign-out" id="signOutBtn">Sign Out</button>
            <button class="action-button cancel" id="cancelBtn">Cancel</button>
        </div>

        <div class="student-lists">
            <!-- Logged In Students -->
            <div class="student-list-section logged-in-section">
                <h2 class="list-title logged-in-title">Logged In (<?php echo count($logged_in); ?>)</h2>
                <div class="student-list">
                    <?php
                    if (count($logged_in) > 0) {
                        foreach($logged_in as $student) {
                            echo '<button class="student-item" data-student-id="' . htmlspecialchars($student['student_id']) . '">';
                            echo htmlspecialchars($student['name']);
                            echo '</button>';
                        }
                    } else {
                        echo '<p class="empty-list">No students currently logged in</p>';
                    }
                    ?>
                </div>
            </div>

            <!-- Logged Out Students -->
            <div class="student-list-section logged-out-section">
                <h2 class="list-title logged-out-title">Logged Out (<?php echo count($logged_out); ?>)</h2>
                <div class="student-list">
                    <?php
                    if (count($logged_out) > 0) {
                        foreach($logged_out as $student) {
                            echo '<button class="student-item" data-student-id="' . htmlspecialchars($student['student_id']) . '">';
                            echo htmlspecialchars($student['name']);
                            echo '</button>';
                        }
                    } else {
                        echo '<p class="empty-list">No students logged out</p>';
                    }
                    ?>
                </div>
            </div>

            <!-- Never Logged In Students -->
            <div class="student-list-section never-logged-section">
                <h2 class="list-title never-logged-title">Never Logged In (<?php echo count($never_logged); ?>)</h2>
                <div class="student-list">
                    <?php
                    if (count($never_logged) > 0) {
                        foreach($never_logged as $student) {
                            echo '<button class="student-item" data-student-id="' . htmlspecialchars($student['student_id']) . '">';
                            echo htmlspecialchars($student['name']);
                            echo '</button>';
                        }
                    } else {
                        echo '<p class="empty-list">All students have logged in at least once</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
<?php
$conn->close();
?>
