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

$theme = $_SESSION['theme'] ?? 'light';
$isDark = $theme === 'dark';

$userName  = $_SESSION['user']['name']  ?? 'Super Admin';
$userEmail = $_SESSION['user']['email'] ?? 'admin@university.edu';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;

$msg = '';

// Handle role update
if (isset($_POST['update_role'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = $_POST['role'];
    if ($user_id !== $_SESSION['user']['id']) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        $stmt->execute();
        $msg = "User role updated successfully.";
    } else {
        $msg = "You cannot change your own superadmin role.";
    }
}

// Handle delete
if (isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    if ($user_id !== $_SESSION['user']['id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $msg = "User deleted successfully.";
    } else {
        $msg = "You cannot delete your own account.";
    }
}

// Fetch users
$users_result = $conn->query("SELECT id, name, email, role, university_id FROM users ORDER BY role DESC, name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CampusConnect - Manage Users</title>
  <style>
    /* Remove underline from all links by default */
    a {
      text-decoration: none;
      color: inherit; /* So it inherits from its parent instead of forcing a link color */
    }

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
      font-family: "Segoe UI", Arial, sans-serif;
      background-color: var(--bg-color);
      color: var(--text-color);
      display: flex;
      height: 100vh;
      overflow: hidden;
    }

    /* SIDEBAR */
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

    /* MAIN CONTENT */
    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
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
      padding: 20px;
    }
    .content-section h1 {
      font-size: 22px;
      margin-bottom: 20px;
    }

    .card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
      gap: 20px;
    }
    .card-item {
      background-color: var(--card-bg);
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .item-header {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 8px;
    }
    .item-details {
      font-size: 14px;
      opacity: 0.8;
      margin-bottom: 10px;
    }

    .card-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }
    select {
      padding: 4px;
      border-radius: 4px;
      border: 1px solid #ccc;
    }

    .approve-button,
    .delete-button {
      padding: 6px 10px;
      font-size: 13px;
      border: none;
      border-radius: 4px;
      color: #fff;
      cursor: pointer;
    }
    .approve-button {
      background-color: var(--button-bg);
    }
    .approve-button:hover {
      background-color: var(--button-hover);
    }
    .delete-button {
      background-color: #c92a2a;
    }
    .delete-button:hover {
      background-color: #e03131;
    }

    .message {
      margin-bottom: 12px;
      font-weight: bold;
      color: #4B6A46;
    }

    /* SCROLLBAR */
    .main-content::-webkit-scrollbar {
      width: 8px;
    }
    .main-content::-webkit-scrollbar-thumb {
      background-color: #aaa;
      border-radius: 4px;
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


    <div class="content-section">
      <h1>Manage Users</h1>

      <?php if (!empty($msg)): ?>
        <div class="message"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <?php if ($users_result && $users_result->num_rows > 0): ?>
        <div class="card-grid">
          <?php while ($user = $users_result->fetch_assoc()): ?>
            <div class="card-item">
              <div class="item-header"><?= htmlspecialchars($user['name']) ?></div>
              <div class="item-details">
                Email: <?= htmlspecialchars($user['email']) ?><br />
                Role: <?= htmlspecialchars($user['role']) ?><br />
                University ID: <?= htmlspecialchars($user['university_id']) ?>
              </div>
              <div class="card-actions">
                <form method="POST">
                  <input type="hidden" name="user_id" value="<?= $user['id'] ?>" />
                  <select name="role">
                    <option value="student"    <?= $user['role'] === 'student'    ? 'selected' : '' ?>>Student</option>
                    <option value="admin"      <?= $user['role'] === 'admin'      ? 'selected' : '' ?>>Admin</option>
                    <option value="superadmin" <?= $user['role'] === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                  </select>
                  <button class="approve-button" type="submit" name="update_role">Update</button>
                </form>
                <form method="POST">
                  <input type="hidden" name="user_id" value="<?= $user['id'] ?>" />
                  <button class="delete-button" type="submit" name="delete_user">Delete</button>
                </form>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <p>No users found.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
