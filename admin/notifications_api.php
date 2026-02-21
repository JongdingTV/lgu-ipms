<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/database.php';

set_no_cache_headers();
check_auth();
check_suspicious_activity();

header('Content-Type: application/json');

if ($db->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$items = [];
$latestId = 0;
$pendingCount = 0;

try {
    $pendingSql = "SELECT COUNT(*) AS c FROM feedback WHERE LOWER(COALESCE(status, '')) = 'pending'";
    if ($pendingRes = $db->query($pendingSql)) {
        $row = $pendingRes->fetch_assoc();
        $pendingCount = (int)($row['c'] ?? 0);
        $pendingRes->free();
    }

    $stmt = $db->prepare("
        SELECT id, user_name, subject, category, location, status, date_submitted
        FROM feedback
        ORDER BY id DESC
        LIMIT 12
    ");

    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id > $latestId) {
                $latestId = $id;
            }

            $status = strtolower(trim((string)($row['status'] ?? '')));
            $category = strtolower(trim((string)($row['category'] ?? '')));
            $level = 'info';
            if ($status === 'pending') {
                $level = 'warning';
            }
            if ($category === 'critical') {
                $level = 'danger';
            }

            $user = trim((string)($row['user_name'] ?? 'Citizen'));
            $subject = trim((string)($row['subject'] ?? 'Concern submitted'));
            $location = trim((string)($row['location'] ?? ''));

            $items[] = [
                'id' => $id,
                'level' => $level,
                'title' => $subject,
                'message' => $location !== '' ? ($user . ' • ' . $location) : $user,
                'status' => $row['status'] ?? 'Pending',
                'created_at' => $row['date_submitted'] ?? null
            ];
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load notifications'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'latest_id' => $latestId,
    'pending_count' => $pendingCount,
    'items' => $items
]);

