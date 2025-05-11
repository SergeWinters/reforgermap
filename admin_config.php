<?php
// admin_config.php
define('ADMIN_USERNAME', 'admin');
// IMPORTANT: For a real application, hash this password!
// Use password_hash("your_chosen_password", PASSWORD_DEFAULT); to generate the hash.
// Store the HASH here, not the plain text password.
define('ADMIN_PASSWORD_HASH', password_hash('testingpass', PASSWORD_DEFAULT)); // Replace 'your_secure_password'
?>