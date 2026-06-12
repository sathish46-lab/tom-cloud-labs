<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sand Erase Animation</title>
  <style>
    /* Reset and Layout */
    body {
      margin: 0;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      background-color: #121212;
      overflow: hidden;
    }

    /* SVG Grain Filter Definition */
    .sand-container {
      position: relative;
      /* The SVG filter creates the gritty, sand-like scattering effect */
      filter: url(#sand-grain);
    }

    /* Animated Text Element */
    .sand-text {
      color: #e0b034; /* Sand yellow color */
      font-family: 'Arial Black', sans-serif;
      font-size: 5rem;
      letter-spacing: 2px;
      text-transform: uppercase;
      margin: 0;
      
      /* Pure CSS Mask for the Erase / Wipe effect */
      -webkit-mask-image: linear-gradient(to right, transparent 0%, black 15%, black 100%);
      mask-image: linear-gradient(to right, transparent 0%, black 15%, black 100%);
      -webkit-mask-size: 400% 100%;
      mask-size: 400% 100%;
      -webkit-mask-position: 100% 0;
      mask-position: 100% 0;
      
      /* Triggering the animations */
      animation: 
        erase-wipe 4s ease-in-out infinite alternate,
        dissolve-drift 4s ease-in-out infinite alternate;
    }

    /* Animation 1: The Left-to-Right Erase Mask */
    @keyframes erase-wipe {
      0% {
        -webkit-mask-position: 100% 0;
        mask-position: 100% 0;
      }
      100% {
        -webkit-mask-position: 0% 0;
        mask-position: 0% 0;
      }
    }

    /* Animation 2: Distorting & Drifting the text as it erases */
    @keyframes dissolve-drift {
      0% {
        transform: translateX(0) scale(1);
        opacity: 1;
      }
      70% {
        opacity: 0.9;
      }
      100% {
        transform: translateX(40px) scale(0.95);
        opacity: 0;
      }
    }
  </style>
</head>
<body>

  <!-- The Animation Wrapper -->
  <div class="sand-container">
    <h1 class="sand-text">Desert Sand</h1>
  </div>

  <!-- SVG Filter to produce the grainy, sandy texture breakdown -->
  <svg width="0" height="0" style="position: absolute;">
    <filter id="sand-grain">
      <!-- Generates high-frequency digital noise -->
      <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="3" result="noise" />
      <!-- Displaces the original text pixels using the noise map -->
      <feDisplacementMap in="SourceGraphic" in2="noise" scale="15" xChannelSelector="R" yChannelSelector="G" />
    </filter>
  </svg>

</body>
</html>
