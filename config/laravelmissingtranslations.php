<?php

return [
    'paths' => [
        app_path(),
        resource_path('views'),
    ],
    'extensions' => ['php', 'blade.php'],
    'exclude_paths' => [],
    'sort_keys' => true,
    'exclude_dot_keys' => false,
    'include_functions' => [
        '__',
        'trans',
        'trans_choice',
        '@lang',
        '@choice',
        'Lang::get',
        'Lang::has',
        'Lang::choice',
    ],
    'exclude_patterns' => [],
    'json_flags' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
];
