<?php
require_once 'db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get attendance records for a specific student
        $student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

        if (!$student_id) {
            echo json_encode(['success' => false, 'message' => 'Student ID required']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT id, student_id, timestamp, action, status
            FROM attendance_log
            WHERE student_id = ?
            ORDER BY timestamp DESC
        ");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $records = [];
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'records' => $records]);
        break;

    case 'PUT':
        // Update an attendance record
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Record ID required']);
            exit;
        }

        $id = intval($data['id']);
        $timestamp = isset($data['timestamp']) ? $data['timestamp'] : null;
        $action = isset($data['action']) ? $data['action'] : null;
        $status = isset($data['status']) ? $data['status'] : null;

        // Validate action
        if ($action !== null && $action !== 'in' && $action !== 'out') {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
        }

        // Validate status
        $valid_statuses = ['on_time', 'late', 'outside_window', null, ''];
        if ($status !== null && !in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }

        // Build dynamic update query
        $updates = [];
        $params = [];
        $types = "";

        if ($timestamp !== null) {
            $updates[] = "timestamp = ?";
            $params[] = $timestamp;
            $types .= "s";
        }
        if ($action !== null) {
            $updates[] = "action = ?";
            $params[] = $action;
            $types .= "s";
        }
        if (array_key_exists('status', $data)) {
            $updates[] = "status = ?";
            $params[] = $status === '' ? null : $status;
            $types .= "s";
        }

        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }

        $params[] = $id;
        $types .= "i";

        $sql = "UPDATE attendance_log SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Record updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found or no changes made']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'DELETE':
        // Delete an attendance record
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM attendance_log WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Record deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Record not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>
