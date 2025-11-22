<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Failed</title>
    <link rel="stylesheet" href="../css/main.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .message-box {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .message-box h1 {
            color: #f44336;
        }
        .message-box p {
            color: #555;
        }
        .message-box a {
            color: #007bff;
            text-decoration: none;
        }
        .message-box a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <h1>Verification Failed</h1>
        <p>The verification link is invalid or has expired. Please try again.</p>
        <p><a href="resend-verification.php">Resend Verification Email</a></p>
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>