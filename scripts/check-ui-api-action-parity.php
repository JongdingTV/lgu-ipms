<?php
declare(strict_types=1);

$root = dirname(__DIR__);

$pairs = [
    [
        'api' => 'department-head/api.php',
        'ui' => ['department-head/department-head.js']
    ],
    [
        'api' => 'engineer/api.php',
        'ui' => ['engineer/monitoring.php', 'engineer/task_milestone.php']
    ],
    [
        'api' => 'contractor/api.php',
        'ui' => ['contractor/dashboard.php', 'contractor/progress_monitoring.php']
    ],
];

function read_file_safe(string $path): string
{
    return is_file($path) ? (string)file_get_contents($path) : '';
}

function extract_api_actions(string $content): array
{
    $actions = [];
    if (preg_match_all("/'([a-z0-9_]+)'\\s*=>\\s*'([a-z0-9_.]+)'/i", $content, $m)) {
        foreach ($m[1] as $act) {
            $actions[strtolower($act)] = true;
        }
    }
    return array_keys($actions);
}

function extract_ui_actions(string $content): array
{
    $actions = [];
    if (preg_match_all("/api(?:Get|Post|PostForm)\\(\\s*'([a-z0-9_]+)'/i", $content, $m)) {
        foreach ($m[1] as $act) {
            $actions[strtolower($act)] = true;
        }
    }
    if (preg_match_all("/action=([a-z0-9_]+)/i", $content, $m2)) {
        foreach ($m2[1] as $act) {
            $actions[strtolower($act)] = true;
        }
    }
    return array_keys($actions);
}

$warnings = [];

foreach ($pairs as $pair) {
    $apiPath = $root . DIRECTORY_SEPARATOR . $pair['api'];
    $apiContent = read_file_safe($apiPath);
    if ($apiContent === '') {
        $warnings[] = "Missing API file: {$pair['api']}";
        continue;
    }
    $apiActions = extract_api_actions($apiContent);
    $apiSet = array_fill_keys($apiActions, true);

    foreach ($pair['ui'] as $uiRel) {
        $uiPath = $root . DIRECTORY_SEPARATOR . $uiRel;
        $uiContent = read_file_safe($uiPath);
        if ($uiContent === '') {
            $warnings[] = "Missing UI file: {$uiRel}";
            continue;
        }
        $uiActions = extract_ui_actions($uiContent);
        foreach ($uiActions as $action) {
            if (!isset($apiSet[$action])) {
                $warnings[] = "{$uiRel} uses action '{$action}' not mapped in {$pair['api']}";
            }
        }
    }
}

echo "UI/API Action Parity Check\n";
if (empty($warnings)) {
    echo "[OK] No mismatches detected.\n";
    exit(0);
}
echo "[WARN] Potential mismatches:\n";
foreach ($warnings as $w) {
    echo " - {$w}\n";
}
exit(0);

