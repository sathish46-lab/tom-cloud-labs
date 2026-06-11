<?php if (Session::get('show_session_expired')): ?>
<!-- Real Floating Warning Popup -->
<div id="session-expired-overlay">
    <div class="popup-content">
        <!-- Warning Icon -->
        <div class="icon-container">
            <i class='bx bxs-lock-open-alt bx-tada'></i>
        </div>

        <h2>Session Expired</h2>
        <p>
            Your secure session has timed out. Please sign in again to continue managing your labs and infrastructure.
        </p>

        <div style="display: flex; flex-direction: column; gap: 12px;">
            <a href="/signin" class="btn-signin">
                <i class='bx bx-log-in-circle' style="margin-right: 8px; vertical-align: middle; font-size: 20px;"></i>
                SIGN IN NOW
            </a>
            <a href="/" class="btn-home">
                Return to Lobby
            </a>
        </div>
    </div>
</div>
<?php endif; ?>
