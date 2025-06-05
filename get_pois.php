<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$map_id = isset($_GET['map_id']) ? (int)$_GET['map_id'] : 0;

if ($map_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid map_id provided']);
    exit;
}

$response_data = [
    'pois' => [],
    'connections' => []
];

// Fetch approved POIs for the public map
$sql_pois = "SELECT id, name, latitude, longitude, image_path, fa_icon_class 
             FROM pois 
             WHERE map_id = ? AND status = 'approved'";

if ($stmt_pois = $conn->prepare($sql_pois)) {
    $stmt_pois->bind_param("i", $map_id);
    if ($stmt_pois->execute()) {
        $result_pois = $stmt_pois->get_result();
        while ($row = $result_pois->fetch_assoc()) {
            $row['id'] = (int)$row['id']; // Ensure POI ID is integer
            $row['latitude'] = (float)$row['latitude'];
            $row['longitude'] = (float)$row['longitude'];
            $response_data['pois'][] = $row;
        }
        $result_pois->free();
    } else {
        error_log("Get POIs Execute Error: " . $stmt_pois->error . " (Map ID: " . $map_id . ")");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute POI query']);
        $stmt_pois->close();
        $conn->close();
        exit;
    }
    $stmt_pois->close();
} else {
    error_log("Get POIs Prepare Error: " . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare POI query']);
    $conn->close();
    exit;
}

// Fetch all connections for the current map_id
$sql_connections = "SELECT id, poi_id_from, poi_id_to, line_style 
                    FROM poi_connections 
                    WHERE map_id = ?";

if ($stmt_connections = $conn->prepare($sql_connections)) {
    $stmt_connections->bind_param("i", $map_id);
    if ($stmt_connections->execute()) {
        $result_connections = $stmt_connections->get_result();
        while ($row = $result_connections->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['poi_id_from'] = (int)$row['poi_id_from'];
            $row['poi_id_to'] = (int)$row['poi_id_to'];
            // Attempt to parse line_style JSON, default to null if invalid
            if ($row['line_style']) {
                $parsed_style = json_decode($row['line_style'], true);
                $row['line_style'] = (json_last_error() === JSON_ERROR_NONE) ? $parsed_style : null;
            }
            $response_data['connections'][] = $row;
        }
        $result_connections->free();
    } else {
        error_log("Get Connections Execute Error: " . $stmt_connections->error . " (Map ID: " . $map_id . ")");
        // Not a fatal error for the whole request, POIs might still be useful
    }
    $stmt_connections->close();
} else {
    error_log("Get Connections Prepare Error: " . $conn->error);
}


$conn->close();
echo json_encode($response_data); // Send combined data
?>