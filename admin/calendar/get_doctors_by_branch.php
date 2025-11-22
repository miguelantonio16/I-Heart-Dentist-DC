<?php
include("../../connection.php");

// Returns active doctors for a given branch_id (or all active doctors if no branch_id provided)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $branch_id = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? intval($_GET['branch_id']) : null;

    $doctors = [];
    if ($branch_id) {
        $stmt = $database->query("SELECT docid, docname FROM doctor WHERE status='active' AND branch_id = " . $branch_id . " ORDER BY docname ASC");
    } else {
        $stmt = $database->query("SELECT docid, docname FROM doctor WHERE status='active' ORDER BY docname ASC");
    }

    while ($row = $stmt->fetch_assoc()) {
        $doctors[] = ['docid' => $row['docid'], 'docname' => $row['docname']];
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => true, 'doctors' => $doctors]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['status' => false, 'doctors' => []]);
exit;

?>
