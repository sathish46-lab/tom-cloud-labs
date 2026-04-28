const TomBG = {
  // Themes are now loaded dynamically from PHP (window.TomThemes)
  themes: window.TomThemes || {},

  init: function () {
    // 1. Check for forced mode (Login Page) before looking at localStorage
    const saved = localStorage.getItem("tom-labs-bg-mode") || "parallax";
    const modeToUse = window.FORCED_BG_MODE || saved;

    this.apply(modeToUse);

    // Watch for theme changes (Light/Dark mode toggle) to update colors instantly
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.attributeName === "data-coreui-theme") {
          const currentMode =
            window.FORCED_BG_MODE ||
            localStorage.getItem("tom-labs-bg-mode") ||
            "parallax";
          this.apply(currentMode);
        }
      });
    });

    observer.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ["data-coreui-theme"]
    });

    this.updateCustomSlotsUI();
    this.initCustomPicker();
    this.initWheel();
    this.initScenery();

    // Handle editing state when modal opens/closes
    const modalEl = document.getElementById("plainColorModal");
    if (modalEl) {
      modalEl.addEventListener("show.bs.modal", () => {
        // Default to Slot 0 if no specific slot was picked via the wand icon
        if (this.currentEditingSlot === null) this.currentEditingSlot = 0;
      });
      modalEl.addEventListener("hidden.bs.modal", () => {
        this.currentEditingSlot = null;
      });
    }
  },

  currentEditingSlot: null,

  openDesignerForSlot: function (index) {
    this.currentEditingSlot = index;
    const slotColor = localStorage.getItem(`tom-labs-custom-color-${index}`) || "#ffffff";
    this.setPlainColor(slotColor);
    bootstrap.Modal.getOrCreateInstance(document.getElementById("plainColorModal")).show();
  },

  applySlot: function (index) {
    const slotColor = localStorage.getItem(`tom-labs-custom-color-${index}`);
    if (slotColor) {
      this.setPlainColor(slotColor);
    } else {
      this.openDesignerForSlot(index);
    }
  },

  initWheel: function () {
    const wheel = document.getElementById("color-wheel");
    if (!wheel) return;

    let isDragging = false;

    const updateFromWheel = (e) => {
      const rect = wheel.getBoundingClientRect();
      const centerX = rect.width / 2;
      const centerY = rect.height / 2;
      const clientX = e.clientX || (e.touches && e.touches[0].clientX);
      const clientY = e.clientY || (e.touches && e.touches[0].clientY);
      const x = clientX - rect.left - centerX;
      const y = clientY - rect.top - centerY;

      // Calculate Angle (Hue)
      let angle = Math.atan2(y, x) * (180 / Math.PI) + 90;
      if (angle < 0) angle += 360;

      // Calculate Distance (Saturation)
      const dist = Math.sqrt(x * x + y * y);
      const radius = rect.width / 2;
      const s = Math.min(100, (dist / radius) * 100);
      const v = document.getElementById("brightness-slider") ? document.getElementById("brightness-slider").value : 100;

      const hex = this.hsvToHex(angle, s, v);
      this.setPlainColor(hex);

      // Update wheel cursor position
      const cursor = document.getElementById("wheel-cursor");
      if (cursor) {
        cursor.style.left = (centerX + x) + "px";
        cursor.style.top = (centerY + y) + "px";
      }
    };

    wheel.addEventListener("mousedown", (e) => {
      isDragging = true;
      updateFromWheel(e);
    });

    window.addEventListener("mousemove", (e) => {
      if (isDragging) updateFromWheel(e);
    });

    window.addEventListener("mouseup", () => {
      isDragging = false;
    });
  },

  updateBrightness: function (val) {
    const valBright = document.getElementById("val-bright");
    if (valBright) valBright.innerText = val + "%";
    const currentHex = localStorage.getItem("tom-labs-plain-color") || "#0b1e36";
    const hsv = this.hexToHsv(currentHex);
    const newHex = this.hsvToHex(hsv.h, hsv.s, val);
    this.setPlainColor(newHex);
  },

  hexToHsv: function (hex) {
    const rgb = hex.match(/[A-Za-z0-9]{2}/g).map(function (x) { return parseInt(x, 16) / 255; });
    const max = Math.max.apply(Math, rgb), min = Math.min.apply(Math, rgb);
    const d = max - min;
    let h, s = max === 0 ? 0 : d / max, v = max;
    if (max === min) h = 0;
    else {
      switch (max) {
        case rgb[0]: h = (rgb[1] - rgb[2]) / d + (rgb[1] < rgb[2] ? 6 : 0); break;
        case rgb[1]: h = (rgb[2] - rgb[0]) / d + 2; break;
        case rgb[2]: h = (rgb[0] - rgb[1]) / d + 4; break;
      }
      h /= 6;
    }
    return { h: h * 360, s: s * 100, v: v * 100 };
  },

  initCustomPicker: function () {
    const specMap = document.getElementById("spectrum-map");
    if (!specMap) return;

    let isDragging = false;

    const updateFromMap = (e) => {
      const rect = specMap.getBoundingClientRect();
      const clientX = e.clientX || (e.touches && e.touches[0].clientX);
      const clientY = e.clientY || (e.touches && e.touches[0].clientY);
      const x = Math.max(0, Math.min(rect.width, clientX - rect.left));
      const y = Math.max(0, Math.min(rect.height, clientY - rect.top));

      const h = (x / rect.width) * 360;
      let s, v;

      if (y < rect.height / 2) {
        s = (y / (rect.height / 2)) * 100;
        v = 100;
      } else {
        s = 100;
        v = 100 - ((y - rect.height / 2) / (rect.height / 2)) * 100;
      }

      const hex = this.hsvToHex(h, s, v);
      this.setPlainColor(hex);

      const cursor = document.getElementById("spectrum-cursor");
      if (cursor) {
        cursor.style.left = x + "px";
        cursor.style.top = y + "px";
      }
    };

    specMap.addEventListener("mousedown", (e) => {
      isDragging = true;
      updateFromMap(e);
    });

    window.addEventListener("mousemove", (e) => {
      if (isDragging) updateFromMap(e);
    });

    window.addEventListener("mouseup", () => {
      isDragging = false;
    });

    // Palettes
    document.querySelectorAll(".palette-item").forEach((p) => {
      p.onclick = () => this.setPlainColor(p.style.backgroundColor);
    });

    // Pencils
    document.querySelectorAll(".pencil-item").forEach((p) => {
      p.onclick = () => this.setPlainColor(p.getAttribute("data-color"));
    });
  },

  hsvToHex: function (h, s, v) {
    s /= 100;
    v /= 100;
    const i = Math.floor(h / 60);
    const f = h / 60 - i;
    const p = v * (1 - s);
    const q = v * (1 - f * s);
    const t = v * (1 - (1 - f) * s);
    let r, g, b;
    switch (i % 6) {
      case 0: r = v, g = t, b = p; break;
      case 1: r = q, g = v, b = p; break;
      case 2: r = p, g = v, b = t; break;
      case 3: r = p, g = q, b = v; break;
      case 4: r = t, g = p, b = v; break;
      case 5: r = v, g = p, b = q; break;
    }
    const toHex = (x) => {
      const hex = Math.round(x * 255).toString(16);
      return hex.length === 1 ? "0" + hex : hex;
    };
    return "#" + toHex(r) + toHex(g) + toHex(b);
  },

  switchPickerTab: function (tabId) {
    // Update buttons
    const tabs = document.querySelectorAll(".designer-tab");
    tabs.forEach((t) => {
      const title = t.getAttribute("title").toLowerCase();
      if (title === tabId) {
        t.classList.add("active");
      } else {
        t.classList.remove("active");
      }
    });

    // Update content
    const modes = document.querySelectorAll(".picker-mode");
    modes.forEach((m) => {
      m.classList.add("d-none");
      m.classList.remove("active");
    });

    const activeMode = document.getElementById("picker-" + tabId);
    if (activeMode) {
      activeMode.classList.remove("d-none");
      activeMode.classList.add("active");
    }

    // Toggle global brightness slider for visual modes
    const brightCont = document.getElementById("global-brightness-cont");
    if (brightCont) {
      if (tabId === "wheel" || tabId === "spectrum") {
        brightCont.classList.remove("d-none");
      } else {
        brightCont.classList.add("d-none");
      }
    }
  },

  updateFromSliders: function () {
    const r = document.querySelector(".rgb-slider.r").value;
    const g = document.querySelector(".rgb-slider.g").value;
    const b = document.querySelector(".rgb-slider.b").value;

    const valR = document.getElementById("val-r");
    const valG = document.getElementById("val-g");
    const valB = document.getElementById("val-b");
    if (valR) valR.innerText = r;
    if (valG) valG.innerText = g;
    if (valB) valB.innerText = b;

    const toHex = (c) => {
      const hex = parseInt(c).toString(16);
      return hex.length === 1 ? "0" + hex : hex;
    };
    const hex = "#" + toHex(r) + toHex(g) + toHex(b);
    this.setPlainColor(hex);
  },

  syncPickers: function (hex) {
    const hsv = this.hexToHsv(hex);
    const rgb = hex.match(/[A-Za-z0-9]{2}/g).map(function (v) { return parseInt(v, 16); });

    // 1. Sync Sliders
    const rSlider = document.querySelector(".rgb-slider.r");
    if (rSlider) {
      rSlider.value = rgb[0];
      document.querySelector(".rgb-slider.g").value = rgb[1];
      document.querySelector(".rgb-slider.b").value = rgb[2];
      const valR = document.getElementById("val-r");
      const valG = document.getElementById("val-g");
      const valB = document.getElementById("val-b");
      if (valR) valR.innerText = rgb[0];
      if (valG) valG.innerText = rgb[1];
      if (valB) valB.innerText = rgb[2];
    }

    // 2. Sync Spectrum Cursor
    const specMap = document.getElementById("spectrum-map");
    const specCursor = document.getElementById("spectrum-cursor");
    if (specMap && specCursor) {
      const rect = specMap.getBoundingClientRect();
      const x = (hsv.h / 360) * rect.width;
      let y;
      if (hsv.v === 100) {
        y = (hsv.s / 100) * (rect.height / 2);
      } else {
        y = rect.height / 2 + (1 - hsv.v / 100) * (rect.height / 2);
      }
      specCursor.style.left = x + "px";
      specCursor.style.top = y + "px";
    }

    // 3. Sync Wheel Cursor
    const wheel = document.getElementById("color-wheel");
    const wheelCursor = document.getElementById("wheel-cursor");
    if (wheel && wheelCursor) {
      const rect = wheel.getBoundingClientRect();
      const centerX = rect.width / 2;
      const centerY = rect.height / 2;
      const angle = (hsv.h - 90) * (Math.PI / 180);
      const radius = (hsv.s / 100) * (rect.width / 2);
      const x = centerX + radius * Math.cos(angle);
      const y = centerY + radius * Math.sin(angle);
      wheelCursor.style.left = x + "px";
      wheelCursor.style.top = y + "px";
    }

    // 4. Sync Brightness Slider
    const bSlider = document.getElementById("brightness-slider");
    if (bSlider) {
      bSlider.value = Math.round(hsv.v);
      const valBright = document.getElementById("val-bright");
      if (valBright) valBright.innerText = Math.round(hsv.v) + "%";
    }
  },

  setPlainColor: function (color) {
    const hex = this.toHex(color);

    // Default to Slot 0 if no slot is active, ensuring the Designer always has a target
    const targetSlot = (this.currentEditingSlot !== null) ? this.currentEditingSlot : 0;

    // Save to the target slot (Real-time update)
    this.saveCustomColor(targetSlot, hex);

    // Always update main theme and apply immediately for real-time feedback
    localStorage.setItem("tom-labs-plain-color", hex);
    localStorage.setItem("tom-labs-bg-mode", "plain");
    this.apply("plain");

    // Sync preview
    const preview = document.getElementById("hex-preview");
    if (preview) {
      preview.innerText = hex.toUpperCase();
      preview.style.color = hex;
    }

    // Update active tab icon color ONLY
    const activeTab = document.querySelector(".designer-tab.active i");
    if (activeTab) {
      activeTab.style.color = hex;
    }

    // Reset other icons to default opacity
    document.querySelectorAll(".designer-tab:not(.active) i").forEach((i) => { i.style.color = ""; });

    // Global Sync all other pickers
    this.syncPickers(hex);
  },

  apply: function (mode) {
    const scene = document.getElementById("scene");
    if (!scene) return;

    const root = document.documentElement;
    const isLight = root.getAttribute("data-coreui-theme") === "light";
    const sc = document.querySelector(".scenery-container");

    if (mode === "plain") {
      const color = localStorage.getItem("tom-labs-plain-color") || "#0b1e36";
      if (sc) sc.style.opacity = "1";
      scene.style.display = "none";
      document.body.style.background = ""; // Clear inline transparent style

      const safeColor = isLight ? this.ensureLightness(color, 0.8) : this.ensureDarkness(color, 0.2);

      if (isLight) {
        const baseLight = this.ensureLightness(color, 0.92);
        root.style.setProperty("--c1", "#ffffff");
        root.style.setProperty("--c2", this.adjustColor(baseLight, 2));
        root.style.setProperty("--c3", this.adjustColor(baseLight, 5));
        root.style.setProperty("--c4", this.adjustColor(baseLight, -5));
        root.style.setProperty("--snow-color", "rgba(0,0,0,0.15)");
      } else {
        root.style.setProperty("--c1", this.adjustColor(color, -35));
        root.style.setProperty("--c2", this.adjustColor(color, -15));
        root.style.setProperty("--c3", this.adjustColor(color, 20));
        root.style.setProperty("--c4", this.adjustColor(color, 45));
        root.style.setProperty("--snow-color", "#ffffff");
      }

      const primaryColor = this.adjustColor(color, isLight ? -40 : 40);
      const pRGB = this.hexToRgbValues(primaryColor);

      root.style.setProperty("--glass-bg", isLight ? this.hexToRgba(safeColor, 0.4) : this.hexToRgba(safeColor, 0.85));
      root.style.setProperty("--glass-bg-solid", isLight ? this.hexToRgba(this.ensureLightness(color, 0.92), 0.94) : this.hexToRgba(safeColor, 0.94));
      root.style.setProperty("--cui-card-bg", isLight ? "rgba(255,255,255,0.05)" : this.hexToRgba(safeColor, 0.2));
      root.style.setProperty("--cui-card-bg-solid", isLight ? this.hexToRgba(this.ensureLightness(color, 0.96), 0.94) : this.hexToRgba(safeColor, 0.95));
      root.style.setProperty("--cui-body-bg", safeColor);
      root.style.setProperty("--cui-primary", primaryColor);
      root.style.setProperty("--cui-primary-rgb", pRGB);
      root.style.setProperty("--cui-sidebar-bg", this.hexToRgba(safeColor, 0.95));
      root.style.setProperty("--cui-header-bg", this.hexToRgba(safeColor, 0.85));

      if (typeof TomParallax !== "undefined") TomParallax.destroy();
    } else {
      scene.style.display = "block";
      document.body.style.background = "transparent";
      if (sc) sc.style.opacity = "0";

      const theme = this.themes[mode] || this.themes["parallax"];
      const assets = theme.assets || theme;
      const themeColor = theme.color || null;

      if (themeColor) {
        const safeColor = isLight ? this.ensureLightness(themeColor, 0.8) : this.ensureDarkness(themeColor, 0.15);
        const primaryColor = this.adjustColor(themeColor, isLight ? -40 : 40);
        const pRGB = this.hexToRgbValues(primaryColor);

        root.style.setProperty("--glass-bg", isLight ? "rgba(255, 255, 255, 0.79)" : this.hexToRgba(safeColor, 0.85));
        root.style.setProperty("--glass-bg-solid", isLight ? this.hexToRgba(this.ensureLightness(themeColor, 0.92), 0.94) : this.hexToRgba(safeColor, 0.94));
        root.style.setProperty("--cui-card-bg", isLight ? "rgba(255, 255, 255, 0.7)" : this.hexToRgba(safeColor, 0.2));
        root.style.setProperty("--cui-card-bg-solid", isLight ? this.hexToRgba(this.ensureLightness(themeColor, 0.96), 0.94) : this.hexToRgba(safeColor, 0.95));
        root.style.setProperty("--cui-body-bg", safeColor);
        root.style.setProperty("--cui-primary", primaryColor);
        root.style.setProperty("--cui-primary-rgb", pRGB);
        root.style.setProperty("--cui-sidebar-bg", isLight ? "rgba(255, 255, 255, 0.6)" : this.hexToRgba(safeColor, 0.95));
        root.style.setProperty("--cui-header-bg", isLight ? "rgba(255, 255, 255, 0.4)" : this.hexToRgba(safeColor, 0.85));

        root.style.setProperty("--c1", isLight ? "#ffffff" : this.adjustColor(safeColor, -5));
        root.style.setProperty("--c2", isLight ? "#f8f9fa" : safeColor);
        root.style.setProperty("--c3", isLight ? "#ffffff" : this.adjustColor(safeColor, 5));
        root.style.setProperty("--c4", isLight ? "#f0f2f5" : this.adjustColor(safeColor, 10));
      } else {
        // Fallback for image themes without specific colors
        const defaultPrimary = isLight ? "#0d6efd" : "rgba(255, 255, 255, 0.9)";
        const pRGB = isLight ? "13, 110, 253" : "255, 255, 255";
        root.style.setProperty("--cui-primary", defaultPrimary);
        root.style.setProperty("--cui-primary-rgb", pRGB);

        if (isLight) {
          root.style.setProperty("--glass-bg", "rgba(255, 255, 255, 0.79)");
          root.style.setProperty("--glass-bg-solid", "rgba(240, 245, 255, 0.94)");
          root.style.setProperty("--cui-card-bg", "rgba(255, 255, 255, 0.1)");
          root.style.setProperty("--cui-card-bg-solid", "rgba(248, 250, 255, 0.94)");
          root.style.setProperty("--cui-body-bg", "#f8f9fa");
          root.style.setProperty("--cui-sidebar-bg", "rgba(245, 250, 255, 0.95)");
        } else {
          root.style.setProperty("--glass-bg", "rgba(0, 10, 24, 0.823)");
          root.style.setProperty("--glass-bg-solid", "rgba(10, 20, 35, 0.94)");
          root.style.setProperty("--cui-card-bg", "rgba(255, 255, 255, 0.03)");
          root.style.setProperty("--cui-card-bg-solid", "rgba(15, 30, 50, 0.94)");
          root.style.setProperty("--cui-body-bg", "rgba(3, 17, 36, 0.94)");
          root.style.setProperty("--cui-sidebar-bg", "rgba(11, 30, 54, 0.95)");
        }
      }

      const layers = scene.querySelectorAll(".bg-cover");
      layers.forEach((layer, index) => {
        if (assets[index]) {
          layer.style.backgroundImage = "url('" + assets[index] + "')";
          layer.style.display = "block";
        } else {
          layer.style.backgroundImage = "none";
          layer.style.display = "none";
        }
      });

      if (typeof TomParallax !== "undefined") TomParallax.init();
    }
  },

  adjustColor: function (hex, percent) {
    const num = parseInt(hex.replace("#", ""), 16),
      amt = Math.round(2.55 * percent),
      R = (num >> 16) + amt,
      B = (num >> 8 & 0x00ff) + amt,
      G = (num & 0x0000ff) + amt;
    return (
      "#" +
      (
        0x1000000 +
        (R < 255 ? (R < 1 ? 0 : R) : 255) * 0x10000 +
        (B < 255 ? (B < 1 ? 0 : B) : 255) * 0x100 +
        (G < 255 ? (G < 1 ? 0 : G) : 255)
      )
        .toString(16)
        .slice(1)
    );
  },

  hexToRgba: function (hex, opacity) {
    const rgb = this.hexToRgbValues(hex);
    return "rgba(" + rgb + ", " + opacity + ")";
  },

  hexToRgbValues: function (hex) {
    const num = parseInt(hex.replace("#", ""), 16),
      R = (num >> 16) & 0xff,
      G = (num >> 8) & 0xff,
      B = num & 0xff;
    return R + ", " + G + ", " + B;
  },

  ensureDarkness: function (hex, maxLuminance) {
    const rgbVal = this.hexToRgbValues(hex);
    const rgb = rgbVal.split(",").map(Number);
    const luminance = (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255;

    if (luminance > maxLuminance) {
      const factor = maxLuminance / luminance;
      const r = Math.round(rgb[0] * factor);
      const g = Math.round(rgb[1] * factor);
      const b = Math.round(rgb[2] * factor);
      const toHexStr = (c) => {
        const h = c.toString(16);
        return h.length === 1 ? "0" + h : h;
      };
      return "#" + toHexStr(r) + toHexStr(g) + toHexStr(b);
    }
    return hex;
  },

  ensureLightness: function (hex, minLuminance) {
    const rgbVal = this.hexToRgbValues(hex);
    const rgb = rgbVal.split(",").map(Number);
    const luminance = (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255;

    if (luminance < minLuminance) {
      const factor = (1 - minLuminance) / (1 - luminance);
      const r = Math.round(255 - (255 - rgb[0]) * factor);
      const g = Math.round(255 - (255 - rgb[1]) * factor);
      const b = Math.round(255 - (255 - rgb[2]) * factor);
      const toHexStr = (c) => {
        const h = c.toString(16);
        return h.length === 1 ? "0" + h : h;
      };
      return "#" + toHexStr(r) + toHexStr(g) + toHexStr(b);
    }
    return hex;
  },

  saveCustomColor: function (index, color) {
    const hex = this.toHex(color);
    localStorage.setItem("tom-labs-custom-color-" + index, hex);
    this.updateCustomSlotsUI();
  },

  setMode: function (mode) {
    localStorage.setItem("tom-labs-bg-mode", mode);
    this.apply(mode);
  },

  updateCustomSlotsUI: function () {
    for (let i = 0; i < 4; i++) {
      const slot = document.getElementById("custom-slot-" + i);
      if (slot) {
        const color = localStorage.getItem("tom-labs-custom-color-" + i);
        const plus = slot.querySelector(".bx-plus");
        const edit = slot.querySelector(".edit-icon");

        if (color) {
          slot.style.background = color;
          slot.classList.add("has-color");
          if (plus) plus.classList.add("d-none");
          if (edit) edit.style.opacity = "1";
        } else {
          slot.style.background = "rgba(255,255,255,0.05)";
          slot.classList.remove("has-color");
          if (plus) plus.classList.remove("d-none");
          if (edit) edit.style.opacity = "0";
        }
      }
    }
  },

  toHex: function (color) {
    if (!color) return "#0b1e36";
    if (color.indexOf("#") === 0) return color;
    const rgb = color.match(/\d+/g);
    if (!rgb || rgb.length < 3) return "#0b1e36";
    const r = parseInt(rgb[0]),
      g = parseInt(rgb[1]),
      b = parseInt(rgb[2]);
    return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  },

  initScenery: function () {
    if (document.querySelector(".scenery-container")) return;
    const container = document.createElement("div");
    container.className = "scenery-container";
    container.innerHTML = `
      <div class="scenery-snow"></div>
      <div class="scenery-mountains"></div>
      <div class="scenery-house"></div>
      <div class="scenery-orb-1"></div>
      <div class="scenery-orb-2"></div>
    `;
    document.body.appendChild(container);
  }
};

document.addEventListener("DOMContentLoaded", function () { TomBG.init(); });
