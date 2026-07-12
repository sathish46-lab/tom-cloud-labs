import pika
import json
import os
import sys
import time
import datetime
import re
import requests
import redis
try:
    import markdown
except ImportError:
    markdown = None
import google.generativeai as genai
from google.generativeai import caching as genai_caching
from pymongo import MongoClient
from bson.objectid import ObjectId

# Configuration
CONFIG_PATH = '/host_www/www/env.json' if os.path.exists('/host_www/www/env.json') else '../../env.json'

def load_config():
    print(f"Loading config from {CONFIG_PATH}...", flush=True)
    try:
        with open(CONFIG_PATH, 'r') as f:
            data = json.load(f)
            print("Config loaded successfully.", flush=True)
            return data
    except Exception as e:
        print(f"Error loading config: {e}", flush=True)
        return {}

config = load_config()

# RabbitMQ Config
AMQP_HOST = config.get('amqp_host', '127.0.0.1')
AMQP_PORT = config.get('amqp_port', 5672)
AMQP_USER = config.get('amqp_user', 'admin')
AMQP_PASS = config.get('amqp_pass', 'RootTom@46')
QUEUE_NAME = 'ai_jobs'
CONTENT_QUEUE_NAME = 'ai_content_jobs'

# Redis Config
try:
    redis_client = redis.Redis(host='127.0.0.1', port=6379, db=0, decode_responses=True)
    redis_client.ping()
    print("Redis connection established.", flush=True)
except Exception as e:
    print(f"Redis connection warning: {e}", flush=True)
    redis_client = None

# Gemini Config
GEMINI_MODEL_NAME = 'models/gemini-flash-latest'
print("Configuring Gemini API...", flush=True)
genai.configure(api_key=config.get('ai_api_key'))

# Define Gemini Tools
check_lab_status_func = genai.types.FunctionDeclaration(
    name="check_lab_status",
    description="Check the user's currently active lab environment status and details (like IP address, Lab Name, instance ID). Use this whenever the user asks if their lab is running, asks for connection details, or wants to check their instance hash.",
)
ai_tools = [check_lab_status_func]

model = genai.GenerativeModel(GEMINI_MODEL_NAME, tools=ai_tools)
print(f"Gemini model initialized ({GEMINI_MODEL_NAME}) with tools.", flush=True)

# ===========================================================================
# CONTEXT CACHING: Cache system prompt + lesson context for cost savings
# ===========================================================================
def get_or_create_cached_context(user_id, lesson_id, system_context_text, history_for_cache):
    """Get or create a Gemini CachedContent for the system prompt + lesson context.
    Caches static content so subsequent queries reuse cached tokens (up to 75% cheaper).
    Returns the cache name (str) or None if caching fails/is unavailable."""
    cache_redis_key = f"gemini_cache:{user_id}:{lesson_id}"
    
    # Try to reuse existing cache from Redis
    if redis_client:
        try:
            existing_cache_name = redis_client.get(cache_redis_key)
            if existing_cache_name:
                # Verify it still exists in Gemini
                try:
                    cached = genai_caching.CachedContent.get(existing_cache_name)
                    print(f"   > Reusing existing context cache: {existing_cache_name}")
                    return existing_cache_name
                except Exception:
                    print(f"   > Cached content expired, creating new one...")
                    redis_client.delete(cache_redis_key)
        except Exception as e:
            print(f"   > Redis cache lookup error: {e}")
    
    # Build the content to cache (system context + history prefix)
    # Gemini context caching requires minimum 32,768 tokens, so we include the full context
    contents_to_cache = [
        genai.protos.Content(role="user", parts=[genai.protos.Part(text=f"[System Context]: {system_context_text}")]),
        genai.protos.Content(role="model", parts=[genai.protos.Part(text="Understood. I am locked onto your active lesson, chapter, and lab context.")])
    ]
    
    # Add conversation history as cacheable prefix
    for msg in history_for_cache:
        role = msg.get('role', 'user')
        content = msg.get('content', '')
        if role == 'system_summary':
            contents_to_cache.append(genai.protos.Content(role="user", parts=[genai.protos.Part(text=f"[System: Here is a summary of our previous conversation]: {content}")]))
            contents_to_cache.append(genai.protos.Content(role="model", parts=[genai.protos.Part(text="I understand. I'll keep this context in mind as we continue our conversation.")]))
        else:
            gemini_role = 'model' if role in ('assistant', 'model') else 'user'
            contents_to_cache.append(genai.protos.Content(role=gemini_role, parts=[genai.protos.Part(text=content)]))
    
    try:
        cached_content = genai_caching.CachedContent.create(
            model=GEMINI_MODEL_NAME,
            display_name=f"learn_ai_{user_id}_{lesson_id}",
            contents=contents_to_cache,
            ttl=datetime.timedelta(minutes=30)
        )
        cache_name = cached_content.name
        print(f"   > Created new context cache: {cache_name}")
        
        # Store in Redis with 25 min TTL (slightly less than Gemini's 30 min)
        if redis_client:
            try:
                redis_client.setex(cache_redis_key, 1500, cache_name)
            except Exception:
                pass
        
        return cache_name
    except Exception as e:
        print(f"   > Context caching unavailable (min token threshold not met or API error): {e}")
        return None

# MongoDB Config
print(f"Connecting to MongoDB...", flush=True)
DB_URI = config.get('database_file', 'mongodb://admin:Tombootroot@127.0.0.1:27017/tom_labs_db?authSource=admin')
client = MongoClient(DB_URI)
db = client[config.get('main_db', 'tom_labs_db')]
print("MongoDB connection established.", flush=True)

# RAG Summarization threshold
SUMMARIZE_THRESHOLD = 20  # Trigger summarization when messages exceed this count
KEEP_RECENT = 10          # Number of recent messages to keep after summarization

def generate_summary_via_lm_studio(messages_to_summarize):
    """Generate a summary of old messages using LM Studio"""
    lm_studio_url = config.get('lm_studio_url', 'http://172.17.0.1:1234/v1/chat/completions')
    
    # Build conversation text from old messages
    conversation_text = ""
    for msg in messages_to_summarize:
        role = msg.get('role', 'user')
        role_label = "User" if role == 'user' else "AI Assistant"
        conversation_text += f"{role_label}: {msg.get('content', '')}\n\n"
    
    summary_prompt = (
        "You are a conversation summarizer. Below is a conversation between a user and an AI assistant. "
        "Create a concise summary of the key topics discussed, important facts shared, user preferences, "
        "and any specific information the user revealed about themselves (name, goals, etc). "
        "Keep it under 300 words. Focus on facts that would help continue the conversation naturally.\n\n"
        f"--- CONVERSATION ---\n{conversation_text}\n--- END ---\n\n"
        "Summary:"
    )
    
    # Fetch model ID
    base_url = lm_studio_url.replace('/chat/completions', '')
    model_id = "local-model"
    try:
        models_resp = requests.get(f"{base_url}/models", timeout=3)
        if models_resp.status_code == 200:
            models_data = models_resp.json()
            if 'data' in models_data and len(models_data['data']) > 0:
                model_id = models_data['data'][0]['id']
    except Exception:
        pass
    
    payload = {
        "model": model_id,
        "messages": [
            {"role": "system", "content": "You are a precise conversation summarizer."},
            {"role": "user", "content": summary_prompt}
        ],
        "temperature": 0.3,
        "max_tokens": 500,
        "stream": False
    }
    
    try:
        response = requests.post(lm_studio_url, json=payload, timeout=30)
        if response.status_code == 200:
            data = response.json()
            if 'choices' in data and len(data['choices']) > 0:
                return data['choices'][0]['message']['content'].strip()
    except Exception as e:
        print(f" [!] LM Studio summary generation failed: {e}")
    
    return None

def generate_summary_via_gemini(messages_to_summarize):
    """Generate a summary of old messages using Gemini"""
    conversation_text = ""
    for msg in messages_to_summarize:
        role = msg.get('role', 'user')
        role_label = "User" if role == 'user' else "AI Assistant"
        conversation_text += f"{role_label}: {msg.get('content', '')}\n\n"
    
    summary_prompt = (
        "Create a concise summary of this conversation. Include key topics discussed, "
        "important facts shared, user preferences, and any personal information the user revealed. "
        "Keep it under 300 words. Focus on facts that would help continue the conversation naturally.\n\n"
        f"--- CONVERSATION ---\n{conversation_text}\n--- END ---"
    )
    
    try:
        response = model.generate_content(summary_prompt)
        if response.text:
            return response.text.strip()
    except Exception as e:
        print(f" [!] Gemini summary generation failed: {e}")
    
    return None

def maybe_summarize(user_id, lesson_id, chapter_id, ai_model='lm_studio'):
    """Check if conversation needs summarization and perform it if needed"""
    try:
        filter_key = {"user_id": int(user_id), "lesson_id": lesson_id} if lesson_id else {"user_id": int(user_id), "chapter_id": chapter_id}
        chat_doc = db.ai_chat_history.find_one(filter_key)
        if not chat_doc or 'messages' not in chat_doc:
            return
        
        messages = chat_doc['messages']
        
        last_summarized_index = chat_doc.get('last_summarized_index', 0)
        unsummarized_messages = messages[last_summarized_index:]
        
        # Filter out existing system_summary messages in case they are still in the DB from the old system
        actual_unsummarized = [m for m in unsummarized_messages if m.get('role') != 'system_summary']
        
        if len(actual_unsummarized) <= SUMMARIZE_THRESHOLD:
            return  # Not enough messages to warrant summarization
        
        print(f" [⚡] Triggering RAG summarization ({len(actual_unsummarized)} unsummarized messages > {SUMMARIZE_THRESHOLD} threshold)")
        
        # Split: messages to summarize now, and recent to keep as raw context
        messages_to_summarize = actual_unsummarized[:-KEEP_RECENT]
        
        # If there's an existing RAG summary, we should include it in the summarization prompt so context isn't lost
        old_summary = chat_doc.get('rag_summary', '')
        if old_summary:
            messages_to_summarize.insert(0, {'role': 'user', 'content': f"[Previous Summary Context to merge]: {old_summary}"})
            
        # Generate summary using the same model the user is chatting with
        summary_text = None
        if ai_model == 'lm_studio':
            summary_text = generate_summary_via_lm_studio(messages_to_summarize)
        else:
            summary_text = generate_summary_via_gemini(messages_to_summarize)
        
        if not summary_text:
            print(" [!] Summary generation returned empty, skipping summarization")
            return
            
        new_summarized_index = last_summarized_index + len(actual_unsummarized[:-KEEP_RECENT])
        
        # Atomic update in MongoDB - ONLY update the summary fields, DO NOT touch messages array
        db.ai_chat_history.update_one(
            filter_key,
            {'$set': {
                'rag_summary': summary_text,
                'last_summarized_index': new_summarized_index,
                'last_summary_at': int(time.time()),
                'total_summarized': new_summarized_index
            }}
        )
        
        print(f" [✓] RAG Summarization complete. Total summarized pointer moved to index {new_summarized_index}")
        
    except Exception as e:
        print(f" [!] Summarization error: {e}")

def stream_lm_studio(query, url, stream_callback, history=None):
    # Dynamically fetch the loaded model ID first
    base_url = url.replace('/chat/completions', '')
    model_id = "local-model"
    try:
        models_resp = requests.get(f"{base_url}/models", timeout=3)
        if models_resp.status_code == 200:
            models_data = models_resp.json()
            if 'data' in models_data and len(models_data['data']) > 0:
                model_id = models_data['data'][0]['id']
    except Exception as e:
        print(f"Failed to fetch models from LM studio: {e}")

    messages = [{"role": "system", "content": "You are a helpful AI learning assistant."}]
    
    # Append conversation history (includes summary if present)
    if history:
        for msg in history:
            role = msg.get('role', 'user')
            content = msg.get('content', '')
            
            # Handle system_summary: inject as system context
            if role == 'system_summary':
                messages.append({
                    "role": "system",
                    "content": f"[Previous conversation summary]: {content}"
                })
                continue
            
            # Convert 'model' to 'assistant' for OpenAI compatibility
            role = 'assistant' if role == 'model' else role
            messages.append({"role": role, "content": content})
            
    # Append the new query
    messages.append({"role": "user", "content": query})

    payload = {
        "model": model_id,
        "messages": messages,
        "temperature": 0.7,
        "max_tokens": -1,
        "stream": True,
        "frequency_penalty": 0.1,
        "presence_penalty": 0.1
    }
    
    try:
        with requests.post(url, json=payload, stream=True) as response:
            if response.status_code != 200:
                stream_callback(f"Error from LM Studio: HTTP {response.status_code}\n", is_final=False)
                return ""
            
            full_content = ""
            for line in response.iter_lines():
                if line:
                    decoded_line = line.decode('utf-8')
                    if decoded_line.startswith('data: '):
                        data_str = decoded_line[6:]
                        if data_str.strip() == '[DONE]':
                            break
                        try:
                            data = json.loads(data_str)
                            if 'choices' in data and len(data['choices']) > 0:
                                chunk_content = data['choices'][0]['delta'].get('content', '')
                                if chunk_content:
                                    full_content += chunk_content
                                    stream_callback(chunk_content, is_final=False)
                        except json.JSONDecodeError:
                            pass
            return full_content
    except Exception as e:
        stream_callback(f"Failed to connect to LM Studio: {e}\nPlease check if it is running and accessible at {url}", is_final=False)
        return ""

def stream_to_user(channel, session_id, message_id, chunk_text, is_final=False, topic_prefix="ai_stream", usage=None, source='llm'):
    """Publish a stream chunk to a specific topic"""
    try:
        if is_final:
            msg = {
                'type': 'stream_end',
                'data': '',
                'message_id': message_id,
                'source': source
            }
            if usage:
                msg['usage'] = usage
            payload = json.dumps(msg)
        else:
            payload = json.dumps({
                'type': 'text_delta',
                'data': chunk_text,
                'message_id': message_id
            })
        
        # Using amq.topic for routing to browser session
        routing_key = f"{topic_prefix}.{session_id}"
        channel.basic_publish(exchange='amq.topic', routing_key=routing_key, body=payload)
    except Exception as e:
        print(f"Failed to stream to user: {e}")

def send_tool_execution(channel, session_id, message_id, tool_name, tool_output, topic_prefix="ai_stream"):
    """Send a tool execution event to the frontend"""
    try:
        payload = json.dumps({
            'type': 'tool_execution',
            'message_id': message_id,
            'tool_name': tool_name,
            'tool_output': tool_output
        })
        routing_key = f"{topic_prefix}.{session_id}"
        channel.basic_publish(exchange='amq.topic', routing_key=routing_key, body=payload)
    except Exception as e:
        print(f"Failed to send tool execution event: {e}")

def process_ai_job(ch, method, properties, body):
    """Callback function to process an AI generation job"""
    try:
        job = json.loads(body)
        print(f" [x] Processing AI Job: {job}")
        
        session_id = job.get('session_id')
        message_id = job.get('message_id')
        query = job.get('query')
        lesson_id = str(job.get('lesson_id', ''))
        chapter_id = job.get('chapter_id', '')
        user_id = job.get('user_id')
        ai_model = job.get('ai_model', 'gemini')
        
        # Normalize types
        if user_id is not None:
            user_id = int(user_id)
        if chapter_id is None:
            chapter_id = ''
        
        if not query or not session_id or not message_id:
            print("Missing query or identifiers, skipping...")
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return

        # Fetch Chat History (single shared chat history per lesson)
        history = []
        if user_id is not None:
            chat_doc = None
            if lesson_id:
                chat_doc = db.ai_chat_history.find_one({"user_id": user_id, "lesson_id": lesson_id})
            if not chat_doc and chapter_id:
                chat_doc = db.ai_chat_history.find_one({"user_id": user_id, "chapter_id": chapter_id})
            if chat_doc and 'messages' in chat_doc:
                full_history = chat_doc['messages']
                
                # Retrieve the hidden RAG summary instead of searching the messages array
                rag_summary = chat_doc.get('rag_summary', '')
                last_summarized_index = chat_doc.get('last_summarized_index', 0)
                
                # If there's a background summary, inject it into the AI's history
                if rag_summary:
                    history.append({'role': 'system_summary', 'content': rag_summary})
                    
                # Now, only append the raw messages that come AFTER the summary pointer
                # Also filter out any legacy 'system_summary' messages that might still be in the array
                unsummarized_messages = full_history[last_summarized_index:]
                recent = [m for m in unsummarized_messages if m.get('role') != 'system_summary']
                
                history.extend(recent)
                
                print(f"   > Loaded {len(history)} context entries (1 hidden summary + {len(recent)} unsummarized of {len(full_history)} total)")

        # Build authoritative system context from Layer 5 pre-fetch
        ctx = job.get('context', {})
        ch_ctx = ctx.get('chapter_context', {})
        lab_ctx = ctx.get('lab_context', {})
        system_context_text = (
            f"You are the AI Learning Assistant for Tom Cloud Labs. "
            f"The user is currently studying the lesson '{ch_ctx.get('lesson_title', 'Database Design and Organization')}', "
            f"Module '{ch_ctx.get('module_name', 'General')}', "
            f"Chapter '{ch_ctx.get('title', 'Chapter Overview')}'. "
            f"Their active lab environment is '{lab_ctx.get('name', 'Essentials')}' (IP: {lab_ctx.get('ip', '172.30.0.28')}). "
            f"CRITICAL RULE: DO NOT spontaneously mention the lesson name, chapter name, module name, or lab IP in your greetings or general responses. ONLY mention this context if the user EXPLICITLY asks about their current lesson, chapter, or lab environment."
        )

        # 1. Start Streaming from Gemini or LM Studio
        full_content = ""
        usage_data = None
        executed_tools = []
        
        if ai_model == 'lm_studio':
            lm_studio_url = config.get('lm_studio_url', 'http://172.17.0.1:1234/v1/chat/completions')
            stream_to_user(ch, session_id, message_id, "", is_final=False)
            
            def send_chunk(text, is_final=False):
                stream_to_user(ch, session_id, message_id, text, is_final=is_final)
                
            lm_history = [{"role": "system", "content": system_context_text}] + history
            full_content = stream_lm_studio(query, lm_studio_url, send_chunk, history=lm_history)
            print(f"   > LM Studio stream completed")
            # LM Studio: token tracking N/A
            usage_data = {'source': 'lm_studio'}
        else:
            # Standard path: Format history for Gemini API
            gemini_history = [
                {"role": "user", "parts": [f"[System Context]: {system_context_text}"]},
                {"role": "model", "parts": ["Understood. I am locked onto your active lesson, chapter, and lab context."]}
            ]
            for msg in history:
                role = msg.get('role', 'user')
                content = msg.get('content', '')
                
                if role == 'system_summary':
                    gemini_history.append({"role": "user", "parts": [f"[System: Here is a summary of our previous conversation]: {content}"]})
                    gemini_history.append({"role": "model", "parts": ["I understand. I'll keep this context in mind as we continue our conversation."]})
                    continue
                
                role = 'model' if role == 'assistant' else role
                gemini_history.append({"role": role, "parts": [content]})
                
            chat_session = model.start_chat(history=gemini_history)
            response = chat_session.send_message(query, stream=True)
            
            for chunk in response:
                fc = None
                try:
                    for part in chunk.parts:
                        if getattr(part, 'function_call', None):
                            fc = part.function_call
                            break
                except Exception:
                    pass

                if fc:
                    try:
                        response.resolve()
                    except Exception:
                        pass
                        
                    if fc.name == "check_lab_status":
                        # Execute Tool
                        tool_data = lab_ctx if lab_ctx else {"status": "No active lab"}
                        send_tool_execution(
                            channel=ch, 
                            session_id=session_id, 
                            message_id=message_id, 
                            tool_name="check_lab_status", 
                            tool_output=json.dumps(tool_data)
                        )
                        executed_tools.append({
                            "name": "check_lab_status",
                            "output": tool_data,
                            "lab_name": lab_ctx.get('name', 'Unknown') if lab_ctx else 'Unknown'
                        })
                        # Reply to Model
                        tool_resp = genai.protos.Part(
                            function_response=genai.protos.FunctionResponse(name=fc.name, response=tool_data)
                        )
                        # Wait for model to generate text stream based on tool result
                        follow_up_response = chat_session.send_message(tool_resp, stream=True)
                        for f_chunk in follow_up_response:
                            if f_chunk.text:
                                full_content += f_chunk.text
                                words = re.findall(r'\S+|\s+', f_chunk.text)
                                for word in words:
                                    stream_to_user(ch, session_id, message_id, word)
                                    time.sleep(0.01)
                        # Extract usage from the final response
                        response = follow_up_response 
                        break

                if chunk.text:
                    full_content += chunk.text
                    stream_to_user(ch, session_id, message_id, chunk.text)
            
            # Extract token usage metadata from the completed response
            try:
                um = response.usage_metadata
                usage_data = {
                    'source': 'gemini',
                    'input_tokens': um.prompt_token_count if um else 0,
                    'output_tokens': um.candidates_token_count if um else 0,
                    'cached_tokens': um.cached_content_token_count if um else 0,
                    'total_tokens': um.total_token_count if um else 0
                }
                cache_pct = round((usage_data['cached_tokens'] / usage_data['input_tokens'] * 100), 1) if usage_data['input_tokens'] > 0 else 0
                usage_data['cache_hit_percent'] = cache_pct
                print(f"   > Token Usage: input={usage_data['input_tokens']}, output={usage_data['output_tokens']}, cached={usage_data['cached_tokens']} ({cache_pct}%), total={usage_data['total_tokens']}")
            except Exception as e:
                print(f"   > Could not extract usage metadata: {e}")
                usage_data = {'source': 'gemini'}

        # 2. Finalize with usage data
        stream_to_user(ch, session_id, message_id, "", is_final=True, usage=usage_data, source=usage_data.get('source', 'llm') if usage_data else 'llm')
        print(" [✓] Stream completed.")

        # 3. Persistence: Push new messages to Chat Database
        if user_id is not None and full_content.strip():
            try:
                ts = int(time.time())
                filter_key = {'user_id': user_id, 'lesson_id': lesson_id} if lesson_id else {'user_id': user_id, 'chapter_id': chapter_id}
                db.ai_chat_history.update_one(
                    filter_key,
                    {'$push': {
                        'messages': {
                            '$each': [
                                {'role': 'user', 'content': query, 'timestamp': ts},
                                {'role': 'model', 'content': full_content, 'timestamp': ts + 1, 'usage': usage_data, 'tools': executed_tools}
                            ]
                        }
                    }},
                    upsert=True
                )
                print(f" [✓] Persisted chat memory for user {user_id} in lesson '{lesson_id}'")
                
                # 4. RAG Summarization: Check if we need to compress old messages
                maybe_summarize(user_id, lesson_id, chapter_id, ai_model)
                
            except Exception as e:
                print(f" [!] Failed to update MongoDB Chat History: {e}")

    except Exception as e:
        print(f" [!] Error processing AI job: {e}")
            
    finally:
        ch.basic_ack(delivery_tag=method.delivery_tag)
        print(" [x] AI Job Done")

def process_content_job(ch, method, properties, body):
    """Callback function to generate human-like tutorial chapter content and stream it"""
    try:
        job = json.loads(body)
        print(f" [x] Processing Content Generation Job: {job}")
        
        session_id = job.get('session_id')
        message_id = job.get('message_id', 'content_msg')
        chapter_id = job.get('chapter_id')
        user_id = job.get('user_id')
        custom_prompt = job.get('custom_prompt', '')

        if not chapter_id or not session_id:
            print("Missing chapter_id or session_id, skipping...")
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return

        chapter = db.ai_chapters.find_one({"_id": ObjectId(chapter_id)})
        if not chapter:
            print(f"Chapter {chapter_id} not found")
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return

        lesson = db.ai_lessons.find_one({"_id": chapter.get('lesson_id')})
        lesson_title = lesson.get('title', 'AI & Software Development') if lesson else 'AI Course'
        module_name = chapter.get('module_name', 'Module 1')
        chapter_title = chapter.get('title', 'Lesson Chapter')

        # Human Tutor Prompt Tuning
        prompt = (
            f"Act as an exceptionally experienced, engaging human senior mentor and tech educator teaching a live course on '{lesson_title}'.\n"
            f"You are currently writing the official lesson material for the chapter: '{chapter_title}' (part of '{module_name}').\n\n"
            "CRITICAL INSTRUCTIONS FOR YOUR TONE AND STYLE:\n"
            "1. Write like a warm, relatable human mentor explaining practical engineering concepts clearly. Avoid robotic AI phrases (e.g. 'Delve into', 'In conclusion', 'As an AI').\n"
            "2. Keep the content focused, practical, and highly digestible (concise enough for rapid testing and focused learning, no unnecessary filler).\n"
            "3. Use structured Markdown:\n"
            "   - Start directly with Level 2 (`##`) and Level 3 (`###`) subheadings.\n"
            "   - Provide clean real-world examples and bullet points.\n"
            "4. SYNTAX HIGHLIGHTING & CODE BLOCKS: Whenever you include code examples or commands in ANY language (Python, JavaScript, PHP, SQL, Bash, JSON, C++, Go, Rust, HTML/CSS, etc.), YOU MUST enclose them in standard Markdown fenced code blocks with the exact language name explicitly specified on the opening backticks (e.g. ```python, ```javascript, ```php, ```sql, ```bash). Every code block must have a valid language tag.\n"
        )
        if custom_prompt:
            prompt += f"\nSpecific focus requested by the user: {custom_prompt}\n"

        stream_to_user(ch, session_id, message_id, "", is_final=False, topic_prefix="content_stream")

        full_content = ""
        response = model.generate_content(prompt, stream=True)
        for chunk in response:
            if chunk.text:
                full_content += chunk.text
                stream_to_user(ch, session_id, message_id, chunk.text, topic_prefix="content_stream")

        stream_to_user(ch, session_id, message_id, "", is_final=True, topic_prefix="content_stream")
        print(" [✓] Content Generation Stream completed.")

        if full_content.strip():
            # Parse Markdown to HTML
            if markdown:
                rendered_html = markdown.markdown(
                    full_content,
                    extensions=['fenced_code', 'tables', 'nl2br', 'sane_lists']
                )
            else:
                rendered_html = f'<div class="raw-markdown">{full_content}</div>'

            # Save to MongoDB
            db.ai_chapters.update_one(
                {'_id': ObjectId(chapter_id)},
                {'$set': {
                    'content': full_content,
                    'content_html': rendered_html,
                    'content_updated_at': int(time.time())
                }}
            )
            print(f" [✓] Updated MongoDB ai_chapters with generated content & pre-rendered HTML for chapter {chapter_id}")

            # Save to Redis Cache
            if redis_client:
                try:
                    redis_client.setex(f"learn:content:{chapter_id}", 86400, rendered_html)
                    print(f" [✓] Cached rendered HTML in Redis key learn:content:{chapter_id}")
                except Exception as re:
                    print(f" [!] Redis cache error: {re}")

    except Exception as e:
        print(f" [!] Error processing content job: {e}")
    finally:
        ch.basic_ack(delivery_tag=method.delivery_tag)
        print(" [x] Content Generation Job Done")

def main():
    while True:
        try:
            print(f"Connecting to RabbitMQ at {AMQP_HOST}...", flush=True)
            credentials = pika.PlainCredentials(AMQP_USER, AMQP_PASS)
            parameters = pika.ConnectionParameters(host=AMQP_HOST, port=AMQP_PORT, credentials=credentials)
            connection = pika.BlockingConnection(parameters)
            channel = connection.channel()
            print("RabbitMQ connection established.", flush=True)

            # Declare queues
            channel.queue_declare(queue=QUEUE_NAME, durable=True)
            channel.queue_declare(queue=CONTENT_QUEUE_NAME, durable=True)
            
            # Fair dispatch
            channel.basic_qos(prefetch_count=1)
            
            print(f" [*] AI Worker waiting for jobs in '{QUEUE_NAME}' & '{CONTENT_QUEUE_NAME}'.", flush=True)
            
            channel.basic_consume(queue=QUEUE_NAME, on_message_callback=process_ai_job)
            channel.basic_consume(queue=CONTENT_QUEUE_NAME, on_message_callback=process_content_job)
            channel.start_consuming()
            
        except pika.exceptions.AMQPConnectionError:
            print("Connection lost, retrying in 5s...")
            time.sleep(5)
        except Exception as e:
            print(f"Unexpected error: {e}")
            time.sleep(5)

if __name__ == '__main__':
    main()
