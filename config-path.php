<?php
// Path configuration for production and development - returns JavaScript config
// Call this in PHP and echo the output in the HTML head

function get_app_config_script() {
    $document_root = $_SERVER['DOCUMENT_ROOT'];
    $script_path = __DIR__;
    
    // Relative path from document root
    $relative_path = str_replace($document_root, '', $script_path);
    $path_parts = array_filter(explode('/', trim($relative_path, '/')));
    
    // Known subdirectories
    $known_dirs = ['dashboard', 'contractors', 'project-registration', 'progress-monitoring', 
                   'budget-resources', 'task-milestone', 'project-prioritization', 'user-dashboard'];
    
    $app_root = '/';
    
    // Find if we're in a subdirectory
    foreach ($path_parts as $i => $part) {
        if (in_array($part, $known_dirs)) {
            $app_root = '/' . implode('/', array_slice($path_parts, 0, $i)) . '/';
            break;
        }
    }
    
    // Fallback: if we have path parts and first one isn't a known dir, it's the app
    if ($app_root === '/' && count($path_parts) > 0) {
        $app_root = '/' . $path_parts[0] . '/';
    }
    
    return <<<HTML
<script>
window.APP_ROOT = '$app_root';
window.getApiUrl = function(endpoint) {
    return window.APP_ROOT + endpoint;
};
console.log('APP_ROOT configured as:', window.APP_ROOT);
</script>
HTML;
}
?>