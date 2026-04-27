/**
 * macOS Style Background Parallax Initializer
 */
const TomParallax = {
  instance: null,
  sceneElement: null,

  init: function () {
    this.sceneElement = document.getElementById("scene");
    if (!this.sceneElement) return;

    // Initialize the engine
    this.instance = new Parallax(this.sceneElement, {
      relativeInput: true, // Movement relative to the element
      hoverOnly: true, // Only move when mouse is over the page
      pointerEvents: false, // Don't interfere with clicks on the dashboard
      frictionX: 0.1, // Smoothness of horizontal return
      frictionY: 0.1, // Smoothness of vertical return
      scalarX: 8.0, // Sensitivity of movement
      scalarY: 8.0,
    });

    console.log("[✓] Parallax background active.");
  },

  // Optimization: Stop movement if user is idle or tab is hidden
  destroy: function () {
    if (this.instance) {
      this.instance.disable();
      console.log("[Optimization] Parallax suspended.");
    }
  },

  resume: function () {
    if (this.instance) {
      this.instance.enable();
    }
  },
};

// Start Parallax when DOM is ready
document.addEventListener("DOMContentLoaded", () => TomParallax.init());
