<?php
/**
 * Test: PromptLoader — shared AI prompt templates in opt/labs-control-panel/src/learn/prompts.
 *
 * REAL RUNTIME — loads the actual shipped prompt files through the real class
 * (not source grepping):
 *   1. Happy path: every registered prompt loads, is substantial, vars inject
 *   2. Placeholders: substituted values present, no unresolved {tokens}
 *   3. Literal braces: JSON bodies in prompts survive (strtr, not interpolation)
 *   4. Failure path: missing file and path traversal throw — never a silent
 *      inline fallback
 *
 * Auth/ownership categories do not apply: PromptLoader is a non-HTTP internal
 * class with no user input surface beyond the relative path its own callers pass.
 *
 * Usage:
 *   php workspace/tests/test_prompt_loader.php
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== PromptLoader Tests ===\n\n";

// Keep in sync with opt/labs-control-panel/src/learn/prompts/README.md
$known = [
    'Lessons/curriculum.txt'      => ['prompt' => 'Learn PHP', 'level' => 'Beginner'],
    'Chapters/content_writer.txt' => ['lesson_title' => 'L', 'chapter_title' => 'C', 'module_name' => 'M'],
    'Tutor/system.txt'            => ['lesson_id' => 'lid1', 'chapter_id' => 'cid1'],
    'Tutor/summarizer.txt'        => ['conversation' => 'USER: hi'],
    'Tutor/summarizer_local.txt'  => ['conversation' => 'USER: hi'],
];

foreach ($known as $path => $vars) {
    try {
        $text = PromptLoader::get($path, $vars);
        test("load: {$path}", $text !== '' && strlen($text) > 40, 'len=' . strlen($text));
    } catch (\Throwable $e) {
        test("load: {$path}", false, $e->getMessage());
    }
}

$cur = PromptLoader::get('Lessons/curriculum.txt', ['prompt' => 'Learn PHP', 'level' => 'Beginner']);
test('curriculum: user request injected', str_contains($cur, 'User Request: "Learn PHP"'));
test('curriculum: level injected', str_contains($cur, 'Difficulty Level: Beginner') && str_contains($cur, '"Beginner" difficulty level'));
test('curriculum: no unresolved placeholders', !str_contains($cur, '{prompt}') && !str_contains($cur, '{level}'));
test('curriculum: JSON braces preserved', str_contains($cur, '"modules": [') && str_contains($cur, "    {\n      \"module_name\""));

$tut = PromptLoader::get('Tutor/system.txt', ['lesson_id' => 'LX', 'chapter_id' => 'CX']);
test('tutor: ids injected', str_contains($tut, "Lesson ID: 'LX'") && str_contains($tut, "Chapter ID: 'CX'"));
test('tutor: rule set intact', str_contains($tut, 'RULES:') && str_contains($tut, '8. CRITICAL RULE:'));

$chap = PromptLoader::get('Chapters/content_writer.txt', [
    'lesson_title' => 'Docker', 'chapter_title' => 'Volumes', 'module_name' => 'M1',
]);
test('chapter: titles injected', str_contains($chap, "course on 'Docker'") && str_contains($chap, "chapter: 'Volumes' (part of 'M1')"));
test('chapter: code-fence rule intact', str_contains($chap, 'SYNTAX HIGHLIGHTING & CODE BLOCKS'));

// Documented semantics: without vars, placeholders stay literal (callers must pass them)
$partial = PromptLoader::get('Tutor/summarizer.txt');
test('summarizer: placeholder literal without vars', str_contains($partial, '{conversation}'));

// Failure paths
try {
    PromptLoader::get('Lessons/no_such_prompt.txt');
    test('missing file throws', false, 'no exception');
} catch (\RuntimeException $e) {
    test('missing file throws', true, $e->getMessage());
}

try {
    PromptLoader::get('../../../../etc/passwd');
    test('path traversal rejected', false, 'no exception');
} catch (\RuntimeException $e) {
    test('path traversal rejected', true, $e->getMessage());
}

test_summary();
