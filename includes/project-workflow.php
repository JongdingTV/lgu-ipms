<?php

if (!function_exists('pw_table_exists')) {
    function pw_table_exists(mysqli $db, string $table): bool
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
        $ok = $res && $res->num_rows > 0;
        if ($res) {
            $res->free();
        }
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('pw_normalize_status')) {
    function pw_normalize_status(string $status): ?string
    {
        $raw = strtolower(trim($status));
        if ($raw === '') {
            return null;
        }
        $map = [
            'draft' => 'Draft',
            'for approval' => 'For Approval',
            'for_approval' => 'For Approval',
            'approved' => 'Approved',
            'ongoing' => 'Ongoing',
            'in progress' => 'Ongoing',
            'in_progress' => 'Ongoing',
            'delayed' => 'Delayed',
            'on-hold' => 'On-hold',
            'on hold' => 'On-hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'canceled' => 'Cancelled',
        ];
        return $map[$raw] ?? null;
    }
}

if (!function_exists('pw_allowed_transitions')) {
    function pw_allowed_transitions(): array
    {
        return [
            'Draft' => ['For Approval', 'Approved', 'Cancelled'],
            'For Approval' => ['Draft', 'Approved', 'Cancelled'],
            'Approved' => ['Ongoing', 'Delayed', 'On-hold', 'Completed', 'Cancelled'],
            'Ongoing' => ['Delayed', 'On-hold', 'Completed', 'Cancelled'],
            'Delayed' => ['Ongoing', 'On-hold', 'Completed', 'Cancelled'],
            'On-hold' => ['Ongoing', 'Delayed', 'Completed', 'Cancelled'],
            'Completed' => [],
            'Cancelled' => [],
        ];
    }
}

if (!function_exists('pw_can_transition')) {
    function pw_can_transition(string $current, string $next): bool
    {
        if ($current === $next) {
            return true;
        }
        $matrix = pw_allowed_transitions();
        return isset($matrix[$current]) && in_array($next, $matrix[$current], true);
    }
}

if (!function_exists('pw_log_status_history')) {
    function pw_log_status_history(mysqli $db, int $projectId, string $newStatus, int $changedBy = 0, string $notes = ''): void
    {
        if ($projectId <= 0 || !pw_table_exists($db, 'project_status_history')) {
            return;
        }
        $stmt = $db->prepare(
            "INSERT INTO project_status_history (project_id, status, changed_by, notes, changed_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        if (!$stmt) {
            return;
        }
        $actor = $changedBy > 0 ? $changedBy : null;
        $stmt->bind_param('isis', $projectId, $newStatus, $actor, $notes);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('pw_get_current_status')) {
    function pw_get_current_status(mysqli $db, int $projectId): ?string
    {
        $stmt = $db->prepare("SELECT status FROM projects WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $projectId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) {
            $res->free();
        }
        $stmt->close();
        if (!$row) {
            return null;
        }
        return pw_normalize_status((string)($row['status'] ?? ''));
    }
}

if (!function_exists('pw_validate_transition')) {
    function pw_validate_transition(mysqli $db, int $projectId, string $requestedStatus): array
    {
        $next = pw_normalize_status($requestedStatus);
        if ($next === null) {
            return ['ok' => false, 'message' => 'Invalid project status value.', 'current' => null, 'next' => null];
        }
        $current = pw_get_current_status($db, $projectId);
        if ($current === null) {
            return ['ok' => false, 'message' => 'Project not found for status update.', 'current' => null, 'next' => $next];
        }
        if (!pw_can_transition($current, $next)) {
            return [
                'ok' => false,
                'message' => "Invalid status transition: {$current} -> {$next}.",
                'current' => $current,
                'next' => $next
            ];
        }
        return ['ok' => true, 'message' => 'OK', 'current' => $current, 'next' => $next];
    }
}

