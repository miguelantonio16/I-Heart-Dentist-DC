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

    // Accept legacy field names if present
    if ($stored === '' && isset($row['photo'])) {
        $stored = trim($row['photo']);
    }

    // If stored value is empty, return default
    if ($stored === '') {
        return $default;
    }

    // If the stored filename is actually the site logo (or similar), treat it as empty
    $basename = strtolower(basename($stored));
    $logoNames = ['logo.png', 'sdmc logo.png', 'sdmc_logo.png', 'logo.jpg', 'logo.jpeg', 'default.jpg', 'default.png', 'default.jpeg'];
    foreach ($logoNames as $ln) {
        if ($basename === $ln) {
            return $default;
        }
    }

    // If name starts with "default" treat it as not a user profile
    if (strpos($basename, 'default') === 0) {
        return $default;
    }

    // Resolve absolute filesystem path to check existence
    $projectRoot = realpath(__DIR__ . '/..');
    $candidate = $projectRoot . DIRECTORY_SEPARATOR . ltrim($stored, '/\\');

    if (file_exists($candidate)) {
        // If candidate resolves to the shared logo path, don't return it as a profile
        $candidateBase = strtolower(basename($candidate));
        if (in_array($candidateBase, $logoNames)) {
            return $default;
        }

        // Normalize: if file is under admin/uploads or uploads, prefix accordingly
        $relative = ltrim(str_replace($projectRoot, '', $candidate), '/\\');
        return $relative !== '' ? $relative : ltrim($stored, '/\\');
    }

    // fallback to default
    return $default;
}
