<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Service Profiles</title>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#080a10;
    --card:#0d121b;
    --stroke:#1d2535;
    --orange:#f2661c;
    --white:#f3f5f9;
    --gray:#a7aebb;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{background:var(--bg);height:100%;overflow:hidden}
  body{display:flex;align-items:center;justify-content:center}
  #stage{
    position:relative;width:2576px;height:1287px;flex:none;
    transform-origin:center center;
    font-family:'Ubuntu',system-ui,sans-serif;color:var(--white);
    background:var(--bg);
  }
  #stage svg.bg{position:absolute;inset:0}
  .t{position:absolute;white-space:nowrap;line-height:1;transform:translateY(-50%);font-size:37px;font-weight:400}
  .c{transform:translate(-50%,-50%)}
  .h{font-size:40px;font-weight:500;color:var(--orange)}
  .m{color:var(--gray)}
  .o{color:var(--orange)}
  .b{font-weight:500}
  .dot{position:absolute;width:7px;height:7px;border-radius:50%;transform:translate(-50%,-50%)}
  .dot.or{background:var(--orange)}
  .dot.wh{background:var(--white);width:10px;height:10px}
  .dot.gr{background:#8b93a1;width:12px;height:12px}
</style>
</head>
<body>
<div id="stage">

  <svg class="bg" width="2576" height="1287" viewBox="0 0 2576 1287" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient id="glint" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#9fb0ff" stop-opacity="0"/>
        <stop offset="1" stop-color="#c9d4ff" stop-opacity=".95"/>
      </linearGradient>
      <linearGradient id="glintR" x1="1" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#9fb0ff" stop-opacity="0"/>
        <stop offset="1" stop-color="#c9d4ff" stop-opacity=".95"/>
      </linearGradient>
      <linearGradient id="stem" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#2a3345" stop-opacity=".9"/>
        <stop offset="1" stop-color="#2a3345" stop-opacity="0"/>
      </linearGradient>
      <pattern id="grid" width="70" height="70" patternUnits="userSpaceOnUse" x="116" y="678">
        <path d="M70 0H0V70" fill="none" stroke="#1a2232" stroke-width="1.5"/>
      </pattern>
      <radialGradient id="gridfade" cx="0.15" cy="0.1" r="0.9">
        <stop offset="0" stop-color="#fff"/>
        <stop offset="1" stop-color="#000"/>
      </radialGradient>
      <mask id="gm"><rect x="116" y="678" width="1156" height="489" fill="url(#gridfade)"/></mask>
    </defs>

    <!-- Top-left stepped card -->
    <path fill="var(--card)" stroke="var(--stroke)" stroke-width="2"
      d="M144 142H1502Q1530 142 1530 170V284Q1530 312 1502 312H1332Q1274 312 1274 370V589Q1274 617 1246 617H144Q116 617 116 589V170Q116 142 144 142Z"/>

    <!-- Bottom-left card with grid -->
    <rect x="116" y="678" width="1156" height="489" rx="28" fill="var(--card)" stroke="var(--stroke)" stroke-width="2"/>
    <rect x="116" y="678" width="1156" height="489" fill="url(#grid)" mask="url(#gm)" opacity=".9"/>

    <!-- Right stepped card -->
    <path fill="var(--card)" stroke="var(--stroke)" stroke-width="2"
      d="M1619 142H2462Q2490 142 2490 170V1139Q2490 1167 2462 1167H1361Q1333 1167 1333 1139V380Q1333 352 1361 352H1530Q1591 352 1591 291V170Q1591 142 1619 142Z"/>

    <!-- Funnel decoration -->
    <g fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M2233 144V262Q2233 272 2240 279L2329 375L2418 285Q2425 278 2425 268V144" stroke="#2c3649"/>
      <path d="M2238 282L2329 375" stroke="url(#glint)"/>
      <path d="M2420 282L2329 375" stroke="url(#glintR)"/>
      <path d="M2329 375V540" stroke="url(#stem)" stroke-width="2"/>
    </g>
  </svg>

  <!-- ===== Top-left: summary ===== -->
  <div class="t b" style="left:174px;top:232px;font-weight:500">Two service profiles, and how much memory each one uses on the machine.</div>

  <div class="dot" style="left:199px;top:338px;width:14px;height:14px;background:#9aa2b0"></div>
  <div class="t" style="left:228px;top:336px"><span class="o">Default Setup</span> 1151 MB essential services running.</div>

  <div class="dot" style="left:199px;top:408px;width:14px;height:14px;background:#9aa2b0"></div>
  <div class="t" style="left:228px;top:406px"><span class="o">Full Setup</span> 3,594 MB all services enabled</div>

  <div class="dot" style="left:199px;top:478px;width:14px;height:14px;background:#9aa2b0"></div>
  <div class="t" style="left:228px;top:476px"><span class="o">Measured On</span> 3,915 MB total memory of a 4 GB</div>
  <div class="t" style="left:228px;top:534px">machine.</div>

  <!-- ===== Bottom-left: service profile ===== -->
  <div class="t c h" style="left:731px;top:775px">Service profile · default</div>
  <div class="t c" style="left:731px;top:836px">6 lit / 17 bays</div>

  <!-- col 1 -->
  <div class="dot or" style="left:191px;top:920px"></div><div class="t" style="left:211px;top:918px">traefik</div>
  <div class="dot or" style="left:191px;top:984px"></div><div class="t" style="left:211px;top:982px">mq</div>
  <div class="dot or" style="left:191px;top:1048px"></div><div class="t" style="left:211px;top:1046px">labsctl</div>
  <div class="t m" style="left:188px;top:1110px">mysql</div>
  <!-- col 2 -->
  <div class="t m" style="left:368px;top:918px">postgresql</div>
  <div class="t m" style="left:368px;top:982px">memcached</div>
  <div class="t m" style="left:368px;top:1046px">adminer</div>
  <div class="t m" style="left:368px;top:1110px">judge0</div>
  <!-- col 3 -->
  <div class="t m" style="left:618px;top:918px">dvwa</div>
  <div class="dot or" style="left:622px;top:984px"></div><div class="t" style="left:642px;top:982px">wgvpn</div>
  <div class="dot or" style="left:622px;top:1048px"></div><div class="t" style="left:642px;top:1046px">labs</div>
  <div class="dot or" style="left:622px;top:1111px"></div><div class="t" style="left:642px;top:1110px">mongo</div>
  <!-- col 4 -->
  <div class="t m" style="left:806px;top:918px">mariadb</div>
  <div class="t m" style="left:806px;top:982px">valkey</div>
  <div class="t m" style="left:806px;top:1046px">rabbitmq</div>
  <div class="t m" style="left:806px;top:1110px">redisinsight</div>
  <!-- col 5 -->
  <div class="t m" style="left:1042px;top:918px">embed</div>

  <!-- ===== Right: modules ===== -->
  <div class="t c h" style="left:1893px;top:369px">Modules · what people see</div>
  <div class="t c" style="left:1893px;top:428px">2 always on / 6</div>

  <div class="dot wh" style="left:1650px;top:507px"></div><div class="t" style="left:1681px;top:506px">Labs</div>
  <div class="dot wh" style="left:1992px;top:507px"></div><div class="t" style="left:2024px;top:506px">always</div>

  <div class="dot wh" style="left:1650px;top:595px"></div><div class="t" style="left:1681px;top:594px">Instances</div>
  <div class="dot wh" style="left:1992px;top:595px"></div><div class="t" style="left:2024px;top:594px">always</div>

  <div class="t m" style="left:1681px;top:682px">Learn</div>       <div class="t m" style="left:2024px;top:682px">optional</div>
  <div class="t m" style="left:1681px;top:769px">Discuss</div>     <div class="t m" style="left:2024px;top:769px">optional</div>
  <div class="t m" style="left:1681px;top:857px">Evaluate</div>    <div class="t m" style="left:2024px;top:857px">optional</div>
  <div class="t m" style="left:1681px;top:944px">CTF Missions</div><div class="t m" style="left:2024px;top:944px">optional</div>

</div>

<script>
  // Scale the 2576x1287 stage to fit any viewport
  const s = document.getElementById('stage');
  function fit(){
    const k = Math.min(innerWidth/2576, innerHeight/1287);
    s.style.transform = 'scale(' + k + ')';
  }
  addEventListener('resize', fit); fit();
</script>
</body>
</html>