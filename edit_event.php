<?php
session_start();

// Only admins can edit events (adjust logic if you want creators or superadmins, etc.)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Basic user info for the topbar and theming
$user       = $_SESSION['user'];
$userId     = $user['id'];
$userName   = $user['name']  ?? 'Admin';
$userEmail  = $user['email'] ?? 'admin@university.edu';
$userAvatar = $user['avatar_filename'] ?? null;
$theme      = $_SESSION['theme'] ?? 'light';
$isDark     = ($theme === 'dark');

// Check the event ID in the query params
if (!isset($_GET['id'])) {
    echo "No event selected.";
    exit();
}
$event_id = intval($_GET['id']);

// Fetch the event data
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
if (!$event) {
    echo "Event not found.";
    exit();
}

// If you want to ensure only the creator or certain roles can edit, do checks here.
// e.g. if ($event['created_by'] !== $userId) { ... }

// Fetch all locations to populate the drop-down
$university_id = $user['university_id'];  // get the logged-in user's university_id
$stmtLoc = $conn->prepare("SELECT loc_id, name FROM locations WHERE university_id = ?");
$stmtLoc->bind_param("i", $university_id);
$stmtLoc->execute();
$locations = $stmtLoc->get_result();


// Prepare messages
$successMsg = '';
$errorMsg   = '';

// If the user submitted the form
if (isset($_POST['update_event'])) {
    $name          = $_POST['name']          ?? '';
    $description   = $_POST['description']   ?? '';
    $type          = $_POST['type']          ?? 'public';
    $event_date    = $_POST['event_date']    ?? '';
    $start_time    = $_POST['start_time']    ?? '';
    $end_time      = $_POST['end_time']      ?? '';
    $contact_email = $_POST['contact_email'] ?? '';
    $contact_phone = $_POST['contact_phone'] ?? '';
    $location_id   = $_POST['location_id']   ?? '';

    // Keep old image if no new image is uploaded
    $image_filename = $event['image_filename'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name   = $_FILES['image']['tmp_name'];
        $orig_name  = basename($_FILES['image']['name']);
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

    // Update the event in the database
    $update_sql = "
        UPDATE events
        SET 
            name = ?,
            description = ?,
            type = ?,
            event_date = ?,
            start_time = ?,
            end_time = ?,
            contact_email = ?,
            contact_phone = ?,
            image_filename = ?,
            location_id = ?
        WHERE id = ?
    ";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param(
        "ssssssssssi",
        $name,
        $description,
        $type,
        $event_date,
        $start_time,
        $end_time,
        $contact_email,
        $contact_phone,
        $image_filename,
        $location_id,
        $event_id
    );

    // Execute with try/catch to handle overlapping event from the trigger
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
  <title>CampusConnect - Edit Event</title>
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

    /* SIDEBAR */
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

    /* MAIN CONTENT */
    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
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
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px;
      font-size: 14px;
      border-radius: 6px;
      border: 1px solid #ccc;
      background-color: <?= $isDark ? '#3a3f4b' : '#fff' ?>;
      color: var(--text-color);
    }
    .form-group textarea {
      height: 80px;
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
      <li><a href="event_list.php">All Events</a></li>
      <li><a href="external_events.php">UCF Feed</a></li>
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
        <h1>Edit Event</h1>

        <?php if ($successMsg): ?>
          <div class="message success"><?= htmlspecialchars($successMsg) ?></div>
<?php elseif ($errorMsg): ?>
  <div class="message error"><?= $errorMsg ?></div>
<?php endif; ?>


        <form method="POST" enctype="multipart/form-data">
          <div class="form-group">
            <label>Event Name</label>
            <input type="text" name="name"
                   value="<?= htmlspecialchars($event['name']) ?>"
                   required />
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" required><?= htmlspecialchars($event['description']) ?></textarea>
          </div>

          <div class="form-group">
            <label>Event Type</label>
            <select name="type" required>
              <option value="public"  <?= ($event['type'] === 'public')  ? 'selected' : '' ?>>Public</option>
              <option value="private" <?= ($event['type'] === 'private') ? 'selected' : '' ?>>Private</option>
              <option value="rso"     <?= ($event['type'] === 'rso')     ? 'selected' : '' ?>>RSO</option>
            </select>
          </div>

          <div class="form-group">
            <label>Event Date</label>
            <input type="date" name="event_date"
                   value="<?= htmlspecialchars($event['event_date']) ?>"
                   required />
          </div>

          <div class="form-group">
            <label>Start Time</label>
            <input type="time" name="start_time"
                   value="<?= htmlspecialchars($event['start_time']) ?>"
                   required />
          </div>

          <div class="form-group">
            <label>End Time</label>
            <input type="time" name="end_time"
                   value="<?= htmlspecialchars($event['end_time']) ?>"
                   required />
          </div>

          <div class="form-group">
            <label>Contact Email</label>
            <input type="email" name="contact_email"
                   value="<?= htmlspecialchars($event['contact_email']) ?>" />
          </div>

          <div class="form-group">
            <label>Contact Phone</label>
            <input type="text" name="contact_phone"
                   value="<?= htmlspecialchars($event['contact_phone']) ?>" />
          </div>

          <div class="form-group">
            <label>Location</label>
            <select name="location_id" required>
              <option value="">Select a location</option>
              <?php
                // Reset pointer if needed or re-query
                $locations->data_seek(0);
                while ($loc = $locations->fetch_assoc()):
              ?>
                <option value="<?= $loc['loc_id'] ?>"
                  <?= ($loc['loc_id'] == $event['location_id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($loc['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Event Image (optional)</label>
            <?php if (!empty($event['image_filename'])): ?>
              <p style="margin-bottom:6px;">
                Current image:
                <br/>
                <img src="<?= htmlspecialchars($event['image_filename']) ?>"
                     alt="Event Image"
                     style="max-width:200px; border-radius:6px; margin-top:8px;">
              </p>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*" />
          </div>

          <button type="submit" name="update_event" class="btn-submit">
            Update Event
          </button>
        </form>
      </div>
    </div>
  </div> <!-- .main-content -->
</body>
</html>
