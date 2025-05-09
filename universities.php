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

$theme      = $_SESSION['theme'] ?? 'light';
$isDark     = ($theme === 'dark');
$userName   = $_SESSION['user']['name'] ?? 'Super Admin';
$userEmail  = $_SESSION['user']['email'] ?? 'admin@university.edu';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;

// Handle universities
if (isset($_POST['add_university'])) {
    $stmt = $conn->prepare("INSERT INTO universities (name, description, email_domain, location, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $_POST['uni_name'], $_POST['uni_description'], $_POST['uni_email'], $_POST['uni_location']);
    $stmt->execute();
}
if (isset($_POST['edit_university'])) {
    $stmt = $conn->prepare("UPDATE universities SET name=?, description=?, email_domain=?, location=? WHERE id=?");
    $stmt->bind_param("ssssi", $_POST['edit_uni_name'], $_POST['edit_uni_description'], $_POST['edit_uni_email'], $_POST['edit_uni_location'], $_POST['edit_uni_id']);
    $stmt->execute();
}
if (isset($_POST['delete_university'])) {
    $uid = intval($_POST['delete_university']);
    $conn->query("DELETE FROM locations WHERE university_id = $uid");
    $conn->query("DELETE FROM universities WHERE id = $uid");
}

// Handle locations
if (isset($_POST['add_location'])) {
    $stmt = $conn->prepare("INSERT INTO locations (university_id, name, address, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $_POST['location_uni_id'], $_POST['location_name'], $_POST['location_address']);
    $stmt->execute();
}
if (isset($_POST['edit_location'])) {
    $stmt = $conn->prepare("UPDATE locations SET university_id=?, name=?, address=? WHERE loc_id=?");
    $stmt->bind_param("issi", $_POST['edit_location_uni_id'], $_POST['edit_location_name'], $_POST['edit_location_address'], $_POST['edit_loc_id']);
    $stmt->execute();
}
if (isset($_POST['delete_location'])) {
    $lid = intval($_POST['delete_location']);
    $conn->query("DELETE FROM locations WHERE loc_id = $lid");
}

$universities = $conn->query("SELECT * FROM universities ORDER BY id ASC");
$locations = $conn->query("SELECT l.*, u.name AS university_name FROM locations l JOIN universities u ON l.university_id = u.id ORDER BY l.loc_id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CampusConnect | Universities</title>
    <style>
        :root {
            --bg-color: <?= $isDark ? '#1e1e26' : '#f4f5f9' ?>;
            --text-color: <?= $isDark ? '#e0e0e0' : '#333' ?>;
            --card-bg: <?= $isDark ? '#2a2f3b' : '#fff' ?>;
            --sidebar-bg: <?= $isDark ? '#181822' : '#1e3d7b' ?>;
            --sidebar-link: <?= $isDark ? '#ccc' : '#cbd3e6' ?>;
            --sidebar-link-hover: <?= $isDark ? '#2e3440' : '#2a4d95' ?>;
            --topbar-bg: <?= $isDark ? '#2c2c38' : '#fff' ?>;
            --topbar-border: <?= $isDark ? '#444' : '#e7e7e7' ?>;
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
        a {
          text-decoration: none;
          color: inherit;
        }
        ul {
          list-style-type: none;
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
  background-color: var(--topbar-bg);
  border-bottom: 1px solid var(--topbar-border);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 0 20px;
  flex-shrink: 0;
  box-sizing: border-box;
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
        h2 {
            font-size: 22px;
            margin-bottom: 20px;
        }
        form {
            background: var(--card-bg);
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        input, select {
            padding: 10px;
            margin: 8px 0;
            width: 100%;
            max-width: 400px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: <?= $isDark ? '#2e2e38' : '#fff' ?>;
            color: var(--text-color);
        }
        button {
            background: #1e3d7b;
            color: #fff;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background: #2a4d95;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: var(--card-bg);
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            border-radius: 6px;
            overflow: hidden;
        }
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: <?= $isDark ? '#333948' : '#f1f1f1' ?>;
        }
        .actions form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .actions input {
            flex: 1 1 150px;
        }
    </style>
</head>
<body>
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
                    <div style="font-size: 12px; opacity: 0.7;"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="user-avatar">
                    <?php if (!empty($userAvatar)): ?>
                        <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar">
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-section">
            <h2>Universities</h2>
            <form method="POST">
                <input name="uni_name" placeholder="University Name" required>
                <input name="uni_description" placeholder="Description">
                <input name="uni_email" placeholder="Email Domain" required>
                <input name="uni_location" placeholder="Location" required>
                <button type="submit" name="add_university">Add University</button>
            </form>

            <table>
                <tr><th>ID</th><th>Name</th><th>Description</th><th>Email</th><th>Location</th><th>Actions</th></tr>
                <?php $universities->data_seek(0); while ($u = $universities->fetch_assoc()): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['description']) ?></td>
                        <td><?= htmlspecialchars($u['email_domain']) ?></td>
                        <td><?= htmlspecialchars($u['location']) ?></td>
                        <td>
                            <form method="POST" class="actions">
                                <input type="hidden" name="edit_uni_id" value="<?= $u['id'] ?>">
                                <input name="edit_uni_name" value="<?= htmlspecialchars($u['name']) ?>">
                                <input name="edit_uni_description" value="<?= htmlspecialchars($u['description']) ?>">
                                <input name="edit_uni_email" value="<?= htmlspecialchars($u['email_domain']) ?>">
                                <input name="edit_uni_location" value="<?= htmlspecialchars($u['location']) ?>">
                                <button name="edit_university">Save</button>
                                <button name="delete_university" value="<?= $u['id'] ?>" onclick="return confirm('Delete university?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>

            <h2 style="margin-top: 40px;">Locations</h2>
            <form method="POST">
                <select name="location_uni_id" required>
                    <option value="">Select University</option>
                    <?php $universities->data_seek(0); while ($u = $universities->fetch_assoc()): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <input name="location_name" placeholder="Campus Name" required>
                <input name="location_address" placeholder="Address" required>
                <button type="submit" name="add_location">Add Location</button>
            </form>

            <table>
                <tr><th>ID</th><th>University</th><th>Name</th><th>Address</th><th>Actions</th></tr>
                <?php while ($l = $locations->fetch_assoc()): ?>
                    <tr>
                        <td><?= $l['loc_id'] ?></td>
                        <td><?= htmlspecialchars($l['university_name']) ?></td>
                        <td><?= htmlspecialchars($l['name']) ?></td>
                        <td><?= htmlspecialchars($l['address']) ?></td>
                        <td>
                            <form method="POST" class="actions">
                                <input type="hidden" name="edit_loc_id" value="<?= $l['loc_id'] ?>">
                                <input name="edit_location_uni_id" value="<?= $l['university_id'] ?>">
                                <input name="edit_location_name" value="<?= htmlspecialchars($l['name']) ?>">
                                <input name="edit_location_address" value="<?= htmlspecialchars($l['address']) ?>">
                                <button name="edit_location">Save</button>
                                <button name="delete_location" value="<?= $l['loc_id'] ?>" onclick="return confirm('Delete location?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
