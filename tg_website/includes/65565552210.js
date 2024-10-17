const tonweb = new window.TonWeb();
const connectWalletButton = document.getElementById("connectWalletButton");
const payNowButton = document.getElementById("payNowButton");
let address;
let lastTxHash;

const tonconnectUI = new TON_CONNECT_UI.TonConnectUI({
    manifestUrl: 'https://tg.pday.online/tonconnect-manifest.json',
});

async function connectWallet() {
    connectWalletButton.disabled = true;
    try {
        if (!tonconnectUI) {
            showMessage('error', "TonConnectUI not initialized.");
            return;
        }

        const currentIsConnectedStatus = tonconnectUI.connected;

        if (currentIsConnectedStatus) {
            await tonconnectUI.disconnect(); // disconnect any previously connected wallet
        }

        if (connectWalletButton.innerHTML == "Disconnect Wallet") {
            connectWalletButton.innerHTML = "Connect Wallet"
            connectWalletButton.disabled = false;
            return;
        }

        const wallet = await tonconnectUI.connectWallet();
        const mechineaddress = new TonWeb.utils.Address(wallet.account.address);
        address = mechineaddress.toString(isUserFriendly = true);
        connectWalletButton.innerHTML = "Disconnect Wallet";
        payNowButton.disabled = false;
        // Get user's last transaction hash using tonweb
        const lastTx = (await tonweb.getTransactions(address, 1))[0]
        // we use if in case of new wallet.
        if (lastTx) {
            lastTxHash = lastTx.transaction_id.hash
        }
        showMessage('success', `Wallet connected: ${maskString(address)}`);
    } catch (error) {
        connectWalletButton.disabled = false;
        payNowButton.disabled = true;
        console.error("Failed to connect wallet:", error);
        showMessage('error', "Failed to connect wallet.");
    } finally {
        //Also invoke check limit here
        checkLimit();
    }
}

function maskString(input) {
    // Check if the input is at least 8 characters long
    if (input.length < 8) {
        return input;
    }

    // Extract the first four and last four characters
    const firstFour = input.slice(0, 4);
    const lastFour = input.slice(-4);

    // Combine the first four characters, six asterisks, and the last four characters
    return `${firstFour}******${lastFour}`;
}

// Function to update the message div
function showMessage(type, text) {
    const messageDiv = document.getElementById('message');
    messageDiv.className = 'message ' + type;
    messageDiv.innerHTML = text;
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function transferTON() {
    if (!address) {
        showMessage('error', "Connect your wallet first.");
        transferStatus.innerHTML = "";
        return;
    }

    // collect 0.2 TON
    try {
        // Initiating the payment collection
        const tonAmount = 0.2; // TON amount to collect

        // Simulate TON transfer request (this will need a TON payment SDK in production)
        const paymentResponse = await tonconnectUI.sendTransaction({
            validUntil: Date.now() + 60 * 20000, // valid for 20 minutes
            messages: [{
                address: "UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2",
                amount: tonAmount * 1e9 // TON amount converted to nanoTON
            }]
        });

        //log response for debugging
        console.log('Payment Response:', paymentResponse);

        showMessage('success', 'Checking... <div class="spinner"></div>');

        //Get the transaction
        const bocCellBytes = await TonWeb.boc.Cell.oneFromBoc(TonWeb.utils.base64ToBytes(paymentResponse.boc)).hash();

        const hashBase64 = TonWeb.utils.bytesToBase64(bocCellBytes);

        // We try to confirm transaction here
        // Run a loop until user's last tx hash changes
        var txHash = lastTxHash
        while (txHash == lastTxHash) {
            await sleep(1500) // some delay between API calls
            let tx = (await tonweb.getTransactions(address, 1))[0]
            txHash = tx.transaction_id.hash
        }

        // Check if the payment was successful (this depends on the TON SDK response structure)
        if (paymentResponse) {
            showMessage('success', "0.2 TON collected successfully. Verifying payment...");

            // After payment is successful, verify payment via AJAX
            $.ajax({
                url: 'verify_payment.php',
                type: 'POST',
                data: { address: address, hash: hashBase64, checkLimit: ""  },
                success: function (response) {
                    if (response === 'success') {
                        $.ajax({
                            url: 'credit_tokens.php',
                            type: 'POST',
                            data: { address: address, amount: 1000000 },
                            success: function () {
                                showMessage('success', "Wallet connected and 0.2 TON payment credited successfully!\nDistribution will be announced soon!!");
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
        connectWalletButton.disabled = false;
        payNowButton.disabled = true;
        console.error(error);
        showMessage('error', "An error occurred during the payment process. Please try again.");
    } finally {
        payNowButton.disabled = true;
    }
}

async function checkLimit() {
    $.ajax({
        url: 'verify_payment.php',
        type: 'POST',
        data: { address: "", hash: "", checkLimit: "true" },
        success: function (response) {
            if (response === 'disabled') {
                var msg = "<p>We have reached the maximum number of participants for the PayDay Token Distribution.</p>";
                msg += "<p>Thank you for your interest!</p>";
                // Disable payment as limit has been reached.
                connectWalletButton.disabled = true;
                payNowButton.disabled = true;
                showMessage('error', msg);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error("Error verifying limit:", textStatus, errorThrown);
            console.error("Server response:", jqXHR.responseText);  // Logs the actual server response
        }
    });
}

// tonconnectUI.on('connect', async (wallet) => {

// });

connectWalletButton.addEventListener('click', connectWallet);
payNowButton.addEventListener('click', transferTON);
// Check limit
checkLimit();