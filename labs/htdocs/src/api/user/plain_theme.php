<?php
require_once '../../load.php';

// Only allow authenticated users
if (!Session::getUser()) {
    http_response_code(401);
    die("Unauthorized");
}
?>
<div class="row g-4">
    <!-- ===== LEFT: Color Pickers ===== -->
    <div class="col-lg-7">
        <!-- Background / Accent Toggle -->
        <div class="picker-target-toggle d-flex gap-2 mb-3">
            <button type="button" id="picker-target-bg" class="picker-target-btn active" onclick="TomBG.switchPickerTarget('background')">
                <i class='bx bxs-color-fill me-1'></i>Background
            </button>
            <button type="button" id="picker-target-accent" class="picker-target-btn" onclick="TomBG.switchPickerTarget('accent')">
                <i class='bx bxs-star me-1'></i>Accent
            </button>
        </div>

        <!-- Tabbed Color Designer -->
        <div class="color-designer-tabs d-flex justify-content-center gap-2 mb-3 p-1 rounded-4">
            <button type="button" class="designer-tab active" onclick="TomBG.switchPickerTab('spectrum')" title="Spectrum">
                <i class="bx bxs-grid-alt"></i>
            </button>
            <button type="button" class="designer-tab" onclick="TomBG.switchPickerTab('wheel')" title="Wheel">
                <i class="bx bx-loader-circle"></i>
            </button>
            <button type="button" class="designer-tab" onclick="TomBG.switchPickerTab('sliders')" title="Sliders">
                <i class="bx bx-slider-alt"></i>
            </button>
            <button type="button" class="designer-tab" onclick="TomBG.switchPickerTab('palettes')" title="Palettes">
                <i class="bx bxs-palette"></i>
            </button>
            <button type="button" class="designer-tab" onclick="TomBG.switchPickerTab('pencils')" title="Pencils">
                <i class="bx bx-pencil"></i>
            </button>
        </div>

        <div id="picker-content" class="picker-content">
            <!-- Spectrum Mode -->
            <div id="picker-spectrum" class="picker-mode active">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <label class="small fw-bold text-white m-0 opacity-50 tracking-widest uppercase" style="font-size: 9px;">Spectrum Designer</label>
                    <span class="text-secondary fw-mono" style="font-size: 10px;" id="hex-preview">#0B1E36</span>
                </div>
                <div id="spectrum-map" class="spectrum-map rounded-4 position-relative pointer shadow-inner mb-4">
                    <div class="spectrum-cursor" id="spectrum-cursor"></div>
                </div>
            </div>

            <!-- Wheel Mode -->
            <div id="picker-wheel" class="picker-mode d-none text-center">
                <label class="small fw-bold text-white d-block mb-3 opacity-50 tracking-widest uppercase text-start" style="font-size: 9px;">Color Wheel</label>
                <div class="d-flex justify-content-center mb-3">
                    <div id="color-wheel" class="color-wheel">
                        <div class="wheel-cursor" id="wheel-cursor"></div>
                    </div>
                </div>
            </div>
            
            <!-- Global Brightness (For Wheel & Spectrum) -->
            <div class="brightness-container mb-2 px-2 d-none" id="global-brightness-cont">
                <div class="d-flex justify-content-between small mb-2 opacity-50"><span>Brightness</span><span id="val-bright">100%</span></div>
                <input type="range" id="brightness-slider" min="0" max="100" value="100" class="brightness-slider w-100" oninput="TomBG.updateBrightness(this.value)">
            </div>

            <!-- Sliders Mode -->
            <div id="picker-sliders" class="picker-mode d-none">
                <label class="small fw-bold text-white d-block mb-3 opacity-50 tracking-widest uppercase" style="font-size: 9px;">RGB Sliders</label>
                <div class="slider-group mb-3">
                    <div class="d-flex justify-content-between small mb-1"><span>Red</span><span id="val-r">0</span></div>
                    <input type="range" class="rgb-slider r" min="0" max="255" value="11" oninput="TomBG.updateFromSliders()">
                </div>
                <div class="slider-group mb-3">
                    <div class="d-flex justify-content-between small mb-1"><span>Green</span><span id="val-g">0</span></div>
                    <input type="range" class="rgb-slider g" min="0" max="255" value="30" oninput="TomBG.updateFromSliders()">
                </div>
                <div class="slider-group mb-3">
                    <div class="d-flex justify-content-between small mb-1"><span>Blue</span><span id="val-b">0</span></div>
                    <input type="range" class="rgb-slider b" min="0" max="255" value="54" oninput="TomBG.updateFromSliders()">
                </div>
            </div>

            <!-- Palettes Mode -->
            <div id="picker-palettes" class="picker-mode d-none">
                <label class="small fw-bold text-white d-block mb-3 opacity-50 tracking-widest uppercase" style="font-size: 9px;">Web Palettes</label>
                <div class="palette-grid">
                    <?php 
                    $webColors = ['#F44336','#E91E63','#9C27B0','#673AB7','#3F51B5','#2196F3','#03A9F4','#00BCD4','#009688','#4CAF50','#8BC34A','#CDDC39','#FFEB3B','#FFC107','#FF9800','#FF5722','#795548','#9E9E9E','#607D8B','#000000','#FFFFFF'];
                    foreach($webColors as $c): ?>
                        <div class="palette-item" style="background: <?= $c ?>" onclick="TomBG.setPlainColor('<?= $c ?>')"></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Pencils Mode -->
            <div id="picker-pencils" class="picker-mode d-none">
                <label class="small fw-bold text-white d-block mb-3 opacity-50 tracking-widest uppercase" style="font-size: 9px;">Professional Pencils</label>
                <div class="pencils-container">
                    <?php 
                    $pencils = [
                        '#010d12', '#1e293b', '#334155', '#475569', '#64748b', '#94a3b8', '#cbd5e1', '#f8fafc',
                        '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16', '#22c55e', '#10b981', '#06b6d4',
                        '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e', '#71717a'
                    ];
                    foreach($pencils as $p): ?>
                        <div class="pencil-item" data-color="<?= $p ?>" style="--pencil-color: <?= $p ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT: Contrast + Preview ===== -->
    <div class="col-lg-5">
        <!-- Contrast Accessibility Scores -->
        <div class="contrast-panel rounded-4 p-3 mb-3" style="background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.06);">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="fw-bold text-white" style="font-size: 0.9rem;" id="contrast-overall-score">—</span>
                <div>
                    <span class="fw-semibold text-warning" style="font-size: 0.8rem;" id="contrast-overall-label">Checking...</span>
                    <div class="d-flex gap-1 mt-1" id="contrast-stars">
                        <i class='bx bxs-star' style="font-size: 0.7rem; color: #fbbf24;"></i>
                        <i class='bx bx-star' style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"></i>
                        <i class='bx bx-star' style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"></i>
                        <i class='bx bx-star' style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"></i>
                        <i class='bx bx-star' style="font-size: 0.7rem; color: rgba(255,255,255,0.2);"></i>
                    </div>
                </div>
            </div>
            
            <div class="d-flex flex-column gap-2" id="contrast-rows-container">
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-white opacity-70" style="font-size: 0.72rem; min-width: 95px;" id="contrast-text-label">Text on bg</span>
                    <div class="contrast-bar flex-grow-1 mx-2"><div id="contrast-text-bar" class="contrast-bar-fill" style="width: 50%;"></div></div>
                    <span class="text-white fw-bold" style="font-size: 0.72rem; min-width: 28px; text-align: right;" id="contrast-text-val">—</span>
                    <span class="contrast-badge badge-aaa ms-2" id="contrast-text-badge">—</span>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-white opacity-70" style="font-size: 0.72rem; min-width: 95px;" id="contrast-accent-label">Accent on bg</span>
                    <div class="contrast-bar flex-grow-1 mx-2"><div id="contrast-accent-bar" class="contrast-bar-fill" style="width: 50%;"></div></div>
                    <span class="text-white fw-bold" style="font-size: 0.72rem; min-width: 28px; text-align: right;" id="contrast-accent-val">—</span>
                    <span class="contrast-badge badge-fail ms-2" id="contrast-accent-badge">—</span>
                </div>
            </div>
            
            <!-- Semantic Colors Preview -->
            <div class="d-flex gap-3 mt-3 pt-2" style="border-top: 1px solid rgba(255,255,255,0.06);">
                <span style="font-size: 0.7rem;"><span class="rounded-circle d-inline-block me-1" style="width: 8px; height: 8px; background: #22c55e;"></span><span class="text-white opacity-60">Success</span></span>
                <span style="font-size: 0.7rem;"><span class="rounded-circle d-inline-block me-1" style="width: 8px; height: 8px; background: #ef4444;"></span><span class="text-white opacity-60">Danger</span></span>
                <span style="font-size: 0.7rem;"><span class="rounded-circle d-inline-block me-1" style="width: 8px; height: 8px; background: #f59e0b;"></span><span class="text-white opacity-60">Warning</span></span>
                <span style="font-size: 0.7rem;"><span class="rounded-circle d-inline-block me-1" style="width: 8px; height: 8px; background: #3b82f6;"></span><span class="text-white opacity-60">Info</span></span>
            </div>
        </div>

        <!-- Preview Spheres: Background / Preview / Accent -->
        <div class="d-flex justify-content-center gap-4 mb-3">
            <div class="text-center">
                <div class="rounded-circle mx-auto mb-2 designer-preview-sphere"
                     id="designer-sphere-bg"
                     style="width: 58px; height: 58px; box-shadow: 0 6px 16px rgba(0,0,0,0.4), inset 0 -4px 8px rgba(0,0,0,0.3), inset 0 2px 4px rgba(255,255,255,0.15); border: 2px solid rgba(255,255,255,0.1);"></div>
                <span class="text-white d-block" style="font-size: 0.68rem; font-weight: 600; opacity: 0.7;">Background</span>
            </div>
            <div class="text-center">
                <div class="rounded-circle mx-auto mb-2 designer-preview-sphere position-relative"
                     id="designer-sphere-preview"
                     style="width: 68px; height: 68px; box-shadow: 0 8px 24px rgba(0,0,0,0.5), inset 0 -6px 12px rgba(0,0,0,0.35), inset 0 3px 6px rgba(255,255,255,0.2); border: 3px solid rgba(255,255,255,0.15);">
                    <span class="rounded-circle position-absolute" id="designer-sphere-preview-dot"
                          style="width: 22px; height: 22px; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 2px solid rgba(255,255,255,0.2); box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></span>
                </div>
                <span class="text-white d-block" style="font-size: 0.68rem; font-weight: 600; opacity: 0.7;">Preview</span>
            </div>
            <div class="text-center">
                <div class="rounded-circle mx-auto mb-2 designer-preview-sphere"
                     id="designer-sphere-accent"
                     style="width: 58px; height: 58px; box-shadow: 0 6px 16px rgba(0,0,0,0.4), inset 0 -4px 8px rgba(0,0,0,0.3), inset 0 2px 4px rgba(255,255,255,0.15); border: 2px solid rgba(255,255,255,0.1);"></div>
                <span class="text-white d-block" style="font-size: 0.68rem; font-weight: 600; opacity: 0.7;">Accent</span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-2 justify-content-end mt-3">
            <button type="button" class="enhance-btn" onclick="TomBG.enhanceAccent()">
                <i class='bx bxs-magic-wand me-1'></i>Enhance
            </button>
            <button type="button" class="btn btn-light rounded-pill px-4 fw-semibold" style="font-size: 0.82rem;"
                    onclick="var slot = TomBG.currentEditingSlot !== null ? TomBG.currentEditingSlot : 0; TomBG.saveCustomTheme(slot); var m = coreui.Modal.getInstance(document.getElementById('plainColorModal')); if(m)m.hide();">
                Save
            </button>
        </div>
    </div>
</div>
