/**
 * Quiz Engine Module
 * Handles dynamic question loading, state management, and scoring
 */

let currentStep = 0;
let selectedOption = null;
let score = 0;
let timeLeft = 0;
let timerInterval = null;
let userAnswers = []; // Store answers securely to send at the end

/**
 * Initialize Dynamic Progress Bar
 */
function initProgressBar() {
    if (!window.QuizEngineConfig || !window.QuizEngineConfig.totalQuestions) return;
    const totalQ = window.QuizEngineConfig.totalQuestions;
    const container = document.getElementById('evaluation-progress-container');
    if (!container) return;
    container.innerHTML = '';
    
    for (let idx = 0; idx < totalQ; idx++) {
        const step = document.createElement('div');
        step.className = 'step-item' + (idx === 0 ? ' active' : '');
        step.setAttribute('data-step', idx + 1);
        step.innerHTML = `<span>${idx + 1}</span>`;
        container.appendChild(step);
        
        if (idx < totalQ - 1) {
            const segment = document.createElement('div');
            segment.className = 'progress-segment';
            segment.setAttribute('data-segment', idx + 1);
            container.appendChild(segment);
        }
    }
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
    const submitBtn = document.getElementById('submit-btn');
    if (submitBtn) submitBtn.disabled = false;
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
    formData.append('total', window.QuizEngineConfig.totalQuestions);
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
    if (!config || !config.totalQuestions) return;
    
    const qCount = config.totalQuestions;
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
    if (!window.QuizEngineConfig || !window.QuizEngineConfig.totalQuestions) {
        console.error("[Quiz Engine] Quiz config missing or empty!");
        alert("Sorry, the quiz config could not be loaded.");
        return;
    }

    const qText = document.getElementById('question-text');
    if (qText) {
        qText.innerHTML = '<div class="text-center"><i class="bx bx-loader-alt bx-spin fs-4"></i> Loading question...</div>';
    }

    const btns = document.querySelectorAll('.quiz-option-btn');
    btns.forEach(btn => {
        btn.disabled = true;
        btn.classList.remove('correct', 'wrong', 'selected');
        const optText = btn.querySelector('.option-text');
        if (optText) optText.innerHTML = '&nbsp;';
    });

    const formData = new FormData();
    formData.append('hash', window.QuizEngineConfig.hash);
    formData.append('question_index', currentStep);

    fetch('/api/quiz/get_question', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status !== 'success' || !data.question) {
            if (qText) qText.innerHTML = '<span class="text-danger">Failed to load question.</span>';
            return;
        }

        const q = data.question;

        if (qText) {
            let rawText = q.text || q.question || "Unknown Question";
            // Parse backticks into beautifully styled inline code blocks
            rawText = rawText.replace(/`([^`]+)`/g, '<code class="text-info bg-info bg-opacity-10 px-2 py-1 rounded mx-1" style="font-family: var(--cui-font-monospace, monospace); font-weight: 600;">$1</code>');
            qText.innerHTML = rawText;
        }

        btns.forEach((btn, idx) => {
            const optText = btn.querySelector('.option-text');
            const optionsArray = q.options || q.answers || [];
            if (optText) {
                let optStr = optionsArray[idx] || "";
                optStr = optStr.replace(/`/g, ''); // Remove backticks since options are already styled as code
                optText.innerText = optStr;
            }
            btn.disabled = false;
        });
        
        resetButtons();
        selectedOption = null;
        
        const steps = document.querySelectorAll('.step-item');
        steps.forEach((step, idx) => {
            if(idx === currentStep) step.classList.add('active');
            else if(idx > currentStep) step.classList.remove('active', 'completed-correct', 'completed-wrong');
        });
    })
    .catch(err => {
        console.error("Error loading question:", err);
        if (qText) qText.innerHTML = '<span class="text-danger">Network error loading question.</span>';
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
    if (!window.QuizEngineConfig || !window.QuizEngineConfig.totalQuestions) return;
    const totalQ = window.QuizEngineConfig.totalQuestions;
    const btns = document.querySelectorAll('.quiz-option-btn');
    const steps = document.querySelectorAll('.step-item');
    const segments = document.querySelectorAll('.progress-segment');
    const currentStepItem = steps[currentStep];
    
    // Store user's answer locally to send at the end
    userAnswers[currentStep] = selectedOption;

    btns.forEach(btn => btn.disabled = true);
    resetButtons();

    // Loading state while checking securely with backend
    let submitBtn = document.getElementById('submit-btn');
    let originalText = "";
    if (submitBtn) {
        submitBtn.classList.remove('d-none');
        originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Checking...';
        submitBtn.disabled = true;
    }

    const formData = new FormData();
    formData.append('hash', window.QuizEngineConfig.hash);
    formData.append('question_index', currentStep);
    formData.append('selected_option', selectedOption);
    formData.append('answers', JSON.stringify(userAnswers));

    fetch('/api/quiz/check_answer', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) {
            submitBtn.innerHTML = originalText;
            submitBtn.classList.add('d-none');
        }

        let isCorrect = data.correct === true;
        
        if(isCorrect) {
            btns[selectedOption].classList.add('correct');
            currentStepItem.classList.add('completed-correct');
            score++;
            // Motivational Toast for Correct Answer
            const msg = data.toast_message || "Correct!";
            const title = data.toast_title || "Correct Answer";
            if (window.TomNotify) TomNotify.show(msg, title, "success", 2500);

        } else {
            btns[selectedOption].classList.add('wrong');
            // Reveal the correct option if the backend tells us
            if (typeof data.correct_option !== 'undefined' && btns[data.correct_option]) {
                btns[data.correct_option].classList.add('correct');
            }
            
            currentStepItem.classList.add('completed-wrong');
            if (segments[currentStep]) segments[currentStep].classList.add('wrong');

            // Encouragement Toast for Wrong Answer
            const msg = data.toast_message || "Keep going!";
            const title = data.toast_title || "Keep Going";
            if (window.TomNotify) TomNotify.show(msg, title, "warning", 2500);
        }

        const isLastQuestion = (currentStep >= totalQ - 1);
        if (isLastQuestion) {
            if (timerInterval) clearInterval(timerInterval);
            toggleButton('result-btn', true);
        } else {
            toggleButton('next-btn', true);
        }
    })
    .catch(err => {
        console.error("Error checking answer:", err);
        alert("Network error checking answer. Please try again.");
        if (submitBtn) {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
};

window.nextQuestion = function() {
    currentStep++;
    loadQuestion();
};

window.showResult = function() {
    if (timerInterval) clearInterval(timerInterval);
    if (!window.QuizEngineConfig || !window.QuizEngineConfig.totalQuestions) return;
    const totalQ = window.QuizEngineConfig.totalQuestions;
    const pointsPerCorrect = window.QuizEngineConfig.pointsPerCorrect;
    const quizHash = window.QuizEngineConfig.hash;

    const formData = new FormData();
    formData.append('hash', quizHash);
    formData.append('status', 'completed');
    formData.append('answers', JSON.stringify(userAnswers));

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
        resultScoreContainer.innerHTML = `<span id="score-val">${score}</span>/${totalQ}`;
    }
    
    const totalPoints = score * pointsPerCorrect;
    const ratio = score / totalQ;
    let msg = "";
    if(ratio === 1) msg = `Outstanding! 💎 Perfect command of the subject.`;
    else if(ratio >= 0.6) msg = `Great job! ⚡️ Solid grasp of the concepts.`;
    else msg = `Keep learning! 🔥 Practice makes perfect.`;
    
    const resultMsg = document.getElementById('result-msg');
    if (resultMsg) resultMsg.innerText = msg;
};

document.addEventListener('DOMContentLoaded', () => {
    // Only init if we're on the quiz evaluation page
    if (document.getElementById('quiz-start-view')) {
        initProgressBar();
        preInitTimer();

        // Record unique view
        if (window.QuizEngineConfig && window.QuizEngineConfig.hash) {
            const formData = new FormData();
            formData.append('hash', window.QuizEngineConfig.hash);
            fetch('/api/quiz/record_view', {
                method: 'POST',
                body: formData
            });
        }
    }
});
