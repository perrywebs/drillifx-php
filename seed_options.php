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
    
    // Seed the first 3 upgrade options
    echo "Seeding upgrade options...\n";
    $pdo->exec("--
INSERT INTO upgrade_options (name, display_name, display_rating, daily_hash_allowance, daily_spin_allowance, referral_requirement, requirement_type, price, duration_days, sort_order, active) VALUES
('beginner', 'Beginner', '⭐⭐', 1, 1, 3, 'fixed', 0.00, 30, 1, 1),
('amateur', 'Amateur', '⭐⭐⭐', 2, 2, 5, 'fixed', 0.00, 30, 2, 1),
('expert', 'Expert', '⭐⭐⭐⭐', 3, 3, 11, 'threshold', 0.00, 30, 3, 1)");
    echo "OK - seeded 3 upgrade options\n";
    
    // Update user takom to have upgrade_option_id = 1 (Beginner)
    echo "Updating user takom to Beginner upgrade option...\n";
    $pdo->exec("UPDATE users SET upgrade_option_id = 1 WHERE username = 'takom'");
    
    // Verify
    $st = $pdo->query("SELECT COUNT(*) c FROM users WHERE upgrade_option_id IS NULL");
    echo "Users without upgrade_option_id: " . $st->fetch()['c'] . "\n";
    
    $st = $pdo->query("SELECT u.username, u.upgrade_option_id, o.display_name FROM users u LEFT JOIN upgrade_options o ON o.id=u.upgrade_option_id WHERE u.username = 'takom'");
    foreach ($st->fetchAll() as $row) {
        echo "user={$row['username']} upgrade_option_id={$row['upgrade_option_id']} opt={$row['opt_name']}\n";
    }
    
    echo "\nDone!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}