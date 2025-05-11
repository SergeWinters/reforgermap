<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$session_key = isset($_GET['session_key']) ? trim($_GET['session_key']) : '';
$client_id = isset($_GET['client_id']) ? trim($_GET['client_id']) : '';
$last_marker_id = isset($_GET['last_marker_id']) ? (int)$_GET['last_marker_id'] : 0;
$last_drawing_id = isset($_GET['last_drawing_id']) ? (int)$_GET['last_drawing_id'] : 0;

if (empty($session_key)) {
    echo json_encode(['success' => false, 'message' => 'Session key required.']);
    exit;
}

$session_stmt = $conn->prepare("SELECT id, map_id FROM collaboration_sessions WHERE session_key = ?");
if (!$session_stmt) {
    error_log("Prepare failed (session select in get_updates): " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Server error preparing session query.']);
    exit;
}
$session_stmt->bind_param("s", $session_key);
$session_stmt->execute();
$session_result = $session_stmt->get_result();
if (!($session_db_data = $session_result->fetch_assoc())) {
    echo json_encode(['success' => false, 'message' => 'Invalid session.']);
    $session_stmt->close();
    $conn->close();
    exit;
}
$session_id = (int)$session_db_data['id'];
$session_map_id = (int)$session_db_data['map_id'];
$session_stmt->close();

$update_stmt = $conn->prepare("UPDATE collaboration_sessions SET last_active_at = CURRENT_TIMESTAMP WHERE id = ?");
if ($update_stmt) {
    $update_stmt->bind_param("i", $session_id);
    $update_stmt->execute();
    $update_stmt->close();
} else {
    error_log("Prepare failed (update last_active_at): " . $conn->error);
}


$updates = [
    'success' => true,
    'map_id' => $session_map_id,
    'markers' => [],
    'drawings' => [],
];

// Fetch new markers, now including fa_icon_class, marker_color, marker_text
$marker_sql = "SELECT id, client_id, marker_type, latitude, longitude, fa_icon_class, marker_color, marker_text, created_at 
               FROM collaboration_markers 
               WHERE session_id = ? AND id > ? 
               ORDER BY id ASC";

if ($stmt_markers = $conn->prepare($marker_sql)) {
    $stmt_markers->bind_param("ii", $session_id, $last_marker_id);
    $stmt_markers->execute();
    $result_markers = $stmt_markers->get_result();
    while ($row = $result_markers->fetch_assoc()) {
        $row['latitude'] = (float)$row['latitude'];
        $row['longitude'] = (float)$row['longitude'];
        // fa_icon_class, marker_color, marker_text will be included as they are
        $updates['markers'][] = $row;
    }
    $stmt_markers->close();
} else {
    error_log("Get Collab Markers Prepare Error: " . $conn->error);
    $updates['success'] = false;
    $updates['message'] = 'Error fetching markers.';
}

// Fetch new drawings (this part remains the same)
$drawing_sql = "SELECT id, client_id, layer_type, geojson_data, client_layer_id, created_at 
                FROM collaboration_drawings 
                WHERE session_id = ? AND id > ? 
                ORDER BY id ASC";
if ($stmt_drawings = $conn->prepare($drawing_sql)) {
    $stmt_drawings->bind_param("ii", $session_id, $last_drawing_id);
    $stmt_drawings->execute();
    $result_drawings = $stmt_drawings->get_result();
    while ($row = $result_drawings->fetch_assoc()) {
        $updates['drawings'][] = $row;
    }
    $stmt_drawings->close();
} else {
    error_log("Get Collab Drawings Prepare Error: " . $conn->error);
    $updates['success'] = false;
    $updates['message'] = (isset($updates['message']) ? $updates['message'] . ' ' : '') . 'Error fetching drawings.';
}

echo json_encode($updates);
if ($conn) $conn->close();
?>