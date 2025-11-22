<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';

    if ($name === '') {
        header('Location: settings.php?action=add_branch&error=2');
        exit;
    }

    // Check duplicate
    $check = $database->query("SELECT id FROM branches WHERE name = '". $database->real_escape_string($name) ."' LIMIT 1");
    if ($check->num_rows > 0) {
        header('Location: settings.php?action=add_branch&error=1');
        exit;
    }

    $stmt = $database->prepare("INSERT INTO branches (name, address) VALUES (?, ?)");
    $stmt->bind_param('ss', $name, $address);
    if ($stmt->execute()) {
        header('Location: settings.php');
    } else {
        header('Location: settings.php?action=add_branch&error=2');
    }
} else {
    header('Location: settings.php');
}
?>