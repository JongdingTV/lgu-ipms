<?php

function user_avatar_initials(string $name): string
{
    $nameParts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach ($nameParts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials !== '' ? $initials : 'U';
}

function user_avatar_color(string $seed): string
{
    $palette = ['#1d4ed8', '#0891b2', '#16a34a', '#9333ea', '#ea580c', '#c2410c', '#0f766e', '#be123c', '#4f46e5', '#0ea5e9'];
    $hash = abs(crc32($seed));
    return $palette[$hash % count($palette)];
}

function user_profile_photo_web_path(int $userId): string
{
    $uploadDir = dirname(__DIR__) . '/uploads/user-profile';
    if (!is_dir($uploadDir)) {
        return '';
    }

    $matches = glob($uploadDir . '/user_' . $userId . '.*') ?: [];
    if (!$matches) {
        return '';
    }

    usort($matches, static function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    return '/uploads/user-profile/' . basename($matches[0]);
}

function user_table_has_column(mysqli $db, string $tableName, string $columnName): bool
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

function user_feedback_verification_status(mysqli $db, int $userId): string
{
    if (!user_table_has_column($db, 'users', 'verification_status')) {
        return 'verified';
    }

    $stmt = $db->prepare('SELECT verification_status FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return 'pending';
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    $status = strtolower(trim((string) ($row['verification_status'] ?? 'pending')));
    return $status !== '' ? $status : 'pending';
}

function user_feedback_access_allowed(mysqli $db, int $userId): bool
{
    $status = user_feedback_verification_status($db, $userId);
    return in_array($status, ['verified', 'approved', 'active'], true);
}
