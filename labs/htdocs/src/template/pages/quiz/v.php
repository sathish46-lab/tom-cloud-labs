<?php
$quiz = Session::get('current_quiz');
$parent = Session::get('parent_topic');
$subtopic = Session::get('current_subtopic');

// Professional fallbacks for high-density rendering
$desc = $quiz['desc'] ?? "Challenge yourself with these intriguing queries. Each question sheds light on a different aspect of this domain, honing your beginner skills and paving the way to grasp more complex concepts.";
$tags = (isset($quiz['tags']) && is_array($quiz['tags'])) ? $quiz['tags'] : [$parent['title'] ?? 'Cybersecurity', $subtopic['title'] ?? 'Networking'];
$difficulty = $quiz['difficulty'] ?? 'normal';
$points = $quiz['points_per_correct'] ?? 25;
$totalRewards = $points * 5;
?>

<div class="quiz-evaluation-view fade-in lab-header-section">
    <!-- 1. Background Layer -->
    <div class="evaluation-bg"></div>

    <!-- 2. Header Section (Title, Desc, Tags, Stats) -->
    <div class=" d-flex flex-column align-items-center">
        <!-- Narrative Block -->
        <div class="col-md-8 col-xl-8 text-center mb-3">
            <h3 class="fw-bold mb-2 text-body-emphasis"><?= $quiz['title'] ?></h3>
            <p class="small text-body-secondary lh-base mb-0 opacity-75">
                <?= $desc ?>
            </p>
        </div>

        <!-- Tags Block -->
        <div class="col-12 text-center mb-3">
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <?php foreach ($tags as $tag): ?>
                    <span class="badge badge-evaluation-tag px-3 py-2 rounded-pill"><?= strtolower($tag) ?></span>
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
                    <span class="text-body-emphasis fw-bold me-2"><?= $totalRewards ?> <i class="bx bxs-hot text-danger"></i></span>
                    <span class="text-body-emphasis fw-bold">2 <i class="bx bxs-zap text-warning"></i></span>
                </div>
                <div class="vr bg-secondary opacity-10 d-none d-md-block" style="height: 20px;"></div>
                <div class="stat-group d-flex align-items-center">
                    <span class="text-body-secondary x-small me-3">Time Remaining</span>
                    <span class="text-body-emphasis fw-bold">06:00 <i class="bx bx-time-five text-body-secondary ms-1"></i></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Interactive Area Section -->
    <div class="container-fluid px-0 mb-3 pb-3">
        <div class="glass-card p-4 p-lg-5 py-xl-5 mt-4">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    
                    <!-- Progress Steps -->
                    <div class="evaluation-progress-steps d-flex justify-content-between align-items-center mb-3 position-relative">
                        <div class="step-item active" data-step="1"><span>1</span></div>
                        <div class="progress-segment" data-segment="1"></div>
                        <div class="step-item" data-step="2"><span>2</span></div>
                        <div class="progress-segment" data-segment="2"></div>
                        <div class="step-item" data-step="3"><span>3</span></div>
                        <div class="progress-segment" data-segment="3"></div>
                        <div class="step-item" data-step="4"><span>4</span></div>
                        <div class="progress-segment" data-segment="4"></div>
                        <div class="step-item" data-step="5"><span>5</span></div>
                    </div>

                    <!-- 1. Start View -->
                    <div id="quiz-start-view" class="quiz-step-view text-center py-5">
                        <button class="btn btn-success rounded-pill px-5 py-3 fw-bold shadow-lg transition-all" onclick="startQuiz()">
                            Begin Quiz <i class="bx bx-play-circle ms-2"></i>
                        </button>
                    </div>

                    <!-- 2. Question View -->
                    <div id="quiz-question-view" class="quiz-step-view d-none">
                        <h4 id="question-text" class="text-center text-body-emphasis mb-5 fw-bold px-md-5"></h4>
                        
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

                        <div class="d-flex justify-content-center gap-3">
                            <button id="submit-btn" class="btn btn-success rounded-pill px-5 py-2 fw-bold d-none transition-all" onclick="submitAnswer()">Submit Answer</button>
                            <button id="next-btn" class="btn btn-outline-success rounded-pill px-5 py-2 fw-bold d-none transition-all" onclick="nextQuestion()">Next Question</button>
                            <button id="result-btn" class="btn btn-primary rounded-pill px-5 py-2 fw-bold d-none transition-all" onclick="showResult()">See Result</button>
                        </div>
                    </div>

                    <!-- 3. Result View -->
                    <div id="quiz-result-view" class="quiz-step-view d-none text-center py-5">
                        <div class="result-circle mx-auto mb-2">
                            <div class="result-score"><span id="score-val">0</span>/5</div>
                        </div>
                        <h3 class="text-body-emphasis fw-bold mb-4">Evaluation Complete!</h3>
                        <p id="result-msg" class="text-body-secondary mb-3"></p>
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

<script>
const quizData = <?= json_encode($quiz['questions']) ?>;
const pointsPerCorrect = <?= $quiz['points_per_correct'] ?? 25 ?>;

let currentStep = 0;
let selectedOption = null;
let score = 0;

function startQuiz() {
    document.getElementById('quiz-start-view').classList.add('d-none');
    document.getElementById('quiz-question-view').classList.remove('d-none');
    loadQuestion();
}

function loadQuestion() {
    const q = quizData[currentStep];
    document.getElementById('question-text').innerText = q.text;
    const btns = document.querySelectorAll('.quiz-option-btn');
    btns.forEach((btn, idx) => {
        btn.querySelector('.option-text').innerText = q.options[idx];
        btn.classList.remove('correct', 'wrong', 'selected');
        btn.disabled = false;
    });
    document.getElementById('submit-btn').classList.add('d-none');
    document.getElementById('next-btn').classList.add('d-none');
    selectedOption = null;
    
    // Update progress steps
    const steps = document.querySelectorAll('.step-item');
    steps.forEach((step, idx) => {
        if(idx === currentStep) step.classList.add('active');
        else if(idx > currentStep) step.classList.remove('active', 'completed-correct', 'completed-wrong');
    });
}

function selectOption(idx) {
    selectedOption = idx;
    const btns = document.querySelectorAll('.quiz-option-btn');
    btns.forEach((btn, i) => {
        if(i === idx) btn.classList.add('selected');
        else btn.classList.remove('selected');
    });
    document.getElementById('submit-btn').classList.remove('d-none');
}

function submitAnswer() {
    const q = quizData[currentStep];
    const btns = document.querySelectorAll('.quiz-option-btn');
    const steps = document.querySelectorAll('.step-item');
    const segments = document.querySelectorAll('.progress-segment');
    const currentStepItem = steps[currentStep];
    
    btns.forEach(btn => btn.disabled = true);
    document.getElementById('submit-btn').classList.add('d-none');

    let isCorrect = selectedOption === q.correct;
    if(isCorrect) {
        btns[selectedOption].classList.add('correct');
        currentStepItem.classList.add('completed-correct');
        score++;
        if (currentStep < segments.length) segments[currentStep].classList.add('correct');
    } else {
        btns[selectedOption].classList.add('wrong');
        currentStepItem.classList.add('completed-wrong');
        if (currentStep < segments.length) segments[currentStep].classList.add('wrong');
    }

    if(currentStep < quizData.length - 1) {
        document.getElementById('next-btn').classList.remove('d-none');
    } else {
        document.getElementById('result-btn').classList.remove('d-none');
    }
}

function nextQuestion() {
    currentStep++;
    loadQuestion();
}

function showResult() {
    document.getElementById('quiz-question-view').classList.add('d-none');
    document.getElementById('quiz-result-view').classList.remove('d-none');
    document.getElementById('score-val').innerText = score;
    
    const totalPoints = score * pointsPerCorrect;
    
    let msg = "";
    if(score === 5) msg = `Priceless! 💎 You earned ${totalPoints} Zeal 🔥. Perfect command!`;
    else if(score >= 3) msg = `Great job! ⚡️ You earned ${totalPoints} Zeal 🔥. Solid grasp!`;
    else msg = `Keep learning! 🔥 You earned ${totalPoints} Zeal 🔥. Practice makes perfect.`;
    document.getElementById('result-msg').innerText = msg;
}
</script>
