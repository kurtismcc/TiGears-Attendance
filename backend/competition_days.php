<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT id, date, label FROM competition_days ORDER BY date DESC");
    $days = [];
    while ($row = $result->fetch_assoc()) {
        $days[] = $row;
    }
    echo json_encode(['success' => true, 'days' => $days]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid date']);
        exit;
    }

    $date = $data['date'];
    $label = isset($data['label']) ? substr(trim($data['label']), 0, 100) : '';

    $stmt = $conn->prepare("INSERT INTO competition_days (date, label) VALUES (?, ?)");
    $stmt->bind_param("ss", $date, $label);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
    } else {
        if ($conn->errno === 1062) {
            echo json_encode(['success' => false, 'message' => 'A competition day already exists for that date']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    }
    $stmt->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing id']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM competition_days WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }
    $stmt->close();

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

$conn->close();
?>
