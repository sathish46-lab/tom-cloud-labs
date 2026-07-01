<footer class="footer footer-glass px-4 py-2 mt-auto d-flex align-items-center">
    <button class="btn btn-link p-0 text-secondary hover-text-white me-3 page-footer-toggle" type="button"
        onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()">
        <i class="bx bx-menu fs-4"></i>
    </button>
    
    <div class="footer-left">
        Created by 🔥
        <a href="https://sathish46.selfmade.technology" target="_blank" class="footer-link"
            style="text-decoration: none; color: #ff9800">Sathish</a>
        © 2025 |
        <a href="https://tomweb.fun" target="_blank" class="footer-link"
            style="text-decoration: none; color: #ff9800">blogs</a>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="text-info ">
            Page Rendered in <?= Session::getRenderTime() ?>
        </span>
        <span class="text-info">
           |  <?= Session::getVersion() ?>
        </span>
    </div>
</footer>

<style>
/* Smart hide: only show the page footer toggle when the sidebar is hidden */
.sidebar:not(.hide):not(.sidebar-hide) ~ .wrapper .page-footer-toggle {
    display: none !important;
}
</style>