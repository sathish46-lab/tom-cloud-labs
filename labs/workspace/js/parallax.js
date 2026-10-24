/**
 * Wrapped with IIFE Error Boundary
 */
try {
  (function() {
    "use strict";


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
      frictionX: 0.15, // Smooth damping — removes snap-shake
      frictionY: 0.15,
      scalarX: 1.0, // Subtle movement range
      scalarY: 1.0,
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
window.onPageLoad( () => TomParallax.init());


    

    // --- Explicit Window Exports for Inline HTML ---
    window.TomParallax = TomParallax;

  })();
} catch (e) {
  console.error("[Fatal Error in parallax.js]", e);
}
