<?php

// Load configuration
$config = include('config.php');

// Rate Limiting
$under_construction = true; // Set to false after the site has gone live

// Check if "isadmin=true" is passed in the request (GET or POST)
if (isset($_REQUEST['isadmin']) && $_REQUEST['isadmin'] === 'true') {
    $under_construction = false;
}

// MySQL configuration from environment variables
$dbServer = $config['db_server'];
$dbUser = $config['db_user'];
$dbPassword = $config['db_password'];
$dbName = $config['db_name'];

$totalEarned = 0;
$actions = [
    'enableLinkedInFollow' => true,
    'enableLinkedInRepost' => false,
    'enableTwitterFollow' => false,
    'enableTwitterRepost' => false,
    'walletConnected' => false
];

// Post parameters
$postParams = [
    'tgWebAppStartParam' => null,
    'tgWebAppData' => null,
    'chat_instance' => null,
    'chat_type' => null,
    'start_param' => null,
    'auth_date' => null,
    'hash' => null,
    'tgWebAppVersion' => null,
    'tgWebAppPlatform' => null,
    'tgWebAppThemeParams' => null
];

// Handle full_url if set
if (isset($_POST['full_url'])) {
    $full_url = $_POST['full_url'];
    $parsed_url = parse_url($full_url);

    // Assign query and fragment parameters
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $query_params);
        $postParams['tgWebAppStartParam'] = $query_params['tgWebAppStartParam'] ?? null;
    }

    if (isset($parsed_url['fragment'])) {
        parse_str($parsed_url['fragment'], $fragment_params);
        foreach ($fragment_params as $key => $value) {
            $postParams[$key] = $value ?? null;
        }
    }
} else {
    invalidRequestPage("full_url not set");
}

$siteKey = $config['site_key'];

// Validate the site key
if (is_null($postParams['tgWebAppStartParam']) || $postParams['tgWebAppStartParam'] !== $siteKey) {
    invalidRequestPage("Invalid site key: {$postParams['tgWebAppStartParam']}");
}

// Rate limiting setup
$LIMIT = 5; // Max requests allowed
$TIME_FRAME = 60; // Time frame in seconds
$IP = $_SERVER['REMOTE_ADDR'];

// Establish database connection using PDO
try {
    $dsn = "mysql:host=$dbServer;dbname=$dbName;charset=utf8mb4";
    $db = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Ensure the requests table exists and clean old entries
    createRequestsTable($db);
    cleanUpOldRequests($db, time() - $TIME_FRAME);

    // Count current requests
    if (countRequests($db, $IP, time() - $TIME_FRAME) >= $LIMIT) {
        sendRateLimitExceededResponse();
    }

    // Log the current request
    logRequest($db, $IP, time());

    // Serve presale page if under construction
    if ($underConstruction) {
        servePresalePage();
        exit;
    }

    // Ensure the users table exists
    createUsersTable($db);

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Telegram bot configuration
$botToken = $config['bot_token'];
$website = "https://api.telegram.org/bot" . $botToken;

// Validate tgWebAppData
if (is_null($postParams['tgWebAppData'])) {
    invalidRequestPage("tgWebAppData is null");
}

// Extract and decode tgWebAppData
$user_data = json_decode(str_replace('user=', '', $postParams['tgWebAppData']), true);
if (!$user_data || !isset($user_data['id'])) {
    invalidRequestPage("UserID is missing");
}

// Extract user data
$UserID = $user_data['id'];
$FirstName = $user_data['first_name'] ?? null;
$LastName = $user_data['last_name'] ?? null;
$UserName = $user_data['username'] ?? null;
$Language = $user_data['language_code'] ?? null;

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if user exists
    $stmt = $db->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$UserID]);

    if ($stmt->rowCount() > 0) {
        // User exists, fetch their data
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $_SESSION['telegram_id'] = $UserID;
        $totalEarned = calculateEarnings($user, $actions);
    } else {
        // Insert new user
        $stmt = $db->prepare("INSERT INTO users (telegram_id, first_name, last_name, user_name, lang) 
                              VALUES (:telegram_id, :first_name, :last_name, :user_name, :lang)");
        $stmt->execute([
            ':telegram_id' => $UserID,
            ':first_name' => $FirstName,
            ':last_name' => $LastName,
            ':user_name' => $UserName,
            ':lang' => $Language
        ]);
        $_SESSION['telegram_id'] = $UserID;

        // Send welcome message
        sendTelegramMessage($website, $postParams['chat_instance'], $FirstName);
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    invalidRequestPage("Database error");
}

// Helper functions

/**
 * Creates the requests table if it does not exist.
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
 * Cleans up old request entries.
 */
function cleanUpOldRequests(PDO $db, int $timestamp)
{
    $stmt = $db->prepare("DELETE FROM requests WHERE timestamp < :timestamp");
    $stmt->execute([':timestamp' => $timestamp]);
}

/**
 * Counts requests from a specific IP in the given timeframe.
 */
function countRequests(PDO $db, string $ip, int $timestamp): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM requests WHERE ip = :ip AND timestamp >= :timestamp");
    $stmt->execute([':ip' => $ip, ':timestamp' => $timestamp]);
    return (int) $stmt->fetchColumn();
}

/**
 * Logs a request.
 */
function logRequest(PDO $db, string $ip, int $timestamp)
{
    $stmt = $db->prepare("INSERT INTO requests (ip, timestamp) VALUES (:ip, :timestamp)");
    $stmt->execute([':ip' => $ip, ':timestamp' => $timestamp]);
}

/**
 * Calculates the total earnings for a user based on their actions.
 */
function calculateEarnings(array $user, array &$actions): int
{
    $total = $user['tokens'];

    // Calculate based on LinkedIn actions
    if ($user['linkedin_followed']) {
        $actions['enableLinkedInFollow'] = false;
        $total = 200000;
    }
    if ($user['linkedin_liked']) {
        $actions['enableLinkedInRepost'] = false;
        $total += 200000;
    } else {
        if (!$actions['enableLinkedInFollow']) {
            $actions['enableLinkedInRepost'] = true;
        }
    }

    // Calculate based on Twitter actions
    if ($user['twitter_followed']) {
        $actions['enableTwitterFollow'] = false;
        $total += 200000;
    } else {
        if (!$actions['enableLinkedInRepost']) {
            $actions['enableTwitterFollow'] = true;
        }
    }

    if ($user['twitter_retweeted']) {
        $actions['enableTwitterRepost'] = false;
        $total += 200000;
    } else {
        if (!$actions['enableTwitterFollow']) {
            $actions['enableTwitterRepost'] = true;
        }
    }

    // Wallet connection
    if ($user['wallet_connected']) {
        $actions['walletConnected'] = false;
        $total += 200000;
    } else {
        if (!$actions['enableTwitterRepost']) {
            $actions['walletConnected'] = true;
        }
    }

    $_SESSION['total'] = $total;
    $_SESSION['enableLinkedInFollow'] = $actions['enableLinkedInFollow'] ? "true" : "false";
    $_SESSION['enableLinkedInRepost'] = $actions['enableLinkedInRepost'] ? "true" : "false";
    $_SESSION['enableTwitterFollow'] = $actions['enableTwitterFollow'] ? "true" : "false";
    $_SESSION['enableTwitterRepost'] = $actions['enableTwitterRepost'] ? "true" : "false";
    $_SESSION['walletConnected'] = $actions['walletConnected'] ? "true" : "false";

    // Limit total earnings
    return min($total, 1000000);
}

/**
 * Sends a welcome message via Telegram.
 */
function sendTelegramMessage(string $website, string $chat_instance, string $firstName)
{
    $reply = "Hey $firstName,\n\n";
    $reply .= "Welcome to the PayDay Token Distribution!\n\n";
    $reply .= "Click the PLAY button above to receive your 1,000,000 PDAY Tokens!\n\n";
    $reply .= "Hurry, there's a limited distribution of PDAY Tokens.\n\n";
    $reply .= "Follow us on LinkedIn to stay updated.";

    file_get_contents("$website/sendMessage?chat_id=$chat_instance&text=" . urlencode($reply));
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
    // we return here to improve oerformance as the tables are already created

    // $db->exec("CREATE TABLE IF NOT EXISTS users (
    //     id INT AUTO_INCREMENT PRIMARY KEY,
    //     telegram_id VARCHAR(255) UNIQUE,
    //     ton_wallet VARCHAR(255) UNIQUE,
    //     tokens INT DEFAULT 0,
    //     linkedin_followed BOOLEAN DEFAULT FALSE,
    //     linkedin_liked BOOLEAN DEFAULT FALSE,
    //     twitter_followed BOOLEAN DEFAULT FALSE,
    //     twitter_retweeted BOOLEAN DEFAULT FALSE,
    //     wallet_connected BOOLEAN DEFAULT FALSE,
    //     last_api_call TIMESTAMP NULL DEFAULT NULL
    // )");

    // // Check if the columns already exist
    // try {
    //     $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'first_name'");
    //     $firstNameExists = $stmt->fetchColumn() !== false;

    //     if (!$firstNameExists) {
    //         // Add the new columns
    //         $db->exec("ALTER TABLE users 
    //                     ADD COLUMN first_name VARCHAR(255),
    //                     ADD COLUMN last_name VARCHAR(255),
    //                     ADD COLUMN user_name VARCHAR(255),
    //                     ADD COLUMN lang VARCHAR(10)");
    //     }
    // } catch (PDOException $e) {
    //     // Handle any potential errors
    //     echo "Error checking or altering table: " . $e->getMessage();
    // }
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
            justify-content: flex-start;
            align-items: center;
            width: 100%;
            height: 100vh;
            padding: 0 20px;
            box-sizing: border-box;
            overflow-y: auto;
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
            margin-bottom: 80px;
            /* To avoid overlap with bottom tab */
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
            max-width: 300px;
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

        /* General styles for desktop and mobile */
        .bottom-tab {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #333;
            display: flex;
            justify-content: space-around;
            padding: 10px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            /* Make sure it's above other elements */
        }

        .bottom-tab button {
            background-color: #ffcc00;
            color: #1a1a1a;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100px;
            font-size: 1em;
        }

        .bottom-tab button:hover {
            background-color: #ffd700;
        }

        .bottom-tab button:disabled {
            background-color: #333;
            color: rgba(255, 255, 255, 0.3);
            cursor: not-allowed;
        }

        /* Mobile-specific adjustments */
        @media (max-width: 600px) {
            .bottom-tab {
                padding: 8px;
                /* Reduce padding for mobile */
            }

            .bottom-tab button {
                width: 80px;
                /* Smaller button width for mobile */
                padding: 8px;
                /* Reduce padding */
                font-size: 0.9em;
                /* Slightly smaller font size */
            }

            .bottom-tab button:hover {
                background-color: #ffdd33;
                /* Slightly lighter hover effect for mobile */
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>

    <img src="imgs/payday.png" alt="PayDay Token Logo" class="logo">

    <div class="container">
        <h1>PayDay Token Distribution!</h1>
        <div id="telegram-id"
            style="display: flex; justify-content: space-between; align-items: center; color: #ffcc00; margin-bottom: 10px; margin-left: 20px; margin-right: 20px;">
            <div style="text-align: left;">
                TG ID: <span id="telegramIdDisplay"></span>
            </div>
            <div class="info" id="taskStatus" style="text-align: right;"></div>
        </div>
        <p style="font-size: 30px; color: #ffcc00; font-weight: 600;">
            $: <span id="token-count">0</span>
        </p>
        <button id="linkedInFollowBtn">Follow on LinkedIn 200,000 $PDAY</button>
        <button id="linkedInLikeBtn" disabled>Like and Repost our LinkedIn Post 200,000 $PDAY</button>
        <button id="twitterFoollowBtn" disabled>Follow us on Twitter 200,000 $PDAY</button>
        <button id="twitterRetweetBtn" disabled>Like and Retweet our Twitter Post 200,000 $PDAY</button>
        <button id="connectWalletBtn" disabled>Connect TON Wallet 200,000 tokens</button>
    </div>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://tg.pday.online/includes/545387treyt75545.js"></script>
    <script>
        // Convert session values into actual booleans
        const telegramId = "<?php echo $_SESSION['telegram_id']; ?>";
        const totalEearned = "<?php echo $_SESSION['total']; ?>";
        const enableLinkedInFollow = "<?php echo $_SESSION['enableLinkedInFollow']; ?>" === 'true';
        const enableLinkedInRepost = "<?php echo $_SESSION['enableLinkedInRepost']; ?>" === 'true';
        const enableTwitterFollow = "<?php echo $_SESSION['enableTwitterFollow']; ?>" === 'true';
        const enableTwitterRepost = "<?php echo $_SESSION['enableTwitterRepost']; ?>" === 'true';
        const walletConnected = "<?php echo $_SESSION['walletConnected']; ?>" === 'true';

        // Display telegram ID and token count
        document.getElementById('telegramIdDisplay').innerText = telegramId;
        document.getElementById('token-count').innerText = totalEearned.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");

        // Enable/disable buttons based on session values
        document.getElementById('linkedInFollowBtn').disabled = !enableLinkedInFollow;
        document.getElementById('linkedInLikeBtn').disabled = !enableLinkedInRepost;
        document.getElementById('twitterFoollowBtn').disabled = !enableTwitterFollow;
        document.getElementById('twitterRetweetBtn').disabled = !enableTwitterRepost;
        document.getElementById('connectWalletBtn').disabled = !walletConnected;

    </script>
    <!-- Fixed bottom tab bar -->
    <div class="bottom-tab">
        <button id="homeBtn"><i class="fas fa-home"></i><br>Home</button>
        <button id="profileBtn" disabled><i class="fas fa-user"></i><br>Profile</button>
        <button id="settingsBtn" disabled><i class="fas fa-cog"></i><br>Settings</button>
        <!-- <button><i class="fas fa-search"></i><br>Search</button> -->
    </div>

</body>

</html>