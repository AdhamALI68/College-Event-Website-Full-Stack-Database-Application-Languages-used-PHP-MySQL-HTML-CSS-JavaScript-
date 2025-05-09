<?php
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['student','admin'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user']['id'];
$userName  = $_SESSION['user']['name'] ?? 'Student';
$userEmail = $_SESSION['user']['email'] ?? 'email@university.edu';
$userAvatar = $_SESSION['user']['avatar_filename'] ?? null;
$theme = $_SESSION['theme'] ?? 'light';
$role = $_SESSION['user']['role'] ?? null;

if (isset($_POST['cancel'], $_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
    $stmt = $conn->prepare("DELETE FROM event_participants WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $user_id, $event_id);
    $stmt->execute();
}

if (isset($_POST['rate'], $_POST['event_id'], $_POST['rating'])) {
    $event_id = intval($_POST['event_id']);
    $rating = intval($_POST['rating']);
    $conn->query("DELETE FROM event_ratings WHERE user_id = $user_id AND event_id = $event_id");
    $stmt = $conn->prepare("INSERT INTO event_ratings (user_id, event_id, rating) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $user_id, $event_id, $rating);
    $stmt->execute();
}

$query = "
    SELECT e.*, l.name AS location_name,
           (SELECT rating FROM event_ratings WHERE user_id = ? AND event_id = e.id) AS user_rating
    FROM event_participants ep
    JOIN events e ON ep.event_id = e.id
    JOIN locations l ON e.location_id = l.loc_id
    WHERE ep.user_id = ?
    ORDER BY e.event_date, e.start_time
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$isDark = $theme === 'dark';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Joined Events - CampusConnect</title>
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
            text-decoration: none;
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

        .events-section {
            padding: 20px;
        }
        .events-section h1 {
            margin-bottom: 20px;
            font-size: 22px;
        }

        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .event-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }

        .event-image {
            width: 100%;
            height: 150px;
            background-color: #ccc;
            background-size: cover;
            background-position: center;
        }

        .card-content {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .event-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .event-details {
            font-size: 13px;
            opacity: 0.8;
            margin-bottom: 8px;
        }

        .event-actions {
            margin-top: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        select {
            padding: 4px;
            border-radius: 4px;
        }

        .details-button {
            padding: 6px 10px;
            border-radius: 4px;
            background-color: var(--button-bg);
            color: #fff;
            font-size: 13px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .details-button:hover {
            background-color: var(--button-hover);
        }

        .rate-button {
            background: #28a745;
        }
        .cancel-button {
            background: #dc3545;
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


        <div class="events-section">
            <h1>My Joined Events</h1>
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="event-grid">
                    <?php while ($row = $result->fetch_assoc()):
                        $image_url = !empty($row['image_filename']) ? $row['image_filename'] : 'https://via.placeholder.com/400x200/EEE/333?text=Event+Image';
                    ?>
                        <div class="event-card">
                            <div class="event-image" style="background-image: url('<?= htmlspecialchars($image_url) ?>');"></div>
                            <div class="card-content">
                                <div>
                                    <div class="event-name"><?= htmlspecialchars($row['name']) ?></div>
                                    <div class="event-details">
                                        <?= date("D, M j g:i A", strtotime($row['event_date'].' '.$row['start_time'])) ?><br>
                                        Location: <?= htmlspecialchars($row['location_name']) ?><br>
                                        Type: <?= ucfirst($row['type']) ?><br>
                                        Rating: <?= $row['user_rating'] ? $row['user_rating'] . " ⭐" : "Not rated" ?>
                                    </div>
                                </div>
                                <div class="event-actions">
                                    <form method="POST">
                                        <input type="hidden" name="event_id" value="<?= $row['id'] ?>">
                                        <select name="rating">
                                            <option value="">Rate</option>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <option value="<?= $i ?>"><?= $i ?> ⭐</option>
                                            <?php endfor; ?>
                                        </select>
                                        <button type="submit" name="rate" class="details-button rate-button">Submit</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="event_id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="cancel" class="details-button cancel-button">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="opacity: 0.7;">You haven’t joined any events yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
