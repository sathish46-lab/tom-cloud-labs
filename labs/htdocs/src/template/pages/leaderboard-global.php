<?php
/**
 * Global Leaderboard — everyone ranked by zeal (reference / SNA design).
 *
 * Ranks 1–15 render here (3 winner cards + 12 table rows); the "Load more"
 * button fetches the next 10 ranks as raw <tr> HTML from
 * /api/leaderboard_rows. Ladder maths: src/utils/leagues.php, shared row
 * rendering: src/utils/leaderboard.php.
 */
require_once __DIR__ . '/../../utils/leaderboard.php';

$lbTotal   = leaderboard_total();
$lbEntries = leaderboard_entries(0, LB_INITIAL_RANKS, $lbCtfTotal);
$lbShown   = count($lbEntries);

// Top 3 are full-width winner rows; ranks 4–15 go in the table.
$lbWinners = array_slice($lbEntries, 0, LB_WINNER_COUNT);
$tableRows = array_slice($lbEntries, LB_WINNER_COUNT);
$lbHasMore = $lbShown < $lbTotal;

$lbMe = null;
if ($meUser = Session::getUser()) {
    $meStats = \TomLabs\Labs\Quiz::getUserStats($meUser->getEmail());
    $meZeal  = (int)($meStats['zeal'] ?? 0);
    $lbMe    = [
        'username' => $meUser->getUsername(),
        'rank'     => (int)DatabaseConnection::getDefaultDatabase()
            ->user_stats->countDocuments(['zeal' => ['$gt' => $meZeal]]) + 1,
        'zeal'     => $meZeal,
    ];
}
?>
<!-- Header banner — same .blur treatment the other page headers use -->
<div class="blur banner mb-3 rounded-0">
    <div class="container-fluid px-4">
        <div class="row align-items-center py-3">
            <div class="col">
                <div class="lb-hero">
                    <h1 class="lb-hero-title">Global Leaderboard</h1>
                    <p class="lb-hero-copy">Embrace the Global Leaderboard, your arena to transcend limits with every mission and
                        achievement. Garner Zeal 🔥, ascend the ranks, and embody the relentless pursuit of self-betterment
                        inherent in a ronin.</p>
                </div>
            </div>
            <div class="col-auto">
                <a class="btn btn-light btn-sm rounded-pill px-3" href="/leaderboard-global" hx-boost="false">
                    <i class="bx bx-refresh"></i> Refresh
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container p-1 py-3">

    <?php if (!$lbEntries): ?>
    <div class="card blur">
        <div class="card-body text-center py-5 text-medium-emphasis">
            <i class="bx bx-trophy display-6 d-block mb-2 opacity-50"></i>
            No ranked ninjas yet — earn some Zeal to claim the top spot.
        </div>
    </div>
    <?php else: ?>

    <?php if ($lbWinners): ?>
    <!-- Top 3 — winner cards -->
    <div id="lb-winners">
        <?php foreach ($lbWinners as $i => $w): ?>
        <?php $rankNo = $i + 1; ?>
        <div class="card winner-card winner-rank-<?= $rankNo ?> blur mb-3">
            <div class="winner-card-wrap position-relative" style="padding: 0.75rem 1rem; overflow: hidden;">
                <h5 class="card-title-winner rank-<?= $rankNo ?>"><?= $rankNo ?></h5>

                <div class="d-flex align-items-center gap-3" style="padding-left: 8%; position: relative; z-index: 1;">

                    <!-- User Info - LEFT SIDE -->
                    <div class="d-flex align-items-center gap-3" style="min-width: 280px; max-width: 280px;">
                        <div class="position-relative flex-shrink-0">
                            <a href="/<?= htmlspecialchars($w['username']) ?>"
                               data-coreui-toggle="tooltip" aria-label="@<?= htmlspecialchars($w['username']) ?>"
                               data-coreui-original-title="@<?= htmlspecialchars($w['username']) ?>" hx-boost="false">
                                <div class="avatar avatar-xl" style="width: 4.5rem; height: 4.5rem;">
                                    <img class="avatar-img" onerror="this.onerror=null;this.src='/assets/avatars/'+(this.src.match(/avatar\d+\.png/)||['avatar1.png'])[0]" src="<?= htmlspecialchars($w['avatar']) ?>"
                                         alt="<?= htmlspecialchars($w['username']) ?>">
                                </div>
                            </a>
                            <div class="position-absolute crown-icon rank-<?= $rankNo ?>-crown"
                                 style="top: -10px; right: -8px; font-size: 1.5rem;"><?= ['👑', '🥈', '🥉'][$i] ?? '🏅' ?></div>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fs-4 fw-bold text-truncate">
                                <a href="/<?= htmlspecialchars($w['username']) ?>" target="_blank"
                                   class="winner-name hvr-grow" title="<?= htmlspecialchars($w['username']) ?>">
                                    <?= htmlspecialchars($w['username']) ?></a>
                            </div>
                            <div class="text-medium-emphasis fs-6">
                                <?= number_format($w['zeal']) ?> 🔥 | <?= number_format($w['jolt']) ?> ⚡
                            </div>
                            <div class="league_badge d-inline-flex align-items-center gap-1 mt-1">
                                <img class="level-avatar" src="<?= htmlspecialchars($w['badge']) ?>"
                                     style="width: 1.25rem; height: 1.25rem;" alt="">
                                <span class="fw-bold fs-7 text-truncate" style="max-width: 120px;"><?= htmlspecialchars($w['title']) ?></span>
                            </div>
                            <?php if (!empty($w['is_me'])): ?>
                            <div class="d-inline-block badge rounded-pill bg-success bg-opacity-10 text-success mt-1">It's You</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Stats Grid - RIGHT SIDE -->
                    <div class="flex-grow-1 d-flex align-items-center justify-content-between gap-2">

                        <!-- CTF Challenges -->
                        <div class="text-center flex-fill">
                            <div class="text-medium-emphasis fs-6 fw-semibold mb-1 winner-analysis">CTF</div>
                            <div class="fs-4 fw-bold">
                                <?= number_format($w['ctf_solved']) ?>
                                <?php if ($lbCtfTotal > 0): ?><span class="small text-muted fw-normal">/ <?= number_format($lbCtfTotal) ?></span><?php endif; ?>
                            </div>
                            <?= leaderboard_pills($w['ctf_zeal'], $w['ctf_jolt']) ?>
                            <div class="fs-6 text-muted mt-1">⏱️ 00:00:00</div>
                        </div>

                        <!-- Quiz Performance -->
                        <div class="text-center flex-fill">
                            <div class="text-medium-emphasis fs-6 fw-semibold mb-1 winner-analysis">Quiz</div>
                            <div class="fs-4 fw-bold"><?= number_format($w['quiz_count']) ?></div>
                            <?= leaderboard_pills($w['quiz_zeal'], $w['quiz_jolt']) ?>
                            <div class="fs-6 text-muted mt-1">📊 <?= number_format($w['quiz_right']) ?>:<?= number_format($w['quiz_wrong']) ?></div>
                        </div>

                        <!-- Code Arena -->
                        <div class="text-center flex-fill">
                            <div class="text-medium-emphasis fs-6 fw-semibold mb-1 winner-analysis">Code Arena</div>
                            <div class="fs-4 fw-bold"><?= number_format($w['code_count']) ?></div>
                            <?= leaderboard_pills($w['code_zeal'], $w['code_jolt']) ?>
                            <div class="fs-6 text-muted mt-1">📊 0:0</div>
                        </div>

                        <!-- Achievements -->
                        <div class="text-center flex-fill">
                            <div class="text-medium-emphasis fs-6 fw-semibold mb-1 winner-analysis">Achievements</div>
                            <div class="fs-4 fw-bold">
                                <?= $w['ach'] ? number_format($w['ach']['unlocked']) : 0 ?>
                                <span class="fs-6">Earned</span>
                            </div>
                            <div class="badge bg-secondary bg-opacity-10 text-body fs-6 mt-1">
                                <?= $w['ach'] ? number_format($w['ach']['unlocked']) : 0 ?> /
                                <?= $w['ach'] ? number_format($w['ach']['total']) : 0 ?>
                            </div>
                            <div class="mt-2"><?= leaderboard_ach_chips($w['ach_groups']) ?></div>
                        </div>

                        <!-- Total - RIGHT END -->
                        <div class="text-center flex-shrink-0 ps-3 border-start" style="min-width: 120px;">
                            <div class="mb-2">
                                <div class="fs-4 fw-bold"><?= number_format($w['zeal']) ?> 🔥</div>
                                <div class="text-muted small">Total Zeal</div>
                            </div>
                            <div>
                                <div class="fs-4 fw-bold"><?= number_format($w['jolt']) ?> ⚡</div>
                                <div class="text-muted small">Total Jolt</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card blur">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 ninja-leaderboard"
                   data-page="1" data-rc="<?= count($tableRows) ?>">
                <thead>
                    <tr>
                        <th style="width: 64px;">Rank #</th>
                        <th style="width: 56px;"></th>
                        <th>Ninja Name</th>
                        <th>League</th>
                        <th>CTF Metrics</th>
                        <th>Quiz Metrics</th>
                        <th>Code Arena</th>
                        <th>Achievements</th>
                        <th class="text-end">Total Zeal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tableRows as $row): ?>
                    <?= leaderboard_row_html($row, $lbCtfTotal) ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($lbHasMore): ?>
        <!-- Load more — fetches the next <?= LB_LOAD_MORE_ROWS ?> ranks as raw <tr> HTML -->
        <div class="card-footer text-center" id="lb-load-wrap">
            <button type="button" class="btn btn-dark rounded-pill px-4 fw-semibold" id="lb-load-more"
                    data-offset="<?= (int)($lbShown + 1) ?>" data-total="<?= (int)$lbTotal ?>"
                    data-step="<?= (int)LB_LOAD_MORE_ROWS ?>">
                <i class="bx bx-chevrons-down"></i> Load more
            </button>
            <div class="small text-medium-emphasis mt-2" id="lb-load-status">
                Showing <?= number_format($lbShown > 0 ? 1 : 0) ?>–<?= number_format($lbShown) ?> of <?= number_format($lbTotal) ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card-footer small text-medium-emphasis">
            Showing <?= number_format($lbShown > 0 ? 1 : 0) ?>–<?= number_format($lbShown) ?> of <?= number_format($lbTotal) ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        (function () {
            var btn = document.getElementById('lb-load-more');
            if (!btn) return;
            var tbody = document.querySelector('table.ninja-leaderboard tbody');
            var status = document.getElementById('lb-load-status');
            if (!tbody) return;

            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                btn.disabled = true;

                var offset = parseInt(btn.dataset.offset, 10);
                var step = parseInt(btn.dataset.step, 10);
                var total = parseInt(btn.dataset.total, 10);
                var before = tbody.querySelectorAll('tr').length;

                fetch('/api/leaderboard_rows?offset=' + offset + '&limit=' + step, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'fetch' }
                })
                    .then(function (res) {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.text();
                    })
                    .then(function (html) {
                        if (html.trim()) {
                            tbody.insertAdjacentHTML('beforeend', html);
                            btn.dataset.offset = String(offset + step);
                            if (window.CoreUI && typeof CoreUI.initTooltips === 'function') {
                                CoreUI.initTooltips(tbody);
                            }
                        }
                        var shown = before + <?= (int)LB_WINNER_COUNT ?> + (html.trim() ? tbody.querySelectorAll('tr').length - before : 0);
                        if (status) {
                            status.textContent = 'Showing 1–' + Math.min(shown, total) + ' of ' + total;
                        }
                        if (!html.trim() || shown >= total) {
                            var wrap = document.getElementById('lb-load-wrap');
                            if (wrap) wrap.remove();
                            return;
                        }
                        btn.disabled = false;
                    })
                    .catch(function () {
                        btn.disabled = false;
                    });
            });
        })();
    </script>
    <?php endif; ?>
</div>
