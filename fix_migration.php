<?php
// Migration fix script - with hardcoded DB connection
$pdo = new PDO("mysql:host=localhost;port=3306;dbname=drillifx;charset=utf8mb4", "root", "secret");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check upgrade options
echo "Upgrade options:\n";
$st = $pdo->query("SELECT * FROM upgrade_options ORDER BY sort_order");
foreach ($st->fetchAll() as $row) {
    echo "ID={$row['id']} name={$row['name']} display={$row['display_name']} rating={$row['display_rating']} hash={$row['daily_hash_allowance']} spin={$row['daily_spin_allowance']} ref={$row['referral_requirement']} type={$row['requirement_type']} price={$row['price']} active={$row['active']}\n";
}

// Update users without upgrade_option_id
echo "\nUpdating users without upgrade_option_id...\n";
$pdo->exec("UPDATE users SET upgrade_option_id = 1 WHERE upgrade_option_id IS NULL AND tier_id > 0");

// Verify
$st = $pdo->query("SELECT COUNT(*) c FROM users WHERE upgrade_option_id IS NULL");
echo "Users without upgrade_option_id: " . $st->fetch()['c'] . "\n";

// Show a few users
echo "\nSample users:\n";
$st = $pdo->query("SELECT u.username, u.tier_id, u.upgrade_option_id, t.name as tier_name, o.display_name as opt_name FROM users u LEFT JOIN tiers t ON t.id=u.tier_id LEFT JOIN upgrade_options o ON o.id=u.upgrade_option_id LIMIT 5");
foreach ($st->fetchAll() as $row) {
    echo "user={$row['username']} tier={$row['tier_name']} opt={$row['opt_name']}\n";
}

echo "\n=== Done ===\n";
?>