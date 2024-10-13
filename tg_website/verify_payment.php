<?php

// verify_payment.php

use Toncenter\ToncenterApi;
use mysqli;

require_once 'vendor/autoload.php';
$config = include('config.php');

// Configuration
const SERVERNAME = $config['db_server'];
const USERNAME = $config['db_user'];
const PASSWORD = $config['db_password'];
const DBNAME = $config['db_name'];
const PAYDAY_TOKEN_WALLET = "UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2";
const TON_API_KEY = $config['ton_api_key'];
const PAYMENT_LIMIT = 70000;
const REQUIRED_AMOUNT = 0.2;
const TON_DIVISOR = 1000000000;

// Database Connection
function getDatabaseConnection() {
    $conn = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Check if payment limit is reached
function isPaymentLimitReached($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS paidUsersCount FROM users WHERE wallet_connected = TRUE");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['paidUsersCount'] >= PAYMENT_LIMIT;
}

// Verify transaction in the TON blockchain
function verifyTransaction($address, $toncenterApi) {
    $transactions = $toncenterApi->getTransactions(PAYDAY_TOKEN_WALLET, ['limit' => 100]);
    foreach ($transactions['result'] as $transaction) {
        $source = $transaction['in_msg']['source'];
        $amount = $transaction['in_msg']['value'] / TON_DIVISOR;
        if ($source === $address && $amount == REQUIRED_AMOUNT) {
            return true;
        }
    }
    return false;
}

// Update user wallet status in the database
function updateUserWalletStatus($conn, $address) {
    $stmt = $conn->prepare("UPDATE users SET wallet_connected = TRUE WHERE ton_wallet = ?");
    $stmt->bind_param("s", $address);
    $stmt->execute();
}

// Main Logic
try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $address = $_POST["address"] ?? '';

        if (empty($address)) {
            throw new Exception("Address not provided");
        }

        $conn = getDatabaseConnection();

        if (isPaymentLimitReached($conn)) {
            echo "disabled";
        } else {
            $toncenterApi = new ToncenterApi(['apiKey' => TON_API_KEY]);
            if (verifyTransaction($address, $toncenterApi)) {
                updateUserWalletStatus($conn, $address);
                echo "success";
            } else {
                echo "failed";
            }
        }

        $conn->close();
    }
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
}

?>
