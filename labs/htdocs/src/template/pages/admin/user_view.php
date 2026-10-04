<?php
require_once __DIR__ . '/../../../../src/load.php';
require_once __DIR__ . '/../../../../src/utils/storage.php';

$email = $_GET['email'] ?? '';
$db = DatabaseConnection::getDefaultDatabase();
$userData = $db->users->findOne(['email' => $email]);

if (!$userData) {
    echo "User not found.";
    return;
}

$user = new User($email);
$gravatarHash = md5(strtolower(trim($email)));
$avatar = $userData['avatar_url'] ?? $userData['avatar'] ?? "https://www.gravatar.com/avatar/{$gravatarHash}?d=identicon&s=200";

$displayName = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
if ($displayName === '') $displayName = $userData['username'] ?? $email;
$username = (string)($userData['username'] ?? '');

/* ---------------------------------------------------------- BSON helpers */
$fmtWhen = function ($v) {
    if ($v === null) return '—';
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return $v->toDateTime()->format('d M Y, H:i');
    if ($v instanceof DateTimeInterface) return $v->format('d M Y, H:i');
    if (is_int($v) || is_float($v)) return date('d M Y, H:i', (int)$v);
    if (!is_scalar($v) && !($v instanceof Stringable)) return '—';
    try { return (new DateTimeImmutable((string)$v))->format('d M Y, H:i'); } catch (Throwable $e) { return '—'; }
};
$ago = function ($ts) {
    if (!$ts) return 'Never';
    $sec = time() - (int)$ts;
    if ($sec < 0) return 'just now';
    if ($sec < 60) return $sec . ' seconds ago';
    if ($sec < 3600) return intdiv($sec, 60) . ' minute' . (intdiv($sec, 60) === 1 ? '' : 's') . ' ago';
    if ($sec < 86400) return intdiv($sec, 3600) . ' hour' . (intdiv($sec, 3600) === 1 ? '' : 's') . ' ago';
    if ($sec < 2592000) return intdiv($sec, 86400) . ' day' . (intdiv($sec, 86400) === 1 ? '' : 's') . ' ago';
    if ($sec < 31536000) return intdiv($sec, 2592000) . ' month' . (intdiv($sec, 2592000) === 1 ? '' : 's') . ' ago';
    return intdiv($sec, 31536000) . ' year' . (intdiv($sec, 31536000) === 1 ? '' : 's') . ' ago';
};
$toTs = function ($v) {
    if ($v === null) return 0;
    if ($v instanceof \MongoDB\BSON\UTCDateTime) return (int)floor($v->toDateTime()->getTimestamp());
    if ($v instanceof DateTimeInterface) return $v->getTimestamp();
    if (is_int($v) || is_float($v)) return (int)$v;
    if (is_string($v) && $v !== '') { $t = strtotime($v); return $t === false ? 0 : $t; }
    return 0;
};

/* ------------------------------------------------------------ Classification */
$role = $userData['role'] ?? 'user';
if (!in_array($role, [Constants::GROUP_SUPERUSER, Constants::GROUP_ADMIN, 'user'], true)) $role = 'user';
$roleLabel = $role === Constants::GROUP_SUPERUSER ? 'Superuser' : ($role === Constants::GROUP_ADMIN ? 'Admin' : 'Member');
$roleColor = $role === 'user' ? 'secondary' : 'danger';

$moderator = !empty($userData['moderator']);

$plans = ['free' => ['Free', 'Free Plan', 'secondary'],
          'default' => ['Default (Paid)', 'Default Plan', 'success'],
          'pro' => ['Pro (Paid)', 'Pro Plan', 'primary']];
$plan = $userData['plan'] ?? 'default';   // unset accounts sit on the platform default tier
if (!isset($plans[$plan])) $plan = 'free';

$state = strtolower((string)($userData['state'] ?? 'active'));
$stateBadge = $state === 'active' ? ['Active', 'success']
            : ($state === 'suspended' ? ['Suspended', 'danger'] : [ucfirst($state ?: 'unknown'), 'secondary']);

/* ------------------------------------------------------------------- Stats */
$statsDoc = $db->user_stats->findOne(['user_email' => $email]);
$zeal = (int)($statsDoc['zeal'] ?? $userData['zeal_stats']['zeal'] ?? 0);
$jolt = (int)($statsDoc['jolt'] ?? $userData['zeal_stats']['jolt'] ?? 0);

$labsTotal = 0; $labsRunning = 0;
try {
    $labsTotal = $db->machine_labs->countDocuments(['email' => $email]);
    $labsRunning = $db->machine_labs->countDocuments(['email' => $email, 'status' => ['$in' => ['running', 'active']]]);
} catch (Throwable $e) { /* non-fatal */ }

$domains = [];
try { $domains = iterator_to_array($db->domains->find(['email' => $email])); } catch (Throwable $e) {}

$quizCount = is_array($userData['quizzes_completed'] ?? null) ? count($userData['quizzes_completed']) : 0;

$lastIpV4 = $userData['ip_address_v4'] ?? null;
$lastIpV6 = $userData['ip_address_v6'] ?? null;
$lastIpAny = $userData['ip_address'] ?? null;
if (empty($lastIpV4) && !empty($lastIpAny) && filter_var($lastIpAny, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    $lastIpV4 = $lastIpAny;
}
if (empty($lastIpV6) && !empty($lastIpAny) && filter_var($lastIpAny, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    $lastIpV6 = $lastIpAny;
}

/* -------------------------------------------------------------- ledger */
$txBreakdown = currency_breakdown($email, 'earned', 'zeal');
$txRecent    = currency_recent($email, 5);
$txCount     = currency_count(['user_email' => $email, 'valid' => true]);
$txTypeDesc  = [];
foreach (currency_groups() as $txGroup => $txTypes) {
    foreach ($txTypes as $txType => $txDesc) $txTypeDesc[$txType] = $txDesc;
}

/* --------------------------------------------------------------- storage */
$storageRow    = null;
try { $storageRow = $db->storage_usage->findOne(['user_email' => $email]); } catch (Throwable $e) {}
$limitOverride = storage_limit_override_of((array)$userData);
$storageLimit  = storage_effective_limit($limitOverride, $plan);
$storageUsed   = (int)($storageRow['bytes'] ?? 0);
$storagePct    = $storageLimit > 0 ? min(999, round($storageUsed / $storageLimit * 100, 1)) : 0;
$storageOver   = $storageUsed > $storageLimit;
$storageCustom = $limitOverride !== null;
$storagePlanCap = storage_plan_limit($plan);
$storageAudited = (int)($storageRow['audited_at'] ?? 0);

$lastLoginTs  = (int)($userData['last_login'] ?? 0);
$createdTs    = (int)($userData['created_at'] ?? 0);
$currentTs    = 0;
$lastActivityTs = 0;
try {
    foreach ((array)($userData['session_tokens'] ?? []) as $tok) {
        $t = max($toTs($tok['last_activity'] ?? null), $toTs($tok['created_at'] ?? null));
        if ($t > $currentTs) $currentTs = $t;
    }
    $act = $db->user_activity->findOne(['email' => $email], ['sort' => ['timestamp' => -1]]);
    if ($act) $lastActivityTs = $toTs($act['timestamp'] ?? ($act['created_at'] ?? null));
} catch (Throwable $e) { /* non-fatal */ }

$tiles = [
    ['Zeal Earned',           number_format($zeal), '🔥', 'bx-fire',        'text-danger',    null],
    ['Jolt Earned',           number_format($jolt), '⚡', 'bx-bolt',        'text-warning',   null],
    ['Jolt Spent',            null, '⚡', 'bx-bolt',  'text-info',     null],
    ['Achievements',          null, '', 'bx-trophy',     'text-warning',   null],
    ['Quizzes Completed',     (string)$quizCount, '', 'bx-help-circle', 'text-primary',  null],
    ['Active / Total Instances', $labsRunning . ' / ' . $labsTotal, '', 'bx-server', 'text-success', null],
    ['Clan',                  null, '', 'bx-group',      'text-info',       null],
    ['Cohorts',               null, '', 'bx-book-reader','text-secondary',  null],
];

$featureToggleUrl = '/api/admin/toggle_feature';
$csrf = htmlspecialchars(Session::csrfToken());
?>

<style>
.adm-tile { transition: border-color .15s, transform .15s; }
.adm-tile:hover { border-color: rgba(var(--cui-primary-rgb, 33,150,243), .4); transform: translateY(-2px); }
.adm-tile .adm-tile-val { font-size: 1.6rem; font-weight: 700; line-height: 1.1; letter-spacing: -.5px; }
.adm-tile .adm-tile-na { color: rgba(var(--cui-body-color-rgb,255,255,255), .35); }
.adm-kv { display: flex; justify-content: space-between; gap: 1rem; padding: .5rem 0; border-bottom: 1px dashed rgba(var(--cui-body-color-rgb,255,255,255), .08); }
.adm-kv:last-child { border-bottom: 0; }
.adm-btn-imp { border: 1px solid rgba(250,204,21,.55) !important; color: #facc15 !important; background: rgba(250,204,21,.06); }
.adm-btn-imp:hover { background: rgba(250,204,21,.16); }
.adm-btn-xfer { border: 1px solid rgba(244,114,182,.55) !important; color: #f472b6 !important; background: rgba(244,114,182,.06); }
.adm-btn-xfer:hover { background: rgba(244,114,182,.16); }
.adm-btn-stor { border: 1px solid rgba(77,171,247,.55) !important; color: #4dabf7 !important; background: rgba(77,171,247,.06); }
.adm-btn-stor:hover { background: rgba(77,171,247,.16); }
.adm-spct { height: 7px; border-radius: 999px; background: rgba(0,0,0,.35); overflow: hidden; }
.adm-spct > i { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg,#ffd200,#ff8a00); }
.adm-spct.over > i { background: linear-gradient(90deg,#ff5f6d,#ff8a5c); }
</style>

<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left:1rem;margin-right:1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <img class="avatar-img" src="<?= htmlspecialchars($avatar) ?>" alt="">
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <a href="/admin/users" class="small text-body-secondary d-inline-flex align-items-center gap-1 text-decoration-none">
                            <i class='bx bx-left-arrow-alt'></i> Back to Users
                        </a>
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title"><?= htmlspecialchars($displayName) ?></h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="text-secondary opacity-75"><?= htmlspecialchars($email) ?></span>
                            <span class="badge rounded-pill text-bg-<?= $roleColor ?>"><?= $roleLabel ?></span>
                            <?php if ($moderator): ?><span class="badge rounded-pill text-bg-info"><i class='bx bx-shield-quarter me-1'></i>Moderator</span><?php endif; ?>
                            <span class="badge rounded-pill text-bg-<?= $plans[$plan][2] ?>"><?= $plans[$plan][1] ?></span>
                            <span class="badge rounded-pill text-bg-<?= $stateBadge[1] ?>"><?= $stateBadge[0] ?></span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="/admin/storage" class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-hdd me-1'></i>Storage
                    </a>
                    <a href="/home" data-no-boost="true"
                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 py-4">

    <div class="row g-4 mb-4">
        <!-- ========================= Identity card ========================= -->
        <div class="col-lg-3 col-md-4">
            <div class="card border-0 rounded-4 blur shadow-sm h-100">
                <div class="card-body text-center py-4">
                    <img src="<?= htmlspecialchars($avatar) ?>" alt=""
                         class="rounded-circle mb-3 shadow-lg border border-body-secondary border-opacity-10"
                         style="width:96px;height:96px;object-fit:cover;">
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($displayName) ?></h5>
                    <div class="small text-body-secondary mb-3">@<?= htmlspecialchars($username) ?></div>

                    <!-- Role -->
                    <div class="d-flex gap-2 mb-2">
                        <select id="uvRole" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                            <option value="user"       <?= $role === 'user' ? 'selected' : '' ?>>Member</option>
                            <option value="admin"      <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="superuser"  <?= $role === 'superuser' ? 'selected' : '' ?>>Superuser</option>
                        </select>
                        <button type="button" id="uvRoleSave" class="btn btn-sm btn-warning fw-semibold text-nowrap">Save</button>
                    </div>

                    <!-- Platform moderator -->
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="uvModerator" <?= $moderator ? 'checked' : '' ?>>
                        </div>
                        <label for="uvModerator" class="small fw-semibold mb-0">Platform moderator</label>
                    </div>

                    <!-- Plan -->
                    <div class="d-flex gap-2 mb-3">
                        <select id="uvPlan" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                            <?php foreach ($plans as $key => $meta): ?>
                                <option value="<?= $key ?>" <?= $plan === $key ? 'selected' : '' ?>><?= $meta[0] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="uvPlanSave" class="btn btn-sm btn-warning fw-semibold text-nowrap">Save</button>
                    </div>

                    <!-- Storage -->
                    <div class="mb-3 text-start">
                        <div class="d-flex justify-content-between align-items-center small mb-1">
                            <span class="text-body-secondary"><i class='bx bx-hdd me-1'></i>Storage</span>
                            <span class="fw-semibold text-nowrap"><?= storage_format_bytes($storageUsed) ?> / <?= storage_format_bytes($storageLimit) ?></span>
                        </div>
                        <div class="adm-spct<?= $storageOver ? ' over' : '' ?>"><i style="width:<?= (int)min(100, $storagePct) ?>%"></i></div>
                        <div class="d-flex justify-content-between align-items-center mt-1" style="font-size:.68rem;">
                            <span class="<?= $storageOver ? 'text-danger' : 'text-body-secondary' ?>"><?= number_format($storagePct, 1) ?>% used</span>
                            <span class="text-body-secondary opacity-75">
                                <?= $storageCustom ? 'Custom cap' : htmlspecialchars(storage_plan_label($plan) . ' plan') ?>
                                <?php if ($storageAudited): ?>&middot; <?= htmlspecialchars($ago($storageAudited)) ?><?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="button" id="uvStorage" class="btn btn-sm rounded-pill fw-semibold adm-btn-stor">
                            <i class='bx bx-edit-alt me-1'></i>Storage Limit
                        </button>
                        <button type="button" id="uvImpersonate" class="btn btn-sm rounded-pill fw-semibold adm-btn-imp">
                            <i class='bx bx-user-circle me-1'></i>Impersonate User
                        </button>
                        <button type="button" id="uvTransfer" class="btn btn-sm rounded-pill fw-semibold adm-btn-xfer">
                            <i class='bx bx-transfer me-1'></i>Transfer Entitlements
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================ Tiles ============================ -->
        <div class="col-lg-9 col-md-8">
            <div class="row g-3">
                <?php foreach ($tiles as $t):
                    [$label, $value, $emoji, $icon, $color, $hint] = $t;
                    $isNa = $value === null;
                ?>
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="adm-tile rounded-4 p-3 h-100 text-center">
                        <div class="adm-tile-val <?= $isNa ? 'adm-tile-na' : '' ?> <?= $isNa ? '' : $color ?>"
                             <?= $isNa ? 'title="Not tracked yet"' : '' ?>>
                            <?= $isNa ? '—' : htmlspecialchars($value) ?><?= $emoji ? ' <span class="fs-5">' . $emoji . '</span>' : '' ?>
                        </div>
                        <div class="small text-body-secondary mt-1"><?= $label ?></div>
                        <?php if ($isNa): ?><div class="small text-body-secondary opacity-50" style="font-size:.68rem;">not tracked yet</div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card border-0 rounded-4 blur shadow-sm mt-3">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class='bx bx-slider-alt text-success me-2'></i>Adjust currency</h5>
                    <a class="small text-body-secondary" href="/admin/transactions?user=<?= urlencode($email) ?>" title="Transaction Monitor">Monitor<i class='bx bx-link-external ms-1'></i></a>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-body-secondary">Zeal 🔥 <span id="uvZealBal" class="fw-bold text-body"><?= number_format($zeal) ?></span></span>
                        <span class="text-body-secondary">Jolt ⚡ <span id="uvJoltBal" class="fw-bold text-body"><?= number_format($jolt) ?></span></span>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small text-body-secondary mb-1" for="uvAdjCurrency">Currency</label>
                            <select id="uvAdjCurrency" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                                <option value="zeal">Zeal 🔥</option>
                                <option value="jolt">Jolt ⚡</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-body-secondary mb-1" for="uvAdjDirection">Action</label>
                            <select id="uvAdjDirection" class="form-select form-select-sm bg-transparent border-secondary border-opacity-25">
                                <option value="add">Credit (+)</option>
                                <option value="subtract">Debit (−)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-body-secondary mb-1" for="uvAdjAmount">Amount</label>
                        <input type="number" id="uvAdjAmount" class="form-control form-control-sm bg-transparent border-secondary border-opacity-25"
                               min="1" max="1000000" step="1" value="1">
                    </div>

                    <div class="mb-2">
                        <label class="form-label small text-body-secondary mb-1" for="uvAdjReason">Reason <span class="text-danger">*</span></label>
                        <textarea id="uvAdjReason" rows="2" maxlength="300"
                                  class="form-control form-control-sm bg-transparent border-secondary border-opacity-25"
                                  placeholder="Why is this balance being adjusted?"></textarea>
                    </div>

                    <button type="button" id="uvAdjustSave" class="btn btn-sm btn-warning fw-semibold rounded-pill w-100">
                        Apply adjustment
                    </button>
                    <div class="form-text text-start">Written to the ledger with your reason and the audit log.</div>

                    <?php if ($txRecent): ?>
                    <ul class="list-unstyled small mt-2 mb-0 border-top border-body-secondary border-opacity-10 pt-2">
                        <?php foreach ($txRecent as $txRow): $txCur = (string)($txRow['currency'] ?? 'zeal'); ?>
                        <li class="d-flex justify-content-between gap-2 py-1 border-bottom border-body-secondary border-opacity-5">
                            <span class="text-body-secondary text-truncate" title="<?= htmlspecialchars((string)($txRow['description'] ?? '')) ?>">
                                <?= (string)($txRow['direction'] ?? '') === 'earned' ? '+' : '−' ?>
                                <?= number_format((int)($txRow['amount'] ?? 0)) ?> <?= htmlspecialchars(currency_label($txCur)) ?>
                            </span>
                            <span class="text-nowrap text-body-secondary opacity-75"><?= htmlspecialchars($ago((int)($txRow['created_at'] ?? 0))) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="small d-inline-block mt-1" href="/admin/transactions?user=<?= urlencode($email) ?>">All <?= number_format($txCount) ?> transactions</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- ========================= User details ========================= -->
        <div class="col-lg-4">
            <div class="card border-0 rounded-4 blur shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3">
                    <h5 class="mb-0 fw-bold"><i class='bx bx-id-card me-2 text-primary'></i>User Details</h5>
                </div>
                <div class="card-body py-2">
                    <div class="adm-kv"><span class="text-body-secondary small">Email</span><span class="small fw-semibold text-break"><?= htmlspecialchars($email) ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Username</span><span class="small fw-semibold"><?= htmlspecialchars($username) ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">User ID</span><code class="small"><?= htmlspecialchars((string)($userData['user_id'] ?? '—')) ?></code></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Created</span><span class="small fw-semibold"><?= htmlspecialchars($ago($createdTs)) ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Last Sign In</span><span class="small fw-semibold"><?= htmlspecialchars($ago($lastLoginTs)) ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Current Sign In</span><span class="small fw-semibold"><?= htmlspecialchars($currentTs ? $ago($currentTs) : '—') ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Last Activity</span><span class="small fw-semibold"><?= htmlspecialchars($lastActivityTs ? $ago($lastActivityTs) : '—') ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Plan</span><span class="badge rounded-pill text-bg-<?= $plans[$plan][2] ?>"><?= $plans[$plan][1] ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">2FA</span><span class="small fw-semibold"><?= !empty($userData['two_factor_enabled']) ? 'Enabled' : 'Disabled' ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Profile</span><?php if ($username): ?><a class="small fw-semibold" href="/<?= htmlspecialchars($username) ?>" target="_blank">View Public Profile <i class='bx bx-link-external'></i></a><?php else: ?><span class="small">—</span><?php endif; ?></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Transactions</span>
                        <a class="small fw-semibold" href="/admin/transactions?user=<?= urlencode($email) ?>">
                            <?= $txCount ? number_format($txCount) . ' recorded' : 'View monitor' ?> <i class='bx bx-link-external'></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================== Zeal earnings breakdown =================== -->
        <div class="col-lg-8">
            <div class="card border-0 rounded-4 blur shadow-sm h-100">
                <div class="card-header bg-transparent border-bottom border-body-secondary border-opacity-10 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class='bx bx-fire me-2 text-danger'></i>Zeal Earnings Breakdown</h5>
                    <span class="small text-body-secondary"><?= number_format($zeal) ?> 🔥 total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead class="table-dark opacity-75">
                                <tr>
                                    <th class="ps-4">Type</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end pe-4">Total 🔥</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$txBreakdown): ?>
                                <tr>
                                    <td colspan="3" class="ps-4 pe-4 text-center text-body-secondary py-5">
                                        <i class='bx bx-data fs-1 d-block mb-2 opacity-50'></i>
                                        No earnings ledger is recorded for this account yet —
                                        only the running totals above are stored.
                                    </td>
                                </tr>
                                <?php else:
                                    $txTotalZeal = 0; $txTotalCount = 0;
                                    foreach ($txBreakdown as $txRow) { $txTotalZeal += $txRow['total']; $txTotalCount += $txRow['count']; }
                                    foreach ($txBreakdown as $txRow): ?>
                                <tr>
                                    <td class="ps-4 align-top">
                                        <span class="fw-semibold"><?= htmlspecialchars($txRow['type']) ?></span>
                                        <div class="small text-body-secondary"><?= htmlspecialchars($txTypeDesc[$txRow['type']] ?? '') ?></div>
                                    </td>
                                    <td class="text-end align-top"><?= number_format($txRow['count']) ?></td>
                                    <td class="text-end pe-4 align-top fw-semibold text-danger"><?= number_format($txRow['total']) ?> 🔥</td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="border-top border-body-secondary border-opacity-10">
                                    <td class="ps-4 fw-semibold">Total</td>
                                    <td class="text-end fw-semibold"><?= number_format($txTotalCount) ?></td>
                                    <td class="text-end pe-4 fw-bold text-danger"><?= number_format($txTotalZeal) ?> 🔥</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== Existing admin options ==================== -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 rounded-4 blur shadow-sm h-100">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3">
                    <h5 class="mb-0 fw-bold"><i class='bx bx-slider-alt text-success me-2'></i>Feature Overrides</h5>
                </div>
                <div class="card-body">
                    <p class="text-body-secondary small mb-4">Override specific lab features exclusively for this user. These settings bypass standard lab restrictions.</p>
                    <div class="list-group list-group-flush bg-transparent">
                        <?php
                        $userFeaturesDoc = $user->getLabFeatures();
                        $userFeatures = ($userFeaturesDoc && is_object($userFeaturesDoc) && method_exists($userFeaturesDoc, 'getArrayCopy')) ? $userFeaturesDoc->getArrayCopy() : ((array)$userFeaturesDoc ?: []);
                        $featuresList = [
                            'http_proxies'   => 'HTTP Proxies (Reverse Proxy Domains)',
                            'expose_web'     => 'Expose Web Publicly',
                            'startup_script' => 'Custom Startup Bash Scripts',
                            'always_on'      => 'Always On (No Auto-Expiration)'
                        ];
                        foreach ($featuresList as $key => $label):
                            $isChecked = !empty($userFeatures[$key]);
                        ?>
                        <div class="list-group-item bg-transparent border-body-secondary border-opacity-10 d-flex justify-content-between align-items-center py-3 px-0">
                            <div>
                                <h6 class="mb-1 fw-semibold"><?= $label ?></h6>
                                <small class="text-body-secondary font-monospace"><?= $key ?></small>
                            </div>
                            <div class="form-check form-switch fs-4 mb-0">
                                <input class="form-check-input pointer user-feature-toggle" type="checkbox" role="switch" data-feature="<?= $key ?>" <?= $isChecked ? 'checked' : '' ?>>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 rounded-4 blur shadow-sm h-100">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3">
                    <h5 class="mb-0 fw-bold"><i class='bx bx-detail text-info me-2'></i>Security</h5>
                </div>
                <div class="card-body">
                    <div class="adm-kv"><span class="text-body-secondary small">Verified</span>
                        <span class="badge rounded-pill text-bg-<?= !empty($userData['is_verified']) ? 'success' : 'secondary' ?>"><?= !empty($userData['is_verified']) ? 'Yes' : 'No' ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">2FA</span>
                        <span class="badge rounded-pill text-bg-<?= !empty($userData['two_factor_enabled']) ? 'success' : 'secondary' ?>"><?= !empty($userData['two_factor_enabled']) ? 'Enabled' : 'Disabled' ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Failed logins</span><span class="small fw-semibold"><?= (int)($userData['failed_login_attempts'] ?? 0) ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Locked until</span><span class="small fw-semibold"><?= !empty($userData['locked_until']) && $userData['locked_until'] > time() ? $fmtWhen($userData['locked_until']) : 'Not locked' ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Last IPv4</span><code class="small"><?= htmlspecialchars((string)($lastIpV4 ?: '—')) ?></code></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Last IPv6</span><code class="small"><?= htmlspecialchars((string)($lastIpV6 ?: '—')) ?></code></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Active sessions</span><span class="small fw-semibold"><?= count((array)($userData['session_tokens'] ?? [])) ?></span></div>
                    <div class="adm-kv"><span class="text-body-secondary small">Last sign-in</span><span class="small fw-semibold"><?= htmlspecialchars($fmtWhen($lastLoginTs ?: null)) ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================== Labs & domains ====================== -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 rounded-4 blur shadow-sm h-100">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class='bx bx-server text-primary me-2'></i>Active Labs</h5>
                    <span class="badge bg-primary rounded-pill"><?= $labsTotal ?></span>
                </div>
                <div class="card-body p-0" style="max-height:400px;overflow-y:auto;">
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php
                        $labs = [];
                        try { $labs = iterator_to_array($db->machine_labs->find(['email' => $email])); } catch (Throwable $e) {}
                        if (!$labs): ?>
                            <li class="list-group-item bg-transparent text-body-secondary py-4 text-center border-0">No active labs found.</li>
                        <?php else: foreach ($labs as $lab): ?>
                            <li class="list-group-item bg-transparent border-body-secondary border-opacity-10 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($lab['lab_type'] ?? 'Unknown') ?></h6>
                                        <span class="text-body-secondary small font-monospace"><?= htmlspecialchars(substr((string)($lab['instance_hash'] ?? ''), 0, 16)) ?>…</span>
                                    </div>
                                    <?php $st = strtolower((string)($lab['status'] ?? '')); ?>
                                    <span class="badge rounded-pill bg-<?= in_array($st, ['running', 'active'], true) ? 'success' : (in_array($st, ['error', 'failed'], true) ? 'danger' : 'secondary') ?>">
                                        <?= htmlspecialchars($lab['status'] ?? 'unknown') ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 rounded-4 blur shadow-sm h-100">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class='bx bx-globe text-info me-2'></i>Custom Domains</h5>
                    <span class="badge bg-info text-dark rounded-pill"><?= count($domains) ?></span>
                </div>
                <div class="card-body p-0" style="max-height:400px;overflow-y:auto;">
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php if (!$domains): ?>
                            <li class="list-group-item bg-transparent text-body-secondary py-4 text-center border-0">No domains configured.</li>
                        <?php else: foreach ($domains as $domain): ?>
                            <li class="list-group-item bg-transparent border-body-secondary border-opacity-10 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex flex-column">
                                        <a href="http://<?= htmlspecialchars($domain['domain']) ?>" target="_blank" class="text-decoration-none fw-bold mb-1">
                                            <?= htmlspecialchars($domain['domain']) ?> <i class='bx bx-link-external small opacity-50'></i>
                                        </a>
                                        <span class="text-body-secondary small">Port: <?= htmlspecialchars($domain['port'] ?? '—') ?> | Target: <?= htmlspecialchars($domain['container_name'] ?? 'N/A') ?></span>
                                    </div>
                                    <span class="badge rounded-pill bg-body-secondary">Custom</span>
                                </div>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================== Transfer modal ====================== -->
<div class="modal fade" id="uvTransferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 blur shadow-lg">
            <div class="modal-header border-bottom border-body-secondary border-opacity-10">
                <h5 class="modal-title fw-bold"><i class='bx bx-transfer me-2 text-pink'></i>Transfer Entitlements</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-body-secondary mb-1">From</label>
                    <input type="text" class="form-control bg-transparent border-secondary border-opacity-25" value="<?= htmlspecialchars($email) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-body-secondary mb-1" for="xferTo">To (email)</label>
                    <input type="email" id="xferTo" class="form-control bg-transparent border-secondary border-opacity-25" placeholder="recipient@example.com" autocomplete="off">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small text-body-secondary mb-1" for="xferType">Type</label>
                        <select id="xferType" class="form-select bg-transparent border-secondary border-opacity-25">
                            <option value="zeal">Zeal 🔥</option>
                            <option value="jolt">Jolt ⚡</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-body-secondary mb-1" for="xferAmount">Amount</label>
                        <input type="number" id="xferAmount" min="1" step="1" value="1" class="form-control bg-transparent border-secondary border-opacity-25">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small text-body-secondary mb-1" for="xferConfirm">Type <code id="xferPhrase"></code> to confirm</label>
                    <input type="text" id="xferConfirm" class="form-control bg-transparent border-secondary border-opacity-25" placeholder="TRANSFER someone@example.com" autocomplete="off">
                </div>
                <div class="form-text">Moves the balance between the two accounts and is written to the audit log.</div>
            </div>
            <div class="modal-footer border-top border-body-secondary border-opacity-10">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-coreui-dismiss="modal">Cancel</button>
                <button type="button" id="xferGo" class="btn btn-sm btn-danger fw-semibold rounded-pill px-3">Transfer</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================ Storage limit modal ============================ -->
<div class="modal fade" id="uvStorageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 blur shadow-lg">
            <div class="modal-header border-bottom border-body-secondary border-opacity-10">
                <h5 class="modal-title fw-bold"><i class='bx bx-hdd me-2 text-info'></i>Storage limit</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small text-body-secondary mb-1" for="slUser">Tenant</label>
                    <input type="text" id="slUser" class="form-control bg-transparent border-secondary border-opacity-25" value="<?= htmlspecialchars($email) ?>" disabled>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small text-body-secondary mb-1" for="slUsedU">Used now</label>
                        <input type="text" id="slUsedU" class="form-control bg-transparent border-secondary border-opacity-25" value="<?= storage_format_bytes($storageUsed) ?>" disabled>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-body-secondary mb-1" for="slCurrentU">Current cap</label>
                        <input type="text" id="slCurrentU" class="form-control bg-transparent border-secondary border-opacity-25"
                               value="<?= ($storageCustom ? 'Custom · ' : '') . storage_format_bytes($storageLimit) ?>" disabled>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-body-secondary mb-1">Plan default</label>
                    <input type="text" class="form-control bg-transparent border-secondary border-opacity-25"
                           value="<?= htmlspecialchars(storage_plan_label($plan)) ?> — <?= storage_format_bytes($storagePlanCap) ?>" disabled>
                </div>
                <div class="mb-1">
                    <label class="form-label small text-body-secondary mb-1" for="slGbU">New limit (GB)</label>
                    <div class="input-group">
                        <input type="number" id="slGbU" min="0.1" step="0.1"
                               value="<?= $storageCustom ? htmlspecialchars(rtrim(rtrim(number_format($storageLimit / 1073741824, 3), '0'), '.')) : '' ?>"
                               class="form-control bg-transparent border-secondary border-opacity-25">
                        <span class="input-group-text border-secondary border-opacity-25 bg-transparent text-body-secondary">GB</span>
                    </div>
                    <div class="form-text">Caps apply from the next audit. Use <strong>Reset to plan</strong> to clear a custom cap.</div>
                </div>
            </div>
            <div class="modal-footer border-top border-body-secondary border-opacity-10 d-flex justify-content-between">
                <button type="button" id="slResetU" class="btn btn-sm btn-outline-secondary rounded-pill">Reset to plan</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-coreui-dismiss="modal">Cancel</button>
                    <button type="button" id="slSaveU" class="btn btn-sm btn-warning fw-semibold rounded-pill px-3">Save limit</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const EMAIL = <?= json_encode($email) ?>;
    const CSRF  = <?= json_encode(Session::csrfToken()) ?>;
    const notify = (m, t, ty) => { if (window.TomNotify) TomNotify.show(m, t, ty || 'success', 4000); };

    async function post(url, payload) {
        const body = new URLSearchParams(payload);
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        return res.json();
    }

    /* ---------------------------------------------------- feature overrides */
    document.querySelectorAll('.user-feature-toggle').forEach(toggle => {
        toggle.addEventListener('change', async function () {
            const feature = this.getAttribute('data-feature');
            const state = this.checked;
            try {
                const fd = new FormData();
                fd.append('scope', 'user');
                fd.append('email', EMAIL);
                fd.append('feature', feature);
                fd.append('state', state);
                const response = await fetch('/api/admin/toggle_feature', { method: 'POST', body: fd });
                const res = await response.json();
                if (res.status === 'success') {
                    notify(feature + ' is now ' + (state ? 'ENABLED' : 'DISABLED') + ' for this user.', 'Override Saved', 'success');
                } else {
                    notify(res.error || 'Failed to update', 'Error', 'error');
                    this.checked = !state;
                }
            } catch (e) {
                notify('Network error', 'Error', 'error');
                this.checked = !state;
            }
        });
    });

    /* ---------------------------------------------------------------- role */
    document.getElementById('uvRoleSave')?.addEventListener('click', async function () {
        const role = document.getElementById('uvRole').value;
        this.disabled = true;
        try {
            const res = await post('/api/admin/set_role', { email: EMAIL, role: role });
            if (res.status === 'success') { notify('Role updated to ' + role + '.', 'Saved'); setTimeout(() => location.reload(), 600); }
            else notify(res.error || 'Failed', 'Error', 'error');
        } catch (e) { notify('Network error', 'Error', 'error'); }
        this.disabled = false;
    });

    /* ------------------------------------------------------------ moderator */
    document.getElementById('uvModerator')?.addEventListener('change', async function () {
        const state = this.checked;
        try {
            const res = await post('/api/admin/set_moderator', { email: EMAIL, state: state });
            if (res.status === 'success') { notify('Moderator flag ' + (state ? 'enabled' : 'disabled') + '.', 'Saved'); setTimeout(() => location.reload(), 600); }
            else { notify(res.error || 'Failed', 'Error', 'error'); this.checked = !state; }
        } catch (e) { notify('Network error', 'Error', 'error'); this.checked = !state; }
    });

    /* ------------------------------------------------------------ currency */
    document.getElementById('uvAdjustSave')?.addEventListener('click', async function () {
        const currency  = document.getElementById('uvAdjCurrency').value;
        const direction = document.getElementById('uvAdjDirection').value;
        const amount    = parseInt(document.getElementById('uvAdjAmount').value, 10);
        const reason    = document.getElementById('uvAdjReason').value.trim();

        if (!amount || amount < 1) { notify('Amount must be at least 1', 'Error', 'error'); return; }
        if (reason.length < 3) { notify('A reason is required (3 characters minimum)', 'Error', 'error'); return; }

        this.disabled = true;
        try {
            const res = await post('/api/admin/adjust_currency', {
                email: EMAIL, currency: currency, direction: direction, amount: amount, reason: reason
            });
            if (res.status === 'success') {
                const unit = currency === 'jolt' ? 'Jolt' : 'Zeal';
                notify((direction === 'add' ? 'Credited ' : 'Debited ') + amount + ' ' + unit +
                       ' — new balance ' + res.balance, 'Adjustment applied', 'success');
                document.getElementById('uvAdjReason').value = '';
                setTimeout(() => location.reload(), 700);
            } else {
                notify(res.error || 'Failed', 'Error', 'error');
            }
        } catch (e) { notify('Network error', 'Error', 'error'); }
        this.disabled = false;
    });

    /* ---------------------------------------------------------------- plan */
    document.getElementById('uvPlanSave')?.addEventListener('click', async function () {
        const plan = document.getElementById('uvPlan').value;
        this.disabled = true;
        try {
            const res = await post('/api/admin/set_plan', { email: EMAIL, plan: plan });
            if (res.status === 'success') { notify('Plan updated to ' + plan + '.', 'Saved'); setTimeout(() => location.reload(), 600); }
            else notify(res.error || 'Failed', 'Error', 'error');
        } catch (e) { notify('Network error', 'Error', 'error'); }
        this.disabled = false;
    });

    /* ------------------------------------------------------------ storage */
    const storModal = document.getElementById('uvStorageModal');
    document.getElementById('uvStorage')?.addEventListener('click', () => {
        new coreui.Modal(storModal).show();
    });

    document.getElementById('slSaveU')?.addEventListener('click', async function () {
        const gb = parseFloat(document.getElementById('slGbU').value);
        if (!(gb > 0)) { notify('Enter a limit greater than 0', 'Error', 'error'); return; }
        this.disabled = true;
        try {
            const res = await post('/api/admin/set_storage_limit', { email: EMAIL, limit_gb: gb });
            if (res.status === 'success') {
                notify('Storage limit set to ' + res.label, 'Limit saved');
                coreui.Modal.getInstance(storModal)?.hide();
                setTimeout(() => location.reload(), 700);
            } else notify(res.error || 'Failed', 'Error', 'error');
        } catch (e) { notify('Network error', 'Error', 'error'); }
        this.disabled = false;
    });

    document.getElementById('slResetU')?.addEventListener('click', async function () {
        this.disabled = true;
        try {
            const res = await post('/api/admin/set_storage_limit', { email: EMAIL, reset: '1' });
            if (res.status === 'success') {
                notify('Storage limit reset to the plan default', 'Limit reset');
                coreui.Modal.getInstance(storModal)?.hide();
                setTimeout(() => location.reload(), 700);
            } else notify(res.error || 'Failed', 'Error', 'error');
        } catch (e) { notify('Network error', 'Error', 'error'); }
        this.disabled = false;
    });

    /* --------------------------------------------------------- impersonate */
    document.getElementById('uvImpersonate')?.addEventListener('click', async function () {
        if (!confirm('Impersonate ' + <?= json_encode($username ?: $email) ?> + '?\n\nYou will browse the site as this user until you exit.')) return;
        this.disabled = true;
        try {
            const res = await post('/api/admin/impersonate', { email: EMAIL });
            if (res.status === 'success') {
                notify('Now viewing as ' + res.as, 'Impersonating', 'warning');
                setTimeout(() => { window.location.href = res.redirect || '/home'; }, 400);
            } else {
                notify(res.error || 'Failed', 'Error', 'error');
                this.disabled = false;
            }
        } catch (e) { notify('Network error', 'Error', 'error'); this.disabled = false; }
    });

    /* ------------------------------------------------------------ transfer */
    const modalEl = document.getElementById('uvTransferModal');
    const toInput = document.getElementById('xferTo');
    const phrase  = document.getElementById('xferPhrase');

    function syncPhrase() {
        phrase.textContent = 'TRANSFER ' + (toInput.value.trim() || '<email>');
    }
    toInput?.addEventListener('input', syncPhrase);
    syncPhrase();

    document.getElementById('uvTransfer')?.addEventListener('click', () => {
        new coreui.Modal(modalEl).show();
    });

    document.getElementById('xferGo')?.addEventListener('click', async function () {
        const to = toInput.value.trim();
        const type = document.getElementById('xferType').value;
        const amount = parseInt(document.getElementById('xferAmount').value, 10);
        const confirmText = document.getElementById('xferConfirm').value.trim();

        if (!to) { notify('Recipient email is required', 'Error', 'error'); return; }
        if (!amount || amount < 1) { notify('Amount must be at least 1', 'Error', 'error'); return; }
        if (confirmText !== 'TRANSFER ' + to) { notify('Type exactly: TRANSFER ' + to, 'Confirmation required', 'warning'); return; }

        this.disabled = true;
        try {
            const res = await post('/api/admin/transfer_entitlements', {
                from_email: EMAIL, to_email: to, type: type, amount: amount, confirm: confirmText
            });
            if (res.status === 'success') {
                notify('Transferred ' + res.amount + ' ' + res.type + ' to ' + res.to.email, 'Transfer complete');
                coreui.Modal.getInstance(modalEl)?.hide();
                setTimeout(() => location.reload(), 700);
            } else {
                notify(res.error || 'Transfer failed', 'Error', 'error');
            }
        } catch (e) { notify('Network error', 'Error', 'error'); }
        this.disabled = false;
    });
})();
</script>
