<?php
// Phase 3 migration script - fixed
$cfg = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset={$cfg['charset']}", $cfg['user'], $cfg['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Applying Phase 3 Migration: Dynamic Upgrade Options ===" . PHP_EOL;
    
    // 1. Create upgrade_options table
    echo "1. Creating upgrade_options table..." . PHP_EOL;
    $pdo->exec("CREATE TABLE IF NOT EXISTS upgrade_options (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(50) NOT NULL DEFAULT '',
      display_name VARCHAR(50) NOT NULL DEFAULT '',
      display_rating VARCHAR(20) NOT NULL DEFAULT '',
      daily_hash_allowance TINYINT UNSIGNED NOT NULL DEFAULT 1,
      daily_spin_allowance TINYINT UNSIGNED NOT NULL DEFAULT 1,
      referral_requirement DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      requirement_type ENUM('fixed','threshold') NOT NULL DEFAULT 'fixed',
      price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      duration_days INT UNSIGNED NOT NULL DEFAULT 30,
      sort_order INT UNSIGNED NOT NULL DEFAULT 0,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "   OK" . PHP_EOL;
    
    // 2. Add upgrade_option_id column to users table (without constraint first, since table might have data)
    echo "2. Adding upgrade_option_id to users table..." . PHP_EOL;
    $pdo->exec("ALTER TABLE users ADD COLUMN upgrade_option_id INT UNSIGNED NULL DEFAULT NULL AFTER tier_id");
    echo "   OK" . PHP_EOL;
    
    // 3. Create the first 3 upgrade options
    echo "3. Seeding first 3 upgrade options (Beginner, Amateur, Expert)..." . PHP_EOL;
    $pdo->exec("--
INSERT IGNORE INTO upgrade_options (name, display_name, display_rating, daily_hash_allowance, daily_spin_allowance, referral_requirement, requirement_type, price, duration_days, sort_order, active) VALUES
('beginner', 'Beginner', '⭐⭐', 1, 1, 3, 'fixed', 0.00, 30, 1, 1),
('amateur', 'Amateur', '⭐⭐⭐', 2, 2, 5, 'fixed', 0.00, 30, 2, 1),
('expert', 'Expert', '⭐⭐⭐⭐', 3, 3, 11, 'threshold', 0.00, 30, 3, 1)");
    echo "   OK" . PHP_EOL;
    
    // 4. Set default upgrade option for existing users without one
    echo "4. Setting default upgrade option for existing users..." . PHP_EOL;
    $pdo->exec("UPDATE users SET upgrade_option_id = 1 WHERE upgrade_option_id IS NULL AND tier_id = 0");
    $st = $pdo->query("SELECT COUNT(*) c FROM users WHERE upgrade_option_id = 1 AND tier_id = 0");
    $count = $st->fetch()['c'];
    echo "   Updated $count users to Beginner upgrade option" . PHP_EOL;
    
    // 5. Add foreign key constraint
    echo "5. Adding foreign key constraint..." . PHP_EOL;
    // MySQL doesn't support adding FK via ALTER TABLE easily if data might violate it
    // But since we set default=1 and 1 exists, it should be fine
    try {
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_upgrade_option FOREIGN KEY (upgrade_option_id) REFERENCES upgrade_options(id) ON DELETE SET NULL");
        echo "   OK" . PHP_EOL;
    } catch (PDOException $e) {
        echo "   Note: FK could not be added (may already exist or data issue): " . $e->getMessage() . PHP_EOL;
    }
    
    // 6. Verify
    echo "6. Verifying migration..." . PHP_EOL;
    $st = $pdo->query("SELECT COUNT(*) c FROM upgrade_options WHERE active = 1");
    $optCount = $st->fetch()['c'];
    echo "   Active upgrade options: $optCount" . PHP_EOL;
    
    $st = $pdo->query("SELECT COUNT(*) c FROM users WHERE upgrade_option_id IS NOT NULL");
    $usersWithOption = $st->fetch()['c'];
    echo "   Users with upgrade_option_id: $usersWithOption" . PHP_EOL;
    
    // Show the upgrade options
    echo PHP_EOL . "   Upgrade options:" . PHP_EOL;
    $st = $pdo->query("SELECT * FROM upgrade_options ORDER BY sort_order");
    foreach ($st->fetchAll() as $row) {
        echo "   ID={$row['id']} {$row['display_name']} ({$row['display_rating']}) hash={$row['daily_hash_allowance']}/day spin={$row['daily_spin_allowance']}/day ref={$row['referral_requirement']}({$row['requirement_type']}) price=\${$row['price']} active={$row['active']}" . PHP_EOL;
    }
    
    // Show sample users
    echo PHP_EOL . "   Sample users:" . PHP_EOL;
    $st = $pdo->query("SELECT u.username, u.tier_id, u.upgrade_option_id, t.name as tier_name, o.display_name as opt_name FROM users u LEFT JOIN tiers t ON t.id=u.tier_id LEFT JOIN upgrade_options o ON o.id=u.upgrade_option_id LIMIT 5");
    foreach ($st->fetchAll() as $row) {
        echo "   user={$row['username']} tier={$row['tier_name']} opt={$row['opt_name']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "=== Migration Complete ===" . PHP_EOL;
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
?>