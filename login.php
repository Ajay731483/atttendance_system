<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $login_ok = false;
    if ($user) {
        // Preferred: password stored as hash
        if (password_verify($password, $user['password'])) {
            $login_ok = true;
        } else {
            // Backwards compatibility: some installs may have stored plain-text passwords.
            // Allow plain-text match once and immediately re-hash the password in DB.
            if (hash_equals($user['password'], $password)) {
                $login_ok = true;
                // Re-hash and update stored password
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $up->execute([$newHash, $user['id']]);
            }
        }
    }

    if ($login_ok) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        session_regenerate_id(true);
        if ($user['role'] == 'admin') header('Location: admin.php');
        elseif ($user['role'] == 'teacher') header('Location: teacher.php');
        elseif ($user['role'] == 'student') header('Location: student.php');
        exit;
    } else {
        $error = "Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <form method="POST">
        <h2>Login</h2>
        <?php if (isset($error)) echo "<p>$error</p>"; ?>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
</body>
</html>