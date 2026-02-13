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

