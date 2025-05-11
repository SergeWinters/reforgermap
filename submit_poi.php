<?php
// Ensure no stray output before this line
require_once 'db_config.php'; // Ensure this path is correct

// Set header to JSON at the very beginning
header('Content-Type: application/json');

// Centralized error handler to ensure JSON output
function send_json_error($message, $log_message = null, $http_code = 400) {
    if ($log_message === null) {
        $log_message = $message;
    }
    error_log("POI Submission Error: " . $log_message);
    http_response_code($http_code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Invalid request method.');
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null; // Keep as string for now, cast to float later
$longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null; // Keep as string for now
$map_id_str = isset($_POST['map_id']) ? trim($_POST['map_id']) : '';
$fa_icon_class = isset($_POST['fa_icon_class']) ? trim($_POST['fa_icon_class']) : null;

if (empty($fa_icon_class)) {
    $fa_icon_class = null;
}

// --- Validation ---
if (empty($name) || strlen($name) > 150) {
    send_json_error('Invalid POI name.');
}

// Validate and cast numeric inputs
if ($latitude === null || !is_numeric($latitude) || $longitude === null || !is_numeric($longitude)) {
    send_json_error('Invalid coordinates. Must be numeric.');
}
$latitude_float = (float)$latitude;
$longitude_float = (float)$longitude;

if (empty($map_id_str) || !ctype_digit($map_id_str)) {
     send_json_error('Invalid map ID. Must be a positive integer.');
}
$map_id_int = (int)$map_id_str;
if ($map_id_int <= 0) {
    send_json_error('Invalid map ID. Must be a positive integer value.');
}


// --- Database Connection Check (from db_config.php) ---
if ($conn->connect_error) {
    send_json_error('Database connection failed.', "DB Connect Error: " . $conn->connect_error, 500);
}

// --- Database Insertion ---
$sql = "INSERT INTO pois (map_id, name, latitude, longitude, status, image_path, fa_icon_class) VALUES (?, ?, ?, ?, 'pending', NULL, ?)";

if ($stmt = $conn->prepare($sql)) {
    // Bind parameters: i for integer map_id, s for string name, d for double lat/lng, s for string fa_icon_class
    $stmt->bind_param("isdds", $map_id_int, $name, $latitude_float, $longitude_float, $fa_icon_class);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'POI submitted successfully for review.']);
    } else {
        send_json_error('Database error: Could not save POI.', "DB Execute Error: " . $stmt->error . " (Data: map_id=$map_id_int, name=$name, fa_icon=$fa_icon_class)", 500);
    }
    $stmt->close();
} else {
    send_json_error('Database error: Could not prepare statement.', "DB Prepare Error: " . $conn->error, 500);
}

$conn->close();
?>