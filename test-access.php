<?php
/**
 * Debug: Test file accessibility
 * Visit: https://ipms.infragovservices.com/test-access.php
 */

echo "<h1>System Access Test</h1>";
echo "<p>If you see this, PHP is working.</p>";

echo "<h2>File/Directory Tests:</h2>";
echo "<ul>";

// Test various paths
$paths = [
    '/admin/index.php',
    '/admin/',
    '/public/',
    '/public/admin-login.php',
    '/public/index.php',
    '/config/',
    '/database.php',
];

foreach ($paths as $path) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
    if (file_exists($full_path)) {
        echo "<li>✅ $path - EXISTS</li>";
    } else {
        echo "<li>❌ $path - NOT FOUND</li>";
    }
}

echo "</ul>";

echo "<h2>PHP Info:</h2>";
echo "<ul>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li>Current File: " . __FILE__ . "</li>";
echo "<li>Session Support: " . (extension_loaded('session') ? 'YES' : 'NO') . "</li>";
echo "</ul>";

echo "<h2>Direct Links to Test:</h2>";
echo "<ul>";
echo "<li><a href='/public/admin-login.php'>→ /public/admin-login.php</a></li>";
echo "<li><a href='/admin/index.php'>→ /admin/index.php</a></li>";
echo "</ul>";
?>
