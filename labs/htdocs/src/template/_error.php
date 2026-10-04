<?php
$e = Session::get('error_exception');
$reqId = $_SERVER['UNIQUE_ID'] ?? 'N/A';
$ref = (string)(Session::get('error_ref') ?? '');
if ($ref === '' && function_exists('errors_report') && $e) {
    // Reached without going through the global handler — audit it now.
    $ref = errors_report([
        'context' => 'exception.page',
        'message' => $e->getMessage(),
        'extra'   => ['class' => get_class($e), 'file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => errors_excerpt($e->getTraceAsString(), 2000)],
    ]);
    Session::set('error_ref', $ref);
}
$isAdmin = false;
try { $isAdmin = AuthMiddleware::isAdmin(); } catch (Throwable $ignored) {}
?>
<style>
.error-card {
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 16px;
    padding: 3rem;
    max-width: 900px;
    margin: 4rem auto;
    color: #333;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 100;
}
html[data-coreui-theme="dark"] .error-card {
    background: rgba(10, 15, 25, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
}

.error-header {
    display: flex;
    align-items: center;
    gap: 2rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    padding-bottom: 2rem;
}
html[data-coreui-theme="dark"] .error-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.error-title {
    font-size: 4rem;
    font-weight: 700;
    margin: 0;
    color: #1e293b;
}
html[data-coreui-theme="dark"] .error-title {
    color: #f1f5f9;
}

.error-subtitle h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: #334155;
}
html[data-coreui-theme="dark"] .error-subtitle h4 {
    color: #f1f5f9;
}

.error-subtitle p {
    margin: 0;
    color: #64748b;
    font-size: 0.95rem;
}
html[data-coreui-theme="dark"] .error-subtitle p {
    color: #94a3b8;
}

.req-id {
    font-family: monospace;
    color: #94a3b8;
    font-size: 0.85rem;
    margin-top: 1rem;
}
html[data-coreui-theme="dark"] .req-id {
    color: #cbd5e1;
}

.error-trace-container {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 0.85rem;
    line-height: 1.6;
    overflow-x: auto;
}
.trace-headline {
    color: #334155;
    margin-bottom: 1rem;
}
html[data-coreui-theme="dark"] .trace-headline {
    color: #e2e8f0;
}

.trace-message {
    color: #dc2626; /* Red */
}
html[data-coreui-theme="dark"] .trace-message {
    color: #ff7b72;
}

.trace-line {
    padding-left: 1rem;
    color: #475569;
}
html[data-coreui-theme="dark"] .trace-line {
    color: #c9d1d9;
}

.trace-keyword {
    color: #dc2626;
}
html[data-coreui-theme="dark"] .trace-keyword {
    color: #ff7b72;
}

.trace-function {
    color: #0284c7; /* Cyan/Blue */
}
html[data-coreui-theme="dark"] .trace-function {
    color: #79c0ff;
}

.trace-file {
    color: #334155;
}
html[data-coreui-theme="dark"] .trace-file {
    color: #e2e8f0;
}

.trace-line-num {
    color: #dc2626;
}
html[data-coreui-theme="dark"] .trace-line-num {
    color: #ff7b72;
}

.raw-trace {
    margin-top: 2rem;
    color: #94a3b8;
    white-space: pre-wrap;
    font-size: 0.8rem;
}
html[data-coreui-theme="dark"] .raw-trace {
    color: #8b949e;
}
</style>

<div class="error-card">
    <div class="error-header">
        <h1 class="error-title">Oops!</h1>
        <div class="error-subtitle">
            <h4>Something went wrong on our side.</h4>
            <p>This is our fault, not yours. Please try again — if it keeps happening,
               contact an admin and quote the reference below so we can find it.</p>
            <div class="req-id">Reference: <code><?= htmlspecialchars($ref !== '' ? $ref : $reqId) ?></code></div>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.5rem;">
        <a href="/home" class="btn btn-primary btn-sm rounded-pill px-4" data-no-boost="true">Back to Labs</a>
        <a href="javascript:location.reload()" class="btn btn-outline-secondary btn-sm rounded-pill px-4">Try again</a>
    </div>

    <?php if ($isAdmin && $e): ?>
    <div class="error-trace-container" style="margin-top:2rem;">
        <div class="trace-headline">
            Caused by: <?= htmlspecialchars(get_class($e)) ?>: <span class="trace-message"><?= htmlspecialchars($e->getMessage()) ?></span>
        </div>

        <?php
        $file = basename($e->getFile());
        $line = $e->getLine();
        echo "<div class='trace-line'><span class='trace-keyword'>at</span> <span class='trace-function'>include</span>(<span class='trace-file'>{$file}</span>:<span class='trace-line-num'>{$line}</span>)</div>";

        $trace = $e->getTrace();
        foreach ($trace as $t) {
            $tfile = isset($t['file']) ? basename($t['file']) : 'unknown';
            $tline = $t['line'] ?? '0';
            $tclass = isset($t['class']) ? htmlspecialchars($t['class']) . htmlspecialchars($t['type']) : '';
            $tfunc = isset($t['function']) ? htmlspecialchars($t['function']) : '';

            echo "<div class='trace-line'><span class='trace-keyword'>at</span> {$tclass}<span class='trace-function'>{$tfunc}</span>(<span class='trace-file'>{$tfile}</span>:<span class='trace-line-num'>{$tline}</span>)</div>";
        }
        ?>

        <div class="raw-trace">
Stack Trace:
<?= htmlspecialchars($e->getTraceAsString()) ?>
        </div>
    </div>
    <?php endif; ?>
</div>
