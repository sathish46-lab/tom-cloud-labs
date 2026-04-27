const TomBG = {
  // Define your image sets here.
  // You can add as many as you want!
  themes: {
    robo: {
      assets: [
        "/assets/Background_Img/robo/0.png",
        "/assets/Background_Img/robo/1.png",
        "/assets/Background_Img/robo/2.png",
      ],
      color: "#0b2b1c"
    },
    ninja: {
      assets: [
        "/assets/Background_Img/ninja/0.png",
        "/assets/Background_Img/ninja/1.png",
        "/assets/Background_Img/ninja/2.png",
      ],
      // color: "#1c0b2b"
    },
    robotower: {
      assets: [
        "/assets/Background_Img/RoboTower/0.png",
        "/assets/Background_Img/RoboTower/1.png",
        "/assets/Background_Img/RoboTower/2.png",
        "/assets/Background_Img/RoboTower/3.png",
      ],
      color: "#0b2b1c"
    },
  },

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
      attributeFilter: ["data-coreui-theme"],
    });

    this.updateCustomSlotsUI();
  },

  setMode: function (mode) {
    localStorage.setItem("tom-labs-bg-mode", mode);
    this.apply(mode);

    const modalEl = document.getElementById("bgSelectModal");
    if (modalEl) {
      const modal = coreui.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }
  },

  setPlainColor: function (color) {
    localStorage.setItem("tom-labs-plain-color", color);
    localStorage.setItem("tom-labs-bg-mode", "plain");
    this.apply("plain");
  },

  apply: function (mode) {
    const scene = document.getElementById("scene");
    if (!scene) return;

    if (mode === "plain") {
      const color = localStorage.getItem("tom-labs-plain-color") || "#0b1e36";
      const root = document.documentElement;
      const isLight = root.getAttribute("data-coreui-theme") === "light";

      // Ensure color is safe for the current theme (Light or Dark)
      const safeColor = isLight ? this.ensureLightness(color, 0.8) : this.ensureDarkness(color, 0.15);

      scene.style.display = "none";

      // Update theme variables with subtle variants for a harmonious look
      root.style.setProperty("--c1", this.adjustColor(safeColor, -3));
      root.style.setProperty("--c2", safeColor);
      root.style.setProperty("--c3", this.adjustColor(safeColor, 3));
      root.style.setProperty("--c4", this.adjustColor(safeColor, isLight ? -6 : 6));

      // Update global theme variables
      const primaryColor = this.adjustColor(color, isLight ? -40 : 40);
      const pRGB = this.hexToRgbValues(primaryColor);

      root.style.setProperty("--glass-bg", isLight ? this.hexToRgba(safeColor, 0.4) : this.hexToRgba(safeColor, 0.85));
      root.style.setProperty("--cui-card-bg", isLight ? "rgba(0,0,0,0.05)" : this.hexToRgba(safeColor, 0.2));
      root.style.setProperty("--cui-body-bg", safeColor);
      root.style.setProperty("--cui-primary", primaryColor);
      root.style.setProperty("--cui-primary-rgb", pRGB);

      // Sync sidebar and other components
      root.style.setProperty("--cui-sidebar-bg", this.hexToRgba(safeColor, 0.95));
      root.style.setProperty("--cui-header-bg", this.hexToRgba(safeColor, 0.85));

      if (typeof TomParallax !== "undefined") TomParallax.destroy();
    } else {
      scene.style.display = "block";
      document.body.style.background = "transparent";

      const theme = this.themes[mode] || this.themes["parallax"];
      const assets = theme.assets || theme;
      const themeColor = theme.color || null;

      // Update global theme variables if theme has a specific color
      const root = document.documentElement;
      if (themeColor) {
        const primaryColor = this.adjustColor(themeColor, 40);
        const pRGB = this.hexToRgbValues(primaryColor);

        root.style.setProperty("--glass-bg", this.hexToRgba(themeColor, 0.85));
        root.style.setProperty("--cui-card-bg", this.hexToRgba(themeColor, 0.2));
        root.style.setProperty("--cui-body-bg", themeColor);
        root.style.setProperty("--cui-primary", primaryColor);
        root.style.setProperty("--cui-primary-rgb", pRGB);
        root.style.setProperty("--cui-sidebar-bg", this.hexToRgba(themeColor, 0.95));
        root.style.setProperty("--cui-header-bg", this.hexToRgba(themeColor, 0.85));

        // Sync background gradient variables
        root.style.setProperty("--c1", this.adjustColor(themeColor, -3));
        root.style.setProperty("--c2", themeColor);
        root.style.setProperty("--c3", this.adjustColor(themeColor, 3));
        root.style.setProperty("--c4", this.adjustColor(themeColor, 6));
      } else {
        // Reset to defaults if no theme color
        const isDark = root.getAttribute("data-coreui-theme") === "dark";
        if (isDark) {
          root.style.setProperty("--glass-bg", "rgba(0, 10, 24, 0.823)");
          root.style.setProperty("--cui-card-bg", "rgba(255, 255, 255, 0.03)");
          root.style.setProperty("--cui-body-bg", "rgba(3, 17, 36, 0.98)");
          root.style.setProperty("--cui-primary", "#321fdb");
          root.style.setProperty("--cui-primary-rgb", "50, 31, 219");

          root.style.setProperty("--c1", "#0b1e36");
          root.style.setProperty("--c2", "#112e4a");
          root.style.setProperty("--c3", "#16375e");
          root.style.setProperty("--c4", "#1a2c48");
        } else {
          root.style.setProperty("--glass-bg", "rgba(255, 255, 255, 0.866)");
          root.style.setProperty("--cui-card-bg", "rgba(255, 255, 255, 0.754)");
          root.style.setProperty("--cui-body-bg", "rgba(255, 255, 255, 0.8)");
          root.style.setProperty("--cui-primary", "#321fdb");
          root.style.setProperty("--cui-primary-rgb", "50, 31, 219");

          root.style.setProperty("--c1", "#ffffff");
          root.style.setProperty("--c2", "#e4e6ff");
          root.style.setProperty("--c3", "#e4fbff");
          root.style.setProperty("--c4", "#e2ecff");
        }
      }

      // Dynamically Inject URLs from the themes object
      const layers = scene.querySelectorAll(".bg-cover");

      layers.forEach((layer, index) => {
        if (assets[index]) {
          layer.style.backgroundImage = `url('${assets[index]}')`;
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
    var num = parseInt(hex.replace("#", ""), 16),
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
    return `rgba(${rgb}, ${opacity})`;
  },

  hexToRgbValues: function (hex) {
    var num = parseInt(hex.replace("#", ""), 16),
      R = (num >> 16) & 0xff,
      G = (num >> 8) & 0xff,
      B = num & 0xff;
    return `${R}, ${G}, ${B}`;
  },

  ensureDarkness: function (hex, maxLuminance) {
    const rgb = this.hexToRgbValues(hex).split(",").map(Number);
    // Relative luminance formula
    const luminance = (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255;

    if (luminance > maxLuminance) {
      const factor = maxLuminance / luminance;
      const r = Math.round(rgb[0] * factor);
      const g = Math.round(rgb[1] * factor);
      const b = Math.round(rgb[2] * factor);
      return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
    }
    return hex;
  },

  ensureLightness: function (hex, minLuminance) {
    const rgb = this.hexToRgbValues(hex).split(",").map(Number);
    const luminance = (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255;

    if (luminance < minLuminance) {
      // Lighten the color
      const factor = (1 - minLuminance) / (1 - luminance);
      const r = Math.round(255 - (255 - rgb[0]) * factor);
      const g = Math.round(255 - (255 - rgb[1]) * factor);
      const b = Math.round(255 - (255 - rgb[2]) * factor);
      return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
    }
    return hex;
  },

  saveCustomColor: function (index, color) {
    const customColors = JSON.parse(localStorage.getItem("tom-labs-custom-colors") || "[]");
    customColors[index] = color || localStorage.getItem("tom-labs-plain-color") || "#0b1e36";
    localStorage.setItem("tom-labs-custom-colors", JSON.stringify(customColors));
    this.updateCustomSlotsUI();
  },

  updateCustomSlotsUI: function () {
    const customColors = JSON.parse(localStorage.getItem("tom-labs-custom-colors") || "[]");
    for (let i = 0; i < 4; i++) {
      const slot = document.getElementById(`custom-slot-${i}`);
      if (slot) {
        const color = customColors[i];
        if (color) {
          slot.style.background = color;
          slot.classList.add("has-color");
          slot.innerHTML = "";
        } else {
          slot.style.background = "rgba(255,255,255,0.05)";
          slot.classList.remove("has-color");
          slot.innerHTML = '<i class="bx bx-plus"></i>';
        }
      }
    }
  },
};

document.addEventListener("DOMContentLoaded", () => TomBG.init());
