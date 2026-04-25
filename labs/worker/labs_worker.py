import pika
import json
import subprocess
import os
import sys
import time

# Configuration
AMQP_HOST = '127.0.0.1'
AMQP_PORT = 5672
AMQP_USER = 'admin'
AMQP_PASS = 'RootTom@46'
QUEUE_NAME = 'labs_jobs'

def get_db_connection():
    # Placeholder if DB access is needed directly in worker
    pass

def log_to_user(channel, exchange_name, routing_key, message):
    """Publish a log message to a specific exchange with routing key"""
    try:
        payload = json.dumps({'log': message})
        channel.basic_publish(exchange=exchange_name, routing_key=routing_key, body=payload)
    except Exception as e:
        print(f"Failed to log to user: {e}")

def execute_job(ch, method, properties, body):
    """Callback function to process a job"""
    try:
        job = json.loads(body)
        print(f" [x] Received Job: {job}")
        
        user = job.get('user')
        action = job.get('action', 'deploy')
        lab = job.get('lab', 'essentials')
        instance_hash = job.get('hash')
        
        # User Log Topic
        routing_key = f"logs.{instance_hash}"

        # 1. Notify User: Job Started
        log_to_user(ch, "amq.topic", routing_key, f"[Queue] Job accepted. Worker assigned.")
        
        # 2. Construct Command
        # Using the same logic as DeployLog.worker.php but in Python
        # cmd = "sudo /usr/bin/python3 /opt/labs-control-panel/labsctl.py <action> <lab>:lab --user=<user> --hash=<hash>"
        
        cmd = [
            'sudo', '/usr/bin/python3', '/opt/labs-control-panel/labsctl.py',
            action, f"{lab}:lab",
            f"--user={user}", f"--hash={instance_hash}"
        ]
        
        # Append extra flags
        if 'minio_console_domain' in job:
            cmd.append(f"--minio-console-domain={job['minio_console_domain']}")
        if 'minio_api_domain' in job:
            cmd.append(f"--minio-api-domain={job['minio_api_domain']}")
        if 'n8n_domain' in job:
            cmd.append(f"--n8n-domain={job['n8n_domain']}")

        # TEST MODE (For stress testing without killing the server)
        if job.get('test_mode'):
            print(f" [TEST] Simulating job for {user} ({instance_hash})")
            log_to_user(ch, "amq.topic", routing_key, f"[TEST] Simulation started for {user}...")
            time.sleep(1)
            log_to_user(ch, "amq.topic", routing_key, f"[TEST] Simulation step 1/3...")
            time.sleep(1)
            log_to_user(ch, "amq.topic", routing_key, f"[TEST] Simulation step 2/3...")
            time.sleep(1)
            log_to_user(ch, "amq.topic", routing_key, f"[TEST] Simulation completed.")
            print(" [x] Job Done (Simulation)")
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return
            
        start_time = time.time()
        print(f" [{start_time}] Executing: {' '.join(cmd)}")
        
        # 3. Execute and Stream Logs
        process = subprocess.Popen(
            cmd, 
            stdout=subprocess.PIPE, 
            stderr=subprocess.STDOUT, 
            universal_newlines=True
        )
        
        # Log streaming loop
        first_line = True
        for line in process.stdout:
            clean_line = line.strip()
            if clean_line:
                if first_line:
                    print(f" [{time.time()}] First output received (Delay: {time.time() - start_time:.4f}s)")
                    first_line = False
                print(f"   > {clean_line}")
                log_to_user(ch, "amq.topic", routing_key, clean_line)
                
        process.wait()
        
        if process.returncode == 0:
            log_to_user(ch, "amq.topic", routing_key, "[✓] Job completed successfully.")
        else:
            log_to_user(ch, "amq.topic", routing_key, "[!] Job failed. Please check parameters.")

    except Exception as e:
        print(f" [!] Error processing job: {e}")
        # Try to notify user of system error
        if 'routing_key' in locals():
            log_to_user(ch, "amq.topic", routing_key, f"[!] System Error: {str(e)}")
            
    finally:
        # Always acknowledge the message so it's removed from queue
        ch.basic_ack(delivery_tag=method.delivery_tag)
        print(" [x] Job Done")

def main():
    while True:
        try:
            credentials = pika.PlainCredentials(AMQP_USER, AMQP_PASS)
            parameters = pika.ConnectionParameters(host=AMQP_HOST, port=AMQP_PORT, credentials=credentials)
            connection = pika.BlockingConnection(parameters)
            channel = connection.channel()

            # Declare the task queue (durable = survives restart)
            channel.queue_declare(queue=QUEUE_NAME, durable=True)
            
            # Fair dispatch: don't give a worker more than 1 message at a time
            channel.basic_qos(prefetch_count=1)
            
            print(f" [*] Waiting for jobs in '{QUEUE_NAME}'. To exit press CTRL+C")
            
            channel.basic_consume(queue=QUEUE_NAME, on_message_callback=execute_job)
            channel.start_consuming()
            
        except pika.exceptions.AMQPConnectionError:
            print("Connection lost, retrying in 5s...")
            time.sleep(5)
        except Exception as e:
            print(f"Unexpected error: {e}")
            time.sleep(5)

if __name__ == '__main__':
    main()
