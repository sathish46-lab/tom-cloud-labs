<?php
/**
 * Apple-Style Liquid Glass Showcase
 * Demonstrates high-fidelity glassmorphism with dynamic light/dark mode support.
 */
?>

<div class="glass-showcase-container py-5 min-vh-100">
    <div class="container text-center mb-5">
        <h1 class="display-4 fw-bold text-white mb-2 tc-fade-in">Crystal Glass</h1>
        <p class="lead text-white-50 tc-fade-in" style="animation-delay: 0.1s;">High-fidelity refraction and saturation inspired by Apple Design.</p>
    </div>

    <div class="container">
        <div class="row g-4 justify-content-center">
            <!-- 1. The Control Center Card -->
            <div class="col-md-4">
                <div class="apple-glass-card p-4 tc-fade-in" style="animation-delay: 0.2s;">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-box me-3">
                            <i class='bx bxs-widget fs-3' style="color: #00d2ff;"></i>
                        </div>
                        <h4 class="mb-0 fw-bold">System Dashboard</h4>
                    </div>
                    <p class="text-secondary small">Real-time telemetry and atmospheric refraction rendering.</p>
                    <div class="mt-4 pt-3 border-top border-white border-opacity-10">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Processing Power</span>
                            <span class="text-info">84%</span>
                        </div>
                        <div class="progress bg-white bg-opacity-10" style="height: 6px;">
                            <div class="progress-bar bg-info w-75 rounded-pill shadow-glow"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. The Notification Card (Thin Crystal) -->
            <div class="col-md-4">
                <div class="apple-glass-card p-4 tc-fade-in" style="animation-delay: 0.3s;">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-box me-3">
                            <i class='bx bxs-bell fs-3' style="color: #ff007f;"></i>
                        </div>
                        <h4 class="mb-0 fw-bold">Alert Center</h4>
                    </div>
                    <p class="text-secondary small">Smart filtering enabled for high-priority security events.</p>
                    <div class="d-grid gap-2 mt-4">
                        <button class="btn glass-btn text-white small py-2">View Alerts</button>
                        <button class="btn btn-link text-white-50 small text-decoration-none p-0">Clear All</button>
                    </div>
                </div>
            </div>

            <!-- 3. The Info Card (Deep Refraction) -->
            <div class="col-md-4">
                <div class="apple-glass-card p-4 tc-fade-in" style="animation-delay: 0.4s;">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-box me-3">
                            <i class='bx bxs-cloud-upload fs-3' style="color: #00ff88;"></i>
                        </div>
                        <h4 class="mb-0 fw-bold">Cloud Sync</h4>
                    </div>
                    <p class="text-secondary small">Last synced 2 minutes ago to Global CDN Edge nodes.</p>
                    <div class="mt-4 d-flex gap-2">
                        <span class="badge rounded-pill bg-success bg-opacity-25 text-success border border-success border-opacity-25 px-3 py-2">Stable</span>
                        <span class="badge rounded-pill bg-info bg-opacity-25 text-info border border-info border-opacity-25 px-3 py-2">Edge-01</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* 
   Apple Liquid Glass Design System
   Matches Dashboard toggle classes and light/dark modes
*/

.apple-glass-card {
    /* 1. Base Structure (Clear & Transparent by default) */
    position: relative;
    border-radius: 28px;
    transition: all 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
    overflow: hidden;
    color: white;
    min-height: 220px;
    
    // Default state: No blur, ultra-clean transparency
    background: rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.02) !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    border: 1px solid rgba(var(--cui-emphasis-color-rgb, 255, 255, 255), 0.08) !important;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15) !important;

    /* 2. The "Liquid Glass" Effect (Only when .glass-mode is active) */
    .glass-mode & {
        background: rgba(255, 255, 255, 0.04) !important;
        backdrop-filter: saturate(1.8) brightness(1.05) blur(20px) !important;
        -webkit-backdrop-filter: saturate(1.8) brightness(1.05) blur(20px) !important;
        border: none !important;

        /* Apple Multi-Layer Crystal Shadow (Softened) */
        box-shadow: 
            inset 0 0 0 1px rgba(255, 255, 255, 0.1),  /* Sharp inner border */
            inset 0 1px 0 0 rgba(255, 255, 255, 0.15),  /* Top edge shine */
            rgba(0, 0, 0, 0.35) 0px 10px 25px 0px,      /* Depth shadow */
            rgba(255, 255, 255, 0.02) 0px 0px 15px 0px !important;
    }

    /* Dark Mode Optimization (Deep transparency, no oversaturation) */
    [data-coreui-theme="dark"] & {
        background: rgba(0, 0, 0, 0.42) !important;
        
        .glass-mode & {
            background: rgba(0, 0, 0, 0.08) !important;
            backdrop-filter: saturate(1.8) brightness(1.0) blur(31px) !important;
        }
    }
}

/* Light Mode Overrides */
[data-coreui-theme="light"] .apple-glass-card {
    color: #1a1a1a;
    background: rgba(255, 255, 255, 0.9) !important;
    border: 1px solid rgba(0, 0, 0, 0.06) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04) !important;

    .glass-mode & {
        background: rgba(255, 255, 255, 0.65) !important;
        backdrop-filter: saturate(1.6) brightness(1.02) blur(20px) !important;
        box-shadow: 
            inset 0 0 0 1px rgba(255, 255, 255, 0.8),
            rgba(0, 0, 0, 0.04) 0px 8px 20px 0px !important;
    }

    .text-secondary { color: #444 !important; }
}

/* Helper Components */
.icon-box {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.glass-btn {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.shadow-glow {
    box-shadow: 0 0 15px rgba(0, 210, 255, 0.4);
}

.tc-fade-in {
    opacity: 0;
    transform: translateY(20px);
    animation: tcFadeIn 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
}

@keyframes tcFadeIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
