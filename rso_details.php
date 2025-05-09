<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['student', 'admin'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user       = $_SESSION['user'];
$user_id    = $user['id'];
$userName   = $user['name'] ?? 'Student';
$userEmail  = $user['email'] ?? 'email@university.edu';
$userAvatar = $user['avatar_filename'] ?? null;
$theme      = $_SESSION['theme'] ?? 'light';
$isDark     = ($theme === 'dark');
$role       = $user['role'];

$rso_id = $_GET['id'] ?? null;
if (!$rso_id) {
    echo "RSO not found.";
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    
    if (isset($_POST['delete_rso'])) {

        $tempStmt = $conn->prepare("SELECT created_by FROM rsos WHERE id=?");
        $tempStmt->bind_param("i", $rso_id);
        $tempStmt->execute();
        $tempRes = $tempStmt->get_result();
        $rsoRow = $tempRes->fetch_assoc();
        
        if (!$rsoRow || ($rsoRow['created_by'] != $user_id && $role !== 'superadmin')) {
            echo "You do not have permission to delete this RSO.";
            exit();
        }
        

        $delStmt = $conn->prepare("DELETE FROM events WHERE rso_id = ?");
        $delStmt->bind_param("i", $rso_id);
        $delStmt->execute();
        

        $delStmt = $conn->prepare("DELETE FROM rso_members WHERE rso_id = ?");
        $delStmt->bind_param("i", $rso_id);
        $delStmt->execute();
        

        $delStmt = $conn->prepare("DELETE FROM rsos WHERE id = ?");
        $delStmt->bind_param("i", $rso_id);
        $delStmt->execute();
        
        header("Location: student_rsos.php");
        exit();
    }
    

    if (isset($_POST['edit_name']) && !empty($_POST['new_name'])) {
        $new_name = trim($_POST['new_name']);
        
        $updStmt = $conn->prepare("UPDATE rsos SET name=? WHERE id=? AND created_by=?");
        $updStmt->bind_param("sii", $new_name, $rso_id, $user_id);
        $updStmt->execute();
    }

    // EDIT DESCRIPTION
    if (isset($_POST['edit_description']) && isset($_POST['new_description'])) {
        $new_desc = trim($_POST['new_description']);
        
        $updStmt = $conn->prepare("UPDATE rsos SET description=? WHERE id=? AND created_by=?");
        $updStmt->bind_param("sii", $new_desc, $rso_id, $user_id);
        $updStmt->execute();
    }


    if (isset($_POST['edit_photo']) && !empty($_FILES['new_photo']['name'])) {

        $uploadDir  = 'uploads/';
        $tmpName    = $_FILES['new_photo']['tmp_name'];
        $origName   = basename($_FILES['new_photo']['name']);

        $newPhotoName = time() . '_' . $origName;
        $uploadPath = $uploadDir . $newPhotoName;
        
        if (move_uploaded_file($tmpName, $uploadPath)) {

            $updStmt = $conn->prepare("UPDATE rsos SET photo=? WHERE id=? AND created_by=?");
            $updStmt->bind_param("sii", $newPhotoName, $rso_id, $user_id);
            $updStmt->execute();
        } else {
            echo "Error uploading new photo.";
        }
    }

}

$stmt = $conn->prepare("
    SELECT r.*, r.approved, u.email AS admin_email, u.name AS admin_name
    FROM rsos r
    JOIN users u ON r.created_by = u.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $rso_id);
$stmt->execute();
$rso = $stmt->get_result()->fetch_assoc();
if (!$rso) {
    echo "RSO not found.";
    exit();
}


$is_rso_approved = ($rso['approved'] == 1);
$is_pending      = ($rso['status'] !== 'active');
$is_rso_admin    = ($rso['created_by'] == $user_id);


if (isset($_POST['leave_rso'])) {
    $stmt = $conn->prepare("DELETE FROM rso_members WHERE rso_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $rso_id, $user_id);
    $stmt->execute();
    header("Location: student_rsos.php");
    exit();
}

$events = null;
if (!$is_pending) {
    $stmt = $conn->prepare("
        SELECT e.*, l.name AS location_name
        FROM events e
        JOIN locations l ON e.location_id = l.loc_id
        WHERE e.rso_id = ? AND e.approved = 1
        ORDER BY e.event_date, e.start_time
    ");
    $stmt->bind_param("i", $rso_id);
    $stmt->execute();
    $events = $stmt->get_result();
}
?>

<!-- HTML layout starts here (unchanged except for small inline forms/buttons) -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RSO Details</title>
    <style>
        :root {
            --bg-color: <?= $isDark ? '#1e1e26' : '#f4f5f9' ?>;
            --text-color: <?= $isDark ? '#fff' : '#333' ?>;
            --card-bg: <?= $isDark ? '#2a2f3b' : '#fff' ?>;
            --sidebar-bg: <?= $isDark ? '#181822' : '#1e3d7b' ?>;
            --sidebar-link: <?= $isDark ? '#ccc' : '#cbd3e6' ?>;
            --sidebar-link-hover: <?= $isDark ? '#2e3440' : '#2a4d95' ?>;
            --button-bg: <?= $isDark ? '#3e8ed0' : '#1e3d7b' ?>;
            --button-hover: <?= $isDark ? '#5aa1e3' : '#2a4d95' ?>;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        a { text-decoration: none; color: inherit; }
        ul { list-style-type: none; }

        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            color: #fff;
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }
        .sidebar .brand {
            margin-left: 20px;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 20px;
        }
        .sidebar .nav-links { flex: 1; }
        .sidebar .nav-links li {
            margin: 10px 0;
        }
        .sidebar .nav-links li a {
            display: block;
            padding: 10px 20px;
            font-size: 14px;
            color: var(--sidebar-link);
            transition: background 0.3s;
        }
        .sidebar .nav-links li a:hover {
            background-color: var(--sidebar-link-hover);
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--bg-color);
            overflow-y: auto;
        }

        .topbar {
            height: 60px;
            background-color: <?= $isDark ? '#2c2c38' : '#fff' ?>;
            display: flex;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid <?= $isDark ? '#444' : '#e7e7e7' ?>;
            justify-content: flex-end;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-text {
            text-align: right;
        }
        .user-text .name {
            font-weight: 600;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #999;
            border-radius: 50%;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .container {
            padding: 30px 40px;
        }

        .rso-image {
            width: 100%;
            max-height: 250px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .rso-header h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .rso-header p {
            line-height: 1.5;
            margin-bottom: 6px;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .event-card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            padding: 16px;
        }
        .event-card h4 {
            margin-bottom: 6px;
            font-size: 16px;
        }
        .event-card p {
            margin-bottom: 4px;
            font-size: 14px;
        }
        .event-card a.btn {
            display: inline-block;
            margin-top: 6px;
            background-color: var(--button-bg);
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
        }
        .event-card a.btn:hover {
            background-color: var(--button-hover);
        }

        .admin-actions {
            margin-top: 20px;
        }
        .admin-actions a, .admin-actions form button {
            display: inline-block;
            margin-right: 10px;
            background-color: var(--button-bg);
            color: #fff;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        .admin-actions form { display: inline; }
        .admin-actions a:hover, .admin-actions button:hover {
            background-color: var(--button-hover);
        }


        .inline-form {
            display: inline-block;
            margin-left: 10px;
        }
        .inline-form input[type="text"] {
            width: 140px;
            font-size: 14px;
            margin-right: 6px;
        }
        .inline-form button {
            padding: 5px 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="brand">CampusConnect</div>
    <ul class="nav-links">
        <?php if ($role === 'student'): ?>
            <li><a href="student_dashboard.php">Dashboard</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="my_joined_events.php">Joined Events</a></li>
            <li><a href="student_rsos.php">RSOs</a></li>
            <li><a href="event_list.php">All Events</a></li>
            <li><a href="external_events.php">UCF Feed</a></li>
            <li><a href="logout.php">Logout</a></li>
        <?php else: ?>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="my_joined_events.php">Joined Events</a></li>
            <li><a href="create_event.php">Create New Event</a></li>
            <li><a href="my_events.php">Created Events</a></li>
            <li><a href="student_rsos.php">RSOs</a></li>
            <li><a href="event_list.php">All Events</a></li>
            <li><a href="external_events.php">UCF Feed</a></li>
            <li><a href="logout.php">Logout</a></li>
        <?php endif; ?>
    </ul>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="user-info">
            <div class="user-text">
                <div class="name"><?= htmlspecialchars($userName) ?></div>
                <div class="email" style="font-size: 12px; opacity: 0.7;">
                    <?= htmlspecialchars($userEmail) ?>
                </div>
            </div>
            <div class="user-avatar">
                <?php if (!empty($userAvatar)): ?>
                    <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar">
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($rso['photo'])): ?>
            <img class="rso-image" src="uploads/<?= htmlspecialchars($rso['photo']) ?>" alt="RSO Photo">
        <?php endif; ?>

        <!-- If admin who created the RSO, allow changing the photo under it -->
        <?php if ($is_rso_admin && $role === 'admin'): ?>
        <form class="inline-form" method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
            <input type="hidden" name="edit_photo" value="1">
            <label>Change Photo:</label>
            <input type="file" name="new_photo" accept="image/*">
            <button type="submit">Save</button>
        </form>
        <?php endif; ?>

        <div class="rso-header">
            <h2>
                <?= htmlspecialchars($rso['name']) ?>
                <!-- Inline edit name if admin who created -->
                <?php if ($is_rso_admin && $role === 'admin'): ?>
                    <form class="inline-form" method="POST">
                        <input type="hidden" name="edit_name" value="1">
                        <input type="text" name="new_name" placeholder="New Name">
                        <button type="submit">Save</button>
                    </form>
                <?php endif; ?>
            </h2>

            <p><strong>Status:</strong> <?= ucfirst($rso['status']) ?></p>

            <p>
                <strong>Description:</strong> <?= htmlspecialchars($rso['description']) ?>
                <?php if ($is_rso_admin && $role === 'admin'): ?>
                    <form class="inline-form" method="POST">
                        <input type="hidden" name="edit_description" value="1">
                        <input type="text" name="new_description" placeholder="New Description">
                        <button type="submit">Save</button>
                    </form>
                <?php endif; ?>
            </p>

            <p><strong>Admin:</strong> <?= htmlspecialchars($rso['admin_name']) ?> (<?= htmlspecialchars($rso['admin_email']) ?>)</p>
        </div>

        <div class="admin-actions">
            <?php if ($role === 'admin' && $is_rso_admin): ?>
                <a href="view_rso_members.php?rso_id=<?= $rso_id ?>">View Members</a>
                <?php if (!$is_pending && $is_rso_approved): ?>
                    <a href="create_rso_event.php?rso_id=<?= $rso_id ?>">Create Event for This RSO</a>
                <?php endif; ?>
                
                <!-- DELETE RSO Button -->
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Are you sure you want to delete this RSO and ALL its events/members?');">
                    <input type="hidden" name="delete_rso" value="1">
                    <button type="submit">Delete RSO</button>
                </form>

            <?php elseif ($role === 'student'): ?>
                <form method="POST">
                    <button type="submit" name="leave_rso">Leave RSO</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!$is_pending && $events && $events->num_rows > 0): ?>
            <h3 style="margin-top: 30px;">RSO Events</h3>
            <div class="events-grid">
                <?php while ($event = $events->fetch_assoc()): ?>
                    <div class="event-card">
                        <h4><?= htmlspecialchars($event['name']) ?></h4>
                        <p><?= date("D, M j Y g:i A", strtotime($event['event_date'].' '.$event['start_time'])) ?></p>
                        <p><strong>Location:</strong> <?= htmlspecialchars($event['location_name']) ?></p>
                        <a class="btn" href="event_details.php?id=<?= $event['id'] ?>">View Details</a>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php elseif (!$is_pending): ?>
            <p>No events for this RSO yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
