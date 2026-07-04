<?php
/**
 * AI Quiz Generation Hub: Live progress and background processing.
 */
use TomLabs\Labs\Quiz;

$topicId = $_GET['topic'] ?? null;
$subtopicId = $_GET['subtopic'] ?? null;
$diff = $_GET['diff'] ?? 'normal';

if (!$topicId || !$subtopicId) {
    header("Location: /quiz");
    exit;
}

$topic = Quiz::getCategory($topicId);
$subtopic = Quiz::getSubtopic($subtopicId);

if (!$topic || !$subtopic) {
    header("Location: /quiz");
    exit;
}

// Start the generation job
$response = Quiz::startGeneration($topicId, $subtopicId, $diff);
$jobId = $response['id'];

Session::$pageTitle = "AI is Generating: " . $subtopic['title'];
?>

<div class="quiz-gen-view d-flex align-items-center justify-content-center min-vh-100 fade-in">
    <div class="container text-center">
        <div class="gen-card glass-card p-5 mx-auto" style="max-width: 500px;">
            <div class="mb-4">
                <div class="ai-loader-container mb-4">
                    <div class="ai-pulse"></div>
                    <i class="bx bxs-brain ai-icon"></i>
                </div>
                <h2 class="fs-4 fw-bold theme-text mb-2">Generating Your Technical Challenge</h2>
                <p class="text-body-secondary small">Gemini AI is crafting a professional quiz for <strong><?= $subtopic['title'] ?></strong>.</p>
                <div class="x-small text-white-50 mt-1 opacity-50">PID: <?= $response['pid'] ?></div>
            </div>

            <div class="progress-wrapper mb-3">
                <div class="progress rounded-pill bg-white bg-opacity-10" style="height: 12px;">
                    <div id="genProgress" class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                         role="progressbar" style="width: 5%"></div>
                </div>
                <div class="d-flex justify-content-between x-small mt-2 theme-text opacity-75">
                    <span id="genStatus">Initializing...</span>
                    <span id="genPercent">5%</span>
                </div>
            </div>

            <div id="attemptInfo" class="x-small text-body-tertiary mb-3">
                Attempt: <span id="attemptCount">1</span> / 3
            </div>

            <div class="gen-footer x-small text-body-tertiary">
                <i class="bx bx-info-circle me-1"></i>This usually takes 10-15 seconds.
            </div>
        </div>
    </div>
</div>

<style>
.ai-loader-container {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ai-icon {
    font-size: 3rem;
    color: var(--bs-success);
    z-index: 2;
}
.ai-pulse {
    position: absolute;
    width: 100%;
    height: 100%;
    background: rgba(25, 135, 84, 0.2);
    border-radius: 50%;
    animation: ai-pulse 2s infinite ease-in-out;
}
@keyframes ai-pulse {
    0% { transform: scale(0.8); opacity: 0.8; }
    50% { transform: scale(1.2); opacity: 0.3; }
    100% { transform: scale(0.8); opacity: 0.8; }
}
.gen-card {
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const jobId = '<?= $jobId ?>';
    const progressBar = document.getElementById('genProgress');
    const statusText = document.getElementById('genStatus');
    const percentText = document.getElementById('genPercent');
    const attemptText = document.getElementById('attemptCount');

    const checkStatus = () => {
        fetch(`/api/quiz/job_status.php?job_id=${jobId}`)
            .then(res => res.json())
            .then(data => {
                // Update basic stats
                const pct = data.percentage || 5;
                progressBar.style.width = pct + '%';
                percentText.innerText = pct + '%';
                statusText.innerText = data.status_text || 'Processing...';
                attemptText.innerText = data.generation_attempt || 1;

                if (data.generation_success && data.result_hash) {
                    progressBar.style.width = '100%';
                    percentText.innerText = '100%';
                    statusText.innerText = 'Success! Redirecting...';
                    
                    setTimeout(() => {
                        htmx.ajax('GET', `/quiz/v/${data.result_hash}`, {target: '#main-content'});
                    }, 1000);
                } else if (data.generation_failed) {
                    statusText.innerText = 'Error: ' + (data.status_text || 'Generation failed.');
                    progressBar.classList.remove('bg-success');
                    progressBar.classList.add('bg-danger');
                } else {
                    // Continue polling
                    setTimeout(checkStatus, 1500);
                }
            })
            .catch(err => {
                console.error('Status check failed:', err);
                setTimeout(checkStatus, 3000);
            });
    };

    // Start polling
    setTimeout(checkStatus, 1000);
});
</script>
