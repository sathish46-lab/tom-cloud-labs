<div class="container">
  <!-- Card base glass -->
  <div class="glass">
    <div class="card-header">
      <div class="icon glass">🎵</div>
      <div>
        <h3 class="card-title">Today's Hits</h3>
        <p class="card-subtitle">Apple Music Hits</p>
      </div>
    </div>
    <div class="music-widget glass">
      <div class="album-cover">♪</div>
      <div style="flex: 1">
        <div style="color: white; font-weight: 500">Current Song</div>
        <div style="color: rgba(255, 255, 255, 0.7); font-size: 14px">Artist Name</div>
      </div>
      <button class="play-btn">▶</button>
    </div>
  </div>

  <div class="glass">
    <div class="card-header">
      <div class="icon glass">📝</div>
      <div>
        <h3 class="card-title">Reminders</h3>
        <p class="card-subtitle">3 items</p>
      </div>
    </div>
    <div class="card-content">
      <div style="margin-bottom: 10px">○ Pick up contacts</div>
      <div style="margin-bottom: 10px">○ Order plant food</div>
      <div>○ Water Monstera</div>
    </div>
  </div>

  <div class="glass">
    <div class="card-header">
      <div class="icon glass">✨</div>
      <div>
        <h3 class="card-title">Glassmorphism</h3>
        <p class="card-subtitle">Modern Design</p>
      </div>
    </div>
    <div class="card-content">
      <p>
        The glass effect relies on transparency, background blur and thin edges to create depth.
      </p>
      <a
        href="#"
        class="btn-glass glass"
        >Discover more</a
      >
    </div>
  </div>
</div>


<style>
.container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr));
  gap: 1.25rem;
  max-width: 62.5rem;
  width: 100%;
}

/* Liquid glass effect class */
.glass {
  position: relative;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(2px) saturate(180%);
  border: 0.0625rem solid rgba(255, 255, 255, 0.8);
  border-radius: 2rem;
  padding: 1.25rem;
  box-shadow: 0 8px 32px rgba(31, 38, 135, 0.2), inset 0 4px 20px rgba(255, 255, 255, 0.3);
}

.glass::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 2rem;
  backdrop-filter: blur(1px);
  box-shadow: inset -10px -8px 0px -11px rgba(255, 255, 255, 1),
    inset 0px -9px 0px -8px rgba(255, 255, 255, 1);
  opacity: 0.6;
  z-index: -1;
  filter: blur(1px) drop-shadow(10px 4px 6px black) brightness(115%);
  pointer-events: none;
}

/* Content style */
.card-header {
  display: flex;
  align-items: center;
  gap: 0.9375rem;
  margin-bottom: 0.9375rem;
}

.icon {
  position: relative;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.625rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.icon::after {
  border-radius: 0.625rem;
}

.card-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: white;
  margin: 0;
}

.card-subtitle {
  font-size: 1.1rem;
  color: white;
  margin: 0.3125rem 0 0 0;
}

.card-content {
  color: white;
  line-height: 1.6;
}

.card-content-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.938rem;
  margin-top: 0.938rem;
}

.card-content-icon {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 10px;
  margin: 0 auto 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
}

.btn-glass {
  position: relative;
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 0.75rem;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 0.9375rem;
  display: inline-block;
  text-decoration: none;
}

.btn-glass::after {
  border-radius: 0.75rem;
}

.btn-glass:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.05);
}

/* Musical Widget */
.music-widget {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.9375rem;
  padding: 0.9375rem;
  border-radius: 0.9375rem;
  margin-top: 0.9375rem;
}

.music-widget::after {
  border-radius: 0.9375rem;
}

.album-cover {
  width: 3.125rem;
  height: 3.125rem;
  background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
}

.play-btn {
  width: 2.5rem;
  height: 2.5rem;
  background: rgba(255, 255, 255, 0.3);
  border: none;
  border-radius: 50%;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.play-btn:hover {
  background: rgba(255, 255, 255, 0.5);
  transform: scale(1.1);
}

/* Responsive */
@media (max-width: 550) {
  .container {
    grid-template-columns: 1fr;
  }
}
</style>