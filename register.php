<?php
session_start();

$conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$universities = $conn->query("SELECT * FROM universities");
$emailUsedError = "";
$domainError = "";

if (isset($_POST['register'])) {
    $name          = $_POST['fullname'];
    $email         = $_POST['email'];
    $password      = $_POST['password'];
    $confirm       = $_POST['confirm_password'];
    $university_id = $_POST['university_id'];
    $avatarPath    = null;

    // Email already used?
    $emailCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $emailCheck->bind_param("s", $email);
    $emailCheck->execute();
    $emailResult = $emailCheck->get_result();

    if ($emailResult->num_rows > 0) {
        $emailUsedError = "This email is already used.";
    } elseif ($password !== $confirm) {
        echo "<script>alert('❌ Passwords do not match!');</script>";
    } else {
        // Check email domain matches university
        $emailDomain = substr(strrchr($email, "@"), 1);
        $domainStmt = $conn->prepare("SELECT email_domain FROM universities WHERE id = ?");
        $domainStmt->bind_param("i", $university_id);
        $domainStmt->execute();
        $domainResult = $domainStmt->get_result();

        if ($domainResult->num_rows === 1) {
            $uni = $domainResult->fetch_assoc();
            $expectedDomain = $uni['email_domain'];

            if ($emailDomain !== $expectedDomain) {
                $domainError = "Email must match university domain: @$expectedDomain";
            } else {
                // Upload avatar if present
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $avatarTmp  = $_FILES['avatar']['tmp_name'];
                    $avatarName = time() . "_" . basename($_FILES['avatar']['name']);
                    $uploadDir  = "uploads/avatars/";
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $uploadPath = $uploadDir . $avatarName;
                    if (move_uploaded_file($avatarTmp, $uploadPath)) {
                        $avatarPath = $uploadPath;
                    }
                }

                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, university_id, avatar_filename) VALUES (?, ?, ?, 'student', ?, ?)");
                $stmt->bind_param("sssis", $name, $email, $password, $university_id, $avatarPath);

                if ($stmt->execute()) {
                    echo "<script>alert('✅ Registration successful!'); window.location.href='login.php';</script>";
                } else {
                    echo "<script>alert('❌ Error: " . $stmt->error . "');</script>";
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CampusConnect - Register</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: "Segoe UI", Arial, sans-serif;
    background-color: #f4f5f9;
    color: #333;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
  }
  .form-container {
    background: #fff;
    width: 500px;
    padding: 40px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }
  .header {
    text-align: center;
    margin-bottom: 25px;
  }
  .header h2 {
    font-size: 24px;
    color: #1e3d7b;
  }
  form {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .input-field { display: flex; flex-direction: column; gap: 4px; position: relative; }
  .input-field label { font-size: 14px; color: #666; }
  .input-field input, .input-field select {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
    width: 100%;
  }
  .eye-icon {
    position: absolute;
    right: 12px;
    top: 35px;
    cursor: pointer;
  }
  .error-message {
    font-size: 12px;
    color: red;
    margin-top: 3px;
  }
  .btn-register {
    padding: 12px;
    background-color: #1e3d7b;
    color: #fff;
    font-weight: bold;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.3s;
  }
  .btn-register:hover {
    background-color: #2a4d95;
  }
</style>
</head>
<body>
<div class="form-container">
  <div class="header">
    <h2>Create Your Student Account</h2>
  </div>
  <form method="POST" enctype="multipart/form-data">
    <div class="input-field">
      <label for="fullname">Full Name</label>
      <input type="text" name="fullname" id="fullname" required placeholder="e.g. John Doe" />
    </div>

    <div class="input-field">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" required placeholder="yourname@university.edu" />
      <?php if (!empty($emailUsedError)): ?>
        <div class="error-message"><?= $emailUsedError ?></div>
      <?php endif; ?>
      <?php if (!empty($domainError)): ?>
        <div class="error-message"><?= $domainError ?></div>
      <?php endif; ?>
    </div>

    <div class="input-field">
      <label for="password">Password</label>
      <input type="password" name="password" id="password" required placeholder="********" />
      <span class="eye-icon" onclick="togglePassword('password')">👁</span>
    </div>

    <div class="input-field">
      <label for="confirm_password">Confirm Password</label>
      <input type="password" name="confirm_password" id="confirm_password" required placeholder="********" />
      <span class="eye-icon" onclick="togglePassword('confirm_password')">👁</span>
    </div>

    <div class="input-field">
      <label for="university_id">Select Your University</label>
      <select name="university_id" id="university_id" required>
        <option value="">-- Choose University --</option>
        <?php while ($uni = $universities->fetch_assoc()): ?>
          <option value="<?= $uni['id'] ?>"><?= htmlspecialchars($uni['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>

    <div class="input-field">
      <label for="avatar">Upload Profile Picture (optional)</label>
      <input type="file" name="avatar" id="avatar" accept="image/*" />
    </div>

    <button type="submit" name="register" class="btn-register">Register</button>
  </form>
</div>
<script>
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  field.type = field.type === "password" ? "text" : "password";
}
</script>
</body>
</html>
