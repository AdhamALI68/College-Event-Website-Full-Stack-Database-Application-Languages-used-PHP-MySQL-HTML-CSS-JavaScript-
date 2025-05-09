<?php
session_start();

// Restrict page to student or admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['student','admin'])) {
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];
$role = $user['role'];
$student_id    = $user['id'];
$university_id = $user['university_id'];
$userName      = $user['name']  ?? 'Student';
$userEmail     = $user['email'] ?? 'student@university.edu';
$userAvatar    = $user['avatar_filename'] ?? null;
$theme         = $_SESSION['theme'] ?? 'light';
$isDark        = ($theme === 'dark');

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle "Join" submission
$message = "";
if (isset($_POST['join']) && isset($_POST['rso_id'])) {
    $rso_id = intval($_POST['rso_id']);

    // Check if user is already a member
    $check = $conn->prepare("SELECT * FROM rso_members WHERE rso_id = ? AND user_id = ?");
    $check->bind_param("ii", $rso_id, $student_id);
    $check->execute();
    $result = $check->get_result();
    if ($result->num_rows > 0) {
        $message = "❌ You are already a member of this RSO.";
    } else {
        // Insert membership
        $stmt = $conn->prepare("INSERT INTO rso_members (rso_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $rso_id, $student_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "✅ Joined RSO successfully.";
        } else {
            $message = "❌ Error joining RSO.";
        }
    }
}

// Fetch ALL RSOs at the user's university, whether approved or not
// (excludes RSOs the user already joined).
$query = "
    SELECT r.*, u.name AS admin_name, u.email AS admin_email
    FROM rsos r
    JOIN users u ON r.created_by = u.id
    WHERE r.university_id = ?
      AND r.id NOT IN (
          SELECT rso_id FROM rso_members WHERE user_id = ?
      )
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $university_id, $student_id);
$stmt->execute();
$rsos = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Join an RSO - CampusConnect</title>
<style>
  :root {
    --bg-color: <?= $isDark ? '#1e1e26' : '#f4f5f9' ?>;
    --text-color: <?= $isDark ? '#fff' : '#333' ?>;
    --card-bg: <?= $isDark ? '#2a2f3b' : '#fff' ?>;
    --sidebar-bg: <?= $isDark ? '#181822' : '#1e3d7b' ?>;
    --sidebar-link: <?= $isDark ? '#ccc' : '#cbd3e6' ?>;
    --sidebar-link-hover: <?= $isDark ? '#2e3440' : '#2a4d95' ?>;
    --button-bg: #1e3d7b; /* or your chosen color */
    --button-hover: #2a4d95;
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
    overflow: hidden;
  }
  .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  /* CONTENT SECTION */
  .content-section {
    padding: 30px 40px;
  }
  .message {
    text-align: center;
    margin-bottom: 15px;
    font-weight: bold;
  }

  /* RSO GRID */
  .rso-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
  }

  /* RSO CARD */
  .rso-card {
    background-color: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    font-family: "Segoe UI", Arial, sans-serif;
  }
  .rso-image {
    width: 100%;
    height: 200px; /* adjust as desired */
    background-size: cover;
    background-position: center;
    background-color: #aaa;
  }
  .rso-card-content {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
  }
  .rso-name {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 10px;
  }
  .pending-badge {
    font-size: 12px;
    font-weight: normal;
    color: #999;
    margin-left: 8px;
  }
  .rso-description, .rso-admin {
    font-size: 14px;
    margin-bottom: 5px;
    line-height: 1.4;
  }
  .join-button {
    margin-top: auto;
    padding: 10px 18px;
    background-color: var(--button-bg);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.3s;
  }
  .join-button:hover {
    background-color: var(--button-hover);
  }

  /* SCROLLBAR */
  .main-content::-webkit-scrollbar {
    width: 8px;
  }
  .main-content::-webkit-scrollbar-track {
    background-color: var(--bg-color);
  }
  .main-content::-webkit-scrollbar-thumb {
    background-color: #999;
    border-radius: 4px;
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
          <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar">
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="content-section">
    <?php if ($message): ?>
      <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="rso-grid">
      <?php if ($rsos->num_rows === 0): ?>
        <div style="padding: 20px 40px;">
          <h2 style="font-size: 28px; font-weight: 700; color: var(--text-color); margin-top: 10px;">
            No RSOs Available
          </h2>
        </div>
      <?php else: ?>
        <?php while ($rso = $rsos->fetch_assoc()): ?>
          <div class="rso-card">
            <!-- RSO Banner Image -->
            <?php if (!empty($rso['photo'])): ?>
              <div class="rso-image"
                   style="background-image: url('uploads/<?= htmlspecialchars($rso['photo']) ?>');">
              </div>
            <?php else: ?>
              <div class="rso-image"
                   style="background-image: url('https://via.placeholder.com/700x300/EEE/999?text=No+Image');">
              </div>
            <?php endif; ?>

            <!-- RSO Card Content -->
            <div class="rso-card-content">
              <div class="rso-name">
                <?= htmlspecialchars($rso['name']) ?>
                <?php if ($rso['approved'] == 0): ?>
                  <span class="pending-badge">(Pending Approval)</span>
                <?php endif; ?>
              </div>

              <div class="rso-description">
                <strong>Description:</strong> <?= htmlspecialchars($rso['description']) ?>
              </div>

              <div class="rso-admin">
                <strong>Admin:</strong>
                <?= htmlspecialchars($rso['admin_name']) ?>
                (<?= htmlspecialchars($rso['admin_email']) ?>)
              </div>

              <form method="POST" style="margin-top: 10px;">
                <input type="hidden" name="rso_id" value="<?= $rso['id'] ?>">
                <button type="submit" name="join" class="join-button">Join</button>
              </form>
            </div><!-- .rso-card-content -->
          </div><!-- .rso-card -->
        <?php endwhile; ?>
      <?php endif; ?>
    </div><!-- .rso-grid -->
  </div><!-- .content-section -->
</div><!-- .main-content -->
</body>
</html>
