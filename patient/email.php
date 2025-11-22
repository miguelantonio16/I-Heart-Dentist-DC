<?php
function send_email($to, $subject, $body) {
    $headers = "From: no-reply@yourdomain.com\r\n";
    $headers .= "Reply-To: no-reply@yourdomain.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // Send the email using PHP's mail() function
    if(mail($to, $subject, $body, $headers)) {
        return true;
    } else {
        return false;
    }
}
?>
