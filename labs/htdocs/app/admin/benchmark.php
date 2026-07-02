<?php
require_once __DIR__ . '/../../src/load.php';

function benchmark($name, $callback, $iterations = 10000) {
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $callback();
    }
    $end = microtime(true);
    $time = ($end - $start) * 1000; // in milliseconds
    echo "$name x $iterations iterations: " . number_format($time, 2) . " ms\n";
}

// 1. Benchmark get_config
benchmark('get_config("database_file")', function() {
    get_config('database_file');
});

// 2. Benchmark Cache::get
// First ensure something is in cache
Cache::set('benchmark_test', ['a' => 1, 'b' => 2]);
benchmark('Cache::get("benchmark_test")', function() {
    Cache::get('benchmark_test');
});
