<?php
// Redirect to login or dashboard depending on session
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: auth/login.php');
}
exit;


