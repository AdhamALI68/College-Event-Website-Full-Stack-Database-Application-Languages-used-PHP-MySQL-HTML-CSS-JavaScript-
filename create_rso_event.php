<?php
session_start();

// Only admins can create RSO events
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user       = $_SESSION['user'];
$userId     = $user['id'];
$userName   = $user['name']  ?? 'Admin';
$userEmail  = $user['email'] ?? 'admin@university.edu';
$userAvatar = $user['avatar_filename'] ?? null;
$theme      = $_SESSION['theme'] ?? 'light';
$isDark     = ($theme === 'dark');

$successMsg = '';
$errorMsg   = '';

// Fetch ONLY approved RSOs that THIS admin created
$rsoQuery = "
    SELECT r.id, r.name
    FROM rsos r
    WHERE r.created_by = ?
      AND r.approved = 1
";
$stmt = $conn->prepare($rsoQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$rsos = $stmt->get_result();

// Fetch available locations
$university_id = $user['university_id'];  // get the logged-in user's university_id
$stmtLoc = $conn->prepare("SELECT loc_id, name FROM locations WHERE university_id = ?");
$stmtLoc->bind_param("i", $university_id);
$stmtLoc->execute();
$locations = $stmtLoc->get_result();


// Handle form submission
if (isset($_POST['create_event'])) {
    $rso_id        = $_POST['rso_id'];
    $name          = $_POST['name'];
    $description   = $_POST['description'];
    $event_date    = $_POST['event_date'];
    $start_time    = $_POST['start_time'];
    $end_time      = $_POST['end_time'];
    $contact_email = $_POST['contact_email'] ?? null;
    $contact_phone = $_POST['contact_phone'] ?? null;
    $location_id   = $_POST['location_id'];
    $created_by    = $userId;

    // Handle image upload if provided
    $image_filename = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name    = $_FILES['image']['tmp_name'];
        $orig_name   = basename($_FILES['image']['name']);
        $unique_name = time() . "_" . $orig_name;
        $upload_path = "uploads/" . $unique_name;

        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        if (move_uploaded_file($tmp_name, $upload_path)) {
            $image_filename = $upload_path;
        } else {
            $errorMsg = "Image upload failed.";
        }
    }

    // Insert the RSO event (type = 'rso')
    $sql = "
        INSERT INTO events 
            (name, description, type, event_date, start_time, end_time, 
             contact_email, contact_phone, image_filename, created_by, location_id, rso_id, university_id)
        VALUES (?, ?, 'rso', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssii",
        $name,
        $description,
        $event_date,
        $start_time,
        $end_time,
        $contact_email,
        $contact_phone,
        $image_filename,
        $created_by,
        $location_id,
        $rso_id,
        $university_id
    );

    try {
        $stmt->execute();
        $successMsg = "✅ Event created successfully!";
    } catch (mysqli_sql_exception $e) {
        // Check if this is the overlap trigger error
        if (strpos($e->getMessage(), 'Overlapping event exists') !== false) {
            // Query ALL events for this location/date so we can list them
            $stmtAll = $conn->prepare("
                SELECT name, start_time, end_time
                FROM events
                WHERE location_id = ?
                  AND event_date = ?
                ORDER BY start_time
            ");
            $stmtAll->bind_param("is", $location_id, $event_date);
            $stmtAll->execute();
            $resultOverlap = $stmtAll->get_result();

            // Build a string with all events (name + times)
            $allDetails = "";
            while ($row = $resultOverlap->fetch_assoc()) {
                $formattedStart = date("g:i A", strtotime($row['start_time']));
                $formattedEnd   = date("g:i A", strtotime($row['end_time']));
                $allDetails .= htmlspecialchars($row['name']) . ": "
                             . $formattedStart . " - " . $formattedEnd . "<br />";
            }

            if ($allDetails) {
                // Style a red alert box
                $errorMsg = "
                <div style='padding: 12px; border-radius: 6px; background-color: #f8d7da; color: #721c24; margin-bottom: 15px;'>
                  <strong style='display: block; margin-bottom: 8px; font-size: 15px;'>
                    ❌ The event is overlapping with another event on this day.
                  </strong>
                  <p style='margin-bottom: 5px;'>
                    Here are all events for <em>$event_date</em> at this location:
                  </p>
                  <div style='font-size: 14px; line-height: 1.4;'>
                    $allDetails
                  </div>
                </div>";
            } else {
                // Fallback if no rows found
                $errorMsg = "
                <div style='padding: 12px; border-radius: 6px; background-color: #f8d7da; color: #721c24; margin-bottom: 15px;'>
                  <strong style='font-size: 15px;'>
                    ❌ The event is overlapping with another event.
                  </strong>
                </div>";
            }
        } else {
            // Another error from MySQL
            $errorMsg = "
            <div style='padding: 12px; border-radius: 6px; background-color: #f8d7da; color: #721c24; margin-bottom: 15px;'>
              <strong style='font-size: 15px;'>
                ❌ Error: " . htmlspecialchars($e->getMessage()) . "
              </strong>
            </div>";
        }
    }
    $stmt->close();

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CampusConnect - Create RSO Event</title>
  <style>
    :root {
      --bg-color: <?= $isDark ? '#1e1e26' : '#f4f5f9' ?>;
      --text-color: <?= $isDark ? '#fff' : '#333' ?>;
      --sidebar-bg: <?= $isDark ? '#181822' : '#1e3d7b' ?>;
      --sidebar-link: <?= $isDark ? '#ccc' : '#cbd3e6' ?>;
      --sidebar-link-hover: <?= $isDark ? '#2e3440' : '#2a4d95' ?>;
      --topbar-bg: <?= $isDark ? '#2c2c38' : '#fff' ?>;
      --topbar-border: <?= $isDark ? '#444' : '#e7e7e7' ?>;
      --form-bg: <?= $isDark ? '#2a2f3b' : '#fff' ?>;
      --btn-bg: <?= $isDark ? '#3e8ed0' : '#1e3d7b' ?>;
      --btn-hover: <?= $isDark ? '#5aa1e3' : '#2a4d95' ?>;
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
      list-style: none;
    }

    .sidebar {
      width: 250px;
      background-color: var(--sidebar-bg);
      display: flex;
      flex-direction: column;
      padding: 20px 0;
    }
    .sidebar .brand {
      color: white;
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
      background: #ccc;
      border-radius: 50%;
      overflow: hidden;
    }
    .user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .content-scroll {
      flex: 1;
      overflow-y: auto;
      padding: 40px 20px;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    .form-card {
      background-color: var(--form-bg);
      padding: 30px;
      border-radius: 10px;
      width: 100%;
      max-width: 600px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .form-card h1 {
      text-align: center;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      font-weight: 600;
      margin-bottom: 5px;
      display: block;
    }
    .form-group input,
    .form-group textarea,
    .form-group select {
      width: 100%;
      padding: 10px;
      font-size: 14px;
      border-radius: 6px;
      border: 1px solid #ccc;
      background-color: <?= $isDark ? '#3a3f4b' : '#fff' ?>;
      color: var(--text-color);
    }
    .form-group textarea {
      height: 100px;
    }

    .btn-submit {
      background-color: var(--btn-bg);
      color: white;
      border: none;
      padding: 12px;
      width: 100%;
      font-weight: bold;
      border-radius: 6px;
      cursor: pointer;
    }
    .btn-submit:hover {
      background-color: var(--btn-hover);
    }

    .message {
      text-align: center;
      font-weight: bold;
      margin-bottom: 15px;
      padding: 10px;
      border-radius: 6px;
    }
    .success {
      background-color: #dff0d8;
      color: #3c763d;
    }
    .error {
      background-color: #f2dede;
      color: #a94442;
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
    }
  </style>
</head>
<body>
  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="brand">CampusConnect</div>
    <ul class="nav-links">
      <li><a href="admin_dashboard.php">Dashboard</a></li>
      <li><a href="profile.php">Profile</a></li>
      <li><a href="settings.php">Settings</a></li>
      <li><a href="my_joined_events.php">Joined Events</a></li>
      <li><a href="create_event.php">Create New Event</a></li>
      <li><a href="my_events.php">Created Events</a></li>
      <li><a href="student_rsos.php">RSOs</a></li>
      <li><a href="create_rso_event.php">Create RSO Event</a></li>
      <li><a href="event_list.php">All Events</a></li>
      <li><a href="external_events.php">UCF Feed</a></li>
      <li><a href="logout.php">Logout</a></li>
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

    <div class="content-scroll">
      <div class="form-card">
        <h1>Create an RSO Event</h1>

        <?php if ($successMsg): ?>
          <div class="message success"><?= htmlspecialchars($successMsg) ?></div>
<?php elseif ($errorMsg): ?>
  <div class="message error"><?= $errorMsg ?></div>
<?php endif; ?>


        <form action="" method="POST" enctype="multipart/form-data">
          <!-- RSO Selection (only approved & created by this admin) -->
          <div class="form-group">
            <label>Select Approved RSO You Created</label>
            <select name="rso_id" required>
              <option value="">-- Select RSO --</option>
              <?php while ($rso = $rsos->fetch_assoc()): ?>
                <option value="<?= $rso['id'] ?>"><?= htmlspecialchars($rso['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Event Name</label>
            <input type="text" name="name" required />
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" required></textarea>
          </div>

          <div class="form-group">
            <label>Event Date</label>
            <input type="date" name="event_date" required />
          </div>

          <div class="form-group">
            <label>Start Time</label>
            <input type="time" name="start_time" required />
          </div>

          <div class="form-group">
            <label>End Time</label>
            <input type="time" name="end_time" required />
          </div>

          <div class="form-group">
            <label>Contact Email</label>
            <input type="email" name="contact_email" />
          </div>

          <div class="form-group">
            <label>Contact Phone</label>
            <input type="text" name="contact_phone" />
          </div>

          <div class="form-group">
            <label>Location</label>
            <select name="location_id" required>
              <option value="">Select a location</option>
              <?php while ($loc = $locations->fetch_assoc()): ?>
                <option value="<?= $loc['loc_id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Event Image (optional)</label>
            <input type="file" name="image" accept="image/*" />
          </div>

          <button type="submit" name="create_event" class="btn-submit">Create RSO Event</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
