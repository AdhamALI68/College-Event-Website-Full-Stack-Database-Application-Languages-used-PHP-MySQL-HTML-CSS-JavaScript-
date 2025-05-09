<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['student','admin'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$userId = $user['id'];
$userName = $user['name'];
$userEmail = $user['email'];
$userAvatar = $user['avatar_filename'] ?? null;
$universityId = $user['university_id'] ?? null;
$role = $user['role'];
$theme = $_SESSION['theme'] ?? 'light';
$isDark = $theme === 'dark';
$message = '';

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get university name
$universityName = '';
if ($universityId) {
    $result = $conn->query("SELECT name FROM universities WHERE id = $universityId");
    if ($row = $result->fetch_assoc()) {
        $universityName = $row['name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rsoName = trim($_POST['rso_name']);
    $description = trim($_POST['description']);
    $photoFilename = null;

    // Handle photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoFilename = 'rso_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $uploadPath = __DIR__ . '/uploads/' . $photoFilename;
        move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath);
    }

    if (!empty($rsoName) && $universityId) {
        // Check for duplicate RSO name
        $check = $conn->prepare("SELECT id FROM rsos WHERE name = ?");
        $check->bind_param("s", $rsoName);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "An RSO with this name already exists. Please choose a different name.";
        } else {
            $stmt = $conn->prepare("INSERT INTO rsos (name, description, university_id, created_by, approved, status, photo) VALUES (?, ?, ?, ?, 0, 'pending', ?)");
            $stmt->bind_param("ssiis", $rsoName, $description, $universityId, $userId, $photoFilename);
            if ($stmt->execute()) {
                $newRsoId = $conn->insert_id;

                // Insert creator as 'member' (not admin yet)
                $memberStmt = $conn->prepare("INSERT INTO rso_members (rso_id, user_id, role) VALUES (?, ?, 'member')");
                $memberStmt->bind_param("ii", $newRsoId, $userId);
                $memberStmt->execute();

                $message = "RSO request submitted successfully!";
            } else {
                $message = "Error creating RSO.";
            }
        }
    } else {
        $message = "RSO name is required.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create RSO</title>
    <style>
        :root {
            --bg-color: <?= $isDark ? '#1e1e26' : '#f4f5f9' ?>;
            --text-color: <?= $isDark ? '#fff' : '#333' ?>;
            --input-bg: <?= $isDark ? '#2e2e38' : '#fff' ?>;
            --sidebar-bg: <?= $isDark ? '#181822' : '#1e3d7b' ?>;
            --sidebar-link: <?= $isDark ? '#ccc' : '#cbd3e6' ?>;
            --sidebar-link-hover: <?= $isDark ? '#2e3440' : '#2a4d95' ?>;
            --button-bg: <?= $isDark ? '#3e8ed0' : '#1e3d7b' ?>;
            --button-hover: <?= $isDark ? '#5aa1e3' : '#2a4d95' ?>;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        a { text-decoration: none !important; }
        body {
            font-family: "Segoe UI", sans-serif;
            display: flex;
            background-color: var(--bg-color);
            color: var(--text-color);
            height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            color: white;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar .brand {
            margin-left: 20px;
            font-size: 18px;
            font-weight: bold;
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
            justify-content: flex-end;
            align-items: center;
            padding: 0 20px;
            border-bottom: 1px solid <?= $isDark ? '#444' : '#e7e7e7' ?>;
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
            font-weight: bold;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background-color: #ccc;
            border-radius: 50%;
        }

        .content-section {
            padding: 30px 40px;
            max-width: 600px;
        }

        .content-section h1 {
            margin-bottom: 20px;
        }

        form input, form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            background-color: var(--input-bg);
            border: 1px solid #aaa;
            border-radius: 5px;
            color: var(--text-color);
        }

        form input[disabled] {
            background-color: #ccc;
            color: #666;
            cursor: not-allowed;
        }

        .submit-btn {
            background-color: var(--button-bg);
            color: white;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }

        .submit-btn:hover {
            background-color: var(--button-hover);
        }

        .message {
            margin-top: 15px;
            font-weight: bold;
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
                <div class="email" style="font-size: 12px; opacity: 0.7;"><?= htmlspecialchars($userEmail) ?></div>
            </div>
            <div class="user-avatar">
                <?php if (!empty($userAvatar)): ?>
                    <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content-section">
        <h1>Create New RSO</h1>
        <form method="POST" enctype="multipart/form-data">
            <label>RSO Name</label>
            <input type="text" name="rso_name" placeholder="Enter RSO name" required>

            <label>Description</label>
            <textarea name="description" rows="4" placeholder="Brief description of the RSO"></textarea>

            <label>Upload RSO Photo</label>
            <input type="file" name="photo" accept="image/*">

            <label>Admin Email</label>
            <input type="email" value="<?= htmlspecialchars($userEmail) ?>" disabled>

            <label>University</label>
            <input type="text" value="<?= htmlspecialchars($universityName) ?>" disabled>

            <button type="submit" class="submit-btn">Submit RSO Request</button>
        </form>

        <?php if (!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
