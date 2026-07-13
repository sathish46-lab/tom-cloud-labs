<?php
/**
 * Topic Leaderboard Template
 * Designed to match high-end Ninja reference aesthetics.
 */
?>
<div class="table-responsive mt-3 animate__animated animate__fadeIn">
    <table class="table table-borderless align-middle text-white mb-0" style="background: rgba(0,0,0,0.2); border-radius: 16px; overflow: hidden;">
        <thead class="text-uppercase x-small opacity-50 border-bottom border-white border-opacity-10">
            <tr>
                <th class="ps-4 py-3">Rank #</th>
                <th><i class='bx bx-user me-1'></i> Ninja's Name</th>
                <th class="text-center">Ninja's League</th>
                <th class="text-center">Quiz Zeal Earned</th>
                <th class="text-center">Winning Ratio <i class='bx bx-info-circle x-small opacity-50'></i></th>
                <th class="text-center pe-4">Most Played Tags</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($leaderboard)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="opacity-25 mb-2"><i class='bx bx-trophy display-1'></i></div>
                        <h5 class="text-body-secondary">No champions yet. Be the first!</h5>
                    </td>
                </tr>
            <?php else: foreach ($leaderboard as $index => $ninja): 
                $rank = $index + 1;
                $userEmail = $ninja['_id'];
                // Split email to get a name if profile doesn't exist
                $displayName = explode('@', $userEmail)[0];
                
                // League mapping (Simplified)
                $league = "Digital Dragon III";
                $leagueIcon = "digital_dragon.png";
                if ($rank === 1) { $league = "Cyber Knight Supreme IV"; $leagueIcon = "cyber_knight.png"; }
                elseif ($rank === 2) { $league = "Digital Dragon III"; $leagueIcon = "digital_dragon.png"; }
                elseif ($rank >= 3) { $league = "Cyber Crusader III"; $leagueIcon = "cyber_crusader.png"; }

                $ratioNum = ($ninja['total_score'] / max(1, $ninja['total_questions'])) * 100;
                $ratio = round($ratioNum, 0) . "%";
                
                // Flatten and count tags
                $allTags = [];
                foreach ($ninja['tags'] as $quizTags) {
                    if (is_array($quizTags)) {
                        foreach ($quizTags as $tag) {
                            $allTags[] = strtolower($tag);
                        }
                    }
                }
                $tagCounts = array_count_values($allTags);
                arsort($tagCounts);
                $topTags = array_slice($tagCounts, 0, 5, true);
            ?>
            <tr class="border-bottom border-white border-opacity-10 hover-bg-white-05 transition-all">
                <td class="ps-4 py-4">
                    <span class="display-6 fw-bold <?= $rank <= 3 ? 'text-info' : 'opacity-25' ?>"><?= $rank ?></span>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <div class="position-relative">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=random&color=fff" class="rounded-circle border border-2 <?= $rank === 1 ? 'border-info' : 'border-white border-opacity-25' ?>" width="52" height="52">
                            <?php if ($rank === 1): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-info p-1 border border-dark border-2">
                                    <i class='bx bxs-crown text-white' style="font-size: 10px;"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="fw-bold text-info fs-6"><?= htmlspecialchars(ucwords(str_replace('.', ' ', $displayName))) ?></div>
                            <div class="x-small text-body-tertiary">
                                <span class="me-2"><?= number_format($ninja['zeal_earned']) ?> <i class='bx bxs-hot text-danger'></i></span>
                                <span><?= number_format($ninja['quizzes_played'] * 2) ?> <i class='bx bxs-zap text-warning'></i></span>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <div class="card blur border-white border-opacity-10 py-2 px-3 mx-auto" style="max-width: 200px; background: rgba(255,255,255,0.05);">
                        <div class="d-flex align-items-center gap-2 justify-content-center">
                            <i class='bx bxs-shield-alt text-primary fs-4'></i>
                            <div class="text-uppercase fw-bold" style="font-size: 10px; letter-spacing: 0.5px;"><?= $league ?></div>
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2">
                        <span class="fs-4 fw-bold"><?= number_format($ninja['zeal_earned']) ?></span>
                        <span class="fs-5">🔥</span>
                    </div>
                </td>
                <td class="text-center">
                    <span class="fs-5 fw-bold opacity-75"><?= $ratio ?></span>
                </td>
                <td class="pe-4">
                    <div class="d-flex flex-wrap justify-content-center gap-1">
                        <?php foreach ($topTags as $tag => $count): ?>
                            <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size: 9px; padding: 3px 8px;">
                                <?= htmlspecialchars($tag) ?> <span class="opacity-50">× <?= $count ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<style>
.hover-bg-white-05:hover {
    background: rgba(255,255,255,0.03) !important;
}
.letter-spacing-1 {
    letter-spacing: 1px;
}
</style>
