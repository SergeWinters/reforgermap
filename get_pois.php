<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$map_id = isset($_GET['map_id']) ? (int)$_GET['map_id'] : 0;

if ($map_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid map_id provided']);
    exit;
}

$pois = [];
// Only fetch approved POIs for the public map
$sql = "SELECT name, latitude, longitude, image_path FROM pois WHERE map_id = ? AND status = 'approved'";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $map_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Convert lat/lng to float, as they might come as strings from DB
            $row['latitude'] = (float)$row['latitude'];
            $row['longitude'] = (float)$row['longitude'];
            $pois[] = $row;
        }
        $result->free();
    } else {
        // Log error: $stmt->error;
        http_response_code(500);
        echo json_encode(['error' => 'Failed to execute POI query']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
} else {
    // Log error: $conn->error;
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare POI query']);
    $conn->close();
    exit;
}

$conn->close();
echo json_encode($pois);
?>