<div class="card-body p-0 flex-grow-1 overflow-auto hide-scrollbar d-flex flex-column bg-transparent position-relative">
    <div class="mermaid-container w-100 h-100 p-4 d-flex justify-content-center">
        <div class="mermaid" id="course-mermaid-map">
            mindmap
              root(("<div class='fw-bold fs-5' style='color: var(--cui-body-color) !important;'><?= htmlspecialchars($lesson['title']) ?></div>"))
<?php
$mod_local_idx = 1;
foreach ($modules as $mod_name => $mod_chapters) {
    $clean_mod_name = preg_replace('/^\d+[\.\)]\s*/', '', $mod_name);
    // Replace non-alphanumeric characters for mermaid ID safety
    $mod_id = 'mod_' . preg_replace('/[^a-zA-Z0-9]/', '', $clean_mod_name);
    echo "                {$mod_id}[\"<div class='d-flex align-items-center gap-2' style='color: var(--cui-body-color) !important;'><span class='badge bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm' style='width: 22px; height: 22px; font-size: 0.75rem; font-weight: 600;'>{$mod_local_idx}</span><span class='fw-medium' style='color: inherit !important;'>" . htmlspecialchars($clean_mod_name) . "</span></div>\"]\n";
    $chap_local_idx = 1;
    foreach ($mod_chapters as $chap) {
        $clean_chap_title = preg_replace('/^\d+[\.\)]\s*/', '', $chap['title']);
        $url = "/learn/lesson/{$lesson['_id']}/chapter/{$chap['_id']}";
        echo "                  chap_{$chap['_id']}[\"<a href='{$url}' hx-boost='false' class='text-decoration-none ajax-chapter-load d-flex align-items-center gap-2' style='color: var(--cui-body-color) !important; display: flex;'><span class='badge bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm' style='width: 22px; height: 22px; font-size: 0.75rem; font-weight: 600;'>{$chap_local_idx}</span><span class='text-start fw-medium' style='color: inherit !important;'>{$clean_chap_title}</span></a>\"]\n";
        $chap_local_idx++;
    }
    $mod_local_idx++;
}
?>
        </div>
    </div>
</div>

<!-- Ensure mermaid is loaded and initialized -->
<script src="/assets/sna_js/mermaid.min.js"></script>
<script>
    // Sometimes HTMX loads this, so we need to run immediately if DOM is ready
    function initMermaid() {
        if (typeof mermaid !== 'undefined') {
            mermaid.initialize({ 
                startOnLoad: false, 
                securityLevel: 'loose', 
                theme: 'dark',
                mindmap: {
                    padding: 15,
                    useMaxWidth: false
                }
            });
            mermaid.init(undefined, document.querySelectorAll('.mermaid'));
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", initMermaid);
    } else {
        initMermaid();
    }
</script>
