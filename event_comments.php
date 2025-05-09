<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user       = $_SESSION['user'];
$user_id    = $user['id'];
$userName   = $user['name'] ?? "User";
$userEmail  = $user['email'] ?? "user@university.edu";
$theme      = $_SESSION['theme'] ?? 'light';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;
$role = $_SESSION['user']['role'] ?? null;
$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if event ID is provided
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ensure user is participating in the event
$check = $conn->prepare("SELECT * FROM event_participants WHERE user_id = ? AND event_id = ?");
$check->bind_param("ii", $user_id, $event_id);
$check->execute();
$check_result = $check->get_result();
if ($check_result->num_rows === 0) {
    echo "<p>You must join this event to view or comment.</p>";
    exit();
}

// Handle new comment
if (isset($_POST['comment']) && !empty(trim($_POST['content']))) {
    $content = trim($_POST['content']);
    $stmt = $conn->prepare("INSERT INTO event_comments (user_id, event_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $user_id, $event_id, $content);
    $stmt->execute();
    header("Location: event_comments.php?id=" . $event_id);
    exit();
}

// Handle delete
if (isset($_POST['delete']) && isset($_POST['comment_time'])) {
    $time = $_POST['comment_time'];
    $stmt = $conn->prepare("DELETE FROM event_comments WHERE user_id = ? AND event_id = ? AND created_at = ?");
    $stmt->bind_param("iis", $user_id, $event_id, $time);
    $stmt->execute();
    header("Location: event_comments.php?id=" . $event_id);
    exit();
}

// Handle edit submission
if (isset($_POST['edit_submit']) && isset($_POST['edited_content']) && isset($_POST['edit_time'])) {
    $edited_content = trim($_POST['edited_content']);
    $edit_time = $_POST['edit_time'];
    $stmt = $conn->prepare("UPDATE event_comments SET content = ? WHERE user_id = ? AND event_id = ? AND created_at = ?");
    $stmt->bind_param("siis", $edited_content, $user_id, $event_id, $edit_time);
    $stmt->execute();
    header("Location: event_comments.php?id=" . $event_id);
    exit();
}

// Get event name
$eventQuery = $conn->prepare("SELECT name FROM events WHERE id = ?");
$eventQuery->bind_param("i", $event_id);
$eventQuery->execute();
$eventResult = $eventQuery->get_result()->fetch_assoc();
$eventName   = $eventResult['name'] ?? 'Event';

// Fetch comments
$comments = $conn->prepare("
    SELECT ec.content, ec.created_at, ec.user_id, u.name 
    FROM event_comments ec 
    JOIN users u ON ec.user_id = u.id 
    WHERE ec.event_id = ? 
    ORDER BY ec.created_at DESC
");
$comments->bind_param("i", $event_id);
$comments->execute();
$comment_results = $comments->get_result();

$isDark = $theme === 'dark';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CampusConnect - Event Comments</title>
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
      --input-bg: <?= $theme === 'dark' ? '#2b3749' : '#fff' ?>;
      --comment-bg: <?= $theme === 'dark' ? '#444b5a' : '#f0f2f5' ?>;
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
  background-color: <?= $isDark ? '#2c2c38' : '#fff' ?>;
  display: flex;
  align-items: center;
  padding: 0 20px;
  border-bottom: 1px solid <?= $isDark ? '#444' : '#e7e7e7' ?>;
  justify-content: flex-end;
}

    /* USER INFO */
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

    /* CONTENT SECTION */
    .content-section {
      flex: 1;
      padding: 20px;
    }
    .comment-container {
      background: var(--card-bg);
      padding: 25px;
      border-radius: 8px;
      max-width: 700px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      margin: 0 auto 30px auto;
    }
    textarea, input[type="text"] {
      width: 100%;
      padding: 10px;
      border-radius: 6px;
      border: 1px solid #ccc;
      background-color: var(--input-bg);
      color: var(--text-color);
    }
    .btn {
      background: var(--button-bg);
      color: #fff;
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
      margin-right: 6px;
    }
    .btn:hover {
      background: var(--button-hover);
    }
    .comment {
      background: var(--comment-bg);
      color: var(--text-color);
      padding: 12px;
      margin-bottom: 10px;
      border-radius: 6px;
    }
  </style>
</head>
<body>
  <!-- SIDEBAR -->
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
                <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
            <?php endif; ?>
        </div>
    </div>
</div>


    <!-- PAGE CONTENT -->
    <div class="content-section">
      <div class="comment-container">
        <h2>Comments for: <?= htmlspecialchars($eventName) ?></h2>
        <form method="POST">
          <textarea name="content" rows="3" placeholder="Leave your comment..." required></textarea>
          <button type="submit" name="comment" class="btn">Post Comment</button>
        </form>
      </div>

      <div class="comment-container">
        <?php while ($row = $comment_results->fetch_assoc()): ?>
          <div class="comment">
            <strong><?= htmlspecialchars($row['name']) ?></strong><br>
            <small><?= htmlspecialchars($row['created_at']) ?></small>

            <?php if (isset($_POST['edit']) && $_POST['edit_time'] === $row['created_at']): ?>
              <form method="POST">
                <input type="hidden" name="edit_time" value="<?= htmlspecialchars($row['created_at']) ?>">
                <input type="text" name="edited_content" value="<?= htmlspecialchars($row['content']) ?>">
                <button type="submit" name="edit_submit" class="btn">Update</button>
              </form>
            <?php else: ?>
              <p><?= nl2br(htmlspecialchars($row['content'])) ?></p>
              <?php if ($row['user_id'] == $user_id): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="comment_time" value="<?= htmlspecialchars($row['created_at']) ?>">
                  <button type="submit" name="delete" class="btn">Delete</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="edit_time" value="<?= htmlspecialchars($row['created_at']) ?>">
                  <button type="submit" name="edit" class="btn">Edit</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
</body>
</html>
