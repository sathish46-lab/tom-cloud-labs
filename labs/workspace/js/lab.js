/**
 * ============================================================================
 * TOM LABS - LAB DASHBOARD CONTROLLER
 * ============================================================================
 * Handles all lab-specific operations: deployment, monitoring, UI updates
 *
 * Dependencies:
 * - mq-client.js (OverviewSocket, LogSocket, ActivityTracker)
 * - chart.js
 * - coreui.js
 *
 * ============================================================================
 */

const Dashboard = {
  /* ========================================================================
   * STATE MANAGEMENT
   * ====================================================================== */
  isProcessing: false,
  statsInterval: null,
  charts: {},
  historyLimit: 20,
  isFirstLoad: true,

  /* ========================================================================
   * INITIALIZATION
   * ====================================================================== */

  /**
   * Main initialization - called on page load
   */
  init: function () {
    console.log("[Dashboard] Initializing...");

    // 1. Initialize Charts
    this.initCharts();

    // 2. Initial Terminal Setup
    this.resetTerminal();
    this.appendCommand("");

    // 3. Start Services
    this.initSockets();
    this.startStatsPolling();

    // 4. Initialize Optimization Tracker
    if (typeof ActivityTracker !== "undefined") {
      ActivityTracker.init();
    }

    console.log("[Dashboard] Initialization complete.");
  },

  /* ========================================================================
   * SOCKET MANAGEMENT
   * ====================================================================== */

  /**
   * Toggle button loading state
   * @param {HTMLElement} btn 
   * @param {boolean} show 
   */
  toggleLoading: function (btn, show) {
    if (!btn) return;

    if (show) {
      const type = btn.getAttribute('data-coreui-spinner-type') || 'border';
      const spinner = `<span class="spinner-${type} spinner-${type}-sm me-2" role="status" aria-hidden="true"></span>`;

      if (!btn.dataset.originalContent) {
        btn.dataset.originalContent = btn.innerHTML;
      }

      btn.classList.add('disabled');
      // Fix: Only show Spinner + Text (Hide original Icon)
      btn.innerHTML = spinner + btn.textContent.trim();
    } else {
      if (btn.dataset.originalContent) {
        btn.innerHTML = btn.dataset.originalContent;
      }
      btn.classList.remove('disabled');
    }
  },

  /**
   * Initialize Socket Connections (Overview and Instance Logs)
   */
  initSockets: function () {
    // console.log("[Dashboard] Initializing sockets...");

    // 1. Connect to Global Sidebar Stats (Overview)
    try {
      if (!OverviewSocket.isConnected && document.getElementById("sidebar-stats-container") && !document.getElementById("session-expired-overlay")) {
        OverviewSocket.connect("/stats-ws", (data) =>
          this.updateSidebar(data),
        );
      }
    } catch (e) {
      console.error("[Dashboard] OverviewSocket connection failed:", e);
    }

    // 2. Connect to Instance Logs (Only if session exists and not expired)
    try {
      if (window.SESSION_HASH && !LogSocket.isConnected && !document.getElementById("session-expired-overlay")) {
        const dot = document.getElementById("mq-status-dot");
        // Only connect if the UI element exists or we handle the null UI gracefully
        const ui = dot ? { dot: dot } : null;

        LogSocket.connect(
          `logs.${window.SESSION_HASH}`,
          (data) => this.appendLog(data),
          ui,
        );
      }
    } catch (e) {
      console.error("[Dashboard] LogSocket connection failed:", e);
    }
  },

  /* ========================================================================
   * CHART MANAGEMENT
   * ====================================================================== */

  /**
   * Initialize all monitoring charts
   */
  initCharts: function () {
    const create = (id, color, type = "line", extraOptions = {}) => {
      const ctx = document.getElementById(id);
      if (!ctx) return;

      const isBar = type === "bar";
      const limit = extraOptions.limit || this.historyLimit;

      const datasets = extraOptions.datasets || [
        {
          data: new Array(limit).fill(0),
          borderColor: color,
          backgroundColor: isBar ? color : "transparent",
          borderWidth: isBar ? 0 : 2,
          pointRadius: 0,
          tension: extraOptions.tension !== undefined ? extraOptions.tension : 0.4,
          fill: extraOptions.fill || false,
          barThickness: extraOptions.barThickness || 'flex',
          borderRadius: 2
        },
      ];

      this.charts[id] = new Chart(ctx, {
        type: type,
        data: {
          labels: new Array(limit).fill(""),
          datasets: datasets,
        },
        options: {
          maintainAspectRatio: false,
          animation: {
            duration: 300,
            easing: 'linear'
          },
          plugins: {
            legend: { display: false },
            tooltip: { enabled: false }
          },
          scales: {
            x: { display: false },
            y: {
              display: false,
              suggestedMin: 0
            }
          },
          ...extraOptions.options
        },
      });
      this.charts[id].limit = limit;
    };

    // SNA Colors
    const colors = {
      cyan: "#00e5ff",
      green: "#00ff88",
      yellow: "#ffcc00",
      red: "#ff4444",
      blue: "#3d5afe"
    };

    // Initialize all charts with SNA-style configurations
    // Net IO: Download (Purple) & Upload (Cyan)
    create("chart-net-io", "#8b91f9", "line", {
      tension: 0.4,
      limit: 30,
      datasets: [
        { label: 'Download', borderColor: "#8b91f9", data: [], borderWidth: 2, pointRadius: 0, tension: 0.4 },
        { label: 'Upload', borderColor: "#50c7f6", data: [], borderWidth: 2, pointRadius: 0, tension: 0.4 }
      ]
    });

    // Block IO: Read (Yellow) & Write (Green)
    create("chart-block-io", "#f2b90d", "line", {
      tension: 0.4,
      limit: 30,
      datasets: [
        { label: 'Read', borderColor: "#f2b90d", data: [], borderWidth: 2, pointRadius: 0, tension: 0.4 },
        { label: 'Write', borderColor: "#55b16e", data: [], borderWidth: 2, pointRadius: 0, tension: 0.4 }
      ]
    });

    // Averages use varying densities and wide bars
    create("chart-avg-1", colors.blue, "bar", { barThickness: 15, limit: 6 });
    create("chart-avg-5", colors.yellow, "bar", { barThickness: 4, limit: 15 });
    create("chart-avg-15", colors.green, "bar", { barThickness: 4, limit: 15 });

    // History uses jagged line charts
    create("chart-peak-cpu", colors.cyan, "line", { tension: 0.4, limit: 30 });
    create("chart-max-pid", colors.red, "line", {
      tension: 0.4,
      limit: 30,
      options: {
        scales: {
          y: {
            display: false,
            suggestedMin: 0,
            suggestedMax: 150 // Better context for standard PID counts (~80-100)
          }
        }
      }
    });
    create("chart-high-mem", colors.yellow, "line", { tension: 0.4, limit: 30 });
  },

  /**
   * Push new data point to a chart
   * @param {string} id - Chart ID
   * @param {number} value - Data value
   */
  pushChartData: function (id, value) {
    const chart = this.charts[id];
    if (!chart) return;

    const limit = chart.limit || this.historyLimit;

    // Handle multi-dataset vs single dataset
    if (Array.isArray(value)) {
      value.forEach((v, i) => {
        if (chart.data.datasets[i]) {
          const d = chart.data.datasets[i].data;
          d.push(v);
          if (d.length > limit) d.shift();
        }
      });
    } else {
      const d = chart.data.datasets[0].data;
      d.push(value);
      if (d.length > limit) d.shift();
    }

    chart.update("none");
  },

  /* ========================================================================
   * UI UPDATES - SIDEBAR
   * ====================================================================== */

  /**
   * Update sidebar stats from MQ data
   * @param {object} data - System stats from MQ
   */
  updateSidebar: function (data) {
    // Remove loading state on first update
    const container = document.getElementById("sidebar-stats-container");
    if (container && container.classList.contains("loading-state")) {
      container.classList.remove("loading-state");
      container.querySelectorAll(".progress-bar").forEach((el) => {
        el.classList.remove("placeholder-shimmer");
        el.style.width = "0%";
      });
    }

    // Update CPU bars
    if (data.cpu) {
      data.cpu.forEach((val, i) => {
        const bar = document.querySelector(`.cpu-${i}`);
        if (bar) bar.style.width = val + "%";
      });

      const avg = (
        data.cpu.reduce((a, b) => a + b, 0) / data.cpu.length
      ).toFixed(2);
      document.getElementById("sidebar-cpu-val").innerText = avg + "%";
    }

    // Update Load Average
    if (data.loadavg) {
      document.getElementById("sidebar-load-val").innerText =
        `Load Avg: ${data.loadavg[0].toFixed(2)}, ${data.loadavg[1].toFixed(2)}, ${data.loadavg[2].toFixed(2)}`;
    }

    // Update Memory
    if (data.mem) {
      const used = (data.mem.used / 1073741824).toFixed(2);
      const total = (data.mem.total / 1073741824).toFixed(2);
      const avail = (data.mem.available / 1073741824).toFixed(2);
      const perc = (data.mem.used / data.mem.total) * 100;

      document.getElementById("sidebar-mem-bar").style.width = perc + "%";
      document.getElementById("sidebar-mem-details").innerText =
        `${used} GiB / ${total} GiB Avail: ${avail} GiB`;
    }

    // Update Swap
    if (data.swap) {
      const sUsed = (data.swap.used / 1048576).toFixed(2);
      const sTotal = (data.swap.total / 1073741824).toFixed(2);
      const sFree = (data.swap.free / 1073741824).toFixed(2);

      document.getElementById("sidebar-swap-bar").style.width =
        data.swap.percent + "%";
      document.getElementById("sidebar-swap-details").innerText =
        `${sUsed} MiB / ${sTotal} GiB Free: ${sFree} GiB`;
    }
  },

  /* ========================================================================
   * UI UPDATES - INSTANCE STATS
   * ====================================================================== */

  /**
   * Start polling instance stats from API
   */
  startStatsPolling: function () {
    // 1. Only poll if we have a valid session hash (Dashboard Page)
    if (!window.SESSION_HASH) {
      // console.log("[Dashboard] No active session hash found. Stats polling disabled.");
      return;
    }

    const start = () => {
      if (this.statsInterval) clearInterval(this.statsInterval);

      const poll = () => {
        if (document.hidden) return; // double check

        fetch(`/api/instance/stats?hash=${window.SESSION_HASH}`)
          .then((res) => res.json())
          .then((data) => {
            if (data.status === "offline" || data.status === "initializing") {
              this.updateUIIdle();
            } else {
              this.updateUI(data);
            }
          })
          .catch((err) => {
            console.warn("Stats Fetch Error:", err.message);
            this.updateUIIdle();
          });
      };

      poll(); // Initial call
      this.statsInterval = setInterval(poll, 5000); // Standard 5s polling to save resources
    };

    const stop = () => {
      if (this.statsInterval) {
        clearInterval(this.statsInterval);
        this.statsInterval = null;
      }
    };

    // 2. Handle Visibility Changes to save resources
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        stop();
      } else {
        start();
      }
    });

    // Start initially if visible
    if (!document.hidden) {
      start();
    }
  },

  /**
   * Update UI with live stats
   * @param {object} data - Stats from API
   */
  updateUI: function (data) {
    const safeSetText = (id, text) => { const el = document.getElementById(id); if (el) el.innerText = text; };
    const safeSetWidth = (id, width) => { const el = document.getElementById(id); if (el) el.style.width = width; };

    // Update text stats
    const pidContainer = document.getElementById("stat-pid-container");
    if (pidContainer) pidContainer.style.display = "block";
    
    safeSetText("stat-cpu-usage", data.CPUPerc);
    safeSetWidth("stat-cpu-bar", data.CPUPerc);
    safeSetText("stat-pid-count", data.PIDs);
    safeSetText("stat-mem-perc", data.MemPerc);
    safeSetWidth("stat-mem-bar", data.MemPerc);
    safeSetText("stat-mem-info", data.MemUsage);

    safeSetText("stat-load-1", parseFloat(data.Load1).toFixed(4));
    safeSetText("stat-load-5", parseFloat(data.Load5).toFixed(4));
    safeSetText("stat-load-15", parseFloat(data.Load15).toFixed(4));

    safeSetText("stat-peak-cpu", data.PeakCPU);
    safeSetText("stat-max-pid", data.MaxPID);
    safeSetText("stat-high-mem", data.HighMem);
    safeSetText("stat-net-io", data.NetIO);
    safeSetText("stat-block-io", data.BlockIO);

    // Update charts (first load vs. incremental)
    if (this.isFirstLoad && data.cpu_h) {
      const mapping = {
        "chart-peak-cpu": data.cpu_h,
        "chart-high-mem": data.mem_h,
        "chart-net-io": data.net_h,
        "chart-block-io": data.block_h,
        "chart-max-pid": data.pids_h,
        "chart-avg-1": data.l1_h,
        "chart-avg-5": data.l5_h,
        "chart-avg-15": data.l15_h,
      };

      for (let id in mapping) {
        if (this.charts[id])
          this.charts[id].data.datasets[0].data = mapping[id];
      }

      Object.values(this.charts).forEach((c) => c.update());
      this.isFirstLoad = false;
    } else {
      // Incremental updates
      // Parse composite strings for IO charts (e.g., "1.46kB / 358B")
      const parseIO = (str) => {
        if (!str) return [0, 0];
        const parts = str.split(' / ').map(p => parseFloat(p) || 0);
        return parts.length === 2 ? parts : [parts[0] || 0, 0];
      };

      this.pushChartData("chart-net-io", parseIO(data.NetIO));
      this.pushChartData("chart-block-io", parseIO(data.BlockIO));

      this.pushChartData("chart-peak-cpu", parseFloat(data.CPUPerc));
      this.pushChartData("chart-high-mem", parseFloat(data.MemPerc));
      this.pushChartData("chart-max-pid", parseInt(data.PIDs));

      this.pushChartData("chart-avg-1", data.Load1);
      this.pushChartData("chart-avg-5", data.Load5);
      this.pushChartData("chart-avg-15", data.Load15);
    }

    // Update status badge
    const badgeArea = document.getElementById("badge-area");
    if (badgeArea && !badgeArea.innerHTML.includes("Running")) {
      badgeArea.innerHTML = `<span class="badge text-bg-success border-0 px-2 py-1 small pulse">Running</span>`;
    }
  },

  /**
   * Reset UI to idle/offline state
   */
  updateUIIdle: function () {
    const resets = {
      "stat-cpu-usage": "0.00%",
      "stat-mem-perc": "0.00%",
      "stat-load-1": "0.0000",
      "stat-load-5": "0.0000",
      "stat-load-15": "0.0000",
      "stat-peak-cpu": "0.00%",
      "stat-net-io": "0B / 0B",
      "stat-block-io": "0B / 0B",
      "stat-max-pid": "0",
      "stat-high-mem": "0.00 MB",
    };

    for (let id in resets) {
      const el = document.getElementById(id);
      if (el) el.innerText = resets[id];
    }

    ["stat-cpu-bar", "stat-mem-bar"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.style.width = "0%";
    });

    const memInfo = document.getElementById("stat-mem-info");
    if (memInfo) memInfo.innerText = "Lab Offline";

    const badgeArea = document.getElementById("badge-area");
    if (badgeArea) {
      badgeArea.innerHTML = `<span class="badge text-bg-danger border-0 px-2 py-1 small">Offline</span>`;
    }

    const pidContainer = document.getElementById("stat-pid-container");
    if (pidContainer) pidContainer.style.display = "none";

    // Reset charts
    Object.values(this.charts).forEach((chart) => {
      chart.data.datasets.forEach((dataset) => {
        dataset.data = new Array(this.historyLimit).fill(null);
      });
      chart.update();
    });
  },

  /* ========================================================================
   * TERMINAL / LOG MANAGEMENT
   * ====================================================================== */

  /**
   * Clear terminal display
   */
  resetTerminal: function () {
    const container = document.getElementById("live-logs-container");
    if (container) container.innerHTML = "";
  },

  /**
   * Append a command prompt line to terminal
   * @param {string} cmd - Command text
   */
  appendCommand: function (cmd) {
    const container = document.getElementById("live-logs-container");
    if (!container) return;

    const user = window.LAB_USER || "tom";
    const host = "Tomlabs";
    const div = document.createElement("div");
    div.className = "log-entry py-1";
    div.innerHTML = `<span class="term-user">${user}</span>@<span class="term-host" style="color:#FFA500;">${host}</span> <span class="term-symbol">$</span> <span class="text-white">${cmd}</span>`;
    container.appendChild(div);
  },

  /**
   * Append a log message to terminal (called from MQ)
   * @param {object|string} data - Log data from MQ
   */
  appendLog: function (data) {
    const container = document.getElementById("live-logs-container");
    if (!container) return;

    // Extract message
    let msg = data.log || data.message || data;

    // Clean up message formatting
    msg = msg.replace(/^\[\d{2}:\d{2}:\d{2}\]\s*/, "");
    msg = msg.replace(/^(\[\*\]\s*)+/, "[*] ");
    msg = msg.replace(/^(\[!\]\s*)+/, "[!] ");

    // Create log entry
    const div = document.createElement("div");
    div.className = "log-entry py-1";

    // Color coding
    if (msg.startsWith("[✓]")) div.style.color = "#a6e3a1";
    if (msg.startsWith("[!]")) div.style.color = "#f38ba8";

    div.innerText = msg;
    container.appendChild(div);

    // Auto-scroll to bottom
    const viewport = document.getElementById("terminal-viewport");
    if (viewport) viewport.scrollTop = viewport.scrollHeight;

    // Check for completion messages
    const lower = msg.toLowerCase();
    if (
      msg.includes("[*] reload") ||
      (msg.includes("[✓]") &&
        (lower.includes("deployment complete") ||
          lower.includes("successfully") ||
          lower.includes("graceful shutdown") ||
          lower.includes("now offline") ||
          lower.includes("complete")))
    ) {
      // Sequence finished
      this.isProcessing = false;

      // Update ActivityTracker
      if (typeof ActivityTracker !== "undefined") {
        ActivityTracker.setProcessing(false);
      }

      // Increased delay to 4s to ensure DB writes are fully committed/visible to PHP
      setTimeout(() => location.reload(), 4000);
    }
  },
};

/* ============================================================================
 * LAB ACTION HANDLERS
 * ========================================================================== */
/**
 * handleDeploy now refreshes the domain chips automatically
 */
async function handleDeploy(btn, labType) {
  if (Dashboard.isProcessing) return;

  // Start loading animation
  Dashboard.toggleLoading(btn, true);

  try {
    const type = labType || window.LAB_TYPE || "essentials";

    // 1. Reset Modal State
    document.getElementById("domain_dropdown").style.display = "none";
    document.getElementById("dropdown_arrow").classList.remove("bx-chevron-up");
    document.getElementById("dropdown_arrow").classList.add("bx-chevron-down");

    // Custom Domain Visibility for MinIO & n8n
    const minioWrapper = document.getElementById("minio_domain_wrapper");
    const n8nWrapper = document.getElementById("n8n_domain_wrapper");
    const vscWrapper = document.getElementById("vsc_domain_wrapper");
    const exposeWrapper = document.getElementById("expose_web_wrapper");
    const domainSelectionWrapper = document.getElementById("domain_selection_wrapper");

    if (type === 'minio') {
      if (minioWrapper) minioWrapper.style.display = 'block';
      if (n8nWrapper) n8nWrapper.style.display = 'none';
      if (vscWrapper) vscWrapper.style.display = 'none';
      if (exposeWrapper) exposeWrapper.style.display = 'none';
      if (domainSelectionWrapper) domainSelectionWrapper.style.display = 'none';
    } else if (type === 'n8n') {
      if (minioWrapper) minioWrapper.style.display = 'none';
      if (n8nWrapper) n8nWrapper.style.display = 'block';
      if (vscWrapper) vscWrapper.style.display = 'none';
      if (exposeWrapper) exposeWrapper.style.display = 'none';
      if (domainSelectionWrapper) domainSelectionWrapper.style.display = 'none';
    } else {
      if (minioWrapper) minioWrapper.style.display = 'none';
      if (n8nWrapper) n8nWrapper.style.display = 'none';
      if (vscWrapper) vscWrapper.style.display = 'flex';
      if (exposeWrapper) exposeWrapper.style.display = 'flex';

      const isExposed = document.getElementById("expose_web_toggle").value === 'true';
      if (domainSelectionWrapper) domainSelectionWrapper.style.display = isExposed ? 'flex' : 'none';
    }

    // 2. Bind the Button
    document.getElementById("redeploy-confirm-btn").onclick = () => executeRedeploy(type);

    // 3. Show Modal
    new coreui.Modal(document.getElementById("redeployModal")).show();

    // 4. PERSISTENCE FIX: Draw chips from existing PHP-checked boxes
    setTimeout(() => {
      updateSelectedDomains();
      updateDomainAvailability();
    }, 200);
  } catch (e) {
    console.error("Deploy Error:", e);
  } finally {
    // Stop loading animation immediately after logic runs (modal opens)
    Dashboard.toggleLoading(btn, false);
  }
}

/**
 * Executes the actual POST request
 */
async function executeRedeploy(labType) {
  const modalEl = document.getElementById("redeployModal");
  const type = labType || window.LAB_TYPE || "essentials";
  const modal = coreui.Modal.getInstance(
    document.getElementById("redeployModal"),
  );

  // 1. Collect form data
  const vscDomain = document.getElementById("vsc_domain_selector").value;
  const exposeWeb = document.getElementById("expose_web_toggle").value;
  const checkedDomains = modalEl.querySelectorAll(".domain-selector:checked");
  const domains = Array.from(checkedDomains).map((cb) => cb.value);

  // Collect MinIO specific domains
  const minioConsole = document.getElementById("minio_console_domain").value;
  const minioApi = document.getElementById("minio_api_domain").value;
  // Collect n8n specific domains
  const n8nDomain = document.getElementById("n8n_domain_selector") ? document.getElementById("n8n_domain_selector").value : '';

  modal.hide();
  Dashboard.isProcessing = true;
  if (typeof ActivityTracker !== "undefined")
    ActivityTracker.setProcessing(true);

  // Add Grow Animation to Deployment Button (matches Stop/Launch behavior)
  const deployBtn = document.getElementById("btn-deploy-action"); // Modal button
  if (deployBtn) {
    deployBtn.classList.add("disabled");
    deployBtn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span> Processing';
  }

  // ALSO trigger the main header redeploy button animation
  const headerRedeployBtn = document.querySelector('.btn-redeploy-lab');
  if (headerRedeployBtn) {
    Dashboard.toggleLoading(headerRedeployBtn, true);
  }

  // 2. Log to Terminal (Now shows 'minio' correctly)
  Dashboard.resetTerminal();
  Dashboard.appendCommand(
    `labsctl redeploy ${type} --hash=${window.SESSION_HASH}`,
  );

  // 3. Handshake with PHP API
  const formData = new URLSearchParams();
  formData.append("lab", type);
  formData.append("hash", window.SESSION_HASH);
  formData.append("expose_web", exposeWeb);
  formData.append("code_domain", vscDomain);

  if (type === 'minio') {
    formData.append("minio_console_domain", minioConsole);
    formData.append("minio_api_domain", minioApi);
  } else if (type === 'n8n') {
    formData.append("n8n_domain", n8nDomain);
  }

  domains.forEach((d) => formData.append("domains[]", d));

  const response = await fetch("/api/instance/deploy", {
    method: "POST",
    body: formData,
  });

  const data = await response.json();

  if (data.status === 'success' && data.hash) {
    // UPDATE GLOBAL HASH
    window.SESSION_HASH = data.hash;

    // RECONNECT SOCKET to ensure we are listening to the active channel
    console.log("[Dashboard] specific socket reconnecting to: " + data.hash);
    LogSocket.disconnect();
    setTimeout(() => {
      LogSocket.connect("logs." + data.hash, (d) => Dashboard.appendLog(d));
    }, 100);
  }

  Dashboard.appendLog("[*] Handshake accepted. Starting stream...");
}

/**
 * Handle Stop button click - Show Modal
 */
function handleStop() {
  if (Dashboard.isProcessing) return;
  new coreui.Modal(document.getElementById("stopModal")).show();
}

/**
 * Execute the actual stop request
 */
async function executeStop() {
  const modalEl = document.getElementById("stopModal");
  const modal = coreui.Modal.getInstance(modalEl);
  const btn = document.getElementById("stop-confirm-btn");
  const headerBtn = document.getElementById("btn-stop-action");
  const type = window.LAB_TYPE || "essentials";

  modal.hide();
  Dashboard.isProcessing = true;
  if (typeof ActivityTracker !== "undefined") {
    ActivityTracker.setProcessing(true);
  }

  // Trigger header button animation too
  if (headerBtn) Dashboard.toggleLoading(headerBtn, true);

  // Update modal button state (if visible)
  if (btn) {
    btn.classList.add("disabled");
    btn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span> Stopping...';
  }

  Dashboard.resetTerminal();
  Dashboard.appendCommand(`labsctl stop ${type} --hash=${window.SESSION_HASH}`);

  // Wait a bit then stop
  await new Promise((r) => setTimeout(r, 300));
  Dashboard.appendLog("[*] Analyzing active session hooks...");

  try {
    const response = await fetch("/api/instance/stop", {
      method: "POST",
      body: new URLSearchParams({
        lab: type,
        hash: window.SESSION_HASH
      }),
    });

    const data = await response.json();
    if (data.status === 'success') {
      Dashboard.appendLog("[*] Shutdown signal acknowledged. Streaming logs...");
    } else {
      Dashboard.appendLog(`[!] Error: ${data.error || 'Shutdown request failed'}`);
      Dashboard.isProcessing = false;
      if (headerBtn) Dashboard.toggleLoading(headerBtn, false);
    }
  } catch (e) {
    console.error("Stop Error:", e);
    if (headerBtn) Dashboard.toggleLoading(headerBtn, false);
    Dashboard.isProcessing = false;
  }
}

/**
 * Launch Code-Server IDE
 * @param {Event} event
 */
async function launchCodeIDE(event, targetUrl = null) {
  // 1. Determine Target URL & Mode
  // If we are in MinIO mode, the button might be different?
  // Actually MinIO launch is separate in the modal. 
  // This function is for the main "Code" or "Launch" button on dashboard.

  const type = window.LAB_TYPE || 'essentials';
  let url = targetUrl || window.CODE_SERVER_URL;
  let actionName = "Code-Server";
  let ensureAction = "ensure-codeserver";

  // MinIO Handling
  if (type === 'minio') {
    // For MinIO, we probably just want to open the console
    // But we can still "ensure" the container is running if we want.
    // However currently MinIO doesn't have an "idle timeout" feature planned yet.
    // So we just open the URL.
    if (!targetUrl && window.LAB_CONFIG && window.LAB_CONFIG.fields) {
      const consoleField = window.LAB_CONFIG.fields.find(f => f.label === 'MinIO Console Endpoint');
      if (consoleField) url = consoleField.value;
    }
    actionName = "MinIO Console";
    ensureAction = null; // No auto-start logic for MinIO yet
  }

  const btn = event.target.closest("button");
  const originalText = btn.innerHTML;

  // 3. Auto-Start Logic (Only for Code-Server currently)
  // We move this BEFORE the URL check because we might get the URL from the response
  if (ensureAction) {
    // Add spinner to button
    btn.classList.add("disabled");
    btn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span> Waking up...';
    Dashboard.resetTerminal();
    Dashboard.appendLog(`[*] Ensuring ${actionName} is running...`);

    try {
      const formData = new URLSearchParams();
      formData.append("lab", type);
      formData.append("hash", window.SESSION_HASH);

      let response = await fetch("/api/instance/ensure_codeserver", {
        method: "POST",
        body: formData
      });

      let data = await response.json();
      if (data.url) {
        url = data.url; // Use fresh URL from backend if provided
        window.CODE_SERVER_URL = url;
        Dashboard.appendLog(`[*] Backend returned URL: ${url}`);
      }

      // Wait for worker logs to show success
      await new Promise((r) => setTimeout(r, 2000));

    } catch (e) {
      console.error("Auto-start failed", e);
      Dashboard.appendLog(`[!] Warning: Auto-start trigger failed. Trying saved URL...`);
    }
  }

  if (!url || url === "") {
    Dashboard.appendLog(`[!] Critical: No URL found for ${actionName}.`);
    alert(`${actionName} URL not found. Please Redeploy your lab.`);
    btn.classList.remove("disabled");
    btn.innerHTML = originalText;
    return;
  }

  // 2. Add spinner to button
  btn.classList.add("disabled");
  btn.innerHTML = '<span class="spinner-grow spinner-grow-sm me-2" role="status" aria-hidden="true"></span> Launching...';

  Dashboard.resetTerminal();

  // 3. Auto-Start Logic (Only for Code-Server currently)
  if (ensureAction) {
    Dashboard.appendLog(`[*] Ensuring ${actionName} is running...`);

    try {
      // Handshake with API to trigger worker
      const formData = new URLSearchParams();
      formData.append("lab", type);
      formData.append("hash", window.SESSION_HASH);

      await fetch("/api/instance/ensure_codeserver", {
        method: "POST",
        body: formData
      });

      // Wait for worker logs to show success
      // We'll give it a few seconds buffer
      await new Promise((r) => setTimeout(r, 2000));

    } catch (e) {
      console.error("Auto-start failed", e);
      Dashboard.appendLog(`[!] Warning: Auto-start trigger failed. Trying direct connection...`);
    }
  }

  // 4. Show validation logs (Visual feedback)
  Dashboard.appendLog(`[*] Connecting to ${url}...`);
  await new Promise((r) => setTimeout(r, 800));

  // 5. Open window
  const newWin = window.open(url, "_blank");

  if (!newWin || newWin.closed || typeof newWin.closed === "undefined") {
    Dashboard.appendLog("[!] Popup Blocked! User intervention required.");
    alert("Your browser blocked the popup. Please allow popups for this site.");
  } else {
    Dashboard.appendLog(`[✓] ${actionName} Launched.`);
  }

  // Cleanup
  await new Promise((r) => setTimeout(r, 500));
  btn.classList.remove("disabled");
  btn.innerHTML = originalText;

  // Close modal if open
  const modalEl = document.getElementById("vscModal");
  if (modalEl) {
    const modal = coreui.Modal.getInstance(modalEl);
    if (modal) modal.hide();
  }
}

/* ============================================================================
 * DOMAIN SELECTION HELPERS (for Redeploy Modal)
 * ========================================================================== */

// Track dropdown state
let domainDropdownOpen = false;

/**
 * Toggle domain dropdown visibility
 *
 * Focuses the hidden input field when the container is clicked
 */
function focusSearch() {
  document.getElementById("domain_search").focus();
}

/**
 * Shows the dropdown when the input is focused or user starts typing
 */
function showDropdown() {
  const dropdown = document.getElementById("domain_dropdown");
  dropdown.style.display = "block";
  document
    .getElementById("dropdown_arrow")
    .classList.replace("bx-chevron-down", "bx-chevron-up");
}

/**
 * Toggles the dropdown specifically for the arrow click
 *
 * Filter logic: Shows matching domains as you type.
 * If searching, it ensures the list is visible.
 */
function filterDomains() {
  const searchVal = document
    .getElementById("domain_search")
    .value.toLowerCase();
  const dropdown = document.getElementById("domain_dropdown");
  const items = document.querySelectorAll(".domain-item");
  const arrow = document.getElementById("dropdown_arrow");

  if (searchVal.length > 0) {
    dropdown.style.display = "block"; // Show to display results
    arrow.classList.replace("bx-chevron-down", "bx-chevron-up");

    items.forEach((item) => {
      const text = item.innerText.toLowerCase();
      item.style.display = text.includes(searchVal) ? "block" : "none";
    });
  } else {
    // If search is cleared, hide the list unless it was manually expanded
    if (!window.dropdownManuallyExpanded) {
      dropdown.style.display = "none";
      arrow.classList.replace("bx-chevron-up", "bx-chevron-down");
    }
  }
}

/**
 * Dropdown Arrow Toggle Logic
 */
function toggleDomainDropdown(event) {
  if (event) event.stopPropagation();
  const dropdown = document.getElementById("domain_dropdown");
  const arrow = document.getElementById("dropdown_arrow");
  const isHidden = dropdown.style.display === "none" || dropdown.style.display === "";

  if (isHidden) {
    dropdown.style.display = "block";
    arrow.style.transform = "rotate(180deg)";
  } else {
    dropdown.style.display = "none";
    arrow.style.transform = "rotate(0deg)";
  }
}

// Add global state tracker
window.dropdownManuallyExpanded = false;

/**
 * Modified update function to handle placeholder behavior
 */
function updateSelectedDomains() {
  const display = document.getElementById("selected_domains_display");
  const searchInput = document.getElementById("domain_search");
  const checkedBoxes = document.querySelectorAll(".domain-selector:checked");

  display.innerHTML = "";

  if (checkedBoxes.length > 0) {
    searchInput.placeholder = ""; // Hide placeholder if domains are selected
    checkedBoxes.forEach((checkbox) => {
      const chip = document.createElement("span");
      chip.className =
        "badge bg-dark bg-opacity-50 border border-white border-opacity-10 rounded-pill d-inline-flex align-items-center px-3 py-1 me-1 mb-1";
      chip.style.fontSize = "11px";
      chip.innerHTML = `
                <span class="text-white opacity-75">${checkbox.value}</span>
                <i class='bx bx-x ms-2' style="cursor:pointer" onclick="removeDomainChip('${checkbox.id}'); event.stopPropagation();"></i>
            `;
      display.appendChild(chip);
    });
  } else {
    searchInput.placeholder = "Click to select domains...";
  }

  // Update domain availability across all selectors
  updateDomainAvailability();
}

/**
 * Remove a domain chip
 * @param {string} checkboxId - ID of checkbox to uncheck
 */
function removeDomainChip(checkboxId) {
  const checkbox = document.getElementById(checkboxId);
  if (checkbox) {
    checkbox.checked = false;
    updateSelectedDomains();
  }
}

/**
 * Select all domains
 */
function selectAllDomains() {
  const checkboxes = document.querySelectorAll(".domain-selector");
  checkboxes.forEach((cb) => (cb.checked = true));
  updateSelectedDomains();
}

/**
 * Toggle domain section visibility based on "Expose to Web" choice
 */
function toggleDomainSection() {
  const isPublic =
    document.getElementById("expose_web_toggle").value === "true";
  const wrapper = document.getElementById("domain_selection_wrapper");

  if (isPublic) {
    wrapper.style.display = "flex";
    wrapper.classList.replace("animate__fadeOut", "animate__fadeIn");
  } else {
    wrapper.classList.replace("animate__fadeIn", "animate__fadeOut");

    // Delay display:none to allow animation
    setTimeout(() => {
      if (document.getElementById("expose_web_toggle").value === "false") {
        wrapper.style.display = "none";
      }
    }, 500);

    // Clear selections when hiding
    document
      .querySelectorAll(".domain-selector")
      .forEach((cb) => (cb.checked = false));
    updateSelectedDomains();
  }
}

/**
 * Update domain availability across all selectors
 * Ensures a domain can only be used in ONE place at a time
 * Uses database-backed DOMAIN_USAGE_MAP for cross-lab checking
 */
function updateDomainAvailability() {
  // 1. Use the database-backed usage map (already includes ALL labs)
  const usageMap = window.DOMAIN_USAGE_MAP || {};

  // Also check currently selected domains in THIS modal (not yet saved to DB)
  const currentSelections = {};

  const vscSelector = document.getElementById("vsc_domain_selector");
  if (vscSelector && vscSelector.offsetParent !== null) {
    const vscDomain = vscSelector.value;
    if (vscDomain && !vscDomain.includes('.tomweb.shop')) {
      currentSelections[vscDomain] = { usage: 'VS Code Web', lab_type: window.LAB_TYPE };
    }
  }

  const minioConsoleSelector = document.getElementById("minio_console_domain");
  if (minioConsoleSelector && minioConsoleSelector.offsetParent !== null) {
    const consoleDomain = minioConsoleSelector.value;
    if (consoleDomain && !consoleDomain.includes('.tomweb.shop')) {
      currentSelections[consoleDomain] = { usage: 'MinIO Console', lab_type: window.LAB_TYPE };
    }
  }

  const minioApiSelector = document.getElementById("minio_api_domain");
  if (minioApiSelector && minioApiSelector.offsetParent !== null) {
    const apiDomain = minioApiSelector.value;
    if (apiDomain && !apiDomain.includes('.tomweb.shop')) {
      currentSelections[apiDomain] = { usage: 'S3 API', lab_type: window.LAB_TYPE };
    }
  }

  const n8nSelector = document.getElementById("n8n_domain_selector");
  if (n8nSelector && n8nSelector.offsetParent !== null) {
    const n8nDomain = n8nSelector.value;
    if (n8nDomain && !n8nDomain.includes('.tomweb.shop')) {
      currentSelections[n8nDomain] = { usage: 'n8n Interface', lab_type: window.LAB_TYPE };
    }
  }

  const checkedDomains = document.querySelectorAll(".domain-selector:checked");
  checkedDomains.forEach(checkbox => {
    currentSelections[checkbox.value] = { usage: 'Public Exposure', lab_type: window.LAB_TYPE };
  });

  // 2. Filter VS Code selector options
  if (vscSelector) {
    Array.from(vscSelector.options).forEach(option => {
      const domain = option.value;
      const originalDomain = domain;

      // Only skip filtering for the currently selected domain in THIS selector
      if (domain === vscSelector.value) {
        option.disabled = false;
        option.textContent = originalDomain;
      } else {
        // Check if used in DB or current modal (for ALL domains including .tomweb.shop)
        const dbUsage = usageMap[domain];
        const currentUsage = currentSelections[domain];

        let usageText = '';
        if (dbUsage && dbUsage.usage !== 'VS Code Web') {
          usageText = ` (Used: ${dbUsage.usage} in ${dbUsage.lab_type} lab)`;
        } else if (currentUsage && currentUsage.usage !== 'VS Code Web') {
          usageText = ` (Used: ${currentUsage.usage})`;
        }

        option.disabled = (usageText !== '');
        option.textContent = originalDomain + usageText;
      }
    });
  }

  // 3. Filter MinIO Console selector options
  if (minioConsoleSelector) {
    Array.from(minioConsoleSelector.options).forEach(option => {
      const domain = option.value;
      const originalDomain = domain;

      if (domain === minioConsoleSelector.value) {
        option.disabled = false;
        option.textContent = originalDomain;
      } else {
        const dbUsage = usageMap[domain];
        const currentUsage = currentSelections[domain];

        let usageText = '';
        if (dbUsage && dbUsage.usage !== 'MinIO Console') {
          usageText = ` (Used: ${dbUsage.usage} in ${dbUsage.lab_type} lab)`;
        } else if (currentUsage && currentUsage.usage !== 'MinIO Console') {
          usageText = ` (Used: ${currentUsage.usage})`;
        }

        option.disabled = (usageText !== '');
        option.textContent = originalDomain + usageText;
      }
    });
  }

  // 4. Filter MinIO API selector options
  if (minioApiSelector) {
    Array.from(minioApiSelector.options).forEach(option => {
      const domain = option.value;
      const originalDomain = domain;

      if (domain === minioApiSelector.value) {
        option.disabled = false;
        option.textContent = originalDomain;
      } else {
        const dbUsage = usageMap[domain];
        const currentUsage = currentSelections[domain];

        let usageText = '';
        if (dbUsage && dbUsage.usage !== 'S3 API') {
          usageText = ` (Used: ${dbUsage.usage} in ${dbUsage.lab_type} lab)`;
        } else if (currentUsage && currentUsage.usage !== 'S3 API') {
          usageText = ` (Used: ${currentUsage.usage})`;
        }

        option.disabled = (usageText !== '');
        option.textContent = originalDomain + usageText;
      }
    });
  }

  // 5. Filter n8n selector options
  if (n8nSelector) {
    Array.from(n8nSelector.options).forEach(option => {
      const domain = option.value;
      const originalDomain = domain;

      if (domain === n8nSelector.value) {
        option.disabled = false;
        option.textContent = originalDomain;
      } else {
        const dbUsage = usageMap[domain];
        const currentUsage = currentSelections[domain];

        let usageText = '';
        if (dbUsage && dbUsage.usage !== 'n8n Interface') {
          usageText = ` (Used: ${dbUsage.usage} in ${dbUsage.lab_type} lab)`;
        } else if (currentUsage && currentUsage.usage !== 'n8n Interface') {
          usageText = ` (Used: ${currentUsage.usage})`;
        }

        option.disabled = (usageText !== '');
        option.textContent = originalDomain + usageText;
      }
    });
  }

  // 6. Filter public exposure domain checkboxes
  const allDomainItems = document.querySelectorAll(".domain-item");
  allDomainItems.forEach(item => {
    const checkbox = item.querySelector(".domain-selector");
    if (!checkbox) return;

    const domain = checkbox.value;
    const isCurrentlyChecked = checkbox.checked;
    const label = item.querySelector(".form-check-label");

    // Determine where the domain is used (check DB first, then current modal)
    const dbUsage = usageMap[domain];
    const currentUsage = currentSelections[domain];

    let usageLabel = '';
    let labInfo = '';
    let isUsedInSelector = false;

    if (dbUsage) {
      usageLabel = `Used: ${dbUsage.usage}`;
      labInfo = dbUsage.lab_type ? ` (${dbUsage.lab_type} lab)` : '';
      isUsedInSelector = true;
    } else if (currentUsage) {
      usageLabel = `Used: ${currentUsage.usage}`;
      isUsedInSelector = true;
    }

    if (isUsedInSelector && !isCurrentlyChecked) {
      // Domain is used elsewhere - show it but disabled with usage label
      item.style.display = '';
      item.style.opacity = '0.5';
      checkbox.disabled = true;

      // Add or update usage badge
      let usageBadge = label.querySelector('.usage-badge');
      if (!usageBadge) {
        usageBadge = document.createElement('span');
        usageBadge.className = 'usage-badge badge bg-info bg-opacity-25 text-info ms-2';
        usageBadge.style.fontSize = '9px';
        usageBadge.style.fontWeight = 'normal';
        label.appendChild(usageBadge);
      }
      usageBadge.textContent = usageLabel + labInfo;
    } else {
      // Domain is available - remove opacity and enable
      item.style.display = '';
      item.style.opacity = '1';
      checkbox.disabled = false;

      // Remove usage badge if it exists
      const existingBadge = label.querySelector('.usage-badge');
      if (existingBadge) {
        existingBadge.remove();
      }
    }
  });
}
/**
 * Professional Launcher
 * Opens VS Code for Essentials, S3 Console for MinIO
 */
function launchService(btn, type) {
  // Start loading
  Dashboard.toggleLoading(btn, true);

  setTimeout(() => {
    if (type === "minio") {
      const minioModal = new coreui.Modal(document.getElementById("minioModal"));
      minioModal.show();
    } else if (type === "n8n") {
      let url = "";
      if (window.LAB_CONFIG && window.LAB_CONFIG.fields) {
        const urlField = window.LAB_CONFIG.fields.find(f => f.label === 'Public URL');
        if (urlField) url = urlField.value;
      }

      if (url) {
        window.open(url, '_blank');
      } else {
        alert("n8n URL not found. Please redeploy.");
      }
    } else {
      const vscModal = new coreui.Modal(document.getElementById("vscModal"));
      vscModal.show();
    }

    // Stop loading after action is triggered so it doesn't spin forever
    Dashboard.toggleLoading(btn, false);
  }, 100); // Small delay to show interaction
}
/* ============================================================================
 * UTILITIES: Clipboard Handling
 * ============================================================================ */
document.addEventListener('DOMContentLoaded', () => {
  // Global handler for .clipboard buttons
  document.body.addEventListener('click', async (e) => {
    const btn = e.target.closest('.clipboard');
    if (!btn) return;

    e.preventDefault();
    const text = btn.getAttribute('data-clipboard-text');
    if (!text) return;

    try {
      // Robust Copy Handler with Fallback
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
      } else {
        // Fallback for non-secure contexts
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-9999px";
        textArea.style.top = "0";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
      }

      // 1. Visual Feedback on Button
      const originalInner = btn.innerHTML;
      btn.innerHTML = '<i class="bx bx-check text-success"></i>';
      btn.classList.add('active');

      setTimeout(() => {
        btn.innerHTML = originalInner;
        btn.classList.remove('active');
      }, 2000);

      // 2. Premium Toast Notification
      const toastEl = document.getElementById('copyToast');
      if (toastEl) {
        const toast = coreui.Toast.getOrCreateInstance(toastEl);
        const titleEl = document.getElementById('toast-title');
        const msgEl = document.getElementById('toast-message');

        // Extract label from tooltip or use generic
        let label = btn.getAttribute('title') || btn.getAttribute('data-coreui-title') || 'Information';
        label = label.replace('Copy ', ''); // Clean up title

        if (titleEl) titleEl.innerText = 'Copied!';
        if (msgEl) msgEl.innerText = `${label} has been copied into your clipboard`;

        toast.show();
      }

    } catch (err) {
      console.error('Failed to copy: ', err);
    }
  });
});
//  * EVENT LISTENERS
//  * ========================================================================== */

/**
 * Initialize dashboard when DOM is ready
 */
document.addEventListener("DOMContentLoaded", () => {
  Dashboard.init();

  // Close dropdown when clicking outside
  document.addEventListener("click", function (event) {
    const dropdown = document.getElementById("domain_dropdown");
    const display = document.getElementById("selected_domains_display");

    if (dropdown && display && domainDropdownOpen) {
      if (!dropdown.contains(event.target) && !display.contains(event.target)) {
        domainDropdownOpen = false;
        dropdown.style.display = "none";
      }
    }
  });
});

// --- Challenge Labs Search & Filter Logic ---
function initChallengeSearch() {
    const searchContainer = document.getElementById('expandableSearchContainer');
    const searchInput = document.getElementById('challengeSearchInput');
    
    // Only execute on pages that have the challenge search UI
    if (!searchContainer || !searchInput) return;

    const searchBarUI = document.getElementById('searchBarUI');
    const searchLabel = document.getElementById('searchLabel');
    const filterBtn = document.getElementById('filterToggleBtn');
    const closeBtn = document.getElementById('searchCloseBtn');
    const gridContainer = document.getElementById('challengesGrid');
    
    // Parse saved filters from PHP session object on load
    const savedFilters = window.savedChallengeFilters || {};
    if(savedFilters['q']) {
        searchInput.value = savedFilters['q'];
    }
    const setChecks = (paramName, ids) => {
        if(savedFilters[paramName]) {
            const valStr = String(savedFilters[paramName]);
            const vals = valStr.split(',');
            vals.forEach(val => {
                const map = ids[val];
                if(map && document.getElementById(map)) document.getElementById(map).checked = true;
            });
        }
    };
    setChecks('plan', { 'premium': 'filterPremium', 'free': 'filterFree' });
    setChecks('filter', { 'team': 'filterTeam', 'event': 'filterEvent', 'solo': 'filterSolo', 'retired': 'filterRetired' });
    setChecks('sort', { 'new': 'sortNew', 'partial': 'sortPartial', 'completed': 'sortCompleted' });
    setChecks('level', { 'easy': 'levelEasy', 'medium': 'levelMedium', 'hard': 'levelHard' });

    // Check if there's any active filter or search text
    const hasSearchQuery = savedFilters['q'] && savedFilters['q'].trim() !== '';
    const hasAnyFilter = Object.keys(savedFilters).some(k => ['q','plan','filter','sort','level'].includes(k));
    
    // Always show floating close button if any filter/search is active
    if (hasAnyFilter && closeBtn) {
        closeBtn.classList.remove('d-none');
        closeBtn.classList.add('d-flex');
    }

    // Expand search if there's a text query
    if (hasSearchQuery && searchContainer) {
        searchContainer.classList.add('expanded');
        searchLabel.style.display = 'none';
        searchInput.style.display = 'block';
        filterBtn.classList.remove('d-none');
        filterBtn.classList.add('d-flex');
        searchBarUI.style.cursor = 'text';
    }

    if(searchBarUI) {
        searchBarUI.addEventListener('click', function(e) {
            if (e.target.closest('#filterToggleBtn') || e.target.closest('#searchCloseBtn') || e.target.closest('.dropdown-menu')) {
                return;
            }
            if (!searchContainer.classList.contains('expanded')) {
                searchContainer.classList.add('expanded');
                searchLabel.style.display = 'none';
                searchInput.style.display = 'block';
                filterBtn.classList.remove('d-none');
                filterBtn.classList.add('d-flex');
                searchBarUI.style.cursor = 'text';
                searchInput.focus();
            } else {
                // If it is already expanded, clicking anywhere on the bar should focus the input
                searchInput.focus();
            }
        });
    }

    // Collapse ONLY if input is empty. Close badge stays if filters are active.
    document.addEventListener('click', function(e) {
        if (!searchContainer) return;
        if (!searchContainer.contains(e.target) && searchInput.value.trim() === '') {
            searchContainer.classList.remove('expanded');
            searchLabel.style.display = 'block';
            searchInput.style.display = 'none';
            filterBtn.classList.add('d-none');
            filterBtn.classList.remove('d-flex');
            searchBarUI.style.cursor = 'pointer';
        }
    });

    // Close Button logic: clears everything
    if(closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            searchInput.value = '';
            document.querySelectorAll('.custom-search-dropdown .form-check-input').forEach(cb => cb.checked = false);
            
            searchContainer.classList.remove('expanded');
            searchLabel.style.display = 'block';
            searchInput.style.display = 'none';
            filterBtn.classList.add('d-none');
            filterBtn.classList.remove('d-flex');
            closeBtn.classList.add('d-none');
            closeBtn.classList.remove('d-flex');
            searchBarUI.style.cursor = 'pointer';
            
            triggerAjaxFetch();
        });
    }

    // Fetch update logic
    let debounceTimer;
    const triggerAjaxFetch = () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const currentParams = new URLSearchParams(window.location.search);
            
            // Collect new params
            const q = searchInput.value.trim();
            const getChecked = (ids) => Object.entries(ids).filter(([val, id]) => document.getElementById(id).checked).map(([val, id]) => val);
            
            const plans = getChecked({'premium': 'filterPremium', 'free': 'filterFree'});
            const filters = getChecked({'team': 'filterTeam', 'event': 'filterEvent', 'solo': 'filterSolo', 'retired': 'filterRetired'});
            const sorts = getChecked({'new': 'sortNew', 'partial': 'sortPartial', 'completed': 'sortCompleted'});
            const levels = getChecked({'easy': 'levelEasy', 'medium': 'levelMedium', 'hard': 'levelHard'});
            
            if(q) currentParams.set('q', q); else currentParams.delete('q');
            if(plans.length) currentParams.set('plan', plans.join(',')); else currentParams.delete('plan');
            if(filters.length) currentParams.set('filter', filters.join(',')); else currentParams.delete('filter');
            if(sorts.length) currentParams.set('sort', sorts.join(',')); else currentParams.delete('sort');
            if(levels.length) currentParams.set('level', levels.join(',')); else currentParams.delete('level');
            
            const newUrl = window.location.pathname + '?';
            // URL history.pushState is deliberately omitted so the URL in the address bar never changes.

            // Dynamically show/hide close button based on new params
            if (currentParams.toString() !== '' && closeBtn) {
                closeBtn.classList.remove('d-none');
                closeBtn.classList.add('d-flex');
            } else if (closeBtn) {
                closeBtn.classList.add('d-none');
                closeBtn.classList.remove('d-flex');
            }

            // Fetch URL with ajax flag to get only partial HTML
            const fetchUrl = newUrl + (currentParams.toString() ? currentParams.toString() + '&ajax=1' : 'ajax=1');

            // Show loader
            if(gridContainer) {
                gridContainer.style.opacity = '0.4';
                gridContainer.style.pointerEvents = 'none';
            }

            fetch(fetchUrl)
                .then(res => res.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newGrid = doc.getElementById('challengesGrid');
                    if(gridContainer && newGrid) {
                        gridContainer.innerHTML = newGrid.innerHTML;
                        gridContainer.style.opacity = '1';
                        gridContainer.style.pointerEvents = 'auto';
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    if(gridContainer) {
                        gridContainer.style.opacity = '1';
                        gridContainer.style.pointerEvents = 'auto';
                    }
                });
        }, 400); // 400ms debounce
    };

    // Attach listeners
    if (searchInput) {
        searchInput.addEventListener('input', triggerAjaxFetch);
    }
    const checkboxes = document.querySelectorAll('.custom-search-dropdown .form-check-input');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', triggerAjaxFetch);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChallengeSearch);
} else {
    initChallengeSearch();
}
