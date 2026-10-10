<?php
// Test with Free Tier user (user 24)
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/dashboard_debug2.log');

require __DIR__ . '/includes/bootstrap.php';

$dbCfg = require __DIR__ . '/config/database.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbCfg['host'], $dbCfg['port'], $dbCfg['db'], $dbCfg['charset']);
$pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// Simulate user 24 - Free Tier, no upgrade option
$_SESSION['user_id'] = 24;

$st = $pdo->prepare('SELECT u.*, t.name AS tier_name FROM users u LEFT JOIN tiers t ON t.id = u.tier_id WHERE u.id = ? LIMIT 1');
$st->execute([24]);
$user = $st->fetch();

if (!$user || ($user['status'] ?? '') !== 'active') {
    echo "User not found or not active\n";
    exit(1);
}

// Expire paid tiers
if (!empty($user['tier_expires_at']) && (int)$user['tier_id'] > 0 && strtotime($user['tier_expires_at']) < time()) {
    $up = $pdo->prepare('UPDATE users SET tier_id = 0, tier_expires_at = NULL WHERE id = ?');
    $up->execute([24]);
    $user['tier_id'] = 0;
    $user['tier_name'] = 'Free Tier';
    $user['tier_expires_at'] = null;
}

// Upgrade over
if (!empty($user['upgrade_option_id'])) {
    $uoSt = $pdo->prepare('SELECT * FROM upgrade_options WHERE id = ? AND active = 1 LIMIT 1');
    $uoSt->execute([$user['upgrade_option_id']]);
    $uo = $uoSt->fetch();
    if ($uo) {
        $user['tier_name'] = $uo['display_name'] ?? $user['tier_name'];
        $user['tier_rating'] = $uo['display_rating'] ?? '';
    }
}

// Dashboard tier logic
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

// today_count
function today_count($pdo, $table, $userId) {
    $allowed = ['hashes' => true, 'spins' => true];
    if (!isset($allowed[$table])) return 0;
    $st = $pdo->prepare("SELECT COUNT(*) c FROM `$table` WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $st->execute([$userId]);
    return (int)($st->fetch()['c'] ?? 0);
}

$hashDone = today_count($pdo, 'hashes', 24);
$spinDone = today_count($pdo, 'spins', 24);

// Referral count
$st = $pdo->prepare('SELECT COUNT(*) c FROM referrals WHERE referrer_id=?');
$st->execute([24]);
$refCount = (int)$st->fetch()['c'];

// Output
echo "User: " . $user['username'] . "\n";
echo "Tier ID: " . $user['tier_id'] . "\n";
echo "Tier name: $tierName\n";
echo "Hash limit: $hashLimit\n";
echo "Hash done: $hashDone\n";
echo "SUCCESS - Free Tier user simulation works!\n";