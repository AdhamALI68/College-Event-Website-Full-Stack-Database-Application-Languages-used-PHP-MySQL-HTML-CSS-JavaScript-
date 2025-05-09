<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$role = $user['role'];
$userName  = $user['name']  ?? 'User';
$userEmail = $user['email'] ?? 'user@university.edu';
$theme     = $_SESSION['theme'] ?? 'light';
$isDark    = $theme === 'dark';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;
// Attempt UCF API or fallback
$feed_url = "https://events.ucf.edu/feed.json";
$data = @file_get_contents($feed_url);
$feedWarning = false;

if ($data === false) {
    $data = @file_get_contents("mock_ucf_events.json");
    $feedWarning = true;
}

$events = [];
if ($data !== false) {
    $decoded = json_decode($data, true);
    if (is_array($decoded)) {
        $events = $decoded;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CampusConnect - UCF Feed</title>
  <style>
    :root {
      --bg-color: <?= $isDark ? '#1e1e26' : '#f4f5f9' ?>;
      --text-color: <?= $isDark ? '#fff' : '#333' ?>;
      --card-bg: <?= $isDark ? '#2a2f3b' : '#fff' ?>;
      --sidebar-bg: <?= $isDark ? '#181822' : '#1e3d7b' ?>;
      --sidebar-link: <?= $isDark ? '#ccc' : '#cbd3e6' ?>;
      --sidebar-link-hover: <?= $isDark ? '#2e3440' : '#2a4d95' ?>;
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
    ul { list-style: none; }

    .sidebar {
      width: 250px;
      background-color: var(--sidebar-bg);
      display: flex;
      flex-direction: column;
      padding: 20px 0;
    }
    .sidebar .brand {
      margin-left: 20px;
      font-weight: bold;
      font-size: 18px;
      margin-bottom: 20px;
        color: white;
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
    .user-text { text-align: right; }
    .user-text .name { font-weight: 600; }
    .user-avatar {
      width: 40px;
      height: 40px;
      background: #999;
      border-radius: 50%;
    }

    .content-scroll {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
    }
    .content-scroll::-webkit-scrollbar {
      width: 8px;
    }
    .content-scroll::-webkit-scrollbar-thumb {
      background-color: #999;
      border-radius: 4px;
    }

    .section-header {
      margin-bottom: 20px;
      font-size: 20px;
      text-align: center;
      color: <?= $isDark ? '#ffffff' : '#1e3d7b' ?>;
    }
    .warning {
      text-align: center;
      color: #f66;
      margin-bottom: 10px;
    }
    .ucf-event {
      background: var(--card-bg);
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      margin-bottom: 15px;
    }
    .ucf-event h3 {
      margin-bottom: 8px;
      font-size: 16px;
      color: <?= $isDark ? '#83c5ff' : '#1e3d7b' ?>;
    }
    .ucf-event p {
      font-size: 14px;
      margin: 5px 0;
    }
    .ucf-event a {
      color: #4da3ff;
    }
    .ucf-event a:hover {
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      .sidebar {
        width: 60px;
      }
      .sidebar .brand { display: none; }
      .sidebar .nav-links li a {
        text-align: center;
        padding: 10px 0;
      }
      .main-content {
        margin-left: 60px;
      }
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
            <div class="email" style="font-size: 12px; opacity: 0.7;">
                <?= htmlspecialchars($userEmail) ?>
            </div>
        </div>
        <div class="user-avatar">
            <?php if (!empty($userAvatar)): ?>
                <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
            <?php endif; ?>
        </div>
    </div>
</div>

    <div class="content-scroll">
      <h2 class="section-header">🌐 UCF Upcoming Events</h2>

      <?php if ($feedWarning): ?>
        <p class="warning">⚠️ Using local backup. UCF live feed unavailable.</p>
      <?php endif; ?>

      <?php if (!empty($events)): ?>
        <?php foreach ($events as $ev): ?>
          <div class="ucf-event">
            <h3><?= htmlspecialchars($ev['title']) ?></h3>
            <p><strong>Time:</strong> <?= htmlspecialchars($ev['starts']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($ev['location']) ?></p>
            <a href="<?= htmlspecialchars($ev['url']) ?>" target="_blank">View Event</a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="warning">⚠️ No events found or feed data could not be parsed.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
