import pika
import time
import sys

def callback(ch, method, properties, body):
    print(f" [x] {method.routing_key}:{body.decode()}", flush=True)

credentials = pika.PlainCredentials('admin', 'RootTom@46')
parameters = pika.ConnectionParameters(host='127.0.0.1', port=5672, credentials=credentials)
try:
    connection = pika.BlockingConnection(parameters)
    channel = connection.channel()
    
    result = channel.queue_declare(queue='', exclusive=True)
    queue_name = result.method.queue
    
    channel.queue_bind(exchange='amq.topic', queue=queue_name, routing_key='#')
    
    print(' [*] Waiting for logs. To exit press CTRL+C', flush=True)
    channel.basic_consume(queue=queue_name, on_message_callback=callback, auto_ack=True)
    channel.start_consuming()
except Exception as e:
    print(e)
