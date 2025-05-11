<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$map_id = isset($_POST['map_id']) ? (int)$_POST['map_id'] : null;

if (!$map_id) {
    echo json_encode(['success' => false, 'message' => 'Map ID is required.']);
    exit;
}

// Check if map exists
$map_check_stmt = $conn->prepare("SELECT id FROM maps WHERE id = ?");
$map_check_stmt->bind_param("i", $map_id);
$map_check_stmt->execute();
$map_result = $map_check_stmt->get_result();
if ($map_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Map ID.']);
    $map_check_stmt->close();
    $conn->close();
    exit;
}
$map_check_stmt->close();


$session_key = bin2hex(random_bytes(8)); // Generates a 16-character hex string, reasonably unique

$sql = "INSERT INTO collaboration_sessions (session_key, map_id) VALUES (?, ?)";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("si", $session_key, $map_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'session_key' => $session_key, 'map_id' => $map_id]);
    } else {
        error_log("Create Session DB Error: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Could not create session.']);
    }
    $stmt->close();
} else {
    error_log("Create Session Prepare Error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error creating session.']);
}
$conn->close();
?>