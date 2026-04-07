<?php
declare(strict_types=1);

// auth.php handles the session check and redirects safely
require_once __DIR__ . '/auth.php';

try {
    $pdo = db();
    $userId = (int)$_SESSION['user_id'];
    $userName = $_SESSION['user_name'] ?? 'User';

    // 1. Verify the user exists and fetch settings
    $stmtUser = $pdo->prepare("SELECT total_required_hours FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . ". Please check if your 'users' table exists in the database.");
}

$totalRequired = (float)$user['total_required_hours'];
$errors = [];
$success = '';

// Pagination variables
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Helper to calculate hours rendered
function computeHours(?string $in, ?string $out): float
{
    if (!$in || !$out) return 0.0;
    $timeIn = new DateTime($in);
    $timeOut = new DateTime($out);
    $seconds = max(0, $timeOut->getTimestamp() - $timeIn->getTimestamp());
    return round($seconds / 3600, 2);
}

function formatHoursMins(float $hours): string
{
    $totalMinutes = (int) round($hours * 60);
    $h = intdiv($totalMinutes, 60);
    $m = $totalMinutes % 60;
    return "{$h}h {$m}m";
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save Total Required Hours Settings
    if (isset($_POST['save_settings'])) {
        $newRequired = trim($_POST['total_required_hours'] ?? '');
        if ($newRequired === '' || !is_numeric($newRequired) || (float)$newRequired < 0) {
            $errors[] = 'Total required hours must be a non-negative number.';
        } else {
            $totalRequired = (float)$newRequired;
            $updateReq = $pdo->prepare('UPDATE users SET total_required_hours = ? WHERE id = ?');
            $updateReq->execute([$totalRequired, $userId]);
            $success = 'Settings updated.';
        }
    }

    // Add Manual Shift
    if (isset($_POST['manual_add'])) {
        $manualIn = trim($_POST['manual_time_in'] ?? '');
        $manualOut = trim($_POST['manual_time_out'] ?? '');
        $manualIn = $manualIn !== '' ? str_replace('T', ' ', $manualIn) . ':00' : null;
        $manualOut = $manualOut !== '' ? str_replace('T', ' ', $manualOut) . ':00' : null;

        if (!$manualIn) {
            $errors[] = 'Manual Time In is required.';
        } elseif ($manualOut && strtotime($manualOut) < strtotime($manualIn)) {
            $errors[] = 'Manual Time Out cannot be earlier than Time In.';
        } else {
            $hours = computeHours($manualIn, $manualOut);
            $ins = $pdo->prepare('INSERT INTO time_logs (user_id, time_in, time_out, hours_rendered) VALUES (?, ?, ?, ?)');
            $ins->execute([$userId, $manualIn, $manualOut, $hours]);
            header("Location: dashboard.php?page=$page");
            exit;
        }
    }

    // Save Edited Shift
    if (isset($_POST['save_edit'])) {
        $logId = (int)($_POST['edit_log_id'] ?? 0);
        $editInRaw = trim($_POST['edit_time_in'] ?? '');
        $editOutRaw = trim($_POST['edit_time_out'] ?? '');
        $editIn = $editInRaw !== '' ? str_replace('T', ' ', $editInRaw) . ':00' : null;
        $editOut = $editOutRaw !== '' ? str_replace('T', ' ', $editOutRaw) . ':00' : null;

        if ($logId <= 0 || !$editIn) {
            $errors[] = 'Invalid shift edit payload.';
        } elseif ($editOut && strtotime($editOut) < strtotime($editIn)) {
            $errors[] = 'Edited Time Out cannot be earlier than Time In.';
        } else {
            $hours = computeHours($editIn, $editOut);
            $upd = $pdo->prepare('UPDATE time_logs SET time_in = ?, time_out = ?, hours_rendered = ? WHERE id = ? AND user_id = ?');
            $upd->execute([$editIn, $editOut, $hours, $logId, $userId]);
            header("Location: dashboard.php?page=$page");
            exit;
        }
    }

    // Delete Shift
    if (isset($_POST['delete_shift'])) {
        $logId = (int)($_POST['delete_log_id'] ?? 0);
        if ($logId > 0) {
            $del = $pdo->prepare('DELETE FROM time_logs WHERE id = ? AND user_id = ?');
            $del->execute([$logId, $userId]);
            header("Location: dashboard.php?page=$page");
            exit;
        }
    }
}

// Fetch Stats
$stmtSum = $pdo->prepare('
    SELECT COALESCE(SUM(hours_rendered), 0) AS rendered,
           COUNT(*) AS total_shifts,
           COALESCE(AVG(hours_rendered), 0) AS avg_hours
    FROM time_logs
    WHERE user_id = ?
');
$stmtSum->execute([$userId]);
$sumRow = $stmtSum->fetch();

$hoursRendered = (float)$sumRow['rendered'];
$totalShifts = (int)$sumRow['total_shifts'];
$avgHoursPerShift = (float)$sumRow['avg_hours'];
$hoursRemaining = max(0, $totalRequired - $hoursRendered);
$progress = $totalRequired > 0 ? min(100, round(($hoursRendered / $totalRequired) * 100, 2)) : 0;

// Pagination total pages
$totalPages = ceil($totalShifts / $limit);

// Fetch History with Pagination
$stmtHistory = $pdo->prepare("SELECT id, time_in, time_out, hours_rendered FROM time_logs WHERE user_id = ? ORDER BY time_in DESC LIMIT $limit OFFSET $offset");
$stmtHistory->execute([$userId]);
$history = $stmtHistory->fetchAll();

$editingId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// UI Config
$bodyClass = 'theme-netflix';
$tableClass = 'table table-dark table-hover align-middle mb-0';
$inputClass = 'form-control nf-input';
$progressColorHex = '#e10600';
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard - OJT Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        window.va = window.va || function () { (window.vaq = window.vaq || []).push(arguments); };
    </script>
    <script defer src="/_vercel/insights/script.js"></script>
    <style>
        :root {
            --accent: #e10600;
            --accent-dark: #b80400;
            --text-main: #f5f5f1;
            --text-soft: #cfd3d8;
            --panel: #121212;
            --panel-2: #0f0f0f;
            --line: rgba(255,255,255,.09);
        }
        body {
            font-family: "Netflix Sans", "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            background:
                radial-gradient(1000px 580px at 15% -12%, rgba(225,6,0,.22) 0%, rgba(16,16,16,0) 60%),
                radial-gradient(850px 420px at 100% 0%, rgba(225,6,0,.14) 0%, rgba(16,16,16,0) 60%),
                linear-gradient(180deg, #0c0c0c 0%, #070707 100%);
            color: var(--text-main);
        }
        .top-nav {
            backdrop-filter: blur(8px);
            background: rgba(7,7,7,.88);
            border-bottom: 1px solid rgba(229,9,20,.26);
            box-shadow: 0 8px 20px rgba(0,0,0,.35);
        }
        .brand-logo { height: 52px; width: auto; object-fit: contain; }
        .pill-btn { border-radius: 10px; padding: .48rem .95rem; font-weight: 700; font-size: .86rem; }
        .btn-settings {
            border: 1px solid rgba(229,9,20,.45);
            background: #151515;
            color: #f3f4f6;
        }
        .btn-settings:hover { background: #1a1a1a; color: #fff; border-color: rgba(229,9,20,.7); }
        .btn-logout {
            background: linear-gradient(180deg, #f20b00 0%, #c80500 100%);
            border-color: #d10600;
            color: #fff;
        }
        .btn-logout:hover { background: linear-gradient(180deg, #ff1207 0%, #b90400 100%); color: #fff; }
        .glass-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            background: linear-gradient(180deg, #171717 0%, #101010 100%);
            box-shadow: 0 10px 24px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.03);
        }
        .stat-head { font-size: .75rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; color: var(--text-soft); }
        .stat-value { font-size: 1.85rem; font-weight: 900; letter-spacing: .01em; }
        .progress-shell {
            background: linear-gradient(180deg, rgba(255,255,255,.13) 0%, rgba(255,255,255,.07) 100%);
            border-radius: 999px;
            height: 28px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.14);
        }
        .progress-fill {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: .83rem;
            text-shadow: 0 1px 1px rgba(0,0,0,.4);
        }
        .nf-input { background: #111 !important; border: 1px solid #333 !important; color: #fff !important; }
        .nf-input:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 .2rem rgba(225,6,0,.2) !important; }
        .table.table-dark { --bs-table-bg: #121212; --bs-table-striped-bg: #161616; --bs-table-hover-bg: #1b1b1b; border-color: rgba(255,255,255,.08); }
        .table thead th { font-size: .77rem; letter-spacing: .04em; text-transform: uppercase; color: #d1d5db; border-bottom-color: rgba(255,255,255,.13); }
        .table td { font-size: .9rem; }
        .action-group {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 62px;
            border-radius: 8px;
            padding: .28rem .58rem;
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .02em;
            text-decoration: none;
            border: 1px solid transparent;
            transition: .15s ease-in-out;
        }
        .action-edit {
            color: #e5e7eb;
            background: #171717;
            border-color: rgba(255,255,255,.16);
        }
        .action-edit:hover {
            color: #fff;
            background: #202020;
            border-color: rgba(255,255,255,.28);
        }
        .action-delete {
            color: #fff;
            background: linear-gradient(180deg, #f20b00 0%, #bc0500 100%);
            border-color: #d10600;
            cursor: pointer;
        }
        .action-delete:hover {
            background: linear-gradient(180deg, #ff1308 0%, #a70400 100%);
            border-color: #e10600;
        }
        .modal-content.glass-card { background: linear-gradient(180deg, #151515 0%, #0f0f0f 100%); border-color: rgba(255,255,255,.12); }
        
        /* Custom Pagination styles to match Netflix theme */
        .page-link { background-color: #171717; border-color: rgba(255,255,255,.08); color: #cfd3d8; }
        .page-link:hover { background-color: #202020; border-color: rgba(255,255,255,.16); color: #fff; }
        .page-item.disabled .page-link { background-color: #121212; border-color: rgba(255,255,255,.04); color: #6c757d; }
        
        @media (max-width: 768px) {
            .top-nav .container {
                flex-direction: column;
                align-items: flex-start !important;
                gap: .65rem;
            }
            .top-nav .d-flex.gap-2 {
                width: 100%;
                display: grid !important;
                grid-template-columns: 1fr 1fr;
            }
            .pill-btn {
                width: 100%;
                white-space: nowrap;
                text-align: center;
                font-size: .82rem;
                padding: .5rem .6rem;
            }
            .stat-value { font-size: 1.45rem; }
        }
        @media (max-width: 576px) {
            body { background: #080808; }
            .container.py-4 { padding-top: .95rem !important; padding-bottom: 1.1rem !important; }
            .brand-logo { height: 34px; }
            .glass-card.card.p-4 { padding: .95rem !important; border-radius: 12px; }
            .table td, .table th { font-size: .78rem; }
            .btn.btn-danger.btn-sm, .btn.btn-secondary.btn-sm { font-size: .72rem; }
        }
    </style>
</head>
<body>

<nav class="navbar top-nav py-3">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <img src="OJTTracking.png" alt="OJT Tracking" class="brand-logo">
            <p class="mb-0 small opacity-75 mt-1">Hello, <?= htmlspecialchars($userName) ?></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-settings pill-btn" data-bs-toggle="modal" data-bs-target="#settingsModal">⚙ Settings</button>
            <a href="logout.php" class="btn btn-logout pill-btn">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php if ($errors): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="glass-card card p-4">
                <div class="stat-head">Hours Rendered</div>
                <div class="stat-value text-white"><?= htmlspecialchars(formatHoursMins($hoursRendered)) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card card p-4">
                <div class="stat-head">Hours Remaining</div>
                <div class="stat-value text-white"><?= htmlspecialchars(formatHoursMins($hoursRemaining)) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card card p-4">
                <div class="stat-head">Goal</div>
                <div class="stat-value text-white"><?= number_format($totalRequired, 0) ?></div>
            </div>
        </div>
    </div>

    <div class="glass-card card p-4 mb-4">
        <h5 class="mb-3">Log New Shift</h5>
        <form method="post" class="row g-3">
            <div class="col-sm-5">
                <label class="form-label small">Time In</label>
                <input type="datetime-local" name="manual_time_in" class="<?= $inputClass ?>" required>
            </div>
            <div class="col-sm-5">
                <label class="form-label small">Time Out</label>
                <input type="datetime-local" name="manual_time_out" class="<?= $inputClass ?>">
            </div>
            <div class="col-sm-2 d-flex align-items-end">
                <button type="submit" name="manual_add" class="btn btn-logout w-100 rounded-pill">Add</button>
            </div>
        </form>
    </div>

    <div class="glass-card card p-4 mb-4">
        <h5 class="mb-3">Progress - <?= $progress ?>%</h5>
        <div class="progress-shell">
            <div class="progress-fill" style="width: <?= $progress ?>%; background: <?= $progressColorHex ?>;">
                <?= $progress ?>%
            </div>
        </div>
    </div>

    <div class="glass-card card p-4">
        <h5>History</h5>
        <div class="table-responsive">
            <table class="<?= $tableClass ?>">
                <thead>
                    <tr><th>Time In</th><th>Time Out</th><th>Hrs</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (count($history) > 0): ?>
                        <?php foreach ($history as $log): ?>
                            <tr>
                                <?php if ($editingId === (int)$log['id']): ?>
                                    <form method="post">
                                        <input type="hidden" name="edit_log_id" value="<?= $log['id'] ?>">
                                        <td><input type="datetime-local" name="edit_time_in" class="<?= $inputClass ?> form-control-sm" value="<?= date('Y-m-d\TH:i', strtotime((string)$log['time_in'])) ?>"></td>
                                        <td><input type="datetime-local" name="edit_time_out" class="<?= $inputClass ?> form-control-sm" value="<?= $log['time_out'] ? date('Y-m-d\TH:i', strtotime((string)$log['time_out'])) : '' ?>"></td>
                                        <td>-</td>
                                        <td>
                                            <button name="save_edit" class="btn btn-danger btn-sm">Save</button>
                                            <a href="dashboard.php?page=<?= $page ?>" class="btn btn-secondary btn-sm">X</a>
                                        </td>
                                    </form>
                                <?php else: ?>
                                    <td><?= date('M d, H:i', strtotime((string)$log['time_in'])) ?></td>
                                    <td><?= $log['time_out'] ? date('H:i', strtotime((string)$log['time_out'])) : 'Active' ?></td>
                                    <td><?= htmlspecialchars(formatHoursMins((float)$log['hours_rendered'])) ?> <span class="opacity-75">(<?= number_format((float)$log['hours_rendered'], 2) ?>h)</span></td>
                                    <td>
                                        <div class="action-group">
                                            <a href="?page=<?= $page ?>&edit=<?= $log['id'] ?>" class="action-btn action-edit">Edit</a>
                                            <form method="post" class="d-inline mb-0">
                                                <input type="hidden" name="delete_log_id" value="<?= $log['id'] ?>">
                                                <button name="delete_shift" class="action-btn action-delete" onclick="return confirm('Delete this shift?')">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center opacity-50 py-3">No shifts logged yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="History pagination" class="mt-4">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?><?= $editingId ? '&edit='.$editingId : '' ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link <?= $i === $page ? 'bg-danger border-danger text-white' : '' ?>" href="?page=<?= $i ?><?= $editingId ? '&edit='.$editingId : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?><?= $editingId ? '&edit='.$editingId : '' ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card">
            <form method="post">
                <div class="modal-header border-0"><h5 class="modal-title">Settings</h5></div>
                <div class="modal-body">
                    <label class="form-label">Total Required Hours</label>
                    <input type="number" name="total_required_hours" class="<?= $inputClass ?>" value="<?= $totalRequired ?>">
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" name="save_settings" class="btn btn-logout rounded-pill px-4">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
