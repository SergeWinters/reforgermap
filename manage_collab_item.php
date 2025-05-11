<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$session_key = isset($_POST['session_key']) ? trim($_POST['session_key']) : '';
$client_id = isset($_POST['client_id']) ? trim($_POST['client_id']) : '';
$item_type = isset($_POST['item_type']) ? $_POST['item_type'] : '';
$action = isset($_POST['action']) ? $_POST['action'] : 'add';

if (empty($session_key) || empty($client_id) || empty($item_type)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters (session_key, client_id, item_type).']);
    exit;
}

$session_stmt = $conn->prepare("SELECT id FROM collaboration_sessions WHERE session_key = ?");
if (!$session_stmt) {
    error_log("Prepare failed (session select): " . $conn->error);
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
$session_stmt->close();

$response = ['success' => false, 'message' => 'Invalid action or item type.'];

if ($action === 'add') {
    if ($item_type === 'marker') {
        $marker_type_name = isset($_POST['marker_type_name']) ? $_POST['marker_type_name'] : 'default_marker'; // e.g., 'enemy', 'custom_collab'
        $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        
        // New fields for custom collaborative markers
        $fa_icon_class = isset($_POST['fa_icon_class']) ? trim($_POST['fa_icon_class']) : null;
        $marker_color = isset($_POST['marker_color']) ? trim($_POST['marker_color']) : '#FFFFFF'; // Default to white
        $marker_text = isset($_POST['marker_text']) ? trim($_POST['marker_text']) : null;

        if (empty($fa_icon_class)) $fa_icon_class = null;
        if (empty($marker_text)) $marker_text = null;
        // Validate color format (basic hex)
        if (!preg_match('/^#[a-f0-9]{6}$/i', $marker_color)) {
            $marker_color = '#FFFFFF'; // Default if invalid
        }


        if (!empty($marker_type_name) && $latitude !== null && $longitude !== null) {
            $sql = "INSERT INTO collaboration_markers (session_id, client_id, marker_type, latitude, longitude, fa_icon_class, marker_color, marker_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                // Types: i(session_id), s(client_id), s(marker_type), d(lat), d(lng), s(fa_icon), s(color), s(text)
                $stmt->bind_param("issddsss", $session_id, $client_id, $marker_type_name, $latitude, $longitude, $fa_icon_class, $marker_color, $marker_text);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'item_id' => $stmt->insert_id, 'message' => 'Marker added.'];
                } else {
                    error_log("Add Collab Marker DB Error: " . $stmt->error);
                    $response['message'] = 'Failed to add marker.';
                }
                $stmt->close();
            } else {
                 error_log("Add Collab Marker Prepare Error: " . $conn->error);
                 $response['message'] = 'Server error adding marker.';
            }
        } else {
            $response['message'] = 'Missing marker data (type, lat, lng).';
        }
    } elseif ($item_type === 'drawing') {
        // ... (drawing logic remains the same) ...
        $layer_type = isset($_POST['layer_type']) ? $_POST['layer_type'] : '';
        $geojson_data = isset($_POST['geojson_data']) ? $_POST['geojson_data'] : '';
        $client_layer_id = isset($_POST['client_layer_id']) ? $_POST['client_layer_id'] : null;

        if (!empty($layer_type) && !empty($geojson_data)) {
            $sql = "INSERT INTO collaboration_drawings (session_id, client_id, layer_type, geojson_data, client_layer_id) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("issss", $session_id, $client_id, $layer_type, $geojson_data, $client_layer_id);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'item_id' => $stmt->insert_id, 'message' => 'Drawing added.'];
                } else {
                    error_log("Add Collab Drawing DB Error: " . $stmt->error);
                    $response['message'] = 'Failed to add drawing.';
                }
                $stmt->close();
            } else {
                error_log("Add Collab Drawing Prepare Error: " . $conn->error);
                $response['message'] = 'Server error adding drawing.';
            }
        } else {
            $response['message'] = 'Missing drawing data.';
        }
    }
} elseif ($action === 'delete') {
    $db_item_id = isset($_POST['db_item_id']) ? (int)$_POST['db_item_id'] : 0;
    if ($db_item_id > 0) {
        $table_name = '';
        if ($item_type === 'marker') $table_name = 'collaboration_markers';
        elseif ($item_type === 'drawing') $table_name = 'collaboration_drawings';

        if (!empty($table_name)) {
            $sql = "DELETE FROM $table_name WHERE id = ? AND session_id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $db_item_id, $session_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                         $response = ['success' => true, 'message' => ucfirst($item_type) . ' deleted.'];
                    } else {
                         $response = ['success' => false, 'message' => ucfirst($item_type) . ' not found or not part of this session.'];
                    }
                } else {
                    error_log("Delete Collab Item DB Error: " . $stmt->error);
                    $response['message'] = 'Failed to delete ' . $item_type . '.';
                }
                $stmt->close();
            } else {
                error_log("Delete Collab Item Prepare Error: " . $conn->error);
                $response['message'] = 'Server error deleting ' . $item_type . '.';
            }
        }
    } else {
         $response['message'] = 'Missing item ID for deletion.';
    }
}

echo json_encode($response);
if ($conn) $conn->close(); // Ensure $conn is checked before closing
?>