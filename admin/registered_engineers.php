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
check_suspicious_activity();

if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
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
    
    $companyCol = registered_pick_column($db, 'contractors', ['company', 'company_name', 'name']);
    $fullNameCol = registered_pick_column($db, 'contractors', ['full_name']);
    $licenseCol = registered_pick_column($db, 'contractors', ['license', 'license_number', 'prc_license_no']);
    $licenseExpiryCol = registered_pick_column($db, 'contractors', ['license_expiration_date']);
    $emailCol = registered_pick_column($db, 'contractors', ['email', 'contact_email']);
    $phoneCol = registered_pick_column($db, 'contractors', ['phone', 'contact_number', 'mobile']);
    $statusCol = registered_pick_column($db, 'contractors', ['status']);
    $ratingCol = registered_pick_column($db, 'contractors', ['rating']);
    $specializationCol = registered_pick_column($db, 'contractors', ['specialization']);
    $experienceCol = registered_pick_column($db, 'contractors', ['experience']);
    $complianceCol = registered_pick_column($db, 'contractors', ['compliance_status']);
    $riskLevelCol = registered_pick_column($db, 'contractors', ['risk_level']);
    $riskScoreCol = registered_pick_column($db, 'contractors', ['risk_score']);
    $performanceCol = registered_pick_column($db, 'contractors', ['performance_rating']);
    $reliabilityCol = registered_pick_column($db, 'contractors', ['reliability_score']);

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

    $Engineers = [];
    $hasDocumentsTable = registered_table_exists($db, 'contractor_documents');
    $todayDate = date('Y-m-d');
    $docsStmt = null;
    if ($hasDocumentsTable) {
        $docsStmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_docs,
                SUM(CASE WHEN LOWER(COALESCE(document_type,''))='license' THEN 1 ELSE 0 END) AS license_docs,
                SUM(CASE WHEN LOWER(COALESCE(document_type,''))='resume' THEN 1 ELSE 0 END) AS resume_docs,
                SUM(CASE WHEN LOWER(COALESCE(document_type,''))='certificate' THEN 1 ELSE 0 END) AS certificate_docs,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) AS verified_docs
             FROM contractor_documents
             WHERE contractor_id = ?"
        );
    }

    try {
        $result = $db->query("SELECT " . implode(', ', $selectParts) . " FROM contractors ORDER BY id DESC LIMIT 100");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['display_name'] = trim((string)($row['full_name'] ?? '')) !== ''
                    ? (string) $row['full_name']
                    : ((trim((string)($row['company'] ?? '')) !== '') ? (string) $row['company'] : ('Engineer #' . (int)($row['id'] ?? 0)));

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
        if ($docsStmt) {
            $docsStmt->close();
        }
    } catch (Throwable $e) {
        error_log("Engineers query error: " . $e->getMessage());
        if ($docsStmt) {
            $docsStmt->close();
        }
        echo json_encode([]);
        exit;
    }
    
    echo json_encode($Engineers);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_evaluation_overview') {
    header('Content-Type: application/json');
    echo json_encode(ee_build_dashboard_lists($db));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_contractor_documents') {
    header('Content-Type: application/json');
    $contractorId = isset($_GET['contractor_id']) ? (int) $_GET['contractor_id'] : 0;
    if ($contractorId <= 0 || !registered_table_exists($db, 'contractor_documents')) {
        echo json_encode([]);
        exit;
    }

    $stmt = $db->prepare(
        "SELECT id, contractor_id, document_type, file_path, original_name, mime_type, file_size, expires_on, is_verified, verified_at, uploaded_at
         FROM contractor_documents
         WHERE contractor_id = ?
         ORDER BY uploaded_at DESC, id DESC"
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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'recommended_engineers') {
    header('Content-Type: application/json');

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

    $stmt = $db->prepare($query);
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

    echo json_encode([
        'success' => $ok,
        'message' => $ok ? ($isVerified ? 'Document verified.' : 'Document marked as unverified.') : 'Unable to update document status.'
    ]);
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
        $result = $db->query("SELECT " . implode(', ', $projectSelect) . " FROM projects ORDER BY {$orderBy}");
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
        $result->free();
    }
    
    echo json_encode($projects);
    exit;
}

// Handle POST request for assigning Engineer to project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_contractor') {
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
    header('Content-Type: application/json');

    $contractor_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($contractor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Engineer ID']);
        exit;
    }

    $stmt = $db->prepare("DELETE FROM contractors WHERE id = ?");
    $stmt->bind_param("i", $contractor_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Engineer deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete Engineer']);
    }

    $stmt->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'evaluate_contractor') {
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
                <a href="project_registration.php" class="nav-main-item" id="projectRegToggle">
                    <img src="../assets/images/admin/list.png" class="nav-icon">Project Registration
                    <span class="dropdown-arrow">&#9662;</span>
                </a>
                <div class="nav-submenu" id="projectRegSubmenu">
                    <a href="project_registration.php" class="nav-submenu-item">
                        <span class="submenu-icon">&#128203;</span>
                        <span>Registered Engineers</span>
                    </a>
                </div>
            </div>
            
            <a href="project-prioritization.php"><img src="../assets/images/admin/prioritization.png" class="nav-icon">Project Prioritization</a>
                <a href="citizen-verification.php" class="nav-main-item"><img src="../assets/images/admin/person.png" class="nav-icon">Citizen Verification</a>
        </div>
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
                <div id="contractorsCount" class="contractor-count-pill">0 Engineers</div>
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


























