// Shared configuration for API paths - works for both local and production
(function() {
    const pathname = window.location.pathname;
    const pathSegments = pathname.split('/').filter(p => p);
    
    // Known subdirectory names in the app
    const knownDirs = ['dashboard', 'contractors', 'project-registration', 'progress-monitoring', 
                      'budget-resources', 'task-milestone', 'project-prioritization', 'user-dashboard'];
    
    let appRoot = '/';
    let currentPagePath = '';
    
    // Find which known directory we're in
    const currentDirIndex = pathSegments.findIndex(seg => knownDirs.includes(seg));
    
    if (currentDirIndex >= 0) {
        // We're in a subdirectory
        appRoot = '/' + pathSegments.slice(0, currentDirIndex).join('/') + '/';
        currentPagePath = pathSegments[currentDirIndex] + '/';
    } else if (pathSegments.length > 0) {
        // We're in the root, but there might be an app folder
        appRoot = '/' + pathSegments[0] + '/';
    }
    
    window.CONFIG = {
        appRoot: appRoot,
        currentPath: currentPagePath,
        getApiUrl: function(endpoint) {
            return this.appRoot + endpoint;
        },
        getCurrentDirUrl: function(endpoint) {
            return endpoint;
        }
    };
})();
