<?php

use Toncenter\ToncenterApi;

require_once 'vendor/autoload.php'; 

// Database Connection
$servername = "your_database_server";
$username = "your_database_user";
$password = "your_database_password";
$dbname = "your_database_name";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $address = $_POST["address"];
  $paydayTokenWallet = "YOUR_PAYDAY_TOKEN_WALLET_ADDRESS"; 

  // Check if the number of paid users has reached the limit
  $sql = "SELECT COUNT(*) FROM users WHERE wallet_connected = TRUE";
  $result = $conn->query($sql);
  $row = $result->fetch_assoc();
  $paidUsersCount = $row['COUNT(*)'];

  if ($paidUsersCount >= 70000) {
    // Payment is disabled
    echo "disabled"; 
  } else {
    // TON SDK payment verification logic
    $toncenterApi = new ToncenterApi([
        'apiKey' => 'YOUR_TONCENTER_API_KEY', 
    ]);

    try {
        // Get transactions for the Payday Token wallet
        $transactions = $toncenterApi->getTransactions($paydayTokenWallet, [
            'limit' => 100, 
        ]);

        $paymentVerified = false;

        foreach ($transactions['result'] as $transaction) {
            if ($transaction['in_msg']['source'] === $address) {
                $amount = $transaction['in_msg']['value'] / 1000000000; 
                if ($amount >= 0.2) {
                    $paymentVerified = true;
                    break;
                }
            }
        }

        if ($paymentVerified) {
          // Credit tokens (no token reward for this task)

          // Update wallet_connected status in the database
          $sql = "UPDATE users SET wallet_connected = TRUE WHERE ton_wallet = '$address'";
          $conn->query($sql); 

          echo "success";
        } else {
          echo "failed"; 
        }

    } catch (\Exception $e) {
        echo "error: " . $e->getMessage();
    }
  }
}

$conn->close();

?>