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

// --- POI Action Handling (Approve/Reject/Delete/Update Icon) ---
if ($isAdminLoggedIn && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && 
    in_array($_POST['action'], ['approve_poi', 'reject_poi', 'delete_poi', 'update_poi_icon'])) {
    $poi_id = isset($_POST['poi_id']) ? (int)$_POST['poi_id'] : 0;
    $message_poi = '';
    $message_poi_type = 'error';

    if ($poi_id > 0) {
        if ($_POST['action'] === 'approve_poi') {
            $stmt = $conn->prepare("UPDATE pois SET status = 'approved' WHERE id = ?");
            $stmt->bind_param("i", $poi_id);
            if($stmt->execute()) { $message_poi = "POI Approved."; $message_poi_type = 'success';} 
            else { $message_poi = "Error approving POI: ".$stmt->error; }
            $stmt->close();
        } elseif ($_POST['action'] === 'reject_poi') {
            $stmt = $conn->prepare("UPDATE pois SET status = 'rejected' WHERE id = ?");
            $stmt->bind_param("i", $poi_id);
            if($stmt->execute()) { $message_poi = "POI Rejected."; $message_poi_type = 'success';}
            else { $message_poi = "Error rejecting POI: ".$stmt->error; }
            $stmt->close();
        } elseif ($_POST['action'] === 'delete_poi') {
            $stmt = $conn->prepare("DELETE FROM pois WHERE id = ?");
            $stmt->bind_param("i", $poi_id);
            if($stmt->execute()) { $message_poi = "POI Deleted."; $message_poi_type = 'success';}
            else { $message_poi = "Error deleting POI: ".$stmt->error; }
            $stmt->close();
        } elseif ($_POST['action'] === 'update_poi_icon') { 
            $fa_icon_class = isset($_POST['fa_icon_class']) ? trim($_POST['fa_icon_class']) : null;
            if (empty($fa_icon_class)) $fa_icon_class = null;
            $stmt = $conn->prepare("UPDATE pois SET fa_icon_class = ? WHERE id = ?");
            $stmt->bind_param("si", $fa_icon_class, $poi_id);
            if($stmt->execute()) { $message_poi = "POI Icon Updated."; $message_poi_type = 'success';}
            else { $message_poi = "Error updating icon: ".$stmt->error; }
            $stmt->close();
        }
        $_SESSION['admin_message'] = ['text' => $message_poi, 'type' => $message_poi_type];
        header('Location: admin.php?view=pois#poi-table'); 
        exit;
    } else {
        $_SESSION['admin_message'] = ['text' => "Invalid POI ID for action.", 'type' => 'error'];
        header('Location: admin.php?view=pois#poi-table');
        exit;
    }
}

// --- Map Action Handling ---
if ($isAdminLoggedIn && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['map_action'])) {
    $message_map = '';
    $message_map_type = 'error';
    if ($_POST['map_action'] === 'add_map' && !empty($_POST['map_name']) && !empty($_POST['map_image_path'])) {
        $map_name = trim($_POST['map_name']);
        $map_image_path = trim($_POST['map_image_path']);
        $stmt = $conn->prepare("INSERT INTO maps (name, image_path) VALUES (?, ?)");
        $stmt->bind_param("ss", $map_name, $map_image_path);
        if($stmt->execute()){ $message_map = "Map Added."; $message_map_type = 'success'; }
        else { $message_map = "Error adding map: ".$stmt->error; }
        $stmt->close();
    } elseif ($_POST['map_action'] === 'delete_map' && isset($_POST['map_id'])) {
        $map_id_to_delete = (int)$_POST['map_id'];
        $stmt = $conn->prepare("DELETE FROM maps WHERE id = ?");
        $stmt->bind_param("i", $map_id_to_delete);
        if($stmt->execute()){ $message_map = "Map Deleted."; $message_map_type = 'success'; }
        else { $message_map = "Error deleting map: ".$stmt->error; }
        $stmt->close();
    }
    $_SESSION['admin_message'] = ['text' => $message_map, 'type' => $message_map_type];
    header('Location: admin.php?view=maps#map-table');
    exit;
}

// --- POI Connection Action Handling ---
if ($isAdminLoggedIn && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['connection_action'])) {
    $message_conn = '';
    $message_conn_type = 'error'; 

    if ($_POST['connection_action'] === 'add_connection') {
        $map_id_conn = isset($_POST['map_id_conn']) ? (int)$_POST['map_id_conn'] : 0;
        $poi_id_from = isset($_POST['poi_id_from']) ? (int)$_POST['poi_id_from'] : 0;
        $poi_id_to = isset($_POST['poi_id_to']) ? (int)$_POST['poi_id_to'] : 0;
        $line_style_json = isset($_POST['line_style']) ? trim($_POST['line_style']) : null;

        if (empty($line_style_json) || json_decode($line_style_json) === null) {
            $line_style_json = null; 
        }

        if ($map_id_conn > 0 && $poi_id_from > 0 && $poi_id_to > 0 && $poi_id_from !== $poi_id_to) {
            $check_stmt = $conn->prepare("SELECT id FROM poi_connections WHERE map_id = ? AND poi_id_from = ? AND poi_id_to = ?");
            $check_stmt->bind_param("iii", $map_id_conn, $poi_id_from, $poi_id_to);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows === 0) {
                $stmt = $conn->prepare("INSERT INTO poi_connections (map_id, poi_id_from, poi_id_to, line_style) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiis", $map_id_conn, $poi_id_from, $poi_id_to, $line_style_json);
                if ($stmt->execute()) { $message_conn = "Connection added successfully!"; $message_conn_type = 'success'; } 
                else { $message_conn = "Error adding connection: " . $stmt->error; }
                $stmt->close();
            } else { $message_conn = "Error: This connection already exists."; }
            $check_stmt->close();
        } else { $message_conn = "Invalid input. Ensure map and POIs are selected, and POIs are different."; }
    } elseif ($_POST['connection_action'] === 'delete_connection' && isset($_POST['connection_id'])) {
        $connection_id = (int)$_POST['connection_id'];
        if ($connection_id > 0) {
            $stmt = $conn->prepare("DELETE FROM poi_connections WHERE id = ?");
            $stmt->bind_param("i", $connection_id);
            if ($stmt->execute()) { $message_conn = "Connection deleted successfully!"; $message_conn_type = 'success'; } 
            else { $message_conn = "Error deleting connection: " . $stmt->error; }
            $stmt->close();
        } else { $message_conn = "Invalid connection ID."; }
    }
    $_SESSION['admin_message'] = ['text' => $message_conn, 'type' => $message_conn_type];
    header('Location: admin.php?view=connections#connections-table');
    exit;
}

// Fetch data for dropdowns
$all_maps_for_select = [];
$maps_q = $conn->query("SELECT id, name FROM maps ORDER BY name ASC");
if ($maps_q) { while ($row = $maps_q->fetch_assoc()) { $all_maps_for_select[] = $row; } $maps_q->free(); }

$pois_by_map = [];
$pois_q_all = $conn->query("SELECT id, name, map_id FROM pois WHERE status = 'approved' ORDER BY map_id, name ASC");
if ($pois_q_all) { while ($row = $pois_q_all->fetch_assoc()) { $pois_by_map[$row['map_id']][] = ['id' => $row['id'], 'name' => $row['name']]; } $pois_q_all->free(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        .form-group { margin-bottom: 1rem; } 
        .form-control { display: block; width: 100%; padding: .375rem .75rem; font-size: 1rem; line-height: 1.5; color: #495057; background-color: #fff; background-clip: padding-box; border: 1px solid #ced4da; border-radius: .25rem; transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out; box-sizing: border-box;}
        select.form-control { height: calc(1.5em + .75rem + 2px); } /* Adjusted height for select */

        .login-form button, .action-form button, .action-button { padding: 8px 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; }
        .action-button.approve { background-color: #28a745; } .action-button.reject { background-color: #dc3545; }
        .action-button.delete { background-color: #ffc107; color: black; } .action-button.edit { background-color: #17a2b8; }
        .error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; padding: .75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem; }
        .success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; padding: .75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem; }
        nav.admin-nav { background-color: #343a40; padding: 10px 0; margin-bottom: 20px; text-align: center;}
        nav.admin-nav a { margin: 0 15px; text-decoration: none; color: #f8f9fa; font-weight: bold; }
        nav.admin-nav a.logout { color: #ffc107; }
        .status-pending { background-color: #fff3cd; } .status-approved { background-color: #d4edda; } .status-rejected { background-color: #f8d7da; }
        .poi-image-thumbnail { max-width: 80px; max-height: 80px; object-fit: cover; border-radius: 3px; }
        .icon-cell i { font-size: 1.5em; } .edit-icon-form input[type="text"] { width: auto; margin-right: 5px; }
        .edit-icon-form .input-group-append button { font-size: 0.8em; } .current-icon-display { margin-left: 10px; font-size: 1.2em; }
        textarea.form-control { min-height: 60px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Admin Panel</h1>
        <?php if (!$isAdminLoggedIn): ?>
            <h2>Login</h2>
            <?php if (isset($login_error)): ?><p class="error"><?php echo htmlspecialchars($login_error); ?></p><?php endif; ?>
            <form method="POST" action="admin.php" class="login-form">
                <div><label for="username">Username:</label><input type="text" class="form-control" id="username" name="username" required></div>
                <div><label for="password">Password:</label><input type="password" class="form-control" id="password" name="password" required></div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
        <?php else: ?>
            <nav class="admin-nav">
                <a href="admin.php?view=pois">Manage POIs</a>
                <a href="admin.php?view=maps">Manage Maps</a>
                <a href="admin.php?view=connections">Manage POI Connections</a>
                <a href="admin.php?action=logout" class="logout">Logout</a>
            </nav>
            <hr>
            <?php
            if (isset($_SESSION['admin_message'])) {
                $admin_msg = $_SESSION['admin_message'];
                echo '<div class="' . htmlspecialchars($admin_msg['type']) . '">' . htmlspecialchars($admin_msg['text']) . '</div>';
                unset($_SESSION['admin_message']);
            }
            $view = isset($_GET['view']) ? $_GET['view'] : 'pois';
            ?>

            <?php if ($view === 'maps'): ?>
            <h2 id="map-table">Manage Maps</h2>
            <form method="POST" action="admin.php" class="action-form">
                <h3>Add New Map</h3> <input type="hidden" name="map_action" value="add_map">
                <div class="form-group"><label for="map_name">Map Name:</label><input type="text" class="form-control" id="map_name" name="map_name" required></div>
                <div class="form-group"><label for="map_image_path">Map Image Path (e.g., maps/new.png):</label><input type="text" class="form-control" id="map_image_path" name="map_image_path" required></div>
                <button type="submit" class="btn btn-success">Add Map</button>
            </form><hr><h3>Existing Maps</h3>
            <div class="table-responsive"><table class="table table-striped table-bordered"><thead><tr><th>ID</th><th>Name</th><th>Path</th><th>Actions</th></tr></thead><tbody>
            <?php $maps_res = $conn->query("SELECT id, name, image_path FROM maps ORDER BY name ASC"); if($maps_res && $maps_res->num_rows > 0){while($map=$maps_res->fetch_assoc()):?>
            <tr><td><?php echo $map['id'];?></td><td><?php echo htmlspecialchars($map['name']);?></td><td><?php echo htmlspecialchars($map['image_path']);?></td>
            <td><form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete map & ALL POIs?');"><input type="hidden" name="map_action" value="delete_map"><input type="hidden" name="map_id" value="<?php echo $map['id'];?>"><button type="submit" class="action-button reject">Del</button></form></td></tr>
            <?php endwhile;}else{echo "<tr><td colspan='4'>No maps.</td></tr>";} if($maps_res)$maps_res->free();?></tbody></table></div>
            <?php endif; ?>

            <?php if ($view === 'pois'): ?>
            <h2 id="poi-table">Manage Points of Interest</h2>
            <div class="table-responsive"><table class="table table-striped table-bordered"><thead><tr><th>ID</th><th>Name</th><th>Map</th><th>Coords</th><th>Status</th><th>Img</th><th>Icon</th><th>Actions</th></tr></thead><tbody>
            <?php $poi_sql="SELECT p.id,p.name AS poi_name,p.latitude,p.longitude,p.status,p.image_path AS poi_image_path,p.fa_icon_class,m.name AS map_name FROM pois p JOIN maps m ON p.map_id=m.id ORDER BY p.status ASC,p.created_at DESC"; $pois_res=$conn->query($poi_sql); if($pois_res && $pois_res->num_rows > 0){while($poi=$pois_res->fetch_assoc()): $s_class='status-'.strtolower($poi['status']);?>
            <tr class="<?php echo $s_class;?>"><td><?php echo $poi['id'];?></td><td><?php echo htmlspecialchars($poi['poi_name']);?></td><td><?php echo htmlspecialchars($poi['map_name']);?></td><td><?php echo round($poi['latitude'],2).', '.round($poi['longitude'],2);?></td><td><?php echo ucfirst($poi['status']);?></td>
            <td><?php if(!empty($poi['poi_image_path'])):?><img src="<?php echo htmlspecialchars($poi['poi_image_path']);?>" class="poi-image-thumbnail"><?php else:?>N/A<?php endif;?></td>
            <td class="icon-cell"><form method="POST" action="admin.php" class="edit-icon-form form-inline"><input type="hidden" name="action" value="update_poi_icon"><input type="hidden" name="poi_id" value="<?php echo $poi['id'];?>"><div class="input-group"><input type="text" name="fa_icon_class" class="form-control form-control-sm icon-input-field" value="<?php echo htmlspecialchars($poi['fa_icon_class']);?>" placeholder="fas fa-star" id="fa-input-<?php echo $poi['id'];?>"><div class="input-group-append"><button type="button" class="btn btn-secondary btn-sm icon-picker-trigger" data-iconpicker-input="input#fa-input-<?php echo $poi['id'];?>" data-iconpicker-preview="#icon-preview-<?php echo $poi['id'];?>"><i class="fas fa-icons"></i></button><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button></div></div><span class="current-icon-display" id="icon-preview-<?php echo $poi['id'];?>"><?php if(!empty($poi['fa_icon_class'])):?><i class="<?php echo htmlspecialchars($poi['fa_icon_class']);?>"></i><?php endif;?></span></form></td>
            <td> <?php if($poi['status']==='pending'):?><form method="POST" action="admin.php" style="display:inline-block;margin-bottom:5px;"><input type="hidden" name="poi_id" value="<?php echo $poi['id'];?>"><input type="hidden" name="action" value="approve_poi"><button type="submit" class="action-button approve">App</button></form><form method="POST" action="admin.php" style="display:inline-block;margin-bottom:5px;"><input type="hidden" name="poi_id" value="<?php echo $poi['id'];?>"><input type="hidden" name="action" value="reject_poi"><button type="submit" class="action-button reject">Rej</button></form><?php endif;?><form method="POST" action="admin.php" style="display:inline-block;" onsubmit="return confirm('Delete POI?');"><input type="hidden" name="poi_id" value="<?php echo $poi['id'];?>"><input type="hidden" name="action" value="delete_poi"><button type="submit" class="action-button delete">Del</button></form></td></tr>
            <?php endwhile;}else{echo "<tr><td colspan='8'>No POIs.</td></tr>";} if($pois_res)$pois_res->free();?></tbody></table></div>
            <?php endif; ?>

            <?php if ($view === 'connections'): ?>
            <h2 id="connections-table">Manage POI Connections</h2>
            <div class="action-form">
                <h3>Add New POI Connection</h3>
                <form method="POST" action="admin.php">
                    <input type="hidden" name="connection_action" value="add_connection">
                    <div class="form-group"><label for="map_id_conn_select">Select Map:</label><select name="map_id_conn" id="map_id_conn_select" class="form-control" required><option value="">-- Select Map --</option><?php foreach($all_maps_for_select as $map_item):?><option value="<?php echo $map_item['id'];?>"><?php echo htmlspecialchars($map_item['name']);?></option><?php endforeach;?></select></div>
                    <div class="form-group"><label for="poi_id_from_select">From POI:</label><select name="poi_id_from" id="poi_id_from_select" class="form-control" required disabled><option value="">-- Select Map First --</option></select></div>
                    <div class="form-group"><label for="poi_id_to_select">To POI:</label><select name="poi_id_to" id="poi_id_to_select" class="form-control" required disabled><option value="">-- Select Map First --</option></select></div>
                    <div class="form-group"><label for="line_style">Line Style (Optional JSON):</label><textarea name="line_style" class="form-control" rows="2" placeholder='{"color": "#FFFF00", "weight": 2}'></textarea></div>
                    <button type="submit" class="btn btn-success">Add Connection</button>
                </form>
            </div><hr><h3>Existing Connections</h3>
            <div class="table-responsive"><table class="table table-striped table-bordered"><thead><tr><th>ID</th><th>Map</th><th>From POI (ID)</th><th>To POI (ID)</th><th>Style</th><th>Actions</th></tr></thead><tbody>
            <?php $conn_sql="SELECT pc.id, m.name as map_name, p_from.name as poi_from_name, pc.poi_id_from, p_to.name as poi_to_name, pc.poi_id_to, pc.line_style FROM poi_connections pc JOIN maps m ON pc.map_id = m.id JOIN pois p_from ON pc.poi_id_from = p_from.id JOIN pois p_to ON pc.poi_id_to = p_to.id ORDER BY m.name, p_from.name, p_to.name"; $conn_res=$conn->query($conn_sql); if($conn_res && $conn_res->num_rows > 0){while($cr=$conn_res->fetch_assoc()):?>
            <tr><td><?php echo $cr['id'];?></td><td><?php echo htmlspecialchars($cr['map_name']);?></td><td><?php echo htmlspecialchars($cr['poi_from_name']);?> (<?php echo $cr['poi_id_from'];?>)</td><td><?php echo htmlspecialchars($cr['poi_to_name']);?> (<?php echo $cr['poi_id_to'];?>)</td><td><pre><?php echo htmlspecialchars($cr['line_style']?:'Default');?></pre></td>
            <td><form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete connection?');"><input type="hidden" name="connection_action" value="delete_connection"><input type="hidden" name="connection_id" value="<?php echo $cr['id'];?>"><button type="submit" class="action-button reject">Del</button></form></td></tr>
            <?php endwhile;}else{echo "<tr><td colspan='6'>No connections.</td></tr>";} if($conn_res)$conn_res->free();?></tbody></table></div>
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
            var $button = $(this); var inputTarget = $button.data('iconpicker-input'); var previewTarget = $button.data('iconpicker-preview');
            $button.iconpicker({ align:'center', arrowClass:'btn-secondary', arrowPrevIconClass:'fas fa-angle-left', arrowNextIconClass:'fas fa-angle-right', cols:8, iconset:'fontawesome6', labelHeader:'{0}/{1}', labelFooter:'{0}-{1} of {2}', placement:'bottom', rows:5, search:true, searchText:'Search...', selectedClass:'btn-success', unselectedClass:'btn-outline-secondary'})
            .on('change', function(e) { if(e.icon){$(inputTarget).val(e.icon); $(previewTarget).html('<i class="'+e.icon+'"></i>');}else{$(inputTarget).val(''); $(previewTarget).html('');}});
        });
        var poisByMapData = <?php echo json_encode($pois_by_map); ?>;
        $('#map_id_conn_select').on('change', function() {
            var selectedMapId = $(this).val(); var $poiFromSelect = $('#poi_id_from_select'); var $poiToSelect = $('#poi_id_to_select');
            $poiFromSelect.empty().append('<option value="">-- Select POI --</option>').prop('disabled', true);
            $poiToSelect.empty().append('<option value="">-- Select POI --</option>').prop('disabled', true);
            if (selectedMapId && poisByMapData[selectedMapId]) {
                poisByMapData[selectedMapId].forEach(function(poi) { $poiFromSelect.append('<option value="'+poi.id+'">'+poi.name+' (ID: '+poi.id+')</option>'); $poiToSelect.append('<option value="'+poi.id+'">'+poi.name+' (ID: '+poi.id+')</option>'); });
                $poiFromSelect.prop('disabled', false); $poiToSelect.prop('disabled', false);
            } else if (selectedMapId) { $poiFromSelect.empty().append('<option value="">-- No POIs for map --</option>'); $poiToSelect.empty().append('<option value="">-- No POIs for map --</option>');}
        });
    });
    </script>
</body>
</html>