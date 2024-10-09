<?php

// connect_wallet.php

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

// Check if the number of paid users has reached the limit
$sql = "SELECT COUNT(*) FROM users WHERE wallet_connected = TRUE";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$paidUsersCount = $row['COUNT(*)'];

?>
<!DOCTYPE html>
<html>
<head>
  <title>Connect TON Wallet</title>
  <script src="https://unpkg.com/@tonconnect/ui@2.1.1/dist/tonconnect-ui.min.js"></script> 
  <link rel="stylesheet" href="https://unpkg.com/@tonconnect/ui@2.1.1/dist/tonconnect-ui.min.css" />
  <style>
    body {
      background-color: #222; 
      color: gold; 
      font-family: sans-serif;
      text-align: center;
    }
    h1 {
      color: gold; 
    }
    .logo { 
      width: 200px; 
      height: auto;
      margin: 20px auto; 
    }
  </style>
</head>
<body>

  <img src="path/to/your/logo.png" alt="PayDay Token Logo" class="logo">

  <h1>Connect Your TON Wallet</h1>

  <?php if ($paidUsersCount >= 70000) { ?>
    <div>
      <p>We have reached the maximum number of participants for the PayDay Token Distribution.</p>
      <p>Thank you for your interest!</p>
    </div>
  <?php } else { ?>
    <div id="tonconnect-button"></div>
    <script>
      const tonconnectUI = new TonConnectUI({
        manifestUrl: 'tonconnect-manifest.json' 
      });

      tonconnectUI.render('#tonconnect-button');

      tonconnectUI.on('connect', async (wallet) => {
        const address = wallet.account.address; 

        $.ajax({
          url: 'verify_payment.php',
          type: 'POST',
          data: { address: address },
          success: function(response) {
            if (response === 'success') {
              $.ajax({
                url: 'credit_tokens.php',
                type: 'POST',
                data: { address: address },
                success: function(response) {
                  alert("Wallet connected and tokens credited successfully!"); 
                  // You might want to redirect the user to a success page here
                },
                error: function() {
                  alert("Error crediting tokens. Please contact support.");
                }
              });
            } else if (response === 'disabled') {
              alert("The PayDay Token Distribution has reached its maximum capacity. Payment is currently disabled.");
            } else {
              alert("Payment verification failed. Please ensure you have sent 0.2 TON.");
            }
          },
          error: function() {
            alert("Error verifying payment. Please contact support.");
          }
        }); 
      });
    </script>
  <?php } ?>

</body>
</html>