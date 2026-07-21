<?php
// /src/template/pages/instances/manage/files.php
// Real, DB-backed file manager (Copy-on-Write template inheritance).
// Content is rendered client-side by labs/workspace/js/files.js via AJAX.
$slug = $_GET['slug'] ?? '';
?>
<div class="card blur border-0 rounded-4 p-4 shadow-lg mb-4" id="filesManager" data-slug="<?= htmlspecialchars($slug) ?>">
    <!-- Row 1: title (left) + Open in editor (right) -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h5 class="fw-bold theme-text m-0 d-flex align-items-center gap-2">
            <i class='bx bx-folder-open fs-4'></i> Scaffolded files
            <span class="instance-badge text-bg-soft-secondary fw-normal">editor: reaped</span>
        </h5>
        <button class="btn btn-primary rounded-pill px-4 btn-sm fw-bold" data-editor-open>
            <i class='bx bx-code-alt'></i> Open in editor
        </button>
    </div>

    <!-- Row 2: New file / folder / upload (left) + Save (right) -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div class="d-flex gap-2">
            <button class="btn instance-action-btn instance-action-danger btn-sm rounded-pill fw-bold px-3" data-new-file>
                <i class='bx bx-file-blank'></i> New file
            </button>
            <button class="btn instance-action-btn instance-action-danger btn-sm rounded-pill fw-bold px-3" data-new-folder>
                <i class='bx bx-folder'></i> New folder
            </button>
            <button class="btn instance-action-btn instance-action-danger btn-sm rounded-pill fw-bold px-3" data-upload-file>
                <i class='bx bx-upload'></i> Upload asset
            </button>
            <input type="file" id="fileUploadInput" class="d-none" data-upload-input>
        </div>
        <div class="d-flex gap-2">
            <button class="btn instance-action-btn btn-sm rounded-pill fw-bold px-3" data-refresh-files>
                <i class='bx bx-refresh'></i> Refresh
            </button>
            <button class="btn instance-action-btn instance-action-danger btn-sm rounded-pill fw-bold px-3" data-delete-file disabled>
                <i class='bx bx-trash'></i> Delete
            </button>
            <button class="btn instance-action-btn instance-action-primary btn-sm rounded-pill px-4 fw-bold" data-save-file disabled>
                <i class='bx bxs-save'></i> Save <span class="fw-normal opacity-75 ms-1" style="font-size: 0.7rem;">Ctrl+S</span>
            </button>
        </div>
    </div>

    <!-- Row 3: file tree (left) + file content (right) -->
    <div class="instance-file-tree">
        <div class="instance-file-sidebar">
            <div class="instance-tree" id="fileTree" aria-live="polite">
                <div class="text-secondary small py-3 text-center">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                </div>
            </div>
        </div>
        <div class="instance-file-editor">
            <div class="instance-editor-header">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <i class='bx bx-file-blank text-editor-accent'></i>
                    <span class="instance-editor-filename text-truncate" id="editorFileName">No file selected</span>
                    <!-- <span class="instance-editor-lang ms-1" id="editorLang"></span> -->
                    <span class="instance-editor-modified d-none" id="editorModified" title="You have unsaved changes">
                        <span class="instance-editor-dot"></span> modified
                    </span>
                </div>
            </div>
            <div class="instance-editor-body">
                <textarea class="instance-code-editor" id="fileEditor" placeholder="# Select a file from the tree to view and edit" disabled spellcheck="false"></textarea>
                <div class="instance-editor-overlay d-none" id="editorOverlay">
                    <div class="text-center">
                        <i class='bx bx-file fs-1 opacity-50'></i>
                        <div class="mt-2" id="editorOverlayText">Select a file to view its contents</div>
                    </div>
                </div>
            </div>
            <div class="instance-editor-status">
                <div class="d-flex align-items-center gap-3" id="editorMeta"></div>
                <div class="instance-editor-stats ms-auto" id="editorStatus">Ln 1, Col 1 &nbsp;·&nbsp; 0 words</div>
            </div>
        </div>
    </div>

    <div class="text-secondary opacity-75 mt-3 small">
        Quick edits save straight to the template (and into its version history). For multi-file refactors, a terminal, or extensions use <strong>Open in editor</strong>—the full VS Code editor. Binary files can only be managed there.
    </div>
</div>
