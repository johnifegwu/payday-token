<?php
// Load configuration file
$config = include('config.php');

// Fetch current round and database credentials
$current_round = $config['round'];
$servername = $config['db_server'];
$username = $config['db_user'];
$password = $config['db_password'];
$dbname = $config['db_name'];
$validSiteKey = $config['site_key']; // Set your SiteKey for validation

// Establish a secure connection with error handling
$conn = new mysqli($servername, $username, $password, $dbname);

// Check for connection error
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode(["error" => "Database connection failed. Please try again later."]));
}

// Get the POST parameter with fallback
$siteKey = $_POST['sitekey'] ?? null;

// Input validation for siteKey
if (empty($siteKey)) {
    die(json_encode(["error" => "Missing sitekey parameter."]));
}

// Authentication check
if ($siteKey !== $validSiteKey) {
    die(json_encode(["error" => "Invalid Site Key."]));
}

// Use prepared statements to prevent SQL injection
$query = "SELECT SUM(PayDayTokenDue) AS totalPayDayBought FROM transactions WHERE Round = ?";
$stmt = $conn->prepare($query);

// Check if the prepared statement was successful
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die(json_encode(["error" => "Error preparing query."]));
}

// Bind the parameter and execute the query
$stmt->bind_param('s', $current_round);
$stmt->execute();
$result = $stmt->get_result();

// Check for query execution errors
if ($result) {
    $row = $result->fetch_assoc();
    $totalPayDayBought = $row['totalPayDayBought'] ?? 0; // Default to 0 if no records
    echo json_encode(["totalPayDayBought" => $totalPayDayBought]);
} else {
    error_log("Error fetching data: " . $conn->error);
    die(json_encode(["error" => "Error fetching total PayDayTokenDue."]));
}

// Close the statement and connection
$stmt->close();
$conn->close();
?>
