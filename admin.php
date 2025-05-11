<?php
session_start();
require_once 'db_config.php'; 
require_once 'admin_config.php'; 

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
        header('Location: admin.php'); 
        exit;
    } else {
        $login_error = "Invalid username or password.";
    }
}

$isAdminLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

$action_message = ''; // For displaying feedback

// --- POI Action Handling ---
if ($isAdminLoggedIn && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && strpos($_POST['action'], 'poi') !== false) {
    $poi_id = isset($_POST['poi_id']) ? (int)$_POST['poi_id'] : 0;
    if ($poi_id > 0) {
        if ($_POST['action'] === 'approve_poi') {
            $stmt = $conn->prepare("UPDATE pois SET status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $poi_id); $stmt->execute(); $stmt->close();
            $action_message = "POI #$poi_id approved.";
        } elseif ($_POST['action'] === 'reject_poi') {
            $stmt = $conn->prepare("UPDATE pois SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $poi_id); $stmt->execute(); $stmt->close();
            $action_message = "POI #$poi_id rejected.";
        } elseif ($_POST['action'] === 'delete_poi') {
            $stmt = $conn->prepare("DELETE FROM pois WHERE id = ?");
            $stmt->bind_param("i", $poi_id); $stmt->execute(); $stmt->close();
            $action_message = "POI #$poi_id deleted.";
        } elseif ($_POST['action'] === 'update_poi_icon') {
            $fa_icon_class = isset($_POST['fa_icon_class']) ? trim($_POST['fa_icon_class']) : null;
            if (empty($fa_icon_class)) $fa_icon_class = null;
            $stmt = $conn->prepare("UPDATE pois SET fa_icon_class = ? WHERE id = ?");
            $stmt->bind_param("si", $fa_icon_class, $poi_id); $stmt->execute(); $stmt->close();
            $action_message = "Icon updated for POI #$poi_id.";
        }
        // No redirect here to show $action_message, or can redirect with query param
        // For simplicity now, message displayed on same page load if POST.
    }
}

// --- Map Action Handling ---
if ($isAdminLoggedIn && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['map_action'])) {
    if ($_POST['map_action'] === 'add_map' && !empty($_POST['map_name']) && !empty($_POST['map_image_path'])) {
        // ... (add map logic as before) ...
        $action_message = "Map '" . htmlspecialchars($_POST['map_name']) . "' added.";
    } elseif ($_POST['map_action'] === 'delete_map' && isset($_POST['map_id'])) {
        // ... (delete map logic as before) ...
        $action_message = "Map ID #" . htmlspecialchars($_POST['map_id']) . " and its POIs deleted.";
    }
}

// --- Collaboration Session Cleanup Action ---
if ($isAdminLoggedIn && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_action']) && $_POST['admin_action'] === 'delete_old_sessions') {
    $days_old = 5; // Define how old sessions should be to be deleted
    // Calculate the date $days_old ago
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days_old days"));

    $sql_delete_old_sessions = "DELETE FROM collaboration_sessions WHERE last_active_at < ?";
    if ($stmt = $conn->prepare($sql_delete_old_sessions)) {
        $stmt->bind_param("s", $cutoff_date);
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $action_message = "$affected_rows old collaboration session(s) (older than $days_old days, last active before $cutoff_date) and their data deleted successfully.";
        } else {
            $action_message = "Error deleting old sessions: " . htmlspecialchars($stmt->error);
            error_log("Admin Delete Old Sessions Execute Error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        $action_message = "Error preparing to delete old sessions: " . htmlspecialchars($conn->error);
        error_log("Admin Delete Old Sessions Prepare Error: " . $conn->error);
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Reforger Map</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-iconpicker/1.10.0/css/bootstrap-iconpicker.min.css"/>

    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding:0; background-color: #f4f4f4; color: #333; }
        .admin-container { max-width: 1200px; margin: 20px auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; margin-top:0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: middle;}
        th { background-color: #e9ecef; }
        .login-form, .action-form { margin-bottom: 20px; padding:15px; border:1px solid #ccc; border-radius:5px; background-color:#f9f9f9;}
        .login-form input, .action-form input, .action-form select, .action-form textarea { width: calc(100% - 22px); padding: 10px; margin-bottom:10px; border:1px solid #ccc; border-radius:4px;}
        .login-form button, .action-form button, .action-button { padding: 8px 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; }
        .action-button.approve { background-color: #28a745; }
        .action-button.reject { background-color: #dc3545; }
        .action-button.delete { background-color: #ffc107; color: black; }
        .action-button.edit { background-color: #17a2b8; }
        .error { color: red; margin-bottom: 15px; }
        .success { color: green; margin-bottom: 15px; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        nav.admin-nav { background-color: #343a40; padding: 10px 0; margin-bottom: 20px; text-align: center;}
        nav.admin-nav a { margin: 0 15px; text-decoration: none; color: #f8f9fa; font-weight: bold; }
        nav.admin-nav a.logout { color: #ffc107; }
        .status-pending { background-color: #fff3cd; }
        .status-approved { background-color: #d4edda; }
        .status-rejected { background-color: #f8d7da; }
        .poi-image-thumbnail { max-width: 80px; max-height: 80px; object-fit: cover; border-radius: 3px; }
        .icon-cell i { font-size: 1.5em; }
        .edit-icon-form { display: flex; align-items: center; }
        .edit-icon-form input[type="text"] { width: auto; margin-right: 5px; flex-grow:1; }
        .edit-icon-form .input-group-append button { font-size: 0.8em; flex-shrink: 0;}
        .current-icon-display { margin-left: 10px; font-size: 1.2em; flex-shrink: 0;}
        .admin-actions-section { margin-top: 20px; margin-bottom: 20px; padding: 15px; border: 1px dashed #ccc; background-color: #fdfdfd; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Admin Panel</h1>

        <?php if (!$isAdminLoggedIn): ?>
            <h2>Login</h2>
            <?php if (isset($login_error)): ?><p class="error"><?php echo htmlspecialchars($login_error); ?></p><?php endif; ?>
            <form method="POST" action="admin.php" class="login-form">
                <div><label for="username">Username:</label><input type="text" id="username" name="username" required></div>
                <div><label for="password">Password:</label><input type="password" id="password" name="password" required></div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
        <?php else: ?>
            <nav class="admin-nav">
                <a href="admin.php?view=dashboard">Dashboard</a>
                <a href="admin.php?view=pois">Manage POIs</a>
                <a href="admin.php?view=maps">Manage Maps</a>
                <a href="admin.php?action=logout" class="logout">Logout</a>
            </nav>
            <hr>

            <?php if (!empty($action_message)): ?>
                <div class="message <?php echo (strpos(strtolower($action_message), 'error') === false) ? 'success' : 'error'; ?>">
                    <?php echo $action_message; ?>
                </div>
            <?php endif; ?>

            <?php
            $view = isset($_GET['view']) ? $_GET['view'] : 'dashboard'; // Default to dashboard
            ?>

            <?php if ($view === 'dashboard'): ?>
                <h2>Dashboard & Admin Actions</h2>
                <div class="admin-actions-section">
                    <h3>Collaboration Session Cleanup</h3>
                    <p>Delete collaboration sessions that have not been active for more than 5 days.</p>
                    <form method="POST" action="admin.php?view=dashboard" onsubmit="return confirm('Are you sure you want to delete old collaboration sessions? This action cannot be undone.');">
                        <input type="hidden" name="admin_action" value="delete_old_sessions">
                        <button type="submit" class="btn btn-danger">Delete Old Sessions (older than 5 days)</button>
                    </form>
                </div>
                <!-- Add other dashboard items here -->
            <?php endif; ?>


            <?php if ($view === 'maps'): ?>
            <h2 id="map-table">Manage Maps</h2>
            <form method="POST" action="admin.php?view=maps" class="action-form">
                <h3>Add New Map</h3>
                <input type="hidden" name="map_action" value="add_map">
                <div class="form-group"><label for="map_name">Map Name:</label><input type="text" class="form-control" id="map_name" name="map_name" required></div>
                <div class="form-group"><label for="map_image_path">Map Image Path (e.g., maps/new_map.png):</label><input type="text" class="form-control" id="map_image_path" name="map_image_path" required></div>
                <button type="submit" class="btn btn-success">Add Map</button>
            </form>
            <hr>
            <h3>Existing Maps</h3>
            <table><thead><tr><th>ID</th><th>Name</th><th>Image Path</th><th>Actions</th></tr></thead><tbody>
            <?php $maps_result = $conn->query("SELECT id, name, image_path FROM maps ORDER BY name ASC"); if ($maps_result && $maps_result->num_rows > 0) { while ($map = $maps_result->fetch_assoc()): ?>
            <tr><td><?php echo htmlspecialchars($map['id']); ?></td><td><?php echo htmlspecialchars($map['name']); ?></td><td><?php echo htmlspecialchars($map['image_path']); ?></td><td><form method="POST" action="admin.php?view=maps" style="display:inline;" onsubmit="return confirm('Delete this map and ALL its POIs?');"><input type="hidden" name="map_action" value="delete_map"><input type="hidden" name="map_id" value="<?php echo htmlspecialchars($map['id']); ?>"><button type="submit" class="action-button reject">Delete</button></form></td></tr>
            <?php endwhile; } else { echo "<tr><td colspan='4'>No maps found.</td></tr>"; } if ($maps_result) $maps_result->free(); ?>
            </tbody></table>
            <?php endif; ?>


            <?php if ($view === 'pois'): ?>
            <h2 id="poi-table">Manage Points of Interest</h2>
            <?php
            $poi_sql = "SELECT p.id, p.name AS poi_name, p.latitude, p.longitude, p.status, p.image_path AS poi_image_path, p.fa_icon_class, m.name AS map_name FROM pois p JOIN maps m ON p.map_id = m.id ORDER BY p.status ASC, p.created_at DESC";
            $pois_result = $conn->query($poi_sql);
            ?>
            <div class="table-responsive"><table><thead><tr><th>ID</th><th>Name</th><th>Map</th><th>Coords</th><th>Status</th><th>Image</th><th>Icon</th><th>Actions</th></tr></thead><tbody>
            <?php if ($pois_result && $pois_result->num_rows > 0) { while ($poi = $pois_result->fetch_assoc()): $status_class = 'status-' . strtolower($poi['status']); ?>
            <tr class="<?php echo $status_class; ?>"><td><?php echo $poi['id']; ?></td><td><?php echo htmlspecialchars($poi['poi_name']); ?></td><td><?php echo htmlspecialchars($poi['map_name']); ?></td><td><?php echo number_format($poi['latitude'],2).', '.number_format($poi['longitude'],2); ?></td><td><?php echo ucfirst($poi['status']); ?></td>
            <td><?php if(!empty($poi['poi_image_path'])): ?><img src="<?php echo htmlspecialchars($poi['poi_image_path']); ?>" class="poi-image-thumbnail"><?php else: ?>N/A<?php endif; ?></td>
            <td class="icon-cell"><form method="POST" action="admin.php?view=pois" class="edit-icon-form"><input type="hidden" name="action" value="update_poi_icon"><input type="hidden" name="poi_id" value="<?php echo $poi['id']; ?>"><div class="input-group"><input type="text" name="fa_icon_class" class="form-control form-control-sm icon-input-field" value="<?php echo htmlspecialchars($poi['fa_icon_class']); ?>" placeholder="fas fa-star" id="fa-input-<?php echo $poi['id']; ?>"><div class="input-group-append"><button type="button" class="btn btn-secondary btn-sm icon-picker-trigger" data-iconpicker-input="input#fa-input-<?php echo $poi['id']; ?>" data-iconpicker-preview="#icon-preview-<?php echo $poi['id']; ?>"><i class="fas fa-icons"></i></button><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button></div></div><span class="current-icon-display" id="icon-preview-<?php echo $poi['id']; ?>"><?php if(!empty($poi['fa_icon_class'])):?><i class="<?php echo htmlspecialchars($poi['fa_icon_class']); ?>"></i><?php endif;?></span></form></td>
            <td> <?php if($poi['status']==='pending'):?><form method="POST" action="admin.php?view=pois" style="display:inline-block;margin-bottom:5px;"><input type="hidden" name="poi_id" value="<?php echo $poi['id'];?>"><input type="hidden" name="action" value="approve_poi"><button type="submit" class="action-button approve">Approve</button></form><form method="POST" action="admin.php?view=pois" style="display:inline-block;margin-bottom:5px;"><input type="hidden" name="poi_id" value="<?php echo $poi['id'];?>"><input type="hidden" name="action" value="reject_poi"><button type="submit" class="action-button reject">Reject</button></form><?php endif;?><form method="POST" action="admin.php?view=pois" style="display:inline-block;" onsubmit="return confirm('Delete POI?');"><input type="hidden" name="poi_id" value="<?php echo $poi['id'];?>"><input type="hidden" name="action" value="delete_poi"><button type="submit" class="action-button delete">Delete</button></form></td>
            </tr>
            <?php endwhile; } else { echo "<tr><td colspan='8'>No POIs found.</td></tr>"; } if ($pois_result) $pois_result->free(); ?>
            </tbody></table></div>
            <?php endif; ?>

        <?php endif; ?>
        <?php if ($conn) $conn->close(); ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-iconpicker/1.10.0/js/bootstrap-iconpicker.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        $('.icon-picker-trigger').each(function() {
            var $btn = $(this);
            $btn.iconpicker({ align:'center', arrowClass:'btn-secondary', arrowPrevIconClass:'fas fa-angle-left', arrowNextIconClass:'fas fa-angle-right', cols:8, iconset:'fontawesome6', labelHeader:'{0}/{1}', labelFooter:'{0}-{1} of {2}', placement:'bottom', rows:5, search:true, searchText:'Search...', selectedClass:'btn-success', unselectedClass:'btn-outline-secondary' })
            .on('change', function(e) { const inputTarget = $btn.data('iconpicker-input'); const previewTarget = $btn.data('iconpicker-preview'); if (e.icon) { $(inputTarget).val(e.icon); $(previewTarget).html(`<i class="${e.icon}"></i>`); } else { $(inputTarget).val(''); $(previewTarget).html(''); } });
        });
    });
    </script>
</body>
</html>