<?php

/**
 * Shared AI prompt loader.
 *
 * Reads prompt templates from opt/labs-control-panel/src/learn/prompts/ (the labsctl
 * prompts folder) so wording can be edited without touching application
 * code. Sibling of Cache/TimeHelper: plain global class, loaded by the
 * recursive src/lib/ includes.
 *
 * Placeholders are literal {name} tokens replaced with plain strtr — prompt
 * bodies may contain real JSON braces, so no interpolation is used.
 * A missing or empty file throws: never silently falls back to inline text.
 */
class PromptLoader {

    /**
     * Resolve the prompts root: container mount first, repo-relative fallback.
     */
    private static function root(): string {
        $mounted = '/opt/labs-control-panel/src/learn/prompts';
        if (is_dir($mounted)) {
            return $mounted;
        }
        // __DIR__ = <root>/labs/htdocs/src/lib/core → up 5 = <root>
        return dirname(__DIR__, 5) . '/opt/labs-control-panel/src/learn/prompts';
    }

    /**
     * Load a prompt file and substitute {name} placeholders.
     *
     * @param string $relPath Path under the prompts root, e.g. 'Lessons/curriculum.txt'
     * @param array  $vars    Placeholder values keyed without braces: ['prompt' => '...']
     * @return string Prompt text with placeholders substituted (trailing newline trimmed).
     * @throws \RuntimeException when the file is missing or empty.
     */
    public static function get(string $relPath, array $vars = []): string {
        $root = realpath(self::root());
        $path = ($root !== false) ? realpath($root . '/' . ltrim($relPath, '/')) : false;
        if ($path === false || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
            throw new \RuntimeException("Prompt file not found: {$relPath}");
        }
        $text = file_get_contents($path);
        if ($text === false || trim($text) === '') {
            throw new \RuntimeException("Prompt file empty: {$relPath}");
        }
        if ($vars) {
            $replace = [];
            foreach ($vars as $key => $value) {
                $replace['{' . $key . '}'] = (string)$value;
            }
            $text = strtr($text, $replace);
        }
        return rtrim($text, "\n");
    }
}
