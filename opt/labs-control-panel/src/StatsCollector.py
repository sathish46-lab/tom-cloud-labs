import subprocess
import json
import re
import time
import os
from collections import deque

# Store up to 20 samples (100 seconds of history) to match frontend charts
HISTORY = {}
LIMIT = 20 

def collect_all_stats():
    cmd = ["docker", "stats", "--no-stream", "--format", "{{json .}}"]
    while True:
        try:
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=10)
            all_stats = {}
            for line in result.stdout.splitlines():
                if not line.strip(): continue
                data = json.loads(line)
                name = data['Name']
                # 1. Parse Numeric Values
                cpu = float(data['CPUPerc'].replace('%', ''))
                pids = int(data['PIDs'])
                
                mem_raw = data['MemUsage'].split(' / ')[0]
                mem_val = float(re.sub(r'[^\d\.]', '', mem_raw) or 0)
                mem = mem_val * 1024 if 'G' in mem_raw else mem_val
                
                net_raw = data['NetIO'].split(' / ')[0]
                net = float(re.sub(r'[^\d\.]', '', net_raw) or 0)
                if 'M' in net_raw: net *= 1000
                elif 'G' in net_raw: net *= 1000000
                
                block_raw = data['BlockIO'].split(' / ')[0]
                block = float(re.sub(r'[^\d\.]', '', block_raw) or 0)
                if 'M' in block_raw: block *= 1000
                elif 'G' in block_raw: block *= 1000000

                # 2. Initialize History Deques
                if name not in HISTORY:
                    HISTORY[name] = {k: deque(maxlen=LIMIT) for k in ['cpu', 'mem', 'net', 'block', 'pids', 'l1', 'l5', 'l15']}

                # 3. Calculate Simulated Load Averages
                l1 = round(cpu / 100, 4)
                l5 = round(sum(list(HISTORY[name]['cpu'])[-12:]) / 1200, 4) if HISTORY[name]['cpu'] else l1
                l15 = round(sum(list(HISTORY[name]['cpu'])[-20:]) / 2000, 4) if HISTORY[name]['cpu'] else l1

                # 4. Update History
                HISTORY[name]['cpu'].append(cpu)
                HISTORY[name]['mem'].append(mem)
                HISTORY[name]['net'].append(net)
                HISTORY[name]['block'].append(block)
                HISTORY[name]['pids'].append(pids)
                HISTORY[name]['l1'].append(l1)
                HISTORY[name]['l5'].append(l5)
                HISTORY[name]['l15'].append(l15)

                # 5. Build Final Data Object
                data.update({
                    "cpu_h": list(HISTORY[name]['cpu']),
                    "mem_h": list(HISTORY[name]['mem']),
                    "net_h": list(HISTORY[name]['net']),
                    "block_h": list(HISTORY[name]['block']),
                    "pids_h": list(HISTORY[name]['pids']),
                    "l1_h": list(HISTORY[name]['l1']),
                    "l5_h": list(HISTORY[name]['l5']),
                    "l15_h": list(HISTORY[name]['l15']),
                    "Load1": l1, "Load5": l5, "Load15": l15,
                    "PeakCPU": f"{max(HISTORY[name]['cpu']):.2f}%",
                    "HighMem": f"{max(HISTORY[name]['mem']):.1f} MB",
                    "MaxPID": max(HISTORY[name]['pids'])
                })
                all_stats[name] = data

            # Atomic write to prevent half-read JSON files
            if all_stats:
                with open('/dev/shm/docker_stats.json.tmp', 'w') as f: json.dump(all_stats, f)
                os.rename('/dev/shm/docker_stats.json.tmp', '/dev/shm/docker_stats.json')
        except Exception as e: print(f"Error: {e}")
        time.sleep(5)

if __name__ == "__main__": collect_all_stats()