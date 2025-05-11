<?php
define('DB_SERVER', 'localhost'); // or your db server
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'reforger');

// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // For production, you might want to log this error and show a generic message
    die("ERROR: Could not connect. " . $conn->connect_error);
}

// Set charset
if (!$conn->set_charset("utf8mb4")) {
    // For production, log this
    // printf("Error loading character set utf8mb4: %s\n", $conn->error);
}
?>