<?php
// Set the timezone
date_default_timezone_set('Asia/Singapore');
session_start();


// Check if user is logged in and is a patient
if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'p') {
        header("location: login.php");
        exit;
    }
} else {
    header("location: ../login.php");
    exit;
}


// Include database connection
include("../connection.php");


// Get the email parameter and validate it
if (!isset($_GET['email']) || $_GET['email'] !== $_SESSION["user"]) {
    // Security check: Only allow users to download their own records
    header("Location: profile.php");
    exit;
}


$useremail = $_GET['email'];
$userrow = $database->query("SELECT * FROM patient WHERE pemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["pid"];
$username = $userfetch["pname"];


// Get medical history data
$medicalData = $database->query("SELECT * FROM medical_history WHERE email = '$useremail'");
$hasMedicalHistory = $medicalData->num_rows > 0;


if (!$hasMedicalHistory) {
    // If no medical history, redirect back to profile
    header("Location: profile.php");
    exit;
}


$medical = $medicalData->fetch_assoc();


// Include TCPDF library
require_once('../tcpdf/tcpdf.php');


// Create custom PDF document class with header and footer
class MYPDF extends TCPDF {
    // Page header
    public function Header() {
        $image_file = '../Media/Icon/SDMC Logo.jpg';
       
        // Set logo position (adjust to center horizontally)
        if (file_exists($image_file)) {
            $this->Image($image_file, 95, 15, 20, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
       
        // Empty header to avoid default header
        // We'll add header content manually in the main document body
    }


    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Footer info
        $this->Cell(0, 10, 'Document Security ID: ' . $GLOBALS['document_id'] . ' | Generated on ' . date('d/m/Y H:i:s'), 0, false, 'C');
    }

}


// Create document ID for tracking
$document_id = md5($userid . date('YmdHis'));


// Create new PDF document
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);


// Set document information
$pdf->SetCreator('IHeartDentistDC Dental System');
$pdf->SetAuthor('I Heart Dentist Dental Clinic');
$pdf->SetTitle('Official Medical Record - ' . $username);
$pdf->SetSubject('Medical Record');


// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);


// Set margins - smaller margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(10);


// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);


// Add a page
$pdf->AddPage();


// Format the patient DOB if available
$patientDOB = isset($userfetch['pdob']) ? $userfetch['pdob'] : 'Not specified';


// Calculate age if DOB is available
$age = '';
if (isset($userfetch['pdob']) && $userfetch['pdob'] !== '') {
    $dob = new DateTime($userfetch['pdob']);
    $now = new DateTime();
    $interval = $now->diff($dob);
    $age = $interval->y;
}


// Reference number and date
$refNumber = 'SMR-' . $userid . '-' . date('YmdHis');
$currentDate = date('d F Y');


// Add logo first (larger size, centered)
$image_file = '../Media/Icon/SDMC Logo.jpg';
if (file_exists($image_file)) {
    $pdf->Image($image_file, 85, 10, 40, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
}

// Move cursor down after the image
$pdf->SetY(42); // Adjust depending on image size

// Begin with custom header
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'MEDICAL RECORD', 0, 1, 'C');

/*
// Add reference info in a table
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(100, 5, 'REFERENCE NUMBER: ' . $refNumber, 0, 0);
$pdf->Cell(80, 5, 'DATE ISSUED: ' . date('d M Y'), 0, 1, 'R');
*/

// Add clinic name and address
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 7, 'I HEART DENTIST DENTAL CLINIC', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 5, '1134 Centro St., Brgy Calulut, City of San Fernando, Pampanga', 0, 1, 'C');
$pdf->Cell(0, 5, 'Tel: +63 966 904 7561 | Email: sdmclinic.csfp@gmail.com', 0, 1, 'C');


$pdf->Ln(5);


// Add line separator
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(2);


// PATIENT INFORMATION SECTION
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(0, 51, 102); // Dark blue
$pdf->SetTextColor(255, 255, 255); // White text
$pdf->Cell(0, 6, 'PATIENT INFORMATION', 1, 1, 'L', 1);
$pdf->SetTextColor(0, 0, 0); // Reset to black text


// Patient info table
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(230, 230, 230); // Light gray


// Table headers
$pdf->Cell(40, 6, 'Patient ID', 1, 0, 'L', 1);
$pdf->Cell(140, 6, 'Patient Name', 1, 1, 'L', 1);


// Table data
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(40, 6, $userid, 1, 0, 'L');
$pdf->Cell(140, 6, $username, 1, 1, 'L');


// Date of Birth row
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(40, 6, 'Date of Birth', 1, 0, 'L', 1);
$pdf->Cell(140, 6, 'Age / Gender', 1, 1, 'L', 1);


$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(40, 6, $patientDOB, 1, 0, 'L');
$pdf->Cell(140, 6, ($age !== '' ? $age . ' years' : 'Not specified') . ' / ' . (isset($userfetch['pgender']) ? $userfetch['pgender'] : 'Not specified'), 1, 1, 'L');


// Contact Info row
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(40, 6, 'Contact Number', 1, 0, 'L', 1);
$pdf->Cell(140, 6, 'Email Address', 1, 1, 'L', 1);


$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(40, 6, $userfetch['ptel'], 1, 0, 'L');
$pdf->Cell(140, 6, $useremail, 1, 1, 'L');


// Address row
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(0, 6, 'Residential Address', 1, 1, 'L', 1);


$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 6, $userfetch['paddress'], 1, 1, 'L');


$pdf->Ln(3);


// HEALTH ASSESSMENT SECTION
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(0, 51, 102); // Dark blue
$pdf->SetTextColor(255, 255, 255); // White text
$pdf->Cell(0, 6, 'HEALTH ASSESSMENT', 1, 1, 'L', 1);
$pdf->SetTextColor(0, 0, 0); // Reset to black text


// Health assessment table
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(230, 230, 230); // Light gray


// Table headers
$pdf->Cell(75, 6, 'Health Parameter', 1, 0, 'L', 1);
$pdf->Cell(105, 6, 'Status / Details', 1, 1, 'L', 1);


// Table data rows
$pdf->SetFont('helvetica', '', 8);


$healthParameters = [
    'General Health Status' => $medical['good_health'],
    'Currently Under Medical Treatment' => $medical['under_treatment'],
    'Serious Illness or Surgery' => $medical['serious_illness'],
    'Hospitalization History' => $medical['hospitalized'],
    'Current Medications' => empty($medical['medication']) ? 'None reported' : $medical['medication'],
    'Known Allergies' => empty($medical['allergies']) ? 'None reported' : $medical['allergies']
];


foreach ($healthParameters as $param => $value) {
    $pdf->Cell(75, 6, $param, 1, 0, 'L');
    $pdf->Cell(105, 6, $value, 1, 1, 'L');
}


$pdf->Ln(3);


// RISK FACTORS SECTION
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(0, 51, 102); // Dark blue
$pdf->SetTextColor(255, 255, 255); // White text
$pdf->Cell(0, 6, 'RISK FACTORS & ADDITIONAL INFORMATION', 1, 1, 'L', 1);
$pdf->SetTextColor(0, 0, 0); // Reset to black text


// Risk factors table
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(230, 230, 230); // Light gray


// Table headers
$pdf->Cell(75, 6, 'Parameter', 1, 0, 'L', 1);
$pdf->Cell(105, 6, 'Status / Details', 1, 1, 'L', 1);


// Table data rows
$pdf->SetFont('helvetica', '', 8);


$riskFactors = [
    'Tobacco Use' => $medical['tobacco'],
    'Recreational Drug Use' => $medical['drugs'],
    'Blood Pressure Status' => empty($medical['blood_pressure']) ? 'Normal' : $medical['blood_pressure'],
    'Bleeding Time Issues' => empty($medical['bleeding_time']) ? 'None reported' : $medical['bleeding_time'],
    'Existing Health Conditions' => empty($medical['health_conditions']) ? 'None reported' : $medical['health_conditions'],
    'Last Dental Visit' => empty($medical['last_dental_visit']) ? 'Not specified' : $medical['last_dental_visit']
];


foreach ($riskFactors as $param => $value) {
    $pdf->Cell(75, 6, $param, 1, 0, 'L');
    $pdf->Cell(105, 6, $value, 1, 1, 'L');
}


$pdf->Ln(3);


// VERIFICATION SECTION
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(0, 51, 102); // Dark blue
$pdf->SetTextColor(255, 255, 255); // White text
$pdf->Cell(0, 6, 'VERIFICATION & CERTIFICATION', 1, 1, 'L', 1);
$pdf->SetTextColor(0, 0, 0); // Reset to black text


$pdf->Ln(3);


// Certification text
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell(0, 5, 'I hereby certify that the information contained in this medical record is accurate and complete to the best of my knowledge. This document constitutes an official medical record issued by I Heart Dentist Dental Clinic.', 0, 'L');


$pdf->Ln(10);


// Signature lines - MODIFIED: Removed Medical Director, now only Patient and Dentist centered
$pdf->Cell(10, 0, '', 0, 0);       // Reduced left spacing to compensate for wider lines
$pdf->Cell(75, 0, '', 'B', 0);     // Wider Patient signature line
$pdf->Cell(10, 0, '', 0, 0);       // Adjusted space between
$pdf->Cell(75, 0, '', 'B', 1);     // Wider Dentist signature line


$pdf->Ln(2);


// Signature labels - MODIFIED: Only two labels now centered
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(17, 5, '', 0, 0); // Left spacing to center the two signatures
$pdf->Cell(60, 5, 'Patient Signature', 0, 0, 'C');
$pdf->Cell(25, 5, '', 0, 0); // Space between labels
$pdf->Cell(60, 5, 'Dentist Signature', 0, 1, 'C');


// Signature details - MODIFIED: Only two details now centered
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(17, 5, '', 0, 0); // Left spacing to center the two signatures
$pdf->Cell(60, 5, $username, 0, 0, 'C');
$pdf->Cell(25, 5, '', 0, 0); // Space between details
$pdf->Cell(60, 5, 'I HEART DENTIST DENTAL CLINIC', 0, 1, 'C');


$pdf->Ln(10);

/*
// Official note in a box
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 10, 'This document is an official medical record and is valid only with clinic seal and authorized signatures.', 1, 1, 'C');
$pdf->Cell(0, 5, 'Any alterations to this document will render it invalid.', 1, 1, 'C');
*/

// Add official stamp image if available
$stamp_file = '../Media/Icon/official_stamp.png';
if (file_exists($stamp_file)) {
    $pdf->Image($stamp_file, 135, 225, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
}


// Close and output PDF document
$pdf->Output('Official_Medical_Record_' . $userid . '_' . date('Ymd') . '.pdf', 'D');
exit;
?>
