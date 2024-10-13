<?php
$config = include('config.php');

// Get environment variables for MySQL configuration
$dbServer = $config['db_server'];
$dbUser = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];

// Rate Limiting
$under_construction = false; // Set to false after the site has gone live

// Check if "isadmin=true" is passed in the request (GET or POST)
if (isset($_REQUEST['isadmin']) && $_REQUEST['isadmin'] === 'true') {
    $under_construction = false;
}

// Rate Limiting Configuration
$LIMIT = 5; // Max requests allowed
$TIME_FRAME = 60; // Time frame in seconds

// Get client's IP address
$IP = $_SERVER['REMOTE_ADDR'];

// Establish database connection using PDO
try {
    // Create the DSN (Data Source Name) string
    $dsn = "mysql:host=$dbServer;dbname=$dbName;charset=utf8mb4";
    $db = new PDO($dsn, $dbUser, $dbPassword);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure the requests table exists
    createRequestsTable($db);

    // Current timestamp
    $NOW = time();

    // Clean up old entries and count current requests
    cleanUpOldRequests($db, $NOW - $TIME_FRAME);
    $COUNT = countRequests($db, $IP, $NOW - $TIME_FRAME);

    // Check request limit
    if ($COUNT >= $LIMIT) {
        sendRateLimitExceededResponse();
    }

    // Log the request
    logRequest($db, $IP, $NOW);

    // Serve the presale page if under construction
    if ($under_construction) {
        servePresalePage();
        exit;
    }

    // Creating and interacting with the users table
    createUsersTable($db);

} catch (PDOException $e) {
    echo "Connection failed: dbname=$dbName " . $e->getMessage();
}

// Telegram Integration
$botToken = $config['bot_token'];
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
        $stmt = $db->prepare("INSERT IGNORE INTO users (telegram_id) VALUES (?)");
        $stmt->bindParam(1, $chatID, PDO::PARAM_STR);

        if ($stmt->execute()) {
            // Start a session to track the user
            session_start();
            $_SESSION['telegram_id'] = $chatID;

            // Send a welcome message
            $reply = "Welcome to the PayDay Token! Please follow us on LinkedIn to continue.\n\n";
            $reply .= "https://tg.pday.online";
            $response = file_get_contents($website . "/sendMessage?chat_id=" . $chatID . "&text=" . urlencode($reply));

            if ($response === false) {
                error_log("Failed to send message to Telegram chat_id: " . $chatID);
            }
        } else {
            error_log("Error inserting user into the database: " . $stmt->errorInfo()[2]);
        }
    }
}

/**
 * Create the requests table if it doesn't exist.
 *
 * @param PDO $db
 */
function createRequestsTable(PDO $db)
{
    $db->exec("CREATE TABLE IF NOT EXISTS requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        timestamp INT NOT NULL
    )");
}

/**
 * Clean up old request entries from the database.
 *
 * @param PDO $db
 * @param int $timestamp
 */
function cleanUpOldRequests(PDO $db, int $timestamp)
{
    $stmt_cleanup = $db->prepare("DELETE FROM requests WHERE timestamp < :timestamp");
    $stmt_cleanup->bindValue(':timestamp', $timestamp, PDO::PARAM_INT);
    $stmt_cleanup->execute();
}

/**
 * Count the number of requests from a specific IP within the time frame.
 *
 * @param PDO $db
 * @param string $ip
 * @param int $timestamp
 * @return int
 */
function countRequests(PDO $db, string $ip, int $timestamp): int
{
    $stmt_count = $db->prepare("SELECT COUNT(*) FROM requests WHERE ip = :ip AND timestamp >= :timestamp");
    $stmt_count->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt_count->bindValue(':timestamp', $timestamp, PDO::PARAM_INT);
    $stmt_count->execute();
    return (int) $stmt_count->fetchColumn();
}

/**
 * Log a request in the database.
 *
 * @param PDO $db
 * @param string $ip
 * @param int $timestamp
 */
function logRequest(PDO $db, string $ip, int $timestamp)
{
    $stmt_insert = $db->prepare("INSERT INTO requests (ip, timestamp) VALUES (:ip, :timestamp)");
    $stmt_insert->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt_insert->bindValue(':timestamp', $timestamp, PDO::PARAM_INT);
    $stmt_insert->execute();
}

/**
 * Send a response indicating that the rate limit has been exceeded.
 */
function sendRateLimitExceededResponse()
{
    header('Content-type: text/html');
    echo "<html><body><h1>Rate limit exceeded. Try again later.</h1></body></html>";
    exit;
}

/**
 * Serve the presale page.
 */
function servePresalePage()
{
    header('Content-type: text/html');
    readfile("tg_app.html");
}

/**
 * Create the users table if it doesn't exist.
 *
 * @param PDO $db
 */
function createUsersTable(PDO $db)
{
    $db->exec("CREATE TABLE IF NOT EXISTS users (
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
    )");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayDay Token</title>
    <link rel="icon" href="https://tg.pday.online/imgs/paydayicon.png" type="image/png">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1a1a1a;
            color: #d4af37;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            overflow-x: hidden;
        }

        .main-container {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            height: 100vh;
        }

        .container {
            background-color: #262626;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
            margin: 0 auto;
            overflow-y: auto;
        }

        .links {
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
            padding-bottom: 20px;
        }

        h1 {
            color: #ffcc00;
            font-size: 1.6em;
            margin-bottom: 15px;
        }

        p {
            color: #d4af37;
            font-size: 1em;
        }

        button {
            background-color: #ffcc00;
            color: #1a1a1a;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
            width: 100%;
            max-width: 200px;
            font-size: 1em;
            font-weight: bold;
        }

        button:hover {
            background-color: #ffd700;
        }

        .info {
            margin: 15px 0;
        }

        .logo {
            margin-bottom: 15px;
            max-width: 60%;
            height: auto;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .links a {
            color: #ffcc00;
            text-decoration: none;
            margin: 0 5px;
        }

        .links a:hover {
            text-decoration: underline;
        }

        footer {
            font-size: 12px;
            color: #d4af37;
            padding: 10px;
            text-align: center;
        }

        /* Mobile-specific styling */
        @media (max-width: 600px) {
            body {
                overflow-y: scroll;
            }

            .container {
                height: 100vh;
                box-sizing: border-box;
            }

            h1 {
                font-size: 1.4em;
            }

            button {
                font-size: 0.9em;
            }

            .links {
                font-size: 10px;
            }

            footer {
                font-size: 10px;
            }
        }
    </style>
</head>

<body>

    <img src="imgs/payday.png" alt="PayDay Token Logo" class="logo">

    <div class="container">
        <h1>PayDay Token Distribution!</h1>

        <p>Tokens earned: <span id="token-count">0</span></p>

        <button id="linkedInFollowBtn">1. Follow on LinkedIn (200,000 $PDAY)</button>
        <button id="linkedInLikeBtn" disabled>2. Like and Repost our LinkedIn Post (200,000 $PDAY)</button>
        <button id="twitterFoollowBtn" disabled>3. Follow us on Twitter (200,000 $PDAY)</button>
        <button id="twitterRetweetBtn" disabled>4. Like and Retweet our Twitter Post (200,000 $PDAY)</button>
        <button id="connectWalletBtn">5. Connect TON Wallet (200,000 tokens)</button>

        <div class="info" id="taskStatus"></div>
    </div>

    <footer>
        &copy; 2024 PayDay Token. All Rights Reserved.
    </footer>

    <script src="includes/545387treyt75545.js"></script>

</body>

</html>