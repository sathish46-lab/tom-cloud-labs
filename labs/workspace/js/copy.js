/**
 * Copies raw text string to clipboard and shows toast
 */
function copyText(text, toastMsg = "Text copied to clipboard!") {
    const notifySuccess = () => {
        showToast(toastMsg);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(notifySuccess).catch((err) => {
            console.error("Clipboard API failed: ", err);
            // If it still fails, try fallback synchronously (though it may be blocked)
            if (fallbackCopyText(text)) {
                notifySuccess();
            }
        });
    } else {
        // In insecure contexts, run fallback synchronously immediately during the click event!
        if (fallbackCopyText(text)) {
            notifySuccess();
        }
    }
}

function fallbackCopyText(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    textArea.style.opacity = "0";
    
    // Prevent Modal Focus Trap from stealing focus!
    // Append the hidden textarea to the active modal if one is open, otherwise use body.
    const activeModal = document.querySelector('.modal.show');
    const container = activeModal ? activeModal : document.body;
    
    container.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    let successful = false;
    try {
        successful = document.execCommand('copy');
    } catch (err) {
        console.error('Fallback copy failed', err);
    }
    
    container.removeChild(textArea);
    return successful;
}

/**
 * Copies value from an input element by ID and shows toast
 */
function copyValue(elementId) {
    const copyTextEl = document.getElementById(elementId);
    if (copyTextEl) {
        copyText(copyTextEl.value, "Command copied!");
    }
}

/**
 * CoreUI Toast Trigger Function
 */
function showToast(message) {
    if (window.TomNotify && typeof window.TomNotify.show === 'function') {
        window.TomNotify.show(message, "Success", "success", 3000);
    } else {
        const toastEl = document.getElementById('copyToast');
        const messageEl = document.getElementById('toast-message');
        if (toastEl && messageEl) {
            messageEl.innerText = message;
            const toast = new coreui.Toast(toastEl, {
                delay: 3000,
                autohide: true
            });
            toast.show();
        }
    }

    // Optional: Visual feedback on the clicked button
    try {
        if (typeof event !== 'undefined' && event && event.currentTarget) {
            const btn = event.currentTarget;
            const icon = btn.querySelector('i');
            if (icon) {
                const originalClass = icon.className;
                icon.className = 'bx bx-check text-success';
                setTimeout(() => { icon.className = originalClass; }, 2000);
            }
        }
    } catch (e) {
        console.error(e);
    }
}