<?php
// /src/template/partials/instances/manage/files.php
?>
<div class="card blur border-0 rounded-4 p-4 shadow-lg mb-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="fw-bold theme-text m-0 d-flex align-items-center gap-2"><i class='bx bx-folder-open fs-4'></i> Scaffolded files <span class="badge bg-secondary bg-opacity-25 text-secondary fw-normal">editor: reaped</span></h5>
        <button class="btn btn-primary rounded-pill px-4 btn-sm fw-bold" style="background-color: #ff4b2b; border-color: #ff4b2b;"><i class='bx bx-code-alt'></i> Open in editor</button>
    </div>
    
    <div class="file-tree-container">
        <div class="file-tree-sidebar">
            <div class="d-flex gap-2 mb-3">
                <button class="btn btn-outline-danger btn-sm rounded-pill fw-bold border-opacity-50 px-3 flex-grow-1"><i class='bx bx-file-blank'></i> New file</button>
                <button class="btn btn-outline-danger btn-sm rounded-pill fw-bold border-opacity-50 px-3 flex-grow-1"><i class='bx bx-folder'></i> New folder</button>
            </div>
            
            <div class="file-tree">
                <!-- Static tree for now as per original template -->
                <div class="file-tree-item folder"><i class='bx bx-chevron-down'></i> <i class='bx bxs-folder text-secondary'></i> data</div>
                <div class="ms-3">
                    <div class="file-tree-item folder"><i class='bx bx-chevron-down'></i> <i class='bx bxs-folder text-secondary'></i> config</div>
                    <div class="ms-3">
                        <div class="file-tree-item justify-content-between"><div class="d-flex align-items-center gap-2"><i class='bx bxs-file text-info'></i> code-server.yaml</div> <span class="small text-secondary opacity-50">71 B</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="file-tree-editor">
            <div class="editor-toolbar">
                <button class="btn btn-primary btn-sm rounded-pill px-4 fw-bold" style="background-color: #ff4b2b; border: none;"><i class='bx bxs-save'></i> Save <span class="fw-normal opacity-75 ms-1" style="font-size: 0.7rem;">Ctrl+S</span></button>
            </div>
            <div class="code-mockup">
                <!-- Editor content -->
                <div class="d-flex gap-3"><span class="opacity-50 text-end" style="width: 20px;">1</span><span><span class="code-comment"># Select a file to view</span></span></div>
            </div>
        </div>
    </div>
    <div class="text-secondary opacity-75 mt-3 small">
        Quick edits save straight to the template (and into its version history). For multi-file refactors, a terminal, or extensions use <strong>Open in editor</strong>—the full VS Code editor. Binary files can only be managed there.
    </div>
</div>
