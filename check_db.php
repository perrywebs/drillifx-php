<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 3308;
$db = getenv('DB_NAME') ?: 'drillifx';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'secret';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected OK\n";
    
    // Check upgrade_options
    $st = $pdo->query("SELECT COUNT(*) c FROM upgrade_options");
    echo "upgrade_options rows: " . $st->fetch()['c'] . "\n";
    
    $st = $pdo->query("SELECT * FROM upgrade_options ORDER BY sort_order");
    foreach ($st->fetchAll() as $row) {
        echo "  ID={$row['id']} name={$row['name']} display={$row['display_name']} hash={$row['daily_hash_allowance']} spin={$row['daily_spin_allowance']} ref={$row['referral_requirement']}\n";
    }
    
    // Check users
    $st = $pdo->query("DESCRIBE users");
    echo "\nusers columns:\n";
    foreach ($st->fetchAll() as $row) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    
    $st = $pdo->query("SELECT COUNT(*) c FROM users WHERE upgrade_option_id IS NULL");
    echo "\nUsers without upgrade_option_id: " . $st->fetch()['c'] . "\n";
    
    // Show some user data
    $st = $pdo->query("SELECT u.username, u.tier_id, u.upgrade_option_id, t.name as tier_name, o.display_name as opt_name FROM users u LEFT JOIN tiers t ON t.id=u.tier_id LEFT JOIN upgrade_options o ON o.id=u.upgrade_option_id LIMIT 5");
    echo "\nSample users:\n";
    foreach ($st->fetchAll() as $row) {
        echo "  user={$row['username']} tier={$row['tier_name']} opt={$row['opt_name']}\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}