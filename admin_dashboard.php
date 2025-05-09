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


$user_id = $_SESSION['user']['id'] ?? null;
$university_id = $_SESSION['user']['university_id'] ?? null;
$userName   = $_SESSION['user']['name']  ?? 'Admin';
$userEmail  = $_SESSION['user']['email'] ?? 'admin@university.edu';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;


if (!$user_id || !$university_id) {
    die("User info missing from session.");
}

$currentDateTime = date('Y-m-d H:i:s');


$stmt = $conn->prepare("
    SELECT e.*, l.name AS location_name
    FROM events e
    JOIN locations l ON e.location_id = l.loc_id
    WHERE (
        e.type = 'public'
        OR (e.type = 'private' AND e.university_id = ?)
        OR (e.type = 'rso' AND EXISTS (
            SELECT 1 FROM rso_members rm WHERE rm.rso_id = e.rso_id AND rm.user_id = ?
        ))
    )
    AND e.approved = 1
    AND CONCAT(e.event_date, ' ', e.start_time) >= ?
    ORDER BY e.event_date, e.start_time
");

$stmt->bind_param("iis", $university_id, $user_id, $currentDateTime);
$stmt->execute();
$events = $stmt->get_result();

$theme  = $_SESSION['theme'] ?? 'light';
$isDark = ($theme === 'dark');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>CampusConnect - Admin Dashboard</title>
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
        .sidebar .nav-links li { margin: 10px 0; }
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
            position: relative;
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
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-text { text-align: right; }
        .user-text .name { font-weight: 600; }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #999;
            border-radius: 50%;
        }
        .events-section { padding: 20px; }
        .events-section h1 {
            margin-bottom: 20px;
            font-size: 22px;
        }
        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .event-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }
        .event-image {
            width: 100%;
            height: 150px;
            background-color: #ccc;
            background-size: cover;
            background-position: center;
        }
        .card-content {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .event-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .event-details {
            font-size: 13px;
            opacity: 0.8;
            margin-bottom: 8px;
        }
        .event-actions {
            margin-top: auto;
            display: flex;
            justify-content: flex-end;
        }
        .details-button {
            padding: 6px 10px;
            border-radius: 4px;
            background-color: var(--button-bg);
            color: #fff;
            font-size: 13px;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .details-button:hover {
            background-color: var(--button-hover);
        }
        .main-content::-webkit-scrollbar { width: 8px; }
        .main-content::-webkit-scrollbar-track { background-color: var(--bg-color); }
        .main-content::-webkit-scrollbar-thumb { background-color: #999; border-radius: 4px; }
        .admin-indicator {
            position: fixed;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            background-color: #28a745;
            color: #fff;
            padding: 16px 20px;
            font-size: 16px;
            font-weight: bold;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
            box-shadow: -2px 2px 8px rgba(0,0,0,0.2);
            z-index: 9999;
        }
        @media (max-width: 768px) {
            .sidebar { width: 60px; padding: 20px 0; }
            .sidebar .brand { display: none; }
            .sidebar .nav-links li a {
                text-align: center;
                padding: 10px 0;
            }
            .main-content { margin-left: 60px; }
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
                    <div class="email" style="font-size: 12px; opacity: 0.7;">
                        <?= htmlspecialchars($userEmail) ?>
                    </div>
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
            <h1>Upcoming Events (Admin View)</h1>
            <?php if ($events && $events->num_rows > 0): ?>
                <div class="event-grid">
                <?php while ($event = $events->fetch_assoc()):
                    $image_url = !empty($event['image_filename'])
                        ? $event['image_filename']
                        : 'https://via.placeholder.com/400x200/EEE/333?text=Event+Image';
                ?>
                    <div class="event-card">
                        <div class="event-image" style="background-image: url('<?= htmlspecialchars($image_url) ?>');"></div>
                        <div class="card-content">
                            <div>
                                <div class="event-name"><?= htmlspecialchars($event['name']) ?></div>
                                <div class="event-details">
                                    <?= date("D, M j g:i A", strtotime($event['event_date'].' '.$event['start_time'])) ?><br/>
                                    Location: <?= htmlspecialchars($event['location_name']) ?>
                                </div>
                            </div>
                            <div class="event-actions">
                                <a class="details-button" href="event_details.php?id=<?= $event['id'] ?>">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="opacity: 0.7;">No upcoming events available.</p>
            <?php endif; ?>
        </div>

        <div class="admin-indicator">ADMIN</div>
    </div>
</body>
</html>
