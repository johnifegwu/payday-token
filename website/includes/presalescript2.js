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

let tonConnect;
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


async function initializeTonConnect() {
    try {
        tonConnect = new TonConnectSDK.TonConnect({
            manifestUrl: 'https://pday.online/tonconnect-manifest.json'});
        window.connetor = tonConnect;
        console.log("TonConnect initialized successfully.");
    } catch (error) {
        console.error("Error initializing TonConnect:", error);
    }
}

window.onload = async function () {
    await initializeTonConnect();
}

async function connectWallet() {
    try {
        if (!tonConnect) {
            walletStatus.innerHTML = "TonConnect not initialized.";
            return;
        }

        const currentIsConnectedStatus = tonConnect.connected;

        if(currentIsConnectedStatus){
            await tonConnect.disconnect(); // disconnect any previously connected wallet
        }

        if(connectWalletButton.innerHTML == "Disconnect Wallet"){
            tonWallet = false;
            connectWalletButton.innerHTML = "Connect Wallet"
            walletStatus.innerHTML = "";
            return;
        }

        const walletsList = await tonConnect.getWallets();

        // Should correspond to the wallet that user selects
        const walletConnectionSource = {
            universalLink: 'https://app.tonkeeper.com/ton-connect',
            bridgeUrl: 'https://bridge.tonapi.io/bridge'
        }

        await tonConnect.connect(walletConnectionSource);

        const currentAccount = tonConnect.account;
        console.log(`Account: ${currentAccount}`)
        tonWallet = true;
        const address = new TonWeb.utils.Address(currentAccount.address);
        walletAddress = address.toString(isUserFriendly = true);
        walletStatus.innerHTML = `Wallet connected: ${walletAddress}`;
        connectWalletButton.innerHTML = "Disconnect Wallet";

        // Get user's last transaction hash using tonweb
        const lastTx = (await tonweb.getTransactions(address, 1))[0]
        // we use if in case of new wallet.
        if(lastTx){
            lastTxHash = lastTx.transaction_id.hash
        }

        validateBuyNowButton();

        const unsubscribe = tonConnect.onStatusChange(
            walletInfo => {
              console.log('Connection status:', walletInfo);
            }
        );

        tonConnect.restoreConnection();

    } catch (error) {
        console.error("Failed to connect wallet:", error);
        walletStatus.innerHTML = `Failed to connect wallet.\n${error}`;
    }
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

        const result = await tonConnect.sendTransaction(transaction);
        const resultStr = 
        transferStatus.innerHTML = 'Confirming transaction... <div class="spinner"></div>';

        // Run a loop until user's last tx hash changes
        var txHash = lastTxHash
        while (txHash == lastTxHash) {
            await sleep(1500) // some delay between API calls
            let tx = (await tonweb.getTransactions(address, 1))[0]
            txHash = tx.transaction_id.hash
        }

        transferStatus.innerHTML = `Transfer successful! You have sent ${amountTON} TON.\n${resultStr}`;
    } catch (error) {
        console.error("Failed to transfer TON:", error);
        transferStatus.innerHTML = "Failed to transfer TON.";
    }
}

function validateBuyNowButton() {
    const amount = parseFloat(tonInput.value);
    buyNowButton.disabled = !(tonWallet && amount >= 0.7 && amount <= 2000);
}

connectWalletButton.addEventListener('click', connectWallet);
buyNowButton.addEventListener('click', transferTON);
tonInput.addEventListener('input', validateBuyNowButton);