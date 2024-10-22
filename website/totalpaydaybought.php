<?php
$config = include('config.php');

// Database credentials
$servername = $config['db_server'];
$username = $config['db_user'];
$password = $config['db_password'];
$dbname = $config['db_name'];
$validSiteKey = $config['site_key']; // Set your SiteKey for validation

// Create connection with error handling
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die(json_encode(["error" => "Database connection failed. Please try again later."]));
}

// POST parameter
$siteKey = $_POST['sitekey'] ?? null;

// Input validation
if (!$siteKey) {
    die(json_encode(["error" => "Missing sitekey parameter."]));
}

// Authentication check
if ($siteKey !== $validSiteKey) {
    die(json_encode(["error" => "Invalid Site Key."]));
}

// Fetch the sum of PayDayTokenDue
$query = "SELECT SUM(PayDayTokenDue) AS totalPayDayBought FROM transactions";
$result = $conn->query($query);

if ($result) {
    $row = $result->fetch_assoc();
    $totalPayDayBought = $row['totalPayDayBought'] ?? 0; // Default to 0 if no records
    echo json_encode(["totalPayDayBought" => $totalPayDayBought]);
} else {
    error_log("Error fetching data: " . $conn->error);
    die(json_encode(["error" => "Error fetching total PayDayTokenDue."]));
}

// Close connection
$conn->close();
?>
