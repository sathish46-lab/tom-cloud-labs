<?php
$topic = Session::get('current_topic');
$subtopics = $topic['subtopics'] ?? [];
?>

<div class="quiz-topic-view fade-in pb-4 lab-header-section">
    <div class="container-fluid px-4 pt-2">
        
        <!-- 1. Header Section -->
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <h1 class="fs-4 fw-bold theme-text mb-0"><?= $topic['title'] ?></h1>
                <p class="text-body-secondary small mb-2 opacity-75"><?= $topic['desc'] ?></p>
                
                <p class="x-small text-body-tertiary lh-base mb-0 opacity-50" style="max-width: 750px;">
                    Choose from the list of topics below, or choose "Surprise Me" to get a random topic for 20% higher 
                    <span class="text-warning fw-bold">Zeal 🔥</span> Explore the Recent, Trending to find what others are playing.
                </p>
            </div>
            <div class="col-lg-6 text-lg-end d-flex flex-column justify-content-center align-items-lg-end">
                <div class="d-inline-flex flex-wrap align-items-center gap-2">
                    <div class="performance-modes d-flex rounded-pill overflow-hidden shadow-sm border border-white border-opacity-10">
                        <button class="mode-btn btn-surprise"><i class="bx bxs-bot me-1"></i>Surprise</button>
                        <button class="mode-btn btn-sprint"><i class="bx bxs-zap me-1"></i>Sprint</button>
                        <button class="mode-btn btn-rapid"><i class="bx bxs-flame me-1"></i>Rapid</button>
                        <button class="mode-btn btn-blitz"><i class="bx bxs-bolt me-1"></i>Blitz</button>
                        <button class="mode-btn btn-marathon"><i class="bx bxs-trophy me-1"></i>Marathon</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Navigation Tabs -->
        <div class="d-flex align-items-center gap-2 mb-4 overflow-auto pb-2 flex-nowrap quiz-tabs-row">
            <button class="btn btn-pill-active shadow-sm">Topics</button>
            <div class="dropdown">
                <button class="btn btn-pill-outline dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Recent 📂 in <?= $topic['title'] ?>
                </button>
                <ul class="dropdown-menu quiz-dropdown-menu shadow-lg border-0 mt-2">
                    <li><a class="dropdown-item" href="#">Recent in All</a></li>
                    <li><hr class="dropdown-divider opacity-10"></li>
                    <?php foreach ($subtopics as $sub): ?>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="launchQuiz('<?= $sub['id'] ?>')">Recent in <?= $sub['title'] ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="btn btn-pill-outline">Trending</button>
            <div class="dropdown">
                <button class="btn btn-pill-outline dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Completed ✅ in <?= $topic['title'] ?>
                </button>
                <ul class="dropdown-menu quiz-dropdown-menu shadow-lg border-0 mt-2">
                    <?php foreach ($subtopics as $sub): ?>
                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="launchQuiz('<?= $sub['id'] ?>')">Completed in <?= $sub['title'] ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="btn btn-pill-outline">Leaderboard <span class="badge-new">New 🆕 ✨</span></button>
        </div>

        <hr class="border-secondary opacity-10 mb-5">

        <!-- 3. Sub-Topics Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if (empty($subtopics)): ?>
                <div class="col-12 text-center py-5">
                    <div class="empty-state-card">
                        <i class="bx bx-folder-open display-1 text-body-tertiary mb-3 opacity-25"></i>
                        <h4 class="text-body-secondary">No sub-topics available for this track.</h4>
                        <a href="/quiz" class="btn btn-outline-info rounded-pill px-4 mt-3">Back to Hub</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($subtopics as $sub): ?>
                <div class="col">
                    <div class="card subtopic-premium-card h-100" onclick="launchQuiz('<?= $sub['id'] ?>')">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-2 card-title-text"><?= $sub['title'] ?></h5>
                            <p class="text-body-secondary small mb-0 opacity-75 lh-base"><?= $sub['desc'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function launchQuiz(topicId) {
    const parentId = '<?= $topic['id'] ?>';
    console.log("Launching Quiz for ID:", topicId, "under parent:", parentId);
    window.location.href = `/quiz/${parentId}/v/${topicId}`;
}
</script>

<style>
/* Base Styling & Theme Awareness */
.quiz-topic-view {
    -webkit-font-smoothing: antialiased;
}

/* Header Typography */
.theme-text {
    color: var(--bs-heading-color);
}

/* Surprise AI Button */
.surprise-btn {
    background: #00acee !important;
    border: none !important;
    font-size: 0.95rem;
    color: #fff !important;
    transition: all 0.3s ease;
}
.surprise-btn:hover {
    background: #00c3ff !important;
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0, 172, 238, 0.4) !important;
}

/* Performance Modes */
.performance-modes {
    background: rgba(var(--bs-emphasis-color-rgb), 0.05);
    border: 1px solid rgba(var(--bs-emphasis-color-rgb), 0.1);
}

.mode-btn {
    border: none;
    padding: 10px 20px;
    font-size: 0.85rem;
    font-weight: 700;
    color: #fff;
    transition: all 0.2s ease;
}

.btn-sprint { background: #ffc107; color: #000 !important; }
.btn-rapid { background: #ff4757; }
.btn-blitz { background: #3742fa; }
.btn-marathon { background: #2ed573; }

.mode-btn:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
}

/* Tab Pills - Exact Match */
.quiz-tabs-row .btn-pill-active {
    background: var(--bs-emphasis-color) !important;
    color: var(--bs-body-bg) !important;
    border-radius: 50px;
    padding: 8px 24px;
    font-size: 0.85rem;
    font-weight: 600;
    border: none;
}

.quiz-tabs-row .btn-pill-outline {
    background: rgba(var(--bs-emphasis-color-rgb), 0.03);
    color: var(--bs-body-secondary);
    border: 1px solid rgba(var(--bs-emphasis-color-rgb), 0.15);
    border-radius: 50px;
    padding: 8px 24px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.quiz-tabs-row .btn-pill-outline:hover {
    background: rgba(var(--bs-emphasis-color-rgb), 0.08);
    border-color: rgba(var(--bs-emphasis-color-rgb), 0.4);
    color: var(--bs-emphasis-color);
}

.badge-new {
    color: #00d2ff;
    font-size: 0.8rem;
    margin-left: 5px;
}

/* Sub-Topic Cards - Premium Glass & Theme Adaptive */
.subtopic-premium-card {
    background: rgba(var(--bs-emphasis-color-rgb), 0.03) !important;
    border: 1px solid rgba(var(--bs-emphasis-color-rgb), 0.08) !important;
    border-top: 1px solid rgba(var(--bs-emphasis-color-rgb), 0.15) !important;
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border-radius: 24px !important;
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    cursor: pointer;
    display: flex;
    flex-direction: column;
}

.subtopic-premium-card .card-body {
    padding: 1.75rem !important;
    display: flex;
    flex-direction: column;
    justify-content: center;
    height: 100%;
}

.subtopic-premium-card:hover {
    background: rgba(var(--bs-emphasis-color-rgb), 0.06) !important;
    border-color: rgba(0, 172, 238, 0.4) !important;
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15), 0 0 15px rgba(0, 172, 238, 0.05);
}

.card-title-text {
    color: var(--bs-heading-color);
    font-size: 1.1rem;
    letter-spacing: -0.2px;
    margin-bottom: 0.3rem !important;
}

.subtopic-premium-card p {
    font-size: 0.72rem !important;
    line-height: 1.4 !important;
}

/* Light Mode Specific Overrides for Clean Look */
[data-bs-theme="light"] .subtopic-premium-card {
    background: #ffffff !important;
    border: 1px solid rgba(0, 0, 0, 0.06) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

[data-bs-theme="light"] .subtopic-premium-card:hover {
    background: #ffffff !important;
    border-color: rgba(0, 172, 238, 0.3) !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
}

/* Empty State */
.empty-state-card {
    background: rgba(var(--bs-emphasis-color-rgb), 0.02);
    border: 2px dashed rgba(var(--bs-emphasis-color-rgb), 0.1);
    border-radius: 30px;
    padding: 80px 20px;
}

/* Utilities */
.lh-lg { line-height: 1.8 !important; }
</style>
