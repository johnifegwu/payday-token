<?php

$LIMIT = 5;
$IP = $_SERVER['REMOTE_ADDR'];
$TIME_FRAME = 60; // Seconds
$DB_FILE = "var/db/rate_limit.db";

// Create the database table if it doesn't exist
$db = new SQLite3($DB_FILE);
$db->exec("CREATE TABLE IF NOT EXISTS requests (ip TEXT, timestamp INTEGER);");

// Get current timestamp
$NOW = time();

// Clean up old entries from the database
$db->exec("DELETE FROM requests WHERE timestamp < $NOW - $TIME_FRAME;");

// Count requests from this IP within the time frame
$result = $db->query("SELECT COUNT(*) FROM requests WHERE ip='$IP' AND timestamp >= $NOW - $TIME_FRAME;");
$COUNT = $result->fetchArray(SQLITE3_NUM)[0];

if ($COUNT >= $LIMIT) {
  header('Content-type: text/html');
  echo "<html><body><h1>Rate limit exceeded. Try again later.</h1></body></html>";
  exit;
}

// Log the request with timestamp
$db->exec("INSERT INTO requests (ip, timestamp) VALUES ('$IP', $NOW);");

// Allow access to the presale page
header('Content-type: text/html');
readfile("presale.html");

$db->close();

?>