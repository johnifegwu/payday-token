<?php
// Rate Limiting
$under_construction = true; // Set to false after site has gone live
$LIMIT = 5;
$IP = $_SERVER['REMOTE_ADDR'];
$TIME_FRAME = 60; // Seconds
$DB_FILE = "var/db/rate_limit.db";

// Create or open the SQLite database
$db = new SQLite3($DB_FILE);
$db->exec("CREATE TABLE IF NOT EXISTS requests (ip TEXT, timestamp INTEGER);");

// Get current timestamp
$NOW = time();

// Clean up old entries from the database
$db->exec("DELETE FROM requests WHERE timestamp < $NOW - $TIME_FRAME;");

// Use parameterized queries to count requests
$stmt = $db->prepare("SELECT COUNT(*) FROM requests WHERE ip = ? AND timestamp >= ?");
$stmt->bindValue(1, $IP, SQLITE3_TEXT);
$stmt->bindValue(2, $NOW - $TIME_FRAME, SQLITE3_INTEGER);
$result = $stmt->execute();
$COUNT = $result->fetchArray(SQLITE3_NUM)[0];

if ($COUNT >= $LIMIT) {
    header('Content-type: text/html');
    echo "<html><body><h1>Rate limit exceeded. Try again later.</h1></body></html>";
    exit;
}

// Log the request with timestamp using a prepared statement
$stmt = $db->prepare("INSERT INTO requests (ip, timestamp) VALUES (?, ?)");
$stmt->bindValue(1, $IP, SQLITE3_TEXT);
$stmt->bindValue(2, $NOW, SQLITE3_INTEGER);
$stmt->execute();

// Check for construction mode
if ($under_construction == true) {
    // Display under construction page
    header('Content-type: text/html');
    readfile("tg_app.html");
    $db->close();
    exit;
}

// Database Connection (MySQL)
$servername = "localhost";
$username = "paydayto_tg";
$password = "your_database_password";
$dbname = "paydayto_tg";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check MySQL connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create users table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  telegram_id VARCHAR(255) UNIQUE,
  ton_wallet VARCHAR(255) UNIQUE,
  tokens INT DEFAULT 0,
  linkedin_followed BOOLEAN DEFAULT FALSE,
  linkedin_liked BOOLEAN DEFAULT FALSE,
  twitter_followed BOOLEAN DEFAULT FALSE,
  twitter_retweeted BOOLEAN DEFAULT FALSE,
  wallet_connected BOOLEAN DEFAULT FALSE,
  last_api_call TIMESTAMP NULL DEFAULT NULL
)";

if ($conn->query($sql) === FALSE) {
    error_log("Error creating table: " . $conn->error);
}

// Telegram Integration
$botToken = "YOUR_TELEGRAM_BOT_TOKEN"; 
$website = "https://api.telegram.org/bot" . $botToken;

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
    $chatID = $update["message"]["chat"]["id"];
    $message = $update["message"]["text"];

    // Input validation
    if (!is_numeric($chatID) || empty($message)) {
        error_log("Invalid chatID or message received.");
        exit;
    }

    if ($message == "/start") {
        // Prepare statement to insert the user's Telegram ID
        $stmt = $conn->prepare("INSERT IGNORE INTO users (telegram_id) VALUES (?)");
        $stmt->bind_param("s", $chatID);

        if ($stmt->execute()) {
            // Start a session to track the user
            session_start();
            $_SESSION['telegram_id'] = $chatID;

            // Send a welcome message
            $reply = "Welcome to the PayDay Token Distribution! Please follow us on LinkedIn to continue.\n\n";
            $reply .= "[Your Website URL]";
            $response = file_get_contents($website . "/sendMessage?chat_id=" . $chatID . "&text=" . urlencode($reply));

            if ($response === false) {
                error_log("Failed to send message to Telegram chat_id: " . $chatID);
            }
        } else {
            error_log("Error inserting user into the database: " . $stmt->error);
        }
        $stmt->close(); // Close the prepared statement
    }
}

// Close MySQL connection
$conn->close();

// Close SQLite connection
$db->close();

?>
<!DOCTYPE html>
<html>
<head>
    <title>PayDay Token Distribution</title>
    <link rel="icon" href="https://tg.pday.online/images/paydayicon.png" type="image/png">
    <style>
        body {
            background-color: #222;
            color: gold;
            font-family: sans-serif;
            text-align: center;
        }
        a {
            color: gold;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .logo {
            width: 200px;
            height: auto;
            margin: 20px auto;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<img src="images/payday.png" alt="PayDay Token Logo" class="logo">

<h1>Welcome to the PayDay Token Distribution!</h1>

<p>Tokens earned: <span id="token-count">0</span></p>

<div id="linkedin-follow">
    <a href="https://www.linkedin.com/company/payday-token/about/?viewAsMember=true" target="_blank">1. Follow on LinkedIn (200000 tokens)</a><br>
</div>

<div id="linkedin-like" style="display: none;">
    <a href="https://www.linkedin.com/posts/payday-token_crypto-blockchain-tokenpresale-activity-7249290163904757760-GV6Z?utm_source=share&utm_medium=member_desktop" target="_blank">2. Like and Repost our LinkedIn Post (200000 tokens)</a><br>
</div>

<div id="twitter-follow" style="display: none;">
    <a href="https://x.com/token_payday" target="_blank">3. Follow us on Twitter (200000 tokens)</a><br>
</div>

<div id="twitter-retweet" style="display: none;">
    <a href="https://x.com/token_payday/status/1843531784899981646" target="_blank">4. Like and Retweet our Twitter Post (200000 tokens)</a><br>
</div>

<div id="connect-wallet" style="display: none;">
    <a href="connect_wallet.php">5. Connect TON Wallet (200000 tokens)</a>
</div>

<script>
    function checkTaskCompletion() {
        $.ajax({
            url: 'check_tasks.php',
            type: 'GET',
            success: function(response) {
                const tasks = JSON.parse(response);

                // Update token count
                let tokens = 0;
                if (tasks.linkedin_followed) tokens += 200000;
                if (tasks.linkedin_liked) tokens += 200000;
                if (tasks.twitter_followed) tokens += 200000;
                if (tasks.twitter_retweeted) tokens += 200000;
                $('#token-count').text(tokens);

                if (tasks.linkedin_followed) {
                    $('#linkedin-follow').hide();
                    $('#linkedin-like').show();
                }
                if (tasks.linkedin_liked) {
                    $('#linkedin-like').hide();
                    $('#twitter-follow').show();
                }
                if (tasks.twitter_followed) {
                    $('#twitter-follow').hide();
                    $('#twitter-retweet').show();
                }
                if (tasks.twitter_retweeted) {
                    $('#twitter-retweet').hide();
                    $('#connect-wallet').show();
                }
            }
        });
    }

    setInterval(checkTaskCompletion, 5000);
</script>
</body>
</html>
