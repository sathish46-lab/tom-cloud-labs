/**
 * ============================================================================
 * TOM LABS - MESSAGE QUEUE CLIENT (MQ)
 * ============================================================================
 * Handles all WebSocket connections, STOMP messaging, and real-time updates
 *
 * Dependencies: stomp.js
 * Used by: lab-dashboard.js
 *
 * @version 1.0.0
 * @author Sathish - Tom Labs
 * ============================================================================
 */

/**
 * Hardened Socket Factory with Subscription Tracking and Delayed Binding
 */
const TomSocketClient = function () {
  this.client = null;
  this.isConnected = false;
  this.isSubscribed = false;
  this.subRetryCount = 0;

  /**
   * Establish WebSocket connection to RabbitMQ
   * @param {string} exchange - RabbitMQ exchange name
   * @param {function} callback - Message handler function
   * @param {object} ui - UI elements (optional, e.g., status dot)
   */
  this.connect = function (exchange, callback, ui = null) {
    try {
      // Dynamically resolve MQS domain based on the current hostname
      const currentHost = window.location.hostname;
      let mqDomain = "mq.awshosting.in"; // Default fallback
      
      if (currentHost.includes("dev.awshosting.in")) {
        mqDomain = "mq.dev.awshosting.in";
      } else if (currentHost === "localhost" || currentHost === "127.0.0.1") {
        // When running locally on localhost, the Stomp port is usually 15674
        mqDomain = "localhost:15674"; 
      } else if (currentHost.includes("awshosting.in")) {
        mqDomain = "mq.awshosting.in";
      } else {
        // Generic fallback: swap the first subdomain with 'mq'
        const parts = currentHost.split('.');
        if (parts.length > 2) {
          parts[0] = 'mq';
          mqDomain = parts.join('.');
        } else {
          mqDomain = 'mq.' + currentHost;
        }
      }
      
      const wsProtocol = window.location.protocol === "https:" ? "wss" : "ws";
      const wsUrl = `${wsProtocol}://${mqDomain}/ws`;
      
      console.log(`[MQ] Connecting to WebSocket: ${wsUrl}`);
      const ws = new WebSocket(wsUrl);
      this.client = Stomp.over(ws);
      this.client.debug = null; // Set to console.log for debugging

      this.client.connect(
        "admin",
        "RootTom@46",
        () => {
          this.isConnected = true;
          if (ui && ui.dot) ui.dot.style.color = "#a6e3a1"; // Green

          console.log(`[✓] Socket connected to exchange: ${exchange}`);

          // Start the subscription attempt
          this.safeSubscribe(exchange, callback, ui);
        },
        (err) => {
          this.isConnected = false;
          this.isSubscribed = false;
          if (ui && ui.dot) ui.dot.style.color = "#f38ba8"; // Red

          console.warn("Socket connection failed, retrying in 2s...", err);
          setTimeout(() => this.connect(exchange, callback, ui), 2000);
        },
        "/",
      );
    } catch (e) {
      console.error("Socket Error:", e);
    }
  };

  /**
   * Gracefully disconnect the WebSocket
   */
  this.disconnect = function () {
    if (this.client && this.isConnected) {
      this.client.disconnect(() => {
        console.log("Socket gracefully disconnected to save resources.");
      });
      this.isConnected = false;
      this.isSubscribed = false;
    }
  };

  /**
   * Attempts to subscribe to an exchange, with retries if the exchange isn't ready
   * @param {string} exchange - Exchange name
   * @param {function} callback - Message handler
   * @param {object} ui - UI elements
   */
  this.safeSubscribe = function (exchange, callback, ui) {
    // Wait 1 second initially to give the worker time to declare the exchange
    setTimeout(() => {
      if (!this.isConnected) return;

      try {
        // Use /topic/ instead of /exchange/ for better stability and resource management
        // This maps to amq.topic exchange in RabbitMQ
        let destination;
        if (exchange.startsWith('logs.') || exchange.startsWith('ai_stream.')) {
          destination = `/topic/${exchange}`;
        } else {
          destination = `/exchange/${exchange}/#`;
        }

        this.client.subscribe(destination, (m) => {
          if (m.body) {
            try {
              callback(JSON.parse(m.body));
            } catch (e) {
              callback(m.body);
            }
          }
        });
        this.isSubscribed = true;
        this.subRetryCount = 0;
        console.log(`[✓] Subscribed to ${destination}`);
      } catch (e) {
        console.warn(
          `[!] Subscription failed for ${exchange}. Worker might not be ready. Retrying...`,
        );
        if (this.subRetryCount < 10) {
          this.subRetryCount++;
          this.safeSubscribe(exchange, callback, ui);
        }
      }
    }, 1000);
  };

  /**
   * Check if socket is currently connected
   * @returns {boolean}
   */
  this.isActive = function () {
    return this.isConnected && this.isSubscribed;
  };

  /**
   * Reconnect to the exchange
   * @param {string} exchange
   * @param {function} callback
   * @param {object} ui
   */
  this.reconnect = function (exchange, callback, ui = null) {
    if (this.isConnected) {
      console.log("Socket already connected. Skipping reconnection.");
      return;
    }
    this.connect(exchange, callback, ui);
  };
};

/**
 * Global Socket Instances
 * - OverviewSocket: System-wide stats (CPU, RAM, etc.)
 * - LogSocket: Instance-specific deployment logs
 */
const OverviewSocket = new TomSocketClient();
const LogSocket = new TomSocketClient();

/**
 * ============================================================================
 * ACTIVITY TRACKER - Resource Optimization
 * ============================================================================
 * Disconnects sockets when user is idle or tab is hidden
 */
const ActivityTracker = {
  idleTimer: null,
  idleTimeout: 300000, // 5 Minutes (in milliseconds)
  isProcessing: false, // Flag to prevent disconnection during critical operations

  /**
   * Initialize activity tracking
   */
  init: function () {
    // 1. Listen for Tab Switching
    document.addEventListener("visibilitychange", () => {
      if (document.hidden) {
        console.log("Tab hidden: Optimization active.");
        this.stopAllSockets();
      } else {
        console.log("Tab focused: Reconnecting...");
        this.reconnectSockets();
      }
    });

    // 2. Listen for Mouse/Keyboard Activity
    const resetTimer = () => {
      clearTimeout(this.idleTimer);
      this.idleTimer = setTimeout(() => {
        console.warn("User idle: Disconnecting sockets.");
        this.stopAllSockets();
      }, this.idleTimeout);
    };

    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onscroll = resetTimer;
    document.onclick = resetTimer;
  },

  /**
   * Stop all socket connections (called when idle or tab hidden)
   */
  stopAllSockets: function () {
    // PREVENT: If we are deploying or stopping, DO NOT close the socket
    if (this.isProcessing) {
      console.log(
        "[Optimization Bypass] System is busy with logs. Keeping socket alive.",
      );
      return;
    }

    OverviewSocket.disconnect();
    LogSocket.disconnect();

    console.log("[Optimization] All sockets disconnected to save resources.");
  },

  /**
   * Reconnect sockets when user returns
   */
  reconnectSockets: function () {
    // This will be called by the Dashboard to re-init sockets
    if (
      typeof Dashboard !== "undefined" &&
      typeof Dashboard.initSockets === "function"
    ) {
      Dashboard.initSockets();
    }
  },

  /**
   * Set processing flag to prevent socket closure during critical operations
   * @param {boolean} processing
   */
  setProcessing: function (processing) {
    this.isProcessing = processing;
    console.log(
      `[ActivityTracker] Processing mode: ${processing ? "ON" : "OFF"}`,
    );
  },
};

/**
 * ============================================================================
 * MQ UTILITIES - Helper Functions
 * ============================================================================
 */
const MQUtils = {
  /**
   * Update MQ status indicator in UI
   * @param {string} status - 'connected', 'disconnected', 'connecting'
   */
  updateStatusIndicator: function (status) {
    const dot = document.getElementById("mq-status-dot");
    if (!dot) return;

    const colors = {
      connected: "#a6e3a1", // Green
      disconnected: "#f38ba8", // Red
      connecting: "#f9e2af", // Yellow
    };

    dot.style.color = colors[status] || colors.disconnected;
  },

  /**
   * Log MQ events to console (debug mode)
   * @param {string} message
   * @param {string} type - 'info', 'warn', 'error'
   */
  log: function (message, type = "info") {
    const prefix = "[MQ]";
    switch (type) {
      case "warn":
        console.warn(prefix, message);
        break;
      case "error":
        console.error(prefix, message);
        break;
      default:
        console.log(prefix, message);
    }
  },

  /**
   * Parse incoming message (handle both JSON and plain text)
   * @param {*} data
   * @returns {object|string}
   */
  parseMessage: function (data) {
    if (typeof data === "string") {
      try {
        return JSON.parse(data);
      } catch (e) {
        return data;
      }
    }
    return data;
  },
};

/**
 * Export for use in other modules (if using ES6 modules)
 * For legacy script tags, these are already global
 */
if (typeof module !== "undefined" && module.exports) {
  module.exports = {
    TomSocketClient,
    OverviewSocket,
    LogSocket,
    ActivityTracker,
    MQUtils,
  };
}
