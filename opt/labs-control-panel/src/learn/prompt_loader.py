"""Prompt loader for labsctl — reads opt/labs-control-panel/src/learn/prompts/.

Same conventions as the worker-side loader (labs/worker/prompt_loader.py):
literal {name} replacement (never str.format — bodies contain JSON braces),
hard error on missing/empty files.
"""

import os

PROMPTS_ROOT = os.environ.get('LABS_PROMPTS_DIR') or os.path.abspath(
    os.path.join(os.path.dirname(os.path.abspath(__file__)), 'prompts')
)


class PromptFileError(Exception):
    """Raised when a prompt file is missing or empty."""


def load_prompt(rel_path, **vars):
    """Load a prompt file and substitute literal {name} placeholders."""
    path = os.path.abspath(os.path.join(PROMPTS_ROOT, rel_path))
    if not path.startswith(PROMPTS_ROOT + os.sep):
        raise PromptFileError(f"Prompt path escapes root: {rel_path}")
    try:
        with open(path, 'r', encoding='utf-8') as f:
            text = f.read()
    except OSError as e:
        raise PromptFileError(f"Prompt file missing: {rel_path} ({e})")
    if not text.strip():
        raise PromptFileError(f"Prompt file empty: {rel_path}")
    for key, value in vars.items():
        text = text.replace('{' + key + '}', str(value))
    return text.rstrip('\n')
