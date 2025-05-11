<?php
require_once 'db_config.php'; // Ensure this path is correct

header('Content-Type: application/json');

// Basic input validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
$map_id = isset($_POST['map_id']) ? (int)$_POST['map_id'] : null;

// --- Basic Validation ---
if (empty($name) || strlen($name) > 150) {
    echo json_encode(['success' => false, 'message' => 'Invalid POI name.']);
    exit;
}
if ($latitude === null || $longitude === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates.']);
    exit;
}
if ($map_id === null || $map_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid map ID.']);
    exit;
}

// Optional: Image upload handling (more complex, defer if needed)
// For now, we'll skip image upload to keep it simple.
// If you were to implement it:
// $image_path = null;
// if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
//     $target_dir = "uploads/poi_images/"; // Create this directory and make it writable
//     if (!is_dir($target_dir)) {
//         mkdir($target_dir, 0755, true);
//     }
//     $image_filename = uniqid() . "_" . basename($_FILES["image"]["name"]);
//     $target_file = $target_dir . $image_filename;
//     if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
//         $image_path = $target_file;
//     } else {
//         echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
//         exit;
//     }
// }


// --- Database Insertion ---
// The `image_path` column in your DB should allow NULL or have a default.
// We are setting status to 'pending'
$sql = "INSERT INTO pois (map_id, name, latitude, longitude, status, image_path) VALUES (?, ?, ?, ?, 'pending', NULL)"; // Assuming image_path is NULL for now

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("isdd", $map_id, $name, $latitude, $longitude);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'POI submitted successfully for review.']);
    } else {
        // Log error: $stmt->error
        error_log("POI Submission Error: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error: Could not save POI. ' . $stmt->error]);
    }
    $stmt->close();
} else {
    // Log error: $conn->error
    error_log("POI Submission Prepare Error: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error: Could not prepare statement. ' . $conn->error]);
}

$conn->close();
?>