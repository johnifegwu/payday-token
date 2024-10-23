const tonweb = new window.TonWeb();

const connectWalletButton = document.getElementById("connectWalletButton");
const buyNowButton = document.getElementById("buyNowButton");
const tonInput = document.getElementById("tonInput");
const walletStatus = document.getElementById("walletStatus");
const transferStatus = document.getElementById("transferStatus");
const userAgent = navigator.userAgent;
const android = "Android";
const ios = "iOS";
const desktop = "Desktop";

let tonConnectUI;
let tonWallet = false;
let lastTxHash
let walletAddress
let address;

function getDeviceType() {
    if (/android/i.test(userAgent)) {
        return android;
    } else if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
        return ios;
    } else {
        return desktop;
    }
}

const deviceType = getDeviceType();


async function initializeTonConnectUI() {
    try {
        tonConnectUI = new TON_CONNECT_UI.TonConnectUI({
            manifestUrl: 'https://pday.online/tonconnect-manifest.json',
            walletsListConfiguration: {
                //Customize the displayed wallet list
                includeWallets: [
                    {
                        name: "Bitget Wallet",
                        appName: "bitgetTonWallet",
                        imageUrl:
                            "https://raw.githubusercontent.com/bitkeepwallet/download/main/logo/png/bitget%20wallet_logo_iOS.png",
                        universalLink: "https://bkcode.vip/ton-connect",
                        bridgeUrl: "https://bridge.tonapi.io/bridge",
                        platforms: ["ios", "android", "chrome"],
                    },
                ],
            }
        });
        //Change options if needed
        tonConnectUI.uiOptions = {
            language: "en",
            uiPreferences: {
                theme: 'SYSTEM'
            }
        };
        console.log("TonConnectUI initialized successfully.");
    } catch (error) {
        console.error("Error initializing TonConnectUI:", error);
    }
}

window.onload = async function () {
    await initializeTonConnectUI();
}

async function connectWallet() {
    try {
        if (!tonConnectUI) {
            walletStatus.innerHTML = "TonConnectUI not initialized.";
            return;
        }

        const currentIsConnectedStatus = tonConnectUI.connected;

        if(currentIsConnectedStatus){
            await tonConnectUI.disconnect(); // disconnect any previously connected wallet
        }

        
        if(connectWalletButton.innerHTML == "Disconnect Wallet"){
            tonWallet = false;
            connectWalletButton.innerHTML = "Connect Wallet"
            walletStatus.innerHTML = "";
            return;
        }

        await tonConnectUI.connectWallet();

        const currentAccount = tonConnectUI.account;
        tonWallet = true;
        address = new TonWeb.utils.Address(currentAccount.address);
        walletAddress = address.toString(isUserFriendly = true);
        walletStatus.innerHTML = `Wallet connected: ${maskString(walletAddress)}`;
        connectWalletButton.innerHTML = "Disconnect Wallet";

        // Get user's last transaction hash using tonweb
        const lastTx = (await tonweb.getTransactions(address, 1))[0]
        // we use if in case of new wallet.
        if(lastTx){
            lastTxHash = lastTx.transaction_id.hash
        }
        validateBuyNowButton();
    } catch (error) {
        console.error("Failed to connect wallet:", error);
        walletStatus.innerHTML = "Failed to connect wallet.";
    }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function transferTON() {
    if (!tonWallet) {
        transferStatus.innerHTML = "Please connect your wallet first.";
        return;
    }

    const amountTON = parseFloat(tonInput.value);
    const amountNanoTON = amountTON * 1e9;

    try {
        const transaction = {
            messages: [
                {
                    address: "UQBHTgwIOT5lb3XnylLWWdKRn4ilCgufkw-sZw21yv4WUpK2", // destination address
                    amount: amountNanoTON.toString() // Toncoin in nanotons
                }
            ]
        }

        const paymentResponse = await tonConnectUI.sendTransaction(transaction);
        transferStatus.innerHTML = 'Confirming transaction... <div class="spinner"></div>';

        //Get the transaction
        const bocCellBytes = await TonWeb.boc.Cell.oneFromBoc(TonWeb.utils.base64ToBytes(paymentResponse.boc)).hash();
        const hashBase64 = TonWeb.utils.bytesToBase64(bocCellBytes);

        // Run a loop until user's last tx hash changes
        var txHash = lastTxHash
        while (txHash == lastTxHash) {
            await sleep(1500) // some delay between API calls
            let tx = (await tonweb.getTransactions(address, 1))[0]
            txHash = tx.transaction_id.hash
        }
        
        let sitekey = decodeSiteKey();
        //Log the transaction
        await submitTransaction(walletAddress, hashBase64, amountTON, sitekey);
        transferStatus.innerHTML = `Transfer successful! You have sent ${amountTON} TON.\n${maskString(txHash)}`;
    } catch (error) {
        console.error("Failed to transfer TON:", error);
        transferStatus.innerHTML = "Failed to transfer TON.";
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
  

function validateBuyNowButton() {
    const amount = parseFloat(tonInput.value);
    buyNowButton.disabled = !(tonWallet && amount >= 0.7 && amount <= 5000);
}

function submitTransaction(walletAddress, transactionHash, TONAmountPaid, siteKey) {
    const data = {
        walletAddress: walletAddress,
        transactionHash: transactionHash,
        TONAmountPaid: TONAmountPaid,
        SiteKey: siteKey
    };

    // Create a new XMLHttpRequest object
    const xhr = new XMLHttpRequest();
    
    // Configure it: POST-request for the URL /presalelog.php
    xhr.open('POST', 'https://pday.online/presalelog.php', true);
    
    // Set the request header
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    // Set up a handler for the response
    xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
            const result = xhr.responseText.trim();
            if (result === "Transaction recorded successfully!") {
                console.log("Transaction recorded successfully!");
            } else {
                console.error("Error: ", result);
                // Handle error response
            }
        } else {
            console.error("Request failed. Status:", xhr.status);
            // Handle HTTP errors
        }
    };

    // Set up a handler for network errors
    xhr.onerror = function () {
        console.error("Request failed");
        // Handle network errors
    };

    // Serialize the data to URL-encoded format
    const urlEncodedData = new URLSearchParams(data).toString();

    // Send the request with the serialized data
    xhr.send(urlEncodedData);
}

function decodeSiteKey() {
    return atob(obfuscatedSiteKey);
}

function fetchTotalPayDayBought(siteKey) {
    // Create a new FormData object
    const formData = new FormData();
    formData.append('sitekey', siteKey);

    // Create a new XMLHttpRequest object
    const xhr = new XMLHttpRequest();

    // Configure it: POST-request for the URL /totalpaydaybought.php
    xhr.open('POST', 'https://pday.online/totalpaydaybought.php', true);

    // Set up a handler for the response
    xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
            // Parse the JSON response
            const data = JSON.parse(xhr.responseText);
            if (data.error) {
                console.error("Error:", data.error);
                alert(data.error); // Alert the error
            } else {
                let totalPayDayBought = parseFloat(data.totalPayDayBought) || 0;
                if(totalPayDayBought < 1047592){
                    totalPayDayBought += 1047592;
                }
                const cappedLimit = 10_000_000; // 10 million per round
                const statusBar = document.getElementById("statusBar");
                const progressPercentage = (totalPayDayBought / cappedLimit) * 100;

                // Update status bar
                statusBar.style.width = progressPercentage + "%";
                statusBar.textContent = `$${totalPayDayBought.toFixed(2)}`;

                if (totalPayDayBought >= cappedLimit) {
                    statusBar.textContent = "Sale limit reached!";
                    statusBar.style.backgroundColor = "red"; // Change color to indicate limit reached
                    console.log("Color changed to red.");
                } else {
                    statusBar.style.backgroundColor = "#d4af37"; // Normal color
                    console.log("Color changed to solid gold.");
                }

                console.log("Total PayDayTokens Bought on this site:", totalPayDayBought);
            }
        } else {
            console.error("Request failed. Status:", xhr.status);
            alert("An error occurred: " + xhr.statusText); // Alert fetch error
        }
    };

    // Set up a handler for network errors
    xhr.onerror = function () {
        console.error("Request failed");
        alert("An error occurred during the request."); // Alert network error
    };

    // Send the request with the FormData
    xhr.send(formData);
}

const siteKey = decodeSiteKey();
fetchTotalPayDayBought(siteKey);
connectWalletButton.addEventListener('click', connectWallet);
buyNowButton.addEventListener('click', transferTON);
tonInput.addEventListener('input', validateBuyNowButton);