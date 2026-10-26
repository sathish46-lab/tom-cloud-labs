# AI Prompts

Single source of truth for every AI prompt the platform sends (Learn AI and
Roadmaps). Editing wording here changes behavior — no code deploy needed
beyond the normal repo sync.

## Layout

```
src/learn/prompts/
├── Lessons/curriculum.txt            Lesson outline (title/modules/chapters) — labsctl: src/learn/curriculum.py
├── Chapters/content_writer.txt       Chapter lesson material            — labsctl: src/learn/chapter.py (worker streams it)
├── Tutor/
│   ├── system.txt                    Tutor system context + rules       — Python: labs/worker/ai_worker.py
│   ├── summarizer.txt                Conversation summary (Gemini)      — Python: labs/worker/ai_worker.py
│   └── summarizer_local.txt          Conversation summary (LM Studio)   — Python: labs/worker/ai_worker.py
├── roadmaps/
│   └── structure.txt                 Roadmap structure JSON             — labsctl: src/learn/roadmap.py (worker streams it)
└── README.md
```

## Why roadmap prompts live here

Roadmap generation is part of the learn family today: `roadmap.py` lives in
`src/learn/` and the command is `labsctl learn generate-roadmap`. Prompts
follow their code — one root, one loader, one test suite. If roadmap ever
splits into its own package (`src/roadmap/` + `labsctl roadmap …`), move
`roadmaps/` out with the code in the same change; the loader tests fail
loudly on any path mismatch.

## Conventions

- Placeholders are literal `{name}` tokens, substituted by simple string
  replacement (never `str.format()` / PHP interpolation — prompt bodies may
  contain real JSON braces).
- Missing file = hard error in both runtimes (fail loudly, never silently
  fall back to an inline copy).
- One-line role stubs (e.g. "You are a precise conversation summarizer.")
  stay inline at their call site; only multi-line prompts live here.

## Loaders

- labsctl: `opt/labs-control-panel/src/learn/prompt_loader.py` →
  `load_prompt('Lessons/curriculum.txt', prompt=..., level=...)`.
  Root: `$LABS_PROMPTS_DIR` override, else `opt/labs-control-panel/src/learn/prompts`.
  Used by `labsctl learn generate-*` and the worker (which imports
  `src.learn.*` for all generation).
- Python (tutor/summarizer only): `labs/worker/prompt_loader.py` → `load_prompt('Tutor/system.txt', lesson_id=..., chapter_id=...)`
  Root resolution: `$LABS_PROMPTS_DIR` env override, else repo-relative
  `opt/labs-control-panel/src/learn/prompts` (works in container at `/var/www/opt/...`
  and on host).
- PHP: `TomLabs` sibling class `PromptLoader::get(...)` — currently only the
  worker/CLI-less fallback paths; in `labs/htdocs/src/lib/core/PromptLoader.class.php`.
  Root: `/opt/labs-control-panel/src/learn/prompts` if mounted, else repo-relative.
  New file under `src/lib/` → clear the includes cache once:
  `docker exec Dev_lab rm -f /var/cache/labs/includes.cache`

Tests: `labs/workspace/tests/test_prompt_loader.php`.
