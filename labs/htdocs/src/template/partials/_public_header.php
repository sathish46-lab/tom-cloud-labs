<?php
/**
 * Header for pages rendered without a signed-in user (see IS_PUBLIC_PAGE).
 * Mirrors _sitenav.php's breadcrumb placement but omits the sidebar toggle,
 * search palette, notifications, theme picker and account menu — every one of
 * those assumes Session::getUser() is non-null or a session to write into.
 */
?>
<header class="header header-sticky blur rounded-0 mb-0" style="border-bottom: none; height: 4rem; min-height: 4rem;">
    <div class="container-fluid px-4 h-100 d-flex align-items-center gap-3">
        <nav aria-label="breadcrumb" class="flex-shrink-0">
            <ol id="main-breadcrumb" class="breadcrumb my-0 py-0 mb-0">
                <?php include __DIR__ . '/_breadcrumb.php'; ?>
            </ol>
        </nav>

        <div class="ms-auto d-flex align-items-center gap-2 flex-shrink-0">
            <a href="/" hx-boost="false" data-no-boost="true"
               class="d-flex align-items-center justify-content-center text-secondary text-decoration-none rounded-circle"
               style="width: 32px; height: 32px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); transition: all 0.2s;"
               title="Home">
                <svg class="icon" viewBox="0 0 256 256" width="18" height="18"><use href="/assets/icons/duotone.svg#tom-house"></use></svg>
            </a>
            <a href="/signin" hx-boost="false" data-no-boost="true"
               class="btn btn-sm fw-semibold rounded-pill px-3 btn-primary">Sign in</a>
        </div>
    </div>
</header>
