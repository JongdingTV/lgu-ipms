<?php
session_start();
require __DIR__ . '/database.php';
require __DIR__ . '/config-path.php';
?>
<!doctype html>
<html>
<head>
    <title>Test Config</title>
    <?php echo get_app_config_script(); ?>
</head>
<body>
    <h1>Configuration Test</h1>
    <p>Open your browser console and check if these are defined:</p>
    <pre id="output"></pre>
    
    <script>
        const output = document.getElementById('output');
        output.textContent = `
APP_ROOT = ${window.APP_ROOT}
getApiUrl = ${typeof window.getApiUrl}

Testing getApiUrl('project-registration/registered_projects.php?action=load_projects'):
${getApiUrl('project-registration/registered_projects.php?action=load_projects')}

Testing getApiUrl('progress-monitoring/progress_monitoring.php?action=load_projects'):
${getApiUrl('progress-monitoring/progress_monitoring.php?action=load_projects')}
        `;
    </script>
</body>
</html>
