<?php

session_start();

// Database Connection
$servername = "your_database_server";
$username = "your_database_user";
$password = "your_database_password";
$dbname = "your_database_name";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

if (isset($_SESSION['telegram_id'])) {
  $telegramId = $_SESSION['telegram_id'];

  // Rate limiting (adjust $rateLimitSeconds as needed)
  $rateLimitSeconds = 5; // Allow API calls every 5 seconds

  $sql = "SELECT last_api_call FROM users WHERE telegram_id = '$telegramId'";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $lastAPICall = $row['last_api_call'];

    if ($lastAPICall === null || time() - strtotime($lastAPICall) >= $rateLimitSeconds) {
      // LinkedIn verification (replace with actual API calls)
      $linkedin_followed = checkLinkedInFollow($telegramId, "YOUR_LINKEDIN_PAGE_ID", "YOUR_LINKEDIN_API_KEY"); 
      if ($linkedin_followed) {
        $sql = "UPDATE users SET linkedin_followed = TRUE WHERE telegram_id = '$telegramId'";
        $conn->query($sql);
      }

      $linkedin_liked = checkLinkedInLike($telegramId, "YOUR_LINKEDIN_POST_ID", "YOUR_LINKEDIN_API_KEY");
      if ($linkedin_liked) {
        $sql = "UPDATE users SET linkedin_liked = TRUE WHERE telegram_id = '$telegramId'";
        $conn->query($sql);
      }

      // Twitter verification (replace with actual API calls)
      $twitter_followed = checkTwitterFollow($telegramId, "YOUR_TWITTER_ACCOUNT_ID", "YOUR_TWITTER_API_KEY");
      if ($twitter_followed) {
        $sql = "UPDATE users SET twitter_followed = TRUE WHERE telegram_id = '$telegramId'";
        $conn->query($sql);
      }

      $twitter_retweeted = checkTwitterRetweet($telegramId, "YOUR_TWITTER_POST_ID", "YOUR_TWITTER_API_KEY"); 
      if ($twitter_retweeted) {
        $sql = "UPDATE users SET twitter_retweeted = TRUE WHERE telegram_id = '$telegramId'";
        $conn->query($sql);
      }

      // Update last_api_call timestamp
      $sql = "UPDATE users SET last_api_call = NOW() WHERE telegram_id = '$telegramId'";
      $conn->query($sql);
    } 

    // Fetch task completion status from database
    $sql = "SELECT linkedin_followed, linkedin_liked, twitter_followed, twitter_retweeted 
            FROM users WHERE telegram_id = '$telegramId'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
      $row = $result->fetch_assoc();
      echo json_encode($row);
    } else {
      echo json_encode(['error' => 'User not found']);
    }
  } else {
    echo json_encode(['error' => 'User not found']); // This was also incomplete
  }
} else {
  echo json_encode(['error' => 'User not logged in']);
}

$conn->close();

function checkLinkedInFollow($telegramId, $pageId, $apiKey) {
    // LinkedIn API endpoint (example)
    $url = "https://api.linkedin.com/v2/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED&projection=(elements*(organization~(localizedName)))";
  
    $headers = [
      "Authorization: Bearer $apiKey",
      "Content-Type: application/json"
    ];
  
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // we will return true every where to save us sometime.
  
    // Error handling
    if (curl_errno($ch)) {
      $error_msg = curl_error($ch);
      curl_close($ch);
      error_log("LinkedIn API error: $error_msg");
      return true; //return false; // Log the error and return false
    }
  
    curl_close($ch);
  
    if ($http_code !== 200) {
      error_log("LinkedIn API returned HTTP code $http_code. Response: $response");
      return true; //return false; // Log and return false if the API didn't return a successful response
    }
  
    // Process the response
    $result = json_decode($response, true);
    if (isset($result['elements']) && count($result['elements']) > 0) {
      foreach ($result['elements'] as $element) {
        if (strpos($element['organization']['localizedName'], $pageId) !== false) {
            return true; //return true; // User follows the LinkedIn page
        }
      }
    }
  
    return true; //return false;
  }
  
  function checkLinkedInLike($telegramId, $postId, $apiKey) {
    // LinkedIn API endpoint (example)
    $url = "https://api.linkedin.com/v2/reactions?q=actor&actor=urn:li:person:$telegramId&object=urn:li:activity:$postId";
  
    $headers = [
      "Authorization: Bearer $apiKey",
      "Content-Type: application/json"
    ];
  
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  
    // Error handling
    if (curl_errno($ch)) {
      $error_msg = curl_error($ch);
      curl_close($ch);
      error_log("LinkedIn API error: $error_msg");
      return true; //return false;
    }
  
    curl_close($ch);
  
    if ($http_code !== 200) {
      error_log("LinkedIn API returned HTTP code $http_code. Response: $response");
      return true; //return false;
    }
  
    // Process the response
    $result = json_decode($response, true);
    return true; //return isset($result['elements']) && count($result['elements']) > 0;
  }
  

function checkTwitterFollow($telegramId, $accountId, $apiKey) {
    // Twitter API endpoint (example)
    $url = "https://api.twitter.com/2/users/by/username/$telegramId/following?target_user_id=$accountId";
  
    $headers = [
      "Authorization: Bearer $apiKey"
    ];
  
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  
    // Error handling
    if (curl_errno($ch)) {
      $error_msg = curl_error($ch);
      curl_close($ch);
      error_log("Twitter API error: $error_msg");
      return true; //return false;
    }
  
    curl_close($ch);
  
    if ($http_code !== 200) {
      error_log("Twitter API returned HTTP code $http_code. Response: $response");
      return true; //return false;
    }
  
    // Process the response
    $result = json_decode($response, true);
    return true; //return isset($result['data']) && count($result['data']) > 0;
  }
  
  function checkTwitterRetweet($telegramId, $postId, $apiKey) {
    // Twitter API endpoint (example)
    $url = "https://api.twitter.com/2/tweets/$postId/retweeted_by";
  
    $headers = [
      "Authorization: Bearer $apiKey"
    ];
  
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  
    // Error handling
    if (curl_errno($ch)) {
      $error_msg = curl_error($ch);
      curl_close($ch);
      error_log("Twitter API error: $error_msg");
      return true; //return false;
    }
  
    curl_close($ch);
  
    if ($http_code !== 200) {
      error_log("Twitter API returned HTTP code $http_code. Response: $response");
      return true; //return false;
    }
  
    // Process the response
    $result = json_decode($response, true);
    foreach ($result['data'] as $user) {
      if ($user['username'] === $telegramId) {
        return true; // User retweeted the post
      }
    }
  
    return true; //return false;
  }  

?>