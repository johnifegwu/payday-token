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
        const address = new TonWeb.utils.Address(currentAccount.address);
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

        const result = await tonConnectUI.sendTransaction(transaction);
        transferStatus.innerHTML = 'Confirming transaction... <div class="spinner"></div>';

        // Run a loop until user's last tx hash changes
        var txHash = lastTxHash
        while (txHash == lastTxHash) {
            await sleep(1500) // some delay between API calls
            let tx = (await tonweb.getTransactions(address, 1))[0]
            txHash = tx.transaction_id.hash
        }

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

connectWalletButton.addEventListener('click', connectWallet);
buyNowButton.addEventListener('click', transferTON);
tonInput.addEventListener('input', validateBuyNowButton);