<?php
$e = Session::get('error_exception');
$reqId = $_SERVER['UNIQUE_ID'] ?? 'N/A';
?>
<style>
.error-card {
    background: rgba(10, 15, 25, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 3rem;
    max-width: 900px;
    margin: 4rem auto;
    color: #e2e8f0;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 100;
}
.error-header {
    display: flex;
    align-items: center;
    gap: 2rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    padding-bottom: 2rem;
}
.error-title {
    font-size: 4rem;
    font-weight: 700;
    margin: 0;
    color: #f1f5f9;
}
.error-subtitle h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    color: #f1f5f9;
}
.error-subtitle p {
    margin: 0;
    color: #94a3b8;
    font-size: 0.95rem;
}
.req-id {
    font-family: monospace;
    color: #cbd5e1;
    font-size: 0.85rem;
    margin-top: 1rem;
}
.error-trace-container {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 0.85rem;
    line-height: 1.6;
    overflow-x: auto;
}
.trace-headline {
    color: #e2e8f0;
    margin-bottom: 1rem;
}
.trace-message {
    color: #ff7b72; /* Red for the actual error message */
}
.trace-line {
    padding-left: 1rem;
    color: #c9d1d9;
}
.trace-keyword {
    color: #ff7b72;
}
.trace-function {
    color: #79c0ff; /* Cyan for function names */
}
.trace-file {
    color: #e2e8f0;
}
.trace-line-num {
    color: #ff7b72;
}
.raw-trace {
    margin-top: 2rem;
    color: #8b949e;
    white-space: pre-wrap;
    font-size: 0.8rem;
}
</style>

<div class="error-card">
    <div class="error-header">
        <h1 class="error-title">Error!</h1>
        <div class="error-subtitle">
            <h4>Oops! You're lost.</h4>
            <p>The page you are looking is broken.</p>
            <div class="req-id">Request ID: <?= htmlspecialchars($reqId) ?></div>
        </div>
    </div>

    <?php if ($e): ?>
    <div class="error-trace-container">
        <div class="trace-headline">
            Caused by: <?= htmlspecialchars(get_class($e)) ?>: <span class="trace-message"><?= htmlspecialchars($e->getMessage()) ?></span>
        </div>
        
        <?php
        // Manually format the first line (the file where error originated)
        $file = basename($e->getFile());
        $line = $e->getLine();
        echo "<div class='trace-line'><span class='trace-keyword'>at</span> <span class='trace-function'>include</span>(<span class='trace-file'>{$file}</span>:<span class='trace-line-num'>{$line}</span>)</div>";

        // Format the trace array
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
    <?php else: ?>
        <p>An unknown error occurred and no exception details were provided.</p>
    <?php endif; ?>
</div>
