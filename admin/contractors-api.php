<?php
require dirname(__DIR__) . '/session-auth.php';
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.legacy.contractors_alias', ['admin','department_admin','super_admin']);
check_suspicious_activity();

require __DIR__ . '/engineers-api.php';
