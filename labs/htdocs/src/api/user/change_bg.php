<?php
require_once '../../load.php';

// Only allow authenticated users
if (!Session::getUser()) {
    http_response_code(401);
    die("Unauthorized");
}
?>
<!-- Tab Navigation -->
<div class="px-4 pt-3">
    <ul class="nav nav-pills gap-2" id="bgModalTabs" role="tablist" style="border: none;">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-3 py-2 rounded-pill fw-semibold" id="swatches-tab" data-coreui-toggle="tab" data-coreui-target="#swatches-pane" type="button" role="tab" aria-selected="true"
                style="font-size: 0.82rem; background: rgba(var(--cui-primary-rgb), 0.15); color: var(--cui-primary); border: 1px solid rgba(var(--cui-primary-rgb), 0.25);">
                <i class='bx bxs-palette me-1'></i>Swatches <span class="badge bg-primary bg-opacity-25 text-primary ms-1" style="font-size: 0.65rem;">14</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 rounded-pill fw-semibold" id="themes-tab" data-coreui-toggle="tab" data-coreui-target="#themes-pane" type="button" role="tab" aria-selected="false"
                style="font-size: 0.82rem; color: var(--cui-body-color); opacity: 0.65;">
                <i class='bx bxs-image me-1'></i>Themes <span class="badge bg-body-secondary text-body-secondary ms-1" style="font-size: 0.65rem;">5</span>
            </button>
        </li>
    </ul>
</div>
<div class="modal-body px-4 pb-4 pt-3">
    <div class="tab-content" id="bgModalTabContent">
        <!-- ============ SWATCHES TAB ============ -->
        <div class="tab-pane fade show active" id="swatches-pane" role="tabpanel">
            <div class="d-flex flex-wrap justify-content-center gap-4">
                <?php
                $swatches = [
                    ['name' => 'Default',   'bg' => '#010d12',  'accent' => '#8b91f9', 'gradient' => 'radial-gradient(circle at 35% 30%, #1a3a4a, #010d12 70%)'],
                    ['name' => 'Charcoal',  'bg' => '#0B1E36',  'accent' => '#5dade2', 'gradient' => 'radial-gradient(circle at 35% 30%, #1a3d5c, #0B1E36 70%)'],
                    ['name' => 'Sunset',    'bg' => '#FF6251',  'accent' => '#1a1a2e', 'gradient' => 'radial-gradient(circle at 35% 30%, #ff9a8b, #FF6251 70%)'],
                    ['name' => 'Ocean',     'bg' => '#00BBD6',  'accent' => '#0a2540', 'gradient' => 'radial-gradient(circle at 35% 30%, #5ce1f0, #00BBD6 70%)'],
                    ['name' => 'Gold',      'bg' => '#FFE373',  'accent' => '#3d2e00', 'gradient' => 'radial-gradient(circle at 35% 30%, #fff4b8, #FFE373 70%)'],
                    ['name' => 'Midnight',  'bg' => '#000000',  'accent' => '#a78bfa', 'gradient' => 'radial-gradient(circle at 35% 30%, #3a3a3a, #000000 70%)'],
                    ['name' => 'Arctic',    'bg' => '#ffffff',  'accent' => '#2563eb', 'gradient' => 'radial-gradient(circle at 35% 30%, #ffffff, #d4d4d4 70%)'],
                    ['name' => 'Forest',    'bg' => '#0d5e3a',  'accent' => '#a7f3d0', 'gradient' => 'radial-gradient(circle at 35% 30%, #28a06a, #0d5e3a 70%)'],
                    ['name' => 'Amethyst',  'bg' => '#6B3FA0',  'accent' => '#fbbf24', 'gradient' => 'radial-gradient(circle at 35% 30%, #a06de0, #6B3FA0 70%)'],
                    ['name' => 'Slate',     'bg' => '#3D4F5F',  'accent' => '#67e8f9', 'gradient' => 'radial-gradient(circle at 35% 30%, #6b8da0, #3D4F5F 70%)'],
                    ['name' => 'Teal',      'bg' => '#00796B',  'accent' => '#fde68a', 'gradient' => 'radial-gradient(circle at 35% 30%, #2baf9e, #00796B 70%)'],
                    ['name' => 'Rose',      'bg' => '#C2185B',  'accent' => '#fce7f3', 'gradient' => 'radial-gradient(circle at 35% 30%, #f06292, #C2185B 70%)'],
                    ['name' => 'Deep Sea',  'bg' => '#0D2137',  'accent' => '#38bdf8', 'gradient' => 'radial-gradient(circle at 35% 30%, #1d4a6e, #0D2137 70%)'],
                    ['name' => 'Coral',     'bg' => '#FF7043',  'accent' => '#1e293b', 'gradient' => 'radial-gradient(circle at 35% 30%, #ffab91, #FF7043 70%)'],
                ];
                foreach ($swatches as $swatch): ?>
                    <div class="text-center pointer swatch-sphere-wrap swatch-item" 
                         onclick="TomBG.applySwatchPreset('<?= $swatch['bg'] ?>', '<?= $swatch['accent'] ?>')"
                         data-bg="<?= $swatch['bg'] ?>" data-accent="<?= $swatch['accent'] ?>"
                         style="width: 72px;">
                        <div class="rounded-circle mx-auto mb-2 swatch-sphere dual-sphere d-flex align-items-center justify-content-center position-relative" 
                             style="width: 52px; height: 52px; border: 2px solid <?= $swatch['bg'] ?>; padding: 3px; background: transparent; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);">
                            <div class="w-100 h-100 rounded-circle position-relative" style="background: linear-gradient(to bottom, #1e293b 50%, #ffffff 50%); box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">
                                <span class="dual-sphere-dot" style="background: <?= $swatch['accent'] ?>; border: 2px solid rgba(255,255,255,0.8); width: 14px; height: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.3); position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border-radius: 50%;"></span>
                            </div>
                            <div class="active-badge position-absolute shadow-sm" style="top: -4px; right: -4px; width: 16px; height: 16px; background: #22c55e; border-radius: 50%; color: white; display: none; align-items: center; justify-content: center; font-size: 11px; border: 2px solid var(--cui-body-bg); z-index: 2;">
                                <i class='bx bx-check fw-bold'></i>
                            </div>
                        </div>
                        <span class="d-block text-body-emphasis" style="font-size: 0.7rem; font-weight: 500; opacity: 0.85; line-height: 1.2;"><?= $swatch['name'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Your Custom Colors Section -->
            <div class="mt-4 pt-3" style="border-top: 1px solid rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.08);">
                <h6 class="fw-semibold text-body-emphasis mb-3" style="font-size: 0.82rem; opacity: 0.7;">
                    <i class='bx bxs-brush me-1'></i>Your Custom Colors
                </h6>
                <div id="dynamic-custom-slots" class="d-flex justify-content-center gap-4 align-items-start flex-wrap">
                    <!-- Dynamic custom slots will be injected here by JS -->
                    <!-- Add new custom theme -->
                    <div class="text-center pointer swatch-sphere-wrap create-new-slot" 
                         onclick="TomBG.setMode('plain'); var m = coreui.Modal.getInstance(document.getElementById('bgSelectModal')); if(m)m.hide(); var dm = new coreui.Modal(document.getElementById('plainColorModal')); dm.show();"
                         style="width: 72px;">
                        <div class="rounded-circle mx-auto mb-2 swatch-sphere d-flex align-items-center justify-content-center"
                             style="width: 52px; height: 52px; background: radial-gradient(circle at 35% 30%, rgba(255,255,255,0.1), rgba(255,255,255,0.02) 70%); box-shadow: 0 6px 16px rgba(0,0,0,0.15); border: 2px dashed rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.2); transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);">
                            <i class='bx bx-plus text-body-emphasis' style="font-size: 1.3rem; opacity: 0.4;"></i>
                        </div>
                        <span class="d-block text-body-secondary" style="font-size: 0.65rem; font-weight: 500; line-height: 1.2;">Create New</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ THEMES TAB ============ -->
        <div class="tab-pane fade" id="themes-pane" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                        onclick="TomBG.setMode('robo')" 
                        style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('/assets/Background_Img/robo/robo.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Robot Mode</h6>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                        onclick="TomBG.setMode('ninja')" 
                        style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('/assets/Background_Img/ninja/ninja.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Ninja Mode</h6>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                        onclick="TomBG.setMode('robotower')" 
                        style="background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('/assets/Background_Img/RoboTower/robo_tower.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Robo Tower</h6>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                        onclick="TomBG.setMode('spiderman')" 
                        style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('/assets/Background_Img/spiderman/spiderman.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Spiderman Mode</h6>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-preview rounded-3 p-4 text-center pointer border border-white border-opacity-10 transition-all hover-scale" 
                        onclick="TomBG.setMode('ironman')" 
                        style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('/assets/Background_Img/IronMan/0.jpg'); background-size: cover; background-position: center; min-height: 90px; display: flex; align-items: center; justify-content: center;">
                        <h6 class="fw-bold m-0 text-white" style="font-size: 0.85rem;">Iron Man Mode</h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
