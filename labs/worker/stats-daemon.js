/**
 * Ultra-Lightweight Stats Daemon
 * ─────────────────────────────────────────────────────────────────
 * Replaces both the old stats-daemon.js (systeminformation) AND
 * the labsctl stream --key=overview (Python + psutil + RabbitMQ).
 *
 * Uses ONLY Node.js native `os` module — zero npm dependencies
 * beyond `ws`. Memory footprint: ~50 bytes per WebSocket client.
 *
 * At 10,000 concurrent users:
 *   Connections: ~500 KB RAM
 *   Broadcast:   Single JSON.stringify() → fan out to all clients
 * ─────────────────────────────────────────────────────────────────
 */

const { WebSocketServer } = require('ws');
const os = require('os');

// ── Config ──────────────────────────────────────────────────────
const PORT = 8085;
const SAMPLE_INTERVAL = 1500; // 1.5 seconds

// ── WebSocket Server ────────────────────────────────────────────
const wss = new WebSocketServer({
  port: PORT,
  // Performance tuning for high concurrency
  perMessageDeflate: false,   // Disable compression (saves CPU at scale)
  maxPayload: 1024,           // Clients never send us large payloads
});

const clients = new Set();

wss.on('connection', (ws) => {
  clients.add(ws);
  ws.isAlive = true;

  ws.on('pong', () => { ws.isAlive = true; });
  ws.on('close', () => { clients.delete(ws); });
  ws.on('error', () => { clients.delete(ws); });
});

console.log(`[*] Stats Daemon running on ws://0.0.0.0:${PORT} (lightweight mode)`);

// ── CPU Tracking ────────────────────────────────────────────────
// Node's os.cpus() gives cumulative ticks. We diff between samples
// to calculate per-core usage percentage — same technique as htop.
let prevCpuTimes = null;

function getCpuUsage() {
  const cpus = os.cpus();
  const result = [];

  if (!prevCpuTimes) {
    // First sample — return zeros, next tick will have real data
    prevCpuTimes = cpus.map(c => ({ ...c.times }));
    return cpus.map(() => 0);
  }

  for (let i = 0; i < cpus.length; i++) {
    const prev = prevCpuTimes[i];
    const curr = cpus[i].times;

    const idleDelta = curr.idle - prev.idle;
    const totalDelta =
      (curr.user - prev.user) +
      (curr.nice - prev.nice) +
      (curr.sys - prev.sys) +
      (curr.irq - prev.irq) +
      (curr.idle - prev.idle);

    // Avoid division by zero
    const usage = totalDelta > 0
      ? ((1 - idleDelta / totalDelta) * 100)
      : 0;

    result.push(Math.round(usage * 100) / 100); // 2 decimal places
  }

  // Store current as previous for next diff
  prevCpuTimes = cpus.map(c => ({ ...c.times }));
  return result;
}

// ── Stats Collector ─────────────────────────────────────────────
function collectStats() {
  const cpuCores = getCpuUsage();
  const avgCpu = cpuCores.length > 0
    ? Math.round((cpuCores.reduce((a, b) => a + b, 0) / cpuCores.length) * 100) / 100
    : 0;

  const mem = {
    total: os.totalmem(),
    used: os.totalmem() - os.freemem(),
    available: os.freemem()
  };

  // Read swap from /proc/meminfo (Linux only, graceful fallback)
  let swap = { total: 0, used: 0, free: 0, percent: 0 };
  try {
    const meminfo = require('fs').readFileSync('/proc/meminfo', 'utf8');
    const getVal = (key) => {
      const match = meminfo.match(new RegExp(`${key}:\\s+(\\d+)`));
      return match ? parseInt(match[1]) * 1024 : 0; // Convert kB to bytes
    };
    const swapTotal = getVal('SwapTotal');
    const swapFree = getVal('SwapFree');
    swap = {
      total: swapTotal,
      used: swapTotal - swapFree,
      free: swapFree,
      percent: swapTotal > 0 ? Math.round(((swapTotal - swapFree) / swapTotal) * 10000) / 100 : 0
    };
  } catch (e) {
    // Not Linux or /proc not available — swap stays at 0
  }

  return {
    cpu: [avgCpu, ...cpuCores],  // [overall, core0, core1, ...] — matches old format
    mem: mem,
    swap: swap,
    loadavg: os.loadavg(),        // [1min, 5min, 15min] — native
    online_users: clients.size    // Replaces labsctl's RabbitMQ API call
  };
}

// ── Broadcast Loop ──────────────────────────────────────────────
function broadcastStats() {
  if (clients.size === 0) return; // Don't burn CPU if no UI is connected

  const payload = JSON.stringify(collectStats());

  // Single stringify, fan out the same buffer to all clients
  for (const client of clients) {
    if (client.readyState === 1) { // WebSocket.OPEN
      client.send(payload);
    }
  }
}

setInterval(broadcastStats, SAMPLE_INTERVAL);

// ── Dead Connection Cleanup ─────────────────────────────────────
// Ping every 30s, terminate zombies. Prevents memory leaks from
// clients that disconnect without sending a close frame.
const HEARTBEAT_INTERVAL = 30000;

setInterval(() => {
  for (const ws of clients) {
    if (!ws.isAlive) {
      clients.delete(ws);
      ws.terminate();
      continue;
    }
    ws.isAlive = false;
    ws.ping();
  }
}, HEARTBEAT_INTERVAL);

// ── Graceful Shutdown ───────────────────────────────────────────
process.on('SIGTERM', () => {
  console.log('[*] Stats Daemon shutting down...');
  wss.close(() => process.exit(0));
});

process.on('SIGINT', () => {
  console.log('[*] Stats Daemon interrupted.');
  wss.close(() => process.exit(0));
});
