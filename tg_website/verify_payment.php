<?php

// verify_payment.php

use mysqli;

require_once 'vendor/autoload.php';
$config = include('config.php');

// Configuration
$SERVERNAME = $config['db_server'];
$USERNAME = $config['db_user'];
$PASSWORD = $config['db_password'];
$DBNAME = $config['db_name'];
$PAYDAY_TOKEN_WALLET = "UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2";
$TON_API_KEY = $config['ton_api_key'];
$PAYMENT_LIMIT = 70000;
$REQUIRED_AMOUNT = 0.2;
$TON_DIVISOR = 1000000000;

// Database Connection
function getDatabaseConnection($SERVERNAME, $USERNAME, $PASSWORD, $DBNAME) {
    $conn = new mysqli($SERVERNAME, $USERNAME, $PASSWORD, $DBNAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Check if payment limit is reached
function isPaymentLimitReached($conn, $PAYMENT_LIMIT) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS paidUsersCount FROM users WHERE wallet_connected = TRUE");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['paidUsersCount'] >= $PAYMENT_LIMIT;
}


function hasValidTransaction($address, $requiredAmount, $walletAddress, $apiKey) {
    $endpoint = "getTransactions";
    $params = [
      'address' => $walletAddress,
      'limit' => 100 
    ];
  
    $transactionsData = getToncenterData($endpoint, $params, $apiKey);
  
    if ($transactionsData && isset($transactionsData['result'])) {
      foreach ($transactionsData['result'] as $transaction) {
        // Check if 'in_msg' exists in the transaction
        if (isset($transaction['in_msg']['source']) && isset($transaction['in_msg']['value'])) {
          $source = $transaction['in_msg']['source'];
          $amount = $transaction['in_msg']['value'] / 1000000000; // Assuming TON_DIVISOR is 10^9
  
          if ($source === $address && $amount == $requiredAmount) {
            return true;
          }
        }
      }
    }
  
    return false; 
  }

  function getToncenterData($endpoint, $params = [], $apiKey = '') {
    $url = "https://api.toncenter.com/v2/" . $endpoint;
  
    // Add API key to parameters
    $params['api_key'] = $apiKey;
  
    // Build query string
    $queryString = http_build_query($params);
  
    // Complete URL with query string
    $url .= "?" . $queryString;
  
    // Make the HTTP request
    $response = file_get_contents($url);
  
    // Handle potential errors
    if ($response === false) {
      // Handle the error, e.g., log it or throw an exception
      error_log("Error fetching data from Toncenter API: " . $url);
      return false; 
    }
  
    // Decode the JSON response
    $data = json_decode($response, true);
  
    // Handle potential JSON decoding errors
    if (json_last_error() !== JSON_ERROR_NONE) {
      // Handle the error, e.g., log it or throw an exception
      error_log("Error decoding JSON response from Toncenter API: " . json_last_error_msg());
      return false;
    }
  
    return $data;
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

        $conn = getDatabaseConnection($SERVERNAME, $USERNAME, $PASSWORD, $DBNAME);

        if (isPaymentLimitReached($conn, $PAYMENT_LIMIT)) {
            echo "disabled";
        } else {

            if (hasValidTransaction($address, $REQUIRED_AMOUNT,  $PAYDAY_TOKEN_WALLET, $TON_API_KEY)) {
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
