<?php
/**
 * Role-based access helpers (RBAC)
 * Centralized permission checks for employee roles.
 */

function rbac_permission_matrix(): array
{
    static $matrix = null;
    if ($matrix !== null) {
        return $matrix;
    }

    $path = __DIR__ . '/permission-matrix.php';
    if (!is_file($path)) {
        $matrix = [];
        return $matrix;
    }

    $loaded = require $path;
    $matrix = is_array($loaded) ? $loaded : [];
    return $matrix;
}

function rbac_roles_for(string $permissionCode, array $default = []): array
{
    $matrix = rbac_permission_matrix();
    $permissionCode = trim(strtolower($permissionCode));
    if ($permissionCode !== '' && isset($matrix[$permissionCode]) && is_array($matrix[$permissionCode])) {
        return array_values(array_unique(array_map('strtolower', $matrix[$permissionCode])));
    }
    return $default;
}

function rbac_get_employee_role(): string
{
    return strtolower((string)($_SESSION['employee_role'] ?? ''));
}

function rbac_is_json_request(): bool
{
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $xrw = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $isAjax = ($xrw === 'xmlhttprequest');
    $wantsJson = (strpos($accept, 'application/json') !== false);
    $hasActionParam = isset($_GET['action']) || isset($_POST['action']) || isset($_REQUEST['action']);
    return $isAjax || $wantsJson || $hasActionParam;
}

function rbac_deny(string $message = 'Access denied.'): void
{
    http_response_code(403);
    if (!headers_sent() && rbac_is_json_request()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message, 'error' => 'forbidden']);
        exit;
    }
    die($message);
}

function rbac_require_roles(array $roles): void
{
    $current = rbac_get_employee_role();
    $allowed = array_map('strtolower', $roles);
    if ($current === '' || !in_array($current, $allowed, true)) {
        rbac_deny('Access denied.');
    }
}

/**
 * Enforce role access for a specific action code.
 * Falls back to $defaultRoles when action is not explicitly mapped.
 */
function rbac_require_action_roles(string $action, array $actionRoleMap, array $defaultRoles = []): void
{
    $action = strtolower(trim($action));
    if ($action === '') {
        return;
    }

    if (array_key_exists($action, $actionRoleMap)) {
        rbac_require_roles((array)$actionRoleMap[$action]);
        return;
    }

    if (!empty($defaultRoles)) {
        rbac_require_roles($defaultRoles);
    }
}

/**
 * Enforce action access by mapping action codes to permission codes.
 * Example:
 *  rbac_require_action_matrix('delete', ['delete' => 'admin.engineers.delete'], 'admin.engineers.manage');
 */
function rbac_require_action_matrix(string $action, array $actionPermissionMap, string $defaultPermissionCode = ''): void
{
    $action = strtolower(trim($action));
    if ($action === '') {
        return;
    }

    if (array_key_exists($action, $actionPermissionMap)) {
        $permissionCode = (string)$actionPermissionMap[$action];
        rbac_require_from_matrix($permissionCode, []);
        return;
    }

    if ($defaultPermissionCode !== '') {
        rbac_require_from_matrix($defaultPermissionCode, []);
        return;
    }

    rbac_deny('Access denied.');
}

function rbac_has_permission(string $permission): bool
{
    global $db;
    $role = rbac_get_employee_role();
    if ($role === '' || !isset($db) || $db->connect_error) {
        return false;
    }

    $stmt = $db->prepare(
        "SELECT 1
         FROM roles r
         JOIN role_permissions rp ON rp.role_id = r.id
         JOIN permissions p ON p.id = rp.permission_id
         WHERE LOWER(r.name) = ?
           AND p.code = ?
         LIMIT 1"
    );
    if (!$stmt) return false;
    $stmt->bind_param('ss', $role, $permission);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $ok;
}

function rbac_require_permission(string $permission): void
{
    if (!rbac_has_permission($permission)) {
        rbac_deny('Access denied.');
    }
}

/**
 * Enforce role access using the in-code permission matrix.
 */
function rbac_require_from_matrix(string $permissionCode, array $defaultRoles = []): void
{
    $roles = rbac_roles_for($permissionCode, $defaultRoles);
    if (empty($roles)) {
        rbac_deny('Access denied.');
    }
    rbac_require_roles($roles);
}

function rbac_audit(string $action, string $entityType = '', ?int $entityId = null, array $details = []): void
{
    global $db;
    if (!isset($db) || $db->connect_error) {
        return;
    }
    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
    $role = rbac_get_employee_role();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $payload = json_encode($details, JSON_UNESCAPED_SLASHES);

    $stmt = $db->prepare(
        "INSERT INTO audit_logs (actor_employee_id, actor_role, action, entity_type, entity_id, details_json, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;
    $stmt->bind_param('isssiss', $employeeId, $role, $action, $entityType, $entityId, $payload, $ip);
    $stmt->execute();
    $stmt->close();
}
?>
