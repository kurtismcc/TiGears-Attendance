<?php
require_once 'db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // List all attendance windows
        $result = $conn->query("SELECT id, day_of_week, start_time, end_time FROM attendance_windows ORDER BY day_of_week, start_time");
        $windows = [];
        while ($row = $result->fetch_assoc()) {
            $windows[] = $row;
        }
        echo json_encode(['success' => true, 'windows' => $windows]);
        break;

    case 'POST':
        // Create a new attendance window
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['day_of_week']) || !isset($data['start_time']) || !isset($data['end_time'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $day = intval($data['day_of_week']);
        $start = $data['start_time'];
        $end = $data['end_time'];

        // Validate day of week
        if ($day < 0 || $day > 6) {
            echo json_encode(['success' => false, 'message' => 'Invalid day of week (must be 0-6)']);
            exit;
        }

        // Validate times
        if ($start >= $end) {
            echo json_encode(['success' => false, 'message' => 'Start time must be before end time']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO attendance_windows (day_of_week, start_time, end_time) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $day, $start, $end);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Window created', 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        break;

    case 'DELETE':
        // Delete an attendance window
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid window ID']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM attendance_windows WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Window deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Window not found']);
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
