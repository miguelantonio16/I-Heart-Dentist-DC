<?php
// Superadmin page disabled. Redirect to admin dashboard.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Location: dashboard.php');
exit();

