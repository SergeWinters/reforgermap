<?php
session_start();
require_once 'db_config.php'; // For database connection
require_once 'admin_config.php'; // For admin credentials

// Logout logic
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Login attempt
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    if ($_POST['username'] === ADMIN_USERNAME && password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php'); // Redirect to clear POST data
        exit;
    } else {
        $login_error = "Invalid username or password.";
    }
}

// Check if admin is logged in for actions
$isAdminLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// --- POI Action Handling (Approve/Reject/Delete) ---
if ($isAdminLoggedIn && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $poi_id = isset($_POST['poi_id']) ? (int)$_POST['poi_id'] : 0;

    if ($poi_id > 0) {
        if ($_POST['action'] === 'approve_poi') {
            $stmt = $conn->prepare("UPDATE pois SET status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $poi_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($_POST['action'] === 'reject_poi') {
            // Option 1: Change status to 'rejected'
            $stmt = $conn->prepare("UPDATE pois SET status = 'rejected' WHERE id = ?");
            // Option 2: Delete (if you prefer to remove rejected POIs entirely)
            // $stmt = $conn->prepare("DELETE FROM pois WHERE id = ?");
            $stmt->bind_param("i", $poi_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($_POST['action'] === 'delete_poi') {
            $stmt = $conn->prepare("DELETE FROM pois WHERE id = ?");
            $stmt->bind_param("i", $poi_id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: admin.php?status_updated=1'); // Redirect to show changes and avoid form resubmission
        exit;
    }
}

// --- Map Action Handling ---
if ($isAdminLoggedIn && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['map_action'])) {
    if ($_POST['map_action'] === 'add_map' && !empty($_POST['map_name']) && !empty($_POST['map_image_path'])) {
        $map_name = trim($_POST['map_name']);
        $map_image_path = trim($_POST['map_image_path']);
        $stmt = $conn->prepare("INSERT INTO maps (name, image_path) VALUES (?, ?)");
        $stmt->bind_param("ss", $map_name, $map_image_path);
        $stmt->execute();
        $stmt->close();
        header('Location: admin.php?map_action_status=added');
        exit;
    } elseif ($_POST['map_action'] === 'delete_map' && isset($_POST['map_id'])) {
        $map_id_to_delete = (int)$_POST['map_id'];
        // ON DELETE CASCADE will handle associated POIs
        $stmt = $conn->prepare("DELETE FROM maps WHERE id = ?");
        $stmt->bind_param("i", $map_id_to_delete);
        $stmt->execute();
        $stmt->close();
        header('Location: admin.php?map_action_status=deleted');
        exit;
    }
    // Edit map would be similar, using an UPDATE query.
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Reforger Map</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .login-form, .action-form { margin-bottom: 20px; padding:15px; border:1px solid #ccc; border-radius:5px; background-color:#f9f9f9;}
        .login-form input, .action-form input, .action-form select, .action-form textarea { width: calc(100% - 22px); padding: 10px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px;}
        .login-form button, .action-form button, .action-button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .action-button.approve { background-color: #28a745; }
        .action-button.reject { background-color: #dc3545; }
        .action-button.delete { background-color: #ffc107; color: black; }
        .error { color: red; margin-bottom: 15px; }
        .success { color: green; margin-bottom: 15px; }
        nav { margin-bottom: 20px; }
        nav a { margin-right: 15px; text-decoration: none; color: #007bff; font-weight: bold; }
        .status-pending { background-color: #fff3cd; }
        .status-approved { background-color: #d4edda; }
        .status-rejected { background-color: #f8d7da; }
        .poi-image-thumbnail { max-width: 100px; max-height: 100px; object-fit: cover; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Panel</h1>

        <?php if (!$isAdminLoggedIn): ?>
            <h2>Login</h2>
            <?php if (isset($login_error)): ?>
                <p class="error"><?php echo htmlspecialchars($login_error); ?></p>
            <?php endif; ?>
            <form method="POST" action="admin.php" class="login-form">
                <div>
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div>
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>
        <?php else: ?>
            <p><a href="admin.php?action=logout">Logout</a></p>
            <hr>

            <!-- Map Management -->
            <h2>Manage Maps</h2>
            <?php if(isset($_GET['map_action_status'])): ?>
                <p class="success">Map action successful: <?php echo htmlspecialchars($_GET['map_action_status']); ?></p>
            <?php endif; ?>
            <form method="POST" action="admin.php" class="action-form">
                <h3>Add New Map</h3>
                <input type="hidden" name="map_action" value="add_map">
                <div>
                    <label for="map_name">Map Name:</label>
                    <input type="text" id="map_name" name="map_name" required>
                </div>
                <div>
                    <label for="map_image_path">Map Image Path (e.g., maps/new_map.png):</label>
                    <input type="text" id="map_image_path" name="map_image_path" required>
                </div>
                <button type="submit">Add Map</button>
            </form>
            <hr>
            <h3>Existing Maps</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Image Path</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $maps_result = $conn->query("SELECT id, name, image_path FROM maps ORDER BY name ASC");
                    if ($maps_result && $maps_result->num_rows > 0) {
                        while ($map = $maps_result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($map['id']); ?></td>
                        <td><?php echo htmlspecialchars($map['name']); ?></td>
                        <td><?php echo htmlspecialchars($map['image_path']); ?></td>
                        <td>
                            <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this map and all its POIs?');">
                                <input type="hidden" name="map_action" value="delete_map">
                                <input type="hidden" name="map_id" value="<?php echo htmlspecialchars($map['id']); ?>">
                                <button type="submit" class="action-button reject">Delete</button>
                            </form>
                            <!-- Edit Map Button (TODO: Implement edit functionality) -->
                            <!-- <button onclick="alert('Edit map ID <?php echo $map['id']; ?> - To be implemented')">Edit</button> -->
                        </td>
                    </tr>
                    <?php
                        endwhile;
                    } else {
                        echo "<tr><td colspan='4'>No maps found.</td></tr>";
                    }
                    if ($maps_result) $maps_result->free();
                    ?>
                </tbody>
            </table>


            <hr>
            <!-- POI Management -->
            <h2>Manage Points of Interest</h2>
            <?php if(isset($_GET['status_updated'])): ?>
                <p class="success">POI status updated successfully!</p>
            <?php endif; ?>

            <?php
            // Fetch POIs with map names
            $poi_sql = "SELECT p.id, p.name AS poi_name, p.latitude, p.longitude, p.status, p.image_path AS poi_image_path, m.name AS map_name
                        FROM pois p
                        JOIN maps m ON p.map_id = m.id
                        ORDER BY p.status ASC, p.id DESC"; // Show pending first
            $pois_result = $conn->query($poi_sql);
            ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Map</th>
                        <th>Coords (Lat, Lng)</th>
                        <th>Status</th>
                        <th>Image</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($pois_result && $pois_result->num_rows > 0) {
                        while ($poi = $pois_result->fetch_assoc()):
                            $status_class = 'status-' . htmlspecialchars(strtolower($poi['status']));
                    ?>
                    <tr class="<?php echo $status_class; ?>">
                        <td><?php echo htmlspecialchars($poi['id']); ?></td>
                        <td><?php echo htmlspecialchars($poi['poi_name']); ?></td>
                        <td><?php echo htmlspecialchars($poi['map_name']); ?></td>
                        <td><?php echo htmlspecialchars(number_format($poi['latitude'], 4) . ', ' . number_format($poi['longitude'], 4)); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($poi['status'])); ?></td>
                        <td>
                            <?php if (!empty($poi['poi_image_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($poi['poi_image_path']); ?>" alt="<?php echo htmlspecialchars($poi['poi_name']); ?>" class="poi-image-thumbnail" loading="lazy">
                                <small>(<?php echo htmlspecialchars($poi['poi_image_path']); ?>)</small>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($poi['status'] === 'pending'): ?>
                            <form method="POST" action="admin.php" style="display:inline;">
                                <input type="hidden" name="poi_id" value="<?php echo htmlspecialchars($poi['id']); ?>">
                                <input type="hidden" name="action" value="approve_poi">
                                <button type="submit" class="action-button approve">Approve</button>
                            </form>
                            <form method="POST" action="admin.php" style="display:inline;">
                                <input type="hidden" name="poi_id" value="<?php echo htmlspecialchars($poi['id']); ?>">
                                <input type="hidden" name="action" value="reject_poi">
                                <button type="submit" class="action-button reject">Reject</button>
                            </form>
                            <?php endif; ?>
                            <!-- Edit POI Button (TODO: Implement edit functionality) -->
                            <!-- <button onclick="alert('Edit POI ID <?php echo $poi['id']; ?> - To be implemented')">Edit</button> -->
                            <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this POI?');">
                                <input type="hidden" name="poi_id" value="<?php echo htmlspecialchars($poi['id']); ?>">
                                <input type="hidden" name="action" value="delete_poi">
                                <button type="submit" class="action-button delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php
                        endwhile;
                    } else {
                        echo "<tr><td colspan='7'>No POIs found.</td></tr>";
                    }
                    if ($pois_result) $pois_result->free();
                    ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php $conn->close(); ?>
    </div>
</body>
</html>