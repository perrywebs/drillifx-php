<?php
// Migration verification script
// Load config first
$cfg = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset={$cfg['charset']}", $cfg['user'], $cfg['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if upgrade_options table exists
    $st = $pdo->query("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema='drillifx' AND table_name='upgrade_options'");
    $exists = $st->fetch()['c'] > 0;
    echo "upgrade_options table exists: " . ($exists ? "YES" : "NO") . PHP_EOL;
    
    // Check users table for upgrade_option_id
    $st = $pdo->query("DESCRIBE users");
    echo "\nusers table columns:" . PHP_EOL;
    foreach ($st->fetchAll() as $row) {
        echo "  - " . $row['Field'] . " ({$row['Type']})" . PHP_EOL;
    }
    
    // Show upgrade_options data if exists
    if ($exists) {
        $st = $pdo->query("SELECT * FROM upgrade_options ORDER BY sort_order");
        echo "\nupgrade_options data:" . PHP_EOL;
        foreach ($st->fetchAll() as $row) {
            echo "  ID={$row['id']} name={$row['name']} display={$row['display_name']} rating={$row['display_rating']} hash={$row['daily_hash_allowance']} spin={$row['daily_spin_allowance']} ref_req={$row['referral_requirement']} type={$row['requirement_type']} price={$row['price']} days={$row['duration_days']} active={$row['active']}" . PHP_EOL;
        }
    }
    
    // Show users without upgrade_option_id
    $st = $pdo->query("SELECT COUNT(*) c FROM users WHERE upgrade_option_id IS NULL");
    $nullCount = $st->fetch()['c'];
    echo "\nUsers without upgrade_option_id: $nullCount" . PHP_EOL;
    
    // Show current user tier info
    $st = $pdo->query("SELECT u.*, COALESCE(t.name, 'Free Tier') as tier_name FROM users u LEFT JOIN tiers t ON t.id=u.tier_id LIMIT 5");
    echo "\nFirst 5 users with tiers:" . PHP_EOL;
    foreach ($st->fetchAll() as $row) {
        echo "  user={$row['username']} tier_id={$row['tier_id']} tier_name={$row['tier_name']} upgrade_option_id={$row['upgrade_option_id']}" . PHP_EOL;
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . PHP_EOL;
}
?>