<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Fetch user
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: logout.php');
    exit;
}

$today = new DateTime('today');
$lastPeriod = $user['last_period_start'] ? new DateTime($user['last_period_start']) : null;
$cycleLength = (int)$user['cycle_length'];
$periodLength = (int)$user['period_length'];

// Default section na ipapakita sa pag-load ng page
$initialSection = 'dashboard-section';
// Gamitin ang view parameter para piliin ang unang section
if (isset($_GET['view'])) {
    if ($_GET['view'] === 'calendar') {
        $initialSection = 'calendar-section';
    } elseif ($_GET['view'] === 'symptoms') {
        $initialSection = 'symptoms-section';
    } elseif ($_GET['view'] === 'history') {
        $initialSection = 'history-section';
    } elseif ($_GET['view'] === 'update-cycle') {
        $initialSection = 'update-cycle-section';
    } elseif ($_GET['view'] === 'period-tracer') {
        $initialSection = 'period-tracer-section';
    }
} elseif (isset($_GET['day'])) {
    // fallback: kung may piniling araw pero walang view, manatili sa calendar
    $initialSection = 'calendar-section';
}

// Handle cycle update form
$cycleMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cycle'])) {
    $newLastStart = $_POST['last_period_start'] ?? null;
    $newPeriodLength = (int)($_POST['period_length'] ?? $periodLength);
    $newCycleLength = (int)($_POST['cycle_length'] ?? $cycleLength);

    if ($newLastStart) {
        $stmt = $pdo->prepare('UPDATE users SET last_period_start = ?, period_length = ?, cycle_length = ? WHERE id = ?');
        $stmt->execute([$newLastStart, $newPeriodLength, $newCycleLength, $userId]);

        // Insert new cycle record
        $stmt = $pdo->prepare('INSERT INTO cycles (user_id, period_start, period_length, cycle_length) VALUES (?,?,?,?)');
        $stmt->execute([$userId, $newLastStart, $newPeriodLength, $newCycleLength]);

        $cycleMessage = 'Na-update ang cycle information.';
        // manatili sa update-cycle section pagkatapos mag-save
        $initialSection = 'update-cycle-section';
    } else {
        $cycleMessage = 'Pakilagay ang first day ng iyong period.';
    }
}

// Handle simple period tracer update (last period start only)
$periodTracerMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_period_tracer'])) {
    $tracerStart = $_POST['tracer_last_period_start'] ?? null;

    if ($tracerStart) {
        // Update last_period_start only
        $stmt = $pdo->prepare('UPDATE users SET last_period_start = ? WHERE id = ?');
        $stmt->execute([$tracerStart, $userId]);

        // Insert new cycle record using current period and cycle length
        $stmt = $pdo->prepare('INSERT INTO cycles (user_id, period_start, period_length, cycle_length) VALUES (?,?,?,?)');
        $stmt->execute([$userId, $tracerStart, $periodLength, $cycleLength]);

        // I-refresh ang page papunta sa calendar view para makita agad ang markadong araw
        header('Location: dashboard.php?view=calendar');
        exit;
    } else {
        $periodTracerMessage = 'Pakilagay kung kailan nagsimula ang huling regla mo.';
        $initialSection = 'period-tracer-section';
    }
}

// Handle symptom log
$logMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_log'])) {
    $logDate = $_POST['log_date'] ?? $today->format('Y-m-d');
    $mood = $_POST['mood'] ?? 'neutral';
    $notes = trim($_POST['notes'] ?? '');
    $symptoms = $_POST['symptoms'] ?? [];

    $symptomValues = array_intersect($symptoms, ['cramps','headache','breast_pain','acne','fatigue']);
    $symptomSet = $symptomValues ? implode(',', $symptomValues) : null;

    // Upsert daily log
    $stmt = $pdo->prepare('SELECT id FROM daily_logs WHERE user_id = ? AND log_date = ?');
    $stmt->execute([$userId, $logDate]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare('UPDATE daily_logs SET symptoms = ?, mood = ?, notes = ? WHERE id = ?');
        $stmt->execute([$symptomSet, $mood, $notes, $existing['id']]);
        $logMessage = 'Na-update ang iyong log para sa araw na ito.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO daily_logs (user_id, log_date, symptoms, mood, notes) VALUES (?,?,?,?,?)');
        $stmt->execute([$userId, $logDate, $symptomSet, $mood, $notes]);
        $logMessage = 'Naitala ang iyong sintomas at mood.';
    }

     // manatili sa calendar section pagkatapos mag-save ng log
     $initialSection = 'calendar-section';
}

// Re-fetch logs and cycles
$stmt = $pdo->prepare('SELECT * FROM cycles WHERE user_id = ? ORDER BY period_start DESC LIMIT 6');
$stmt->execute([$userId]);
$cycles = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM daily_logs WHERE user_id = ? ORDER BY log_date DESC LIMIT 10');
$stmt->execute([$userId]);
$logs = $stmt->fetchAll();

// Compute predictions
$nextPeriodDate = null;
$ovulationDate = null;
$fertileStart = null;
$fertileEnd = null;
$delayWarning = null;
$cycleStatus = 'Walang sapat na data';
$periodEndDate = null;
$inPeriodNow = false;
$daysUntilPeriodEnd = null;
$daysUntilNextPeriod = null;

if ($lastPeriod) {
    $nextPeriodDate = (clone $lastPeriod)->modify('+' . $cycleLength . ' days');
    $ovulationDate = (clone $lastPeriod)->modify('+' . ($cycleLength - 14) . ' days');
    $fertileStart = (clone $ovulationDate)->modify('-4 days');
    $fertileEnd = (clone $ovulationDate)->modify('+1 day');

    // Period end date = start + (periodLength - 1)
    if ($periodLength > 0) {
        $periodEndDate = (clone $lastPeriod)->modify('+' . ($periodLength - 1) . ' days');
    }

    // Compute current position and delays
    $diff = (int)$today->diff($lastPeriod)->format('%r%a');
    // Old delay warning - will be replaced by new delay status system below

    // Determine if currently on period
    if ($periodEndDate && $today >= $lastPeriod && $today <= $periodEndDate) {
        $inPeriodNow = true;
        $daysUntilPeriodEnd = (int)$today->diff($periodEndDate)->format('%r%a');
    }

    // Days until next period
    if ($nextPeriodDate) {
        $daysUntilNextPeriod = (int)$today->diff($nextPeriodDate)->format('%r%a');
    }

    // Calculate days after period ends before next period starts
    $daysAfterPeriodBeforeNext = null;
    if ($periodEndDate && $nextPeriodDate) {
        $daysAfterPeriodBeforeNext = (int)$periodEndDate->diff($nextPeriodDate)->format('%a');
        // Add 1 day because we want the count from day after period ends to day before next period starts
        // Actually, we want: days from periodEndDate+1 to nextPeriodDate-1
        // So: (nextPeriodDate - periodEndDate) - 1
        $tempEnd = (clone $periodEndDate)->modify('+1 day');
        $daysAfterPeriodBeforeNext = (int)$tempEnd->diff($nextPeriodDate)->format('%a');
    }

    // Enhanced delay calculation with Normal/Delayed status
    $delayStatus = 'Normal';
    $delayDays = 0;
    $delayMessage = '';
    if ($nextPeriodDate) {
        $daysPastExpected = (int)$today->diff($nextPeriodDate)->format('%r%a');
        if ($daysPastExpected < 0) {
            // Period is late
            $delayDays = abs($daysPastExpected);
            if ($delayDays <= 7) {
                $delayStatus = 'Normal';
                $delayMessage = 'Normal na delay: ' . $delayDays . ' araw. Ang normal na delay ay 0-7 araw.';
            } else {
                $delayStatus = 'Delayed';
                $delayMessage = '⚠️ Delayed Period: ' . $delayDays . ' araw na ang delay. Maaaring magandang kumonsulta sa health professional o mag-pregnancy test kung kinakailangan.';
            }
        } else {
            // Period not yet due
            $delayStatus = 'Normal';
            $delayMessage = 'Normal: ' . $daysPastExpected . ' araw pa bago ang inaasahang regla.';
        }
    }

    // Determine cycle status
    $currentDayInCycle = ($diff >= 0) ? ($diff % $cycleLength) : null;
    if ($currentDayInCycle !== null) {
        if ($currentDayInCycle < $periodLength) {
            $cycleStatus = 'On period';
        } elseif ($today >= $fertileStart && $today <= $fertileEnd) {
            $cycleStatus = 'Fertile window';
        } elseif ($today->format('Y-m-d') === $ovulationDate->format('Y-m-d')) {
            $cycleStatus = 'Ovulation day';
        } elseif ($today < $nextPeriodDate) {
            $cycleStatus = 'Luteal phase (post-ovulation)';
        } else {
            $cycleStatus = 'Pre-period / waiting';
        }
    }
}

// Average cycle calculations
$avgCycle = null;
$irregular = false;
if ($cycles) {
    $sum = 0;
    foreach ($cycles as $c) {
        $sum += (int)$c['cycle_length'];
    }
    $avgCycle = round($sum / count($cycles));
    foreach ($cycles as $c) {
        if (abs((int)$c['cycle_length'] - $avgCycle) > 5) {
            $irregular = true;
            break;
        }
    }
}

// Build calendar for current month
$month = (int)($_GET['month'] ?? $today->format('n'));
$year = (int)($_GET['year'] ?? $today->format('Y'));

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = (int)$today->format('n');
}
if ($year < 2020 || $year > 2100) {
    $year = (int)$today->format('Y');
}

$firstOfMonth = new DateTime("$year-$month-01");
$startWeekday = (int)$firstOfMonth->format('N'); // 1 (Mon) - 7 (Sun)
$daysInMonth = (int)$firstOfMonth->format('t');
$selectedCalendarDay = isset($_GET['day']) ? (int)$_GET['day'] : null;

// Calculate previous and next month
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

function isBetween(DateTime $date, ?DateTime $start, ?DateTime $end): bool {
    if (!$start || !$end) return false;
    return $date >= $start && $date <= $end;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Menstrual Monitor</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-circle">MM</div>
            <div>
                <div class="sidebar-title">Menstrual Monitor</div>
                <div class="sidebar-subtitle">Gentle cycle companion</div>
            </div>
        </div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="#" class="<?php echo $initialSection === 'dashboard-section' ? 'active' : ''; ?>" data-section="dashboard-section">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="<?php echo $initialSection === 'period-tracer-section' ? 'active' : ''; ?>" data-section="period-tracer-section">
                    <span class="nav-icon">🩸</span>
                    <span>Period tracer</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="<?php echo $initialSection === 'calendar-section' ? 'active' : ''; ?>" data-section="calendar-section">
                    <span class="nav-icon">📅</span>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="<?php echo $initialSection === 'symptoms-section' ? 'active' : ''; ?>" data-section="symptoms-section">
                    <span class="nav-icon">❤️</span>
                    <span>Symptoms</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="<?php echo $initialSection === 'insights-section' ? 'active' : ''; ?>" data-section="insights-section">
                    <span class="nav-icon">💡</span>
                    <span>Insights</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="<?php echo $initialSection === 'history-section' ? 'active' : ''; ?>" data-section="history-section">
                    <span class="nav-icon">📜</span>
                    <span>History</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="<?php echo $initialSection === 'update-cycle-section' ? 'active' : ''; ?>" data-section="update-cycle-section">
                    <span class="nav-icon">🔁</span>
                    <span>Update cycle</span>
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <div>Data privacy: Encrypted passwords, personal logs stored securely.</div>
        </div>
    </aside>
    <main class="main">
        <div class="floating-hearts">
            <span class="heart">💗</span>
            <span class="heart">💕</span>
            <span class="heart">💖</span>
            <span class="heart">💗</span>
            <span class="heart">❤️</span>
            <span class="heart">💕</span>
            <span class="heart">💖</span>
            <span class="heart">💗</span>
            <span class="heart">💕</span>
            <span class="heart">❤️</span>
        </div>
        <div class="floating-flowers">
            <span class="flower">🌸</span>
            <span class="flower">🌺</span>
            <span class="flower">🌷</span>
            <span class="flower">🌼</span>
            <span class="flower">🌸</span>
            <span class="flower">🌺</span>
            <span class="flower">🌷</span>
            <span class="flower">🌼</span>
        </div>
        <div class="floating-pads">
            <span class="pad">🩸</span>
            <span class="pad">🩹</span>
            <span class="pad">🩸</span>
            <span class="pad">🩹</span>
            <span class="pad">🩸</span>
            <span class="pad">🩹</span>
        </div>
        <div class="top-bar">
            <div>
                <div class="top-title">Hi, <?php echo htmlspecialchars($user['name']); ?> 👋</div>
                <div class="badge-soft">
                    Gentle reminder: listen to your body today.
                </div>
            </div>
            <div class="user-chip">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div style="font-size:12px;">
                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                    <div style="font-size:11px; color:#999;"><?php echo $today->format('M d, Y'); ?></div>
                </div>
                <button class="btn-outline" onclick="window.location='logout.php'">Logout</button>
            </div>
        </div>

        <div class="grid main-section <?php echo $initialSection === 'dashboard-section' ? 'active' : ''; ?>" id="dashboard-section">
            <section class="card" id="overview-section">
                <div class="card-header">
                    <div class="card-title">Cycle overview</div>
                    <span class="chip">Cycle status: <?php echo htmlspecialchars($cycleStatus); ?></span>
                </div>
                <div class="stats-row">
                    <div class="stat-pill pill-red">
                        <div class="stat-label">Next period</div>
                        <div class="stat-value">
                            <?php echo $nextPeriodDate ? $nextPeriodDate->format('M d, Y') : 'Need last period data'; ?>
                        </div>
                        <div class="pill-subtext">
                            <?php
                            if ($nextPeriodDate) {
                                $daysLeft = (int)$today->diff($nextPeriodDate)->format('%r%a');
                                if ($daysLeft > 0) {
                                    echo $daysLeft . ' day(s) to go';
                                } elseif ($daysLeft === 0) {
                                    echo 'Expected today';
                                } else {
                                    echo 'Expected ' . abs($daysLeft) . ' day(s) ago';
                                }
                            } else {
                                echo 'Update your last period date.';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="stat-pill pill-green">
                        <div class="stat-label">Fertile window</div>
                        <div class="stat-value">
                            <?php
                            if ($fertileStart && $fertileEnd) {
                                echo $fertileStart->format('M d') . ' - ' . $fertileEnd->format('M d');
                            } else {
                                echo 'Need last period data';
                            }
                            ?>
                        </div>
                        <div class="pill-subtext">
                            <?php
                            if ($fertileStart && $fertileEnd) {
                                if (isBetween($today, $fertileStart, $fertileEnd)) {
                                    echo 'You are currently in your fertile window.';
                                } else {
                                    echo 'We will remind you as you approach.';
                                }
                            } else {
                                echo 'Fertility prediction based on average cycle.';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="stat-pill pill-yellow">
                        <div class="stat-label">Ovulation day</div>
                        <div class="stat-value">
                            <?php echo $ovulationDate ? $ovulationDate->format('M d, Y') : 'Need last period data'; ?>
                        </div>
                        <div class="pill-subtext">
                            Ovulation is estimated and may vary per cycle.
                        </div>
                    </div>
                </div>
                <?php if ($delayStatus === 'Delayed'): ?>
                    <div style="margin-top:10px;" class="alert alert-error">
                        <strong>⚠️ Delayed Period:</strong> <?php echo htmlspecialchars($delayMessage); ?>
                    </div>
                <?php elseif ($nextPeriodDate && $daysUntilNextPeriod !== null && $daysUntilNextPeriod <= 3 && $daysUntilNextPeriod > 0): ?>
                    <div style="margin-top:10px;" class="alert" style="background:#fff3cd; color:#856404; border:1px solid #ffeaa7;">
                        <strong>📅 Paalala:</strong> Malapit na ang inaasahang regla mo sa <?php echo $nextPeriodDate->format('M d, Y'); ?> (<?php echo $daysUntilNextPeriod; ?> araw na lang).
                    </div>
                <?php elseif ($nextPeriodDate && $daysUntilNextPeriod === 0): ?>
                    <div style="margin-top:10px;" class="alert" style="background:#d1ecf1; color:#0c5460; border:1px solid #bee5eb;">
                        <strong>📅 Paalala:</strong> Inaasahang regla mo ngayon (<?php echo $nextPeriodDate->format('M d, Y'); ?>).
                    </div>
                <?php endif; ?>
                
                <div class="cycle-phases-legend-horizontal" style="margin-top: 16px;">
                    <div class="phase-card menstruation-card">
                        <div class="phase-icon">🩸</div>
                        <div class="phase-info">
                            <strong>Menstruation</strong>
                            <span class="phase-days">Days 1-5</span>
                            <p>Pagdudugo - nilalabas ang uterine lining. Normal na may cramps at fatigue.</p>
                        </div>
                    </div>
                    <div class="phase-card follicular-card">
                        <div class="phase-icon">🥚</div>
                        <div class="phase-info">
                            <strong>Follicular Phase</strong>
                            <span class="phase-days">Days 6-12</span>
                            <p>Growing follicle - bumubuo ng bagong uterine lining, tumataas ang energy.</p>
                        </div>
                    </div>
                    <div class="phase-card ovulation-card">
                        <div class="phase-icon">✨</div>
                        <div class="phase-info">
                            <strong>Ovulation</strong>
                            <span class="phase-days">Days 13-16</span>
                            <p>Egg release - pinaka-fertile! Mataas ang energy at libido.</p>
                        </div>
                    </div>
                    <div class="phase-card luteal-card">
                        <div class="phase-icon">🌙</div>
                        <div class="phase-info">
                            <strong>Luteal Phase</strong>
                            <span class="phase-days">Days 17-30</span>
                            <p>Corpus luteum forms - PMS symptoms, paghahanda para sa next cycle.</p>
                        </div>
                    </div>
                </div>
            </section>
            <section class="card" id="cycle-wheel-card">
                <div class="card-header">
                    <div class="card-title">Menstrual Cycle (30-Day Chart)</div>
                    <span class="chip">Visual Guide</span>
                </div>
                <div class="detailed-cycle-container">
                    <svg class="detailed-cycle-wheel" viewBox="0 0 500 500" aria-hidden="true">
                        <!-- Outer ring background -->
                        <circle cx="250" cy="250" r="230" fill="#f5f5f5" stroke="#ddd" stroke-width="2"/>
                        
                        <!-- Day number boxes around the wheel -->
                        <?php
                        for ($d = 1; $d <= 30; $d++) {
                            $angle = deg2rad(($d / 30) * 360 - 90);
                            $boxR = 215;
                            $x = 250 + cos($angle) * $boxR;
                            $y = 250 + sin($angle) * $boxR;
                            
                            // Determine color based on phase
                            if ($d >= 1 && $d <= 5) {
                                $boxColor = '#ffcdd2'; // Menstruation - light red
                                $borderColor = '#ef5350';
                            } elseif ($d >= 6 && $d <= 12) {
                                $boxColor = '#bbdefb'; // Follicular - light blue
                                $borderColor = '#64b5f6';
                            } elseif ($d >= 13 && $d <= 16) {
                                $boxColor = '#c8e6c9'; // Ovulation - light green
                                $borderColor = '#66bb6a';
                            } else {
                                $boxColor = '#fff9c4'; // Luteal - light yellow
                                $borderColor = '#ffee58';
                            }
                            
                            echo '<g transform="translate(' . $x . ',' . $y . ') rotate(' . (($d / 30) * 360) . ')">';
                            echo '<rect x="-14" y="-14" width="28" height="28" rx="4" fill="' . $boxColor . '" stroke="' . $borderColor . '" stroke-width="2"/>';
                            echo '<text x="0" y="5" text-anchor="middle" style="font-size:12px; fill:#333; font-weight:bold;" transform="rotate(' . (-(($d / 30) * 360)) . ')">' . $d . '</text>';
                            echo '</g>';
                        }
                        ?>
                        
                        <!-- Inner colored sections -->
                        <!-- Menstruation: Days 1-5 (Pink/Red) -->
                        <path d="M250 60 A190 190 0 0 1 424 155 L370 190 A130 130 0 0 0 250 120 Z" fill="#f8bbd9"/>
                        
                        <!-- Follicular: Days 6-12 (Blue) -->
                        <path d="M424 155 A190 190 0 0 1 380 395 L340 345 A130 130 0 0 0 370 190 Z" fill="#90caf9"/>
                        
                        <!-- Ovulation: Days 13-16 (Green) -->
                        <path d="M380 395 A190 190 0 0 1 120 395 L160 345 A130 130 0 0 0 340 345 Z" fill="#a5d6a7"/>
                        
                        <!-- Luteal: Days 17-30 (Yellow/Cream) -->
                        <path d="M120 395 A190 190 0 0 1 250 60 L250 120 A130 130 0 0 0 160 345 Z" fill="#fff59d"/>
                        
                        <!-- Center white circle -->
                        <circle cx="250" cy="250" r="90" fill="#fff" stroke="#eee" stroke-width="2"/>
                        
                        <!-- Uterus icon in center area -->
                        <path d="M250 200 L250 230 M230 215 Q210 235 220 255 Q230 275 250 265 Q270 275 280 255 Q290 235 270 215" 
                              fill="none" stroke="#e91e63" stroke-width="4" stroke-linecap="round"/>
                    </svg>
                    
                    </div>
            </section>
        </div>

        <div class="grid main-section <?php echo $initialSection === 'period-tracer-section' ? 'active' : ''; ?>" id="period-tracer-section">
            <section class="card period-tracer-card">
                <div class="card-header">
                    <div class="card-title">Period tracer</div>
                    <span class="chip">Auto-counter</span>
                </div>
                <?php if ($periodTracerMessage): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($periodTracerMessage); ?></div>
                <?php endif; ?>
                <div class="period-tracer-layout">
                    <?php
                    // Prep data for the days-left visual counter
                    $countdownDays = null;
                    $countdownLabel = 'Set your last period date to start the counter.';
                    if ($lastPeriod && $periodEndDate) {
                        if ($inPeriodNow && $daysUntilPeriodEnd !== null) {
                            $countdownDays = max($daysUntilPeriodEnd, 0);
                            $countdownLabel = 'Bilang hanggang matapos ang current period';
                        } elseif ($nextPeriodDate && $daysUntilNextPeriod !== null) {
                            $countdownDays = max($daysUntilNextPeriod, 0);
                            $countdownLabel = 'Bilang hanggang sa next period';
                        }
                    }
                    ?>
                    <div class="period-tracer-summary">
                        <div class="countdown-card">
                            <div class="countdown-header">DAYS LEFT</div>
                            <div class="countdown-number">
                                <?php echo $countdownDays !== null ? $countdownDays : '—'; ?>
                            </div>
                            <div class="countdown-subtext">
                                <?php
                                if ($countdownDays === null) {
                                    echo $countdownLabel;
                                } else {
                                    if ($inPeriodNow && $periodEndDate) {
                                        echo 'Expected end: ' . $periodEndDate->format('M d, Y');
                                    } elseif ($nextPeriodDate) {
                                        echo 'Expected next start: ' . $nextPeriodDate->format('M d, Y');
                                    } else {
                                        echo $countdownLabel;
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <div class="countdown-side">
                            <?php if ($countdownDays !== null): ?>
                                <?php
                                $start = max($countdownDays - 9, 0);
                                for ($d = $countdownDays; $d >= $start; $d--):
                                ?>
                                    <div class="mini-day-card">
                                        <div class="mini-day-number"><?php echo $d; ?></div>
                                        <div class="mini-day-label">day<?php echo $d === 1 ? '' : 's'; ?> left</div>
                                    </div>
                                <?php endfor; ?>
                            <?php else: ?>
                                <div class="mini-day-empty">
                                    Walang last period data.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="tracer-pill">
                            <div class="tracer-label">🩸 Petsa ng Huling Regla</div>
                            <div class="tracer-value">
                                <?php echo $lastPeriod ? $lastPeriod->format('M d, Y') : 'Hindi pa naka-set'; ?>
                            </div>
                            <?php if ($lastPeriod && $periodEndDate): ?>
                                <div class="tracer-subtext" style="font-size:11px; margin-top:4px;">
                                    Hanggang: <?php echo $periodEndDate->format('M d, Y'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="tracer-pill">
                            <div class="tracer-label">📅 Bilang ng Araw ng Regla</div>
                            <div class="tracer-value">
                                <?php 
                                if ($periodLength) {
                                    echo $periodLength . ' araw';
                                    if ($periodLength >= 3 && $periodLength <= 7) {
                                        echo ' <span style="color:#4caf50; font-size:11px;">✓ Normal (3-7 araw)</span>';
                                    } else {
                                        echo ' <span style="color:#ff9800; font-size:11px;">⚠ Suriin</span>';
                                    }
                                } else {
                                    echo 'Hindi pa naka-set';
                                }
                                ?>
                            </div>
                            <div class="tracer-subtext" style="font-size:11px; margin-top:4px;">
                                <?php 
                                if ($lastPeriod && $periodEndDate) {
                                    echo 'Mula ' . $lastPeriod->format('M d') . ' hanggang ' . $periodEndDate->format('M d, Y');
                                } else {
                                    echo 'Karaniwang tumatagal ng 3-7 araw ang regla.';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="tracer-pill">
                            <div class="tracer-label">🔄 Bilang ng Araw Bago Muling Reglahin</div>
                            <div class="tracer-value">
                                <?php 
                                if ($cycleLength) {
                                    echo $cycleLength . ' araw';
                                    if ($cycleLength >= 21 && $cycleLength <= 35) {
                                        echo ' <span style="color:#4caf50; font-size:11px;">✓ Normal (21-35 araw)</span>';
                                    } elseif ($cycleLength == 28) {
                                        echo ' <span style="color:#4caf50; font-size:11px;">✓ Karaniwan (28 araw)</span>';
                                    } else {
                                        echo ' <span style="color:#ff9800; font-size:11px;">⚠ Suriin</span>';
                                    }
                                } else {
                                    echo 'Hindi pa naka-set';
                                }
                                ?>
                            </div>
                            <div class="tracer-subtext" style="font-size:11px; margin-top:4px;">
                                <?php 
                                if ($lastPeriod && $nextPeriodDate) {
                                    echo 'Mula ' . $lastPeriod->format('M d, Y') . ' hanggang ' . $nextPeriodDate->format('M d, Y');
                                } else {
                                    echo 'Ang normal na cycle ay 21-35 araw, karaniwan ay 28 araw.';
                                }
                                ?>
                            </div>
                        </div>
                        <?php if ($daysAfterPeriodBeforeNext !== null): ?>
                        <div class="tracer-pill">
                            <div class="tracer-label">⏱️ Araw Pagkatapos ng Regla Bago ang Susunod</div>
                            <div class="tracer-value">
                                <?php echo $daysAfterPeriodBeforeNext; ?> araw
                            </div>
                            <div class="tracer-subtext" style="font-size:11px; margin-top:4px;">
                                Bilang ng araw mula sa pagtatapos ng regla hanggang sa susunod na inaasahang regla.
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($nextPeriodDate): ?>
                        <div class="tracer-pill <?php echo $delayStatus === 'Delayed' ? 'tracer-pill-delayed' : ''; ?>">
                            <div class="tracer-label">⏰ Estimated Delay ng Regla</div>
                            <div class="tracer-value">
                                <?php 
                                if ($delayStatus === 'Delayed') {
                                    echo '<span style="color:#f44336; font-weight:700;">⚠️ DELAYED</span>';
                                } else {
                                    echo '<span style="color:#4caf50; font-weight:700;">✓ NORMAL</span>';
                                }
                                ?>
                            </div>
                            <div class="tracer-subtext" style="font-size:11px; margin-top:4px; color:<?php echo $delayStatus === 'Delayed' ? '#f44336' : '#555'; ?>;">
                                <?php echo $delayMessage; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="period-tracer-form-wrapper">
                        <form method="post" class="period-tracer-form">
                            <input type="hidden" name="save_period_tracer" value="1">
                            <div class="form-group">
                                <label for="tracer_last_period_start">Kailan ka huling niregla?</label>
                                <input class="form-control" type="date" id="tracer_last_period_start" name="tracer_last_period_start"
                                       value="<?php echo $lastPeriod ? $lastPeriod->format('Y-m-d') : ''; ?>" required>
                            </div>
                            <div class="form-helper-text">
                                <strong>Paalala:</strong> Gagamitin ang kasalukuyang bilang ng araw ng regla (<?php echo (int)$periodLength; ?> araw) 
                                at bilang ng araw bago muling reglahin (<?php echo (int)$cycleLength; ?> araw) para kalkulahin ang pagtatapos at susunod na regla.
                                <?php if (!$periodLength || !$cycleLength): ?>
                                    <br><a href="?view=update-cycle" style="color:#c2185b; text-decoration:underline;">I-update ang cycle info dito</a> kung kailangan.
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                                <button class="btn-primary btn-sm" type="submit">Start / update tracer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid main-section <?php echo $initialSection === 'calendar-section' ? 'active' : ''; ?>" id="calendar-section">
            <section class="card">
                <div class="card-header">
                    <div class="card-title">Cycle calendar</div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <a href="?view=calendar&month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?><?php echo isset($_GET['day']) ? '&day=' . (int)$_GET['day'] : ''; ?>" 
                           class="btn-outline btn-sm" 
                           style="text-decoration:none; padding:4px 8px; font-size:11px;">
                            ← Prev
                        </a>
                        <span class="chip"><?php echo $firstOfMonth->format('F Y'); ?></span>
                        <a href="?view=calendar&month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?><?php echo isset($_GET['day']) ? '&day=' . (int)$_GET['day'] : ''; ?>" 
                           class="btn-outline btn-sm" 
                           style="text-decoration:none; padding:4px 8px; font-size:11px;">
                            Next →
                        </a>
                        <a href="?view=calendar"
                           class="btn-outline btn-sm"
                           style="text-decoration:none; padding:4px 8px; font-size:11px;">
                            Reset
                        </a>
                    </div>
                </div>
                <div class="calendar-grid">
                    <?php
                    $daysOfWeek = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                    foreach ($daysOfWeek as $d) {
                        echo '<div class="calendar-day-header">' . $d . '</div>';
                    }
                    $selectedCalendarDay = isset($_GET['day']) ? (int)$_GET['day'] : null;
                    for ($i = 1; $i < $startWeekday; $i++) {
                        echo '<div></div>';
                    }
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $date = new DateTime("$year-$month-$day");
                        $classes = ['calendar-cell'];
                        $tooltip = $date->format('M d, Y');
                        $startIcon = '';

                        // Determine phase colors
                        if ($lastPeriod) {
                            $daysFromLast = (int)$date->diff($lastPeriod)->format('%r%a');
                            if ($daysFromLast >= 0 && $daysFromLast < $periodLength) {
                                $classes[] = 'cell-period';
                                $tooltip .= ' • Period day';
                                // Mark first day of period with red blood icon
                                if ($daysFromLast === 0) {
                                    $startIcon = '<span class=\"calendar-icon\">🩸</span>';
                                }
                            } elseif ($ovulationDate && $date->format('Y-m-d') === $ovulationDate->format('Y-m-d')) {
                                // Check ovulation day FIRST before fertile window (ovulation takes priority)
                                $classes[] = 'cell-ovulation';
                                $tooltip .= ' • Ovulation (estimated)';
                                // Mark ovulation day with yellow blood icon
                                $startIcon = '<span class=\"calendar-icon calendar-icon-yellow-blood\">🩸</span>';
                            } elseif (isBetween($date, $fertileStart, $fertileEnd)) {
                                $classes[] = 'cell-fertile';
                                $tooltip .= ' • Fertile window';
                            }
                        }

                        // Highlight today's date
                        if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
                            $classes[] = 'calendar-today';
                            $tooltip .= ' • Today';
                        }

                        // Highlight selected day from calendar
                        if (!is_null($selectedCalendarDay) && $selectedCalendarDay === $day) {
                            $classes[] = 'cell-selected';
                            $tooltip .= ' • Selected day';
                        }

                        $dayUrl = '?view=symptoms&month=' . $month . '&year=' . $year . '&day=' . $day;
                        echo '<div class="' . implode(' ', $classes) . '" data-tooltip="' . htmlspecialchars($tooltip) . '" onclick="window.location.href=\'' . $dayUrl . '\'">';
                        echo '<span>' . $day . '</span>' . $startIcon;
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="legend">
                    <div class="legend-item">
                        <span class="legend-color legend-red"></span>
                        <span>Period days</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color legend-green"></span>
                        <span>Fertile window</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-color legend-yellow"></span>
                        <span>Ovulation</span>
                    </div>
                </div>
                <div class="legend-dates" style="margin-top:12px; font-size:12px; color:#555;">
                    <?php if ($lastPeriod && $periodEndDate): ?>
                        <div style="margin-bottom:6px;">
                            <strong style="color:#d32f2f;">Period days:</strong> 
                            <?php echo $lastPeriod->format('M d, Y'); ?> - <?php echo $periodEndDate->format('M d, Y'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($fertileStart && $fertileEnd): ?>
                        <div style="margin-bottom:6px;">
                            <strong style="color:#2e7d32;">Fertile window:</strong> 
                            <?php echo $fertileStart->format('M d, Y'); ?> - <?php echo $fertileEnd->format('M d, Y'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($ovulationDate): ?>
                        <div style="margin-bottom:6px;">
                            <strong style="color:#ffd54f;">Ovulation:</strong> 
                            <span style="filter: hue-rotate(45deg) saturate(1.5) brightness(1.2); display:inline-block;">🩸</span> <?php echo $ovulationDate->format('M d, Y'); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!$lastPeriod): ?>
                        <div style="color:#999; font-size:11px;">
                            Update your last period date to see cycle dates.
                        </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top:8px; font-size:11px; color:#777;">
                    Click a date to quickly add notes and symptoms for that day.
                </div>
            </section>
        </div>

        <div class="grid main-section <?php echo $initialSection === 'symptoms-section' ? 'active' : ''; ?>" id="symptoms-section">
            <section class="card symptoms-card">
                <div class="card-header">
                    <div class="card-title">Symptoms & mood tracker</div>
                    <span class="chip">Daily body check-in</span>
                </div>
                <?php if ($logMessage): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($logMessage); ?></div>
                <?php endif; ?>
                <?php
                $selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : (int)$today->format('j');
                $selectedDate = new DateTime("$year-$month-" . str_pad((string)$selectedDay, 2, '0', STR_PAD_LEFT));
                $defaultLogDate = $selectedDate->format('Y-m-d');
                ?>
                <div class="symptoms-layout">
                    <div class="symptoms-form-panel">
                        <div class="symptoms-form-heading">
                            <div class="symptoms-form-title">Log how you feel</div>
                            <div class="symptoms-form-subtitle">
                                Selected date:
                                <strong><?php echo $selectedDate->format('M d, Y'); ?></strong>
                            </div>
                        </div>
                        <form method="post">
                            <input type="hidden" name="save_log" value="1">
                            <div class="form-group">
                                <label for="log_date">Select the date</label>
                                <input class="form-control" type="date" id="log_date" name="log_date"
                                       value="<?php echo $defaultLogDate; ?>">
                            </div>
                            <div class="form-group" style="margin-top:6px;">
                                <label>Symptoms</label>
                                <div class="chips-row">
                                    <?php
                                    $symptomOptions = [
                                        'cramps' => 'Cramps',
                                        'headache' => 'Headache',
                                        'breast_pain' => 'Breast pain',
                                        'acne' => 'Acne',
                                        'fatigue' => 'Fatigue'
                                    ];
                                    foreach ($symptomOptions as $value => $label) {
                                        $id = 'sym_' . $value;
                                        echo '<button type="button" class="chip-toggle" data-for="' . $id . '">' . $label . '</button>';
                                        echo '<input type="checkbox" id="' . $id . '" name="symptoms[]" value="' . $value . '" style="display:none">';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:6px;">
                                <label for="mood">Mood</label>
                                <select id="mood" name="mood" class="form-select">
                                    <option value="happy">Happy</option>
                                    <option value="sad">Sad</option>
                                    <option value="irritable">Irritable</option>
                                    <option value="anxious">Anxious</option>
                                    <option value="neutral" selected>Neutral</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-top:6px;">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" class="form-textarea" placeholder="Halimbawa: Masakit ang balakang, gusto ng sweets, stressed sa work."></textarea>
                            </div>
                            <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                                <button type="submit" class="btn-primary btn-sm">Save log</button>
                            </div>
                        </form>
                    </div>
                    <aside class="symptoms-insights-panel">
                        <div class="symptom-mini-card mood-scale-card">
                            <div class="mini-card-title">Mood scale</div>
                            <div class="mood-scale-row">
                                <span>😊</span>
                                <span>😐</span>
                                <span>🥺</span>
                                <span>😣</span>
                                <span>😴</span>
                            </div>
                            <p>Use the mood field to quickly capture your overall feeling for the day.</p>
                        </div>
                        <div class="symptom-mini-card care-card">
                            <div class="mini-card-title">Gentle self‑care ideas</div>
                            <ul>
                                <li>Warm compress or gentle stretching for cramps.</li>
                                <li>Hydrate well and eat iron‑rich food during heavy days.</li>
                                <li>Schedule quiet time when you feel easily irritated or tired.</li>
                            </ul>
                        </div>
                        <div class="symptom-mini-card alert-card">
                            <div class="mini-card-title">When to seek help</div>
                            <p>If pain is severe, bleeding is very heavy, or mood changes feel overwhelming,
                                track these in notes and consider consulting a health professional.</p>
                        </div>
                    </aside>
                </div>
            </section>
        </div>

        <div class="grid main-section <?php echo $initialSection === 'insights-section' ? 'active' : ''; ?>" id="insights-section">
            <section class="card">
                <div class="card-header">
                    <div class="card-title">Health insights</div>
                </div>
                <div style="font-size:13px;">
                    <p>
                        <strong>Average cycle length:</strong>
                        <?php
                        if ($avgCycle) {
                            echo $avgCycle . ' days ';
                            echo $irregular ? '<span class="tag tag-alert">Irregular pattern detected</span>' : '<span class="tag tag-ok">Relatively stable</span>';
                        } else {
                            echo 'Need at least 2 logged cycles for analysis.';
                        }
                        ?>
                    </p>
                    <p style="margin-top:6px;">
                        <strong>Irregular cycle detection:</strong>
                        <?php
                        if ($avgCycle) {
                            if ($irregular) {
                                echo 'Napapansin namin na may malaking variation sa haba ng cycle mo. Maaaring normal ito, pero kung may kasama kang ibang sintomas (matinding pananakit, sobra o kulang ang pagdurugo), magandang magpatingin sa OB-GYN.';
                            } else {
                                echo 'Sa ngayon, mukhang medyo consistent ang haba ng iyong cycle.';
                            }
                        } else {
                            echo 'Mag-log muna ng ilang cycle para makakita ng pattern.';
                        }
                        ?>
                    </p>
                    <p style="margin-top:6px;">
                        <strong>Reminders:</strong>
                        <ul style="margin-left:18px; margin-top:4px;">
                            <li>Period reminder: tingnan ang "Next period" card para sa inaasahang petsa.</li>
                            <li>Ovulation reminder: bantayan ang fertile window at ovulation card kung nagpa-plan magbuntis o umiwas.</li>
                            <li>Health tips: i-track ang sintomas at mood para mas maintindihan ang body patterns mo buwan-buwan.</li>
                        </ul>
                    </p>
                </div>
            </section>

            <section class="card">
                <div class="card-header">
                    <div class="card-title">Recent logs</div>
                </div>
                <table class="history-table period-history-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Mood</th>
                        <th>Symptoms</th>
                        <th>Notes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo (new DateTime($log['log_date']))->format('M d'); ?></td>
                                <td><?php echo htmlspecialchars($log['mood']); ?></td>
                                <td>
                                    <?php
                                    if ($log['symptoms']) {
                                        echo str_replace(
                                            ['cramps','headache','breast_pain','acne','fatigue'],
                                            ['cramps','headache','breast pain','acne','fatigue'],
                                            htmlspecialchars($log['symptoms'])
                                        );
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    echo $log['notes'] ? htmlspecialchars(mb_strimwidth($log['notes'], 0, 40, '…')) : '-';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">Wala pang symptoms/mood logs.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <div class="grid main-section <?php echo $initialSection === 'history-section' ? 'active' : ''; ?>" id="history-section">
            <section class="card">
                <div class="card-header">
                    <div class="card-title">Period history</div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span class="chip">Last <?php echo $cycles ? count($cycles) : 0; ?> cycles</span>
                        <a href="download_history.php" class="btn-primary btn-sm" style="text-decoration:none;">
                            Download CSV
                        </a>
                    </div>
                </div>
                <table class="history-table period-history-table">
                    <thead>
                    <tr>
                        <th>Start date</th>
                        <th>Days ng regla</th>
                        <th>Cycle length</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($cycles): ?>
                        <?php foreach ($cycles as $c): ?>
                            <tr>
                                <td data-label="Start date"><?php echo (new DateTime($c['period_start']))->format('M d, Y'); ?></td>
                                <td data-label="Days ng regla"><?php echo (int)$c['period_length']; ?> days</td>
                                <td data-label="Cycle length"><?php echo (int)$c['cycle_length']; ?> days</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">Wala pang naitalang history ng period. I-update ang cycle info para magsimulang magtala.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <div class="grid main-section <?php echo $initialSection === 'update-cycle-section' ? 'active' : ''; ?>" id="update-cycle-section">
            <section class="card update-cycle-card">
                <div class="card-header">
                    <div class="card-title">Update cycle info</div>
                </div>
                <?php if ($cycleMessage): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($cycleMessage); ?></div>
                <?php endif; ?>
                <div class="update-cycle-summary">
                    <div class="update-summary-item">
                        <div class="update-summary-label">Last period start</div>
                        <div class="update-summary-value">
                            <?php echo $lastPeriod ? $lastPeriod->format('M d, Y') : 'Not set'; ?>
                        </div>
                    </div>
                    <div class="update-summary-item">
                        <div class="update-summary-label">Cycle length</div>
                        <div class="update-summary-value">
                            <?php echo $cycleLength ? $cycleLength . ' days' : '—'; ?>
                        </div>
                    </div>
                    <div class="update-summary-item">
                        <div class="update-summary-label">Days of period</div>
                        <div class="update-summary-value">
                            <?php echo $periodLength ? $periodLength . ' days' : '—'; ?>
                        </div>
                    </div>
                </div>
                <form method="post">
                    <input type="hidden" name="update_cycle" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="last_period_start_update">First day ng last period</label>
                            <input class="form-control" type="date" id="last_period_start_update" name="last_period_start"
                                   value="<?php echo $lastPeriod ? $lastPeriod->format('Y-m-d') : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="period_length_update">Number of days ng regla</label>
                            <input class="form-control" type="number" id="period_length_update" name="period_length"
                                   min="1" max="10" value="<?php echo htmlspecialchars($periodLength); ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:8px;">
                        <label for="cycle_length_update">Cycle length (days)</label>
                        <input class="form-control" type="number" id="cycle_length_update" name="cycle_length"
                               min="20" max="60" value="<?php echo htmlspecialchars($cycleLength); ?>">
                    </div>
                    <div style="margin-top:10px; display:flex; justify-content:flex-end;">
                        <button class="btn-primary btn-sm" type="submit">Save cycle</button>
                    </div>
                </form>
                <div style="margin-top:10px; font-size:11px; color:#777;">
                    Tip: Kung hindi regular ang cycle mo, i-log pa rin ang periods upang makita ang pattern at makatulong sa health professional.
                </div>
            </section>
        </div>
    </main>
    <div class="tooltip"></div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>


