"""Chapter lesson-material generation (prompt + streamed model call).

The worker owns queue acking, RabbitMQ streaming events and Mongo/Redis
persistence; this module owns what the model is asked and how it is called.
"""

import google.generativeai as genai

from .curriculum import _ensure_configured
from .prompt_loader import load_prompt
from .streaming import chunk_text

CHAPTER_MODEL = 'models/gemini-2.5-flash-lite'  # parity with worker GEMINI_MODEL_NAME

# Model fallback chain — switch model on each rate-limit retry (parity with
# roadmap.py FALLBACK_MODELS; quotas are per-model so the others stay usable).
FALLBACK_MODELS = [
    'models/gemini-2.5-flash-lite',   # 30 RPM, 1500 RPD
    'models/gemini-3.1-flash-lite',   # new gen, separate quota
    'models/gemini-2.5-flash',        # 10 RPM, 500 RPD
]


def build_chapter_prompt(lesson_title, module_name, chapter_title, custom_prompt=''):
    """Build the chapter material prompt from prompts/Chapters/content_writer.txt."""
    prompt = load_prompt(
        'Chapters/content_writer.txt',
        lesson_title=lesson_title,
        module_name=module_name,
        chapter_title=chapter_title,
    )
    if custom_prompt:
        prompt += f"\nSpecific focus requested by the user: {custom_prompt}\n"
    return prompt


def stream_chapter_content(prompt, model_name=None):
    """Yield generated content chunks. Rate-limit failures advance through the
    fallback chain (only before the first chunk — mid-stream errors raise so
    the worker keeps the partial for resume)."""
    if not _ensure_configured():
        raise RuntimeError('ai_api_key missing — cannot generate chapter content')
    chain = [model_name] if model_name else list(FALLBACK_MODELS)
    if model_name:
        chain += [m for m in FALLBACK_MODELS if m != model_name]

    yielded = False
    last_err = None
    for attempt, name in enumerate(chain):
        try:
            model = genai.GenerativeModel(name)
            response = model.generate_content(prompt, stream=True)
            for chunk in response:
                text = chunk_text(chunk)
                if text:
                    yielded = True
                    yield text
            return
        except Exception as e:
            if yielded:
                raise
            err_str = str(e)
            rate_limited = '429' in err_str or 'quota' in err_str.lower() or 'rate' in err_str.lower()
            if rate_limited and attempt < len(chain) - 1:
                next_model = chain[attempt + 1]
                print(
                    f" [!] Rate limited on {name.split('/')[-1]}, "
                    f"switching to {next_model.split('/')[-1]} (attempt {attempt + 1}/{len(chain)})",
                    flush=True,
                )
                last_err = e
                continue
            raise
    raise last_err if last_err else RuntimeError('all chapter models failed')
