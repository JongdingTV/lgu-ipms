<?php
/**
 * Directory Structure Diagnostic
 * Helps identify correct file paths
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directory Structure Diagnostic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 2rem; }
        .code-box { background: #222; color: #0f0; padding: 1rem; border-radius: 6px; font-family: monospace; font-size: 0.9rem; overflow-x: auto; margin: 1rem 0; }
        .card { margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Directory Structure Diagnostic</h1>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Current File Locations</h3>
            </div>
            <div class="card-body">
                <p><strong>Current Script:</strong></p>
                <div class="code-box"><?php echo __FILE__; ?></div>

                <p><strong>Current Directory:</strong></p>
                <div class="code-box"><?php echo __DIR__; ?></div>

                <p><strong>Document Root:</strong></p>
                <div class="code-box"><?php echo $_SERVER['DOCUMENT_ROOT']; ?></div>

                <p><strong>Parent Directory:</strong></p>
                <div class="code-box"><?php echo dirname(__DIR__); ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-success text-white">
                <h3>File Existence Checks</h3>
            </div>
            <div class="card-body">
                <?php
                $paths_to_check = [
                    'config/email.php',
                    '../config/email.php',
                    '/config/email.php',
                    dirname(__DIR__) . '/config/email.php',
                    dirname(__DIR__) . '/database.php',
                    $_SERVER['DOCUMENT_ROOT'] . '/../config/email.php',
                ];

                foreach ($paths_to_check as $path) {
                    $exists = file_exists($path) ? '✅ EXISTS' : '❌ NOT FOUND';
                    $realpath = realpath($path) ? realpath($path) : 'N/A';
                    echo "<p><strong>Path:</strong> " . htmlspecialchars($path) . " <strong style='color: " . (strpos($exists, '✅') === 0 ? 'green' : 'red') . ";'>" . $exists . "</strong></p>";
                    if (strpos($exists, '✅') === 0) {
                        echo "<div class='code-box' style='color: #0f0;'>" . htmlspecialchars($realpath) . "</div>";
                    }
                }
                ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-info text-white">
                <h3>Directory Listing</h3>
            </div>
            <div class="card-body">
                <p><strong>Contents of <?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT']); ?>:</strong></p>
                <div class="code-box">
                    <?php
                    $files = scandir($_SERVER['DOCUMENT_ROOT']);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            $is_dir = is_dir($_SERVER['DOCUMENT_ROOT'] . '/' . $file) ? '📁 ' : '📄 ';
                            echo $is_dir . htmlspecialchars($file) . "\n";
                        }
                    }
                    ?>
                </div>

                <p style="margin-top: 1rem;"><strong>Contents of parent directory (<?php echo htmlspecialchars(dirname($_SERVER['DOCUMENT_ROOT'])); ?>):</strong></p>
                <div class="code-box">
                    <?php
                    $parent_dir = dirname($_SERVER['DOCUMENT_ROOT']);
                    if (is_dir($parent_dir)) {
                        $files = scandir($parent_dir);
                        foreach ($files as $file) {
                            if ($file !== '.' && $file !== '..') {
                                $is_dir = is_dir($parent_dir . '/' . $file) ? '📁 ' : '📄 ';
                                echo $is_dir . htmlspecialchars($file) . "\n";
                            }
                        }
                    } else {
                        echo "Cannot read parent directory";
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-warning">
                <h3>What to do next</h3>
            </div>
            <div class="card-body">
                <p>Based on the file existence checks above:</p>
                <ol>
                    <li><strong>Find the correct path to config/email.php</strong> - Look for ✅ marks above</li>
                    <li><strong>Copy that path</strong> - It will be in green in the code box</li>
                    <li><strong>Report back</strong> with the correct paths for:
                        <ul>
                            <li>config/email.php</li>
                            <li>database.php</li>
                            <li>config-path.php</li>
                        </ul>
                    </li>
                </ol>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
