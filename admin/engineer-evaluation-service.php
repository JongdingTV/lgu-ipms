<?php

declare(strict_types=1);

function ee_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

function ee_column_exists(mysqli $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    return $exists;
}

function ee_pick_column(mysqli $db, string $table, array $candidates): ?string
{
    foreach ($candidates as $column) {
        if (ee_column_exists($db, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function ee_fetch_one_value(mysqli $db, string $sql, string $types = '', array $params = []): ?float
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $res = $stmt->get_result();
    $value = null;
    if ($res && ($row = $res->fetch_row())) {
        $value = isset($row[0]) ? (float) $row[0] : null;
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();
    return $value;
}

function ee_fetch_metrics(mysqli $db, int $contractorId): array
{
    $historyExists = ee_table_exists($db, 'contractor_project_history');
    $violationsExists = ee_table_exists($db, 'contractor_violations');
    $assignmentsExists = ee_table_exists($db, 'contractor_project_assignments');

    $totalProjects = 0;
    $completedProjects = 0;
    $delayedProjects = 0;
    $avgRating = 0.0;
    $violationCount = 0;

    if ($historyExists) {
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_projects,
                SUM(CASE WHEN LOWER(COALESCE(status, '')) IN ('completed', 'done') THEN 1 ELSE 0 END) AS completed_projects,
                SUM(CASE WHEN is_delayed = 1 OR delay_days > 0 THEN 1 ELSE 0 END) AS delayed_projects,
                AVG(NULLIF(project_rating, 0)) AS avg_rating
             FROM contractor_project_history
             WHERE contractor_id = ?"
        );
        if ($stmt) {
            $stmt->bind_param('i', $contractorId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $totalProjects = (int) ($row['total_projects'] ?? 0);
                $completedProjects = (int) ($row['completed_projects'] ?? 0);
                $delayedProjects = (int) ($row['delayed_projects'] ?? 0);
                $avgRating = (float) ($row['avg_rating'] ?? 0);
            }
            if ($res) {
                $res->free();
            }
            $stmt->close();
        }
    } elseif ($assignmentsExists) {
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_projects,
                SUM(CASE WHEN LOWER(COALESCE(p.status, '')) IN ('completed', 'done') THEN 1 ELSE 0 END) AS completed_projects,
                SUM(CASE WHEN LOWER(COALESCE(p.status, '')) IN ('delayed', 'behind schedule') THEN 1 ELSE 0 END) AS delayed_projects
             FROM contractor_project_assignments cpa
             INNER JOIN projects p ON p.id = cpa.project_id
             WHERE cpa.contractor_id = ?"
        );
        if ($stmt) {
            $stmt->bind_param('i', $contractorId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $totalProjects = (int) ($row['total_projects'] ?? 0);
                $completedProjects = (int) ($row['completed_projects'] ?? 0);
                $delayedProjects = (int) ($row['delayed_projects'] ?? 0);
            }
            if ($res) {
                $res->free();
            }
            $stmt->close();
        }
    }

    if ($avgRating <= 0) {
        $avgRating = ee_fetch_one_value($db, "SELECT COALESCE(rating, 0) FROM contractors WHERE id = ? LIMIT 1", 'i', [$contractorId]) ?? 0.0;
    }

    if ($violationsExists) {
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS total_violations
             FROM contractor_violations
             WHERE contractor_id = ? AND LOWER(COALESCE(status,'')) <> 'resolved'"
        );
        if ($stmt) {
            $stmt->bind_param('i', $contractorId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $violationCount = (int) ($row['total_violations'] ?? 0);
            }
            if ($res) {
                $res->free();
            }
            $stmt->close();
        }
    }

    return [
        'total_projects' => $totalProjects,
        'completed_projects' => $completedProjects,
        'delayed_projects' => $delayedProjects,
        'avg_rating' => $avgRating,
        'violation_count' => $violationCount,
    ];
}

function ee_evaluate_scores(array $metrics): array
{
    $total = max(0, (int) ($metrics['total_projects'] ?? 0));
    $completed = max(0, (int) ($metrics['completed_projects'] ?? 0));
    $delayed = max(0, (int) ($metrics['delayed_projects'] ?? 0));
    $avgRating = max(0.0, min(5.0, (float) ($metrics['avg_rating'] ?? 0.0)));
    $violations = max(0, (int) ($metrics['violation_count'] ?? 0));

    $completionRate = $total > 0 ? ($completed / $total) * 100 : 0.0;
    $delayRate = $total > 0 ? ($delayed / $total) * 100 : 0.0;
    $ratingScore = ($avgRating / 5) * 100;
    $violationPenalty = min(100, $violations * 10);

    $performanceScore = (0.45 * $completionRate) + (0.35 * $ratingScore) + (0.20 * (100 - $delayRate));
    $reliabilityScore = (0.60 * $completionRate) + (0.25 * (100 - $delayRate)) + (0.15 * (100 - $violationPenalty));

    $rawRiskScore = 100 - ((0.5 * $performanceScore) + (0.5 * $reliabilityScore)) + min(35, $violations * 7);
    $riskScore = max(0.0, min(100.0, $rawRiskScore));

    $riskLevel = 'Low';
    if ($riskScore >= 70) {
        $riskLevel = 'High';
    } elseif ($riskScore >= 40) {
        $riskLevel = 'Medium';
    }

    $complianceStatus = 'Compliant';
    if ($violations > 0) {
        $complianceStatus = $violations >= 3 ? 'Non-compliant' : 'Needs Review';
    }

    return [
        'completion_rate' => round($completionRate, 2),
        'delay_rate' => round($delayRate, 2),
        'avg_rating' => round($avgRating, 2),
        'performance_score' => round($performanceScore, 2),
        'reliability_score' => round($reliabilityScore, 2),
        'risk_score' => round($riskScore, 2),
        'risk_level' => $riskLevel,
        'compliance_status' => $complianceStatus,
    ];
}

function ee_persist_evaluation(mysqli $db, int $contractorId, array $metrics, array $scores): void
{
    $requiredColumns = [
        'past_project_count',
        'delayed_project_count',
        'performance_rating',
        'reliability_score',
        'risk_score',
        'risk_level',
        'compliance_status',
        'last_evaluated_at',
    ];
    foreach ($requiredColumns as $column) {
        if (!ee_column_exists($db, 'contractors', $column)) {
            return;
        }
    }

    $update = $db->prepare(
        "UPDATE contractors
         SET
            past_project_count = ?,
            delayed_project_count = ?,
            performance_rating = ?,
            reliability_score = ?,
            risk_score = ?,
            risk_level = ?,
            compliance_status = ?,
            last_evaluated_at = NOW()
         WHERE id = ?"
    );
    if ($update) {
        $performanceRating = (float) ($scores['performance_score'] ?? 0);
        $reliabilityScore = (float) ($scores['reliability_score'] ?? 0);
        $riskScore = (float) ($scores['risk_score'] ?? 0);
        $riskLevel = (string) ($scores['risk_level'] ?? 'Medium');
        $complianceStatus = (string) ($scores['compliance_status'] ?? 'Compliant');
        $totalProjects = (int) ($metrics['total_projects'] ?? 0);
        $delayedProjects = (int) ($metrics['delayed_projects'] ?? 0);

        $update->bind_param(
            'iidddssi',
            $totalProjects,
            $delayedProjects,
            $performanceRating,
            $reliabilityScore,
            $riskScore,
            $riskLevel,
            $complianceStatus,
            $contractorId
        );
        $update->execute();
        $update->close();
    }

    if (!ee_table_exists($db, 'contractor_evaluation_logs')) {
        return;
    }

    $insert = $db->prepare(
        "INSERT INTO contractor_evaluation_logs
            (contractor_id, completion_rate, delay_rate, avg_rating, violation_count,
             reliability_score, performance_score, risk_score, risk_level, recommendation, score_breakdown_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if ($insert) {
        $completionRate = (float) ($scores['completion_rate'] ?? 0);
        $delayRate = (float) ($scores['delay_rate'] ?? 0);
        $avgRating = (float) ($scores['avg_rating'] ?? 0);
        $violationCount = (int) ($metrics['violation_count'] ?? 0);
        $reliabilityScore = (float) ($scores['reliability_score'] ?? 0);
        $performanceScore = (float) ($scores['performance_score'] ?? 0);
        $riskScore = (float) ($scores['risk_score'] ?? 0);
        $riskLevel = (string) ($scores['risk_level'] ?? 'Medium');
        $recommendation = $riskLevel === 'Low' ? 'Recommended for assignment' : ($riskLevel === 'Medium' ? 'Assign with monitoring' : 'Do not prioritize for critical projects');
        $breakdown = json_encode(['metrics' => $metrics, 'scores' => $scores], JSON_UNESCAPED_SLASHES);

        $insert->bind_param(
            'idddidddsss',
            $contractorId,
            $completionRate,
            $delayRate,
            $avgRating,
            $violationCount,
            $reliabilityScore,
            $performanceScore,
            $riskScore,
            $riskLevel,
            $recommendation,
            $breakdown
        );
        $insert->execute();
        $insert->close();
    }
}

function ee_build_dashboard_lists(mysqli $db): array
{
    $topPerforming = [];
    $highRisk = [];
    $mostDelayed = [];

    $nameExpr = "COALESCE(NULLIF(" . (ee_pick_column($db, 'contractors', ['full_name', 'company', 'owner']) ?? 'company') . ",''), company, owner, CONCAT('Engineer #', id))";
    $performanceCol = ee_column_exists($db, 'contractors', 'performance_rating') ? 'performance_rating' : 'rating';
    $reliabilityCol = ee_column_exists($db, 'contractors', 'reliability_score') ? 'reliability_score' : '0';
    $riskLevelCol = ee_column_exists($db, 'contractors', 'risk_level') ? 'risk_level' : "'Medium'";
    $riskScoreCol = ee_column_exists($db, 'contractors', 'risk_score') ? 'risk_score' : '0';
    $delayedCol = ee_column_exists($db, 'contractors', 'delayed_project_count') ? 'delayed_project_count' : '0';
    $pastCol = ee_column_exists($db, 'contractors', 'past_project_count') ? 'past_project_count' : '0';
    $complianceCol = ee_column_exists($db, 'contractors', 'compliance_status') ? 'compliance_status' : "'Compliant'";

    $topRes = $db->query(
        "SELECT id, {$nameExpr} AS display_name,
                {$performanceCol} AS performance_rating, {$reliabilityCol} AS reliability_score, {$riskLevelCol} AS risk_level
         FROM contractors
         ORDER BY performance_rating DESC, reliability_score DESC
         LIMIT 5"
    );
    if ($topRes) {
        while ($row = $topRes->fetch_assoc()) {
            $topPerforming[] = $row;
        }
        $topRes->free();
    }

    $riskRes = $db->query(
        "SELECT id, {$nameExpr} AS display_name,
                {$riskScoreCol} AS risk_score, {$riskLevelCol} AS risk_level, {$delayedCol} AS delayed_project_count, {$complianceCol} AS compliance_status
         FROM contractors
         WHERE LOWER(COALESCE({$riskLevelCol}, '')) = 'high'
         ORDER BY risk_score DESC, delayed_project_count DESC
         LIMIT 5"
    );
    if ($riskRes) {
        while ($row = $riskRes->fetch_assoc()) {
            $highRisk[] = $row;
        }
        $riskRes->free();
    }

    $delayRes = $db->query(
        "SELECT id, {$nameExpr} AS display_name,
                {$delayedCol} AS delayed_project_count, {$pastCol} AS past_project_count
         FROM contractors
         ORDER BY delayed_project_count DESC, past_project_count DESC
         LIMIT 5"
    );
    if ($delayRes) {
        while ($row = $delayRes->fetch_assoc()) {
            $mostDelayed[] = $row;
        }
        $delayRes->free();
    }

    return [
        'top_performing' => $topPerforming,
        'high_risk' => $highRisk,
        'most_delayed' => $mostDelayed,
    ];
}
