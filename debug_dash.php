<?php
// Simulate dashboard.php execution with PHP CLI
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/dashboard_debug.log');

require __DIR__ . '/includes/bootstrap.php';

// Simulate a logged-in user (user 6 - takom with upgrade_option_id=1)
$_SESSION['user_id'] = 6;
$_SESSION['csrf'] = bin2hex(random_bytes(32));

// Now try to replicate what dashboard.php does
// Get the PDO instance the way dashboard.php does
$dbCfg = require __DIR__ . '/config/database.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbCfg['host'], $dbCfg['port'], $dbCfg['db'], $dbCfg['charset']);
$pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// Simulate require_login() - get user
$st = $pdo->prepare('SELECT u.*, t.name AS tier_name FROM users u LEFT JOIN tiers t ON t.id = u.tier_id WHERE u.id = ? LIMIT 1');
$st->execute([6]);
$user = $st->fetch();

if (!$user || ($user['status'] ?? '') !== 'active') {
    echo "User not found or not active\n";
    exit(1);
}

// Expire paid tiers automatically (downgrade to Free, balance intact)
if (!empty($user['tier_expires_at']) && (int)$user['tier_id'] > 0 && strtotime($user['tier_expires_at']) < time()) {
    $up = $pdo->prepare('UPDATE users SET tier_id = 0, tier_expires_at = NULL WHERE id = ?');
    $up->execute([6]);
    $user['tier_id'] = 0;
    $user['tier_name'] = 'Free Tier';
    $user['tier_expires_at'] = null;
}

// Upgrade over: if user has an active upgrade option, overlay its display info
if (!empty($user['upgrade_option_id'])) {
    $uoSt = $pdo->prepare('SELECT * FROM upgrade_options WHERE id = ? AND active = 1 LIMIT 1');
    $uoSt->execute([$user['upgrade_option_id']]);
    $uo = $uoSt->fetch();
    if ($uo) {
        $user['tier_name'] = $uo['display_name'] ?? $user['tier_name'];
        $user['tier_rating'] = $uo['display_rating'] ?? '';
    }
}

// Now replicate the dashboard tier logic
$upgradeOptionId = (int)($user['upgrade_option_id'] ?? 0);
$uo = null;
if (!empty($upgradeOptionId)) {
    $st = $pdo->prepare('SELECT * FROM upgrade_options WHERE id = ? AND active = 1 LIMIT 1');
    $st->execute([$upgradeOptionId]);
    $uo = $st->fetch();
}
if (!empty($uo)) {
    $tierName = $uo['display_name'] ?? 'Beginner';
    $rating = $uo['display_rating'] ?? '⭐⭐';
    $hashLimit = (int)($uo['daily_hash_allowance'] ?? 1);
    $spinLimit = (int)($uo['daily_spin_allowance'] ?? 1);
    $referralReq = (float)($uo['referral_requirement'] ?? 3);
    $reqType = $uo['requirement_type'] ?? 'fixed';
} else {
    $st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1');
    $st->execute([(int)$user['tier_id']]);
    $tier = $st->fetch() ?: ['daily_hashes'=>1,'daily_spins'=>1];
    $tierName = $user['tier_name'] ?? 'Free Tier';
    $rating = '';
    $hashLimit = (int)($tier['daily_hashes'] ?? 1);
    $spinLimit = (int)($tier['daily_spins'] ?? 1);
    $referralReq = 3;
    $reqType = 'fixed';
}

// today_count function (from functions.php)
function today_count($pdo, $table, $userId) {
    $allowed = ['hashes' => true, 'spins' => true];
    if (!isset($allowed[$table])) return 0;
    $st = $pdo->prepare("SELECT COUNT(*) c FROM `$table` WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $st->execute([$userId]);
    return (int)($st->fetch()['c'] ?? 0);
}

$hashDone = today_count($pdo, 'hashes', 6);
$spinDone = today_count($pdo, 'spins', 6);

// Referral count
$st = $pdo->prepare('SELECT COUNT(*) c FROM referrals WHERE referrer_id=?');
$st->execute([6]);
$refCount = (int)$st->fetch()['c'];

// Recent transactions
$st = $pdo->prepare('SELECT type, amount, created_at FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 3');
$st->execute([6]);
$recent = $st->fetchAll();

// Output results
echo "User: " . $user['username'] . "\n";
echo "Tier name: $tierName\n";
echo "Rating: $rating\n";
echo "Hash limit: $hashLimit, Spin limit: $spinLimit\n";
echo "Referral req: $referralReq\n";
echo "Hash done: $hashDone, Spin done: $spinDone\n";
echo "Referral count: $refCount\n";
echo "Recent transactions: " . count($recent) . "\n";
echo "\nSUCCESS - Dashboard simulation works!\n";