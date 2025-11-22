<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';

    if ($id <= 0 || $name === '') {
        header('Location: settings.php');
        exit;
    }

    // Check duplicate name
    $check = $database->query("SELECT id FROM branches WHERE name = '". $database->real_escape_string($name) ."' AND id != $id LIMIT 1");
    if ($check->num_rows > 0) {
        header('Location: settings.php?action=edit_branch&id=' . $id . '&error=1');
        exit;
    }

    $stmt = $database->prepare("UPDATE branches SET name = ?, address = ? WHERE id = ?");
    $stmt->bind_param('ssi', $name, $address, $id);
    if ($stmt->execute()) {
        header('Location: settings.php');
    } else {
        header('Location: settings.php?action=edit_branch&id=' . $id . '&error=2');
    }
} else {
    header('Location: settings.php');
}
?>