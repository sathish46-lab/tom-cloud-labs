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
      hoverOnly: false, // Track across the whole viewport like reference
      pointerEvents: false,
      frictionX: 1.0, // Instant movement matching SN
      frictionY: 1.0,
      scalarX: 2.0, // Subtle movement range matching SN
      scalarY: 2.0,
      invertX: true, // Opposite direction tracking
      invertY: true,
      originX: 0.5,
      originY: 0.5,
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
