<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If the user clicked the "Delete" link
if (isset($_GET['delete'])) {
    $admin_id = $_SESSION['user']['id'];
    $deleteId = intval($_GET['delete']);

    // Delete only if the event belongs to the current admin
    $deleteSql = "DELETE FROM events WHERE id = ? AND created_by = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param("ii", $deleteId, $admin_id);
    $deleteStmt->execute();

    // Redirect back to my_events.php to refresh the list
    header("Location: my_events.php");
    exit();
}

$admin_id = $_SESSION['user']['id'];
$userName = $_SESSION['user']['name'] ?? 'Admin';
$userEmail = $_SESSION['user']['email'] ?? 'admin@campus.com';
$theme = $_SESSION['theme'] ?? 'light';
$isDark = $theme === 'dark';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;

// Fetch the events created by this admin
$query = "SELECT * FROM events WHERE created_by = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$events = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusConnect - My Events</title>
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
        .sidebar .nav-links {
            flex: 1;
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
        .user-text .email {
            font-size: 12px;
            opacity: 0.7;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #999;
            border-radius: 50%;
        }

        .events-section {
            padding: 20px;
        }
        .events-section h1 {
            font-size: 22px;
            margin-bottom: 20px;
        }

        .event-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .event-info {
            flex: 1;
        }
        .event-img {
            width: 120px;
            height: 70px;
            border-radius: 6px;
            margin-right: 20px;
            background-color: #eee;
            background-size: cover;
            background-position: center;
        }
        .event-action a {
            background-color: var(--button-bg);
            color: #fff;
            padding: 8px 14px;
            border-radius: 5px;
            font-size: 13px;
            margin-left: 8px; /* Add a little space between Edit and Delete */
        }
        .event-action a:hover {
            background-color: var(--button-hover);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand">CampusConnect</div>
        <ul class="nav-links">
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
        </ul>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="user-info">
                <div class="user-text">
                    <div class="name"><?= htmlspecialchars($userName) ?></div>
                    <div class="email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="user-avatar">
                    <?php if (!empty($userAvatar)): ?>
                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar"
                             style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="events-section">
            <h1>My Events</h1>

            <?php if ($events->num_rows > 0): ?>
                <?php while ($event = $events->fetch_assoc()): ?>
                    <div class="event-card">
                        <!-- Event Image -->
                        <div class="event-img"
                             style="background-image: url('<?= htmlspecialchars($event['image_filename'] ?? 'https://via.placeholder.com/120x70') ?>');">
                        </div>

                        <!-- Event Info -->
                        <div class="event-info">
                            <strong><?= htmlspecialchars($event['name']) ?></strong><br>
                            Date: <?= htmlspecialchars($event['event_date']) ?><br>
                            Time: <?= htmlspecialchars($event['start_time']) ?> - <?= htmlspecialchars($event['end_time']) ?>
                        </div>

                        <!-- Action Links: Edit + Delete -->
                        <div class="event-action">
                            <a href="edit_event.php?id=<?= $event['id'] ?>">Edit</a>

                            <!-- “Delete” link triggers ?delete=ID with a confirmation -->
                            <a href="my_events.php?delete=<?= $event['id'] ?>"
                               onclick="return confirm('Are you sure you want to delete this event?');">
                                Delete
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>You haven’t created any events yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
