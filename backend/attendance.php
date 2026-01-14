<?php
require_once 'db.php';

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['student_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$student_id = $data['student_id'];
$action = $data['action'];

// Validate action
if ($action !== 'in' && $action !== 'out') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Verify student exists
$stmt = $conn->prepare("SELECT name FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    $stmt->close();
    exit;
}

$student = $result->fetch_assoc();
$student_name = $student['name'];
$stmt->close();

// Calculate attendance status for sign-ins
$status = null;
if ($action === 'in') {
    $status = calculateAttendanceStatus($conn);
}

// Insert attendance record
$stmt = $conn->prepare("INSERT INTO attendance_log (student_id, action, status) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $student_id, $action, $status);

if ($stmt->execute()) {
    $action_text = $action === 'in' ? 'signed in' : 'signed out';
    $response = [
        'success' => true,
        'message' => $student_name . ' ' . $action_text . ' successfully!',
        'student_name' => $student_name,
        'action' => $action
    ];
    if ($status !== null) {
        $response['status'] = $status;
    }
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();

/**
 * Calculate attendance status based on current time and attendance windows
 * Returns 'on_time', 'late', or 'outside_window'
 */
function calculateAttendanceStatus($conn) {
    $now = new DateTime();
    $dayOfWeek = (int)$now->format('w'); // 0=Sunday, 6=Saturday
    $currentTime = $now->format('H:i:s');

    // Find windows for today that contain the current time
    $stmt = $conn->prepare("
        SELECT start_time, end_time
        FROM attendance_windows
        WHERE day_of_week = ?
        AND start_time <= ?
        AND end_time >= ?
        ORDER BY start_time ASC
        LIMIT 1
    ");
    $stmt->bind_param("iss", $dayOfWeek, $currentTime, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return 'outside_window';
    }

    $window = $result->fetch_assoc();
    $stmt->close();

    // Check if within grace period
    $startTime = new DateTime($window['start_time']);
    $graceEnd = clone $startTime;
    $graceEnd->modify('+' . GRACE_PERIOD_MINUTES . ' minutes');

    $currentDateTime = new DateTime($currentTime);

    if ($currentDateTime <= $graceEnd) {
        return 'on_time';
    } else {
        return 'late';
    }
}
?>
