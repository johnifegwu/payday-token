
const linkedInFollowButton = document.getElementById("linkedInFollowBtn");
const linkedInLikeButton = document.getElementById("linkedInLikeBtn");
const twitterFoollowButton = document.getElementById("twitterFoollowBtn");
const twitterRetweetButton = document.getElementById("twitterRetweetBtn");
const connectWalletButton = document.getElementById("connectWalletBtn");
const linkedInFollowBtntatus = document.getElementById("linkedInFollowBtnStatus");
const taskStatus = document.getElementById("taskStatus");
const linkedInPageUrl = "https://www.linkedin.com/company/payday-token/about/?viewAsMember=true";
const linkedInPostUrl = "https://www.linkedin.com/feed/update/urn:li:activity:7250590632941944832";
const tqitterProfileUrl = "https://x.com/token_payday";
const twitterPostUrl = "https://x.com/token_payday/status/1843531784899981646";


function checkTaskCompletion(taskName, currentButton, nextButton, taskStatus, url) {
    // Ensure taskStatus is a jQuery object
    var $taskStatus = $(taskStatus); // Convert the div element to a jQuery object

    // Display countdown next to the task
    var countdown = 14;

    if(taskName == "wallet_connected"){
        // set countdown to 300 seconds
        countdown = 300;
    }

    var countdownDisplay = $('<span> (' + countdown + ')</span>').appendTo($taskStatus);

    var countdownInterval = setInterval(function () {
        countdown--;
        countdownDisplay.text(' (' + countdown + ')');

        if (countdown <= 0) {
            clearInterval(countdownInterval);
            countdownDisplay.remove(); // Remove countdown after it finishes
            countdownDisplay = $('Checking... <div class="spinner"></div>').appendTo($currentTaskStatus);
            // Check task completion after countdown
            $.ajax({
                url: 'check_tasks.php', // Use dynamic URL passed to the function
                type: 'GET',
                success: function (response) {
                    try {
                        const tasks = JSON.parse(response);

                        // Update token count
                        let tokens = parseInt($('#token-count').text(), 10); // Ensure the token count is an integer
                        if (tasks[taskName]) {
                            tokens += 200000; // Add tokens if the task is completed
                            $('#token-count').text(tokens);

                            // Update task completion display
                            if(nextButton){
                                nextButton.disabled = false;
                            }
                            currentButton.disabled = true;
                        }
                    } catch (e) {
                        console.error("Failed to parse response: ", e);
                    }
                    finally{
                        countdownDisplay.remove(); // Remove countdown after it finishes
                    }
                },
                error: function (xhr, status, error) {
                    console.error("AJAX request failed: ", error);
                }
            });
        }
    }, 1000);
}


function followOnLinkedIn() {
    // Navigate the user to a new page
    window.open(linkedInPageUrl, "_blank");
    // invoke checkTaskCompletion function
    checkTaskCompletion("linkedin_followed", linkedInFollowButton, linkedInLikeButton, linkedInFollowBtntatus, linkedInPageUrl);
}

function likeAndRepostLinkedInPost() {
    // Navigate the user to a new page
    window.open(linkedInPostUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("linkedin_liked", linkedInLikeButton, twitterFoollowButton, taskStatus, linkedInPostUrl);
}

function followOnTwitter() {
    // Navigate the user to a new page
    window.open(tqitterProfileUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("twitter_followed", twitterFoollowButton, twitterRetweetButton, taskStatus, tqitterProfileUrl);
}

function retweetTwitterPost() {
    // Navigate the user to a new page
    window.open(twitterPostUrl, "_blank");

    // invoke checkTaskCompletion function
    checkTaskCompletion("twitter_retweeted", twitterRetweetButton, connectWalletButton, taskStatus, twitterPostUrl);
}

function connectWallet(){
    // Navigate the user to a new page
    window.open("connect_wallet.php", "_blank");
    checkTaskCompletion("wallet_connected", connectWalletButton, null, taskStatus, null);
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
