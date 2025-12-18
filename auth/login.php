<?php
session_start();
require_once '../config.php';

$error = '';

if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Pakilagay ang email at password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header('Location: ../dashboard.php');
            exit;
        } else {
            $error = 'Maling email o password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Menstrual Monitor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="logo-circle" style="margin-bottom:10px;">MM</div>
        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-subtitle">Mag-login upang i-track ang iyong menstrual cycle nang ligtas at madali.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" required>
            </div>
            <div class="form-group" style="margin-top:8px;">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" required>
            </div>
            <div style="margin-top:14px; display:flex; justify-content:space-between; align-items:center;">
                <button type="submit" class="btn-primary btn-sm">
                    Login
                </button>
            </div>
        </form>

        <div class="auth-switch">
            Wala ka pang account?
            <a href="register.php">Mag-register</a>
        </div>
    </div>
</div>
</body>
</html>


