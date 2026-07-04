<?php
$quiz = Session::get('current_quiz');
$parent = Session::get('parent_topic');
$subtopic = Session::get('current_subtopic');

// Professional fallbacks for high-density rendering
$desc = $quiz['desc'] ?? "Challenge yourself with these intriguing queries. Each question sheds light on a different aspect of this domain, honing your beginner skills and paving the way to grasp more complex concepts.";
$tags = (isset($quiz['tags']) && is_array($quiz['tags'])) ? $quiz['tags'] : [$parent['title'] ?? 'Cybersecurity', $subtopic['title'] ?? 'Networking'];
$difficulty = strtolower($quiz['difficulty'] ?? 'normal');

// Base Points per difficulty
$basePoints = 25; // Default Normal
if ($difficulty === 'easy') $basePoints = 15;
elseif ($difficulty === 'hard') $basePoints = 50;

$points = $quiz['points_per_correct'] ?? $basePoints;

// Extract quiz questions for the frontend engine
$quizData = $quiz['questions'] ?? $quiz['content'] ?? [];



$pointsPerCorrect = $points;
$qCount = count($quizData);
$totalRewards = $points * $qCount;

$joltReward = 2; // Default Normal
if ($difficulty === 'easy') $joltReward = 1;
elseif ($difficulty === 'hard') $joltReward = 5;

// Check if user has already completed this quiz to prevent double rewards UI
$hasCompletedBefore = false;
$user = Session::getUser();
if ($user && isset($quiz['hash'])) {
    $db = DatabaseConnection::getDefaultDatabase();
    $hasCompletedBefore = $db->quiz_attempts->findOne([
        'user_email' => $user->getEmail(),
        'quiz_hash' => $quiz['hash'],
        'status' => 'completed'
    ]) !== null;
}

if ($hasCompletedBefore) {
    $totalRewards = 0;
    $joltReward = 0;
}



?>

<div class="quiz-evaluation-view fade-in">
    <!-- 1. Background Layer -->
    <div class="evaluation-bg"></div>

    <!-- 2. Header Section (Title, Desc, Tags, Stats) -->
    <div class="lab-header-section pt-5 pb-1 mb-4 shadow-sm border-bottom border-secondary border-opacity-10" style="z-index: 10; position: relative;">
        <div class="container d-flex flex-column align-items-center">
            <!-- Narrative Block -->
            <div class="col-md-8 col-xl-8 text-center mb-3 mt-2">
                <h3 class="fw-bold mb-2 text-body-emphasis"><?= $quiz['title'] ?></h3>
                <p class="small text-body-secondary lh-base mb-0 opacity-75">
                    <?= $desc ?>
                </p>
            </div>

            <!-- Tags Block -->
            <div class="col-12 text-center mb-3">
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <?php foreach ($tags as $tag): ?>
                        <span class="badge bg-primary bg-opacity-75 text-white px-3 py-2 rounded-pill shadow-sm" style="font-size: 0.85rem; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1); letter-spacing: 0.5px;"><?= strtolower($tag) ?></span>
                    <?php endforeach; ?>
                    <button class="btn btn-link text-body-secondary p-0 ms-1" title="Share Quiz"><i class="bx bx-share-alt small"></i></button>
                </div>
            </div>

            <!-- Stats Block -->
            <div class="col-12 text-center">
                <div class="d-flex flex-wrap justify-content-center align-items-center gap-4 py-3 evaluation-stats-bar border-top border-secondary border-opacity-10">
                    <div class="stat-group d-flex align-items-center">
                        <span class="text-body-secondary x-small me-3">Difficulty</span>
                        <span class="badge <?= $difficulty == 'hard' ? 'bg-danger' : ($difficulty == 'easy' ? 'bg-info' : 'bg-success') ?> bg-opacity-25 <?= $difficulty == 'hard' ? 'text-danger' : ($difficulty == 'easy' ? 'text-info' : 'text-success') ?> border border-opacity-25 px-3 py-1 rounded-pill x-small fw-bold">
                            <?= strtoupper($difficulty) ?>
                        </span>
                    </div>
                    <div class="vr bg-secondary opacity-10 d-none d-md-block" style="height: 20px;"></div>
                    <div class="stat-group d-flex align-items-center">
                        <span class="text-body-secondary x-small me-3">Rewards Available</span>
                        <span class="text-body-emphasis fw-bold me-2"><?= number_format($totalRewards) ?> <i class="bx bxs-hot text-danger"></i></span>
                        <span class="text-body-emphasis fw-bold"><?= $joltReward ?> <i class="bx bxs-zap text-warning"></i></span>
                    </div>
                    <div class="vr bg-secondary opacity-10 d-none d-md-block" style="height: 20px;"></div>
                    <div class="stat-group d-flex align-items-center">
                        <span class="text-body-secondary x-small me-3">Time Remaining</span>
                        <span id="timer-display" class="text-body-emphasis fw-bold">--:-- <i class="bx bx-time-five text-body-secondary ms-1"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Interactive Area Section -->
    <div class="container-fluid px-0 mb-3 pb-3">
        <div class="card border-0 shadow-lg p-4 p-lg-4 py-xl-4 mt-4" style="background: rgba(var(--bs-body-bg-rgb, 11, 30, 54), 0.45); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 24px;">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    
                    <!-- Progress Steps (Dynamic) -->
                    <div id="evaluation-progress-container" class="evaluation-progress-steps d-flex justify-content-between align-items-center mb-3 position-relative" style="min-height: 40px;">
                        <!-- Generated by JS -->
                    </div>

                    <!-- 1. Start View -->
                    <div id="quiz-start-view" class="quiz-step-view text-center py-5">
                        <button class="btn btn-success rounded-pill px-5 py-3 fw-bold shadow-lg transition-all" onclick="startQuiz()">
                            Begin Quiz <i class="bx bx-play-circle ms-2"></i>
                        </button>
                    </div>

                    <!-- 2. Question View -->
                    <div id="quiz-question-view" class="quiz-step-view d-none">
                        <h3 id="question-text" class="text-center text-body-emphasis mb-5 px-md-5" style="line-height: 1.6; font-weight: 500; font-size: 1.10rem; letter-spacing: 0.2px;"></h3>
                        
                        <div class="row g-4 mb-5">
                            <div class="col-md-6">
                                <button class="btn quiz-option-btn w-100 p-4 text-center h-100" onclick="selectOption(0)">
                                    <span class="option-text"></span>
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn quiz-option-btn w-100 p-4 text-center h-100" onclick="selectOption(1)">
                                    <span class="option-text"></span>
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn quiz-option-btn w-100 p-4 text-center h-100" onclick="selectOption(2)">
                                    <span class="option-text"></span>
                                </button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn quiz-option-btn w-100 p-4 text-center h-100" onclick="selectOption(3)">
                                    <span class="option-text"></span>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center gap-3" style="min-height: 50px;">
                            <button id="submit-btn" class="btn btn-success rounded-pill px-5 py-2 fw-bold d-none shadow-sm" onclick="submitAnswer()">Submit Answer</button>
                            <button id="next-btn" class="btn btn-success rounded-pill px-5 py-2 fw-bold d-none shadow-sm" onclick="nextQuestion()">Next Question</button>
                            <button id="result-btn" class="btn btn-primary rounded-pill px-5 py-2 fw-bold d-none shadow-sm" onclick="showResult()">See Result</button>
                        </div>
                    </div>

                    <!-- 3. Result View -->
                    <div id="quiz-result-view" class="quiz-step-view d-none text-center py-5">
                        <div class="result-circle mx-auto mb-2">
                            <div class="result-score"><span id="score-val">0</span>/5</div>
                        </div>
                        <h3 class="text-body-emphasis fw-bold mb-2">Evaluation Complete!</h3>
                        <p id="result-msg" class="text-body-secondary mb-3"></p>
                        <p id="reward-msg" class="text-info small mb-4 d-none">
                            <i class="bx bxs-gift me-1"></i> Perfect Score! You earned <span id="zeal-earned">0</span> Zeal 🔥 and <span id="jolt-earned">0</span> Jolt ⚡️
                        </p>
                        <div class="d-flex justify-content-center gap-3">
                            <button class="btn btn-success rounded-pill px-5 py-2 fw-bold transition-all" onclick="htmx.ajax('GET', location.href, '#main-content')">Retake Quiz</button>
                            <a href="/quiz" class="btn btn-outline-secondary rounded-pill px-5 py-2 fw-bold">Back to Hub</a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Assets are now bundled into app.js/app.css
?>

<script>
window.QuizEngineConfig = {
    totalQuestions: <?= $qCount ?>,
    pointsPerCorrect: <?= $pointsPerCorrect ?>,
    difficulty: "<?= $difficulty ?>",
    hash: "<?= $quiz['hash'] ?>"
};
</script>
