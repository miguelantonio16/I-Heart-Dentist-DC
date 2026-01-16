<?php
// Helper for safe redirects that preserve context when available.
// Usage:
//   require_once __DIR__ . '/redirect_helper.php';
//   // Either redirect immediately:
//   redirect_with_context('appointment.php', ['action'=>'completed']);
//   // Or get URL string for client-side use:
//   $url = get_redirect_url('my_appointment.php', ['status'=>'cancel_success']);

function get_redirect_url($defaultPage, $params = [], $allowReferrer = true) {
    // If explicit params provided, build query string and return
    if (!empty($params) && is_array($params)) {
        $qs = http_build_query($params);
        return $defaultPage . (strpos($defaultPage, '?') === false ? '?' : '&') . $qs;
    }

    // If HTTP_REFERER is present and points to the default page, use it
    if ($allowReferrer && !empty($_SERVER['HTTP_REFERER'])) {
        $ref = $_SERVER['HTTP_REFERER'];
        // Only accept referrers that contain the basename of the default page for safety
        $basename = basename(parse_url($defaultPage, PHP_URL_PATH));
        if ($basename && strpos($ref, $basename) !== false) {
            return $ref;
        }
    }

    // Fallback to default page without params
    return $defaultPage;
}

function redirect_with_context($defaultPage, $params = [], $allowReferrer = true) {
    $url = get_redirect_url($defaultPage, $params, $allowReferrer);
    header('Location: ' . $url);
    exit();
}

?>