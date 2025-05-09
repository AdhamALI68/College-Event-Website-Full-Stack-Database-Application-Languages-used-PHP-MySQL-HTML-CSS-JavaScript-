<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['student','admin'])) {
    header("Location: login.php");
    exit();
}

$user       = $_SESSION['user'];
$user_id    = $user['id'];
$role       = $user['role'];
$userName   = $user['name'] ?? 'Student';
$userEmail  = $user['email'] ?? 'email@university.edu';
$userAvatar = $user['avatar_filename'] ?? null;
$theme      = $_SESSION['theme'] ?? 'light';
$isDark     = ($theme === 'dark');

$rso_id = $_GET['rso_id'] ?? null;
if (!$rso_id) {
    echo "No RSO specified.";
    exit();
}

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_member_id'])) {
    $delete_id = intval($_POST['delete_member_id']);
    $stmt_del = $conn->prepare("DELETE FROM rso_members WHERE rso_id = ? AND user_id = ?");
    $stmt_del->bind_param("ii", $rso_id, $delete_id);
    $stmt_del->execute();
}

// Fetch RSO details
$stmt = $conn->prepare("
    SELECT r.*, u.name AS admin_name, u.email AS admin_email
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

// Fetch members
$stmt_members = $conn->prepare("
    SELECT u.id, u.name, u.email
    FROM rso_members rm
    JOIN users u ON rm.user_id = u.id
    WHERE rm.rso_id = ?
");
$stmt_members->bind_param("i", $rso_id);
$stmt_members->execute();
$members = $stmt_members->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RSO Members</title>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Segoe UI", sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        ul {
            list-style: none;
        }
        a {
            color: inherit;
            text-decoration: none;
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
            overflow: hidden;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .container {
            padding: 30px 40px;
        }
        .rso-header {
            margin-bottom: 20px;
        }
        .rso-header h2 {
            font-size: 24px;
            margin-bottom: 6px;
        }
        .rso-header p {
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .members-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .members-table th,
        .members-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #ccc;
        }
        .members-table th {
            background-color: <?= $isDark ? '#2c2c38' : '#f0f0f0' ?>;
        }

        .members-table form {
            display: inline;
        }

        .members-table button {
            background-color: #dc3545;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        .members-table button:hover {
            background-color: #b52a37;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 16px;
            background-color: var(--button-bg);
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.3s;
            font-size: 14px;
        }
        .back-btn:hover {
            background-color: var(--button-hover);
        }

        .main-content::-webkit-scrollbar {
            width: 8px;
        }
        .main-content::-webkit-scrollbar-track {
            background-color: var(--bg-color);
        }
        .main-content::-webkit-scrollbar-thumb {
            background-color: #999;
            border-radius: 4px;
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
        <div class="rso-header">
            <h2>Members of RSO: <?= htmlspecialchars($rso['name']) ?></h2>
            <p><strong>Admin:</strong> <?= htmlspecialchars($rso['admin_name']) ?> (<?= htmlspecialchars($rso['admin_email']) ?>)</p>
        </div>

        <?php if ($members && $members->num_rows > 0): ?>
            <table class="members-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($member = $members->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($member['name']) ?></td>
                        <td><?= htmlspecialchars($member['email']) ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Remove this member?')">
                                <input type="hidden" name="delete_member_id" value="<?= $member['id'] ?>">
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No members found for this RSO.</p>
        <?php endif; ?>

        <a class="back-btn" href="rso_details.php?id=<?= $rso_id ?>">⬅ Back to RSO</a>
    </div>
</div>
</body>
</html>
