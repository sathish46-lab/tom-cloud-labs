<?php
/**
 * One leaderboard table row. Rendered by leaderboard_row_html() — both for the
 * initial page load and for /api/leaderboard_rows ("Load more").
 * Expects: array $row, int $ctfTotal.
 */
?>
<tr class="align-middle <?= !empty($row['is_me']) ? 'table-active' : '' ?>">
    <td class="text-center">
        <h3><?= number_format($row['rank']) ?></h3>
    </td>
    <td class="text-center">
        <a href="/<?= htmlspecialchars($row['username']) ?>" target="_blank"
           class="avatar avatar-md" data-coreui-toggle="tooltip"
           data-coreui-custom-class="custom-tooltip" data-coreui-placement="top"
           aria-label="<?= htmlspecialchars($row['username']) ?>"
           data-coreui-original-title="@<?= htmlspecialchars($row['username']) ?>">
            <img class="avatar-img" onerror="this.onerror=null;this.src='/assets/avatars/'+(this.src.match(/avatar\d+\.png/)||['avatar1.png'])[0]" src="<?= htmlspecialchars($row['avatar']) ?>"
                 alt="<?= htmlspecialchars($row['username']) ?>">
        </a>
    </td>
    <td>
        <div class="text-nowrap hvr-grow">
            <a class="text-decoration-none " href="/<?= htmlspecialchars($row['username']) ?>"
               target="_blank"><?= htmlspecialchars($row['username']) ?></a>
        </div>
        <div class="small text-medium-emphasis text-nowrap">
            <?= number_format($row['zeal']) ?> 🔥 | <?= number_format($row['jolt']) ?> ⚡
        </div>
        <?php if (!empty($row['is_me'])): ?>
        <span class="badge rounded-pill bg-success bg-opacity-10 text-success">It's You</span>
        <?php endif; ?>
    </td>
    <td class="text-center">
        <div class="league_badge">
            <div class="col-auto p-0">
                <img class="icon icon-xl my-1 me-2 level-avatar" src="<?= htmlspecialchars($row['badge']) ?>" alt="">
            </div>
            <div class="col-auto p-0">
                <div class="text-nowrap">
                    <span class="level-name"><?= htmlspecialchars($row['title']) ?></span>
                </div>
            </div>
        </div>
    </td>
    <td class="text-center">
        <div class="d-flex flex-column gap-1">
            <div data-coreui-toggle="tooltip" data-coreui-placement="top"
                 data-coreui-original-title="CTF Challenges Completed">
                <span class="fs-5 fw-bold"><?= number_format($row['ctf_solved']) ?></span>
                <?php if ($ctfTotal > 0): ?><span class="small text-muted">/ <?= number_format($ctfTotal) ?></span><?php endif; ?>
            </div>
            <?= leaderboard_pills($row['ctf_zeal'], $row['ctf_jolt']) ?>
            <div class="small text-muted" data-coreui-toggle="tooltip" data-coreui-placement="top"
                 data-coreui-original-title="Time Spent">⏱️ 00:00:00</div>
        </div>
    </td>
    <td class="text-center">
        <div class="d-flex flex-column gap-1">
            <div data-coreui-toggle="tooltip" data-coreui-placement="top"
                 data-coreui-original-title="Quizzes Completed">
                <span class="fs-5 fw-bold"><?= number_format($row['quiz_count']) ?></span>
                <span class="small text-muted">Quizzes</span>
            </div>
            <?= leaderboard_pills($row['quiz_zeal'], $row['quiz_jolt']) ?>
            <div class="small text-medium-emphasis" data-coreui-toggle="tooltip"
                 data-coreui-placement="top"
                 data-coreui-original-title="Winning Ratio: Solved vs Attempted">
                📊 <?= number_format($row['quiz_right']) ?>:<?= number_format($row['quiz_wrong']) ?>
            </div>
        </div>
    </td>
    <td class="text-center">
        <div class="d-flex flex-column gap-1">
            <div data-coreui-toggle="tooltip" data-coreui-placement="top"
                 data-coreui-original-title="Code Problems Solved">
                <span class="fs-5 fw-bold"><?= number_format($row['code_count']) ?></span>
                <span class="small text-muted">Problems</span>
            </div>
            <?= leaderboard_pills($row['code_zeal'], $row['code_jolt']) ?>
            <div class="small text-medium-emphasis" data-coreui-toggle="tooltip"
                 data-coreui-placement="top"
                 data-coreui-original-title="Winning Ratio: Solved vs Attempted">📊 0:0</div>
        </div>
    </td>
    <td class="text-center">
        <div class="d-flex flex-column justify-content-center align-items-center gap-2">
            <div class="d-flex flex-row gap-2 justify-content-center">
                <div class="d-flex flex-row align-items-center px-2 py-1 league_badge"
                     data-coreui-toggle="tooltip" data-coreui-placement="left"
                     data-coreui-original-title="Total Achievements Earned">
                    <span class="fs-6 m-0 fw-bold"><?= $row['ach'] ? number_format($row['ach']['unlocked']) : 0 ?></span>
                </div>
                <div class="d-flex flex-row align-items-center px-2 py-1 league_badge"
                     data-coreui-toggle="tooltip" data-coreui-placement="right"
                     data-coreui-original-title="Total badges available">
                    <span class="fs-6 m-0"><?= $row['ach'] ? number_format($row['ach']['total']) : 0 ?></span>
                </div>
            </div>
            <?= leaderboard_ach_chips($row['ach_groups']) ?>
        </div>
    </td>
    <td class="text-center">
        <div class="d-flex flex-column gap-1">
            <h3 class="text-nowrap fw-bold mb-0"><?= number_format($row['zeal']) ?> 🔥</h3>
            <small class="text-muted"><?= number_format($row['jolt']) ?> ⚡</small>
        </div>
    </td>
</tr>
