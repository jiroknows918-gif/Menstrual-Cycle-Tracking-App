<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Kunin ang lahat ng period history para sa user
$stmt = $pdo->prepare('SELECT period_start, period_length, cycle_length, created_at FROM cycles WHERE user_id = ? ORDER BY period_start DESC');
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();

// I-prepare ang CSV
$filename = 'period_history_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, ['Start date', 'Days of period', 'Cycle length (days)', 'Recorded at']);

if ($rows) {
    foreach ($rows as $r) {
        fputcsv($output, [
            $r['period_start'],
            (int)$r['period_length'],
            (int)$r['cycle_length'],
            $r['created_at'],
        ]);
    }
}

fclose($output);
exit;


