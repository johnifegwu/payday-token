
const linkedInFollowButton = document.getElementById("linkedInFollowBtn");
const linkedInLikeButton = document.getElementById("linkedInLikeBtn");
const twitterFoollowButton = document.getElementById("twitterFoollowBtn");
const twitterRetweetButton = document.getElementById("twitterRetweetBtn");
const connectWalletButton = document.getElementById("connectWalletBtn");
const taskStatus = document.getElementById("taskStatus");
const linkedInPageUrl = "https://www.linkedin.com/company/payday-token/about/?viewAsMember=true";
const linkedInPostUrl = "https://www.linkedin.com/posts/payday-token_crypto-blockchain-tokenpresale-activity-7249290163904757760-GV6Z?utm_source=share&utm_medium=member_desktop";
const tqitterProfileUrl = "https://x.com/token_payday";
const twitterPostUrl = "https://x.com/token_payday/status/1843531784899981646";


function checkTaskCompletion(taskName, currentButton, nextButton, url) {
    // Display countdown next to the task
    var countdown = 14;
    var countdownDisplay = $('<span> (' + countdown + ')</span>').appendTo(taskStatus);

    var countdownInterval = setInterval(function () {
        countdown--;
        countdownDisplay.text(' (' + countdown + ')');

        if (countdown <= 0) {
            clearInterval(countdownInterval);
            countdownDisplay.remove(); // Remove countdown after it finishes

            // Check task completion after countdown
            $.ajax({
                url: 'check_tasks.php',
                type: 'GET',
                success: function (response) {
                    const tasks = JSON.parse(response);

                    // Update token count
                    let tokens = parseInt($('#token-count').text()); // Get current token count
                    if (tasks[taskName]) {
                        tokens += 200000;
                    }
                    $('#token-count').text(tokens);

                    // Update task completion display
                    nextButton.disabled = false;
                    currentButton.disabled = true;
                }
            });
        }
    }, 1000);
}

function followOnLinkedIn() {
    // Navigate the user to a new page
    window.open(linkedInPageUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("linkedin_followed", linkedInFollowButton, linkedInLikeButton, linkedInPageUrl);
}

function likeAndRepostLinkedInPost() {
    // Navigate the user to a new page
    window.open(linkedInPostUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("linkedin_liked", linkedInLikeButton, twitterFoollowButton, linkedInPostUrl);
}

function followOnTwitter() {
    // Navigate the user to a new page
    window.open(tqitterProfileUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("twitter_followed", twitterFoollowButton, twitterRetweetButton, tqitterProfileUrl);
}

function retweetTwitterPost() {
    // Navigate the user to a new page
    window.open(twitterPostUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("twitter_retweeted", twitterRetweetButton, connectWalletButton, twitterPostUrl);
}

function connectWallet(){
    // Navigate the user to a new page
    window.open("connect_wallet.php", "_blank");
}

linkedInFollowButton.addEventListener("click", function () {
    followOnLinkedIn();
});

linkedInLikeButton.addEventListener("click", function () {
    likeAndRepostLinkedInPost();
});

twitterFoollowButton.addEventListener("click", function () {
    followOnTwitter();
});

twitterRetweetButton.addEventListener("click", function () {
    retweetTwitterPost();
});

connectWalletButton.addEventListener("click", function () {
    connectWallet();
});
