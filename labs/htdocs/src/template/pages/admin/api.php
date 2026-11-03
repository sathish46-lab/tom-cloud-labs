<?php
require_once __DIR__ . '/../../../../src/load.php';
$db = DatabaseConnection::getDefaultDatabase();

$globalDoc = $db->global_settings->findOne(['_id' => 'lab_features']);
$globalSettings = ($globalDoc && is_object($globalDoc) && method_exists($globalDoc, 'getArrayCopy')) ? $globalDoc->getArrayCopy() : ((array)$globalDoc ?: []);

$masterDoc = $db->global_settings->findOne(['_id' => 'master_switches']);
$masterSwitches = ($masterDoc && is_object($masterDoc) && method_exists($masterDoc, 'getArrayCopy')) ? $masterDoc->getArrayCopy() : ((array)$masterDoc ?: []);

$matrixDoc = $db->global_settings->findOne(['_id' => 'lab_feature_matrix']);
$labMatrix = ($matrixDoc && is_object($matrixDoc) && method_exists($matrixDoc, 'getArrayCopy')) ? $matrixDoc->getArrayCopy() : ((array)$matrixDoc ?: []);

$knownLabs = [
    'essentials' => 'Essentials',
    'minio' => 'MinIO',
    'n8n' => 'n8n',
    'docker_lab' => 'Docker Lab'
];
$featuresList = [
    'always_on' => 'Always On',
    'http_proxies' => 'HTTP Proxies',
    'startup_script' => 'Startup Script',
    'expose_web' => 'Expose Web',
    'mcp' => 'MCP Inspector',
    'account' => 'Account Service'
];

// MCP-specific settings
$mcpDoc = $db->global_settings->findOne(['_id' => 'mcp_settings']);
$mcpSettings = ($mcpDoc && is_object($mcpDoc) && method_exists($mcpDoc, 'getArrayCopy')) ? $mcpDoc->getArrayCopy() : ((array)$mcpDoc ?: []);

// News automation settings
$newsDoc = $db->global_settings->findOne(['_id' => 'news_settings']);
$newsSettings = ($newsDoc && is_object($newsDoc) && method_exists($newsDoc, 'getArrayCopy')) ? $newsDoc->getArrayCopy() : ((array)$newsDoc ?: []);

// Latest fetch log for stats (inserted/updated counts)
$newsLatestLog = null;
try {
    $newsLatestLog = $db->news_fetch_logs->findOne([], ['sort' => ['completed_at' => -1]]);
} catch (Throwable $e) {
    $newsLatestLog = null;
}
$newsLastInserted = $newsLatestLog ? (int)($newsLatestLog['total_inserted'] ?? 0) : 0;
$newsLastUpdated  = $newsLatestLog ? (int)($newsLatestLog['total_updated'] ?? 0) : 0;

// Count today's news articles (IST day boundary)
$newsTodayCount = 0;
try {
    $istRange = \TimeHelper::dayRange(0);
    $newsTodayCount = (int) $db->news_articles->countDocuments([
        'deleted_at' => null,
        'published_at' => [
            '$gte' => new \MongoDB\BSON\UTCDateTime((int) $istRange['start']->format('Uv')),
            '$lt'  => new \MongoDB\BSON\UTCDateTime((int) $istRange['end']->format('Uv')),
        ],
    ]);
} catch (Throwable $e) {
    $newsTodayCount = 0;
}
?>
<style>
.admin-card { transition: all 0.2s; }
.admin-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(0,0,0,0.28); }
.lab-matrix-card { transition: all 0.2s; cursor: pointer; }
.lab-matrix-card:hover { transform: translateY(-3px); box-shadow: 0 8px 22px rgba(0,0,0,0.28); }
.news-next-run { color: #fd7e14; font-weight: 600; white-space: nowrap; }
</style>

<div class="blur banner mb-3 rounded-0 border-bottom border-secondary border-opacity-10">
    <div class="card-body p-0" style="margin-left: 1rem; margin-right: 1rem;">
        <div class="container-fluid pt-3 pb-1">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0">
                        <div class="avatar lab-header-avatar">
                            <div class="avatar-img d-flex align-items-center justify-content-center bg-dark bg-opacity-25 rounded-circle p-2">
                                <i class='bx bx-crown'></i>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <h3 class="fw-bold mb-0 ls-tight lab-header-title">Modules</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <div class="d-flex align-items-center text-secondary">
                                <span class="me-1 opacity-75">Switch a module off to hide its features and its admin sidebar group</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="/home" data-no-boost="true"
                       class="btn btn-sm btn-outline-secondary border-secondary border-opacity-25 rounded-pill px-3 text-nowrap">
                        <i class='bx bx-left-arrow-circle me-1'></i>Back to Labs
                    </a>
                </div>
            </div>
        
        </div>
    </div>
</div>

<div class="container-fluid px-4 pt-3">

    <div class="row">
        <!-- Master Kill Switches Control -->
        <div class="col-xl-6 mb-4">
            <div class="card border-0 rounded-4 blur shadow-sm admin-card h-100">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3">
                    <h5 class="mb-0 fw-bold text-danger"><i class='bx bx-power-off me-2'></i>Master Kill Switches</h5>
                    <small class="text-body-secondary">Disabling overrides everything else.</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($featuresList as $key => $label): ?>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 rounded-4 h-100 adm-stat">
                                <div>
                                    <h6 class="mb-1 fw-semibold"><?= $label ?></h6>
                                </div>
                                <div class="form-check form-switch fs-4 mb-0">
                                    <input class="form-check-input pointer master-feature-toggle" type="checkbox" role="switch" data-feature="<?= $key ?>" <?= (!isset($masterSwitches[$key]) || $masterSwitches[$key] !== false) ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Features Control -->
        <div class="col-xl-6 mb-4">
            <div class="card border-0 rounded-4 blur shadow-sm admin-card h-100">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3">
                    <h5 class="mb-0 fw-bold"><i class='bx bx-globe text-info me-2'></i>Global Force-Enable</h5>
                    <small class="text-body-secondary">Forces the feature ON for all labs and users.</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($featuresList as $key => $label): ?>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 rounded-4 h-100 adm-stat">
                                <div>
                                    <h6 class="mb-1 fw-semibold"><?= $label ?></h6>
                                </div>
                                <div class="form-check form-switch fs-4 mb-0">
                                    <input class="form-check-input pointer global-feature-toggle" type="checkbox" role="switch" data-feature="<?= $key ?>" <?= !empty($globalSettings[$key]) ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MCP Settings -->
    <div class="row mb-4">
        <div class="col-xl-6">
            <div class="card border-0 rounded-4 blur shadow-sm admin-card h-100">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3">
                    <h5 class="mb-0 fw-bold text-warning"><i class='bx bx-bot me-2'></i>MCP Access Control</h5>
                    <small class="text-body-secondary">Configure who can use MCP tools.</small>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-4 adm-stat">
                        <div>
                            <h6 class="mb-1 fw-semibold">Admin-Only Mode</h6>
                            <small class="text-body-secondary">When enabled, only superusers can connect to MCP tools. Regular users are blocked.</small>
                        </div>
                        <div class="form-check form-switch fs-4 mb-0 ms-3">
                            <input class="form-check-input pointer mcp-admin-only-toggle" type="checkbox" role="switch" <?= !empty($mcpSettings['admin_only']) ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- News Automation Settings -->
    <?php
    // Normalize last_fetch (UTCDateTime | legacy {} BSON doc | scalar) → ms timestamp
    $lastFetchMs = null;
    if (isset($newsSettings['last_fetch'])) {
        $lastFetchRaw = $newsSettings['last_fetch'];
        if ($lastFetchRaw instanceof \MongoDB\BSON\UTCDateTime) {
            $lastFetchMs = (int) $lastFetchRaw->toDateTime()->getTimestamp() * 1000;
        } elseif ($lastFetchRaw instanceof \MongoDB\Model\BSONDocument) {
            $copy = $lastFetchRaw->getArrayCopy();
            if (isset($copy['d']) && is_numeric($copy['d'])) {
                $lastFetchMs = (int) $copy['d'];
            }
        } elseif ($lastFetchRaw instanceof \DateTimeInterface) {
            $lastFetchMs = $lastFetchRaw->getTimestamp() * 1000;
        } elseif (is_numeric($lastFetchRaw)) {
            $lastFetchMs = ((int) $lastFetchRaw > 1e11) ? (int) $lastFetchRaw : (int) $lastFetchRaw * 1000; // sec or ms
        }
    }

    // Next scheduled run = last fetch + interval (the timer gates itself on this)
    $lastFetchEcho = 'Last fetch: ' . ($lastFetchMs !== null ? \TimeHelper::formatWithZone(new \MongoDB\BSON\UTCDateTime($lastFetchMs)) : 'Never');
    $nextRunEcho = '';
    if (!empty($newsSettings['enabled'])) {
        $intervalMin = (int)($newsSettings['interval'] ?? 60);
        if ($lastFetchMs !== null) {
            $nextDueMs = $lastFetchMs + $intervalMin * 60000;
            $nextRunEcho = ($nextDueMs / 1000 <= time())
                ? 'Next fetch: Due now'
                : 'Next fetch: ' . \TimeHelper::formatWithZone(new \MongoDB\BSON\UTCDateTime((int) $nextDueMs));
        } else {
            $nextRunEcho = 'Next fetch: shortly';
        }
    }
    ?>
    <div class="row mb-4">
        <div class="col-xl-6">
            <div class="card border-0 rounded-4 blur shadow-sm admin-card h-100">
                <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3">
                    <h5 class="mb-0 fw-bold text-success"><i class='bx bx-news me-2'></i>News Automation</h5>
                    <small class="text-body-secondary">Auto-fetch cybersecurity news articles from public RSS feeds on a schedule.</small>
                </div>
                <div class="card-body">
                    <!-- Toggle row — matches MCP card pattern -->
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-4 adm-stat mb-3">
                        <div>
                            <h6 class="mb-1 fw-semibold">Automatic Fetching</h6>
                            <small class="text-body-secondary">When ON, the scheduler pulls new articles on your interval.</small>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="small news-next-run<?= $nextRunEcho === '' ? ' d-none' : '' ?>" id="newsNextRunText"><?= htmlspecialchars($nextRunEcho) ?></div>
                            <div class="form-check form-switch fs-4 mb-0">
                                <input class="form-check-input pointer news-autofetch-toggle" type="checkbox" role="switch" id="newsAutofetchToggle" data-last-fetch-ms="<?= $lastFetchMs !== null ? (int) $lastFetchMs : '' ?>" <?= !empty($newsSettings['enabled']) ? 'checked' : '' ?>>
                            </div>
                        </div>
                    </div>

                    <!-- Interval + buttons row — right-aligned like MCP card -->
                    <div class="d-flex justify-content-between align-items-end">
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label mb-0 small fw-semibold text-body-secondary">Fetch Interval</label>
                            <select class="form-select form-select-sm news-interval-select" id="newsIntervalSelect">
                                <option value="30" <?= (isset($newsSettings['interval']) && $newsSettings['interval'] == 30) ? 'selected' : '' ?>>30 min</option>
                                <option value="60" <?= (isset($newsSettings['interval']) && $newsSettings['interval'] == 60) ? 'selected' : '' ?>>1 hour</option>
                                <option value="180" <?= (isset($newsSettings['interval']) && $newsSettings['interval'] == 180) ? 'selected' : '' ?>>3 hours</option>
                                <option value="360" <?= (isset($newsSettings['interval']) && $newsSettings['interval'] == 360) ? 'selected' : '' ?>>6 hours</option>
                                <option value="720" <?= (isset($newsSettings['interval']) && $newsSettings['interval'] == 720) ? 'selected' : '' ?>>12 hours</option>
                                <option value="1440" <?= (isset($newsSettings['interval']) && $newsSettings['interval'] == 1440) ? 'selected' : '' ?>>24 hours</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-success btn-sm news-save-btn" id="newsSaveBtn">
                                <i class='bx bx-save me-1'></i>Save Settings
                            </button>
                            <button class="btn btn-primary btn-sm news-fetch-now-btn" id="newsFetchNowBtn">
                                <i class='bx bx-refresh me-1'></i>Fetch Now
                            </button>
                        </div>
                    </div>

                    <div class="small text-muted mt-2" id="newsStatusText" data-last-fetch-ms="<?= $lastFetchMs !== null ? (int) $lastFetchMs : '' ?>">
                        <?= htmlspecialchars($lastFetchEcho ?? 'Last fetch: Never') ?>
                    </div>

                    <!-- Last-fetch + today stats row -->
                    <div class="row g-2 mt-3">
                        <div class="col-6">
                            <div class="d-flex align-items-center">
                                <i class="bx bx-download text-info me-2 fs-5"></i>
                                <div>
                                    <div class="small text-body-secondary">Last fetch</div>
                                    <div class="fw-semibold text-dark"><?= number_format($newsLastInserted) ?> new, <?= number_format($newsLastUpdated) ?> updated</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="d-flex align-items-center justify-content-end">
                                <div>
                                    <div class="small text-body-secondary">News today</div>
                                    <div class="fw-semibold text-dark"><?= number_format($newsTodayCount) ?> articles</div>
                                </div>
                                <i class="bx bx-calendar text-success ms-2 fs-5"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Per-Lab Feature Matrix Cards -->
    <div class="card border-0 rounded-4 blur shadow-sm mb-4">
        <div class="card-header border-bottom border-body-secondary border-opacity-10 bg-transparent py-3">
            <h5 class="mb-0 fw-bold"><i class='bx bx-grid-alt text-primary me-2'></i>Per-Lab Default Features</h5>
            <small class="text-body-secondary">Click a lab to configure its default available features.</small>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <?php foreach ($knownLabs as $labKey => $labName): 
                    $adminIconMap = [
                        'essentials' => 'bxl-tux',
                        'docker_lab' => 'bxl-docker',
                        'n8n' => 'bx-git-repo-forked'
                    ];
                    $bxClass = $adminIconMap[$labKey] ?? 'bx-cube';
                ?>
                <div class="col-md-3">
                    <div class="card border-0 rounded-4 blur shadow-sm pointer h-100 lab-matrix-card" data-coreui-toggle="modal" data-coreui-target="#matrixModal-<?= $labKey ?>">
                        <div class="card-body text-center p-4 d-flex flex-column justify-content-center align-items-center">
                            <div class="mb-3 rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10" style="width: 60px; height: 60px;">
                                <?php if ($labKey === 'minio'): ?>
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" height="32" width="32" class="text-primary">
                                        <path d="M13.2072 0.006c-0.6216 -0.0478 -1.2 0.1943 -1.6211 0.582a2.15 2.15 0 0 0 -0.0938 3.0352l3.4082 3.5507a3.042 3.042 0 0 1 -0.664 4.6875l-0.463 0.2383V7.2853a15.4198 15.4198 0 0 0 -8.0174 10.4862v0.0176l6.5487 -3.3281v7.621L13.7794 24V13.6817l0.8965 -0.4629a4.4432 4.4432 0 0 0 1.2207 -7.0292l-3.371 -3.5254a0.7489 0.7489 0 0 1 0.037 -1.0547 0.7522 0.7522 0 0 1 1.0567 0.0371l0.4668 0.4863 -0.006 0.0059 4.0704 4.2441a0.0566 0.0566 0 0 0 0.082 0 0.06 0.06 0 0 0 0 -0.0703l-3.1406 -5.1425 -0.1484 0.1425 0.1484 -0.1445C14.4945 0.3926 13.8287 0.0538 13.2072 0.006Zm-0.9024 9.8652v2.9941l-4.1523 2.1484a13.9787 13.9787 0 0 1 2.7676 -3.9277 14.1784 14.1784 0 0 1 1.3847 -1.2148z" fill="currentColor"></path>
                                    </svg>
                                <?php else: ?>
                                    <i class='bx <?= $bxClass ?> text-primary' style="font-size: 2rem;"></i>
                                <?php endif; ?>
                            </div>
                            <h5 class="fw-bold mb-1"><?= $labName ?></h5>
                            <small class="text-body-secondary font-monospace"><?= $labKey ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Per-Lab Feature Matrix Modals -->
<?php foreach ($knownLabs as $labKey => $labName): ?>
<div class="modal fade" id="matrixModal-<?= $labKey ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg blur" style="border: 1px solid rgba(var(--cui-body-color-rgb, 255,255,255), 0.1) !important;">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php
                    $labDescriptions = [
                        'essentials' => 'The essentials lab provides basic Linux tools and utilities for standard tasks.',
                        'docker_lab' => 'A complete Docker environment for building, running, and managing containers.',
                        'n8n'        => 'Workflow automation lab with n8n to connect your apps and automate tasks.',
                        'minio'      => 'S3 compatible object storage server for storing unstructured data.'
                    ];
                    $lDesc = $labDescriptions[$labKey] ?? 'Configure default features for this lab environment.';
                    
                    $adminIconMap = [
                        'essentials' => 'bxl-tux',
                        'docker_lab' => 'bxl-docker',
                        'n8n' => 'bx-git-repo-forked'
                    ];
                    $bxClass = $adminIconMap[$labKey] ?? 'bx-cube';
                ?>
                <div class="d-flex align-items-center mb-4 p-4 rounded-4 adm-stat">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-4 bg-primary bg-opacity-10" style="width: 72px; height: 72px; flex-shrink: 0;">
                        <?php if ($labKey === 'minio'): ?>
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" height="40" width="40" class="text-primary">
                                <path d="M13.2072 0.006c-0.6216 -0.0478 -1.2 0.1943 -1.6211 0.582a2.15 2.15 0 0 0 -0.0938 3.0352l3.4082 3.5507a3.042 3.042 0 0 1 -0.664 4.6875l-0.463 0.2383V7.2853a15.4198 15.4198 0 0 0 -8.0174 10.4862v0.0176l6.5487 -3.3281v7.621L13.7794 24V13.6817l0.8965 -0.4629a4.4432 4.4432 0 0 0 1.2207 -7.0292l-3.371 -3.5254a0.7489 0.7489 0 0 1 0.037 -1.0547 0.7522 0.7522 0 0 1 1.0567 0.0371l0.4668 0.4863 -0.006 0.0059 4.0704 4.2441a0.0566 0.0566 0 0 0 0.082 0 0.06 0.06 0 0 0 0 -0.0703l-3.1406 -5.1425 -0.1484 0.1425 0.1484 -0.1445C14.4945 0.3926 13.8287 0.0538 13.2072 0.006Zm-0.9024 9.8652v2.9941l-4.1523 2.1484a13.9787 13.9787 0 0 1 2.7676 -3.9277 14.1784 14.1784 0 0 1 1.3847 -1.2148z" fill="currentColor"></path>
                            </svg>
                        <?php else: ?>
                            <i class='bx <?= $bxClass ?> text-primary' style="font-size: 2.5rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1"><?= $labName ?> Features</h4>
                        <p class="text-body-secondary small mb-0"><?= $lDesc ?></p>
                    </div>
                </div>
                
                <div class="row g-3">
                <?php 
                    $applicableFeatures = [
                        'essentials' => ['always_on', 'http_proxies', 'startup_script', 'expose_web'],
                        'docker_lab' => ['always_on', 'http_proxies', 'startup_script', 'expose_web'],
                        'minio'      => ['always_on', 'startup_script'],
                        'n8n'        => ['always_on', 'startup_script']
                    ];
                    
                    foreach ($featuresList as $featKey => $featLabel): 
                        $currentLabSupported = \TomLabs\Labs\LabFeatures::getSupportedFeatures($labKey);
                        $isChecked = in_array($featKey, $currentLabSupported, true);
                        $isApplicable = in_array($featKey, $applicableFeatures[$labKey] ?? []);
                        
                        $featureDetails = [
                            'always_on' => ['icon' => 'bx-power-off', 'desc' => 'Keep the lab running indefinitely without automatic shutdown.'],
                            'http_proxies' => ['icon' => 'bx-globe', 'desc' => 'Allow custom HTTP port proxying for web services.'],
                            'startup_script' => ['icon' => 'bx-code-block', 'desc' => 'Execute custom bash scripts on lab initialization.'],
                            'expose_web' => ['icon' => 'bx-server', 'desc' => 'Make standard web ports publicly accessible over the internet.']
                        ];
                        $fIcon = $featureDetails[$featKey]['icon'] ?? 'bx-cog';
                        $fDesc = $featureDetails[$featKey]['desc'] ?? 'Configure this feature for the lab.';
                ?>
                
                <?php if ($isApplicable): ?>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center p-3 h-100 rounded-4 adm-stat">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 42px; height: 42px; background: rgba(var(--cui-body-color-rgb, 255,255,255), 0.05);">
                                <i class='bx <?= $fIcon ?> fs-4 text-primary'></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-semibold" style="font-size: 0.9rem;"><?= $featLabel ?></h6>
                                <div class="text-body-secondary opacity-75 small" style="font-size: 0.7rem; line-height: 1.2;"><?= $fDesc ?></div>
                            </div>
                        </div>
                        <div class="form-check form-switch fs-4 mb-0 ms-2 flex-shrink-0">
                            <input class="form-check-input pointer matrix-feature-toggle" type="checkbox" role="switch" 
                                   data-lab="<?= $labKey ?>" data-feature="<?= $featKey ?>" <?= $isChecked ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center p-3 h-100 rounded-4 opacity-50 adm-stat">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 42px; height: 42px; background: rgba(var(--cui-body-color-rgb, 255,255,255), 0.02);">
                                <i class='bx <?= $fIcon ?> fs-4 text-body-secondary opacity-50'></i>
                            </div>
                            <div>
                                <h6 class="mb-1 text-body-secondary" style="font-size: 0.9rem;"><del><?= $featLabel ?></del></h6>
                                <div class="text-body-secondary opacity-50 small" style="font-size: 0.7rem; line-height: 1.2;"><?= $fDesc ?></div>
                            </div>
                        </div>
                        <div class="ms-2 flex-shrink-0">
                            <span class="badge rounded-pill" style="font-size: 0.65rem; font-weight: normal; background: rgba(var(--cui-body-color-rgb, 255,255,255), 0.1);">N/A</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-semibold" data-coreui-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
async function toggleFeatureAPI(formData, toggleElement, successMsg) {
    const originalState = !toggleElement.checked;
    try {
        const response = await fetch('/api/admin/toggle_feature', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.status === 'success') {
            TomNotify.show(successMsg, 'Saved', 'success', 3000);
        } else {
            TomNotify.show(res.error || 'Failed to update', 'Error', 'error', 4000);
            toggleElement.checked = originalState;
        }
    } catch(e) {
        TomNotify.show('Network error', 'Error', 'error', 4000);
        toggleElement.checked = originalState;
    }
}

document.querySelectorAll('.global-feature-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const feature = this.getAttribute('data-feature');
        const state = this.checked;
        const formData = new FormData();
        formData.append('scope', 'global');
        formData.append('feature', feature);
        formData.append('state', state);
        toggleFeatureAPI(formData, this, `Global Override for ${feature} is now ${state ? 'ENABLED' : 'DISABLED'}`);
    });
});

document.querySelectorAll('.master-feature-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const feature = this.getAttribute('data-feature');
        const state = this.checked;
        const formData = new FormData();
        formData.append('scope', 'master');
        formData.append('feature', feature);
        formData.append('state', state);
        toggleFeatureAPI(formData, this, `Master Switch for ${feature} is now ${state ? 'ON' : 'KILLED'}`);
    });
});

document.querySelectorAll('.matrix-feature-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const lab = this.getAttribute('data-lab');
        const feature = this.getAttribute('data-feature');
        const state = this.checked;
        const formData = new FormData();
        formData.append('scope', 'matrix');
        formData.append('lab', lab);
        formData.append('feature', feature);
        formData.append('state', state);
        toggleFeatureAPI(formData, this, `${feature} for ${lab} is now ${state ? 'ENABLED' : 'DISABLED'}`);
    });
});

document.querySelectorAll('.mcp-admin-only-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const state = this.checked;
        const formData = new FormData();
        formData.append('scope', 'mcp_settings');
        formData.append('setting', 'admin_only');
        formData.append('state', state);
        toggleFeatureAPI(formData, this, `MCP Admin-Only Mode is now ${state ? 'ENABLED (superusers only)' : 'DISABLED (all users)'}`);
    });
});

// ── News Automation Settings ──
const newsAutofetchToggle = document.getElementById('newsAutofetchToggle');
const newsIntervalSelect = document.getElementById('newsIntervalSelect');
const newsSaveBtn = document.getElementById('newsSaveBtn');
const newsFetchNowBtn = document.getElementById('newsFetchNowBtn');
const newsStatusText = document.getElementById('newsStatusText');
const newsIntervalDisplay = document.getElementById('news-interval-display');

if (newsIntervalSelect) {
    newsIntervalSelect.addEventListener('change', function() {
        // interval display removed; value is read on save
    });
}

// Render "Next fetch: …" in the orange label (IST, matches server-side row)
function updateNewsNextRunLabel(enabled, intervalMin) {
    const el = document.getElementById('newsNextRunText');
    if (!el) return;
    if (!enabled) {
        el.textContent = '';
        el.classList.add('d-none');
        return;
    }
    const lastMs = parseInt(newsAutofetchToggle ? (newsAutofetchToggle.dataset.lastFetchMs || '') : '', 10);
    if (!lastMs) {
        el.textContent = 'Next fetch: shortly';
        el.classList.remove('d-none');
        return;
    }
    const nextDueMs = lastMs + intervalMin * 60000;
    if (nextDueMs <= Date.now()) {
        el.textContent = 'Next fetch: Due now';
    } else {
        const fmt = new Intl.DateTimeFormat('en-IN', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true,
            timeZone: 'Asia/Kolkata'
        });
        el.textContent = 'Next fetch: ' + fmt.format(new Date(nextDueMs)) + ' IST';
    }
    el.classList.remove('d-none');
}

function showNewsStatus(msg, type = 'success') {
    if (!newsStatusText) return;
    newsStatusText.textContent = msg;
    newsStatusText.className = 'small ' + (type === 'success' ? 'text-success' : 'text-danger');
    setTimeout(() => {
        newsStatusText.className = 'small text-muted';
    }, 4000);
}

if (newsSaveBtn) {
    newsSaveBtn.addEventListener('click', function() {
        const enabled = newsAutofetchToggle ? newsAutofetchToggle.checked : false;
        const interval = newsIntervalSelect ? newsIntervalSelect.value : '60';

        const formData = new FormData();
        formData.append('enabled', enabled ? '1' : '0');
        formData.append('interval', interval);

        const origText = newsSaveBtn.innerHTML;
        newsSaveBtn.disabled = true;
        newsSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';

        fetch('/api/admin/news_settings.php', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    showNewsStatus('News automation settings saved.', 'success');
                    updateNewsNextRunLabel(enabled, parseInt(interval, 10) || 60);
                } else {
                    showNewsStatus(data.error || 'Save failed', 'danger');
                }
            })
            .catch(() => showNewsStatus('Network error', 'danger'))
            .finally(() => {
                newsSaveBtn.disabled = false;
                newsSaveBtn.innerHTML = origText;
            });
    });
}

if (newsFetchNowBtn) {
    newsFetchNowBtn.addEventListener('click', function() {
        const origText = newsFetchNowBtn.innerHTML;
        newsFetchNowBtn.disabled = true;
        newsFetchNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Fetching...';

        fetch('/api/news/fetch.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' }
        })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const ins = data.data?.total_inserted ?? 0;
                    const upd = data.data?.total_updated ?? 0;
                    showNewsStatus(`Fetch complete: ${ins} inserted, ${upd} updated.`, 'success');
                } else {
                    showNewsStatus(data.error || 'Fetch failed', 'danger');
                }
            })
            .catch(() => showNewsStatus('Network error', 'danger'))
            .finally(() => {
                newsFetchNowBtn.disabled = false;
                newsFetchNowBtn.innerHTML = origText;
            });
    });
}
</script>
