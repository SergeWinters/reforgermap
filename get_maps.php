<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$maps = [];
$sql = "SELECT id, name, image_path FROM maps ORDER BY name ASC";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $maps[] = $row;
    }
    $result->free();
} else {
    // Log error: $conn->error;
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to fetch maps']);
    $conn->close();
    exit;
}

$conn->close();
echo json_encode($maps);
?>