<!-- Code Info Modal (Simplified IDE Launch) -->
<div class="modal fade" id="codeInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg blur rounded-4 overflow-hidden modal-code-access">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold text-white mb-0">Code Server Access</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2" id="codeModalLabName">Lab Name</span>
                </div>
                <div id="codeModalLoading" class="text-center py-5">
                    <div class="spinner-grow text-primary" role="status"></div>
                </div>
                <div id="codeModalOffline" class="text-center py-5 initially-hidden">
                    <i class='bx bx-power-off text-danger fs-1 mb-3'></i>
                    <h6 class="text-white fw-bold">Instance is Offline</h6>
                </div>
                <div id="codeModalContent" class="initially-hidden">
                    <div id="codeFields"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary bg-opacity-25 border-0 fw-bold px-4 rounded-pill" data-coreui-dismiss="modal">Dismiss</button>
                <div id="codeModalActionBtn"></div>
            </div>
        </div>
    </div>
</div>

<!-- Technical Connection Info Modal -->
<div class="modal fade" id="connectionInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg blur rounded-4 overflow-hidden modal-connection-info">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold text-white mb-0">Technical Connection Info</h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-2" id="modalLabName">Lab Name</span>
                </div>
                <div id="modalLoading" class="text-center py-5"><div class="spinner-border text-info" role="status"></div></div>
                <div id="modalOffline" class="text-center py-5 initially-hidden">
                    <i class='bx bx-server text-muted fs-1 mb-3'></i>
                    <h6 class="text-white fw-bold">Offline</h6>
                </div>
                <div id="modalContent" class="initially-hidden">
                    <div id="connectionFields"></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary bg-opacity-25 border-0 fw-bold px-4 rounded-pill w-100" data-coreui-dismiss="modal">Close Details</button>
            </div>
        </div>
    </div>
</div>
