import pika
import json
import subprocess
import os
import re
import sys
import time
import threading
import queue
import pymongo
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone

# Configuration
AMQP_HOST = os.environ.get('RABBITMQ_HOST', '127.0.0.1')
AMQP_PORT = int(os.environ.get('RABBITMQ_PORT', '5672'))
AMQP_USER = os.environ.get('RABBITMQ_USER', 'admin')
AMQP_PASS = os.environ.get('RABBITMQ_PASS', '')
QUEUE_NAME = 'labs_jobs'
DLQ_NAME = 'labs_jobs_dlq'
LOG_FILE = '/var/log/labsctl/labsctl.log'
RUNS_DIR = '/var/log/labsctl/runs'
RUN_FILE_TTL = 7 * 86400  # keep per-run log files for gap-fill history for 7 days
MAX_WORKERS = int(os.environ.get('MAX_WORKERS', '5'))

def _file_log(msg):
    try:
        os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
        ts = datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%S.%f')[:-3] + 'Z'
        with open(LOG_FILE, 'a') as f:
            f.write(f"[{ts}] {msg}\n")
    except Exception as e:
        print(f" [!] _file_log write failed: {e}")

def _cleanup_old_run_files():
    """Delete per-run log files older than RUN_FILE_TTL (startup housekeeping)."""
    try:
        os.makedirs(RUNS_DIR, exist_ok=True)
        cutoff = time.time() - RUN_FILE_TTL
        for name in os.listdir(RUNS_DIR):
            if not name.endswith('.log'):
                continue
            path = os.path.join(RUNS_DIR, name)
            if os.path.getmtime(path) < cutoff:
                os.remove(path)
    except Exception as e:
        print(f" [!] run-file cleanup failed: {e}")

def _publish_to_dlq(job_data, reason="unknown"):
    """Publish failed job to Dead Letter Queue for later inspection."""
    try:
        conn = _get_amqp_connection()
        ch = conn.channel()
        ch.queue_declare(queue=DLQ_NAME, durable=True)
        
        dlq_message = json.dumps({
            'original_job': job_data,
            'failure_reason': reason,
            'failed_at': time.strftime('%Y-%m-%d %H:%M:%S'),
        })
        
        msg = pika.BasicMessage(body=dlq_message.encode(), delivery_mode=2)  # Persistent
        ch.basic_publish(exchange='', routing_key=DLQ_NAME, body=msg.body)
        conn.close()
        _file_log(f"[DLQ] Published failed job to {DLQ_NAME}: {reason}")
    except Exception as e:
        _file_log(f"[DLQ ERROR] Failed to publish to DLQ: {e}")

def _get_mongo_client(db_name='tom_labs_instances_db'):
    mongo_user = os.environ.get('MONGO_USER', '')
    mongo_pass = os.environ.get('MONGO_PASS', '')
    mongo_host = os.environ.get('MONGO_HOST', '127.0.0.1')
    mongo_port = os.environ.get('MONGO_PORT', '27018')

    env_paths = ['/var/www/env.json', '/host_www/www/env.json', '../../env.json']
    for ep in env_paths:
        try:
            with open(ep) as f:
                env_cfg = json.load(f)
            db_file = env_cfg.get('database_file', '')
            if db_file and '://' in db_file:
                import re
                m = re.match(r'mongodb://([^:]+):([^@]+)@([^:/]+):(\d+)/', db_file)
                if m:
                    mongo_user = m.group(1)
                    mongo_pass = m.group(2)
                    mongo_host = m.group(3)
                    mongo_port = m.group(4)
            elif not mongo_user:
                mongo_user = env_cfg.get('mongo_user', '')
                mongo_pass = env_cfg.get('mongo_pass', '')
            if mongo_user and mongo_pass:
                break
        except (FileNotFoundError, KeyError):
            continue

    if not mongo_user or not mongo_pass:
        return None
    uri = f"mongodb://{mongo_user}:{mongo_pass}@{mongo_host}:{mongo_port}/?authSource=admin"
    return pymongo.MongoClient(uri, serverSelectionTimeoutMS=3000)[db_name]

def _get_amqp_connection():
    creds = pika.PlainCredentials(AMQP_USER, AMQP_PASS)
    params = pika.ConnectionParameters(host=AMQP_HOST, port=AMQP_PORT, credentials=creds)
    return pika.BlockingConnection(params)

def reap_expired_challenges():
    print(" [Reaper] Started background thread to clean up expired challenges.")
    while True:
        try:
            db = _get_mongo_client('tom_labs_db')
            if not db:
                time.sleep(15)
                continue

            now_time = time.time()
            for inst in db.challenge_instances.find({"status": {"$in": ["running", "completed"]}}):
                hash_id = inst['instance_hash']
                user = inst['username']
                created_at = inst.get('created_at', now_time)
                expires_at = inst.get('expires_at') or created_at + (inst.get('duration', 15) * 60)
                if now_time >= expires_at:
                    subprocess.run([
                        'sudo', 'labsctl',
                        'challenge', 'stop', f'--user={user}', f'--hash={hash_id}'
                    ])
                    try:
                        db.challenge_instances.update_one(
                            {"instance_hash": hash_id}, {"$set": {"mission_started": False}}
                        )
                    except Exception as e:
                        print(f" [!] Failed to reset challenge state for {hash_id}: {e}")
        except Exception as e:
            print(f" [Reaper Error] {e}")
        time.sleep(15)

def log_to_user(channel, exchange_name, routing_key, message, seq=None, run=None):
    try:
        payload = {'log': message}
        if seq is not None:
            payload['seq'] = seq
        if run:
            payload['run'] = run
        body = json.dumps(payload)
        channel.basic_publish(exchange=exchange_name, routing_key=routing_key, body=body)
    except Exception as e:
        print(f"Failed to log to user: {e}")

def progress_to_user(channel, exchange_name, routing_key, pct, label):
    """Send structured progress update to the browser via AMQP."""
    try:
        payload = json.dumps({'progress': pct, 'label': label})
        channel.basic_publish(exchange=exchange_name, routing_key=routing_key, body=payload)
    except Exception as e:
        print(f"Failed to send progress: {e}")

# Deploy progress steps: (regex, percentage, label)
# These match labsctl output lines and report actual progress to the browser.
DEPLOY_PROGRESS_STEPS = [
    (r'Deployment initiated',                  5,  'Initializing'),
    (r'Fetching lab metadata',                 8,  'Loading metadata'),
    (r'Starting deployment for user',          10, 'Starting'),
    (r'Instance ID:',                          12, 'Preparing'),
    (r'Reusing existing lab IP|Assigned Docker IP', 15, 'Assigning IP'),
    (r'Checking for conflicting containers',   18, 'Checking containers'),
    (r'No existing container|Removing existing container', 20, 'Cleaning up'),
    (r'Storage preserved',                     25, 'Preserving storage'),
    (r'Clearing stale VPN',                    28, 'Clearing VPN'),
    (r'Removing stale WireGuard',              30, 'Removing old peer'),
    (r'Peer removed',                          32, 'Peer removed'),
    (r'Reusing existing keys|Generating new',  35, 'Configuring keys'),
    (r'Peer re-registered',                    38, 'Peer registered'),
    (r'Provisioning',                          40, 'Provisioning'),
    (r'Waiting for container',                 45, 'Starting container'),
    (r'Configuring network routing',           50, 'Configuring network'),
    (r'Routing and firewall configured',       55, 'Firewall ready'),
    (r'Optimizing Apache',                     58, 'Configuring Apache'),
    (r'Configuring user environment',          60, 'Setting up user'),
    (r'Syncing ssh',                           62, 'Syncing SSH'),
    (r'Starting user configuration',           65, 'Creating user'),
    (r'User .* created',                       68, 'User created'),
    (r'System password set',                   70, 'Password set'),
    (r'SSH configured',                        72, 'SSH ready'),
    (r'Bash environment',                      74, 'Shell ready'),
    (r'Configuring WireGuard tunnel',          76, 'Setting up VPN'),
    (r'WireGuard configured',                  80, 'VPN ready'),
    (r'Configuring persistent storage',        82, 'Linking storage'),
    (r'Storage links configured',              85, 'Storage ready'),
    (r'Setting up Code-Server',                88, 'Starting Code-Server'),
    (r'Code-server started',                   90, 'Code-Server ready'),
    (r'Applying firewall rules',               92, 'Applying firewall'),
    (r'Firewall rules applied',                94, 'Firewall ready'),
    (r'Finalizing Traefik',                    96, 'Configuring proxy'),
    (r'Traefik config written',                98, 'Proxy configured'),
    (r'Deployment Complete|Deploy complete|Access URL:', 100, 'Complete'),
]

def _save_deploy_logs(instance_hash, logs, error_msg, is_build=False, action='deploy', duration=None, exit_code=None, user=None, run_id=None):
    try:
        db = _get_mongo_client('tom_labs_instances_db')
        if not db:
            return

        now = time.time()
        log_key = 'build_log' if is_build else 'deploy_log'
        log_doc = {
            'logs': logs[-200:],
            'status': 'error' if error_msg else 'success',
            'message': error_msg or '',
            'run_id': run_id or '',
            'created_at': now,
            'expire_at': now + 300
        }

        status_val = 'error' if error_msg else ('running' if action == 'deploy' else None)
        set_fields = {f'deploy.{log_key}': log_doc, 'deploy.last_error': error_msg or ''}
        if status_val is not None:
            set_fields['status'] = status_val
            set_fields['deploy.status'] = status_val

        db.instances.update_one({'instance_hash': instance_hash}, {'$set': set_fields})

        # Push to deploy_history array in machine_labs
        labs_db = _get_mongo_client('tom_labs_db')
        if labs_db:
            flat_fields = {
                log_key: log_doc,
                'last_error': error_msg or '',
            }
            if status_val is not None:
                flat_fields['status'] = status_val

            history_entry = {
                'status': 'success' if not error_msg else 'failed',
                'action': action,
                'user': user or '',
                'duration': duration or '',
                'exit_code': exit_code if exit_code is not None else '',
                'run_id': run_id or '',
                'timestamp': now
            }
            labs_db.machine_labs.update_one(
                {'instance_hash': instance_hash},
                {
                    '$set': flat_fields,
                    '$push': {
                        'deploy_history': {
                            '$each': [history_entry],
                            '$position': 0
                        }
                    }
                }
            )
    except Exception as e:
        print(f" [_save_deploy_logs] FAILED: {e}")

def _run_job(job_data):
    """Execute a single job — thread owns its own AMQP connection"""
    instance_hash = None
    routing_key = None
    run_id = None
    ch = None
    all_logs = []
    error_lines = []
    last_progress = 0
    state = {'seq': -1, 'path': None, 'run': None}
    thread_name = threading.current_thread().name

    def emit(msg, record=False):
        """Single funnel for user-visible lines: seq + per-run file + AMQP (+ Mongo buffer)."""
        state['seq'] += 1
        try:
            if state['path']:
                with open(state['path'], 'a') as fh:
                    fh.write(msg + '\n')
        except Exception:
            pass
        if ch is not None and routing_key:
            log_to_user(ch, "amq.topic", routing_key, msg, seq=state['seq'], run=state['run'])
        if record:
            all_logs.append(msg)

    try:
        job = json.loads(job_data)
        instance_hash = job.get('hash')
        routing_key = f"logs.{instance_hash}"
        user = job.get('user')
        action = job.get('action', 'deploy')
        lab = job.get('lab', 'essentials')
        run_id = job.get('run_id')

        # Per-run log file — the gap-fill source for browser WS reconnects.
        # Filename is stable: explicit run_id, else instance_hash (legacy jobs).
        os.makedirs(RUNS_DIR, exist_ok=True)
        run_key = str(run_id or instance_hash or f'job-{int(time.time())}')
        run_key = re.sub(r'[^A-Za-z0-9._-]', '_', run_key)[:80]
        state['run'] = run_key
        state['path'] = os.path.join(RUNS_DIR, f'{run_key}.log')

        print(f" [x] [{thread_name}] Received: {action} {instance_hash} run={run_id or '-'}")

        conn = _get_amqp_connection()
        ch = conn.channel()

        emit(f"[Queue] Job accepted. Worker [{thread_name}] assigned.")
        _file_log(f"=== {time.strftime('%Y-%m-%d %H:%M:%S')} | {action} | {instance_hash} | user={user} | worker={thread_name} | run={run_id or '-'} ===")

        # Build command
        if job.get('is_challenge'):
            cmd = ['sudo', 'labsctl',
                   'challenge', action, f"--user={user}", f"--hash={instance_hash}",
                   f"--challenge={job.get('challenge_id')}"]
        elif lab == 'instance':
            cmd = ['sudo', 'labsctl',
                   'instance', action, f"--user={user}", f"--hash={instance_hash}"]
        else:
            cmd = ['sudo', 'labsctl',
                   'lab', action, f"--user={user}", f"--hash={instance_hash}"]
            for flag, key in [("--vsc-domain", "vsc_domain"),
                              ("--minio-console-domain", "minio_console_domain"),
                              ("--minio-api-domain", "minio_api_domain"),
                              ("--n8n-domain", "n8n_domain")]:
                if key in job:
                    cmd.append(f"{flag}={job[key]}")

        # TEST MODE
        if job.get('test_mode'):
            for step in range(3):
                emit(f"[TEST] Simulation step {step+1}/3...")
                time.sleep(1)
            emit("[TEST] Simulation completed.")
            conn.close()
            return

        # REAL EXECUTION
        start_time = time.time()
        _file_log(f"  Executing: {' '.join(cmd)}")

        process = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)

        for line in process.stdout:
            clean_line = line.strip()
            if clean_line:
                emit(clean_line, record=True)
                _file_log(f"  {clean_line}")
                # Detect progress from log lines and send structured progress to browser
                for pattern, pct, label in DEPLOY_PROGRESS_STEPS:
                    if re.search(pattern, clean_line, re.IGNORECASE) and pct > last_progress:
                        progress_to_user(ch, "amq.topic", routing_key, pct, label)
                        last_progress = pct
                        break
                if clean_line.startswith('[!]') or 'error' in clean_line.lower() or 'failed' in clean_line.lower():
                    error_lines.append(clean_line)

        process.wait()
        duration_secs = round(time.time() - start_time, 1)
        duration_str = f"{duration_secs}s"
        exit_code = process.returncode

        is_build = (action == 'build')
        if process.returncode == 0:
            if action == 'stop':
                emit("[*] Instance stopped. Page will reload in few seconds.")
            else:
                emit("[*] Your lab is up and ready to experiment... Page will reload in few seconds.")
            emit("[*] reload")
            _file_log(f"[OK] {action} completed for {instance_hash}")
            _save_deploy_logs(instance_hash, all_logs, None, is_build, action, duration_str, exit_code, user, run_id)
        else:
            error_msg = error_lines[-1] if error_lines else f"Exit code {process.returncode}"
            emit(f"[!] {error_msg}")
            emit("[*] reload")
            _file_log(f"[FAIL] {action} failed for {instance_hash}: {error_msg}")
            _save_deploy_logs(instance_hash, all_logs, error_msg, is_build, action, duration_str, exit_code, user, run_id)

        conn.close()

    except Exception as e:
        print(f" [!] [{thread_name}] Error: {e}")
        _file_log(f"[SYSERR] {e}")
        _publish_to_dlq(job_data, reason=str(e))
        if routing_key:
            try:
                err_conn = _get_amqp_connection()
                err_ch = err_conn.channel()
                ch = err_ch
                emit(f"[!] System Error: {str(e)}")
                emit("[*] reload")
                err_conn.close()
            except Exception as err_notify_err:
                print(f" [!] Failed to send error notification to user: {err_notify_err}")
        if instance_hash:
            _save_deploy_logs(instance_hash, [f"[!] System Error: {str(e)}"], str(e), run_id=run_id)

    finally:
        print(f" [x] [{thread_name}] Job Done")


def main():
    reaper = threading.Thread(target=reap_expired_challenges, daemon=True)
    reaper.start()

    _cleanup_old_run_files()
    _file_log(f"=== Worker started | MAX_WORKERS={MAX_WORKERS} | PID={os.getpid()} ===")

    executor = ThreadPoolExecutor(max_workers=MAX_WORKERS)
    print(f" [*] Worker pool started (MAX_WORKERS={MAX_WORKERS})")

    while True:
        try:
            connection = _get_amqp_connection()
            channel = connection.channel()
            channel.queue_declare(
                queue=DLQ_NAME,
                durable=True,
                arguments={
                    'x-message-ttl': 604800000,  # 7 days retention
                }
            )
            channel.queue_declare(
                queue=QUEUE_NAME,
                durable=True,
                arguments={
                    'x-dead-letter-exchange': '',
                    'x-dead-letter-routing-key': DLQ_NAME,
                }
            )
            channel.basic_qos(prefetch_count=MAX_WORKERS)

            print(f" [*] Connected to '{QUEUE_NAME}' — polling...")

            while connection.is_open:
                try:
                    method_frame, properties, body = channel.basic_get(queue=QUEUE_NAME, auto_ack=False)

                    if method_frame is None:
                        time.sleep(0.2)
                        continue

                    body_str = body.decode('utf-8') if isinstance(body, bytes) else body

                    # ACK immediately on main channel to prevent prefetch stall
                    # (delivery_tag can't be used across connections in threads)
                    channel.basic_ack(delivery_tag=method_frame.delivery_tag)

                    # Submit to thread pool — threads never touch main channel
                    executor.submit(_run_job, body_str)

                except Exception as e:
                    print(f" Poll error: {e}")
                    break

        except pika.exceptions.AMQPConnectionError:
            print(" Connection lost, retrying in 5s...")
            time.sleep(5)
        except Exception as e:
            print(f" Unexpected error: {e}")
            time.sleep(5)

if __name__ == '__main__':
    main()
