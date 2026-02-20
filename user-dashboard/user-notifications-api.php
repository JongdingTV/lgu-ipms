<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';
require dirname(__DIR__) . '/config-path.php';
require __DIR__ . '/user-profile-helper.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

header('Content-Type: application/json');

if (!isset($db) || $db->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userName = trim((string) ($_SESSION['user_name'] ?? ''));
$items = [];
$latestId = 0;

// Project-related updates (new/active/completed projects)
$projectQuery = $db->query("SELECT id, name, location, status, created_at FROM projects ORDER BY id DESC LIMIT 15");
if ($projectQuery) {
    while ($row = $projectQuery->fetch_assoc()) {
        $pid = (int) ($row['id'] ?? 0);
        $nid = $pid + 1000;
        $status = strtolower((string) ($row['status'] ?? ''));
        $level = 'info';
        if ($status === 'completed') {
            $level = 'success';
        } elseif ($status === 'on-hold' || $status === 'cancelled') {
            $level = 'warning';
        }

        $projectName = trim((string) ($row['name'] ?? 'Project'));
        $location = trim((string) ($row['location'] ?? ''));
        $statusText = trim((string) ($row['status'] ?? 'Updated'));

        $items[] = [
            'id' => $nid,
            'level' => $level,
            'title' => 'Project Update: ' . $projectName,
            'message' => $location !== '' ? ($statusText . ' | ' . $location) : $statusText,
            'created_at' => $row['created_at'] ?? null
        ];
        if ($nid > $latestId) {
            $latestId = $nid;
        }
    }
    $projectQuery->free();
}

// User verification status updates
if (user_table_has_column($db, 'users', 'verification_status')) {
    $stmt = $db->prepare('SELECT verification_status, created_at FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        if ($res) {
            $res->free();
        }
        $stmt->close();

        $status = strtolower(trim((string) ($row['verification_status'] ?? 'pending')));
        if (in_array($status, ['verified', 'rejected'], true)) {
            $nid = 900000000 + $userId;
            $items[] = [
                'id' => $nid,
                'level' => $status === 'verified' ? 'success' : 'danger',
                'title' => $status === 'verified' ? 'ID Verification Approved' : 'ID Verification Rejected',
                'message' => $status === 'verified'
                    ? 'Your account is now fully unlocked.'
                    : 'Please review your ID details and upload a valid document.',
                'created_at' => $row['created_at'] ?? null
            ];
            if ($nid > $latestId) {
                $latestId = $nid;
            }
        } elseif ($status === 'pending') {
            $nid = 800000000 + $userId;
            $items[] = [
                'id' => $nid,
                'level' => 'warning',
                'title' => 'ID Verification Pending',
                'message' => 'Your account is in limited mode until verification is approved.',
                'created_at' => $row['created_at'] ?? null
            ];
            if ($nid > $latestId) {
                $latestId = $nid;
            }
        }
    }
}

// Feedback status updates for current user.
if ($userName !== '') {
    $fbStmt = $db->prepare(
        "SELECT id, subject, status, date_submitted
         FROM feedback
         WHERE user_name = ?
         ORDER BY date_submitted DESC, id DESC
         LIMIT 20"
    );
    if ($fbStmt) {
        $fbStmt->bind_param('s', $userName);
        $fbStmt->execute();
        $fbRes = $fbStmt->get_result();
        while ($fbRes && ($fbRow = $fbRes->fetch_assoc())) {
            $feedbackId = (int) ($fbRow['id'] ?? 0);
            if ($feedbackId <= 0) {
                continue;
            }

            $subject = trim((string) ($fbRow['subject'] ?? 'Feedback'));
            $statusText = trim((string) ($fbRow['status'] ?? 'Pending'));
            $statusLower = strtolower($statusText);
            $level = 'info';
            if (in_array($statusLower, ['addressed', 'resolved', 'completed'], true)) {
                $level = 'success';
            } elseif (in_array($statusLower, ['rejected', 'invalid', 'closed'], true)) {
                $level = 'danger';
            } elseif ($statusLower === 'pending') {
                $level = 'warning';
            }

            $nid = 500000000 + $feedbackId;
            $items[] = [
                'id' => $nid,
                'level' => $level,
                'title' => 'Feedback Update: ' . $subject,
                'message' => 'Current status: ' . ($statusText !== '' ? $statusText : 'Pending'),
                'created_at' => $fbRow['date_submitted'] ?? null
            ];
            if ($nid > $latestId) {
                $latestId = $nid;
            }
        }
        if ($fbRes) {
            $fbRes->free();
        }
        $fbStmt->close();
    }
}

usort($items, static function (array $a, array $b): int {
    return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
});

echo json_encode([
    'success' => true,
    'latest_id' => $latestId,
    'items' => $items
]);

$db->close();
