<?php
session_start();
include("../connection.php"); // Ensure this file contains the database connection logic

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve form data
    $initial_treatment_to_be_done = $_POST['initial_treatment_to_be_done'];
    $initial_drugs_medications = $_POST['initial_drugs_medications'];
    $initial_changes_treatment_plan = $_POST['initial_changes_treatment_plan'];
    $initial_radiograph = $_POST['initial_radiograph'];
    $initial_removal_teeth = $_POST['initial_removal_teeth'];
    $initial_crowns_bridges = $_POST['initial_crowns_bridges'];
    $initial_endodontics = $_POST['initial_endodontics'];
    $initial_periodontal_disease = $_POST['initial_periodontal_disease'];
    $initial_fillings = $_POST['initial_fillings'];
    $initial_dentures = $_POST['initial_dentures'];
    $date = date("Y-m-d"); // Automatically get the current date
    $email = $_SESSION['user'];

    // Handle file upload
    $target_dir = "uploads/"; // Ensure this directory exists and is writable
    $file_name = basename($_FILES["valid_id"]["name"]);
    $target_file = $target_dir . time() . "_" . $file_name; // Unique file name
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if file is an actual image
    $check = getimagesize($_FILES["valid_id"]["tmp_name"]);
    if ($check === false) {
        echo "File is not an image.";
        $uploadOk = 0;
    }

    // Allow certain file formats
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
        echo "Only JPG, JPEG, and PNG files are allowed.";
        $uploadOk = 0;
    }

    // Move uploaded file to target directory
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["valid_id"]["tmp_name"], $target_file)) {
            // Insert data into the database
            $query = "INSERT INTO informed_consent (
                email, 
                initial_treatment_to_be_done, 
                initial_drugs_medications, 
                initial_changes_treatment_plan, 
                initial_radiograph, 
                initial_removal_teeth, 
                initial_crowns_bridges, 
                initial_endodontics, 
                initial_periodontal_disease, 
                initial_fillings, 
                initial_dentures, 
                consent_date,
                id_signature_path
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )";

            $stmt = $database->prepare($query);
            $stmt->bind_param(
                "sssssssssssss",
                $email,
                $initial_treatment_to_be_done,
                $initial_drugs_medications,
                $initial_changes_treatment_plan,
                $initial_radiograph,
                $initial_removal_teeth,
                $initial_crowns_bridges,
                $initial_endodontics,
                $initial_periodontal_disease,
                $initial_fillings,
                $initial_dentures,
                $date,
                $target_file
            );

            if ($stmt->execute()) {
                header("Location: dashboard.php");
                exit;
            } else {
                echo "<h1>Error Submitting Form</h1>";
            }

            $stmt->close();
        } else {
            echo "Error uploading file.";
        }
    }

    $database->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informed Consent Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            background-color: #f9f9f9;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
        select {
            width: 150px;
            padding: 10px;
            margin-top: 5px;
            margin-bottom: 30px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: white;
        }
        input[type="file"] {
            margin-top: 10px;
            margin-bottom: 20px;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            width: 100px;
            padding: 10px;
            border-radius: 5px;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        button:hover:not(:disabled) {
            background: #0056b3;
        }
        p {
            margin: 15px 0;
        }
        .consent-option {
            margin-top: 5px;
        }
        .required {
            color: red;
        }
        .instructions {
            background-color: #f0f8ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            max-width: 600px;
            margin: 0 auto 20px auto;
        }
        .instructions h2 {
            margin-top: 0;
            font-size: 18px;
            color: #007bff;
        }
        .instructions ol {
            margin-bottom: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <h1>Informed Consent</h1>

    <div class="instructions">
        <h2>Instructions for completing this form:</h2>
        <ol>
            <li>Please read each section carefully before responding.</li>
            <li>For each section, select "Agree" or "Disagree" from the dropdown menu.</li>
            <li>All sections marked with a red asterisk (<span class="required">*</span>) are required.</li>
            <li>Upload a photo of your valid ID with your signature.</li>
            <li>Check the final consent box to confirm your understanding.</li>
            <li>Click the Submit button when you have completed all sections.</li>
        </ol>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <p><strong>Treatment to be Done: <span class="required">*</span></strong></p>
        <p>I understand and consent to have any treatment done by the dentist after the procedure, the risks & benefits & cost have been fully explained. These treatments include, but are not limited to: x-rays, cleanings, periodontal treatments, fillings, crowns, bridges, all types of extractions, root canals, &/or dentures, local anesthetics & surgical cases.</p>
        <label>
            Response:
            <select name="initial_treatment_to_be_done" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <p><strong>Drugs & Medications: <span class="required">*</span></strong></p>
        <p>I understand that antibiotics, analgesics & other medications can cause allergic reactions causing redness & swelling of tissues, pain, itching, vomiting, &/or anaphylactic shock.</p>
        <label>
            Response:
            <select name="initial_drugs_medications" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <p><strong>Changes in Treatment Plan: <span class="required">*</span></strong></p>
        <p>I understand that during treatment it may be necessary to change/add procedures because of conditions found while working on the teeth that was not discovered during examination. For example, root canal therapy is following routine restorative procedures. I give my permission to the dentist to make any/all changes and additions as necessary w/ my responsibilities to pay all the costs agreed.</p>
        <label>
            Response:
            <select name="initial_changes_treatment_plan" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <p><strong>Radiograph: <span class="required">*</span></strong></p>
        <p>I understand that x-ray shot or a radiograph maybe necessary as part of diagnostic aid to come up with tentative diagnosis of my dental problem and to make a good treatment plan, but this will not give me a 100% assurance for the accuracy of the treatment since all dental treatments are subject to unpredictable complications that later on may lead to sudden change of treatment plan and subject to new charges.</p>
        <label>
            Response:
            <select name="initial_radiograph" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <p><strong>Removal of Teeth: <span class="required">*</span></strong></p>
        <p>I understand that alternatives to tooth removal (root canal therapy, crowns & periodontal surgery, etc.) & agree completely with the dentist to remove teeth & any other necessary forces to remove it. I understand that removing teeth does not always remove all the infections, if present & it may be necessary to have further treatment.</p>
        <label>
            Response:
            <select name="initial_removal_teeth" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <p><strong>Crowns & Bridges: <span class="required">*</span></strong></p>
        <p>Preparing a tooth may irritate the nerve tissue in the center of the tooth, leaving your tooth feeling sensitive to heat, cold & pressure. I understand that sometimes it is not possible to match the color of the crown exactly with artificial teeth. I further understand that I may be wearing temporary crowns.</p>
        <label>
            Response:
            <select name="initial_crowns_bridges" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <p><strong>Endodontics (Root Canal): <span class="required">*</span></strong></p>
        <p>I understand that there is no guarantee that root canal treatment will save a tooth & that complications can occur from the treatment.</p>
        <label>
            Response:
            <select name="initial_endodontics" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <p><strong>Periodontal Disease: <span class="required">*</span></strong></p>
        <p>I understand that periodontal disease is a serious condition causing gums & bone inflammation &/or loss & that can lead to the loss of my teeth. I understand that undertaking any dental procedures may have future adverse effect on my periodontal conditions.</p>
        <label>
            Response:
            <select name="initial_periodontal_disease" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <p><strong>Fillings: <span class="required">*</span></strong></p>
        <p>I understand that care must be exercised in chewing on fillings, especially during the first 24 hours to avoid breakage. I understand that significant sensitivity is a common, but usually temporary, after effect of a newly placed filling.</p>
        <label>
            Response:
            <select name="initial_fillings" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <p><strong>Dentures: <span class="required">*</span></strong></p>
        <p>I understand that wearing of dentures can be difficult. Sore spots, altered speech & difficulty in eating are common problems. Immediate dentures may require considerable adjusting as the tissues heal.</p>
        <label>
            Response:
            <select name="initial_dentures" required>
                <option value="">Select an option</option>
                <option value="Agree">Agree</option>
                <option value="Disagree">Disagree</option>
            </select>
        </label>

        <label><strong>Upload a valid ID with your signature: <span class="required">*</span></strong></label>
        <input type="file" name="valid_id" accept="image/*" required>
        
        <p><strong>I understand that dentistry is not an exact science and that no dentist can properly guarantee results.</strong></p>
        <label>
            <input type="checkbox" id="consentCheckbox" required>
            I understand and Consent
        </label>

        <br><br>
        <button type="submit" id="submitButton" disabled>Submit</button>
    </form>

    <script>
        const checkbox = document.getElementById('consentCheckbox');
        const submitButton = document.getElementById('submitButton');

        checkbox.addEventListener('change', () => {
            submitButton.disabled = !checkbox.checked;
        });
    </script>
</body>
</html>