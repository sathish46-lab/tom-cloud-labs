<?php
/**
 * Challenge Leaderboard Template
 * Variables provided by app/challenges/_challenge_base.php via Session + loadMaster
 */
$instanceHash = Session::get('challenge_instance_hash');
$labId        = Session::get('challenge_lab_id');
$status       = Session::get('challenge_status');
$isRunning    = ($status === 'running');

$db            = DatabaseConnection::getDefaultDatabase();
$challengeMeta = $db->challenges->findOne(['lab_id' => $labId]) ?? [];
$labTitle      = $challengeMeta['title']       ?? ucwords(str_replace('-', ' ', $labId));
$labDesc       = $challengeMeta['description'] ?? 'Engage in real-world hacking scenarios and penetration testing.';
$labImage      = $challengeMeta['image_url']   ?? '/assets/img/challenges/shadow.png';
$maxZeal       = $challengeMeta['max_zeal']    ?? 2232;
$tags          = $challengeMeta['tags']        ?? ['team', 'beta', 'not running'];
$eventName     = $challengeMeta['event_name']  ?? 'Yukthi Finale';
$isEnded       = $challengeMeta['is_ended']    ?? true;
$isRetired     = $challengeMeta['is_retired']  ?? false;
$activeTab     = 'leaderboard';

include __DIR__ . '/partials/challenge_header.php';
?>

<div class="container-fluid px-0 py-3">
    <div id="leaderboard-loading" class="text-center py-5">
        <div class="spinner-border text-info" role="status"></div>
        <p class="text-secondary mt-3 small">Loading leaderboard...</p>
    </div>

    <div id="leaderboard-empty" class="d-none">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="min-height:300px;background:var(--cui-card-bg);">
            <div class="card-body d-flex align-items-center justify-content-center p-5">
                <div class="text-center">
                    <h2 class="text-white fw-bold mb-3">The calm before the storm ⛈️</h2>
                    <p class="text-secondary">Be the tempest that sweeps the leaderboard in this hacking challenge!</p>
                </div>
            </div>
        </div>
    </div>

    <div id="leaderboard-table-wrap" class="d-none">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background:var(--cui-card-bg);">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Leaderboard <span class="small text-muted ms-1"><?= htmlspecialchars($labTitle) ?></span></h6>
                <span class="badge bg-primary-gradient rounded-pill" id="lb-total-count">0 entries</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover mb-0 align-middle">
                        <thead>
                            <tr class="text-muted small text-uppercase" style="border-bottom:1px solid rgba(255,255,255,0.05);">
                                <th class="ps-4 py-3" style="width:60px;">#</th>
                                <th class="py-3">Player</th>
                                <th class="py-3 text-center">Challenges</th>
                                <th class="py-3 text-center"><i class='bx bxs-hot text-warning'></i> Zeal</th>
                                <th class="py-3 text-center pe-4"><i class='bx bx-time text-info'></i> Time Spent</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboard-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const LAB_ID   = <?= json_encode($labId) ?>;
    const MAX_ZEAL = <?= (int)$maxZeal ?>;

    const rankBadge = r => r === 1 ? '🥇' : r === 2 ? '🥈' : r === 3 ? '🥉' : `<span class="text-muted fw-bold">${r}</span>`;
    const fmtTime   = s => [Math.floor(s/3600), Math.floor((s%3600)/60), s%60].map(v => String(v).padStart(2,'0')).join(':');

    function renderRow(e) {
        const zPct = Math.min(100, Math.round((e.zeal / MAX_ZEAL) * 100));
        const cPct = Math.min(100, Math.round((e.challenges_completed / (e.total_challenges || 1)) * 100));
        return `<tr style="border-bottom:1px solid rgba(255,255,255,0.04);">
            <td class="ps-4 py-3 fs-5">${rankBadge(e.rank)}</td>
            <td class="py-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="${e.avatar_url}" class="rounded-circle border border-white border-opacity-10"
                         style="width:36px;height:36px;object-fit:cover;" onerror="this.src='/assets/avatars/default.png'">
                    <span class="fw-bold text-white small">${e.username}</span>
                </div>
            </td>
            <td class="py-3 text-center">
                <div class="text-white fw-bold small">${e.challenges_completed}/${e.total_challenges}</div>
                <div class="progress mt-1 mx-auto" style="height:3px;width:60px;background:rgba(255,255,255,0.1);">
                    <div class="progress-bar bg-success" style="width:${cPct}%"></div>
                </div>
            </td>
            <td class="py-3 text-center">
                <div class="text-warning fw-bold small"><i class='bx bxs-hot'></i> ${e.zeal}</div>
                <div class="progress mt-1 mx-auto" style="height:3px;width:60px;background:rgba(255,255,255,0.1);">
                    <div class="progress-bar bg-warning" style="width:${zPct}%"></div>
                </div>
            </td>
            <td class="py-3 text-center pe-4"><code class="text-info small">${fmtTime(e.time_spent_seconds)}</code></td>
        </tr>`;
    }

    async function load() {
        try {
            const res  = await fetch(`/api/challenges/leaderboard?lab_id=${encodeURIComponent(LAB_ID)}`);
            const data = await res.json();
            document.getElementById('leaderboard-loading').classList.add('d-none');
            if (!data.leaderboard?.length) {
                document.getElementById('leaderboard-empty').classList.remove('d-none');
                return;
            }
            document.getElementById('lb-total-count').textContent = `${data.total} entries`;
            document.getElementById('leaderboard-body').innerHTML  = data.leaderboard.map(renderRow).join('');
            document.getElementById('leaderboard-table-wrap').classList.remove('d-none');
        } catch(e) {
            document.getElementById('leaderboard-loading').classList.add('d-none');
            document.getElementById('leaderboard-empty').classList.remove('d-none');
        }
    }

    document.addEventListener('DOMContentLoaded', load);
})();
</script>
