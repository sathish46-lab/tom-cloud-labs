<?php
// Auto-refresh verification for custom domains on page load
$domainManager = new DomainManager();
$user = Session::getUser();
if ($user) {
    $domainManager->refreshUserDomains($user->getUserId());
}
$certs = Session::get('ssl_certificates') ?: [];
$serverIP = $domainManager->getServerIP();

// Calculate domain limits
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
$tomDomainCount = $db->domains->countDocuments([
    'user_id' => $user->getUserId(),
    'type' => ['$ne' => 'custom']
]);
$tomDomainLimit = 20;

// Helper function to show time ago
function timeAgo($timestamp) {
    if (empty($timestamp)) return 'Never';
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $timestamp);
}
?>
<div class="lab-header-section mb-4 px-4">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="fw-bold theme-text m-0 domains-header-title">Domains</h1>
            <p class="text-secondary opacity-75 mt-2 mb-0 domains-header-desc">
                My Domains is a section where you can reserve stylish Tom Lab Domains or register 3rd party domains to access your lab over Internet.
                In case of 3rd party domains, you will have to manually modify the DNS records of your domain to point to your lab. Domains are used to
                show your work to the WWW over SSL seemlessly. Your online presence makes you powerful 🔥
            </p>
        </div>
        <div class="col-auto text-end">
            <div class="mb-2">
                <button class="btn btn-success fw-bold px-4 rounded-pill shadow-sm btn-add-domain" data-coreui-toggle="modal" data-coreui-target="#addDomainModal">
                    <i class="bx bx-plus"></i> Add New Domain
                </button>
            </div>
            <div class="text-start d-inline-block text-white domains-limit-text">
                <div class="mb-1">Limit for Tom Domains: <?= $tomDomainCount ?>/<?= $tomDomainLimit ?></div>
                <div>Limit for Custom Domains: Unlimited 🎰</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php foreach (Session::get('user_domains') as $d): ?>
    <?php include __DIR__ . '/../partials/_domain_card.php'; ?>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="addDomainModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4">
                <h4 class="modal-title fw-bold">Add Domain</h4>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal"></button>
            </div>

            <div id="addDomainModalContent">
                <div class="modal-body p-5 text-center">
                    <i class="bx bx-loader-alt bx-spin text-primary spinner-loader-icon"></i>
                    <div class="mt-3 text-white opacity-75 fw-semibold tracking-widest uppercase loading-form-text">Loading form...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmDeleteDomainModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg rounded-4 border-0 glass-card glass-modal-content">
        <div class="modal-header border-0 pb-2">
            <h4 class="modal-title fw-bold m-0 modal-title-delete">Delete Domain</h4>
        </div>
        <div class="modal-body py-3 border-top border-bottom border-translucent">
            <p class="mb-0 opacity-75 modal-body-desc">
                You are about to delete a registered domain: <span id="deleteDomainModalName" class="text-info fw-bold font-monospace"></span>. 
                You will no longer be able to communicate via this domain.<br>
                Are you sure to continue?
            </p>
        </div>
        <div class="modal-footer border-0 pt-3 pb-1 d-flex justify-content-end gap-3">
            <button type="button" class="btn px-4 rounded-pill fw-bold border-0 shadow-sm btn-modal-cancel" data-coreui-dismiss="modal">Cancel</button>
            <button type="button" class="btn text-white px-4 rounded-pill fw-bold border-0 btn-modal-delete" id="confirmDeleteDomainBtn" onclick="confirmDeleteDomainAction()">Delete</button>
        </div>
    </div>
  </div>
</div>