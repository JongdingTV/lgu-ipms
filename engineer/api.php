<?php
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/session-auth.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

header('Content-Type: application/json');

if (!isset($_SESSION['employee_id']) || strtolower((string) ($_SESSION['employee_role'] ?? '')) !== 'engineer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$action = (string) ($_GET['action'] ?? '');
if ($action !== 'load_monitoring') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

$db->query("CREATE TABLE IF NOT EXISTS project_progress_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    updated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_created (project_id, created_at),
    CONSTRAINT fk_progress_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_employee FOREIGN KEY (updated_by) REFERENCES employees(id) ON DELETE CASCADE
)");

$sql = "SELECT
            p.id,
            p.code,
            p.name,
            p.status,
            p.location,
            COALESCE(p.budget, 0) AS budget,
            COALESCE(pp.progress_percent, 0) AS progress_percent,
            DATE_FORMAT(pp.created_at, '%b %d, %Y %h:%i %p') AS progress_updated_at
        FROM projects p
        LEFT JOIN (
            SELECT p1.project_id, p1.progress_percent, p1.created_at
            FROM project_progress_updates p1
            INNER JOIN (
                SELECT project_id, MAX(created_at) AS max_created
                FROM project_progress_updates
                GROUP BY project_id
            ) p2 ON p1.project_id = p2.project_id AND p1.created_at = p2.max_created
        ) pp ON pp.project_id = p.id
        ORDER BY p.id DESC
        LIMIT 500";

$rows = [];
$res = $db->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
}

echo json_encode(['success' => true, 'data' => $rows]);

