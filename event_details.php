<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user           = $_SESSION['user'];
$user_id        = $user['id'];
$role           = $user['role'];
$university_id  = $user['university_id'];
$theme          = $_SESSION['theme'] ?? 'light';
$userAvatar     = $_SESSION['user']['avatar_filename'] ?? null;
$userName       = $_SESSION['user']['name'] ?? 'Student';
$userEmail      = $_SESSION['user']['email'] ?? 'email@university.edu';

if (!isset($_GET['id'])) {
    echo "<p>No event selected.</p>";
    exit();
}

$event_id = intval($_GET['id']);

// Fetch the event
$query = "
    SELECT e.*, 
           l.name AS location_name, 
           u.name AS creator_name,
           e.image_filename
    FROM events e
    JOIN locations l ON e.location_id = l.loc_id
    JOIN users u ON e.created_by = u.id
    WHERE e.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

if (!$event) {
    echo "<p>Event not found.</p>";
    exit();
}

// ---- PERMISSION / VISIBILITY ----
// If you truly want to remove all permission restrictions,
// simply remove or comment out any 'can_view' checks like so:

$can_view = true; // Everyone who is logged in can see the event
/*
$can_view = false;
if (in_array($role, ['student','admin','superadmin'])) {
    // If event is public, or private at same university, or an RSO they are in
    if ($event['type'] === 'public') {
        $can_view = true;
    } elseif ($event['type'] === 'private' && $event['university_id'] == $university_id) {
        $can_view = true;
    } elseif ($event['type'] === 'rso') {
        $check = $conn->query("SELECT * FROM rso_members WHERE user_id = $user_id AND rso_id = {$event['rso_id']}");
        if ($check && $check->num_rows > 0) {
            $can_view = true;
        }
    }
}
*/
if (!$can_view) {
    echo "<p>You do not have permission to view this event.</p>";
    exit();
}

// Handle joining event
// (Now admin is also allowed to join, just like a student.)
if (isset($_POST['join']) && in_array($role, ['student','admin'])) {
    $check = $conn->query("SELECT * FROM event_participants WHERE user_id = $user_id AND event_id = $event_id");
    if ($check->num_rows === 0) {
        $conn->query("INSERT INTO event_participants (user_id, event_id, status) VALUES ($user_id, $event_id, 'joined')");
    }
    header("Location: event_details.php?id=" . $event_id);
    exit();
}

// For “Share” links
$current_url   = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Display an image if available, else a placeholder
$displayImage  = !empty($event['image_filename'])
    ? $event['image_filename']
    : 'https://via.placeholder.com/1200x600/EEE/555?text=No+Event+Image';

// Determine if dark mode
$isDark = ($theme === 'dark');

// Determine if the event is expired
$currentDate = date('Y-m-d');
$eventDate   = date('Y-m-d', strtotime($event['event_date']));
$expired     = ($eventDate < $currentDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CampusConnect - Event Details</title>
  <style>
    :root {
      --bg-color: <?= $theme === 'dark' ? '#1e1e26' : '#f4f5f9' ?>;
      --text-color: <?= $theme === 'dark' ? '#fff' : '#333' ?>;
      --card-bg: <?= $theme === 'dark' ? '#2a2f3b' : '#fff' ?>;
      --sidebar-bg: <?= $theme === 'dark' ? '#181822' : '#1e3d7b' ?>;
      --sidebar-link: <?= $theme === 'dark' ? '#ccc' : '#cbd3e6' ?>;
      --sidebar-link-hover: <?= $theme === 'dark' ? '#2e3440' : '#2a4d95' ?>;
      --button-bg: <?= $theme === 'dark' ? '#3e8ed0' : '#1e3d7b' ?>;
      --button-hover: <?= $theme === 'dark' ? '#5aa1e3' : '#2a4d95' ?>;

      --topbar-bg: <?= $theme === 'dark' ? '#2c2c38' : '#fff' ?>;
      --topbar-border: <?= $theme === 'dark' ? '#444' : '#e7e7e7' ?>;
      --search-bar-bg: <?= $theme === 'dark' ? '#3a3f4b' : '#f1f2f6' ?>;
    }

    /* Reset & Base */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background-color: var(--bg-color);
      color: var(--text-color);
      min-height: 100vh;
    }
    a {
      text-decoration: none;
      color: inherit;
    }
    ul {
      list-style-type: none;
    }

    /* SIDEBAR */
    .sidebar {
      width: 250px;
      height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      background-color: var(--sidebar-bg);
      color: #fff;
      display: flex;
      flex-direction: column;
      padding: 20px 0;
    }
    .sidebar .brand {
      margin-left: 20px;
      font-weight: bold;
      font-size: 20px;
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

    /* MAIN CONTENT */
    .main-content {
      margin-left: 250px;
      width: calc(100% - 250px);
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    /* TOPBAR */
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
    }

    /* CONTENT AREA */
    .content-scroll {
      flex: 1;
      overflow-y: auto;
    }
    .content-section {
      display: flex;
      justify-content: center;
      padding: 40px 20px;
    }
    .event-card {
      width: 100%;
      max-width: 900px;
      background: var(--card-bg);
      border-radius: 8px;
      box-shadow: 0 6px 14px rgba(0,0,0,0.1);
      padding: 30px;
    }
    .event-image {
      width: 100%;
      height: 300px;
      background-image: url('<?= htmlspecialchars($displayImage) ?>');
      background-size: cover;
      background-position: center;
      border-radius: 6px;
    }
    h2.event-title {
      font-size: 24px;
      margin-top: 20px;
      margin-bottom: 10px;
    }
    .event-type-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 4px;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 20px;
    }
    .public {
      background: #28a745;
      color: #fff;
    }
    .private {
      background: #ffc107;
      color: #000;
    }
    .rso {
      background: #17a2b8;
      color: #fff;
    }
    .event-info p {
      margin: 6px 0;
      line-height: 1.4;
    }
    .btn {
      display: inline-block;
      background: var(--button-bg);
      color: #fff;
      border-radius: 6px;
      padding: 10px 14px;
      margin-top: 15px;
      text-decoration: none;
      transition: background-color 0.3s;
      border: none;
      cursor: pointer;
    }
    .btn:hover {
      background: var(--button-hover);
    }
    .joined {
      color: limegreen;
      font-weight: bold;
    }
    .share-icons a {
      display: inline-block;
      width: 30px;
      height: 30px;
      line-height: 30px;
      text-align: center;
      border-radius: 50%;
      margin: 0 5px;
      color: white;
    }
    .fb {
      background: #3b5998;
    }
    .tw {
      background: #1da1f2;
    }
    .li {
      background: #0077b5;
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
      <div class="content-section">
        <div class="event-card">
          <div class="event-image"></div>
          <h2 class="event-title"><?= htmlspecialchars($event['name']) ?></h2>
          <span class="event-type-badge <?= htmlspecialchars($event['type']) ?>">
            <?= strtoupper($event['type']) ?> EVENT
          </span>
          <div class="event-info">
            <p><strong>Date:</strong> <?= htmlspecialchars($event['event_date']) ?></p>
            <p><strong>Time:</strong> <?= htmlspecialchars($event['start_time']) ?> - <?= htmlspecialchars($event['end_time']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($event['location_name']) ?></p>
            <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($event['description'])) ?></p>
            <p><strong>Created By:</strong> <?= htmlspecialchars($event['creator_name']) ?></p>
          </div>

          <!--
               Now BOTH student & admin can join.
               If the event is expired, only show the comments button.
          -->
          <?php if (in_array($role, ['student','admin'])): ?>
            <?php if ($expired): ?>
              <!-- If the event is expired, only show "View/Add Comments" -->
              <a href="event_comments.php?id=<?= $event_id ?>" class="btn">💬 View/Add Comments</a>
            <?php else: ?>
              <?php
                  $joined = $conn->query("SELECT * FROM event_participants WHERE user_id = $user_id AND event_id = $event_id");
                  if ($joined->num_rows > 0):
              ?>
                <p class="joined">✅ You have joined this event.</p>
                <a href="event_comments.php?id=<?= $event_id ?>" class="btn">💬 View/Add Comments</a>
              <?php else: ?>
                <form method="POST">
                  <button type="submit" name="join" class="btn">Join Event</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          <?php else: ?>
            <!-- If role is superadmin or something else, you can choose what to display -->
            <a href="event_comments.php?id=<?= $event_id ?>" class="btn">💬 View/Add Comments</a>
          <?php endif; ?>

          <a href="event_list.php" class="btn">⬅ Back to Events</a>

          <div class="share-section" style="margin-top: 20px;">
            <p><strong>Share this event:</strong></p>
            <div class="share-icons">
              <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($current_url) ?>" class="fb" target="_blank">F</a>
              <a href="https://twitter.com/intent/tweet?url=<?= urlencode($current_url) ?>&text=Check+out+this+event%21" class="tw" target="_blank">X</a>
              <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($current_url) ?>" class="li" target="_blank">in</a>
            </div>
          </div>

        </div><!-- .event-card -->
      </div><!-- .content-section -->
    </div><!-- .content-scroll -->
  </div><!-- .main-content -->
</body>
</html>
