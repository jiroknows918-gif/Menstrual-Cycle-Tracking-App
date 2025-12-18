<?php
session_start();
require_once '../config.php';

$error = '';
$success = '';

if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $age = (int)($_POST['age'] ?? 0);
    $cycle_length = (int)($_POST['cycle_length'] ?? 28);
    $period_length = (int)($_POST['period_length'] ?? 5);
    $last_period_start = $_POST['last_period_start'] ?? null;

    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'Pakikompleto ang lahat ng required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Hindi valid ang email address.';
    } elseif ($password !== $confirm) {
        $error = 'Hindi magkatugma ang password at confirmation.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'May existing account na gamit ang email na ito.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, age, cycle_length, period_length, last_period_start) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([
                $name,
                $email,
                $hash,
                $age ?: null,
                $cycle_length ?: 28,
                $period_length ?: 5,
                $last_period_start ?: null
            ]);

            // I-save agad sa period history ang unang period kung meron
            if ($last_period_start) {
                $userId = $pdo->lastInsertId();
                $stmtHistory = $pdo->prepare('INSERT INTO cycles (user_id, period_start, period_length, cycle_length) VALUES (?,?,?,?)');
                $stmtHistory->execute([
                    $userId,
                    $last_period_start,
                    $period_length ?: 5,
                    $cycle_length ?: 28
                ]);
            }

            $success = 'Matagumpay ang pag-register! Maaari ka nang mag-login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Menstrual Monitor</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="logo-circle" style="margin-bottom:10px;">MM</div>
        <h1 class="auth-title">Create account</h1>
        <p class="auth-subtitle">I-set up ang iyong profile para sa personalized menstrual tracking.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="name">Buong Pangalan</label>
                <input class="form-control" type="text" id="name" name="name" required>
            </div>
            <div class="form-group" style="margin-top:8px;">
                <label for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" required>
            </div>
            <div class="form-grid" style="margin-top:8px;">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            <div class="form-grid" style="margin-top:8px;">
                <div class="form-group">
                    <label for="age">Edad</label>
                    <input class="form-control" type="number" id="age" name="age" min="10" max="60">
                </div>
                <div class="form-group">
                    <label for="last_period_start">First day ng last period</label>
                    <input class="form-control" type="date" id="last_period_start" name="last_period_start">
                </div>
            </div>
            <div class="form-grid" style="margin-top:8px;">
                <div class="form-group">
                    <label for="cycle_length">Average cycle length (days)</label>
                    <input class="form-control" type="number" id="cycle_length" name="cycle_length" min="20" max="60" value="28">
                </div>
                <div class="form-group">
                    <label for="period_length">Number of days ng regla</label>
                    <input class="form-control" type="number" id="period_length" name="period_length" min="1" max="10" value="5">
                </div>
            </div>
            <div style="margin-top:14px; display:flex; justify-content:space-between; align-items:center;">
                <button type="submit" class="btn-primary btn-sm">
                    Register
                </button>
            </div>
        </form>

        <div class="auth-switch">
            May account na?
            <a href="login.php">Mag-login</a>
        </div>
    </div>
</div>
</body>
</html>


