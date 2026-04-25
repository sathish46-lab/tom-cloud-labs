import pika
import json
import time

credentials = pika.PlainCredentials('admin', 'RootTom@46')
parameters = pika.ConnectionParameters(host='127.0.0.1', port=5672, credentials=credentials)
connection = pika.BlockingConnection(parameters)
channel = connection.channel()

channel.queue_declare(queue='ai_jobs', durable=True)

job = {
    'request_id': 'test_123',
    'query': 'hi',
    'ai_model': 'lm_studio',
    'timestamp': int(time.time())
}

channel.basic_publish(
    exchange='',
    routing_key='ai_jobs',
    body=json.dumps(job),
    properties=pika.BasicProperties(
        delivery_mode=2,  # make message persistent
    ))
print(" [x] Sent testing job to ai_jobs")
connection.close()
