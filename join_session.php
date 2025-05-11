<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$session_key = isset($_GET['session_key']) ? trim($_GET['session_key']) : '';

if (empty($session_key)) {
    echo json_encode(['success' => false, 'message' => 'Session key is required.']);
    exit;
}

$sql = "SELECT id, map_id FROM collaboration_sessions WHERE session_key = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $session_key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($session_data = $result->fetch_assoc()) {
        // Update last_active_at
        $update_stmt = $conn->prepare("UPDATE collaboration_sessions SET last_active_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update_stmt->bind_param("i", $session_data['id']);
        $update_stmt->execute();
        $update_stmt->close();

        echo json_encode(['success' => true, 'session_key' => $session_key, 'map_id' => (int)$session_data['map_id']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Session not found.']);
    }
    $stmt->close();
} else {
    error_log("Join Session Prepare Error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error joining session.']);
}
$conn->close();
?>