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

$user_id = $_SESSION['user']['id'];
$university_id = $_SESSION['user']['university_id'];
$userName   = $_SESSION['user']['name']  ?? 'Student';
$userEmail  = $_SESSION['user']['email'] ?? 'email@university.edu';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;
$role = $_SESSION['user']['role'] ?? null;

$errorMsg = '';
$successMsg = '';
$themeMsg = '';

if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($stored_password);
    $stmt->fetch();
    $stmt->close();

    if ($current === $stored_password) {
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $new, $user_id);
        $update->execute();
        $successMsg = "Password updated successfully.";
    } else {
        $errorMsg = "Current password is incorrect.";
    }
}

if (isset($_POST['change_theme'])) {
    $theme = $_POST['theme'] ?? 'light';
    $update = $conn->prepare("UPDATE users SET theme = ? WHERE id = ?");
    $update->bind_param("si", $theme, $user_id);
    $update->execute();
    $_SESSION['theme'] = $theme;
    $themeMsg = "Theme updated successfully.";
}

$theme = $_SESSION['theme'] ?? 'light';
$isDark = $theme === 'dark';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CampusConnect - Settings</title>
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
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background-color: var(--bg-color);
      color: var(--text-color);
    }

    a { text-decoration: none; color: inherit; }
    ul { list-style-type: none; }

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

    .main-content {
      margin-left: 250px;
      width: calc(100% - 250px);
      display: flex;
      flex-direction: column;
      min-height: 100vh;
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


    .settings-section {
      padding: 20px;
    }
    .settings-section h1 {
      font-size: 22px;
      margin-bottom: 20px;
    }

    .settings-box {
      background: var(--card-bg);
      padding: 25px;
      border-radius: 8px;
      max-width: 600px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }

    .form-group {
      margin-bottom: 20px;
    }
    .form-group h2 {
      font-size: 17px;
      margin-bottom: 10px;
    }
    .form-group label {
      display: block;
      font-weight: bold;
      margin-bottom: 5px;
    }
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 8px;
      border-radius: 4px;
      border: 1px solid #ccc;
      background-color: <?= $theme === 'dark' ? '#2b3749' : '#fff' ?>;
      color: var(--text-color);
    }

    .btn-submit {
      background: var(--button-bg);
      color: #fff;
      padding: 10px 18px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .btn-submit:hover {
      background: var(--button-hover);
    }

    .message {
      margin-bottom: 15px;
      padding: 10px;
      border-radius: 6px;
      font-size: 14px;
    }
    .error { background: #ffe0e0; color: #a00; }
    .success { background: #e0ffe0; color: #0a0; }
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
                <li><a href="profile.php">Profile</a></li>
                <li><a href="settings.php">Settings</a></li>
                <li><a href="universities.php">Universities</a></li>
                <li><a href="superadmin_manage_events.php">Edit Events</a></li>
                <li><a href="manage_users.php">Manage Users</a></li>
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
    <div class="settings-section">
      <h1>Settings</h1>

      <?php if ($errorMsg): ?>
        <div class="message error"><?= htmlspecialchars($errorMsg) ?></div>
      <?php elseif ($successMsg): ?>
        <div class="message success"><?= htmlspecialchars($successMsg) ?></div>
      <?php endif; ?>

      <div class="settings-box">
        <form method="post">
          <input type="hidden" name="change_password" value="1" />
          <div class="form-group">
            <h2>Change Password</h2>
            <label for="current_password">Current Password:</label>
            <input type="password" name="current_password" required />
            <label for="new_password">New Password:</label>
            <input type="password" name="new_password" required />
          </div>
          <button class="btn-submit" type="submit">Update Password</button>
        </form>
      </div>

      <?php if ($themeMsg): ?>
        <div class="message success"><?= htmlspecialchars($themeMsg) ?></div>
      <?php endif; ?>

      <div class="settings-box">
        <form method="post">
          <input type="hidden" name="change_theme" value="1" />
          <div class="form-group">
            <h2>Theme Preference</h2>
            <label for="theme">Select Theme:</label>
            <select name="theme" id="theme">
              <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>Light</option>
              <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>Dark</option>
            </select>
          </div>
          <button class="btn-submit" type="submit">Apply Theme</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
