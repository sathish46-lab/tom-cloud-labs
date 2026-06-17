const { WebSocketServer } = require('ws');
const si = require('systeminformation');

// Initialize Native WebSocket Server on port 8085
const wss = new WebSocketServer({ port: 8085 });

// Store connected clients
const clients = new Set();

wss.on('connection', (ws) => {
    clients.add(ws);
    
    ws.on('close', () => {
        clients.delete(ws);
    });
    
    ws.on('error', (err) => {
        console.error('WebSocket Error:', err);
    });
});

console.log('[*] Native WebSocket Stats Daemon running on ws://127.0.0.1:8085');

// Sample interval: 1.5 seconds (1500ms)
const SAMPLE_INTERVAL = 1500;

// Gather and broadcast stats
async function broadcastStats() {
    if (clients.size === 0) return; // Don't burn CPU if no UI is connected

    try {
        const [cpu, mem] = await Promise.all([
            si.currentLoad(),
            si.mem()
        ]);

        // Format to match exactly what UI expects (array for cpu cores, etc.)
        const payload = {
            cpu: [cpu.currentLoad, ...cpu.cpus.map(c => c.load)],
            mem: {
                total: mem.total,
                used: mem.active,
                available: mem.available
            },
            swap: {
                total: mem.swaptotal,
                used: mem.swapused,
                free: mem.swapfree,
                percent: (mem.swapused / mem.swaptotal) * 100 || 0
            },
            loadavg: require('os').loadavg() // Returns [1min, 5min, 15min]
        };

        // Broadcast to all connected clients
        const dataStr = JSON.stringify(payload);
        for (const client of clients) {
            if (client.readyState === 1) { // 1 = OPEN
                client.send(dataStr);
            }
        }
    } catch (err) {
        console.error('Error gathering stats:', err);
    }
}

// Start the sampling loop
setInterval(broadcastStats, SAMPLE_INTERVAL);
