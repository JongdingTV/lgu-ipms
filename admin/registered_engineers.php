<?php
// Import security functions
require dirname(__DIR__) . '/session-auth.php';
// Database connection
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require __DIR__ . '/engineer-evaluation-service.php';

// Protect page
set_no_cache_headers();
check_auth();
require dirname(__DIR__) . '/includes/rbac.php';
rbac_require_from_matrix('admin.engineers.manage', ['admin','department_admin','super_admin']);
$rbacAction = strtolower(trim((string)($_REQUEST['action'] ?? '')));
rbac_require_action_matrix(
    $rbacAction,
    [
        'delete_contractor' => 'admin.engineers.delete',
        'verify_contractor_document' => 'admin.engineers.manage',
        'update_contractor_approval' => 'admin.engineers.manage',
        'assign_contractor' => 'admin.engineers.assign',
        'unassign_contractor' => 'admin.engineers.assign',
        'evaluate_contractor' => 'admin.engineers.manage',
        'load_contractors' => 'admin.engineers.manage',
        'load_projects' => 'admin.engineers.manage',
        'load_contractor_documents' => 'admin.engineers.manage',
        'load_approval_history' => 'admin.engineers.manage',
        'load_evaluation_overview' => 'admin.engineers.manage',
        'recommended_engineers' => 'admin.engineers.manage',
        'get_assigned_projects' => 'admin.engineers.manage',
    ],
    'admin.engineers.manage'
);
check_suspicious_activity();
$csrfToken = generate_csrf_token();

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

function registered_require_post_csrf_json(): void
{
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh the page and try again.']);
        exit;
    }
}

function registered_projects_has_column(mysqli $db, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'projects'
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function registered_table_exists(mysqli $db, string $table): bool
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
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function registered_table_has_column(mysqli $db, string $table, string $column): bool
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
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
    $stmt->close();
    return $exists;
}

function registered_pick_column(mysqli $db, string $table, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (registered_table_has_column($db, $table, $candidate)) {
            return $candidate;
        }
    }
    return null;
}

function registered_bind_dynamic(mysqli_stmt $stmt, string $types, array $params): bool
{
    if ($types === '' || empty($params)) {
        return true;
    }
    $bind = [$types];
    foreach ($params as $idx => $value) {
        $bind[] = &$params[$idx];
    }
    return (bool)call_user_func_array([$stmt, 'bind_param'], $bind);
}

function registered_get_profile_verification_status(mysqli $db, int $entityId, bool $isEngineer): string
{
    if ($entityId <= 0) {
        return 'Incomplete';
    }

    $entityTable = $isEngineer ? 'engineers' : 'contractors';
    $licenseExpiryCol = $isEngineer
        ? registered_pick_column($db, $entityTable, ['license_expiry_date', 'license_expiration_date'])
        : registered_pick_column($db, $entityTable, ['license_expiration_date', 'license_expiry_date']);

    $licenseExpiry = '';
    if ($licenseExpiryCol) {
        $stmt = $db->prepare("SELECT {$licenseExpiryCol} AS license_expiry FROM {$entityTable} WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $entityId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $licenseExpiry = trim((string)($row['license_expiry'] ?? ''));
            }
            if ($res) {
                $res->free();
            }
            $stmt->close();
        }
    }

    $todayDate = date('Y-m-d');
    if ($licenseExpiry !== '' && strtotime($licenseExpiry) !== false && $licenseExpiry < $todayDate) {
        return 'Expired License';
    }

    $docsTable = $isEngineer ? 'engineer_documents' : 'contractor_documents';
    if (!registered_table_exists($db, $docsTable)) {
        return 'Incomplete';
    }

    $fkCol = $isEngineer ? 'engineer_id' : 'contractor_id';
    $docTypeCol = registered_pick_column($db, $docsTable, ['document_type', 'doc_type', 'type']);
    $verifiedCol = registered_pick_column($db, $docsTable, ['is_verified']);
    if (!$docTypeCol || !$verifiedCol || !registered_table_has_column($db, $docsTable, $fkCol)) {
        return 'Incomplete';
    }

    $licenseDocExpr = $isEngineer
        ? "SUM(CASE WHEN LOWER(COALESCE({$docTypeCol},''))='prc_license' THEN 1 ELSE 0 END)"
        : "SUM(CASE WHEN LOWER(COALESCE({$docTypeCol},''))='license' THEN 1 ELSE 0 END)";
    $resumeDocExpr = $isEngineer
        ? "SUM(CASE WHEN LOWER(COALESCE({$docTypeCol},''))='resume_cv' THEN 1 ELSE 0 END)"
        : "SUM(CASE WHEN LOWER(COALESCE({$docTypeCol},''))='resume' THEN 1 ELSE 0 END)";
    $certificateDocExpr = "SUM(CASE WHEN LOWER(COALESCE({$docTypeCol},''))='certificate' THEN 1 ELSE 0 END)";
    $verifiedExpr = "SUM(CASE WHEN {$verifiedCol} = 1 THEN 1 ELSE 0 END)";

    $stmt = $db->prepare(
        "SELECT
            {$licenseDocExpr} AS license_docs,
            {$resumeDocExpr} AS resume_docs,
            {$certificateDocExpr} AS certificate_docs,
            {$verifiedExpr} AS verified_docs
         FROM {$docsTable}
         WHERE {$fkCol} = ?"
    );
    if (!$stmt) {
        return 'Incomplete';
    }

    $stmt->bind_param('i', $entityId);
    $stmt->execute();
    $res = $stmt->get_result();
    $docsRow = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    $hasAllRequiredDocs =
        (int)($docsRow['license_docs'] ?? 0) > 0 &&
        (int)($docsRow['resume_docs'] ?? 0) > 0 &&
        (int)($docsRow['certificate_docs'] ?? 0) > 0;
    $allVerified = (int)($docsRow['verified_docs'] ?? 0) >= 3;

    return ($hasAllRequiredDocs && $allVerified) ? 'Complete' : 'Incomplete';
}

function ensure_assignment_table(mysqli $db): bool
{
    if (registered_table_exists($db, 'contractor_project_assignments')) {
        return true;
    }

    try {
        $ok = $db->query("CREATE TABLE IF NOT EXISTS contractor_project_assignments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            contractor_id INT NOT NULL,
            project_id INT NOT NULL,
            assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_assignment (contractor_id, project_id)
        )");
        return (bool) $ok;
    } catch (Throwable $e) {
        error_log('ensure_assignment_table error: ' . $e->getMessage());
        return false;
    }
}

// Handle GET request for loading Engineers
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_contractors') {
    header('Content-Type: application/json');

    $entityTable = registered_table_exists($db, 'engineers') ? 'engineers' : 'contractors';
    $isEngineerTable = $entityTable === 'engineers';

    $companyCol = $isEngineerTable
        ? registered_pick_column($db, 'engineers', ['full_name'])
        : registered_pick_column($db, 'contractors', ['company', 'company_name', 'name']);
    $fullNameCol = $isEngineerTable
        ? registered_pick_column($db, 'engineers', ['full_name'])
        : registered_pick_column($db, 'contractors', ['full_name']);
    $licenseCol = $isEngineerTable
        ? registered_pick_column($db, 'engineers', ['prc_license_number', 'license', 'license_number'])
        : registered_pick_column($db, 'contractors', ['license', 'license_number', 'prc_license_no']);
    $licenseExpiryCol = $isEngineerTable
        ? registered_pick_column($db, 'engineers', ['license_expiry_date', 'license_expiration_date'])
        : registered_pick_column($db, 'contractors', ['license_expiration_date']);
    $emailCol = registered_pick_column($db, $entityTable, ['email', 'contact_email']);
    $phoneCol = $isEngineerTable
        ? registered_pick_column($db, 'engineers', ['contact_number', 'phone', 'mobile'])
        : registered_pick_column($db, 'contractors', ['phone', 'contact_number', 'mobile']);
    $statusCol = $isEngineerTable
        ? registered_pick_column($db, 'engineers', ['account_status', 'availability_status', 'status'])
        : registered_pick_column($db, 'contractors', ['status']);
    $ratingCol = registered_pick_column($db, $entityTable, ['rating']);
    $specializationCol = registered_pick_column($db, $entityTable, ['specialization']);
    $experienceCol = $isEngineerTable
        ? registered_pick_column($db, 'engineers', ['years_experience', 'experience'])
        : registered_pick_column($db, 'contractors', ['experience']);
    $complianceCol = registered_pick_column($db, $entityTable, ['compliance_status']);
    $riskLevelCol = registered_pick_column($db, $entityTable, ['risk_level']);
    $riskScoreCol = registered_pick_column($db, $entityTable, ['risk_score']);
    $performanceCol = registered_pick_column($db, $entityTable, ['performance_rating']);
    $reliabilityCol = registered_pick_column($db, $entityTable, ['reliability_score']);
    $approvalCol = registered_pick_column($db, $entityTable, ['approval_status']);
    $verifiedAtCol = registered_pick_column($db, $entityTable, ['verified_at']);
    $approvedAtCol = registered_pick_column($db, $entityTable, ['approved_at']);
    $rejectedAtCol = registered_pick_column($db, $entityTable, ['rejected_at']);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 25);
    if ($perPage <= 0) {
        $perPage = 25;
    }
    $perPage = min($perPage, 100);
    $offset = ($page - 1) * $perPage;
    $q = trim((string)($_GET['q'] ?? ''));
    $statusFilter = trim((string)($_GET['status'] ?? ''));
    $approvalFilter = strtolower(trim((string)($_GET['approval'] ?? '')));

    $selectParts = ['id'];
    $selectParts[] = $companyCol ? "{$companyCol} AS company" : "'' AS company";
    $selectParts[] = $fullNameCol ? "{$fullNameCol} AS full_name" : "'' AS full_name";
    $selectParts[] = $licenseCol ? "{$licenseCol} AS license" : "'' AS license";
    $selectParts[] = $licenseExpiryCol ? "{$licenseExpiryCol} AS license_expiration_date" : "NULL AS license_expiration_date";
    $selectParts[] = $emailCol ? "{$emailCol} AS email" : "'' AS email";
    $selectParts[] = $phoneCol ? "{$phoneCol} AS phone" : "'' AS phone";
    $selectParts[] = $statusCol ? "{$statusCol} AS status" : "'active' AS status";
    $selectParts[] = $ratingCol ? "{$ratingCol} AS rating" : "0 AS rating";
    $selectParts[] = $specializationCol ? "{$specializationCol} AS specialization" : "'' AS specialization";
    $selectParts[] = $experienceCol ? "{$experienceCol} AS experience" : "0 AS experience";
    $selectParts[] = $complianceCol ? "{$complianceCol} AS compliance_status" : "'Compliant' AS compliance_status";
    $selectParts[] = $riskLevelCol ? "{$riskLevelCol} AS risk_level" : "'Medium' AS risk_level";
    $selectParts[] = $riskScoreCol ? "{$riskScoreCol} AS risk_score" : "0 AS risk_score";
    $selectParts[] = $performanceCol ? "{$performanceCol} AS performance_rating" : "0 AS performance_rating";
    $selectParts[] = $reliabilityCol ? "{$reliabilityCol} AS reliability_score" : "0 AS reliability_score";
    $selectParts[] = $approvalCol ? "{$approvalCol} AS approval_status" : "'pending' AS approval_status";
    $selectParts[] = $verifiedAtCol ? "{$verifiedAtCol} AS verified_at" : "NULL AS verified_at";
    $selectParts[] = $approvedAtCol ? "{$approvedAtCol} AS approved_at" : "NULL AS approved_at";
    $selectParts[] = $rejectedAtCol ? "{$rejectedAtCol} AS rejected_at" : "NULL AS rejected_at";

    $where = [];
    $whereTypes = '';
    $whereParams = [];
    if ($q !== '') {
        $searchCols = array_values(array_unique(array_filter([$companyCol, $fullNameCol, $licenseCol, $emailCol, $phoneCol])));
        if (!empty($searchCols)) {
            $parts = [];
            foreach ($searchCols as $col) {
                $parts[] = "{$col} LIKE ?";
                $whereTypes .= 's';
                $whereParams[] = '%' . $q . '%';
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
    }
    if ($statusFilter !== '' && $statusCol) {
        $where[] = "{$statusCol} = ?";
        $whereTypes .= 's';
        $whereParams[] = $statusFilter;
    }
    if ($approvalFilter !== '' && $approvalCol) {
        $where[] = "LOWER(COALESCE({$approvalCol}, 'pending')) = ?";
        $whereTypes .= 's';
        $whereParams[] = $approvalFilter;
    }
    $whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

    $Engineers = [];
    $documentsTable = $isEngineerTable && registered_table_exists($db, 'engineer_documents')
        ? 'engineer_documents'
        : 'contractor_documents';
    $hasDocumentsTable = registered_table_exists($db, $documentsTable);
    $todayDate = date('Y-m-d');
    $docsStmt = null;
    if ($hasDocumentsTable) {
        try {
            $fkCol = $isEngineerTable ? 'engineer_id' : 'contractor_id';
            $licenseDocExpr = $isEngineerTable
                ? "SUM(CASE WHEN LOWER(COALESCE(document_type,''))='prc_license' THEN 1 ELSE 0 END)"
                : "SUM(CASE WHEN LOWER(COALESCE(document_type,''))='license' THEN 1 ELSE 0 END)";
            $resumeDocExpr = $isEngineerTable
                ? "SUM(CASE WHEN LOWER(COALESCE(document_type,''))='resume_cv' THEN 1 ELSE 0 END)"
                : "SUM(CASE WHEN LOWER(COALESCE(document_type,''))='resume' THEN 1 ELSE 0 END)";
            $docsStmt = $db->prepare(
                "SELECT
                    COUNT(*) AS total_docs,
                    {$licenseDocExpr} AS license_docs,
                    {$resumeDocExpr} AS resume_docs,
                    SUM(CASE WHEN LOWER(COALESCE(document_type,''))='certificate' THEN 1 ELSE 0 END) AS certificate_docs,
                    SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) AS verified_docs
                 FROM {$documentsTable}
                 WHERE {$fkCol} = ?"
            );
        } catch (Throwable $e) {
            error_log('registered_engineers docs query prepare failed: ' . $e->getMessage());
            $docsStmt = null;
        }
    }

    try {
        $total = 0;
        $countStmt = $db->prepare("SELECT COUNT(*) AS total FROM {$entityTable}{$whereSql}");
        if ($countStmt) {
            registered_bind_dynamic($countStmt, $whereTypes, $whereParams);
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            if ($countRes && ($countRow = $countRes->fetch_assoc())) {
                $total = (int)($countRow['total'] ?? 0);
            }
            if ($countRes) {
                $countRes->free();
            }
            $countStmt->close();
        }

        $stats = [
            'total' => $total,
            'active' => 0,
            'suspended' => 0,
            'blacklisted' => 0,
            'avg_rating' => 0.0,
            'approval_counts' => [
                'all' => $total,
                'pending' => 0,
                'verified' => 0,
                'approved' => 0,
                'rejected' => 0,
                'suspended' => 0,
            ],
        ];
        $statusExpr = $statusCol ? "LOWER(COALESCE({$statusCol}, ''))" : "''";
        $approvalExpr = $approvalCol ? "LOWER(COALESCE({$approvalCol}, 'pending'))" : "'pending'";
        $ratingExpr = $ratingCol ? "COALESCE({$ratingCol}, 0)" : "0";
        $statsSql = "SELECT
                        SUM(CASE WHEN {$statusExpr} = 'active' THEN 1 ELSE 0 END) AS active_count,
                        SUM(CASE WHEN {$statusExpr} = 'suspended' THEN 1 ELSE 0 END) AS suspended_count,
                        SUM(CASE WHEN {$statusExpr} = 'blacklisted' THEN 1 ELSE 0 END) AS blacklisted_count,
                        AVG(CASE WHEN {$ratingExpr} > 0 THEN {$ratingExpr} ELSE NULL END) AS avg_rating,
                        SUM(CASE WHEN {$approvalExpr} = 'pending' THEN 1 ELSE 0 END) AS approval_pending,
                        SUM(CASE WHEN {$approvalExpr} = 'verified' THEN 1 ELSE 0 END) AS approval_verified,
                        SUM(CASE WHEN {$approvalExpr} = 'approved' THEN 1 ELSE 0 END) AS approval_approved,
                        SUM(CASE WHEN {$approvalExpr} = 'rejected' THEN 1 ELSE 0 END) AS approval_rejected,
                        SUM(CASE WHEN {$approvalExpr} = 'suspended' THEN 1 ELSE 0 END) AS approval_suspended
                     FROM {$entityTable}{$whereSql}";
        $statsStmt = $db->prepare($statsSql);
        if ($statsStmt) {
            registered_bind_dynamic($statsStmt, $whereTypes, $whereParams);
            $statsStmt->execute();
            $statsRes = $statsStmt->get_result();
            if ($statsRes && ($statsRow = $statsRes->fetch_assoc())) {
                $stats['active'] = (int)($statsRow['active_count'] ?? 0);
                $stats['suspended'] = (int)($statsRow['suspended_count'] ?? 0);
                $stats['blacklisted'] = (int)($statsRow['blacklisted_count'] ?? 0);
                $stats['avg_rating'] = (float)($statsRow['avg_rating'] ?? 0);
                $stats['approval_counts']['pending'] = (int)($statsRow['approval_pending'] ?? 0);
                $stats['approval_counts']['verified'] = (int)($statsRow['approval_verified'] ?? 0);
                $stats['approval_counts']['approved'] = (int)($statsRow['approval_approved'] ?? 0);
                $stats['approval_counts']['rejected'] = (int)($statsRow['approval_rejected'] ?? 0);
                $stats['approval_counts']['suspended'] = (int)($statsRow['approval_suspended'] ?? 0);
            }
            if ($statsRes) {
                $statsRes->free();
            }
            $statsStmt->close();
        }

        $sql = "SELECT " . implode(', ', $selectParts) . " FROM {$entityTable}{$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?";
        $result = null;
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $types = $whereTypes . 'ii';
            $params = array_merge($whereParams, [$perPage, $offset]);
            registered_bind_dynamic($stmt, $types, $params);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['display_name'] = trim((string)($row['full_name'] ?? '')) !== ''
                    ? (string) $row['full_name']
                    : ((trim((string)($row['company'] ?? '')) !== '') ? (string) $row['company'] : ('Engineer #' . (int)($row['id'] ?? 0)));

                if ($isEngineerTable) {
                    $row['past_project_count'] = 0;
                    $row['delayed_project_count'] = 0;
                    $row['avg_rating'] = (float) ($row['rating'] ?? 0);
                    $row['completion_rate'] = 0.0;
                    $row['delay_rate'] = 0.0;
                    $row['performance_score'] = (float) ($row['performance_rating'] ?? $row['rating'] ?? 0);
                    $row['reliability_score'] = (float) ($row['reliability_score'] ?? 0);
                    $row['risk_score'] = (float) ($row['risk_score'] ?? 0);
                    $row['risk_level'] = (string) ($row['risk_level'] ?? 'Medium');
                    $row['compliance_status'] = (string) ($row['compliance_status'] ?? 'Compliant');
                } else {
                    $metrics = ee_fetch_metrics($db, (int) ($row['id'] ?? 0));
                    $scores = ee_evaluate_scores($metrics);
                    try {
                        ee_persist_evaluation($db, (int) ($row['id'] ?? 0), $metrics, $scores);
                    } catch (Throwable $e) {
                        error_log('contractor evaluation persist failed: ' . $e->getMessage());
                    }

                    $row['past_project_count'] = (int) ($metrics['total_projects'] ?? 0);
                    $row['delayed_project_count'] = (int) ($metrics['delayed_projects'] ?? 0);
                    $row['avg_rating'] = (float) ($scores['avg_rating'] ?? 0);
                    $row['completion_rate'] = (float) ($scores['completion_rate'] ?? 0);
                    $row['delay_rate'] = (float) ($scores['delay_rate'] ?? 0);
                    $row['performance_score'] = (float) ($scores['performance_score'] ?? 0);
                    $row['reliability_score'] = (float) ($scores['reliability_score'] ?? 0);
                    $row['risk_score'] = (float) ($scores['risk_score'] ?? 0);
                    $row['risk_level'] = (string) ($scores['risk_level'] ?? 'Medium');
                    $row['compliance_status'] = (string) ($scores['compliance_status'] ?? ($row['compliance_status'] ?? 'Compliant'));
                }

                $licenseExpiry = trim((string) ($row['license_expiration_date'] ?? ''));
                $isExpiredLicense = $licenseExpiry !== '' && strtotime($licenseExpiry) !== false && $licenseExpiry < $todayDate;
                $verificationStatus = 'Incomplete';
                if ($isExpiredLicense) {
                    $verificationStatus = 'Expired License';
                } elseif ($docsStmt) {
                    $contractorId = (int) ($row['id'] ?? 0);
                    $docsStmt->bind_param('i', $contractorId);
                    $docsStmt->execute();
                    $docsRes = $docsStmt->get_result();
                    $docsRow = $docsRes ? $docsRes->fetch_assoc() : null;
                    if ($docsRes) {
                        $docsRes->free();
                    }
                    $hasAllRequiredDocs =
                        (int)($docsRow['license_docs'] ?? 0) > 0 &&
                        (int)($docsRow['resume_docs'] ?? 0) > 0 &&
                        (int)($docsRow['certificate_docs'] ?? 0) > 0;
                    $allVerified = (int)($docsRow['verified_docs'] ?? 0) >= 3;
                    if ($hasAllRequiredDocs && $allVerified) {
                        $verificationStatus = 'Complete';
                    }
                }
                $row['verification_status'] = $verificationStatus;
                $Engineers[] = $row;
            }
            $result->free();
        }
        if ($stmt) {
            $stmt->close();
        }
        if ($docsStmt) {
            $docsStmt->close();
        }
    } catch (Throwable $e) {
        error_log("Engineers query error: " . $e->getMessage());
        if ($docsStmt) {
            $docsStmt->close();
        }
        echo json_encode(['success' => false, 'message' => 'Failed to load Engineers data', 'data' => []]);
        exit;
    }

    $totalPages = $perPage > 0 ? (int)ceil(((int)($stats['total'] ?? 0)) / $perPage) : 1;
    echo json_encode([
        'success' => true,
        'data' => $Engineers,
        'meta' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int)($stats['total'] ?? 0),
            'total_pages' => max(1, $totalPages),
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1,
        ],
        'stats' => $stats,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_evaluation_overview') {
    header('Content-Type: application/json');
    try {
        echo json_encode(ee_build_dashboard_lists($db));
    } catch (Throwable $e) {
        error_log('registered_engineers load_evaluation_overview error: ' . $e->getMessage());
        echo json_encode([
            'top_performing' => [],
            'high_risk' => [],
            'most_delayed' => [],
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_contractor_documents') {
    header('Content-Type: application/json');
    $contractorId = isset($_GET['contractor_id']) ? (int) $_GET['contractor_id'] : 0;
    $useEngineerDocs = registered_table_exists($db, 'engineer_documents');
    $docsTable = $useEngineerDocs ? 'engineer_documents' : 'contractor_documents';
    $fkCol = $useEngineerDocs ? 'engineer_id' : 'contractor_id';

    if ($contractorId <= 0 || !registered_table_exists($db, $docsTable)) {
        echo json_encode([]);
        exit;
    }

    $hasExpiresOn = registered_table_has_column($db, $docsTable, 'expires_on');
    $hasVerifiedAt = registered_table_has_column($db, $docsTable, 'verified_at');
    $hasUploadedAt = registered_table_has_column($db, $docsTable, 'uploaded_at');
    $expiresSelect = $hasExpiresOn ? 'expires_on' : 'NULL AS expires_on';
    $verifiedAtSelect = $hasVerifiedAt ? 'verified_at' : 'NULL AS verified_at';
    $uploadedAtSelect = $hasUploadedAt ? 'uploaded_at' : 'NOW() AS uploaded_at';

    $stmt = $db->prepare(
        "SELECT id, {$fkCol} AS contractor_id, document_type, file_path, original_name, mime_type, file_size, {$expiresSelect}, is_verified, {$verifiedAtSelect}, {$uploadedAtSelect}
         FROM {$docsTable}
         WHERE {$fkCol} = ?
         ORDER BY id DESC"
    );
    if (!$stmt) {
        echo json_encode([]);
        exit;
    }
    $stmt->bind_param('i', $contractorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $row['viewer_url'] = 'engineer-document.php?id=' . (int)($row['id'] ?? 0);
        $rows[] = $row;
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();
    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_approval_history') {
    header('Content-Type: application/json');
    $contractorId = isset($_GET['contractor_id']) ? (int) $_GET['contractor_id'] : 0;
    if ($contractorId <= 0 || !registered_table_exists($db, 'approvals')) {
        echo json_encode([]);
        exit;
    }

    $rows = [];
    $hasEmployees = registered_table_exists($db, 'employees');
    $empFirstCol = $hasEmployees ? registered_pick_column($db, 'employees', ['first_name']) : null;
    $empLastCol = $hasEmployees ? registered_pick_column($db, 'employees', ['last_name']) : null;
    $empEmailCol = $hasEmployees ? registered_pick_column($db, 'employees', ['email']) : null;

    $reviewerNameExpr = "CONCAT('Employee #', a.reviewer_id)";
    if ($hasEmployees && $empFirstCol && $empLastCol && $empEmailCol) {
        $reviewerNameExpr = "COALESCE(NULLIF(CONCAT(COALESCE(e.{$empFirstCol},''), ' ', COALESCE(e.{$empLastCol},'')), ' '), e.{$empEmailCol}, CONCAT('Employee #', a.reviewer_id))";
    } elseif ($hasEmployees && $empEmailCol) {
        $reviewerNameExpr = "COALESCE(e.{$empEmailCol}, CONCAT('Employee #', a.reviewer_id))";
    }

    try {
        if ($hasEmployees && ($empFirstCol || $empEmailCol)) {
            $stmt = $db->prepare(
                "SELECT
                    a.id,
                    a.status,
                    a.reviewer_id,
                    a.reviewer_role,
                    a.notes,
                    COALESCE(a.reviewed_at, a.created_at) AS changed_at,
                    {$reviewerNameExpr} AS reviewer_name
                 FROM approvals a
                 LEFT JOIN employees e ON e.id = a.reviewer_id
                 WHERE a.entity_type IN ('engineer','contractor')
                   AND a.entity_id = ?
                 ORDER BY COALESCE(a.reviewed_at, a.created_at) DESC, a.id DESC
                 LIMIT 50"
            );
        } else {
            $stmt = $db->prepare(
                "SELECT
                    a.id,
                    a.status,
                    a.reviewer_id,
                    a.reviewer_role,
                    a.notes,
                    COALESCE(a.reviewed_at, a.created_at) AS changed_at,
                    CONCAT('Employee #', COALESCE(a.reviewer_id, 0)) AS reviewer_name
                 FROM approvals a
                 WHERE a.entity_type IN ('engineer','contractor')
                   AND a.entity_id = ?
                 ORDER BY COALESCE(a.reviewed_at, a.created_at) DESC, a.id DESC
                 LIMIT 50"
            );
        }
        if (!$stmt) {
            echo json_encode([]);
            exit;
        }
        $stmt->bind_param('i', $contractorId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $rows[] = $row;
        }
        if ($res) {
            $res->free();
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('registered_engineers load_approval_history error: ' . $e->getMessage());
        echo json_encode([]);
        exit;
    }

    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'recommended_engineers') {
    header('Content-Type: application/json');
    if (!registered_table_exists($db, 'contractors')) {
        echo json_encode([]);
        exit;
    }

    $projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
    $sector = '';
    if ($projectId > 0) {
        $stmt = $db->prepare("SELECT COALESCE(sector,'') AS sector FROM projects WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $projectId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $sector = (string) ($row['sector'] ?? '');
            }
            if ($res) {
                $res->free();
            }
            $stmt->close();
        }
    }

    $nameExpr = registered_table_has_column($db, 'contractors', 'full_name')
        ? "COALESCE(NULLIF(full_name,''), company, owner, CONCAT('Engineer #', id))"
        : "COALESCE(NULLIF(company,''), owner, CONCAT('Engineer #', id))";
    $specializationCol = registered_table_has_column($db, 'contractors', 'specialization') ? 'specialization' : "''";
    $performanceCol = registered_table_has_column($db, 'contractors', 'performance_rating') ? 'performance_rating' : (registered_table_has_column($db, 'contractors', 'rating') ? 'rating' : '0');
    $reliabilityCol = registered_table_has_column($db, 'contractors', 'reliability_score') ? 'reliability_score' : '0';
    $riskLevelCol = registered_table_has_column($db, 'contractors', 'risk_level') ? 'risk_level' : "'Medium'";
    $riskScoreCol = registered_table_has_column($db, 'contractors', 'risk_score') ? 'risk_score' : '0';

    $query = "SELECT id, {$nameExpr} AS display_name, {$specializationCol} AS specialization, {$performanceCol} AS performance_rating, {$reliabilityCol} AS reliability_score, {$riskLevelCol} AS risk_level, {$riskScoreCol} AS risk_score
              FROM contractors";
    $types = '';
    $params = [];
    if ($sector !== '') {
        $query .= " WHERE LOWER(COALESCE({$specializationCol},'')) LIKE ?";
        $types = 's';
        $params[] = '%' . strtolower($sector) . '%';
    }
    $query .= " ORDER BY (LOWER(COALESCE(risk_level,''))='low') DESC, performance_rating DESC, reliability_score DESC LIMIT 8";

    try {
        $stmt = $db->prepare($query);
    } catch (Throwable $e) {
        error_log('registered_engineers recommended_engineers prepare error: ' . $e->getMessage());
        echo json_encode([]);
        exit;
    }
    if (!$stmt) {
        echo json_encode([]);
        exit;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();

    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_contractor_document') {
    registered_require_post_csrf_json();
    header('Content-Type: application/json');
    $documentId = isset($_POST['document_id']) ? (int) $_POST['document_id'] : 0;
    $isVerified = isset($_POST['is_verified']) ? (int) $_POST['is_verified'] : 1;
    $isVerified = $isVerified === 1 ? 1 : 0;

    if ($documentId <= 0 || !registered_table_exists($db, 'contractor_documents')) {
        echo json_encode(['success' => false, 'message' => 'Invalid document or table missing.']);
        exit;
    }

    $employeeId = isset($_SESSION['employee_id']) ? (int) $_SESSION['employee_id'] : null;
    $stmt = $db->prepare(
        "UPDATE contractor_documents
         SET is_verified = ?, verified_by = ?, verified_at = CASE WHEN ? = 1 THEN NOW() ELSE NULL END
         WHERE id = ?"
    );
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare verification update.']);
        exit;
    }
    $stmt->bind_param('iiii', $isVerified, $employeeId, $isVerified, $documentId);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && function_exists('rbac_audit')) {
        rbac_audit('engineer.document_verify', 'engineer_document', $documentId, [
            'is_verified' => $isVerified
        ]);
    }

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? ($isVerified ? 'Document verified.' : 'Document marked as unverified.') : 'Unable to update document status.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_contractor_approval') {
    registered_require_post_csrf_json();
    header('Content-Type: application/json');
    $contractorId = isset($_POST['contractor_id']) ? (int) $_POST['contractor_id'] : 0;
    $status = strtolower(trim((string) ($_POST['status'] ?? '')));
    $note = trim((string)($_POST['note'] ?? ''));
    $allowed = ['pending', 'verified', 'approved', 'rejected', 'suspended', 'blacklisted', 'inactive'];
    if ($contractorId <= 0 || !in_array($status, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid approval request.']);
        exit;
    }

    $entityTable = registered_table_exists($db, 'engineers') ? 'engineers' : 'contractors';
    $isEngineerTable = $entityTable === 'engineers';
    if (!registered_table_has_column($db, $entityTable, 'approval_status')) {
        echo json_encode(['success' => false, 'message' => 'Approval fields not available. Run migration.']);
        exit;
    }

    $currentStatus = 'pending';
    $statusStmt = $db->prepare("SELECT approval_status FROM {$entityTable} WHERE id = ? LIMIT 1");
    if ($statusStmt) {
        $statusStmt->bind_param('i', $contractorId);
        $statusStmt->execute();
        $statusRes = $statusStmt->get_result();
        if ($statusRes && ($statusRow = $statusRes->fetch_assoc())) {
            $currentStatus = strtolower((string)($statusRow['approval_status'] ?? 'pending'));
        }
        if ($statusRes) {
            $statusRes->free();
        }
        $statusStmt->close();
    }

    if ($status === 'approved') {
        if (!in_array($currentStatus, ['verified', 'approved'], true)) {
            echo json_encode(['success' => false, 'message' => 'Please verify this engineer first before approving.']);
            exit;
        }
    }

    if (in_array($status, ['verified', 'approved'], true)) {
        $verificationStatus = registered_get_profile_verification_status($db, $contractorId, $isEngineerTable);
        if ($verificationStatus !== 'Complete') {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot set status to ' . $status . '. Requirements are incomplete (' . $verificationStatus . ').'
            ]);
            exit;
        }
    }

    $verifiedAt = "CASE WHEN ? = 'verified' THEN NOW() ELSE verified_at END";
    $approvedAt = "CASE WHEN ? = 'approved' THEN NOW() ELSE approved_at END";
    $rejectedAt = "CASE WHEN ? = 'rejected' THEN NOW() ELSE rejected_at END";
    $stmt = $db->prepare(
        "UPDATE {$entityTable}
         SET approval_status = ?, 
             verified_at = {$verifiedAt},
             approved_at = {$approvedAt},
             rejected_at = {$rejectedAt}
         WHERE id = ?"
    );
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare approval update.']);
        exit;
    }
    $stmt->bind_param('ssssi', $status, $status, $status, $status, $contractorId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok && registered_table_exists($db, 'approvals')) {
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        $role = strtolower((string)($_SESSION['employee_role'] ?? ''));
        $auditNote = $note !== '' ? $note : ('Updated approval status to ' . $status);
        $aStmt = $db->prepare(
            "INSERT INTO approvals (entity_type, entity_id, status, reviewer_id, reviewer_role, notes, reviewed_at)
             VALUES ('engineer', ?, ?, ?, ?, ?, NOW())"
        );
        if ($aStmt) {
            $aStmt->bind_param('isiss', $contractorId, $status, $employeeId, $role, $auditNote);
            $aStmt->execute();
            $aStmt->close();
        }
    }

    if ($ok && function_exists('rbac_audit')) {
        rbac_audit('engineer.approval_update', 'engineer', $contractorId, ['status' => $status]);
    }

    echo json_encode(['success' => (bool) $ok]);
    exit;
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');

    $hasCreatedAt = registered_projects_has_column($db, 'created_at');
    $hasPriorityPercent = registered_projects_has_column($db, 'priority_percent');
    $hasExactAddress = registered_projects_has_column($db, 'location');
    $hasType = registered_projects_has_column($db, 'type');
    $hasProvince = registered_projects_has_column($db, 'province');
    $hasBarangay = registered_projects_has_column($db, 'barangay');
    $hasBudget = registered_projects_has_column($db, 'budget');
    $hasStartDate = registered_projects_has_column($db, 'start_date');
    $hasEndDate = registered_projects_has_column($db, 'end_date');
    $hasDurationMonths = registered_projects_has_column($db, 'duration_months');
    $hasLicenseDoc = registered_projects_has_column($db, 'engineer_license_doc');
    $hasCertDoc = registered_projects_has_column($db, 'engineer_certification_doc');
    $hasCredDoc = registered_projects_has_column($db, 'engineer_credentials_doc');
    $hasTaskTable = registered_table_exists($db, 'project_tasks');
    $hasMilestoneTable = registered_table_exists($db, 'project_milestones');
    $projectsLimit = (int)($_GET['limit'] ?? 200);
    if ($projectsLimit <= 0) {
        $projectsLimit = 200;
    }
    $projectsLimit = min($projectsLimit, 500);
    $orderBy = $hasCreatedAt ? 'created_at DESC' : 'id DESC';
    $prioritySelect = $hasPriorityPercent ? 'priority_percent' : '0 AS priority_percent';
    $projectSelect = [
        'id',
        'code',
        'name',
        $hasType ? 'type' : "'' AS type",
        'sector',
        'status',
        'priority',
        $prioritySelect,
        'location',
        $hasProvince ? 'province' : "'' AS province",
        $hasBarangay ? 'barangay' : "'' AS barangay",
        $hasBudget ? 'budget' : '0 AS budget',
        $hasStartDate ? 'start_date' : 'NULL AS start_date',
        $hasEndDate ? 'end_date' : 'NULL AS end_date',
        $hasDurationMonths ? 'duration_months' : 'NULL AS duration_months',
        $hasLicenseDoc ? 'engineer_license_doc' : "'' AS engineer_license_doc",
        $hasCertDoc ? 'engineer_certification_doc' : "'' AS engineer_certification_doc",
        $hasCredDoc ? 'engineer_credentials_doc' : "'' AS engineer_credentials_doc"
    ];
    try {
        $result = $db->query("SELECT " . implode(', ', $projectSelect) . " FROM projects ORDER BY {$orderBy} LIMIT {$projectsLimit}");
    } catch (Throwable $e) {
        error_log('registered_contractors load_projects query error: ' . $e->getMessage());
        echo json_encode([]);
        exit;
    }
    $projects = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['allocated_budget'] = (float) ($row['budget'] ?? 0);
            $row['spent_budget'] = 0.0;
            $row['remaining_budget'] = max(0, $row['allocated_budget'] - $row['spent_budget']);
            $row['location_exact'] = $hasExactAddress ? (string) ($row['location'] ?? '') : '';
            $row['full_address'] = trim(((string) ($row['province'] ?? '')) . ' / ' . ((string) ($row['barangay'] ?? '')) . ' / ' . ((string) ($row['location'] ?? '')));
            $row['task_summary'] = ['total' => 0, 'completed' => 0];
            $row['milestone_summary'] = ['total' => 0, 'completed' => 0];
            if ($hasTaskTable) {
                try {
                    $taskRes = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed FROM project_tasks WHERE project_id = " . (int)$row['id']);
                    if ($taskRes) {
                        $taskRow = $taskRes->fetch_assoc();
                        $taskRes->free();
                        $row['task_summary'] = ['total' => (int)($taskRow['total'] ?? 0), 'completed' => (int)($taskRow['completed'] ?? 0)];
                    }
                } catch (Throwable $e) {
                    error_log('registered_engineers project_tasks summary error: ' . $e->getMessage());
                }
            }
            if ($hasMilestoneTable) {
                try {
                    $mileRes = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed FROM project_milestones WHERE project_id = " . (int)$row['id']);
                    if ($mileRes) {
                        $mileRow = $mileRes->fetch_assoc();
                        $mileRes->free();
                        $row['milestone_summary'] = ['total' => (int)($mileRow['total'] ?? 0), 'completed' => (int)($mileRow['completed'] ?? 0)];
                    }
                } catch (Throwable $e) {
                    error_log('registered_engineers project_milestones summary error: ' . $e->getMessage());
                }
            }
            $row['documents'] = array_values(array_filter([
                $row['engineer_license_doc'] ?? '',
                $row['engineer_certification_doc'] ?? '',
                $row['engineer_credentials_doc'] ?? ''
            ]));
            $projects[] = $row;
        }
        $result->free();
    }
    
    echo json_encode($projects);
    exit;
}

// Handle POST request for assigning Engineer to project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_contractor') {
    registered_require_post_csrf_json();
    header('Content-Type: application/json');
    
    $contractor_id = isset($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    
    if ($contractor_id > 0 && $project_id > 0) {
        if (!ensure_assignment_table($db)) {
            echo json_encode(['success' => false, 'message' => 'Assignment table is missing and cannot be created.']);
            exit;
        }
        
        $stmt = $db->prepare("INSERT INTO contractor_project_assignments (contractor_id, project_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $contractor_id, $project_id);
        
        if ($stmt->execute()) {
            if (function_exists('rbac_audit')) {
                rbac_audit('engineer.assign_project', 'engineer', $contractor_id, [
                    'project_id' => $project_id
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Engineer assigned to project successfully']);
        } else {
            if (strpos($stmt->error, 'Duplicate') !== false) {
                echo json_encode(['success' => false, 'message' => 'This Engineer is already assigned to this project']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign Engineer: ' . $stmt->error]);
            }
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Engineer or project ID']);
    }
    exit;
}

// Handle POST request for removing Engineer from project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unassign_contractor') {
    registered_require_post_csrf_json();
    header('Content-Type: application/json');
    
    $contractor_id = isset($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : 0;
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
    
    if ($contractor_id > 0 && $project_id > 0) {
        if (!registered_table_exists($db, 'contractor_project_assignments')) {
            echo json_encode(['success' => true, 'message' => 'No assignment found']);
            exit;
        }
        $stmt = $db->prepare("DELETE FROM contractor_project_assignments WHERE contractor_id=? AND project_id=?");
        $stmt->bind_param("ii", $contractor_id, $project_id);
        
        if ($stmt->execute()) {
            if (function_exists('rbac_audit')) {
                rbac_audit('engineer.unassign_project', 'engineer', $contractor_id, [
                    'project_id' => $project_id
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Engineer unassigned from project']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to unassign Engineer']);
        }
        $stmt->close();
    }
    exit;
}

// Handle POST request for deleting Engineer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_contractor') {
    registered_require_post_csrf_json();
    header('Content-Type: application/json');

    $contractor_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($contractor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Engineer ID']);
        exit;
    }

    $entityTable = registered_table_exists($db, 'engineers') ? 'engineers' : 'contractors';
    $stmt = $db->prepare("DELETE FROM {$entityTable} WHERE id = ?");
    $stmt->bind_param("i", $contractor_id);

    if ($stmt->execute()) {
        if (function_exists('rbac_audit')) {
            rbac_audit('engineer.delete', 'engineer', $contractor_id, []);
        }
        echo json_encode(['success' => true, 'message' => 'Engineer deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete Engineer']);
    }

    $stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'evaluate_contractor') {
    registered_require_post_csrf_json();
    header('Content-Type: application/json');
    $contractorId = isset($_POST['contractor_id']) ? (int) $_POST['contractor_id'] : 0;
    if ($contractorId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Engineer ID']);
        exit;
    }

    $metrics = ee_fetch_metrics($db, $contractorId);
    $scores = ee_evaluate_scores($metrics);
    try {
        ee_persist_evaluation($db, $contractorId, $metrics, $scores);
    } catch (Throwable $e) {
        error_log('evaluate_contractor persist error: ' . $e->getMessage());
    }
    if (function_exists('rbac_audit')) {
        rbac_audit('engineer.evaluate', 'engineer', $contractorId, [
            'performance_score' => (float)($scores['performance_score'] ?? 0),
            'reliability_score' => (float)($scores['reliability_score'] ?? 0),
            'risk_level' => (string)($scores['risk_level'] ?? 'unknown')
        ]);
    }

    echo json_encode([
        'success' => true,
        'contractor_id' => $contractorId,
        'metrics' => $metrics,
        'scores' => $scores,
    ]);
    exit;
}

// Handle GET request for loading assigned projects for a Engineer
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_assigned_projects') {
    header('Content-Type: application/json');
    
    $contractor_id = isset($_GET['contractor_id']) ? (int)$_GET['contractor_id'] : 0;
    
    if ($contractor_id > 0) {
        if (!registered_table_exists($db, 'contractor_project_assignments')) {
            echo json_encode([]);
            exit;
        }

        $hasPriorityPercent = registered_projects_has_column($db, 'priority_percent');
        $hasType = registered_projects_has_column($db, 'type');
        $hasProvince = registered_projects_has_column($db, 'province');
        $hasBarangay = registered_projects_has_column($db, 'barangay');
        $hasBudget = registered_projects_has_column($db, 'budget');
        $hasStartDate = registered_projects_has_column($db, 'start_date');
        $hasEndDate = registered_projects_has_column($db, 'end_date');
        $hasDurationMonths = registered_projects_has_column($db, 'duration_months');
        $hasLicenseDoc = registered_projects_has_column($db, 'engineer_license_doc');
        $hasCertDoc = registered_projects_has_column($db, 'engineer_certification_doc');
        $hasCredDoc = registered_projects_has_column($db, 'engineer_credentials_doc');
        $prioritySelect = $hasPriorityPercent ? 'p.priority_percent' : '0 AS priority_percent';
        try {
            $stmt = $db->prepare("SELECT p.id, p.code, p.name, " . ($hasType ? "p.type" : "'' AS type") . ", p.sector, p.status, p.priority, " . $prioritySelect . ", p.location, " . ($hasProvince ? "p.province" : "'' AS province") . ", " . ($hasBarangay ? "p.barangay" : "'' AS barangay") . ", " . ($hasBudget ? "p.budget" : "0 AS budget") . ", " . ($hasStartDate ? "p.start_date" : "NULL AS start_date") . ", " . ($hasEndDate ? "p.end_date" : "NULL AS end_date") . ", " . ($hasDurationMonths ? "p.duration_months" : "NULL AS duration_months") . ", " . ($hasLicenseDoc ? "p.engineer_license_doc" : "'' AS engineer_license_doc") . ", " . ($hasCertDoc ? "p.engineer_certification_doc" : "'' AS engineer_certification_doc") . ", " . ($hasCredDoc ? "p.engineer_credentials_doc" : "'' AS engineer_credentials_doc") . " FROM projects p 
                               INNER JOIN contractor_project_assignments cpa ON p.id = cpa.project_id 
                               WHERE cpa.contractor_id = ?");
        } catch (Throwable $e) {
            error_log('registered_contractors get_assigned_projects prepare error: ' . $e->getMessage());
            echo json_encode([]);
            exit;
        }
        if (!$stmt) {
            echo json_encode([]);
            exit;
        }
        $stmt->bind_param("i", $contractor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $projects = [];
        
        $hasTaskTable = registered_table_exists($db, 'project_tasks');
        $hasMilestoneTable = registered_table_exists($db, 'project_milestones');
        while ($row = $result->fetch_assoc()) {
            $row['allocated_budget'] = (float) ($row['budget'] ?? 0);
            $row['spent_budget'] = 0.0;
            $row['remaining_budget'] = max(0, $row['allocated_budget'] - $row['spent_budget']);
            $row['location_exact'] = (string) ($row['location'] ?? '');
            $row['full_address'] = trim(((string) ($row['province'] ?? '')) . ' / ' . ((string) ($row['barangay'] ?? '')) . ' / ' . ((string) ($row['location'] ?? '')));
            $row['task_summary'] = ['total' => 0, 'completed' => 0];
            $row['milestone_summary'] = ['total' => 0, 'completed' => 0];
            if ($hasTaskTable) {
                $taskRes = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed FROM project_tasks WHERE project_id = " . (int)$row['id']);
                if ($taskRes) {
                    $taskRow = $taskRes->fetch_assoc();
                    $taskRes->free();
                    $row['task_summary'] = ['total' => (int)($taskRow['total'] ?? 0), 'completed' => (int)($taskRow['completed'] ?? 0)];
                }
            }
            if ($hasMilestoneTable) {
                $mileRes = $db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN LOWER(COALESCE(status,'')) IN ('completed','done') THEN 1 ELSE 0 END) AS completed FROM project_milestones WHERE project_id = " . (int)$row['id']);
                if ($mileRes) {
                    $mileRow = $mileRes->fetch_assoc();
                    $mileRes->free();
                    $row['milestone_summary'] = ['total' => (int)($mileRow['total'] ?? 0), 'completed' => (int)($mileRow['completed'] ?? 0)];
                }
            }
            $row['documents'] = array_values(array_filter([
                $row['engineer_license_doc'] ?? '',
                $row['engineer_certification_doc'] ?? '',
                $row['engineer_credentials_doc'] ?? ''
            ]));
            $projects[] = $row;
        }
        
        echo json_encode($projects);
        $stmt->close();
    } else {
        echo json_encode([]);
    }
    exit;
}

$db->close();
?>
<!doctype html>
<html>
<head>
    
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Registered Engineers - LGU IPMS</title>
    <link rel="icon" type="image/png" href="/assets/images/icons/ipms-icon2.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design System & Components CSS -->
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-unified.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-unified.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin-component-overrides.css">
    <link rel="stylesheet" href="../assets/css/table-redesign-base.css">
    <link rel="stylesheet" href="../assets/css/admin-enterprise.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin-enterprise.css'); ?>">
</head>
<body>
    <input type="hidden" id="registeredEngineersCsrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <!-- Sidebar Toggle Button (Floating) -->
    <div class="sidebar-toggle-wrapper">
        <button class="sidebar-toggle-btn" title="Show Sidebar (Ctrl+S)" aria-label="Show Sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>
    <header class="nav" id="navbar">
        <!-- Navbar menu icon - shows when sidebar is hidden -->
        <button class="navbar-menu-icon" id="navbarMenuIcon" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="nav-logo">
            <img src="../assets/images/icons/ipms-icon.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
                <div class="nav-links">
            <a href="dashboard.php"><img src="../assets/images/admin/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <div class="nav-item-group">
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle"><img src="../assets/images/admin/list.png" class="nav-icon">Project Registration<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item"><span class="submenu-icon">&#10133;</span><span>New Project</span></a>
                    <a href="registered_projects.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Projects</span></a>
                </div>
            </div>
            <a href="progress_monitoring.php"><img src="../assets/images/admin/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="budget_resources.php"><img src="../assets/images/admin/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="tasks_milestones.php"><img src="../assets/images/admin/production.png" class="nav-icon">Task & Milestone</a>
            <div class="nav-item-group">
                <a href="registered_engineers.php" class="nav-main-item active" id="contractorsToggle"><img src="../assets/images/admin/contractors.png" class="nav-icon">Engineers/Contractors<span class="dropdown-arrow">&#9662;</span></a>
                <div class="nav-submenu show" id="contractorsSubmenu">
                    <a href="registered_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Engineers</span></a>
                    <a href="registered_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Registered Contractors</span></a>
                    <a href="applications_engineers.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Engineer Applications</span></a>
                    <a href="applications_contractors.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Contractor Applications</span></a>
                    <a href="verified_users.php" class="nav-submenu-item"><span class="submenu-icon">&#10003;</span><span>Verified Users</span></a>
                    <a href="rejected_users.php" class="nav-submenu-item"><span class="submenu-icon">&#128203;</span><span>Rejected / Suspended</span></a>
                </div>
            </div>
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
            <a href="citizen-verification.php" class="nav-main-item"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
        </div>
        <div class="nav-divider"></div>
        <div class="nav-action-footer">
            <a href="/admin/logout.php" class="btn-logout nav-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout</span>
            </a>
        </div>
        <a href="#" id="toggleSidebar" class="sidebar-toggle-btn" title="Toggle sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </a>
    </header>

    <!-- Toggle button to show sidebar -->
    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow" title="Show sidebar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Registered Engineers</h1>
            <p>Review Engineer records, assign projects, and monitor accreditation status.</p>
        </div>

        <div class="recent-projects contractor-page contractor-registry-shell">
            <div class="contractor-page-head">
                <div>
                    <h3>Engineer Registry</h3>
                    <p>Search, filter, assign, and maintain active Engineers in one workspace.</p>
                </div>
                <div class="contractor-head-tools">
                    <span id="contractorLastSync" class="contractor-last-sync">Last synced: --</span>
                    <button type="button" id="refreshContractorsBtn" class="btn-contractor-secondary">Refresh</button>
                    <button type="button" id="exportContractorsCsvBtn" class="btn-contractor-primary">Export CSV</button>
                </div>
            </div>

            <div class="contractors-filter contractor-toolbar">
                <input
                    type="search"
                    id="searchContractors"
                    placeholder="Search by company, license, email, or phone"
                >
                <select id="filterStatus">
                    <option value="">All Status</option>
                    <option>Active</option>
                    <option>Suspended</option>
                    <option>Blacklisted</option>
                </select>
                <select id="filterApproval">
                    <option value="">All Approvals</option>
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="suspended">Suspended</option>
                </select>
                <div id="contractorsCount" class="contractor-count-pill">0 Engineers</div>
            </div>

            <div class="approval-queue" id="approvalQueue">
                <div class="approval-queue-head">
                    <h4>Approval Queue</h4>
                    <small>Quickly review and filter engineers by workflow status.</small>
                </div>
                <div class="approval-queue-grid">
                    <button type="button" class="approval-queue-item active" data-approval-filter="">
                        <span>All</span>
                        <strong id="approvalQueueAll">0</strong>
                    </button>
                    <button type="button" class="approval-queue-item pending" data-approval-filter="pending">
                        <span>Pending</span>
                        <strong id="approvalQueuePending">0</strong>
                    </button>
                    <button type="button" class="approval-queue-item verified" data-approval-filter="verified">
                        <span>Verified</span>
                        <strong id="approvalQueueVerified">0</strong>
                    </button>
                    <button type="button" class="approval-queue-item approved" data-approval-filter="approved">
                        <span>Approved</span>
                        <strong id="approvalQueueApproved">0</strong>
                    </button>
                    <button type="button" class="approval-queue-item rejected" data-approval-filter="rejected">
                        <span>Rejected</span>
                        <strong id="approvalQueueRejected">0</strong>
                    </button>
                    <button type="button" class="approval-queue-item suspended" data-approval-filter="suspended">
                        <span>Suspended</span>
                        <strong id="approvalQueueSuspended">0</strong>
                    </button>
                </div>
            </div>

            <div class="contractor-stats-grid">
                <article class="contractor-stat-card">
                    <span>Total Engineers</span>
                    <strong id="contractorStatTotal">0</strong>
                </article>
                <article class="contractor-stat-card is-active">
                    <span>Active</span>
                    <strong id="contractorStatActive">0</strong>
                </article>
                <article class="contractor-stat-card is-suspended">
                    <span>Suspended</span>
                    <strong id="contractorStatSuspended">0</strong>
                </article>
                <article class="contractor-stat-card is-blacklisted">
                    <span>Blacklisted</span>
                    <strong id="contractorStatBlacklisted">0</strong>
                </article>
                <article class="contractor-stat-card is-rating">
                    <span>Average Rating</span>
                    <strong id="contractorStatAvgRating">0.0</strong>
                </article>
            </div>

            <div class="engineer-eval-grid">
                <article class="engineer-eval-card">
                    <h4>Top Performing Engineers</h4>
                    <ul id="topPerformingList" class="engineer-eval-list">
                        <li>No data yet.</li>
                    </ul>
                </article>
                <article class="engineer-eval-card">
                    <h4>High Risk Engineers</h4>
                    <ul id="highRiskList" class="engineer-eval-list">
                        <li>No data yet.</li>
                    </ul>
                </article>
                <article class="engineer-eval-card">
                    <h4>Most Delayed Engineers</h4>
                    <ul id="mostDelayedList" class="engineer-eval-list">
                        <li>No data yet.</li>
                    </ul>
                </article>
            </div>

            <div class="engineer-recommend-card">
                <h4>Recommended Engineer for Project</h4>
                <div class="engineer-recommend-controls">
                    <select id="recommendProjectSelect">
                        <option value="">Select a project</option>
                    </select>
                    <button type="button" id="recommendEngineerBtn" class="btn-contractor-secondary">Find Recommendation</button>
                </div>
                <ul id="recommendedEngineersList" class="engineer-eval-list">
                    <li>Select a project to load recommendations.</li>
                </ul>
            </div>

            <div class="contractors-section">
                <div id="formMessage" class="contractor-form-message" role="status" aria-live="polite"></div>
                
                <div class="table-wrap">
                    <table id="contractorsTable" class="table">
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>License Number</th>
                                <th>Contact Email</th>
                                <th>Status</th>
                                <th>Approval</th>
                                <th>Verification</th>
                                <th>Performance / Risk</th>
                                <th>Projects Assigned</th>
                                <th>Documents</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="projects-section contractor-project-bank" id="available-projects">
                <h3>Available Projects</h3>
                <p class="contractor-subtext">Projects listed below are available for assignment to selected engineers.</p>
                <div class="table-wrap">
                    <table id="projectsTable" class="table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Sector</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

<!-- Assignment Modal -->
    <div id="assignmentModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="assignmentTitle">
        <div class="contractor-modal-panel">
            <input type="hidden" id="assignContractorId" value="">
            <h2 id="assignmentTitle"></h2>
            <div id="projectsList" class="contractor-modal-list"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="assignCancelBtn" class="btn-contractor-secondary">Cancel</button>
                <button type="button" id="saveAssignments" class="btn-contractor-primary">Save Assignments</button>
            </div>
        </div>
    </div>

    <!-- Projects View Modal -->
    <div id="projectsViewModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="projectsViewTitle">
        <div class="contractor-modal-panel">
            <h2 id="projectsViewTitle"></h2>
            <div id="projectsViewList" class="contractor-modal-list"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="projectsCloseBtn" class="btn-contractor-primary">Close</button>
            </div>
        </div>
    </div>

    <div id="contractorDocsModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="contractorDocsTitle">
        <div class="contractor-modal-panel">
            <h2 id="contractorDocsTitle">Engineer Documents</h2>
            <div id="contractorDocsList" class="contractor-modal-list"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="contractorDocsCloseBtn" class="btn-contractor-primary">Close</button>
            </div>
        </div>
    </div>

    <div id="contractorStatusModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="contractorStatusTitle">
        <div class="contractor-modal-panel contractor-status-panel">
            <input type="hidden" id="statusContractorId" value="">
            <h2 id="contractorStatusTitle">Update Engineer Status</h2>
            <div class="contractor-modal-list">
                <label class="contractor-field-label" for="statusSelect">Approval Status</label>
                <select id="statusSelect" class="contractor-field-select">
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="suspended">Suspended</option>
                </select>
                <label class="contractor-field-label" for="statusNote">Note (optional)</label>
                <textarea id="statusNote" class="contractor-field-textarea" rows="3" placeholder="Add reason or remarks for this status change"></textarea>
            </div>
            <div class="contractor-modal-actions">
                <button type="button" id="statusCancelBtn" class="btn-contractor-secondary">Cancel</button>
                <button type="button" id="statusSaveBtn" class="btn-contractor-primary">Save Status</button>
            </div>
        </div>
    </div>

    <div id="approvalHistoryModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="approvalHistoryTitle">
        <div class="contractor-modal-panel contractor-history-panel">
            <h2 id="approvalHistoryTitle">Approval Timeline</h2>
            <div class="approval-history-filters" id="approvalHistoryFilters">
                <button type="button" class="approval-history-filter active" data-history-window="all">All</button>
                <button type="button" class="approval-history-filter" data-history-window="7">Last 7 days</button>
                <button type="button" class="approval-history-filter" data-history-window="30">Last 30 days</button>
                <button type="button" class="btn-contractor-secondary approval-history-export" id="approvalHistoryExportBtn">Export CSV</button>
            </div>
            <div id="approvalHistoryList" class="contractor-modal-list"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="approvalHistoryCloseBtn" class="btn-contractor-primary">Close</button>
            </div>
        </div>
    </div>

    <div id="contractorDeleteModal" class="contractor-modal" role="dialog" aria-modal="true" aria-labelledby="contractorDeleteTitle">
        <div class="contractor-modal-panel contractor-delete-panel">
            <div class="contractor-delete-head">
                <span class="contractor-delete-icon">!</span>
                <h2 id="contractorDeleteTitle">Delete Engineer?</h2>
            </div>
            <p class="contractor-delete-message">This Engineer and all related assignment records will be permanently deleted.</p>
            <div id="contractorDeleteName" class="contractor-delete-name"></div>
            <div class="contractor-modal-actions">
                <button type="button" id="contractorDeleteCancel" class="btn-contractor-secondary">Cancel</button>
                <button type="button" id="contractorDeleteConfirm" class="btn-contractor-danger">Delete Permanently</button>
            </div>
        </div>
    </div>
    <script src="../assets/js/admin.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin.js'); ?>"></script>
    
    <script src="../assets/js/admin-enterprise.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-enterprise.js'); ?>"></script>
    <script src="../assets/js/admin-registered-engineers.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/admin-registered-engineers.js'); ?>"></script>
</body>
</html>




























