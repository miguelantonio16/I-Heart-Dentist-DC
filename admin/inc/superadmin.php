<?php
// Lightweight helpers for superadmin checks and guards
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_superadmin() {
    return isset($_SESSION['is_superadmin']) && ($_SESSION['is_superadmin'] == 1 || $_SESSION['is_superadmin'] === true);
}

function require_superadmin() {
    if (!is_superadmin()) {
        http_response_code(403);
        // For web pages show a user friendly message
        if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            echo json_encode(['status' => false, 'message' => 'Forbidden: superadmin only']);
        } else {
            echo '<h2>403 Forbidden</h2><p>This area is restricted to superadmin users only.</p>';
        }
        exit();
    }
}
