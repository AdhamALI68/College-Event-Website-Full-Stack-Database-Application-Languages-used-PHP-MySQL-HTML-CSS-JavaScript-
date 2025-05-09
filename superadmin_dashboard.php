<?php
session_start();


if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}


$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if (isset($_POST['approve_event'])) {
    $event_id = intval($_POST['event_id']);
    $conn->query("UPDATE events SET approved = 1 WHERE id = $event_id");
}


if (isset($_POST['approve_rso'])) {
    $rso_id = intval($_POST['rso_id']);


    $conn->query("UPDATE rsos SET approved = 1 WHERE id = $rso_id");


    $creatorResult = $conn->query("SELECT created_by FROM rsos WHERE id = $rso_id");
    if ($creatorResult && $creatorRow = $creatorResult->fetch_assoc()) {
        $creator_id = intval($creatorRow['created_by']);

  
        $conn->query("UPDATE rso_members SET role = 'admin' WHERE rso_id = $rso_id AND user_id = $creator_id");


        $conn->query("UPDATE users SET role = 'admin' WHERE id = $creator_id");
    }
}


$pending_events_sql = "
    SELECT e.*, l.name AS location_name
    FROM events e
    JOIN locations l ON e.location_id = l.loc_id
    WHERE e.type = 'public'
      AND e.approved = 0
    ORDER BY e.event_date, e.start_time
";
$pending_events = $conn->query($pending_events_sql);


$pending_rsos_sql = "
    SELECT *
    FROM rsos
    WHERE approved = 0
      AND status = 'active'
";

$pending_rsos = $conn->query($pending_rsos_sql);


$userName   = $_SESSION['user']['name']  ?? 'Super Admin';
$userEmail  = $_SESSION['user']['email'] ?? 'admin@university.edu';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;
$theme      = $_SESSION['theme'] ?? 'light';
$isDark     = ($theme === 'dark');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusConnect - Super Admin</title>
    <style>
      :root {
         --bg-color: <?= $isDark ? '#1e1e26' : '#f4f5f9' ?>;
         --text-color: <?= $isDark ? '#e0e0e0' : '#333' ?>;
         --heading-color: <?= $isDark ? '#ffffff' : '#1e3d7b' ?>;
         --muted-text-color: <?= $isDark ? '#b8b8b8' : '#555' ?>;
         --card-bg: <?= $isDark ? '#2a2f3b' : '#fff' ?>;
         --sidebar-bg: <?= $isDark ? '#181822' : '#1e3d7b' ?>;
         --sidebar-link: <?= $isDark ? '#ccc' : '#cbd3e6' ?>;
         --sidebar-link-hover: <?= $isDark ? '#2e3440' : '#2a4d95' ?>;
         --topbar-bg: <?= $isDark ? '#2c2c38' : '#fff' ?>;
         --topbar-border: <?= $isDark ? '#444' : '#e7e7e7' ?>;
         --approve-button-bg: #1e3d7b;
         --approve-button-hover: #2a4d95;
      }

      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      body {
        font-family: "Segoe UI", Arial, sans-serif;
        background-color: var(--bg-color);
        color: var(--text-color);
        display: flex;
        height: 100vh;
        overflow: hidden;
      }
      a {
        text-decoration: none;
        color: inherit;
      }
      ul {
        list-style-type: none;
      }
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
        overflow-y: auto;
      }
      .topbar {
        height: 60px;
        background-color: var(--topbar-bg);
        display: flex;
        align-items: center;
        padding: 0 20px;
        border-bottom: 1px solid var(--topbar-border);
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
        overflow: hidden;
      }
      .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .content-section {
        padding: 20px;
      }
      .content-section h1 {
        margin-bottom: 20px;
        font-size: 22px;
        color: var(--heading-color);
      }
      .card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
        gap: 20px;
      }
      .card-item {
        background-color: var(--card-bg);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        padding: 15px;
      }
      .item-image {
        width: 100%;
        height: 160px;
        background: #ccc;
        overflow: hidden;
        border-radius: 6px;
        margin-bottom: 10px;
      }
      .item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .item-header {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text-color);
      }
      .item-details {
        font-size: 14px;
        color: var(--text-color);
        margin-bottom: 8px;
        line-height: 1.5;
      }
      .card-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: auto;
      }
      .approve-button {
        background-color: var(--approve-button-bg);
        color: #fff;
        padding: 6px 14px;
        font-size: 13px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        transition: background 0.3s;
      }
      .approve-button:hover {
        background-color: var(--approve-button-hover);
      }
      .main-content::-webkit-scrollbar {
        width: 8px;
      }
      .main-content::-webkit-scrollbar-track {
        background-color: var(--bg-color);
      }
      .main-content::-webkit-scrollbar-thumb {
        background-color: #666;
        border-radius: 4px;
      }
      @media (max-width: 768px) {
        .sidebar {
          width: 60px;
          padding: 20px 0;
        }
        .sidebar .brand {
          display: none;
        }
        .sidebar .nav-links li a {
          text-align: center;
          padding: 10px 0;
        }
        .main-content {
          margin-left: 60px;
        }
      }
      @media (max-width: 576px) {
        .content-section h1 {
          font-size: 18px;
        }
      }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="brand">CampusConnect</div>
        <ul class="nav-links">
            <li><a href="superadmin_dashboard.php">Dashboard</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="settings.php">Settings</a></li>
            <li><a href="universities.php">Universities</a></li>
            <li><a href="superadmin_manage_events.php">Edit Events</a></li>
            <li><a href="manage_users.php">Manage Users</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- TOPBAR -->
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
                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar"
                             style="object-fit:cover; width:40px; height:40px; border-radius:50%;">
                    <?php endif; ?>
                </div>
            </div>
        </div>

<!-- PENDING PUBLIC EVENTS -->
<div class="content-section">
    <h1>Pending Public Events</h1>
    <?php if ($pending_events && $pending_events->num_rows > 0): ?>
        <div class="card-grid">
            <?php while ($event = $pending_events->fetch_assoc()): ?>
                <div class="card-item">
                    <!-- Always show an image (event or placeholder) -->
                    <div class="item-image">
                        <img src="<?= !empty($event['image_filename']) 
                            ? htmlspecialchars($event['image_filename']) 
                            : 'assets/placeholder.jpg' ?>"
                            alt="Event Image">
                    </div>

                    <div class="item-header"><?= htmlspecialchars($event['name']) ?></div>
                    <div class="item-details">
                        <?php if (!empty($event['description'])): ?>
                            <p><strong>Description:</strong> <?= htmlspecialchars($event['description']) ?></p>
                        <?php endif; ?>
                        <p>
                            <strong>Date/Time:</strong>
                            <?= date("M j, Y", strtotime($event['event_date'])) ?>
                            (<?= substr($event['start_time'], 0, 5) ?> - <?= substr($event['end_time'], 0, 5) ?>)
                        </p>
                        <p><strong>Location:</strong> <?= htmlspecialchars($event['location_name']) ?></p>
                        <p><strong>Created By (User ID):</strong> <?= htmlspecialchars($event['created_by']) ?></p>
                    </div>
                    <div class="card-actions">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                            <button class="approve-button" type="submit" name="approve_event">Approve</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No pending public events at the moment.</p>
    <?php endif; ?>
</div>

<!-- PENDING RSOs -->
<div class="content-section">
    <h1>RSOs Pending Approval</h1>
    <?php if ($pending_rsos && $pending_rsos->num_rows > 0): ?>
        <div class="card-grid">
            <?php while ($rso = $pending_rsos->fetch_assoc()): ?>
                <div class="card-item">
                    <!-- Always show an image (uploaded photo or placeholder) -->
                    <div class="item-image">
                        <img src="<?= !empty($rso['photo']) 
                            ? 'uploads/' . htmlspecialchars($rso['photo']) 
                            : 'assets/placeholder.jpg' ?>"
                            alt="RSO Photo">
                    </div>

                    <div class="item-header"><?= htmlspecialchars($rso['name']) ?></div>
                    <div class="item-details">
                        <?php if (!empty($rso['description'])): ?>
                            <p><strong>Description:</strong> <?= htmlspecialchars($rso['description']) ?></p>
                        <?php endif; ?>
                        <p><strong>University ID:</strong> <?= htmlspecialchars($rso['university_id']) ?></p>
                        <p><strong>Created By (User ID):</strong> <?= htmlspecialchars($rso['created_by']) ?></p>
                        <p><strong>Status:</strong> <?= htmlspecialchars($rso['status']) ?></p>
                    </div>
                    <div class="card-actions">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="rso_id" value="<?= $rso['id'] ?>">
                            <button class="approve-button" type="submit" name="approve_rso">Approve</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>No RSOs pending approval at the moment.</p>
    <?php endif; ?>
</div>

    </div>
</body>
</html>
