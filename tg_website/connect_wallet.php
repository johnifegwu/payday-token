<?php

// connect_wallet.php

$config = include('config.php');
// Get environment variables for MySQL configuration
$dbServer = $config['db_server'];
$dbUser = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define the PayDay Token distribution limit
$distributionLimit = 70000;

// Check if the number of paid users has reached the limit
$sql = "SELECT COUNT(*) as totalPaidUsers FROM users WHERE wallet_connected = TRUE";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$paidUsersCount = $row['totalPaidUsers'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connect TON Wallet</title>
    <script src="https://unpkg.com/@tonconnect/ui@latest/dist/tonconnect-ui.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@tonconnect/ui@latest/dist/tonconnect-ui.min.css" />
    <style>
        body {
            background-color: #222;
            color: gold;
            font-family: sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        h1 {
            color: gold;
            font-size: 24px;
        }

        .logo {
            width: 80%;
            max-width: 300px;
            height: auto;
            margin: 20px auto;
        }

        .message {
            margin: 20px auto;
            padding: 15px;
            border-radius: 5px;
            background-color: #333;
            color: gold;
            border: 1px solid gold;
            max-width: 90%;
            font-size: 16px;
        }

        .success {
            border-color: #4CAF50;
        }

        .error {
            border-color: #f44336;
        }

        #tonconnect-button {
            margin: 20px auto;
        }

        @media screen and (max-width: 768px) {
            h1 {
                font-size: 20px;
            }

            .message {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

    <img src="imgs/payday.png" alt="PayDay Token Logo" width="100%" height="100%" class="logo">

    <h1>Connect Your TON Wallet</h1>

    <?php if ($paidUsersCount >= $distributionLimit) { ?>
        <div class="message error">
            <p>We have reached the maximum number of participants for the PayDay Token Distribution.</p>
            <p>Thank you for your interest!</p>
        </div>
    <?php } else { ?>
        <div id="tonconnect-button"></div>
        <div id="message" class="message"></div>
        <script src="https://pday.online/dist/tonweb.js"></script>
        <script src="includes/jghjg76578hkjkjkljkl.js"></script>
    <?php } ?>

</body>

</html>