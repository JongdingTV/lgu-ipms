<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$matrixPath = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'permission-matrix.php';
if (!is_file($matrixPath)) {
    fwrite(STDERR, "permission-matrix.php not found\n");
    exit(2);
}

$matrix = require $matrixPath;
if (!is_array($matrix)) {
    fwrite(STDERR, "permission matrix is not an array\n");
    exit(2);
}

$targets = ['admin', 'super-admin', 'engineer', 'contractor', 'department-head'];
$used = [];
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
        $content = (string)@file_get_contents($file->getPathname());
        if ($content === '') {
            continue;
        }
        if (preg_match_all("/rbac_require_from_matrix\\('([^']+)'/", $content, $m)) {
            foreach ($m[1] as $perm) {
                $perm = strtolower(trim((string)$perm));
                if ($perm !== '') {
                    $used[$perm] = true;
                }
            }
        }
        if (preg_match_all("/=>\\s*'([^']+)'/", $content, $m2)) {
            foreach ($m2[1] as $perm) {
                $perm = strtolower(trim((string)$perm));
                if ($perm !== '' && (strpos($perm, 'admin.') === 0 || strpos($perm, 'super_admin.') === 0 || strpos($perm, 'department_head.') === 0 || strpos($perm, 'engineer.') === 0 || strpos($perm, 'contractor.') === 0)) {
                    $used[$perm] = true;
                }
            }
        }
    }
}

$matrixKeys = array_map('strtolower', array_keys($matrix));
sort($matrixKeys);
$usedKeys = array_keys($used);
sort($usedKeys);

$missingInMatrix = array_values(array_diff($usedKeys, $matrixKeys));
$unusedInCode = array_values(array_diff($matrixKeys, $usedKeys));

echo "RBAC Consistency Check\n";
echo "Files scanned: {$filesScanned}\n";
echo "Matrix permissions: " . count($matrixKeys) . "\n";
echo "Permissions referenced in code: " . count($usedKeys) . "\n\n";

if (empty($missingInMatrix)) {
    echo "[OK] All referenced permissions exist in permission matrix.\n";
} else {
    echo "[FAIL] Referenced permissions missing in permission matrix:\n";
    foreach ($missingInMatrix as $perm) {
        echo " - {$perm}\n";
    }
}

if (!empty($unusedInCode)) {
    echo "\n[INFO] Permissions defined but not currently referenced in code:\n";
    foreach ($unusedInCode as $perm) {
        echo " - {$perm}\n";
    }
}

exit(empty($missingInMatrix) ? 0 : 1);

