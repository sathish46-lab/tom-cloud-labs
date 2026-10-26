"""Curriculum (lesson outline) generation — moved out of PHP job_status.php.

generate_curriculum(prompt, level) returns:
    {'title', 'topic', 'description', 'modules': [{'module_name', 'chapters': [...]}],
     'source': 'ai' | 'fallback'}

Never raises: any AI/config/parse failure falls back to a deterministic
generic curriculum (parity with the old PHP fallback block).
"""

import json
import os
import re

import google.generativeai as genai

from .prompt_loader import load_prompt

CURRICULUM_MODEL = 'models/gemini-2.5-flash'

_LABSCTL_ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..'))

_STOP_WORDS = [
    'i', 'me', 'the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'are',
    'want', 'how', 'can', 'to', 'do', 'not', 'learn', 'about', 'teach', 'cover',
    'act', 'as', 'generate', 'create', 'please', 'explain', 'build', 'design',
    'manage', 'keep', 'include', 'scratch', 'comprehensive', 'single', 'chapter',
    'sections', 'divided', 'exactly', 'strictly', 'clear', 'expert', 'technician',
    'instructor', 'fundamentals', 'basics', 'practical', 'exercises', 'concepts',
    'application', 'including',
]


def _load_env():
    for path in ('/var/www/env.json', os.path.join(_LABSCTL_ROOT, 'env.json')):
        if os.path.exists(path):
            try:
                with open(path, 'r') as f:
                    return json.load(f)
            except Exception:
                pass
    return {}


def _ensure_configured():
    """Configure genai from env.json ai_api_key. Returns True when a key exists."""
    api_key = _load_env().get('ai_api_key', '')
    if not api_key:
        return False
    os.environ['GOOGLE_API_KEY'] = api_key
    genai.configure(api_key=api_key)
    return True


def _strip_fences(text):
    return re.sub(r'^```(?:json)?\s*|\s*```$', '', text.strip(), flags=re.S)


def generate_curriculum(prompt, level):
    """Generate a lesson curriculum (title + modules + chapters). AI with deterministic fallback."""
    try:
        if not _ensure_configured():
            raise RuntimeError('ai_api_key missing')
        ai_prompt = load_prompt('Lessons/curriculum.txt', prompt=prompt, level=level)
        model = genai.GenerativeModel(CURRICULUM_MODEL)
        response = model.generate_content(
            ai_prompt,
            generation_config=genai.types.GenerationConfig(
                temperature=0.7, max_output_tokens=8192,
                response_mime_type='application/json',
            ),
        )
        parsed = json.loads(_strip_fences(response.text or ''))
        if parsed and parsed.get('title') and parsed.get('modules'):
            subject = parsed.get('topic') or parsed['title']
            return {
                'title': parsed['title'],
                'topic': subject,
                'description': parsed.get('description') or ('AI-generated professional curriculum for ' + subject + '.'),
                'modules': parsed['modules'],
                'source': 'ai',
            }
        raise RuntimeError('invalid curriculum JSON')
    except Exception as e:
        print(f" [!] curriculum AI generation failed ({e}); using fallback", flush=True)
        return _fallback(prompt, level)


def _fallback(prompt, level):
    """Deterministic generic curriculum — ported 1:1 from the old PHP job_status.php block."""
    words = re.split(r'[\s,.\-;:!?]+', prompt or '')
    core = []
    for w in words:
        w = w.strip()
        if w and w.lower() not in _STOP_WORDS and len(w) > 2:
            core.append(w.lower().capitalize())
    seen, unique = set(), []
    for w in core:
        if w not in seen:
            seen.add(w)
            unique.append(w)
    subject = ' '.join(unique[:5]) or 'Technology & Engineering'

    title = subject + ': ' + level + ' Course'
    description = 'Professional structured curriculum for ' + subject + '.'
    modules = [
        {
            'module_name': '1. Foundations and Core Concepts',
            'chapters': ['1. Core Theory and Fundamentals', '2. Environment Setup and Tooling', '3. Data Flow and Architecture'],
        },
        {
            'module_name': '2. Practical Implementation',
            'chapters': ['1. Building Core Components', '2. Integration and Security', '3. State Management and Error Handling'],
        },
        {
            'module_name': '3. Testing and Deployment',
            'chapters': ['1. Performance Profiling', '2. Debugging Techniques', '3. Production Readiness'],
        },
    ]
    return {
        'title': title,
        'topic': subject,
        'description': description,
        'modules': modules,
        'source': 'fallback',
    }
