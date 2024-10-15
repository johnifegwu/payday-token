<?php

// connect_wallet.php

$config = include('config.php');

// Get environment variables for MySQL configuration
$dbServer = $config['db_server'];
$dbUser = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];

$conn = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define the PayDay Token distribution limit
$distributionLimit = 70000;

// Check if the number of paid users has reached the limit
$sql = "SELECT COUNT(*) as totalPaidUsers FROM users WHERE wallet_connected = 1";
$result = $conn->query($sql);

if (!$result) {
    die("Error in query: " . $conn->error);
}

$row = $result->fetch_assoc();
$paidUsersCount = $row['totalPaidUsers'];

$conn->close();

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

    <script>
        const tonweb = new window.TonWeb();

        const tonconnectUI = new TON_CONNECT_UI.TonConnectUI({
            manifestUrl: 'https://tg.pday.online/tonconnect-manifest.json',
            buttonRootId: 'tonconnect-button'
        });

        tonconnectUI.on('connect', async (wallet) => {
            const mechineaddress = new TonWeb.utils.Address(wallet.account.address);
            const address = mechineaddress.toString(isUserFriendly = true);

            // Function to update the message div
            function showMessage(type, text) {
                const messageDiv = document.getElementById('message');
                messageDiv.className = 'message ' + type;
                messageDiv.innerHTML = text;
            }

            // Simulate collecting 0.2 TON after a delay
            setTimeout(async () => {
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
                                        data: { address: address },
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
                }
            }, 2000); // Delay of 2 seconds after wallet connection
        });
    </script>
<?php } ?>


</body>

</html>