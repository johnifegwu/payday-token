<?php

// Rate Limiting Configuration
$LIMIT = 5; // Max requests allowed
$IP = $_SERVER['REMOTE_ADDR']; // Client's IP address
$TIME_FRAME = 60; // Time frame in seconds
$DB_FILE = "var/db/rate_limit.db"; // SQLite database file

// Create or open the SQLite database
$db = new SQLite3($DB_FILE);

// Create the table if it doesn't exist using a prepared statement
$db->exec("CREATE TABLE IF NOT EXISTS requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT,
    timestamp INTEGER
)");

// Get the current timestamp
$NOW = time();

// Clean up old entries that are outside of the time frame
$stmt_cleanup = $db->prepare("DELETE FROM requests WHERE timestamp < ?");
$stmt_cleanup->bindValue(1, $NOW - $TIME_FRAME, SQLITE3_INTEGER);
$stmt_cleanup->execute();

// Use a parameterized query to count requests from the IP in the defined time frame
$stmt_count = $db->prepare("SELECT COUNT(*) FROM requests WHERE ip = ? AND timestamp >= ?");
$stmt_count->bindValue(1, $IP, SQLITE3_TEXT);
$stmt_count->bindValue(2, $NOW - $TIME_FRAME, SQLITE3_INTEGER);
$result = $stmt_count->execute();
$COUNT = $result->fetchArray(SQLITE3_NUM)[0];

// Check if the request count exceeds the limit
if ($COUNT >= $LIMIT) {
    header('Content-type: text/html');
    echo "<html><body><h1>Rate limit exceeded. Try again later.</h1></body></html>";
    exit;
}

// Log the request with the current timestamp using a prepared statement
$stmt_insert = $db->prepare("INSERT INTO requests (ip, timestamp) VALUES (?, ?)");
$stmt_insert->bindValue(1, $IP, SQLITE3_TEXT);
$stmt_insert->bindValue(2, $NOW, SQLITE3_INTEGER);
$stmt_insert->execute();

// Allow access to the presale page
header('Content-type: text/html');
readfile("presale.html");

// Close the database connection
$db->close();

?>
