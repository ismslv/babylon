<?php
function loadConfig(string $file = 'config.json'): array {
    $defaults = [
        'base_language' => 'en',
        'project_name' => 'Untitled',
        'use_versions' => false,
        'languages' => [],
        'users' => []
    ];
    if (!file_exists($file)) return $defaults;

    $json = json_decode(file_get_contents($file), true);
    if (!is_array($json)) return $defaults;

    return array_merge($defaults, $json);
}