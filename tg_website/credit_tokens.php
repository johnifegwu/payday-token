<?php
// credit_tokens.php

$config = include('config.php');
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

    // Process only POST requests
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Validate and sanitize user input
        if (isset($_POST["address"]) && !empty($_POST["address"])) {
            $address = $conn->real_escape_string($_POST["address"]);

            // Check if the address exists first (optional)
            $sql_check = "SELECT ton_wallet FROM users WHERE ton_wallet = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("s", $address);
            $stmt_check->execute();
            $result = $stmt_check->get_result();

            // If the address exists, update (if needed)
            if ($result->num_rows > 0) {
                $stmt_check->close(); // Close the check statement

                // No tokens credited for payment task, just log or respond
                echo "No tokens credited, but address is valid.";

            } else {
                // Handle case where the wallet address does not exist
                echo "Address not found, ";
            }

        } else {
            echo "Invalid address.";
        }
    } else {
        echo "Invalid request method.";
    }

    $conn->close();
} else {
    echo json_encode(['error' => 'User not logged in']);
}

?>