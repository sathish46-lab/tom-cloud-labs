"""labsctl learn — Learn AI generation commands.

Owns lesson/chapter/roadmap generation (prompts under src/learn/prompts/).
RabbitMQ queues, acking, streaming events and Mongo persistence stay with
the worker / PHP API — these commands are the callable generators.
"""

import json
import sys

from src.router import Command


def _emit(status, code, message, data=None):
    print(json.dumps({'status': status, 'code': code, 'message': message, 'data': data}))
    sys.exit(0 if status == 'success' else 1)


def _ok(message='Success', data=None):
    _emit('success', 200, message, data)


def _err(code, message):
    _emit('error', code, message)


class LearnCmd(Command):
    """labsctl learn — Learn AI generation operations."""

    name = "learn"
    description = "Learn AI generation (lessons, chapters, roadmaps)"
    usage = "labsctl learn <generate-lesson|generate-chapter|generate-roadmap> [--flags]"

    def __init__(self, router=None):
        super().__init__()
        self.subcommands = {
            "generate-lesson": (
                self._generate_lesson,
                "Generate a lesson curriculum JSON (AI with deterministic fallback)",
                "labsctl learn generate-lesson --topic=TOPIC [--level=Beginner]",
            ),
            "generate-chapter": (
                self._generate_chapter,
                "Generate one chapter's lesson material (streams markdown to stdout)",
                "labsctl learn generate-chapter --chapter-id=ID [--custom=NOTE] [--save]",
            ),
            "generate-roadmap": (
                self._generate_roadmap,
                "Generate a roadmap structure JSON (streams to stdout)",
                "labsctl learn generate-roadmap --topic=TOPIC [--level=Beginner]",
            ),
        }

    def _generate_lesson(self, args):
        topic = args.flag('topic')
        level = args.flag('level', 'Beginner')
        if not topic or not str(topic).strip():
            _err(400, 'Missing required flag --topic')
        try:
            from src.learn import curriculum
            result = curriculum.generate_curriculum(str(topic).strip(), level)
        except Exception as e:
            _err(500, f'Curriculum generation failed: {e}')
        chapters = sum(len(m.get('chapters') or []) for m in result['modules'])
        _ok('Lesson curriculum generated', {
            'title': result['title'],
            'topic': result['topic'],
            'description': result['description'],
            'level': level,
            'modules_count': len(result['modules']),
            'chapters_count': chapters,
            'source': result['source'],
            'modules': result['modules'],
        })

    def _generate_chapter(self, args):
        chapter_id = args.flag('chapter-id')
        custom = args.flag('custom', '')
        save = bool(args.flag('save'))
        if not chapter_id:
            _err(400, 'Missing required flag --chapter-id')

        from bson import ObjectId
        from src.learn import chapter as chapter_gen

        if self.db is None:
            _err(500, 'MongoDB unavailable')
        try:
            oid = ObjectId(chapter_id)
        except Exception:
            _err(400, f'Invalid --chapter-id: {chapter_id}')

        chapter = self.db.ai_chapters.find_one({'_id': oid})
        if not chapter:
            _err(404, f'Chapter not found: {chapter_id}')
        lesson = self.db.ai_lessons.find_one({'_id': chapter.get('lesson_id')})
        lesson_title = lesson.get('title', 'AI & Software Development') if lesson else 'AI Course'
        module_name = chapter.get('module_name', 'Module 1')
        chapter_title = chapter.get('title', 'Lesson Chapter')

        prompt = chapter_gen.build_chapter_prompt(lesson_title, module_name, chapter_title, custom or '')
        full_content = ''
        try:
            for text in chapter_gen.stream_chapter_content(prompt):
                full_content += text
                sys.stdout.write(text)
                sys.stdout.flush()
        except Exception as e:
            sys.stdout.write('\n')
            _err(500, f'Chapter generation failed: {e}')
        sys.stdout.write('\n')

        if save and full_content.strip():
            try:
                import markdown as md_lib
                rendered_html = md_lib.markdown(
                    full_content,
                    extensions=['fenced_code', 'tables', 'nl2br', 'sane_lists'],
                )
            except Exception:
                rendered_html = f'<div class="raw-markdown">{full_content}</div>'
            import time as _time
            self.db.ai_chapters.update_one(
                {'_id': oid},
                {'$set': {
                    'content': full_content,
                    'content_html': rendered_html,
                    'content_updated_at': int(_time.time()),
                }},
            )
        # Text already streamed; append machine-readable status on the last line.
        print(json.dumps({
            'status': 'success', 'code': 200,
            'message': 'Chapter generated' + (' and saved' if save else ''),
            'data': {'chapter_id': chapter_id, 'chars': len(full_content), 'saved': save},
        }))

    def _generate_roadmap(self, args):
        topic = args.flag('topic')
        level = args.flag('level', 'Beginner')
        if not topic or not str(topic).strip():
            _err(400, 'Missing required flag --topic')
        from src.learn import roadmap as roadmap_gen
        try:
            for text in roadmap_gen.stream_structure(str(topic).strip(), level):
                sys.stdout.write(text)
                sys.stdout.flush()
            sys.stdout.write('\n')
        except Exception as e:
            sys.stdout.write('\n')
            _err(500, f'Roadmap generation failed: {e}')
