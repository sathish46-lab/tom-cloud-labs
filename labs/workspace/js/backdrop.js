const TomVisuals = {
  init: function () {
    const savedPref = localStorage.getItem("tom-labs-visual-blur");
    const isEnabled = savedPref === null ? true : savedPref === "true";

    const supportsBlur =
      CSS.supports("backdrop-filter", "blur(1px)") ||
      CSS.supports("-webkit-backdrop-filter", "blur(1px)");

    if (!supportsBlur) {
      this.apply(false);
      this.syncToggle(false, true);
      this.showWarning(
        "Your browser does not support visual blur effects.",
        "danger",
      );
      return;
    }

    this.apply(isEnabled);
    this.syncToggle(isEnabled, false);

    const hw = this.checkHardware();
    if (isEnabled && hw && !hw.highPerf) {
      if (!sessionStorage.getItem("blur-warning-shown")) {
        this.showWarning(
          "Low-end GPU detected. If the site feels slow, try disabling Visual Blur.",
          "warning",
        );
        sessionStorage.setItem("blur-warning-shown", "true");
      }
    }
  },

  /**
   * MODAL TRIGGER: This fixes the "card not showing" issue
   */
  showRecommendation: function () {
    const modalEl = document.getElementById("visualsRecommendationModal");
    if (modalEl) {
      const modal = new coreui.Modal(modalEl);
      modal.show();
      // Trigger hardware check to fill table values when modal opens
      this.checkHardware();
    }
  },

  showWarning: function (message, type = "success") {
    const toastEl = document.getElementById("copyToast");
    const msgEl = document.getElementById("toast-message");
    const iconEl = document.getElementById("toast-icon");

    if (!toastEl) return;

    toastEl.classList.remove(
      "text-bg-success",
      "text-bg-warning",
      "text-bg-danger",
      "text-white",
      "text-dark",
    );
    iconEl.className = "bx fs-5";

    if (type === "warning") {
      toastEl.classList.add("text-bg-warning", "text-dark");
      iconEl.classList.add("bx-error");
    } else if (type === "danger") {
      toastEl.classList.add("text-bg-danger", "text-white");
      iconEl.classList.add("bx-x-circle");
    } else {
      toastEl.classList.add("text-bg-success", "text-white");
      iconEl.classList.add("bx-check-circle");
    }

    msgEl.innerText = message;
    const toast = new coreui.Toast(toastEl);
    toast.show();
  },

  checkHardware: function () {
    try {
      const canvas = document.createElement("canvas");
      const gl =
        canvas.getContext("webgl") || canvas.getContext("experimental-webgl");
      if (!gl) return { webgl: false, highPerf: false };

      const debugInfo = gl.getExtension("WEBGL_debug_renderer_info");
      const renderer = debugInfo
        ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL)
        : "Unknown";
      const vendor = debugInfo
        ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL)
        : "Unknown";

      const isSlow = renderer.match(/Intel|Software|SwiftShader|Basic/i);
      const highPerf = !isSlow;

      // Fill the modal table if it exists in the DOM
      if (document.getElementById("gpu-webgl")) {
        document.getElementById("gpu-webgl").innerText = "Yes";
        document.getElementById("gpu-performance").innerText = highPerf
          ? "Yes"
          : "Recommended No";
        document.getElementById("gpu-vendor").innerText = vendor;
        document.getElementById("gpu-renderer").innerText = renderer;
      }

      return { webgl: true, renderer, vendor, highPerf };
    } catch (e) {
      return null;
    }
  },

  toggleBlur: function (shouldEnable) {
    localStorage.setItem("tom-labs-visual-blur", shouldEnable);
    this.apply(shouldEnable);

    // Show the message based on state
    if (shouldEnable) {
      this.showWarning("Visual Blur Enabled", "success");
    } else {
      this.showWarning(
        "Visual Blur Disabled - Performance Optimized",
        "warning",
      );
    }
  },

  apply: function (isEnabled) {
    if (isEnabled) {
      document.documentElement.classList.add("enable-blur");
    } else {
      document.documentElement.classList.remove("enable-blur");
    }
  },

  syncToggle: function (isEnabled, disabled) {
    const toggle = document.getElementById("visualBlurToggle");
    if (toggle) {
      toggle.checked = isEnabled;
      if (disabled) toggle.disabled = true;
    } else {
      setTimeout(() => this.syncToggle(isEnabled, disabled), 500);
    }
  },
};
document.addEventListener("DOMContentLoaded", () => TomVisuals.init());
