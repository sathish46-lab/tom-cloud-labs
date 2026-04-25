const TomBG = {
  // Define your image sets here.
  // You can add as many as you want!
  themes: {
    robo: [
      "/assets/img/robo/0.png",
      "/assets/img/robo/1.png",
      "/assets/img/robo/2.png",
    ],
    ninja: [
      "/assets/img/ninja/0.png",
      "/assets/img/ninja/1.png",
      "/assets/img/ninja/2.png",
    ],
  },

  init: function () {
    // 1. Check for forced mode (Login Page) before looking at localStorage
    const saved = localStorage.getItem("tom-labs-bg-mode") || "parallax";
    const modeToUse = window.FORCED_BG_MODE || saved;

    this.apply(modeToUse);
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

  apply: function (mode) {
    const scene = document.getElementById("scene");
    if (!scene) return;

    if (mode === "plain") {
      scene.style.display = "none";
      document.body.style.background =
        "linear-gradient(135deg, #0b1e36 0%, #112e4a 100%)";
      if (typeof TomParallax !== "undefined") TomParallax.destroy();
    } else {
      scene.style.display = "block";
      document.body.style.background = "transparent";

      // Dynamically Inject URLs from the themes object
      const layers = scene.querySelectorAll(".bg-cover");
      const assets = this.themes[mode] || this.themes["parallax"];

      layers.forEach((layer, index) => {
        if (assets[index]) {
          layer.style.backgroundImage = `url('${assets[index]}')`;
        }
      });

      if (typeof TomParallax !== "undefined") TomParallax.init();
    }
  },
};

document.addEventListener("DOMContentLoaded", () => TomBG.init());
