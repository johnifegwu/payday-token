<?php

// connect_wallet.php

$config = include('config.php');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get environment variables for MySQL configuration
$dbServer = $config['db_server'];
$dbUser = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];

// Define the PayDay Token distribution limit
$distributionLimit = 70000;
$paidUsersCount = 0;

if (isset($_SESSION['telegram_id'])) {
    $telegramId = $_SESSION['telegram_id'];
    $conn = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    try {
        // Check if the number of paid users has reached the limit
        $sql = "SELECT COUNT(*) as totalPaidUsers FROM users WHERE wallet_connected = 1";
        $result = $conn->query($sql);

        if (!$result) {
            die("Error in query: " . $conn->error);
        }

        $row = $result->fetch_assoc();
        $paidUsersCount = $row['totalPaidUsers'];

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    } finally {
        $conn->close();
    }

} else {
    echo json_encode(['error' => 'User not logged in']);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connect TON Wallet</title>
    <link rel="icon" href="https://tg.pday.online/imgs/paydayicon.png" type="image/png">
    <script src="https://unpkg.com/@tonconnect/ui@latest/dist/tonconnect-ui.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@tonconnect/ui@latest/dist/tonconnect-ui.min.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1a1a1a;
            color: #d4af37;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            overflow-x: hidden;
        }

        .main-container {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            width: 100%;
            height: 100vh;
            padding: 0 20px;
            box-sizing: border-box;
            overflow-y: auto;
        }

        .container {
            background-color: #262626;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
            overflow-y: auto;
            margin-bottom: 80px;
            /* To avoid overlap with bottom tab */
        }

        .links {
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
            padding-bottom: 20px;
        }

        h1 {
            color: #ffcc00;
            font-size: 1.6em;
            margin-bottom: 15px;
        }

        p {
            color: #d4af37;
            font-size: 1em;
        }

        button {
            background-color: #ffcc00;
            color: #1a1a1a;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
            width: 100%;
            max-width: 300px;
            font-size: 1em;
            font-weight: bold;
        }

        button:hover {
            background-color: #ffd700;
        }

        button:disabled {
            background-color: #333;
            color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
        }

        .info {
            margin: 15px 0;
        }

        .logo {
            margin-bottom: 15px;
            max-width: 60%;
            height: auto;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .links a {
            color: #ffcc00;
            text-decoration: none;
            margin: 0 5px;
        }

        .links a:hover {
            text-decoration: underline;
        }

        footer {
            font-size: 12px;
            color: #d4af37;
            padding: 10px;
            text-align: center;
        }

        /* General styles for desktop and mobile */
        .bottom-tab {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #333;
            display: flex;
            justify-content: space-around;
            padding: 10px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            /* Make sure it's above other elements */
        }

        .bottom-tab button {
            background-color: #ffcc00;
            color: #1a1a1a;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100px;
            font-size: 1em;
        }

        .bottom-tab button:hover {
            background-color: #ffd700;
        }

        .bottom-tab button:disabled {
            background-color: #333;
            color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
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
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #d4af37;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 10px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }
        /* Mobile-specific adjustments */
        @media (max-width: 600px) {
            .bottom-tab {
                padding: 8px;
                /* Reduce padding for mobile */
            }

            .message {
                font-size: 14px;
            }

            .bottom-tab button {
                width: 80px;
                /* Smaller button width for mobile */
                padding: 8px;
                /* Reduce padding */
                font-size: 0.9em;
                /* Slightly smaller font size */
            }

            .bottom-tab button:hover {
                background-color: #ffdd33;
                /* Slightly lighter hover effect for mobile */
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="logo">
        <img src="imgs/payday.png" alt="PayDay Token Logo" width="100%" height="100%">
    </div>
    <div class="container">
        <h1>Connect Your TON Wallet</h1>

        <?php if ($paidUsersCount >= $distributionLimit) { ?>
            <div class="message error">
                <p>We have reached the maximum number of participants for the PayDay Token Distribution.</p>
                <p>Thank you for your interest!</p>
            </div>
        <?php } else { ?>
            <button id="connectWalletButton">Connect Wallet & pay 0.2 TON gass fee.</button>
            <button id="payNowButton" disabled>Pay Now.</button>
            <div class="info" style="font-style: italic; color: #D3D3D3;">
                <p>Use Telegram Wallet on a mobile devices.</p>
                <p>The rest of the Wallets works fine on Desktop.</p>
            </div>
            <div id="message" class="message"></div>
        <?php } ?>
    </div>
    <script src="https://pday.online/dist/tonweb.js"></script>
    <script src="https://tg.pday.online/includes/65565552210.js"></script>
    <footer>
        &copy; 2024 PayDay Token. All Rights Reserved.
    </footer>
</body>

</html>