<?php
// credit_tokens.php

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

if (isset($_SESSION['telegram_id'])) {
    $telegramId = $_SESSION['telegram_id'];
    // Create a database connection using MySQLi with error handling
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    try {
        // Process only POST requests
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Validate and sanitize user input
            if (isset($_POST["address"]) && !empty($_POST["address"]) && isset($_POST["amount"]) && !empty($_POST["amount"])) {
                $address = $conn->real_escape_string($_POST["address"]);
                $amount = intval($$conn->real_escape_string($_POST["amount"]));

                // Check if the address exists first (optional)
                $sql_check = "SELECT ton_wallet FROM users WHERE ton_wallet = ? AND telegram_id = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("s", $address);
                $stmt_check->bind_param("s", $telegramId);
                $stmt_check->execute();
                $result = $stmt_check->get_result();

                // If the address exists, update (if needed)
                if ($result->num_rows > 0) {
                    $stmt_check->close(); // Close the check statement

                    //Update users table set tokens = $amount
                    $sql_update = "UPDATE users SET tokens = ? WHERE ton_wallet = ? AND telegram_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("i", $amount);
                    $stmt_update->bind_param("s", $address);
                    $stmt_check->bind_param("s", $telegramId);
                    $stmt_update->execute();
                    // No tokens credited for payment task, just log or respond
                    echo "Credited with $amount PDAY, address is valid.";

                } else {
                    // Handle case where the wallet address does not exist
                    //Update users table set tokens = $amount
                    $sql_update = "UPDATE users SET tokens = ?, ton_wallet = ? WHERE telegram_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("i", $amount);
                    $stmt_update->bind_param("s", $address);
                    $stmt_check->bind_param("s", $telegramId);
                    $stmt_update->execute();
                    echo "Address added and $amount PDAY credited. ";
                }

            } else {
                echo "Invalid address. or amount";
            }
        } else {
            echo "Invalid request method.";
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    } finally {
        $conn->close();
    }
} else {
    echo json_encode(['error' => 'User not logged in']);
}

?>