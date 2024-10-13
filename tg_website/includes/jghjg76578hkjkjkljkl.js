
const tonweb = new window.TonWeb();

const tonconnectUI = new TON_CONNECT_UI.TonConnectUI({
    manifestUrl: 'https://tg.pday.online/tonconnect-manifest.json'
});

tonconnectUI.render('#tonconnect-button');

tonconnectUI.on('connect', async (wallet) => {
    const mechineaddress = new TonWeb.utils.Address(wallet.account.address);
    const address = mechineaddress.toString(isUserFriendly = true);

    // Function to update the message div
    function showMessage(type, text) {
        const messageDiv = document.getElementById('message');
        messageDiv.className = 'message ' + type;
        messageDiv.innerHTML = text;
    }

    // Simulate collecting 0.2 TON after a delay
    setTimeout(async () => {
        try {
            // Initiating the payment collection
            const tonAmount = 0.2; // TON amount to collect

            // Simulate TON transfer request (this will need a TON payment SDK in production)
            const paymentResponse = await tonconnectUI.sendTransaction({
                validUntil: Date.now() + 60 * 20000, // valid for 20 minute
                messages: [{
                    address: "UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2", 
                    amount: tonAmount * 1e9 // TON amount converted to nanoTON
                }]
            });

            // Check if the payment was successful (this depends on the TON SDK response structure)
            if (paymentResponse) {
                showMessage('success', "0.2 TON collected successfully. Verifying payment...");
                
                // After payment is successful, verify payment via AJAX
                $.ajax({
                    url: 'verify_payment.php',
                    type: 'POST',
                    data: { address: address },
                    success: function (response) {
                        if (response === 'success') {
                            $.ajax({
                                url: 'credit_tokens.php',
                                type: 'POST',
                                data: { address: address },
                                success: function () {
                                    showMessage('success', "Wallet connected and 0.2 TON payment credited successfully!\nDistribution will be anounced soon!!");
                                },
                                error: function () {
                                    showMessage('error', "Error crediting tokens. Please contact support.");
                                }
                            });
                        } else if (response === 'disabled') {
                            showMessage('error', "The PayDay Token Distribution has reached its maximum capacity. Payment is currently disabled.");
                        } else {
                            showMessage('error', "Payment verification failed. Please ensure you have sent 0.2 TON.");
                        }
                    },
                    error: function () {
                        showMessage('error', "Error verifying payment. Please contact support.");
                    }
                });
            } else {
                showMessage('error', "Failed to collect payment. Please try again.");
            }
        } catch (error) {
            showMessage('error', "An error occurred during the payment process. Please try again.");
        }
    }, 2000); // Delay of 2 seconds after wallet connection
});