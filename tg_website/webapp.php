<?php
$config = include('config.php');

// Get environment variables for MySQL configuration
$dbServer = $config['db_server'];
$dbUser = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];

//Post parameters
$tgWebAppStartParam = "";
$tgWebAppData = null;
$chat_instance = null;
$chat_type = null;
$start_param = null;
$auth_date = null;
$hash = null;
$tgWebAppVersion = null;
$tgWebAppPlatform = null;
$tgWebAppThemeParams = null;

if (isset($_POST['full_url'])) {
    // Get the full URL from the POST request
    $full_url = $_POST['full_url'];

    // Parse the URL to extract the parts (scheme, host, path, query, fragment)
    $parsed_url = parse_url($full_url);

    // Extract the query parameters (everything before the # sign)
    parse_str($parsed_url['query'], $query_params);

    // Assign query parameters to variables
    $tgWebAppStartParam = $query_params['tgWebAppStartParam'] ?? null;

    // If there's a fragment (everything after the # sign)
    if (isset($parsed_url['fragment'])) {
        // Parse the fragment part as a query string
        $fragment = $parsed_url['fragment'];
        parse_str($fragment, $fragment_params);

        // Assign fragment parameters to variables
        $tgWebAppData = $fragment_params['tgWebAppData'] ?? null;
        $chat_instance = $fragment_params['chat_instance'] ?? null;
        $chat_type = $fragment_params['chat_type'] ?? null;
        $start_param = $fragment_params['start_param'] ?? null;
        $auth_date = $fragment_params['auth_date'] ?? null;
        $hash = $fragment_params['hash'] ?? null;
        $tgWebAppVersion = $fragment_params['tgWebAppVersion'] ?? null;
        $tgWebAppPlatform = $fragment_params['tgWebAppPlatform'] ?? null;
        $tgWebAppThemeParams = $fragment_params['tgWebAppThemeParams'] ?? null;
    }

} else {
    invalidRequestPage("full_url not set");
}

$sitekey = $config['site_key'];

//Validate this request
if (is_null($tgWebAppStartParam) || $tgWebAppStartParam != $sitekey) {
    // serve the invalid page
    invalidRequestPage("Invalid site key: $tgWebAppStartParam");
}

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

//Define tgWebAppData parameters
if (is_null($tgWebAppData)) {
    //serve invalid page
    invalidRequestPage("tgWebAppData is null");
}

// Remove the 'user=' prefix to get the JSON string
$json_data = str_replace('user=', '', $tgWebAppData);

// Decode the JSON string into an associative array
$user_data = json_decode($json_data, true);

// Extract the desired values
$UserID = $user_data['id'] ?? null;
$FirstName = $user_data['first_name'] ?? null;
$LastName = $user_data['last_name'] ?? null;
$UserName = $user_data['username'] ?? null;
$Language = $user_data['language_code'] ?? null;

if (is_null($UserID)) {
    invalidRequestPage("UserID:$UserID");
}

// Check if session is already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if the user exists in the database
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->bindParam(1, $UserID, PDO::PARAM_STR);
    
    // Execute the query
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            // User exists, track them in session
            $_SESSION['telegram_id'] = $UserID;
        } else {
            // Insert the new user's Telegram ID
            $stmt = $db->prepare("INSERT IGNORE INTO users (telegram_id, first_name, last_name, user_name, lang) 
        VALUES (:telegram_id, :first_name, :last_name, :user_name, :lang)");

            // Bind all parameters including the missing ':lang'
            $stmt->bindParam(':telegram_id', $UserID, PDO::PARAM_STR);
            $stmt->bindParam(':first_name', $FirstName, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $LastName, PDO::PARAM_STR);
            $stmt->bindParam(':user_name', $UserName, PDO::PARAM_STR);
            $stmt->bindParam(':lang', $Language, PDO::PARAM_STR);  // Bind the missing ':lang'

            // Execute the query and handle results
            if ($stmt->execute()) {
                $_SESSION['telegram_id'] = $UserID;

                // Send a welcome message
                $reply = "Hey $FirstName,\n\n";
                $reply .= "Welcome to the PayDay Token Distribution!\n\n";
                $reply .= "Click the PLAY button above to receive your 1,000,000 PDAY Tokens!\n\n";
                $reply .= "Hurry, there's a limited distribution of PDAY Tokens.\n\n";
                $reply .= "Follow us on LinkedIn to stay updated.";
                $response = file_get_contents($website . "/sendMessage?chat_id=" . $chat_instance . "&text=" . urlencode($reply));

                if ($response === false) {
                    error_log("Failed to send message to Telegram chat_id: " . $chat_instance);
                }
            } else {
                error_log("Error inserting user into the database: " . $stmt->errorInfo()[2]);
            }
        }
    } else {
        error_log("Error checking user existence: " . $stmt->errorInfo()[2]);
    }
} catch (PDOException $e) {
    error_log("Error checking user existence: " . $e->getMessage());
    invalidRequestPage("Error checking user existence: " . $e->getMessage());
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
 * Serve the invalid request page.
 */
function invalidRequestPage($Where)
{
    header('Content-type: text/html');
    echo "<html><body><h1>INVALID REQUEST!<br/>$Where</h1></body></html>";
    exit;
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

    // Check if the columns already exist
    try {
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'first_name'");
        $firstNameExists = $stmt->fetchColumn() !== false;

        if (!$firstNameExists) {
            // Add the new columns
            $db->exec("ALTER TABLE users 
                        ADD COLUMN first_name VARCHAR(255),
                        ADD COLUMN last_name VARCHAR(255),
                        ADD COLUMN user_name VARCHAR(255),
                        ADD COLUMN lang VARCHAR(10)");
        }
    } catch (PDOException $e) {
        // Handle any potential errors
        echo "Error checking or altering table: " . $e->getMessage();
    }
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
            padding: 0 20px;
            box-sizing: border-box;
        }

        .container {
            background-color: #262626;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
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

        button:disabled {
            background-color: #333;
            color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
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
        <div id="telegram-id" style="text-align: left; color: #ffcc00; margin-bottom: 10px;">
            TG ID: <span id="telegramIdDisplay"></span>
        </div>
        <p style="font-size: 18px; color: #ffcc00; font-weight: 600;">
            $: <span id="token-count">0</span>
        </p>

        <button id="linkedInFollowBtn">Follow on LinkedIn 200,000 $PDAY</button>
        <button id="linkedInLikeBtn" disabled>Like and Repost our LinkedIn Post 200,000 $PDAY</button>
        <button id="twitterFoollowBtn" disabled>Follow us on Twitter 200,000 $PDAY</button>
        <button id="twitterRetweetBtn" disabled>Like and Retweet our Twitter Post 200,000 $PDAY</button>
        <button id="connectWalletBtn" disabled>Connect TON Wallet 200,000 tokens</button>

        <div class="info" id="taskStatus"></div>
    </div>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://tg.pday.online/includes/545387treyt75545.js"></script>
    <script>
        const telegramId = "<?php echo $_SESSION['telegram_id']; ?>";
        document.getElementById('telegramIdDisplay').innerText = telegramId;
    </script>

    <footer>
        &copy; 2024 PayDay Token. All Rights Reserved.
    </footer>

</body>

</html>