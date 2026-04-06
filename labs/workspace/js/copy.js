/**
 * Copies raw text string to clipboard and shows toast
 */
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast("Text copied to clipboard!");
    });
}

/**
 * Copies value from an input element by ID and shows toast
 */
function copyValue(elementId) {
    const copyText = document.getElementById(elementId);
    if (copyText) {
        navigator.clipboard.writeText(copyText.value).then(() => {
            showToast("Command copied!");
        });
    }
}

/**
 * CoreUI Toast Trigger Function
 */
function showToast(message) {
    const toastEl = document.getElementById('copyToast');
    const messageEl = document.getElementById('toast-message');
    
    // Set the dynamic message
    messageEl.innerText = message;

    // Use CoreUI's built-in Toast component
    const toast = new coreui.Toast(toastEl, {
        delay: 3000, // Show for 3 seconds
        autohide: true
    });
    
    toast.show();

    // Optional: Visual feedback on the clicked button
    if (event && event.currentTarget) {
        const btn = event.currentTarget;
        const icon = btn.querySelector('i');
        const originalClass = icon.className;
        icon.className = 'bx bx-check text-success';
        setTimeout(() => { icon.className = originalClass; }, 2000);
    }
}