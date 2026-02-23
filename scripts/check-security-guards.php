<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$targets = ['admin', 'super-admin', 'engineer', 'contractor', 'department-head'];

$findings = [];
$filesScanned = 0;

foreach ($targets as $dir) {
    $base = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($base)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file instanceof SplFileInfo || strtolower((string)$file->getExtension()) !== 'php') {
            continue;
        }
        $filesScanned++;
        $path = $file->getPathname();
        $content = (string)@file_get_contents($path);
        if ($content === '') {
            continue;
        }

        $hasPostHandling = (strpos($content, 'REQUEST_METHOD') !== false && strpos($content, 'POST') !== false)
            || strpos($content, '$_POST[') !== false;
        $hasCsrfCheck = strpos($content, 'verify_csrf_token(') !== false
            || (strpos($content, 'hash_equals(') !== false && strpos($content, 'csrf') !== false);
        $hasActionHandler = strpos($content, '$_GET[\'action\']') !== false
            || strpos($content, '$_POST[\'action\']') !== false
            || strpos($content, '$_REQUEST[\'action\']') !== false;
        $hasActionMap = strpos($content, 'rbac_require_action_matrix(') !== false;
        $hasAuth = strpos($content, 'check_auth();') !== false;
        $hasSuspicious = strpos($content, 'check_suspicious_activity();') !== false;

        if ($hasPostHandling && $hasAuth && !$hasCsrfCheck) {
            $findings[] = [
                'type' => 'missing_csrf_check',
                'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path),
            ];
        }
        if ($hasActionHandler && $hasAuth && !$hasActionMap) {
            $findings[] = [
                'type' => 'missing_action_rbac_map',
                'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path),
            ];
        }
        if ($hasAuth && !$hasSuspicious) {
            $findings[] = [
                'type' => 'missing_suspicious_activity_guard',
                'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $path),
            ];
        }
    }
}

echo "Security Guard Check\n";
echo "Files scanned: {$filesScanned}\n\n";

if (empty($findings)) {
    echo "[OK] No issues detected by heuristic scan.\n";
    exit(0);
}

$grouped = [];
foreach ($findings as $f) {
    $grouped[$f['type']][] = $f['file'];
}

foreach ($grouped as $type => $files) {
    echo "[WARN] {$type}\n";
    foreach (array_unique($files) as $file) {
        echo " - {$file}\n";
    }
    echo "\n";
}

// Heuristic checker only: warnings should be reviewed manually.
exit(0);

