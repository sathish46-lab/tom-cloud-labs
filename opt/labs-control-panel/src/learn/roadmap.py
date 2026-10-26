"""Roadmap structure generation (prompt + streamed model call with fallback chain).

The worker owns queue acking, RabbitMQ events, partial-section parsing and
Mongo persistence; this module owns the prompt and the model fallback logic.
"""

import time

import google.generativeai as genai

from .curriculum import _ensure_configured
from .prompt_loader import load_prompt
from .streaming import chunk_text

# Model fallback chain — switch model on each rate-limit retry.
# All verified to support JSON output mode.
FALLBACK_MODELS = [
    'models/gemini-2.5-flash-lite',   # 30 RPM, 1500 RPD
    'models/gemini-3.1-flash-lite',   # new gen, separate quota
    'models/gemini-2.5-flash',        # 10 RPM, 500 RPD
]


def build_structure_prompt(prompt, level):
    """Load prompts/roadmaps/structure.txt with {prompt}/{level} substituted."""
    return load_prompt('roadmaps/structure.txt', prompt=prompt, level=level)


def stream_structure(prompt, level, on_progress=None):
    """Yield roadmap JSON text chunks.

    on_progress(dict) is called with {'percentage', 'message'} for progress UI
    (the worker turns those into RabbitMQ events). Raises when every model in
    the fallback chain fails; rate-limit failures advance to the next model.
    """
    if not _ensure_configured():
        raise RuntimeError('ai_api_key missing — cannot generate roadmap')

    ai_prompt = build_structure_prompt(prompt, level)

    def progress(evt):
        if on_progress:
            on_progress(evt)

    for attempt, model_name in enumerate(FALLBACK_MODELS):
        try:
            model = genai.GenerativeModel(model_name)
            response = model.generate_content(
                ai_prompt,
                generation_config=genai.types.GenerationConfig(
                    temperature=0.7, max_output_tokens=4096,
                    response_mime_type='application/json',
                ),
                stream=True,
            )
            chunk_count = 0
            for chunk in response:
                text = chunk_text(chunk)
                if text:
                    chunk_count += 1
                    if chunk_count % 5 == 0:
                        progress({
                            'percentage': min(10 + (chunk_count * 2), 55),
                            'message': f'Designing structure... ({chunk_count * 20} tokens)',
                        })
                    yield text
            return

        except Exception as gemini_err:
            err_str = str(gemini_err)
            rate_limited = '429' in err_str or 'quota' in err_str.lower() or 'rate' in err_str.lower()
            if rate_limited and attempt < len(FALLBACK_MODELS) - 1:
                next_model = FALLBACK_MODELS[attempt + 1]
                print(
                    f" [!] Rate limited on {model_name.split('/')[-1]}, "
                    f"switching to {next_model.split('/')[-1]} (attempt {attempt + 1}/{len(FALLBACK_MODELS)})",
                    flush=True,
                )
                progress({
                    'percentage': 10,
                    'message': f'Rate limited — switching to {next_model.split("/")[-1]}...',
                })
                time.sleep(2)
                continue
            if rate_limited:
                raise Exception('AI rate limit exceeded on all models. Please try again in a few minutes.')
            raise
