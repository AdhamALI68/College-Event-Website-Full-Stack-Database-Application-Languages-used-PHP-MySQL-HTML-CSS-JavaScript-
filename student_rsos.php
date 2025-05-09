<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['student','admin'])) {
    header("Location: login.php");
    exit();
}


$user = $_SESSION['user'];
$userId = $user['id'];
$userName = $user['name'] ?? 'Student';
$userEmail = $user['email'] ?? 'email@university.edu';
$userAvatar = $user['avatar_filename'] ?? null;
$role = $_SESSION['user']['role'] ?? null;

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$theme = $_SESSION['theme'] ?? 'light';
$isDark = $theme === 'dark';

$query = "
    SELECT r.id, r.name, r.approved, r.created_by, r.photo
    FROM rsos r
    JOIN rso_members rm ON r.id = rm.rso_id
    WHERE rm.user_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$rsos = $stmt->get_result();


$rsoUpdateStmt = $conn->prepare("SELECT COUNT(*) AS count FROM rso_members WHERE rso_id = ?");
$resetApprovalStmt = $conn->prepare("UPDATE rsos SET approved = 0 WHERE id = ?");

$updatedRSOs = [];
while ($rso = $rsos->fetch_assoc()) {
    $rsoId = $rso['id'];


    $rsoUpdateStmt->bind_param("i", $rsoId);
    $rsoUpdateStmt->execute();
    $memberResult = $rsoUpdateStmt->get_result();
    $memberCount = $memberResult->fetch_assoc()['count'] ?? 0;


    if ($rso['approved'] && $memberCount < 5) {
        $resetApprovalStmt->bind_param("i", $rsoId);
        $resetApprovalStmt->execute();
        $rso['approved'] = 0; 
    }

    $updatedRSOs[] = $rso;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>CampusConnect - My RSOs</title>
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
            font-family: "Segoe UI", Arial, sans-serif;
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
            background: #ccc;
            border-radius: 50%;
        }

        .content-section {
            padding: 30px 40px;
        }

        .content-section h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .top-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: -40px;
        }

        .create-button {
            background-color: var(--button-bg);
            color: white;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
        }
        .create-button:hover {
            background-color: var(--button-hover);
        }

        .rso-box {
            background-color: var(--card-bg);
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        .rso-box:hover {
            background-color: <?= $isDark ? '#363a45' : '#f0f2f5' ?>;
        }

        .rso-photo {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .pending {
            color: orange;
            font-weight: 600;
            font-size: 14px;
            margin-left: 10px;
        }

        .admin-label {
            color: limegreen;
            font-weight: 600;
            font-size: 14px;
            margin-left: 10px;
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
    <?php elseif ($role === 'admin'): ?>
    <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="profile.php">Profile</a></li>
        <li><a href="admin_settings.php">Settings</a></li>
        <li><a href="my_joined_events.php">Joined Events</a></li>
        <li><a href="create_event.php">Create New Event</a></li>
        <li><a href="my_events.php">Created Events</a></li>
        <li><a href="student_rsos.php">RSOs</a></li>
        <li><a href="event_list.php">All Events</a></li>
        <li><a href="external_events.php">UCF Feed</a></li>
        <li><a href="logout.php">Logout</a></li>
    <?php elseif ($role === 'superadmin'): ?>
      <li><a href="superadmin_dashboard.php">Dashboard</a></li>
      <li><a href="logout.php">Logout</a></li>
    <?php endif; ?>
  </ul>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="user-info">
            <div class="user-text">
                <div class="name"><?= htmlspecialchars($userName) ?></div>
                <div class="email" style="font-size: 12px; opacity: 0.7;"><?= htmlspecialchars($userEmail) ?></div>
            </div>
            <div class="user-avatar">
                <?php if (!empty($userAvatar)): ?>
                    <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content-section">
        <h1>My RSOs</h1>
        <div class="top-buttons">
            <a href="create_rso.php" class="create-button">+ Create RSO Event</a>
            <a href="join_rso.php" class="create-button">+ Join New RSO</a>
        </div>

        <?php if ($rsos->num_rows > 0): ?>
<?php foreach ($updatedRSOs as $rso): ?>
                <a href="rso_details.php?id=<?= $rso['id'] ?>" style="text-decoration: none;">
                    <div class="rso-box">
                        <?php if (!empty($rso['photo'])): ?>
                            <img class="rso-photo" src="uploads/<?= htmlspecialchars($rso['photo']) ?>" alt="RSO Photo">
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($rso['name']) ?></strong>
                        <?php if (!$rso['approved']): ?>
                            <span class="pending">(Pending Approval)</span>
                        <?php endif; ?>
                        <?php if ((int)$rso['created_by'] === $userId): ?>
                            <span class="admin-label">(Admin)</span>
                        <?php endif; ?>
                    </div>
                </a>
<?php endforeach; ?>
        <?php else: ?>
            <p style="opacity: 0.7;">You have not joined any RSOs yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
