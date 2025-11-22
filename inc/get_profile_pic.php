<?php
// Returns a normalized profile picture path (relative to project root, without leading ../)
// Usage: include_once __DIR__ . '/get_profile_pic.php';
//        $profile_pic = get_profile_pic($userRow);

function get_profile_pic($row) {
    // Default path inside project (no leading ../)
    $default = 'Media/Icon/Blue/profile.png';

    if (!is_array($row)) {
        return $default;
    }

    $stored = isset($row['profile_pic']) ? trim($row['profile_pic']) : '';

    if ($stored === '') {
        return $default;
    }

    // Resolve absolute filesystem path to check existence
    $projectRoot = realpath(__DIR__ . '/..');
    $candidate = $projectRoot . DIRECTORY_SEPARATOR . ltrim($stored, '/\\');

    if (file_exists($candidate)) {
        // return stored path normalized (no leading /)
        return ltrim($stored, '/\\');
    }

    // fallback to default
    return $default;
}
