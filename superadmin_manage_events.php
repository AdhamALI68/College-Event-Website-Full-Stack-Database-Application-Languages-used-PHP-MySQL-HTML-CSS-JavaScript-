<?php
session_start();

// Ensure only superadmins can access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$theme = $_SESSION['theme'] ?? 'light';
$isDark = ($theme === 'dark');

// Basic user info
$userName   = $_SESSION['user']['name'] ?? 'Super Admin';
$userEmail  = $_SESSION['user']['email'] ?? 'admin@university.edu';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;

// Check if we are editing a specific event
$editEventId = isset($_GET['edit']) ? intval($_GET['edit']) : null;
$msg = '';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM events WHERE id = $id");
    header("Location: superadmin_manage_events.php");
    exit();
}

// Handle update
if (isset($_POST['update_event'])) {
    $event_id      = intval($_POST['event_id']);
    $name          = $_POST['name'];
    $description   = $_POST['description'];
    $type          = $_POST['type'];
    $event_date    = $_POST['event_date'];
    $start_time    = $_POST['start_time'];
    $end_time      = $_POST['end_time'];
    $contact_email = $_POST['contact_email'];
    $contact_phone = $_POST['contact_phone'];

    // Handle image upload or keep existing
    $imagePath = $_POST['existing_image'] ?? null;
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/events/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $imageName = time() . "_" . basename($_FILES["image_file"]["name"]);
        $imagePath = $targetDir . $imageName;
        move_uploaded_file($_FILES["image_file"]["tmp_name"], $imagePath);
    }

    $stmt = $conn->prepare("UPDATE events 
        SET name=?, description=?, type=?, event_date=?, start_time=?, end_time=?, contact_email=?, contact_phone=?, image_filename=? 
        WHERE id=?");
    $stmt->bind_param("sssssssssi", $name, $description, $type, $event_date, $start_time, $end_time, $contact_email, $contact_phone, $imagePath, $event_id);

    if ($stmt->execute()) {
        $msg = "✅ Event updated successfully!";
    } else {
        $msg = "❌ Error updating event: " . $conn->error;
    }
}

// Fetch event to edit (if needed)
$eventToEdit = null;
if ($editEventId) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
    $stmt->bind_param("i", $editEventId);
    $stmt->execute();
    $eventToEdit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all events (if not editing)
$allEvents = [];
if (!$editEventId) {
    $res = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
    if ($res && $res->num_rows > 0) {
        $allEvents = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CampusConnect - Manage Events</title>
  <style>
    :root {
      /* Same color variables as your dashboard */
      --bg-color: <?= $isDark ? '#1e1e26' : '#f4f5f9' ?>;
      --text-color: <?= $isDark ? '#e0e0e0' : '#333' ?>;
      --heading-color: <?= $isDark ? '#ffffff' : '#1e3d7b' ?>;
      --muted-text-color: <?= $isDark ? '#b8b8b8' : '#555' ?>;
      --card-bg: <?= $isDark ? '#2a2f3b' : '#fff' ?>;
      --sidebar-bg: <?= $isDark ? '#181822' : '#1e3d7b' ?>;
      --sidebar-link: <?= $isDark ? '#ccc' : '#cbd3e6' ?>;
      --sidebar-link-hover: <?= $isDark ? '#2e3440' : '#2a4d95' ?>;
      --topbar-bg: <?= $isDark ? '#2c2c38' : '#fff' ?>;
      --topbar-border: <?= $isDark ? '#444' : '#e7e7e7' ?>;

      /* Button colors */
      --primary-btn-bg: #1e3d7b;
      --primary-btn-hover: #2a4d95;
      --danger-btn-bg: #d9534f;
      --danger-btn-hover: #c9302c;
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

    /* MAIN CONTENT AREA */
    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
        scrollbar-gutter: stable;
    }
    .main-content::-webkit-scrollbar {
      width: 8px;
    }
    .main-content::-webkit-scrollbar-track {
      background-color: var(--bg-color);
    }
    .main-content::-webkit-scrollbar-thumb {
      background-color: #666;
      border-radius: 4px;
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
      overflow: hidden;
    }
    .user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* CONTENT SECTION */
    .content-section {
      padding: 20px;
    }
    .content-section h2 {
      margin-bottom: 20px;
      font-size: 22px;
      color: var(--heading-color);
    }
    p.message {
      margin: 10px 0;
      font-size: 14px;
    }

    /* CARD GRID */
    .card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 20px;
    }
    .card-item {
      background-color: var(--card-bg);
      padding: 15px;
      border-radius: 8px;
      display: flex;
      flex-direction: column;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .card-item h3 {
      font-size: 16px;
      margin-bottom: 8px;
      color: var(--text-color);
    }
    .card-item p {
      font-size: 14px;
      margin-bottom: 6px;
      color: var(--muted-text-color);
    }
    .card-item img {
      max-width: 100%;
      border-radius: 6px;
      margin-bottom: 10px;
      object-fit: cover;
    }
    .card-actions {
      display: flex;
      justify-content: space-between;
      margin-top: auto;
    }

    /* BUTTONS */
    .edit-btn,
    .delete-btn {
      border: none;
      border-radius: 4px;
      padding: 8px 14px;
      color: #fff;
      cursor: pointer;
      font-size: 14px;
    }
    .edit-btn {
      background-color: var(--primary-btn-bg);
    }
    .edit-btn:hover {
      background-color: var(--primary-btn-hover);
    }
    .delete-btn {
      background-color: var(--danger-btn-bg);
    }
    .delete-btn:hover {
      background-color: var(--danger-btn-hover);
    }

    /* EDIT FORM */
    .edit-form {
      background-color: var(--card-bg);
      padding: 20px;
      border-radius: 8px;
      max-width: 600px;
      margin: 0 auto;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .edit-form h2 {
      margin-bottom: 15px;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      font-weight: 600;
      display: block;
      margin-bottom: 6px;
      color: var(--text-color);
    }
    .form-group input,
    .form-group textarea,
    .form-group select {
      width: 100%;
      padding: 10px;
      border-radius: 6px;
      border: 1px solid #ccc;
      background: <?= $isDark ? '#3a3f4b' : '#fff' ?>;
      color: var(--text-color);
      font-size: 14px;
    }
    .update-btn {
      background: var(--primary-btn-bg);
      color: #fff;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      padding: 10px 16px;
      font-size: 14px;
    }
    .update-btn:hover {
      background-color: var(--primary-btn-hover);
    }

    @media (max-width: 768px) {
      .sidebar {
        width: 60px;
      }
      .sidebar .brand {
        display: none;
      }
      .sidebar .nav-links li a {
        text-align: center;
        padding: 10px 0;
      }
      .main-content {
        margin-left: 60px;
      }
    }
    @media (max-width: 576px) {
      .content-section h2 {
        font-size: 18px;
      }
      .card-grid {
        grid-template-columns: 1fr;
      }
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

<!-- MAIN CONTENT -->
<div class="main-content">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="user-info">
      <div class="user-text">
        <div class="name"><?= htmlspecialchars($userName) ?></div>
        <div style="font-size: 12px; opacity: 0.7;">
          <?= htmlspecialchars($userEmail) ?>
        </div>
      </div>
      <div class="user-avatar">
        <?php if (!empty($userAvatar)): ?>
          <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar">
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- CONTENT SECTION -->
  <div class="content-section">

    <?php if ($msg): ?>
      <p class="message"><?= $msg ?></p>
    <?php endif; ?>

    <!-- If we're editing one event, show the form -->
    <?php if ($editEventId && $eventToEdit): ?>
      <div class="edit-form">
        <h2>Edit Event: <?= htmlspecialchars($eventToEdit['name']) ?></h2>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="event_id" value="<?= $eventToEdit['id'] ?>">
          <input type="hidden" name="existing_image" value="<?= $eventToEdit['image_filename'] ?>">

          <div class="form-group">
            <label>Event Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($eventToEdit['name']) ?>" required>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4" required><?= htmlspecialchars($eventToEdit['description']) ?></textarea>
          </div>

          <div class="form-group">
            <label>Type</label>
            <select name="type" required>
              <option value="public"  <?= $eventToEdit['type'] === 'public'  ? 'selected' : '' ?>>Public</option>
              <option value="private" <?= $eventToEdit['type'] === 'private' ? 'selected' : '' ?>>Private</option>
              <option value="rso"     <?= $eventToEdit['type'] === 'rso'     ? 'selected' : '' ?>>RSO</option>
            </select>
          </div>

          <div class="form-group">
            <label>Event Date</label>
            <input type="date" name="event_date" value="<?= $eventToEdit['event_date'] ?>" required>
          </div>

          <div class="form-group">
            <label>Start Time</label>
            <input type="time" name="start_time" value="<?= $eventToEdit['start_time'] ?>" required>
          </div>

          <div class="form-group">
            <label>End Time</label>
            <input type="time" name="end_time" value="<?= $eventToEdit['end_time'] ?>" required>
          </div>

          <div class="form-group">
            <label>Contact Email</label>
            <input type="email" name="contact_email" value="<?= $eventToEdit['contact_email'] ?>">
          </div>

          <div class="form-group">
            <label>Contact Phone</label>
            <input type="text" name="contact_phone" value="<?= $eventToEdit['contact_phone'] ?>">
          </div>

          <div class="form-group">
            <label>Upload New Image</label>
            <input type="file" name="image_file" accept="image/*">
          </div>

          <?php if (!empty($eventToEdit['image_filename'])): ?>
            <div style="margin-bottom: 15px;">
              <label>Current Image</label><br>
              <img src="<?= htmlspecialchars($eventToEdit['image_filename']) ?>"
                   alt="Event Image"
                   style="max-width:100%; border-radius:8px; margin-top:5px;">
            </div>
          <?php endif; ?>

          <button type="submit" name="update_event" class="update-btn">Save Changes</button>
        </form>
      </div>

    <!-- Otherwise, show all events in a card grid -->
    <?php else: ?>
      <h2>All Events</h2>
      <?php if (count($allEvents) > 0): ?>
        <div class="card-grid">
          <?php foreach ($allEvents as $ev): ?>
            <div class="card-item">
              <h3><?= htmlspecialchars($ev['name']) ?></h3>
              <p><strong>Date:</strong> <?= $ev['event_date'] ?></p>
              <p><strong>Time:</strong> <?= substr($ev['start_time'], 0, 5) ?> - <?= substr($ev['end_time'], 0, 5) ?></p>
              <p><strong>Type:</strong> <?= htmlspecialchars($ev['type']) ?></p>
              <?php if (!empty($ev['image_filename'])): ?>
                <img src="<?= htmlspecialchars($ev['image_filename']) ?>" alt="Event Image">
              <?php else: ?>
                <!-- fallback image if you like -->
                <img src="assets/placeholder.jpg" alt="Event Image">
              <?php endif; ?>

              <div class="card-actions">
                <a href="?edit=<?= $ev['id'] ?>" class="edit-btn">Edit</a>
                <a href="?delete=<?= $ev['id'] ?>"
                   onclick="return confirm('Are you sure you want to delete this event?')"
                   class="delete-btn">Delete</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p>No events found.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div> <!-- .content-section -->
</div> <!-- .main-content -->
</body>
</html>
