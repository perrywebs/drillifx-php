<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin(['super_admin', 'admin']);
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Invalid session.');
    else {
        $prevVerify = setting('email_verification', '0');
$fields = [
    'site_name','site_title','site_description','support_email','contact_email','timezone','currency',
    'registration_enabled','referral_enabled','email_verification','mail_enabled',
    'min_deposit','min_withdraw','fx_USD','fx_NGN','fx_GHS','referral_reward',
    'spin_enabled','hash_enabled','maintenance_mode',
    'upgrade_option_name','upgrade_option_display_name','upgrade_option_display_rating',
    'upgrade_option_daily_hash','upgrade_option_daily_spin','upgrade_option_referral_requirement',
    'upgrade_option_requirement_type','upgrade_option_price','upgrade_option_duration',
];
        $changed = [];
        foreach ($fields as $f) {
            $v = $_POST[$f] ?? null;
            if (in_array($f, ['registration_enabled','referral_enabled','email_verification','mail_enabled','spin_enabled','hash_enabled','maintenance_mode'], true)) $v = isset($_POST[$f]) ? '1' : '0';
            $v = is_string($v) ? trim($v) : $v;
            if ($f === 'support_email' || $f === 'contact_email') { if ($v !== '' && !is_valid_email($v)) { admin_flash('error', "Invalid email for $f."); redirect('/admin/settings.php'); } }
            if (in_array($f, ['min_deposit','min_withdraw','fx_USD','fx_NGN','fx_GHS','referral_reward'], true)) { if (!is_numeric($v) || (float)$v < 0) { admin_flash('error', "Invalid number for $f."); redirect('/admin/settings.php'); } }
            if ((string)setting($f, '') !== (string)$v) { set_setting($f, $v); $changed[] = $f; }
            // Upgrade option fields - insert as new row or update existing
            if (in_array($f, ['upgrade_option_name','upgrade_option_display_name','upgrade_option_daily_hash','upgrade_option_daily_spin','upgrade_option_referral_requirement','upgrade_option_price','upgrade_option_duration'], true)) {
                $name = str_replace('upgrade_option_', '', $f);
                // Check if we're creating a new option (name field is provided) or updating
                if (!empty($_POST['upgrade_option_name'] ?? '')) {
                    // Upsert: if an option with this name exists, update it; otherwise insert
                    $existing = $pdo->prepare("SELECT id FROM upgrade_options WHERE name = ? LIMIT 1");
                    $existing->execute([$name]);
                    if ($existing->fetch()) {
                        $pdo->prepare("UPDATE upgrade_options SET display_name = ?, display_rating = ?, daily_hash_allowance = ?, daily_spin_allowance = ?, referral_requirement = ?, requirement_type = ?, price = ?, duration_days = ?, sort_order = COALESCE((SELECT MAX(sort_order)+1 FROM upgrade_options), 1), active = 1 WHERE name = ?")->execute([
                            $v, $_POST['upgrade_option_display_name'] ?? '', (int)($_POST['upgrade_option_daily_hash'] ?? 0), (int)($_POST['upgrade_option_daily_spin'] ?? 0), (int)($_POST['upgrade_option_referral_requirement'] ?? 0), $_POST['upgrade_option_requirement_type'] ?? 'fixed', (float)($v), (int)($_POST['upgrade_option_duration'] ?? 30), $name
                        ]);
                    } else {
                        $pdo->prepare("INSERT INTO upgrade_options (name, display_name, display_rating, daily_hash_allowance, daily_spin_allowance, referral_requirement, requirement_type, price, duration_days, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)")->execute([
                            $name, $v, $_POST['upgrade_option_display_name'] ?? '', (int)($_POST['upgrade_option_daily_hash'] ?? 0), (int)($_POST['upgrade_option_daily_spin'] ?? 0), (int)($_POST['upgrade_option_referral_requirement'] ?? 0), $_POST['upgrade_option_requirement_type'] ?? 'fixed', (float)($v), (int)($_POST['upgrade_option_duration'] ?? 30), 1
                        ]);
                    }
                }
                $changed[] = 'upgrade_option_'.$name;
            }
        }
        // Enabling email verification must not lock out existing users: backfill them as verified
        if ($prevVerify !== '1' && setting('email_verification', '0') === '1') {
            $pdo->exec("UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE email_verified_at IS NULL");
        }
        admin_log((int)$admin['id'], 'settings_change', 'Settings updated: ' . ($changed ? implode(', ', $changed) : 'no changes'));
        admin_flash('success', 'Settings saved' . ($changed ? ' (' . count($changed) . ' changed).' : '.'));
    }
    redirect('/admin/settings.php');
}
$v = fn($k, $d = '') => e((string)setting($k, $d));
$c = fn($k, $d = true) => cfg_flag($k, $d) ? ' checked' : '';
admin_head('Settings');
admin_sidebar($admin, 'settings');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">General Settings</h1>';
echo '<p class="text-sm text-slate-500 mb-4">Changes here take effect immediately across the site.</p>';
echo '<form method="POST" class="space-y-4">' . csrf_field();
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">General</h2><div class="grid md:grid-cols-2 gap-3">';
foreach ([['site_name','Site name'],['site_title','Site title'],['contact_email','Contact email'],['support_email','Support email'],['timezone','Timezone'],['currency','Currency (code)']] as [$k,$label]) {
    echo '<label class="text-sm text-slate-600">' . $label . '<input name="' . $k . '" value="' . $v($k) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
}
echo '<label class="text-sm text-slate-600 md:col-span-2">Site description<textarea name="site_description" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm">' . $v('site_description') . '</textarea></label>';
echo '<label class="text-sm flex items-center gap-2"><input type="checkbox" name="maintenance_mode" value="1"' . $c('maintenance_mode', false) . '> Maintenance mode (users see a notice page)</label>';
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">User settings</h2><div class="grid md:grid-cols-2 gap-3 text-sm">';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="registration_enabled" value="1"' . $c('registration_enabled') . '> Registration open</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="referral_enabled" value="1"' . $c('referral_enabled') . '> Referral system enabled</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="email_verification" value="1"' . $c('email_verification', false) . '> Require email verification (existing users auto-verified on enable)</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="mail_enabled" value="1"' . $c('mail_enabled') . '> Email notifications enabled</label>';
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">Financial</h2><div class="grid md:grid-cols-3 gap-3">';
foreach ([['min_deposit','Min deposit (USD)'],['min_withdraw','Min withdrawal (USD)'],['referral_reward','Referral reward (USD)'],['fx_USD','USD rate'],['fx_NGN','NGN rate'],['fx_GHS','GHS rate']] as [$k,$label]) {
    echo '<label class="text-sm text-slate-600">' . $label . '<input name="' . $k . '" type="number" step="any" min="0" value="' . $v($k, '0') . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
}
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">Games</h2><div class="grid md:grid-cols-2 gap-3 text-sm">';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="spin_enabled" value="1"' . $c('spin_enabled') . '> Spin enabled</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="hash_enabled" value="1"' . $c('hash_enabled') . '> Hash enabled</label>';
echo '</div></div>';
echo '<div class="mt-4">';
echo '<button class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold mb-3 add-upgrade-option-btn">Add Upgrade Option</button>';
echo '<div id="upgrade-options-list" class="space-y-3"></div>';
echo '</div>';

$u = require_login();
$pdo = db();
$options = $pdo->query("SELECT * FROM upgrade_options ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($options as $o) {
    $activeClass = $o['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    $activeText = $o['active'] ? 'Active' : 'Inactive';
    $reqType = $o['requirement_type'] ?? 'fixed';
    echo '<div class="p-3 border rounded-xl ' . ($o['active'] ? 'bg-green-50' : 'bg-red-50') . '">
        <div class="flex items-center justify-between">
            <span class="font-medium text-sm">' . e($o['display_name']) . ' <span class="text-xs ' . $activeClass . '">' . $activeText . '</span></span>
            <span class="text-xs text-slate-500">Rating: ' . e($o['display_rating'] ?? '') . ' | Hash: ' . e($o['daily_hash_allowance'] ?? 0) . ' | Spin: ' . e($o['daily_spin_allowance'] ?? 0) . ' | Refer: ' . e($o['referral_requirement'] ?? 0) . ' (' . ucfirst($reqType) . ') - $' . e(number_format($o['price'] ?? 0)) . '/' . e($o['duration_days'] ?? 30) . 'd</span>
        </div>
        <button class="text-blue-600 text-xs edit-upgrade-option-btn" data-id="' . $o['id'] . '">Edit</button>
        <button class="text-red-600 text-xs delete-upgrade-option-btn" data-id="' . $o['id'] . '">Delete</button>
    </div>';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load existing upgrade options
    function loadUpgradeOptions() {
        fetch('/admin/includes/upgrade_options_list.php')
            .then(r => r.text())
            .then(d => document.getElementById('upgrade-options-list').innerHTML = d);
    }
    
    // Add new upgrade option modal
    let addModalOpen = false;
    document.querySelector('.add-upgrade-option-btn').addEventListener('click', function() {
        if (addModalOpen) return;
        addModalOpen = true;
        const fields = {
            name: '',
            display_name: '',
            display_rating: '⭐⭐',
            daily_hash: 1,
            daily_spin: 1,
            referral_requirement: 3,
            requirement_type: 'fixed',
            price: 0,
            duration: 30
        };
        const html = \`
        <div id="upgrade-option-modal" class="fixed inset-0 bg-black/50 backdrop-blur z-50 hidden items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
                <h3 class="font-bold text-lg mb-4">Add Upgrade Option</h3>
                <form id="upgradeOptionForm" class="space-y-4">
                    <input type="hidden" name="upgrade_option_action" value="create">
                    <input type="hidden" name="upgrade_option_id">
                    <div class="grid md:grid-cols-2 gap-3">
                        <div><label class="text-sm text-slate-600">Name</label><input name="upgrade_option_name" type="text" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['name']) . \"></div>
                        <div><label class="text-sm text-slate-600">Display Name</label><input name="upgrade_option_display_name" type="text" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['display_name']) . \"></div>
                        <div><label class="text-sm text-slate-600">Display Rating</label><input name="upgrade_option_display_rating" type="text" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['display_rating']) . \"></div>
                        <div class="md:col-span-2"><label class="text-sm text-slate-600">Daily Hash Allowance</label><input name="upgrade_option_daily_hash" type="number" min="0" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['daily_hash']) . \"></div>
                        <div class="md:col-span-2"><label class="text-sm text-slate-600">Daily Spin Allowance</label><input name="upgrade_option_daily_spin" type="number" min="0" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['daily_spin']) . \"></div>
                        <div><label class="text-sm text-slate-600">Referral Requirement</label><input name="upgrade_option_referral_requirement" type="number" min="0" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['referral_requirement']) . \"></div>
                        <div><label class="text-sm text-slate-600">Requirement Type</label>
                            <select name="upgrade_option_requirement_type" class="mt-1 w-full border rounded-xl px-3 py-2">
                                <option value="fixed" . ($fields['requirement_type'] === 'fixed' ? 'selected' : '') . ">Fixed</option>
                                <option value="threshold" . ($fields['requirement_type'] === 'threshold' ? 'selected' : '') . ">Threshold</option>
                            </select></div>
                        <div><label class="text-sm text-slate-600">Price (USD)</label><input name="upgrade_option_price" type="number" step="0.01" min="0" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['price']) . \"></div>
                        <div><label class="text-sm text-slate-600">Duration (days)</label><input name="upgrade_option_duration" type="number" min="1" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['duration']) . \"></div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" form="upgradeOptionForm" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold">Create</button>
                        <button type="button" onclick="closeUpgradeOptionModal()" class="bg-slate-200 px-4 py-2 rounded text-sm">Cancel</button>
                    </div>
                </form>
            </div>
        </div>\`;
        document.body.insertAdjacentHTML('beforeend', html);
        document.getElementById('upgrade-option-modal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        document.getElementById('upgradeOptionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('/admin/includes/upgrade_option_action.php', {method: 'POST', body: formData})
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        closeUpgradeOptionModal();
                        loadUpgradeOptions();
                        admin_flash('success', data.message);
                    } else {
                        admin_flash('error', data.message || 'Failed');
                    }
                });
        });
    });
    
    function closeUpgradeOptionModal() {
        const modal = document.getElementById('upgrade-option-modal');
        if (modal) {
            modal.remove();
            addModalOpen = false;
            document.body.style.overflow = '';
        }
    }
    
    // Edit upgrade option
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('edit-upgrade-option-btn')) {
            const id = e.target.dataset.id;
            fetch('/admin/includes/upgrade_option_action.php?action=edit&id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return admin_flash('error', data.message || 'Failed to load');
                    const fields = data.fields;
                    const html = \`
                    <div id="upgrade-option-modal" class="fixed inset-0 bg-black/50 backdrop-blur z-50 hidden items-center justify-center p-4">
                        <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
                            <h3 class="font-bold text-lg mb-4">Edit Upgrade Option</h3>
                            <form id="upgradeOptionForm" class="space-y-4">
                                <input type="hidden" name="upgrade_option_action" value="update">
                                <input type="hidden" name="upgrade_option_id" value="\" + data.id + \">
                                <div class="grid md:grid-cols-2 gap-3">
                                    <div><label class="text-sm text-slate-600">Name</label><input name="upgrade_option_name" type="text" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['name']) . \"></div>
                                    <div><label class="text-sm text-slate-600">Display Name</label><input name="upgrade_option_display_name" type="text" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['display_name']) . \"></div>
                                    <div><label class="text-sm text-slate-600">Display Rating</label><input name="upgrade_option_display_rating" type="text" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['display_rating']) . \"></div>
                                    <div class="md:col-span-2"><label class="text-sm text-slate-600">Daily Hash Allowance</label><input name="upgrade_option_daily_hash" type="number" min="0" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['daily_hash']) . \"></div>
                                    <div class="md:col-span-2"><label class="text-sm text-slate-600">Daily Spin Allowance</label><input name="upgrade_option_daily_spin" type="number" min="0" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['daily_spin']) . \"></div>
                                    <div><label class="text-sm text-slate-600">Referral Requirement</label><input name="upgrade_option_referral_requirement" type="number" min="0" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['referral_requirement']) . \"></div>
                                    <div><label class="text-sm text-slate-600">Requirement Type</label>
                                        <select name="upgrade_option_requirement_type" class="mt-1 w-full border rounded-xl px-3 py-2">
                                            <option value="fixed" . ($fields['requirement_type'] === 'fixed' ? 'selected' : '') . ">Fixed</option>
                                            <option value="threshold" . ($fields['requirement_type'] === 'threshold' ? 'selected' : '') . ">Threshold</option>
                                        </select></div>
                                    <div><label class="text-sm text-slate-600">Price (USD)</label><input name="upgrade_option_price" type="number" step="0.01" min="0" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['price']) . \"></div>
                                    <div><label class="text-sm text-slate-600">Duration (days)</label><input name="upgrade_option_duration" type="number" min="1" class="mt-1 w-full border rounded-xl px-3 py-2" value="\" . e($fields['duration']) . \"></div>
                                </div>
                                <div class="flex gap-3">
                                    <button type="submit" form="upgradeOptionForm" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold">Update</button>
                                    <button type="button" onclick="closeUpgradeOptionModal()" class="bg-slate-200 px-4 py-2 rounded text-sm">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>\`;
                    document.body.insertAdjacentHTML('beforeend', html);
                    document.getElementById('upgrade-option-modal').classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    
                    document.getElementById('upgradeOptionForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        fetch('/admin/includes/upgrade_option_action.php', {method: 'POST', body: formData})
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    closeUpgradeOptionModal();
                                    loadUpgradeOptions();
                                    admin_flash('success', data.message);
                                } else {
                                    admin_flash('error', data.message || 'Failed');
                                }
                            });
                    });
                });
        }
        
        // Delete upgrade option
        if (e.target.classList.contains('delete-upgrade-option-btn')) {
            const id = e.target.dataset.id;
            if (confirm('Are you sure you want to delete this upgrade option?')) {
                fetch('/admin/includes/upgrade_option_action.php?action=delete&id=' + id)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            loadUpgradeOptions();
                            admin_flash('success', data.message);
                        } else {
                            admin_flash('error', data.message || 'Failed');
                        }
                    });
            }
        }
    });
    
    // Initial load
    loadUpgradeOptions();
});
</script>
