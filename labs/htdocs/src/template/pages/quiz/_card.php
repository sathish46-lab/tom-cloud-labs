<?php
/**
 * Quiz Card Partial (Compact & Professional)
 * Optimized for density while maintaining premium reference aesthetics.
 */

$qTitle = $q['title'] ?? "Quiz Title";
$qDesc = $q['desc'] ?? "Explore the intricate mechanics of this domain through our AI-curated challenge.";
$qHash = $q['hash'] ?? "";
$viewCount = $q['view_count'] ?? 0;
$zealReward = $isAttempted ? 0 : ($q['points_per_correct'] ?? 25);
$joltReward = $isAttempted ? 0 : $qJolt;
$tags = (isset($q['tags']) && is_array($q['tags'])) ? $q['tags'] : ['tech', 'cybersecurity'];
$displayTags = array_slice($tags, 0, 2); // Show only 2 tags for compact view
$remainingTagsCount = count($tags) - 2;
$remainingTagsJson = htmlspecialchars(json_encode(array_slice($tags, 2)));

$timeAgo = "recent";
try {
    $timeAgo = is_numeric($q['created_at']) ? date('M j', (int)$q['created_at']) : $q['created_at'];
} catch (\Throwable $t) {}

// Difficulty badge color mapping
$diffColor = '#f9b115'; // Normal
if ($qDiff === 'easy') $diffColor = '#2eb857';
elseif ($qDiff === 'hard') $diffColor = '#e55353';
?>

<div class="col animate__animated animate__fadeIn quiz-card-item">
    <div class="card h-100 border-0 shadow-sm blur" 
         style="background: rgba(var(--cui-emphasis-color-rgb), 0.04) !important; backdrop-filter: blur(20px) !important; border: 1px solid rgba(var(--cui-emphasis-color-rgb), 0.1) !important; border-radius: 18px !important; transition: all 0.3s ease;">
        <div class="card-body p-3 d-flex flex-column">
            
            <!-- Title -->
            <h6 class="card-title fw-bold mb-1 theme-text" style="line-height: 1.4; font-size: 0.95rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.7rem;">
                <?= htmlspecialchars($qTitle) ?>
            </h6>

            <!-- Badges Row -->
            <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                <span class="badge rounded-pill px-2" style="font-size: 0.6rem; background: <?= $diffColor ?>20 !important; color: <?= $diffColor ?> !important; border: 1px solid <?= $diffColor ?>30 !important; font-weight: 800;">
                    <?= strtoupper($qDiff) ?>
                </span>
                
                <?php foreach ($displayTags as $tag): ?>
                    <span class="badge rounded-pill px-2" style="font-size: 0.6rem; background: rgba(59, 130, 246, 0.1) !important; color: #3b82f6 !important; border: 1px solid rgba(59, 130, 246, 0.1) !important;">
                        <?= htmlspecialchars(strtolower($tag)) ?>
                    </span>
                <?php endforeach; ?>
                
                <?php if ($remainingTagsCount > 0): ?>
                    <span class="badge rounded-pill px-2 quiz-tag-more" style="cursor: pointer; font-size: 0.6rem; background: rgba(var(--cui-emphasis-color-rgb), 0.05) !important; color: var(--cui-emphasis-color) !important;"
                        data-remaining-tags='<?= $remainingTagsJson ?>' tabindex="0">+<?= $remainingTagsCount ?></span>
                <?php endif; ?>

                <span class="ms-auto text-body-secondary opacity-50" style="font-size: 0.6rem; font-weight: 600;">
                    <i class="bx bx-time-five"></i> <?= $timeAgo ?>
                </span>
            </div>

            <!-- Description (Compact) -->
            <p class="text-body-secondary x-small mb-3 opacity-75" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.6rem; line-height: 1.4; font-size: 0.725rem;">
                <?= htmlspecialchars($qDesc) ?>
            </p>
            
            <!-- Footer: Stats & Action -->
            <div class="mt-auto pt-2 border-top border-secondary border-opacity-10 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center gap-1" title="Zeal Reward">
                        <span style="font-size: 0.8rem; font-weight: 800; color: #e55353;"><?= $zealReward ?></span>
                        <span style="font-size: 0.75rem;">🔥</span>
                    </div>
                    <div class="d-flex align-items-center gap-1" title="Jolt Reward">
                        <span style="font-size: 0.8rem; font-weight: 800; color: #f9b115;"><?= $joltReward ?></span>
                        <span style="font-size: 0.75rem;">⚡️</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 opacity-50" title="Views">
                        <span style="font-size: 0.8rem; font-weight: 800; color: #3399ff;"><?= $viewCount ?></span>
                        <span style="font-size: 0.75rem;">👁️</span>
                    </div>
                    
                    <button class="btn btn-link btn-sm clipboard p-0 ms-1 opacity-50 hover-opacity-100 theme-text" 
                        data-clipboard-text="<?= "https://labs.selfmade.ninja/quiz/v/" . $qHash ?>" 
                        style="text-decoration: none;">
                        <i class="bx bx-share-alt" style="font-size: 0.9rem;"></i>
                    </button>
                </div>

                <a href="/quiz/v/<?= $qHash ?>" class="btn btn-success btn-sm rounded-pill fw-bold px-3 py-1" style="font-size: 0.675rem; letter-spacing: 0.3px;">
                    <?= $isAttempted ? 'Retake Quiz' : 'Answer Quiz' ?>
                </a>
            </div>
        </div>
    </div>
</div>
