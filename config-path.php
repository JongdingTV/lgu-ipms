<?php
// Path configuration for production and development - returns JavaScript config
// Call this in PHP and echo the output in the HTML head

function get_app_config_script() {
    $pathname = $_SERVER['REQUEST_URI'] ?? '/';
    $path_parts = array_filter(explode('/', trim($pathname, '/')));
    
    // Known subdirectories in the app
    $known_dirs = ['dashboard', 'contractors', 'project-registration', 'progress-monitoring', 
                   'budget-resources', 'task-milestone', 'project-prioritization', 'user-dashboard'];
    
    $app_root = '/';
    
    // Find which known directory we're in
    foreach ($path_parts as $i => $part) {
        if (in_array($part, $known_dirs)) {
            // The root is everything before this known directory
            if ($i > 0) {
                // There are path segments before the known directory
                $app_root = '/' . implode('/', array_slice($path_parts, 0, $i)) . '/';
            } else {
                // Known directory is the first part - we're at app root
                $app_root = '/';
            }
            break;
        }
    }
    
    // If no known directory found and we have path parts, use the first one as app root
    if ($app_root === '/' && count($path_parts) > 0 && !in_array($path_parts[0], $known_dirs)) {
        // Check if the first part looks like an app name (not a file)
        if (!preg_match('/\.(php|html|js|css)$/', $path_parts[0])) {
            $app_root = '/' . $path_parts[0] . '/';
        }
    }
    
    return <<<HTML
<script>
window.APP_ROOT = '$app_root';
window.getApiUrl = function(endpoint) {
    console.log('[getApiUrl] Called with:', endpoint);
    
    // Handle both with and without leading slash
    if (!endpoint) {
        console.error('[getApiUrl] Empty endpoint!');
        return '/';
    }
    
    let cleanEndpoint = endpoint;
    if (cleanEndpoint.startsWith('/')) {
        cleanEndpoint = cleanEndpoint.substring(1);
        console.log('[getApiUrl] Removed leading slash:', cleanEndpoint);
    }
    
    // If APP_ROOT is /, return absolute path with /
    if (window.APP_ROOT === '/') {
        const result = '/' + cleanEndpoint;
        console.log('[getApiUrl] APP_ROOT is /, returning:', result);
        return result;
    }
    
    // Otherwise, combine APP_ROOT with endpoint
    const result = window.APP_ROOT + cleanEndpoint;
    console.log('[getApiUrl] APP_ROOT is ' + window.APP_ROOT + ', returning:', result);
    return result;
};
console.log('✅ APP_ROOT configured as:', window.APP_ROOT);
console.log('✅ getApiUrl function available:', typeof window.getApiUrl === 'function');
</script>
HTML;
}
?>