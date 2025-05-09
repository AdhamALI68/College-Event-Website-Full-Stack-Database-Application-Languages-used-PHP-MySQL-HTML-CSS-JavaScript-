<?php
session_start();

if (isset($_POST['login'])) {
    $conn = new mysqli("localhost", "root", "root", "AdMK_Database", 8889);
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }

    $email    = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($password === $user['password']) {
            $_SESSION['user']  = $user;
            $_SESSION['theme'] = $user['theme'] ?? 'light';

            switch ($user['role']) {
                case 'student':     header("Location: student_dashboard.php"); break;
                case 'admin':       header("Location: admin_dashboard.php"); break;
                case 'superadmin':  header("Location: superadmin_dashboard.php"); break;
            }
            exit();
        } else {
            echo "<script>alert('❌ Incorrect password.');</script>";
        }
    } else {
        echo "<script>alert('❌ No user found with that email.');</script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>CampusConnect - Login</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background-color: #f4f5f9;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .login-container {
      background: #fff;
      width: 450px;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .header {
      text-align: center;
      margin-bottom: 25px;
    }
    .header h2 {
      font-size: 24px;
      color: #1e3d7b;
      font-weight: 700;
    }
    form {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .input-field {
      position: relative;
      display: flex;
      flex-direction: column;
    }
    .input-field label {
      font-size: 14px;
      color: #333;
      margin-bottom: 6px;
    }
    .input-field input {
      width: 100%;
      padding: 12px;
      padding-right: 40px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 14px;
    }
    .eye-icon {
      position: absolute;
      right: 12px;
      top: 36px;
      cursor: pointer;
      font-size: 16px;
    }
    .btn-login {
      padding: 12px;
      background-color: #1e3d7b;
      color: #fff;
      font-weight: bold;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      cursor: pointer;
      transition: background 0.3s;
    }
    .btn-login:hover {
      background-color: #2a4d95;
    }
    .register-link {
      margin-top: 16px;
      text-align: center;
      font-size: 14px;
      color: #333;
    }
    .register-link a {
      color: #1e3d7b;
      font-weight: 500;
      text-decoration: none;
    }
  </style>
</head>
<body>
<div class="login-container">
  <div class="header">
    <h2>Login to CampusConnect</h2>
  </div>
  <form method="POST">
    <div class="input-field">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" required placeholder="yourname@university.edu" />
    </div>
    <div class="input-field">
      <label for="password">Password</label>
      <input type="password" name="password" id="password" required placeholder="••••••••••" />
      <span class="eye-icon" onclick="togglePassword()">👁</span>
    </div>
    <button type="submit" name="login" class="btn-login">Login</button>
  </form>
  <div class="register-link">
    Don’t have an account? <a href="register.php">Register here</a>
  </div>
</div>

<script>
function togglePassword() {
  const pwd = document.getElementById("password");
  pwd.type = pwd.type === "password" ? "text" : "password";
}
</script>
</body>
</html>
