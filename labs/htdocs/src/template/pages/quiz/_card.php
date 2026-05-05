<?php
/**
 * Quiz Card Partial (Premium High-Density Style)
 */

$qTitle = $q['title'] ?? "Quiz Title";
$qHash = $q['hash'] ?? "";
$viewCount = $q['view_count'] ?? 0;
$questions = $q['questions'] ?? $q['content'] ?? [];
$qCount = count($questions);

// Dynamic Point Calculation based on Difficulty
$basePoints = 25;
if ($qDiff === 'easy') $basePoints = 15;
elseif ($qDiff === 'hard') $basePoints = 50;

$zealReward = ($qCount * ($q['points_per_correct'] ?? $basePoints));
$joltReward = $qJolt;
$tags = (isset($q['tags'])) ? (array)$q['tags'] : ['tech'];
if (empty($tags)) $tags = ['tech'];

$tagLimit = 3; // Show max 3 tags initially to ensure 2-3 lines footprint
$displayTags = array_slice($tags, 0, $tagLimit);
$remainingTagsCount = count($tags) - $tagLimit;
$remainingTagsJson = htmlspecialchars(json_encode(array_slice($tags, $tagLimit)));

// Time ago calculation
$createdAt = $q['created_at'] ?? time();
$timeAgo = "recent";
if (is_numeric($createdAt)) {
    $diff = time() - (int)$createdAt;
    if ($diff < 60) $timeAgo = "now";
    elseif ($diff < 3600) $timeAgo = floor($diff/60) . "m";
    elseif ($diff < 86400) $timeAgo = floor($diff/3600) . "h";
    else $timeAgo = floor($diff/86400) . "d";
}

$diffColor = '#f9b115';
if ($qDiff === 'easy') $diffColor = '#2eb857';
elseif ($qDiff === 'hard') $diffColor = '#e55353';

$isNew = (isset($q['created_at']) && time() - (int)$q['created_at'] < 86400 * 30);
?>

<div class="col animate__animated animate__fadeIn quiz-card-item">
    <div class="card glass-card h-100 position-relative" 
         style="overflow: visible !important;">
        <div class="card-body p-3 d-flex flex-column position-relative" style="overflow: visible !important;">
            
            <!-- Title -->
            <h6 class="card-title fw-bold mb-2 theme-text" style="line-height: 1.4; font-size: 0.95rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.7rem;">
                <?= htmlspecialchars($qTitle) ?>
            </h6>

            <!-- Badge Row (Topics & Metadata) -->
            <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                <?php if ($isNew): ?>
                    <span class="badge rounded-pill px-2" style="font-size: 0.52rem; background: var(--sna-primary) !important; color: #000 !important; font-weight: 800; text-transform: lowercase;">new 🥳</span>
                <?php endif; ?>
                
                <span class="badge rounded-pill px-2" style="font-size: 0.52rem; background: <?= $diffColor ?> !important; color: #fff !important; font-weight: 800; text-transform: lowercase;"><?= strtolower($qDiff) ?></span>

                <!-- AI Tags -->
                <?php foreach ($displayTags as $tag): ?>
                    <span class="badge rounded-pill px-2" style="font-size: 0.52rem; background: rgba(255,255,255,0.1) !important; border: 1px solid rgba(255,255,255,0.05); color: #fff !important; font-weight: 600; text-transform: lowercase;"><?= strtolower($tag) ?></span>
                <?php endforeach; ?>
                
                <?php if ($remainingTagsCount > 0): ?>
                    <span class="badge rounded-pill px-2 quiz-tag-more position-relative" style="cursor: pointer; font-size: 0.52rem; background: rgba(255,255,255,0.1) !important; color: #fff !important;"
                        data-remaining-tags='<?= $remainingTagsJson ?>' onclick="toggleTagPopover(this)">+<?= $remainingTagsCount ?></span>
                <?php endif; ?>

                <span class="badge rounded-pill px-2" style="font-size: 0.52rem; background: rgba(255,255,255,0.05) !important; color: var(--sna-text-muted) !important; font-weight: 800; text-transform: lowercase;">
                    <i class="bx bx-time-five me-1"></i><?= strtolower($timeAgo) ?> ago
                </span>
            </div>

            <!-- Space between badges and footer -->
            <div class="mb-2"></div>

            <!-- Footer: Stats & Start -->
            <div class="mt-auto pt-2 border-top border-white border-opacity-10 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center gap-1">
                        <span class="fw-bold theme-text" style="font-size: 0.85rem;"><?= $zealReward ?></span>
                        <span style="font-size: 0.75rem;">🔥</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="fw-bold theme-text" style="font-size: 0.85rem;"><?= $joltReward ?></span>
                        <span style="font-size: 0.75rem;">⚡️</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 opacity-50">
                        <span class="fw-bold theme-text" style="font-size: 0.85rem;"><?= $viewCount ?></span>
                        <span style="font-size: 0.75rem;">👁️</span>
                    </div>
                    
                    <button class="btn btn-link btn-sm clipboard p-0 ms-1 theme-text opacity-40 hover-opacity-100" 
                        data-clipboard-text="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/quiz/v/" . $qHash ?>" 
                        style="text-decoration: none;">
                        <i class="bx bx-share-alt" style="font-size: 0.95rem;"></i>
                    </button>
                </div>

                <a href="/quiz/v/<?= $qHash ?>" class="btn btn-success btn-sm rounded-pill fw-bold px-3 py-1" 
                   style="font-size: 0.675rem; letter-spacing: 0.5px; box-shadow: 0 4px 12px rgba(var(--sna-primary-rgb), 0.3);">
                    start mission
                </a>
            </div>
        </div>
    </div>
</div>

<script>
if (typeof toggleTagPopover === 'undefined') {
    window.toggleTagPopover = function(el) {
        const card = el.closest('.card');
        const existing = card.querySelector('.tag-popover');
        if (existing) { existing.remove(); return; }
        
        const tags = JSON.parse(el.dataset.remainingTags);
        const popover = document.createElement('div');
        popover.className = 'tag-popover animate__animated animate__fadeInUp animate__faster';
        
        // Dynamic Positioning Logic
        const rect = el.getBoundingClientRect();
        const cardRect = card.getBoundingClientRect();
        const leftPos = rect.left - cardRect.left;
        
        popover.style = `
            position: absolute; 
            bottom: calc(100% - ${rect.top - cardRect.top}px + 12px); 
            left: 10px; 
            right: 10px; 
            background: rgba(15, 20, 25, 0.98); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255,255,255,0.15); 
            border-radius: 14px; 
            padding: 12px; 
            display: flex; 
            flex-wrap: wrap; 
            gap: 6px; 
            z-index: 2000; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
        `;

        // Add Pointer Arrow
        const arrow = document.createElement('div');
        arrow.style = `
            position: absolute;
            bottom: -6px;
            left: ${leftPos + 8}px;
            width: 12px;
            height: 12px;
            background: rgba(15, 20, 25, 0.98);
            border-right: 1px solid rgba(255,255,255,0.15);
            border-bottom: 1px solid rgba(255,255,255,0.15);
            transform: rotate(45deg);
        `;
        popover.appendChild(arrow);
        
        tags.forEach(tag => {
            const span = document.createElement('span');
            span.className = 'badge rounded-pill px-2 py-1';
            span.style.background = '#5a57cb';
            span.style.fontSize = '0.52rem';
            span.style.textTransform = 'lowercase';
            span.textContent = tag.toLowerCase();
            popover.appendChild(span);
        });
        
        card.appendChild(popover);
        
        const closeHandler = function(e) {
            if (!card.contains(e.target)) {
                popover.remove();
                document.removeEventListener('click', closeHandler);
            }
        };
        setTimeout(() => document.addEventListener('click', closeHandler), 10);
    }
}
</script>
