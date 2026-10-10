<?php
// Dashboard debug script - user without upgrade option
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/php-errors3.log');

require __DIR__ . '/includes/bootstrap.php';

$dbCfg = require __DIR__ . '/config/database.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbCfg['host'], $dbCfg['port'], $dbCfg['db'], $dbCfg['charset']);

try {
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "DB connection OK\n";
    
    // User 24 (zofoluqyfu) - Free Tier, no upgrade option
    $userId = 24;
    $u = [
        'id' => $userId,
        'username' => 'zofoluqyfu',
        'tier_id' => 0,
        'upgrade_option_id' => null,
        'tier_name' => 'Free Tier',
        'balance' => 0.00,
    ];
    
    // Replicate dashboard.php tier logic
    $upgradeOptionId = (int)($u['upgrade_option_id'] ?? 0);
    $uo = null;
    if (!empty($upgradeOptionId)) {
        $st = $pdo->prepare('SELECT * FROM upgrade_options WHERE id = ? AND active = 1 LIMIT 1');
        $st->execute([$upgradeOptionId]);
        $uo = $st->fetch();
    }
    if (!empty($uo)) {
        $tierName = $uo['display_name'] ?? 'Beginner';
        $hashLimit = (int)($uo['daily_hash_allowance'] ?? 1);
        $spinLimit = (int)($uo['daily_spin_allowance'] ?? 1);
    } else {
        $st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1');
        $st->execute([(int)$u['tier_id']]);
        $tier = $st->fetch();
        $tierName = $u['tier_name'] ?? 'Free Tier';
        $hashLimit = (int)($tier['daily_hashes'] ?? 1);
        $spinLimit = (int)($tier['daily_spins'] ?? 1);
    }
    
    echo "Tier name: $tierName\n";
    echo "Hash limit: $hashLimit\n";
    
    // today_count
    function today_count($pdo, $table, $userId) {
        $allowed = ['hashes' => true, 'spins' => true];
        if (!isset($allowed[$table])) return 0;
        $st = $pdo->prepare("SELECT COUNT(*) c FROM `$table` WHERE user_id = ? AND DATE(created_at) = CURDATE()");
        $st->execute([$userId]);
        return (int)($st->fetch()['c'] ?? 0);
    }
    
    $hashDone = today_count($pdo, 'hashes', $userId);
    $spinDone = today_count($pdo, 'spins', $userId);
    echo "Hash done: $hashDone, Spin done: $spinDone\n";
    
    echo "\nSUCCESS - Dashboard logic works for user 24!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log($e->getMessage() . "\n");
    echo "Script finished with error.\n";
}