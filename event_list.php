<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$role = $user['role'];
$user_id = $user['id'];
$university_id = $user['university_id'];
$userName  = $user['name'];
$userEmail = $user['email'];
$theme     = $_SESSION['theme'] ?? 'light';
$isDark    = $theme === 'dark';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;

$where = "";
if ($role === 'student') {
    $where = "WHERE e.approved = 1 AND (
        e.type = 'public' 
        OR (e.type = 'private' AND e.university_id = $university_id)
        OR (e.type = 'rso' AND e.rso_id IN (
            SELECT rso_id FROM rso_members WHERE user_id = $user_id
        ))
    )";
} elseif ($role === 'admin') {
    $where = "WHERE e.approved = 1 AND (
        e.type = 'public' 
        OR (e.type = 'private' AND e.university_id = $university_id)
        OR (e.type = 'rso' AND e.rso_id IN (
            SELECT rso_id FROM rso_members WHERE user_id = $user_id
        ))
    )";
}
elseif ($role === 'superadmin') {
    $where = "WHERE e.type = 'public'";
}

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$query = "
    SELECT e.*, l.name AS location_name
    FROM events e
    JOIN locations l ON e.location_id = l.loc_id
    $where
    ORDER BY e.event_date, e.start_time
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CampusConnect - Event List</title>
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

    .content-scroll {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
    }
    .section-title {
      text-align: center;
      margin-bottom: 20px;
      font-size: 20px;
      color: var(--text-color);
    }

    .events-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
    }

    .card {
      background: var(--card-bg);
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
    }
    .card h3 {
      margin: 0 0 10px;
      color: var(--text-color);
      font-size: 18px;
    }
    .card p {
      margin: 5px 0;
      font-size: 14px;
      line-height: 1.4;
    }

    .label {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      background: #ddd;
      margin-bottom: 8px;
      font-weight: 600;
    }
    .label.public { background: #28a745; color: white; }
    .label.private { background: #ffc107; color: black; }
    .label.rso { background: #17a2b8; color: white; }

    .btn {
      margin-top: auto;
      padding: 8px 12px;
      background: var(--button-bg);
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-size: 14px;
      text-align: center;
      transition: background 0.3s;
    }
    .btn:hover { background: var(--button-hover); }

    @media (max-width: 768px) {
      .sidebar { width: 60px; }
      .sidebar .brand { display: none; }
      .sidebar .nav-links li a { text-align: center; padding: 10px 0; }
      .main-content { margin-left: 60px; }
    }
  </style>
</head>
<body class="<?= $isDark ? 'dark' : 'light' ?>">
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
      <h2 class="section-title">Browse Events</h2>
      <div class="events-grid">
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($event = $result->fetch_assoc()): ?>
            <div class="card">
              <h3><?= htmlspecialchars($event['name']) ?></h3>
              <span class="label <?= $event['type'] ?>">
                <?= strtoupper($event['type']) ?> EVENT
              </span>
              <p><strong>Date:</strong> <?= $event['event_date'] ?></p>
              <p><strong>Time:</strong> <?= substr($event['start_time'], 0, 5) ?> - <?= substr($event['end_time'], 0, 5) ?></p>
              <p><strong>Location:</strong> <?= htmlspecialchars($event['location_name']) ?></p>
              <a href="event_details.php?id=<?= $event['id'] ?>" class="btn">View Details</a>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p style="text-align:center;">No events found.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
