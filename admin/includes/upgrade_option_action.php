<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../admin_auth.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['upgrade_option_action'] ?? '';
$id = (int)($_GET['id'] ?? $_POST['upgrade_option_id'] ?? 0);
$pdo = db();

if ($action === 'create' || $action === 'update') {
    $name = trim($_POST['upgrade_option_name'] ?? '');
    $displayName = trim($_POST['upgrade_option_display_name'] ?? '');
    $displayRating = trim($_POST['upgrade_option_display_rating'] ?? '⭐⭐');
    $dailyHash = (int)($_POST['upgrade_option_daily_hash'] ?? 1);
    $dailySpin = (int)($_POST['upgrade_option_daily_spin'] ?? 1);
    $referralRequirement = (int)($_POST['upgrade_option_referral_requirement'] ?? 3);
    $requirementType = $_POST['upgrade_option_requirement_type'] ?? 'fixed';
    $price = (float)($_POST['upgrade_option_price'] ?? 0);
    $duration = (int)($_POST['upgrade_option_duration'] ?? 30);

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit;
    }

    if ($action === 'create') {
        // Check if name already exists
        $existing = $pdo->prepare("SELECT id FROM upgrade_options WHERE name = ? LIMIT 1");
        $existing->execute([$name]);
        if ($existing->fetch()) {
            echo json_encode(['success' => false, 'message' => 'An upgrade option with this name already exists']);
            exit;
        }
        $pdo->prepare("INSERT INTO upgrade_options (name, display_name, display_rating, daily_hash_allowance, daily_spin_allowance, referral_requirement, requirement_type, price, duration_days, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM upgrade_options), 1)")
            ->execute([$name, $displayName, $displayRating, $dailyHash, $dailySpin, $referralRequirement, $requirementType, $price, $duration]);
        echo json_encode(['success' => true, 'message' => 'Upgrade option created successfully']);
    } elseif ($action === 'update') {
        $uo = $pdo->prepare("SELECT * FROM upgrade_options WHERE id = ? LIMIT 1")->execute([$id])->fetch();
        if (!$uo) {
            echo json_encode(['success' => false, 'message' => 'Upgrade option not found']);
            exit;
        }
        $pdo->prepare("UPDATE upgrade_options SET display_name = ?, display_rating = ?, daily_hash_allowance = ?, daily_spin_allowance = ?, referral_requirement = ?, requirement_type = ?, price = ?, duration_days = ? WHERE name = ?")
            ->execute([$displayName, $displayRating, $dailyHash, $dailySpin, $referralRequirement, $requirementType, $price, $duration, $uo['name']]);
        echo json_encode(['success' => true, 'message' => 'Upgrade option updated successfully']);
    }
} elseif ($action === 'delete') {
    // Check if any users are using this upgrade option
    $using = $pdo->prepare("SELECT COUNT(*) FROM users WHERE upgrade_option_id = ?")->execute([$id])->fetchColumn();
    if ($using > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: ' . $using . ' user(s) are using this upgrade option']);
        exit;
    }
    $pdo->prepare("DELETE FROM upgrade_options WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Upgrade option deleted successfully']);
} elseif ($action === 'edit') {
    $uo = $pdo->prepare("SELECT * FROM upgrade_options WHERE id = ? LIMIT 1")->execute([$id])->fetch();
    if (!$uo) {
        echo json_encode(['success' => false, 'message' => 'Upgrade option not found']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Loaded', 'fields' => [
        'name' => $uo['name'],
        'display_name' => $uo['display_name'],
        'display_rating' => $uo['display_rating'],
        'daily_hash' => $uo['daily_hash_allowance'],
        'daily_spin' => $uo['daily_spin_allowance'],
        'referral_requirement' => $uo['referral_requirement'],
        'requirement_type' => $uo['requirement_type'],
        'price' => $uo['price'],
        'duration' => $uo['duration_days'],
    ]]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}