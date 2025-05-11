<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$session_key = isset($_GET['session_key']) ? trim($_GET['session_key']) : '';
$client_id = isset($_GET['client_id']) ? trim($_GET['client_id']) : ''; // To exclude own items if needed by client
$last_marker_id = isset($_GET['last_marker_id']) ? (int)$_GET['last_marker_id'] : 0;
$last_drawing_id = isset($_GET['last_drawing_id']) ? (int)$_GET['last_drawing_id'] : 0;
// $last_deleted_id = isset($_GET['last_deleted_id']) ? (int)$_GET['last_deleted_id'] : 0; // For deleted items table

if (empty($session_key)) {
    echo json_encode(['success' => false, 'message' => 'Session key required.']);
    exit;
}

// Get session_id from session_key
$session_stmt = $conn->prepare("SELECT id, map_id FROM collaboration_sessions WHERE session_key = ?");
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

// Update last_active_at for the session
$update_stmt = $conn->prepare("UPDATE collaboration_sessions SET last_active_at = CURRENT_TIMESTAMP WHERE id = ?");
$update_stmt->bind_param("i", $session_id);
$update_stmt->execute();
$update_stmt->close();


$updates = [
    'success' => true,
    'map_id' => $session_map_id, // Send the map_id the session is bound to
    'markers' => [],
    'drawings' => [],
    // 'deleted_items' => []
];

// Fetch new markers
// If $client_id is provided, we could potentially exclude items created by this client
// WHERE session_id = ? AND id > ? AND client_id != ?
// But for simplicity, client can filter its own items by client_id if it wants to.
$marker_sql = "SELECT id, client_id, marker_type, latitude, longitude, created_at FROM collaboration_markers WHERE session_id = ? AND id > ? ORDER BY id ASC";
if ($stmt_markers = $conn->prepare($marker_sql)) {
    $stmt_markers->bind_param("ii", $session_id, $last_marker_id);
    $stmt_markers->execute();
    $result_markers = $stmt_markers->get_result();
    while ($row = $result_markers->fetch_assoc()) {
        $row['latitude'] = (float)$row['latitude'];
        $row['longitude'] = (float)$row['longitude'];
        $updates['markers'][] = $row;
    }
    $stmt_markers->close();
} else {
    error_log("Get Collab Markers Prepare Error: " . $conn->error);
    $updates['success'] = false;
    $updates['message'] = 'Error fetching markers.';
}

// Fetch new drawings
$drawing_sql = "SELECT id, client_id, layer_type, geojson_data, client_layer_id, created_at FROM collaboration_drawings WHERE session_id = ? AND id > ? ORDER BY id ASC";
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

// Fetch deleted items (if using a deleted_items table)
// $deleted_sql = "SELECT id, item_db_id, item_type FROM collaboration_deleted_items WHERE session_id = ? AND id > ? ORDER BY id ASC";
// ... similar logic ...

echo json_encode($updates);
$conn->close();
?>