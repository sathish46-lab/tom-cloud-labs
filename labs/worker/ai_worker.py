import pika
import json
import os
import sys
import time
import datetime
import re
import requests
import redis
from fast_interceptor import FastInterceptor
from prompt_loader import load_prompt
try:
    import markdown
except ImportError:
    markdown = None
import google.generativeai as genai
from google.generativeai import caching as genai_caching
from pymongo import MongoClient
from bson.objectid import ObjectId

# Generation logic (prompts + Gemini calls) lives in labsctl: opt/labs-control-panel/src/learn/
_LABSCTL_ROOT = next(
    (p for p in ('/var/www/opt/labs-control-panel', '/opt/labs-control-panel') if os.path.isdir(p)),
    None,
)
if _LABSCTL_ROOT is None:
    raise RuntimeError("labsctl not found — Learn generation lives in opt/labs-control-panel")
sys.path.insert(0, _LABSCTL_ROOT)
from src.learn import chapter as chapter_gen, curriculum as curriculum_gen, roadmap as roadmap_gen
from src.learn.streaming import chunk_text as safe_chunk_text

# Configuration
CONFIG_PATH = '/var/www/env.json' if os.path.exists('/var/www/env.json') else '/host_www/www/env.json' if os.path.exists('/host_www/www/env.json') else '../../env.json'

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
AMQP_PASS = config.get('amqp_pass') or os.environ.get('RABBITMQ_PASS', '')
QUEUE_NAME = 'ai_jobs'
CONTENT_QUEUE_NAME = 'ai_content_jobs'
DLQ_NAME = 'ai_jobs_dlq'
CONTENT_DLQ_NAME = 'ai_content_jobs_dlq'
LESSON_QUEUE_NAME = 'lesson_generation'
LESSON_DLQ_NAME = 'lesson_generation_dlq'

# AI Worker running in stateless API mode.
print("AI Worker running in stateless API mode.", flush=True)

# Redis Config
try:
    redis_client = redis.Redis(host='127.0.0.1', port=6379, db=0, decode_responses=True)
    redis_client.ping()
    print("Redis connection established.", flush=True)
except Exception as e:
    print(f"Redis connection warning: {e}", flush=True)
    redis_client = None

# Gemini Config - Multi-key / Multi-model support with auto-rotation on rate limit
GEMINI_MODEL_NAMES = [m.strip() for m in config.get('gemini_models', 'models/gemini-2.5-flash-lite').split(',') if m.strip()]
GEMINI_API_KEYS = [k.strip() for k in config.get('ai_api_keys', config.get('ai_api_key', '')).split(',') if k.strip()]

if not GEMINI_API_KEYS:
    print("WARNING: No ai_api_key found in config!", flush=True)
    GEMINI_API_KEYS = ['']
    GEMINI_MODEL_NAMES = ['models/gemini-2.5-flash-lite']

_current_key_index = 0
_current_model_index = 0


def get_current_gemini_config():
    """Get current API key and model name"""
    return GEMINI_API_KEYS[_current_key_index], GEMINI_MODEL_NAMES[_current_model_index]


def switch_to_next_key():
    """Rotate to next API key"""
    global _current_key_index
    _current_key_index = (_current_key_index + 1) % len(GEMINI_API_KEYS)
    key, model_name = get_current_gemini_config()
    os.environ['GOOGLE_API_KEY'] = key
    genai.configure(api_key=key)
    print(f" [Gemini] Switched to API key {_current_key_index + 1}/{len(GEMINI_API_KEYS)}, model: {model_name}", flush=True)
    return key, model_name


def switch_to_next_model():
    """Rotate to next model"""
    global _current_model_index
    _current_model_index = (_current_model_index + 1) % len(GEMINI_MODEL_NAMES)
    key, model_name = get_current_gemini_config()
    os.environ['GOOGLE_API_KEY'] = key
    genai.configure(api_key=key)
    print(f" [Gemini] Switched to model: {model_name} (key {_current_key_index + 1}/{len(GEMINI_API_KEYS)})", flush=True)
    return key, model_name


def create_gemini_model():
    """Create a new Gemini model instance with the current API key"""
    key, model_name = get_current_gemini_config()
    os.environ['GOOGLE_API_KEY'] = key
    genai.configure(api_key=key)
    return genai.GenerativeModel(model_name, tools=ai_tools), key, model_name


def is_rate_limit_error(e):
    """Check if exception is a rate limit / quota error"""
    error_str = str(e).lower()
    return any(kw in error_str for kw in [
        '429', 'quota', 'rate limit', 'rate_limit', 'resource exhausted',
        'too many requests', 'billing', 'quota exceeded', 'models/gemini'
    ])


def generate_with_retry(chat_history, query, max_retries=None):
    """Generate content with automatic model/key switching on rate limit.
    
    Retries with next API key, then next model, cycling through all combinations.
    Returns the streaming response iterator.
    """
    global _current_key_index, _current_model_index
    if max_retries is None:
        max_retries = max(len(GEMINI_API_KEYS), 1) * max(len(GEMINI_MODEL_NAMES), 1) * 2

    last_error = None
    for attempt in range(max_retries):
        try:
            model, key, model_name = create_gemini_model()
            chat_session = model.start_chat(history=chat_history)
            response = chat_session.send_message(query, stream=True)
            # If we get here, connection established successfully
            print(f" [Gemini] Using key {_current_key_index + 1}/{len(GEMINI_API_KEYS)}, model: {model_name}", flush=True)
            return response
        except Exception as e:
            last_error = e
            if is_rate_limit_error(e):
                print(f" [!] Rate limit on key {_current_key_index + 1}/{len(GEMINI_API_KEYS)}, model {GEMINI_MODEL_NAMES[_current_model_index]}: {str(e)[:100]}")
                # Rotate to next key first
                if _current_key_index < len(GEMINI_API_KEYS) - 1:
                    switch_to_next_key()
                elif _current_model_index < len(GEMINI_MODEL_NAMES) - 1:
                    switch_to_next_model()
                else:
                    # All exhausted, reset to first
                    _current_key_index = 0
                    _current_model_index = 0
                    key, model_name = get_current_gemini_config()
                    os.environ['GOOGLE_API_KEY'] = key
                    genai.configure(api_key=key)
                    print(f" [!] All keys/models exhausted, resetting to first: {model_name}", flush=True)
                continue
            else:
                # Non-rate-limit error, re-raise immediately
                raise e

    # All retries exhausted
    raise last_error


class GenAIClient:
    """Gemini client with automatic API key/model rotation on rate limit."""

    def __init__(self, history):
        self.history = history
        self.model = None
        self.chat_session = None
        self._create_new_session()

    def _create_new_session(self):
        model, key, model_name = create_gemini_model()
        self.model = model
        self.chat_session = model.start_chat(history=self.history)
        print(f" [Gemini] Using key {_current_key_index + 1}/{len(GEMINI_API_KEYS)}, model: {model_name}", flush=True)

    def send_message(self, message):
        """Send message with auto key rotation on rate limit."""
        last_error = None
        max_retries = max(len(GEMINI_API_KEYS), 1) * max(len(GEMINI_MODEL_NAMES), 1) * 2
        for attempt in range(max_retries):
            try:
                return self.chat_session.send_message(message, stream=True)
            except Exception as e:
                last_error = e
                if is_rate_limit_error(e):
                    print(f" [!] Rate limit on key {_current_key_index + 1}/{len(GEMINI_API_KEYS)}, model {GEMINI_MODEL_NAMES[_current_model_index]}: {str(e)[:100]}")
                    if _current_key_index < len(GEMINI_API_KEYS) - 1:
                        switch_to_next_key()
                    elif _current_model_index < len(GEMINI_MODEL_NAMES) - 1:
                        switch_to_next_model()
                    else:
                        _current_key_index = 0
                        _current_model_index = 0
                        key, model_name = get_current_gemini_config()
                        os.environ['GOOGLE_API_KEY'] = key
                        genai.configure(api_key=key)
                        print(f" [!] All keys/models exhausted, resetting to first: {model_name}", flush=True)
                    # Recreate model and chat session with new key
                    model, key, model_name = create_gemini_model()
                    self.model = model
                    self.chat_session = model.start_chat(history=self.history)
                else:
                    raise e
        raise last_error


# MongoDB Config
print("Configuring MongoDB...", flush=True)
try:
    mongo_uri = config.get('database_file', 'mongodb://localhost:27017/')
    db_name = config.get('main_db', 'tom_labs_db')
    mongo_client = MongoClient(mongo_uri)
    db = mongo_client[db_name]
    mongo_client.admin.command('ping')
    print(f"MongoDB connection established to database: {db_name}", flush=True)
except Exception as e:
    print(f"MongoDB connection error: {e}", flush=True)
    db = None
# Define Gemini Tools (9 Agent Tools — Tom Architecture)
TOOL_API_BASE = 'http://127.0.0.1:8081/src/api/learnAI/tools'
AI_INTERNAL_TOKEN = config.get('ai_internal_token', '')
API_HEADERS = {"Authorization": f"Bearer {AI_INTERNAL_TOKEN}", "Host": "dev.tomweb.in"}

check_lab_status_func = genai.types.FunctionDeclaration(
    name="check_lab_status",
    description="Check the user's currently active lab environment status and details (IP address, Lab Name, instance ID). Use this whenever the user asks if their lab is running, asks for connection details, or wants to check their instance hash.",
)

list_running_labs_func = genai.types.FunctionDeclaration(
    name="list_running_labs",
    description="List ALL lab instances for the current user with their status (running/offline), IP addresses, and instance IDs. Use this when the user asks 'what labs do I have?', 'show my labs', or when a lab tool fails and you need to find an alternative running lab.",
)

get_lab_user_info_func = genai.types.FunctionDeclaration(
    name="get_lab_user_info",
    description="Get the student's real identity: Linux username, email, lab IP, and lab name. CRITICAL: Call this FIRST before creating files, running user applications, or answering 'who am I?' questions. You run as ROOT by default — this reveals the ACTUAL student username.",
)

execute_command_in_lab_func = genai.types.FunctionDeclaration(
    name="execute_command_in_lab",
    description="Execute a shell command inside the user's running lab container and return the output. Use this for hands-on tasks: running scripts, installing packages, checking processes, testing code. Pass 'username' to run as the student (for file operations), omit it to run as root (for system tasks).",
    parameters={
        "type": "OBJECT",
        "properties": {
            "command": {"type": "STRING", "description": "The shell command to execute"},
            "username": {"type": "STRING", "description": "Optional: Run as this Linux user instead of root. Get from get_lab_user_info first."}
        },
        "required": ["command"]
    }
)

read_file_content_func = genai.types.FunctionDeclaration(
    name="read_file_content",
    description="Read the content of a file from inside the user's lab container. Use this to inspect config files, read student code, or check log files.",
    parameters={
        "type": "OBJECT",
        "properties": {
            "file_path": {"type": "STRING", "description": "Absolute path of the file to read inside the container"}
        },
        "required": ["file_path"]
    }
)

read_chapter_content_func = genai.types.FunctionDeclaration(
    name="read_chapter_content",
    description="Read the current chapter's full markdown content. MANDATORY: Always call this before answering lesson/chapter questions. Never answer from general knowledge — the chapter may teach concepts differently. Use this when the user says 'this lesson', 'these examples', 'setup this in my lab'.",
)

get_lesson_outline_func = genai.types.FunctionDeclaration(
    name="get_lesson_outline",
    description="Get the full lesson outline with all modules and chapters, including which chapters have generated content. Use this to see the lesson structure, find chapter IDs, or check generation status.",
)

detect_tool_versions_func = genai.types.FunctionDeclaration(
    name="detect_tool_versions",
    description="Detect versions of installed tools in the lab container (e.g., python3, node, php, git). Use when user asks 'what version of Python is installed?' or before suggesting code that requires specific tool versions.",
    parameters={
        "type": "OBJECT",
        "properties": {
            "tools": {
                "type": "ARRAY",
                "items": {"type": "STRING"},
                "description": "List of tool names to check (e.g., ['python3', 'node', 'git'])"
            }
        },
        "required": ["tools"]
    }
)

read_student_progress_func = genai.types.FunctionDeclaration(
    name="read_student_progress",
    description="Read the student's learning progress for the current lesson from the database. Shows which chapters they've interacted with and how many messages they've exchanged.",
)

ai_tools = [
    check_lab_status_func,
    list_running_labs_func,
    get_lab_user_info_func,
    execute_command_in_lab_func,
    read_file_content_func,
    read_chapter_content_func,
    get_lesson_outline_func,
    detect_tool_versions_func,
    read_student_progress_func,
]

model, _, _ = create_gemini_model()
print(f"Gemini model initialized ({GEMINI_MODEL_NAMES[_current_model_index]}) with {len(ai_tools)} tools.", flush=True)

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
            model=GEMINI_MODEL_NAMES[_current_model_index],
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
            except Exception as e:
                print(f"   > Warning: failed to cache key in Redis: {e}")

        return cache_name
    except Exception as e:
        print(f"   > Context caching unavailable (min token threshold not met or API error): {e}")
        return None

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
    
    summary_prompt = load_prompt('Tutor/summarizer_local.txt', conversation=conversation_text)
    
    # Fetch model ID
    base_url = lm_studio_url.replace('/chat/completions', '')
    model_id = "local-model"
    try:
        models_resp = requests.get(f"{base_url}/models", timeout=3)
        if models_resp.status_code == 200:
            models_data = models_resp.json()
            if 'data' in models_data and len(models_data['data']) > 0:
                model_id = models_data['data'][0]['id']
    except Exception as e:
        print(f"   > Warning: could not fetch LM Studio model list: {e}")

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
    
    summary_prompt = load_prompt('Tutor/summarizer.txt', conversation=conversation_text)
    
    try:
        response = model.generate_content(summary_prompt)
        if response.text:
            return response.text.strip()
    except Exception as e:
        print(f" [!] Gemini summary generation failed: {e}")
    
    return None

def maybe_summarize(user_id, lesson_id, chapter_id, ai_model='lm_studio'):
    """Check if conversation needs summarization and perform it if needed.

    Queries the chat_history collection for the given user/lesson/chapter.
    If the message count exceeds SUMMARIZE_THRESHOLD, the oldest messages
    (beyond KEEP_RECENT) are summarized and replaced with a single summary
    document so the AI context window stays manageable.
    """
    try:
        chat_col = db["chat_history"]
        query = {"user_id": user_id, "lesson_id": lesson_id, "chapter_id": chapter_id}
        messages = list(chat_col.find(query).sort("timestamp", 1))

        if len(messages) <= SUMMARIZE_THRESHOLD:
            return

        to_summarize = messages[:-KEEP_RECENT]
        recent = messages[-KEEP_RECENT:]

        summary_text = None
        if ai_model == "gemini":
            summary_text = generate_summary_via_gemini(to_summarize)
        else:
            summary_text = generate_summary_via_lm_studio(to_summarize)

        if not summary_text:
            print(f" [!] Summarization produced no text for user {user_id}")
            return

        # Replace old messages with a single summary document
        ids_to_remove = [m["_id"] for m in to_summarize if "_id" in m]
        if ids_to_remove:
            chat_col.delete_many({"_id": {"$in": ids_to_remove}})

        chat_col.insert_one({
            "user_id": user_id,
            "lesson_id": lesson_id,
            "chapter_id": chapter_id,
            "role": "system",
            "content": f"[Conversation Summary] {summary_text}",
            "timestamp": datetime.utcnow(),
            "is_summary": True,
        })
        print(f" [x] Summarized {len(to_summarize)} messages for user {user_id}")
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

def stream_to_user(channel, session_id, message_id, chunk_text, is_final=False, topic_prefix="ai_stream", usage=None, source='llm', seq=None):
    """Publish a stream chunk to a specific topic.

    content_stream frames carry PURE TEXT as the body (json-encoded string,
    e.g. " chapters") with metadata in AMQP headers — RabbitMQ maps custom
    headers onto STOMP headers, so subscribers read seq/frame_type/message_id
    from the frame headers. Chat (ai_stream) keeps the legacy JSON envelope.
    """
    try:
        # Using amq.topic for routing to browser session
        routing_key = f"{topic_prefix}.{session_id}"
        bare = topic_prefix == "content_stream"
        properties = None
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
            if bare:
                properties = pika.BasicProperties(
                    content_type='application/json',
                    headers={'frame_type': 'stream_end', 'message_id': message_id}
                )
        elif bare:
            payload = json.dumps(chunk_text)
            headers = {'frame_type': 'text_delta', 'message_id': message_id}
            if seq is not None:
                # Monotonic per-job sequence so reconnecting clients can drop
                # duplicates and detect gaps (see content_partial resume flow)
                headers['seq'] = seq
            properties = pika.BasicProperties(content_type='application/json', headers=headers)
        else:
            msg = {
                'type': 'text_delta',
                'data': chunk_text,
                'message_id': message_id
            }
            if seq is not None:
                msg['seq'] = seq
            payload = json.dumps(msg)

        channel.basic_publish(exchange='amq.topic', routing_key=routing_key, body=payload, properties=properties)
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
    session_id = None
    message_id = None
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

        # Fetch Chat History (stateless implementation via API)
        history = []
        if user_id is not None:
            try:
                hist_resp = requests.get(
                    f"{TOOL_API_BASE}/../worker_history.php",
                    params={"user_id": user_id, "lesson_id": lesson_id, "chapter_id": chapter_id},
                    headers=API_HEADERS,
                    timeout=10
                )
                if hist_resp.status_code == 200:
                    history = hist_resp.json().get('history', [])
            except Exception as e:
                print(f" [!] Failed to fetch chat history: {e}")
        
        # Build authoritative system context
        system_context_text = load_prompt('Tutor/system.txt', lesson_id=lesson_id, chapter_id=chapter_id)

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
            # Check Fast Interceptor First
            interceptor = FastInterceptor(TOOL_API_BASE, API_HEADERS)
            intercepted_tools = interceptor.match_intents(query)
            
            if intercepted_tools:
                print(f" [FastTrack] Intercepted {len(intercepted_tools)} tools for query '{query}'")
                stream_to_user(ch, session_id, message_id, "", is_final=False)
                
                full_content = ""
                
                for intercepted_tool, intercepted_args in intercepted_tools:
                    # Execute tool
                    tool_result = interceptor.execute_tool(intercepted_tool, intercepted_args, user_id, lesson_id, chapter_id)
                    executed_tools.append({"name": intercepted_tool, "output": tool_result})
                    
                    # Send tool execution badge
                    send_tool_execution(
                        channel=ch,
                        session_id=session_id,
                        message_id=message_id,
                        tool_name=intercepted_tool,
                        tool_output=json.dumps(tool_result)[:500]
                    )
                    
                    # Generate and stream dynamic response immediately
                    dynamic_resp = interceptor.generate_response(intercepted_tool, tool_result)
                    full_content += dynamic_resp + "\n\n"
                    stream_to_user(ch, session_id, message_id, dynamic_resp + "\n\n", is_final=False)
                    
                full_content = full_content.strip()
                # Finalize
                stream_to_user(ch, session_id, message_id, "", is_final=True)
                usage_data = {'source': 'fast_interceptor', 'input_tokens': 0, 'output_tokens': len(full_content.split())}
                
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
                
                genai_client = GenAIClient(gemini_history)
                response = genai_client.send_message(query)
            
                endpoint_map = {
                    "check_lab_status": "connection_info.php",
                    "list_running_labs": "list_running.php",
                    "get_lab_user_info": "userinfo.php",
                    "read_chapter_content": "read_chapters.php",
                    "get_lesson_outline": "outline.php",
                    "read_student_progress": "read_progress.php",
                    "execute_command_in_lab": "exec.php",
                    "read_file_content": "read_file.php",
                    "detect_tool_versions": "detect_versions.php"
                }

                max_tool_rounds = 5
                tool_round = 0

                while tool_round < max_tool_rounds:
                    tool_round += 1
                    found_fc = False

                    for chunk in response:
                        fc = None
                        try:
                            for part in chunk.parts:
                                if getattr(part, 'function_call', None):
                                    fc = part.function_call
                                    break
                        except Exception as e:
                            print(f"   > Warning: failed to extract function call from chunk: {e}")

                        if fc:
                            found_fc = True
                            try:
                                response.resolve()
                            except Exception as e:
                                print(f"   > Warning: response.resolve() failed: {e}")

                            tool_data = None
                            tool_name = fc.name
                            fc_args = dict(fc.args) if fc.args else {}

                            if tool_name in endpoint_map:
                                payload = dict(fc_args)
                                payload['user_id'] = user_id
                                payload['lesson_id'] = lesson_id
                                payload['chapter_id'] = chapter_id

                                try:
                                    api_resp = requests.post(
                                        f"{TOOL_API_BASE}/{endpoint_map[tool_name]}",
                                        json=payload,
                                        headers=API_HEADERS,
                                        timeout=30
                                    )
                                    if api_resp.status_code == 200:
                                        tool_data = api_resp.json()
                                    else:
                                        tool_data = {"error": f"API returned HTTP {api_resp.status_code}", "response": api_resp.text[:200]}
                                except Exception as ae:
                                    tool_data = {"error": f"API execution failed: {ae}"}
                            else:
                                tool_data = {"error": f"Unknown tool: {tool_name}"}

                            print(f"   > Tool [{tool_name}] executed (round {tool_round}): {json.dumps(tool_data)[:200]}")
                            send_tool_execution(
                                channel=ch,
                                session_id=session_id,
                                message_id=message_id,
                                tool_name=tool_name,
                                tool_output=json.dumps(tool_data)[:500]
                            )
                            executed_tools.append({"name": tool_name, "output": tool_data})

                            # Feed tool result back to Gemini
                            if not isinstance(tool_data, dict):
                                tool_response_data = {"result": tool_data}
                            else:
                                tool_response_data = tool_data

                            tool_resp = genai.protos.Part(
                                function_response=genai.protos.FunctionResponse(name=tool_name, response=tool_response_data)
                            )
                            response = genai_client.send_message(tool_resp)
                            break  # Break inner for-loop, continue outer while-loop for next round

                        # No function call — stream text (guard: metadata-only tail
                        # chunks with finish_reason + empty parts raise on .text)
                        text = safe_chunk_text(chunk)
                        if text:
                            full_content += text
                            stream_to_user(ch, session_id, message_id, text)

                    if not found_fc:
                        break  # No more tool calls, exit the while loop
            
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

        # 3. Persistence: Push new messages to Chat Database via API
        if user_id is not None and full_content.strip():
            try:
                save_resp = requests.post(
                    f"{TOOL_API_BASE}/../worker_history.php",
                    json={
                        "user_id": user_id,
                        "lesson_id": lesson_id,
                        "chapter_id": chapter_id,
                        "query": query,
                        "response": full_content,
                        "usage": usage_data,
                        "tools": executed_tools
                    },
                    headers=API_HEADERS,
                    timeout=10
                )
                if save_resp.status_code == 200:
                    print(f" [✓] Persisted chat memory for user {user_id} via API")
                else:
                    print(f" [!] Failed to persist chat memory: {save_resp.text}")
            except Exception as e:
                print(f" [!] Failed to persist chat memory API: {e}")

    except Exception as e:
        print(f" [!] Error processing AI job: {e}")
        if session_id and message_id:
            # End the frontend stream so a failed generation doesn't hang the UI
            stream_to_user(ch, session_id, message_id, "", is_final=True)
            
    finally:
        ch.basic_ack(delivery_tag=method.delivery_tag)
        print(" [x] AI Job Done")

_PARTIAL_FLUSH_INTERVAL = 0.8  # seconds between partial-content flushes
_PARTIAL_FIELDS = {
    'content_partial', 'content_partial_session', 'content_partial_seq',
    'content_partial_status', 'content_partial_at',
}


def _flush_content_partial(chapter_id, text, session_id, seq, status='streaming'):
    """Persist the in-flight generated markdown so a reloaded page can resume."""
    try:
        db.ai_chapters.update_one(
            {'_id': ObjectId(chapter_id)},
            {'$set': {
                'content_partial': text,
                'content_partial_session': session_id,
                'content_partial_seq': seq,
                'content_partial_status': status,
                'content_partial_at': int(time.time()),
            }}
        )
    except Exception as pe:
        print(f" [!] Partial content flush failed: {pe}")


_WIRE_PIECE_RE = re.compile(r'\s*\S+')


def _wire_pieces(text):
    """Yield word-sized pieces so each wire frame is a small pure-content
    chunk (sample-style: one word like " chapters" per frame). Concatenation
    of all pieces equals the original text exactly."""
    if not text:
        return
    pos = 0
    for m in _WIRE_PIECE_RE.finditer(text):
        pos = m.end()
        yield m.group(0)
    if pos < len(text):
        yield text[pos:]


def process_content_job(ch, method, properties, body):
    """Callback function to generate human-like tutorial chapter content and stream it"""
    session_id = None
    message_id = 'content_msg'
    chapter_id = None
    full_content = ""
    seq = 0
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

        # Human Tutor Prompt Tuning (prompt build + model call live in labsctl)
        prompt = chapter_gen.build_chapter_prompt(
            lesson_title=lesson_title, module_name=module_name,
            chapter_title=chapter_title, custom_prompt=custom_prompt,
        )

        stream_to_user(ch, session_id, message_id, "", is_final=False, topic_prefix="content_stream")

        last_partial = time.monotonic()
        for text in chapter_gen.stream_chapter_content(prompt):
            full_content += text
            for piece in _wire_pieces(text):
                seq += 1
                stream_to_user(ch, session_id, message_id, piece, topic_prefix="content_stream", seq=seq)
            if time.monotonic() - last_partial >= _PARTIAL_FLUSH_INTERVAL:
                _flush_content_partial(chapter_id, full_content, session_id, seq, 'streaming')
                last_partial = time.monotonic()

        if full_content:
            _flush_content_partial(chapter_id, full_content, session_id, seq, 'streaming')
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

            # Save to MongoDB (atomically drop the resume-partial fields)
            unset_partial = {f: '' for f in _PARTIAL_FIELDS}
            db.ai_chapters.update_one(
                {'_id': ObjectId(chapter_id)},
                {'$set': {
                    'content': full_content,
                    'content_html': rendered_html,
                    'content_updated_at': int(time.time())
                },
                 '$unset': unset_partial}
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
        if chapter_id and full_content:
            # Keep what was generated for resume-after-reload, marked failed
            _flush_content_partial(chapter_id, full_content, session_id, seq, 'failed')
        if session_id:
            # End the frontend stream so a failed generation doesn't hang the UI
            stream_to_user(ch, session_id, message_id, "", is_final=True, topic_prefix="content_stream")
    finally:
        ch.basic_ack(delivery_tag=method.delivery_tag)
        print(" [x] Content Generation Job Done")

# ===========================================================================
# ROADMAP GENERATION — Streaming section-by-section via RabbitMQ
# ===========================================================================
# Roadmap structure prompt: opt/labs-control-panel/src/learn/prompts/roadmaps/structure.txt (labsctl src/learn/roadmap.py)

def publish_roadmap_event(channel, session_id, event_type, data):
    """Publish a roadmap stream event to RabbitMQ topic"""
    try:
        payload = json.dumps({'type': event_type, **data})
        routing_key = f"roadmap_stream.{session_id}"
        channel.basic_publish(exchange='amq.topic', routing_key=routing_key, body=payload)
    except Exception as e:
        print(f"Failed to publish roadmap event: {e}")

def slugify(text):
    """Generate URL-safe slug from title"""
    import re as _re
    slug = text.lower().strip()
    slug = _re.sub(r'[^a-z0-9\s-]', '', slug)
    slug = _re.sub(r'[\s-]+', '-', slug)
    return slug.strip('-')[:80]

def assign_ids(sections):
    """Assign deterministic IDs to sections, topics, items"""
    import hashlib
    for si, section in enumerate(sections):
        section['id'] = 'sec_' + hashlib.sha1((section.get('title','') + str(si)).encode()).hexdigest()[:8]
        section['order'] = si + 1
        for ti, topic in enumerate(section.get('topics', [])):
            topic['id'] = 'top_' + hashlib.sha1((topic.get('title','') + str(ti)).encode()).hexdigest()[:8]
            topic['order'] = ti + 1
            topic['content'] = None
            topic['content_html'] = None
            topic['resources'] = None
            for ii, item in enumerate(topic.get('items', [])):
                item['id'] = 'item_' + hashlib.sha1((item.get('text','') + str(ii)).encode()).hexdigest()[:8]
                item['order'] = ii + 1
                item['type'] = item.get('type', 'concept')
    return sections

def structure_to_markdown(structure):
    """Convert roadmap structure to markdown"""
    md = "# " + (structure.get('title', 'Untitled')) + "\n"
    md += "> " + (structure.get('description', '')) + "\n"
    md += "`tags: " + ', '.join(structure.get('tags', [])) + "`\n"
    md += "`hours: " + str(structure.get('hours', 0)) + "`\n\n"
    for section in structure.get('sections', []):
        md += "## " + (section.get('title', '')) + "\n\n"
        for topic in section.get('topics', []):
            md += "### " + (topic.get('title', '')) + "\n"
            for item in topic.get('items', []):
                item_type = item.get('type', 'concept')
                text = item.get('text', '')
                if item_type == 'milestone':
                    md += "- **" + text + "**\n"
                elif item_type == 'checkpoint':
                    md += "- [ ] " + text + "\n"
                elif item_type == 'decision':
                    md += "- (decision) " + text + "\n"
                else:
                    md += "- " + text + "\n"
            md += "\n"
    return md

def _parse_sections_from_partial(full_text):
    """Extract complete section objects from partially-received JSON using brace counting.
    Returns list of parsed section dicts that are fully parseable."""
    cleaned = re.sub(r'^```(?:json)?\s*|\s*```$', '', full_text.strip())
    sections = []
    sections_match = re.search(r'"sections"\s*:\s*\[', cleaned)
    if not sections_match:
        return sections
    start = sections_match.end()
    depth = 0
    obj_start = None
    in_string = False
    escape_next = False
    i = start
    while i < len(cleaned):
        c = cleaned[i]
        if escape_next:
            escape_next = False
            i += 1
            continue
        if c == '\\' and in_string:
            escape_next = True
            i += 1
            continue
        if c == '"' and not escape_next:
            in_string = not in_string
            i += 1
            continue
        if in_string:
            i += 1
            continue
        if c == '{':
            if depth == 0:
                obj_start = i
            depth += 1
        elif c == '}':
            depth -= 1
            if depth == 0 and obj_start is not None:
                try:
                    obj = json.loads(cleaned[obj_start:i+1])
                    if isinstance(obj, dict) and obj.get('title'):
                        sections.append(obj)
                except json.JSONDecodeError:
                    pass
                obj_start = None
        elif c == ']' and depth == 0:
            break
        i += 1
    return sections


def process_roadmap_gen_job(ch, method, properties, body):
    """Process a roadmap generation job with live incremental card rendering.
    
    Events emitted:
      section_start  — new section detected (title + index)
      topic_start    — new topic/card detected (title + section/topic indices)
      topic_item     — new item discovered in a topic
      completed      — roadmap done, redirect to view page
    """
    job_id = None
    session_id = None
    try:
        job = json.loads(body)
        if job.get('type') != 'roadmap_generation':
            return

        print(f" [x] Processing Roadmap Generation Job: {job.get('job_id', '?')}", flush=True)

        job_id = job.get('job_id')
        user_id = job.get('user_id')
        username = job.get('username', '')
        email = job.get('email', '')
        prompt = job.get('prompt', '')
        level = job.get('level', 'Beginner')
        visibility = job.get('visibility', 'private')
        session_id = job.get('session_id', job_id)

        if not job_id or not prompt:
            print("Missing job_id or prompt, skipping...")
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return

        publish_roadmap_event(ch, session_id, 'progress', {
            'percentage': 5, 'message': 'Analyzing your topic...', 'title': 'Generating...'
        })
        # Prompt + streamed generation with model fallback live in labsctl.
        full_text = ""

        def _roadmap_progress(evt):
            publish_roadmap_event(ch, session_id, 'progress', evt)

        publish_roadmap_event(ch, session_id, 'progress', {
            'percentage': 10, 'message': 'AI is designing roadmap...',
        })

        for text_chunk in roadmap_gen.stream_structure(prompt, level, on_progress=_roadmap_progress):
            full_text += text_chunk

        if not full_text.strip():
            raise Exception("Empty response from Gemini")

        full_text = re.sub(r'^```(?:json)?\s*|\s*```$', '', full_text.strip())
        structure = json.loads(full_text)

        if not structure.get('title') or not structure.get('sections'):
            raise Exception("Invalid structure from Gemini")

        structure['sections'] = assign_ids(structure['sections'])
        import hashlib as _hl
        structure['slug'] = slugify(structure['title']) + '-' + _hl.md5(str(time.time()).encode()).hexdigest()[:6]
        structure['level'] = level
        structure['hours'] = int(structure.get('hours', 20))
        structure['tags'] = structure.get('tags', [])
        structure['description'] = structure.get('description', '')

        # After streaming: emit topic_start + topic_item events grouped by section.
        # Section labels are created by the frontend when the first topic of each section arrives.
        # This ensures section N+1 label only appears AFTER section N's cards are done typing.
        total_sections = len(structure['sections'])
        for idx, section in enumerate(structure['sections']):
            section_title = section.get('title', '')
            sec_pct = 55 + int((idx / max(total_sections, 1)) * 30)

            for ti, topic in enumerate(section.get('topics', [])):
                topic_title = topic.get('title', '')
                publish_roadmap_event(ch, session_id, 'topic_start', {
                    'section_index': idx, 'topic_index': ti,
                    'section_title': section_title,
                    'total_sections': total_sections,
                    'topic': {'title': topic_title, 'id': topic.get('id', ''), 'order': ti + 1},
                    'percentage': sec_pct,
                    'message': f'Section {idx + 1}: {section_title}',
                })
                time.sleep(0.15)

                for item in topic.get('items', []):
                    publish_roadmap_event(ch, session_id, 'topic_item', {
                        'section_index': idx, 'topic_index': ti,
                        'item_index': 0, 'item': item,
                    })
                    time.sleep(0.15)

        publish_roadmap_event(ch, session_id, 'progress', {
            'percentage': 88, 'message': 'Finalizing roadmap...',
            'title': structure['title'],
        })

        md = structure_to_markdown(structure)
        total_items = sum(len(t.get('items', [])) for s in structure['sections'] for t in s.get('topics', []))

        from bson.objectid import ObjectId as _ObjectId
        roadmap_id = _ObjectId()
        roadmap_doc = {
            '_id': roadmap_id, 'slug': structure['slug'],
            'title': structure['title'],
            'description': structure.get('description', ''),
            'prompt': prompt, 'level': level,
            'hours': structure.get('hours', 20),
            'tags': structure.get('tags', []),
            'type': 'roadmap', 'visibility': visibility,
            'user_id': user_id, 'author': username, 'author_email': email,
            'sections': structure['sections'], 'markdown': md,
            'ai_model': GEMINI_MODEL_NAMES[_current_model_index], 'progress': 0,
            'checkpoints_total': total_items, 'checkpoints_completed': 0,
            'created_at': datetime.datetime.utcnow(),
            'updated_at': datetime.datetime.utcnow(),
        }
        db.ai_roadmaps.insert_one(roadmap_doc)
        roadmap_id_str = str(roadmap_id)

        db.ai_roadmap_jobs.update_one(
            {'request_id': job_id},
            {'$set': {'status': 'completed', 'roadmap_id': roadmap_id_str,
                      'slug': structure['slug'], 'percentage': 100,
                      'updated_at': datetime.datetime.utcnow()}}
        )

        publish_roadmap_event(ch, session_id, 'completed', {
            'slug': structure['slug'], 'roadmap_id': roadmap_id_str,
            'title': structure['title'], 'percentage': 100,
            'total_sections': total_sections,
            'message': 'Roadmap generated successfully!',
        })
        print(f" [+] Roadmap generated: {structure['title']} ({roadmap_id_str})")

    except json.JSONDecodeError as e:
        print(f" [!] JSON parse error: {e}")
        publish_roadmap_event(ch, session_id, 'error', {
            'message': 'Failed to parse AI response. Please try again.'
        })
        db.ai_roadmap_jobs.update_one(
            {'request_id': job_id},
            {'$set': {'status': 'failed', 'error_message': 'JSON parse error'}}
        )
    except Exception as e:
        print(f" [!] Error processing roadmap job: {e}")
        try:
            publish_roadmap_event(ch, session_id, 'error', {
                'message': f'Generation failed: {str(e)[:200]}'
            })
        except Exception:
            pass
        try:
            db.ai_roadmap_jobs.update_one(
                {'request_id': job_id},
                {'$set': {'status': 'failed', 'error_message': str(e)[:500]}}
            )
        except Exception:
            pass
    finally:
        ch.basic_ack(delivery_tag=method.delivery_tag)
        print(" [x] Roadmap Generation Job Done")

def process_lesson_job(ch, method, properties, body):
    """Generate a lesson curriculum via labsctl and persist lesson + chapters.

    Ported from labs/htdocs/src/api/learnAI/job_status.php (inline Gemini block).
    Progress is written back to ai_lesson_jobs; PHP job_status.php now only reads.
    """
    request_id = None
    try:
        job = json.loads(body)
        request_id = job.get('request_id')
        topic = (job.get('topic') or '').strip()
        level = job.get('level') or 'Beginner'
        user_id = job.get('user_id')
        username = job.get('username') or 'AI Assistant'

        if not request_id or not topic:
            print(" [!] Lesson job missing request_id/topic, skipping...", flush=True)
            ch.basic_ack(delivery_tag=method.delivery_tag)
            return

        print(f" [x] Processing Lesson Generation Job: {request_id} ({topic!r})", flush=True)

        def update_job(**fields):
            try:
                db.ai_lesson_jobs.update_one({'request_id': request_id}, {'$set': fields})
            except Exception as ue:
                print(f" [!] Lesson job update failed: {ue}", flush=True)

        update_job(status='running', percentage=35,
                   message='Analyzing learning goals & structuring curriculum...')

        result = curriculum_gen.generate_curriculum(topic, level)

        update_job(status='running', percentage=70,
                   message='Generating structured modules & practical exercises...')

        title = result['title']
        subject = result.get('topic') or title
        description = result.get('description') or ('AI-generated professional curriculum for ' + subject + '.')
        modules_data = result['modules']

        tag_subject = re.sub(r'[^a-z0-9]+', '-', subject.lower())
        tags = [t for t in tag_subject.split('-') if t][:3]
        tags.append('ai-generated')

        lesson_id = ObjectId()
        db.ai_lessons.insert_one({
            '_id': lesson_id,
            'title': title,
            'topic': subject,
            'prompt': topic,
            'description': description,
            'level': level,
            'visibility': 'Private',
            'modules_count': len(modules_data),
            'chapters_count': sum(len(m.get('chapters') or []) for m in modules_data),
            'tags': tags,
            'thumbnail': '/assets/images/learn/ai-assistant.png',
            'progress': 0,
            'author': username,
            'created_at': int(time.time()),
        })

        order = 1
        for mod in modules_data:
            mod_name = mod.get('module_name', 'Module')
            for ch_title in (mod.get('chapters') or []):
                db.ai_chapters.insert_one({
                    'lesson_id': lesson_id,
                    'module_name': mod_name,
                    'title': ch_title,
                    'content': '',
                    'order': order,
                    'status': 'in_progress' if order == 1 else 'pending',
                })
                order += 1

        update_job(status='completed', percentage=100,
                   message='Lesson generated successfully!',
                   lesson_id=str(lesson_id), completed=True)
        print(f" [+] Lesson generated: {title} ({lesson_id})", flush=True)

    except Exception as e:
        print(f" [!] Error processing lesson job: {e}", flush=True)
        try:
            if request_id:
                db.ai_lesson_jobs.update_one(
                    {'request_id': request_id},
                    {'$set': {'status': 'failed', 'failed': True,
                              'error_message': str(e)[:500]}}
                )
        except Exception:
            pass
    finally:
        ch.basic_ack(delivery_tag=method.delivery_tag)


def main():
    while True:
        try:
            print(f"Connecting to RabbitMQ at {AMQP_HOST}...", flush=True)
            credentials = pika.PlainCredentials(AMQP_USER, AMQP_PASS)
            parameters = pika.ConnectionParameters(host=AMQP_HOST, port=AMQP_PORT, credentials=credentials)
            connection = pika.BlockingConnection(parameters)
            channel = connection.channel()
            print("RabbitMQ connection established.", flush=True)

            # Declare DLQs first
            channel.queue_declare(
                queue=DLQ_NAME,
                durable=True,
                arguments={'x-message-ttl': 604800000}  # 7 days
            )
            channel.queue_declare(
                queue=CONTENT_DLQ_NAME,
                durable=True,
                arguments={'x-message-ttl': 604800000}  # 7 days
            )
            channel.queue_declare(
                queue=LESSON_DLQ_NAME,
                durable=True,
                arguments={'x-message-ttl': 604800000}  # 7 days
            )
            
            # Declare main queues with DLQ routing
            channel.queue_declare(
                queue=QUEUE_NAME,
                durable=True,
                arguments={
                    'x-dead-letter-exchange': '',
                    'x-dead-letter-routing-key': DLQ_NAME,
                }
            )
            channel.queue_declare(
                queue=CONTENT_QUEUE_NAME,
                durable=True,
                arguments={
                    'x-dead-letter-exchange': '',
                    'x-dead-letter-routing-key': CONTENT_DLQ_NAME,
                }
            )
            channel.queue_declare(
                queue=LESSON_QUEUE_NAME,
                durable=True,
                arguments={
                    'x-dead-letter-exchange': '',
                    'x-dead-letter-routing-key': LESSON_DLQ_NAME,
                }
            )
            
            # Fair dispatch
            channel.basic_qos(prefetch_count=1)
            
            print(f" [*] AI Worker waiting for jobs in '{QUEUE_NAME}', '{CONTENT_QUEUE_NAME}' & '{LESSON_QUEUE_NAME}'.", flush=True)
            
            def dispatch_ai_job(ch, method, properties, body):
                """Route ai_jobs messages to the correct handler"""
                try:
                    job = json.loads(body)
                    if job.get('type') == 'roadmap_generation':
                        process_roadmap_gen_job(ch, method, properties, body)
                    else:
                        process_ai_job(ch, method, properties, body)
                except Exception:
                    process_ai_job(ch, method, properties, body)
            
            channel.basic_consume(queue=QUEUE_NAME, on_message_callback=dispatch_ai_job)
            channel.basic_consume(queue=CONTENT_QUEUE_NAME, on_message_callback=process_content_job)
            channel.basic_consume(queue=LESSON_QUEUE_NAME, on_message_callback=process_lesson_job)
            channel.start_consuming()
            
        except pika.exceptions.AMQPConnectionError:
            print("Connection lost, retrying in 5s...")
            time.sleep(5)
        except Exception as e:
            print(f"Unexpected error: {e}")
            time.sleep(5)

if __name__ == '__main__':
    main()
