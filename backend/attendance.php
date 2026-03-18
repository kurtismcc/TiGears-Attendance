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

// Check if today is a competition day - if so, force action to 'in'
$today = date('Y-m-d');
$compResult = $conn->query("SELECT label FROM competition_days WHERE date = '$today' LIMIT 1");
$isCompetitionDay = ($compResult && $compResult->num_rows > 0);
if ($isCompetitionDay) {
    $action = 'in';
}

// Insert attendance record
$stmt = $conn->prepare("INSERT INTO attendance_log (student_id, action) VALUES (?, ?)");
$stmt->bind_param("ss", $student_id, $action);

if ($stmt->execute()) {
    $action_text = $action === 'in' ? 'checked in' : 'signed out';
    echo json_encode([
        'success' => true,
        'message' => $student_name . ' ' . $action_text . ' successfully!',
        'student_name' => $student_name,
        'action' => $action,
        'is_competition_day' => $isCompetitionDay
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
