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
            <div class="info" style="font-style: italic; color: #b3b3b3;">
                <p>Use Telegram Wallet if you're on a mobile device..</p>
                <p>The rest of the Wallets works on Desktop.</p>
            </div>
            <div id="message" class="message"></div>
        <?php } ?>
    </div>
    <script src="https://pday.online/dist/tonweb.js"></script>
    <!-- <script src="includes/jghjg76578hkjkjkljkl.js"></script> -->
    <script>
        const tonweb = new window.TonWeb();
        const connectWalletButton = document.getElementById("connectWalletButton");
        const payNowButton = document.getElementById("payNowButton");
        let address = null;

        const currentIsConnectedStatus = tonconnectUI.connected;

        if (currentIsConnectedStatus) {
            await tonconnectUI.disconnect(); // disconnect any previously connected wallet
        }
        const wallet = await tonconnectUI.connectWallet();
        const mechineaddress = new TonWeb.utils.Address(wallet.account.address);
        const address = null;

        const tonconnectUI = new TON_CONNECT_UI.TonConnectUI({
            manifestUrl: 'https://tg.pday.online/tonconnect-manifest.json',
        });

        async function connectWallet() {
            connectWalletButton.disabled = true;
            try {
                if (!tonconnectUI) {
                    showMessage('error', "TonConnectUI not initialized.");
                    return;
                }

                const currentIsConnectedStatus = tonconnectUI.connected;

                if (currentIsConnectedStatus) {
                    await tonconnectUI.disconnect(); // disconnect any previously connected wallet
                }

                if (connectWalletButton.innerHTML == "Disconnect Wallet") {
                    connectWalletButton.innerHTML = "Connect Wallet"
                    return;
                }

                const wallet = await tonconnectUI.connectWallet();
                const mechineaddress = new TonWeb.utils.Address(wallet.account.address);
                address = mechineaddress.toString(isUserFriendly = true);
                showMessage(`Wallet connected: ${maskString(walletAddress)}`;
                connectWalletButton.innerHTML = "Disconnect Wallet";
                payNowButton.disabled = false;

            } catch (error) {
                console.error("Failed to connect wallet:", error);
                showMessage('error', "Failed to connect wallet.");
            }
        }

        // Function to update the message div
        function showMessage(type, text) {
            const messageDiv = document.getElementById('message');
            messageDiv.className = 'message ' + type;
            messageDiv.innerHTML = text;
        }

        async function transferTON() {
            if (!address) {
                showMessage('error', "Connect your wallet first.");
                transferStatus.innerHTML = ;
                return;
            }

            // collect 0.2 TON
            try {
                // Initiating the payment collection
                const tonAmount = 0.2; // TON amount to collect

                // Simulate TON transfer request (this will need a TON payment SDK in production)
                const paymentResponse = await tonconnectUI.sendTransaction({
                    validUntil: Date.now() + 60 * 20000, // valid for 20 minutes
                    messages: [{
                        address: "UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2",
                        amount: tonAmount * 1e9 // TON amount converted to nanoTON
                    }]
                });

                // Check if the payment was successful (this depends on the TON SDK response structure)
                if (paymentResponse) {
                    showMessage('success', "0.2 TON collected successfully. Verifying payment...");

                    // After payment is successful, verify payment via AJAX
                    $.ajax({
                        url: 'verify_payment.php',
                        type: 'POST',
                        data: { address: address },
                        success: function (response) {
                            if (response === 'success') {
                                $.ajax({
                                    url: 'credit_tokens.php',
                                    type: 'POST',
                                    data: { address: address, amount: 1000000 },
                                    success: function () {
                                        showMessage('success', "Wallet connected and 0.2 TON payment credited successfully!\nDistribution will be announced soon!!");
                                    },
                                    error: function () {
                                        showMessage('error', "Error crediting tokens. Please contact support.");
                                    }
                                });
                            } else if (response === 'disabled') {
                                showMessage('error', "The PayDay Token Distribution has reached its maximum capacity. Payment is currently disabled.");
                            } else {
                                showMessage('error', "Payment verification failed. Please ensure you have sent 0.2 TON.");
                            }
                        },
                        error: function () {
                            showMessage('error', "Error verifying payment. Please contact support.");
                        }
                    });
                } else {
                    showMessage('error', "Failed to collect payment. Please try again.");
                }
            } catch (error) {
                showMessage('error', "An error occurred during the payment process. Please try again.");
            } finally {
                payNowButton.disabled = true;
            }
        } catch (error) {
            console.error(error);
        } finally {
            payNowButton.disabled = true;
        }


        // tonconnectUI.on('connect', async (wallet) => {

        // });

        connectWalletButton.addEventListener('click', connectWallet);
        payNowButton.addEventListener('click', transferTON);

    </script>
    <footer>
        &copy; 2024 PayDay Token. All Rights Reserved.
    </footer>
</body>

</html>