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
$seenId = 0;

$db->query("CREATE TABLE IF NOT EXISTS user_notification_state (
    user_id INT PRIMARY KEY,
    last_seen_id BIGINT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode((string) file_get_contents('php://input'), true);
    $markSeenId = (int) ($payload['mark_seen_id'] ?? 0);
    if ($markSeenId > 0 && $userId > 0) {
        $up = $db->prepare(
            "INSERT INTO user_notification_state (user_id, last_seen_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE last_seen_id = GREATEST(last_seen_id, VALUES(last_seen_id))"
        );
        if ($up) {
            $up->bind_param('ii', $userId, $markSeenId);
            $ok = $up->execute();
            $up->close();
            echo json_encode(['success' => (bool) $ok]);
            $db->close();
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'Unable to update notification state']);
    $db->close();
    exit;
}

$seenStmt = $db->prepare('SELECT last_seen_id FROM user_notification_state WHERE user_id = ? LIMIT 1');
if ($seenStmt) {
    $seenStmt->bind_param('i', $userId);
    $seenStmt->execute();
    $seenRes = $seenStmt->get_result();
    $seenRow = $seenRes ? $seenRes->fetch_assoc() : null;
    $seenId = (int) (($seenRow['last_seen_id'] ?? 0) ?: 0);
    if ($seenRes) {
        $seenRes->free();
    }
    $seenStmt->close();
}

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
if ($userId > 0 || $userName !== '') {
    $hasFeedbackUserIdStmt = $db->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'feedback'
           AND COLUMN_NAME = 'user_id'
         LIMIT 1"
    );
    $hasFeedbackUserId = false;
    if ($hasFeedbackUserIdStmt) {
        $hasFeedbackUserIdStmt->execute();
        $hasFeedbackUserIdRes = $hasFeedbackUserIdStmt->get_result();
        $hasFeedbackUserId = $hasFeedbackUserIdRes && $hasFeedbackUserIdRes->num_rows > 0;
        if ($hasFeedbackUserIdRes) {
            $hasFeedbackUserIdRes->free();
        }
        $hasFeedbackUserIdStmt->close();
    }

    $fbStmt = $hasFeedbackUserId
        ? $db->prepare(
            "SELECT id, subject, status, date_submitted
             FROM feedback
             WHERE user_id = ?
             ORDER BY date_submitted DESC, id DESC
             LIMIT 20"
        )
        : $db->prepare(
            "SELECT id, subject, status, date_submitted
             FROM feedback
             WHERE user_name = ?
             ORDER BY date_submitted DESC, id DESC
             LIMIT 20"
        );
    if ($fbStmt) {
        if ($hasFeedbackUserId) {
            $fbStmt->bind_param('i', $userId);
        } else {
            $fbStmt->bind_param('s', $userName);
        }
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
    'seen_id' => $seenId,
    'items' => $items
]);

$db->close();
