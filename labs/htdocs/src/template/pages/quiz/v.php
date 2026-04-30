<?php
$quiz = Session::get('current_quiz');
$parent = Session::get('parent_topic');
$subtopic = Session::get('current_subtopic');

// Professional fallbacks for high-density rendering
$desc = $quiz['desc'] ?? "Challenge yourself with these intriguing queries. Each question sheds light on a different aspect of this domain, honing your beginner skills and paving the way to grasp more complex concepts.";
$tags = (isset($quiz['tags']) && is_array($quiz['tags'])) ? $quiz['tags'] : [$parent['title'] ?? 'Cybersecurity', $subtopic['title'] ?? 'Networking'];
$difficulty = $quiz['difficulty'] ?? 'normal';
$points = $quiz['points_per_correct'] ?? 25;

// Extract quiz questions for the frontend engine
$quizData = $quiz['questions'] ?? $quiz['content'] ?? [];
$pointsPerCorrect = $points;
$qCount = count($quizData);
$totalRewards = $points * $qCount;

$joltReward = 2; // Default Normal
if ($difficulty === 'easy') $joltReward = 1;
elseif ($difficulty === 'hard') $joltReward = 5;

// Premium Motivational Library
$correctMessages = [
    "Outstanding! Your technical intuition is razor-sharp! ⚡",
    "Brilliant! You've successfully decoded that complexity. 🧠",
    "Legendary! That's how a true Ninja approaches a challenge. 🥷",
    "Exceptional work! You're dominating this topic. 🔥",
    "Perfect! Your understanding of this domain is impressive. 💎",
    "Boom! Knowledge is power, and you've got plenty of it! 🚀",
    "Spot on! You make even the toughest problems look easy. ✨",
    "Incredible! Your skills are evolving at a rapid pace. 📈",
    "Absolute mastery! Keep this momentum going. 👑",
    "Phenomenal! That's another milestone conquered. 🚩",
    "Great job! You're building a formidable expertise here. 🛡️",
    "Fantastic! Your attention to detail is your superpower. 🔍",
    "Superb! You've got the logic of a high-performance machine. 🤖",
    "Magnificent! Your technical growth is inspiring to watch. 🌟",
    "Top-tier performance! You're reaching new heights today. 🏔️",
    "Majestic! Your progress is a testament to your dedication. 🏛️",
    "Victory! You've claimed another triumph in this arena. ⚔️"
];

$wrongMessages = [
    "Not quite, but every mistake is a step toward mastery! 📚",
    "Close! Re-analyze the logic and you'll crush it next time. 🔄",
    "Don't stop now! Even the best Ninjas fail before they succeed. 🥷",
    "A tough one, but your persistence is what counts. Keep going! 💪",
    "Mistakes are just data points for your future success. 📊",
    "Keep your head up! You're learning things most people don't. 🎓",
    "Almost there! Refine your approach and try again. 🛠️",
    "That was a tricky one. Take a breath and dive back in! 🌊",
    "Failure is the mother of success. Stay hungry! 🍕",
    "Every wrong answer is an opportunity to strengthen your mind. 🧠",
    "Consistency is key! You'll get it on the next attempt. 🔑",
    "Don't be discouraged. The path to expert is paved with errors. 🛣️",
    "Stay focused! You're much closer than you think. 🎯",
    "It's a learning curve, and you're already halfway up! 🎢",
    "Analyze, adapt, and overcome. You've got this! 🦾",
    "Keep swinging! Each miss makes you stronger for the hit. ⚾",
    "Persistence is your ally. One more try might be the one! 🔄"
];
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
        <div class="card border-0 shadow-lg p-4 p-lg-5 py-xl-5 mt-4" style="background: rgba(var(--bs-body-bg-rgb, 11, 30, 54), 0.45); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 24px;">
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
                        <h3 id="question-text" class="text-center text-body-emphasis mb-5 px-md-5" style="line-height: 1.6; font-weight: 500; font-size: 1.35rem; letter-spacing: 0.2px;"></h3>
                        
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
                            <button class="btn btn-success rounded-pill px-5 py-2 fw-bold transition-all" onclick="location.reload()">Retake Quiz</button>
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
    quizData: <?= json_encode($quizData) ?>,
    pointsPerCorrect: <?= $pointsPerCorrect ?>,
    difficulty: "<?= $difficulty ?>",
    hash: "<?= $quiz['hash'] ?>",
    motivation: {
        correct: <?= json_encode($correctMessages) ?>,
        wrong: <?= json_encode($wrongMessages) ?>
    }
};

/**
 * Quiz Engine Module (Restored to Template for Stability)
 * Handles dynamic question loading, state management, and scoring
 */

let currentStep = 0;
let selectedOption = null;
let score = 0;
let timeLeft = 0;
let timerInterval = null;

/**
 * Initialize Dynamic Progress Bar
 */
function initProgressBar() {
    if (!window.QuizEngineConfig || !window.QuizEngineConfig.quizData) return;
    const quizData = window.QuizEngineConfig.quizData;
    const container = document.getElementById('evaluation-progress-container');
    if (!container) return;
    container.innerHTML = '';
    
    quizData.forEach((_, idx) => {
        const step = document.createElement('div');
        step.className = 'step-item' + (idx === 0 ? ' active' : '');
        step.setAttribute('data-step', idx + 1);
        step.innerHTML = `<span>${idx + 1}</span>`;
        container.appendChild(step);
        
        if (idx < quizData.length - 1) {
            const segment = document.createElement('div');
            segment.className = 'progress-segment';
            segment.setAttribute('data-segment', idx + 1);
            container.appendChild(segment);
        }
    });
}

/**
 * Unified Button Management
 */
function toggleButton(id, show) {
    const btn = document.getElementById(id);
    if (!btn) return;
    if (show) {
        btn.classList.remove('d-none');
        btn.style.setProperty('display', 'inline-block', 'important');
    } else {
        btn.classList.add('d-none');
        btn.style.setProperty('display', 'none', 'important');
    }
}

function resetButtons() {
    toggleButton('submit-btn', false);
    toggleButton('next-btn', false);
    toggleButton('result-btn', false);
}

window.startQuiz = function() {
    console.log("[Quiz Engine] Starting Quiz...");
    const startView = document.getElementById('quiz-start-view');
    const questionView = document.getElementById('quiz-question-view');
    
    if (!startView || !questionView) {
        console.error("[Quiz Engine] UI Views not found!");
        return;
    }

    // Record an initial attempt with 'started' status
    const quizHash = window.QuizEngineConfig.hash;
    const formData = new FormData();
    formData.append('hash', quizHash);
    formData.append('score', 0);
    formData.append('status', 'started');
    formData.append('total', window.QuizEngineConfig.quizData.length);
    fetch('/api/quiz/record_attempt', {
        method: 'POST',
        body: formData
    });

    startView.classList.add('d-none');
    questionView.classList.remove('d-none');
    
    initTimer();
    loadQuestion();
};

function initTimer() {
    timerInterval = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            alert("Time is up! Let's see your results.");
            showResult();
        }
    }, 1000);
}

function preInitTimer() {
    const config = window.QuizEngineConfig;
    if (!config || !config.quizData) return;
    
    const qCount = config.quizData.length;
    let timePerQuestion = 45; // Default Normal

    if (config.difficulty === "easy") timePerQuestion = 30;
    else if (config.difficulty === "hard") timePerQuestion = 60;

    timeLeft = qCount * timePerQuestion;
    updateTimerDisplay();
}

function updateTimerDisplay() {
    const display = document.getElementById('timer-display');
    if (!display) return;
    
    const mins = Math.floor(timeLeft / 60);
    const secs = timeLeft % 60;
    const timeStr = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    display.innerHTML = `${timeStr} <i class="bx bx-time-five text-body-secondary ms-1"></i>`;
    
    if (timeLeft <= 30) {
        display.classList.add('text-danger');
        display.classList.remove('text-body-emphasis');
    }
}

function loadQuestion() {
    if (!window.QuizEngineConfig || !window.QuizEngineConfig.quizData || window.QuizEngineConfig.quizData.length === 0) {
        console.error("[Quiz Engine] Quiz data missing or empty!");
        alert("Sorry, the quiz questions could not be loaded. Please try generating a new quiz.");
        return;
    }
    const quizData = window.QuizEngineConfig.quizData;
    const q = quizData[currentStep];
    if (!q) return;

    const qText = document.getElementById('question-text');
    if (qText) {
        let rawText = q.text || q.question || "Unknown Question";
        // Parse backticks into beautifully styled inline code blocks
        rawText = rawText.replace(/`([^`]+)`/g, '<code class="text-info bg-info bg-opacity-10 px-2 py-1 rounded mx-1" style="font-family: var(--cui-font-monospace, monospace); font-weight: 600;">$1</code>');
        qText.innerHTML = rawText;
    }

    const btns = document.querySelectorAll('.quiz-option-btn');
    btns.forEach((btn, idx) => {
        const optText = btn.querySelector('.option-text');
        const optionsArray = q.options || q.answers || [];
        if (optText) {
            let optStr = optionsArray[idx] || "";
            optStr = optStr.replace(/`/g, ''); // Remove backticks since options are already styled as code
            optText.innerText = optStr;
        }
        btn.classList.remove('correct', 'wrong', 'selected');
        btn.disabled = false;
    });
    
    resetButtons();
    selectedOption = null;
    
    const steps = document.querySelectorAll('.step-item');
    steps.forEach((step, idx) => {
        if(idx === currentStep) step.classList.add('active');
        else if(idx > currentStep) step.classList.remove('active', 'completed-correct', 'completed-wrong');
    });
}

window.selectOption = function(idx) {
    selectedOption = idx;
    const btns = document.querySelectorAll('.quiz-option-btn');
    btns.forEach((btn, i) => {
        if(i === idx) btn.classList.add('selected');
        else btn.classList.remove('selected');
    });
    toggleButton('submit-btn', true);
};

window.submitAnswer = function() {
    if (!window.QuizEngineConfig || !window.QuizEngineConfig.quizData) return;
    const quizData = window.QuizEngineConfig.quizData;
    const q = quizData[currentStep];
    const btns = document.querySelectorAll('.quiz-option-btn');
    const steps = document.querySelectorAll('.step-item');
    const segments = document.querySelectorAll('.progress-segment');
    const currentStepItem = steps[currentStep];
    
    btns.forEach(btn => btn.disabled = true);
    resetButtons();

    let isCorrect = selectedOption === q.correct;
    if(isCorrect) {
        btns[selectedOption].classList.add('correct');
        currentStepItem.classList.add('completed-correct');
        score++;
        // Motivational Toast for Correct Answer
        const motivation = window.QuizEngineConfig.motivation.correct;
        const randomMsg = motivation[Math.floor(Math.random() * motivation.length)];
        if (window.TomNotify) TomNotify.show(randomMsg, "Correct Answer", "success", 2500);

    } else {
        btns[selectedOption].classList.add('wrong');
        currentStepItem.classList.add('completed-wrong');
        if (segments[currentStep]) segments[currentStep].classList.add('wrong');

        // Encouragement Toast for Wrong Answer
        const encouragement = window.QuizEngineConfig.motivation.wrong;
        const randomMsg = encouragement[Math.floor(Math.random() * encouragement.length)];
        if (window.TomNotify) TomNotify.show(randomMsg, "Keep Going", "warning", 2500);
    }

    const isLastQuestion = (currentStep >= quizData.length - 1);
    if (isLastQuestion) {
        if (timerInterval) clearInterval(timerInterval);
        toggleButton('result-btn', true);
    } else {
        toggleButton('next-btn', true);
    }
};

window.nextQuestion = function() {
    currentStep++;
    loadQuestion();
};

window.showResult = function() {
    if (timerInterval) clearInterval(timerInterval);
    if (!window.QuizEngineConfig || !window.QuizEngineConfig.quizData) return;
    const quizData = window.QuizEngineConfig.quizData;
    const pointsPerCorrect = window.QuizEngineConfig.pointsPerCorrect;
    const quizHash = window.QuizEngineConfig.hash;

    const formData = new FormData();
    formData.append('hash', quizHash);
    formData.append('score', score);
    formData.append('status', 'completed');
    formData.append('total', quizData.length);

    fetch('/api/quiz/record_attempt', {
        method: 'POST',
        body: formData
    }).then(res => res.json())
      .then(data => {
          // Always update global header points from source of truth
          const globalZeal = document.getElementById('header-zeal');
          const globalJolt = document.getElementById('header-jolt');
          if (globalZeal && typeof data.total_zeal !== 'undefined') globalZeal.innerText = data.total_zeal.toLocaleString();
          if (globalJolt && typeof data.total_jolt !== 'undefined') globalJolt.innerText = data.total_jolt.toLocaleString();

          if (data.rewarded) {
              const rewardMsg = document.getElementById('reward-msg');
              const zealVal = document.getElementById('zeal-earned');
              const joltVal = document.getElementById('jolt-earned');
              if (rewardMsg) rewardMsg.classList.remove('d-none');
              if (zealVal) zealVal.innerText = data.zeal;
              if (joltVal) joltVal.innerText = data.jolt;
          }
      });

    const qView = document.getElementById('quiz-question-view');
    const rView = document.getElementById('quiz-result-view');
    if (qView) qView.classList.add('d-none');
    if (rView) rView.classList.remove('d-none');
    
    const resultScoreContainer = document.querySelector('.result-score');
    if (resultScoreContainer) {
        resultScoreContainer.innerHTML = `<span id="score-val">${score}</span>/${quizData.length}`;
    }
    
    const totalPoints = score * pointsPerCorrect;
    const ratio = score / quizData.length;
    let msg = "";
    if(ratio === 1) msg = `Outstanding! 💎 Perfect command of the subject.`;
    else if(ratio >= 0.6) msg = `Great job! ⚡️ Solid grasp of the concepts.`;
    else msg = `Keep learning! 🔥 Practice makes perfect.`;
    
    const resultMsg = document.getElementById('result-msg');
    if (resultMsg) resultMsg.innerText = msg;
};

document.addEventListener('DOMContentLoaded', () => {
    initProgressBar();
    preInitTimer();

    // Record unique view
    const quizHash = window.QuizEngineConfig.hash;
    if (quizHash) {
        const formData = new FormData();
        formData.append('hash', quizHash);
        fetch('/api/quiz/record_view', {
            method: 'POST',
            body: formData
        });
    }
});
</script>
