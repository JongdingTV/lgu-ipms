<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_roles(['admin','department_admin','super_admin']);

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden'
    ]);
    exit;
}

if (!($db instanceof mysqli) || $db->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

function dbhc_table_exists(mysqli $db, string $tableName): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

function dbhc_column_exists(mysqli $db, string $tableName, string $columnName): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

$requiredTables = [
    'projects',
    'feedback',
    'contractors',
    'contractor_project_assignments',
    'project_tasks',
    'project_milestones',
    'contractor_documents',
    'contractor_project_history',
    'contractor_violations',
    'contractor_evaluation_logs'
];

$requiredColumns = [
    'projects' => [
        'priority_percent',
        'engineer_license_doc',
        'engineer_certification_doc',
        'engineer_credentials_doc'
    ],
    'feedback' => [
        'district',
        'barangay',
        'alternative_name',
        'exact_address',
        'photo_path',
        'map_lat',
        'map_lng',
        'map_link'
    ],
    'contractors' => [
        'full_name',
        'license_expiration_date',
        'past_project_count',
        'delayed_project_count',
        'performance_rating',
        'compliance_status',
        'reliability_score',
        'risk_score',
        'risk_level',
        'last_evaluated_at'
    ]
];

$missingTables = [];
$missingColumns = [];

foreach ($requiredTables as $table) {
    if (!dbhc_table_exists($db, $table)) {
        $missingTables[] = $table;
    }
}

foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
        if (!dbhc_column_exists($db, $table, $column)) {
            if (!isset($missingColumns[$table])) {
                $missingColumns[$table] = [];
            }
            $missingColumns[$table][] = $column;
        }
    }
}

$ok = empty($missingTables) && empty($missingColumns);

echo json_encode([
    'success' => true,
    'ok' => $ok,
    'checked_at' => date('c'),
    'missing' => [
        'tables' => $missingTables,
        'columns' => $missingColumns
    ],
    'recommended_migration' => '/database/migrations/2026_02_20_admin_enhancements_compat.sql'
    ,
    'recommended_engineer_module_migration' => '/database/migrations/2026_02_21_engineer_hiring_module.sql'
]);
