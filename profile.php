<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Connect to DB
$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve theme and user info from session
$theme = $_SESSION['theme'] ?? 'light';
$isDark = ($theme === 'dark');

$user = $_SESSION['user'];
$userId      = $user['id'];
$userName    = $user['name']  ?? 'User';
$userEmail   = $user['email'] ?? 'user@university.edu';
$userPhone   = $user['phone'] ?? '';
$userMajor   = $user['major'] ?? '';
$userAvatar  = $user['avatar_filename'] ?? null;
$role        = $user['role'] ?? null;

$msg = '';

// Handle avatar deletion
if (isset($_POST['delete_avatar'])) {
    if ($userAvatar && file_exists($userAvatar)) {
        unlink($userAvatar); // delete file from server
    }

    $stmt = $conn->prepare("UPDATE users SET avatar_filename = NULL WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $_SESSION['user']['avatar_filename'] = null;
    $userAvatar = null;
    $msg = "Profile photo deleted successfully.";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_avatar'])) {
    $updatedName  = $_POST['name']  ?? $userName;
    $updatedEmail = $_POST['email'] ?? $userEmail;
    $updatedPhone = $_POST['phone'] ?? $userPhone;
    $updatedMajor = $_POST['major'] ?? $userMajor;

    $avatarFilename = $userAvatar;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $avatarTmp   = $_FILES['avatar']['tmp_name'];
        $avatarName  = $_FILES['avatar']['name'];
        $uniqueName  = time() . "_" . $avatarName;
        $uploadDir   = "avatars/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $uploadPath = $uploadDir . $uniqueName;
        if (move_uploaded_file($avatarTmp, $uploadPath)) {
            $avatarFilename = $uploadPath;
        } else {
            $msg = "Error uploading new profile picture.";
        }
    }

    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, major=?, avatar_filename=? WHERE id=?");
    $stmt->bind_param("sssssi", $updatedName, $updatedEmail, $updatedPhone, $updatedMajor, $avatarFilename, $userId);

    if ($stmt->execute()) {
        $_SESSION['user']['name']            = $updatedName;
        $_SESSION['user']['email']           = $updatedEmail;
        $_SESSION['user']['phone']           = $updatedPhone;
        $_SESSION['user']['major']           = $updatedMajor;
        $_SESSION['user']['avatar_filename'] = $avatarFilename;

        $msg = "Profile updated successfully!";
    } else {
        $msg = "Error updating profile: " . $conn->error;
    }

    $userName   = $_SESSION['user']['name'];
    $userEmail  = $_SESSION['user']['email'];
    $userPhone  = $_SESSION['user']['phone'];
    $userMajor  = $_SESSION['user']['major'];
    $userAvatar = $_SESSION['user']['avatar_filename'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CampusConnect - Profile</title>
  <style>
    :root {
      --bg-color: <?= $isDark ? '#1e1e26' : '#f4f5f9' ?>;
      --text-color: <?= $isDark ? '#e0e0e0' : '#333' ?>;
      --sidebar-bg: <?= $isDark ? '#181822' : '#1e3d7b' ?>;
      --sidebar-link: <?= $isDark ? '#ccc' : '#cbd3e6' ?>;
      --sidebar-link-hover: <?= $isDark ? '#2e3440' : '#2a4d95' ?>;
      --topbar-bg: <?= $isDark ? '#2c2c38' : '#fff' ?>;
      --topbar-border: <?= $isDark ? '#444' : '#e7e7e7' ?>;
      --card-bg: <?= $isDark ? '#2a2f3b' : '#fff' ?>;
      --btn-bg: <?= $isDark ? '#3e8ed0' : '#1e3d7b' ?>;
      --btn-hover: <?= $isDark ? '#5aa1e3' : '#2a4d95' ?>;
      --heading-color: <?= $isDark ? '#ffffff' : '#1e3d7b' ?>;
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
    text-decoration: none;
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
      overflow: hidden;
    }
    .user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .content-section {
      padding: 20px;
    }
    .content-section h1 {
      margin-bottom: 20px;
      font-size: 22px;
      color: var(--heading-color);
    }
    .profile-container {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      align-items: flex-start;
    }
    .profile-form-container {
      flex: 1;
      max-width: 50%;
      background-color: var(--card-bg);
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      padding: 20px;
      min-width: 280px;
    }
    .large-avatar-container {
      flex: 1;
      max-width: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      min-width: 280px;
    }
    .large-avatar-container img {
      max-width: 100%;
      max-height: 400px;
      border-radius: 8px;
      object-fit: cover;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 5px;
    }
    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="file"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
      background: <?= $isDark ? '#3a3f4b' : '#fff' ?>;
      color: var(--text-color);
    }
    .btn-submit {
      background-color: var(--btn-bg);
      color: #fff;
      padding: 10px 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
    }
    .btn-submit:hover {
      background-color: var(--btn-hover);
    }
    .message {
      margin-top: 15px;
      font-weight: bold;
      color: var(--btn-bg);
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
          <?php if ($userAvatar): ?>
            <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar">
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="content-section">
      <h1>My Profile</h1>
      <?php if (!empty($msg)): ?>
        <div class="message"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <div class="profile-container">
        <div class="profile-form-container">
          <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input type="text" name="name" value="<?= htmlspecialchars($userName) ?>" required>
            </div>
            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" name="email" value="<?= htmlspecialchars($userEmail) ?>" required>
            </div>
            <div class="form-group">
              <label for="phone">Phone Number (optional)</label>
              <input type="text" name="phone" value="<?= htmlspecialchars($userPhone) ?>">
            </div>
            <div class="form-group">
              <label for="major">Major (optional)</label>
              <input type="text" name="major" value="<?= htmlspecialchars($userMajor) ?>">
            </div>
            <div class="form-group">
              <label for="avatar">Profile Picture (optional)</label>
              <input type="file" name="avatar" accept="image/*">
            </div>
            <button type="submit" class="btn-submit">Update Profile</button>
          </form>
        </div>

        <div class="large-avatar-container">
          <?php if ($userAvatar): ?>
            <div style="text-align: center;">
              <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Profile Picture">
              <form method="POST" style="margin-top: 10px;">
                <input type="hidden" name="delete_avatar" value="1" />
                <button type="submit" class="btn-submit" style="background-color: crimson;">Delete Photo</button>
              </form>
            </div>
          <?php else: ?>
            <img src="path/to/default_profile.png" alt="Default Avatar" style="opacity: 0.5;">
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
