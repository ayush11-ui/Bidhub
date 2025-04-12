document.addEventListener('DOMContentLoaded', function() {
    // Auction countdown timers
    const countdownElements = document.querySelectorAll('.countdown-timer');
    
    countdownElements.forEach(function(element) {
        const endTime = new Date(element.getAttribute('data-end-time')).getTime();
        
        const updateTimer = function() {
            const now = new Date().getTime();
            const distance = endTime - now;
            
            if (distance < 0) {
                element.innerHTML = 'Auction Ended';
                element.classList.add('text-danger');
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            let timerText = '';
            
            if (days > 0) {
                timerText += days + 'd ';
            }
            
            timerText += hours + 'h ' + minutes + 'm ' + seconds + 's';
            element.innerHTML = timerText;
        };
        
        // Update immediately and then every second
        updateTimer();
        setInterval(updateTimer, 1000);
    });
    
    // Live auction bidding
    const bidForm = document.getElementById('bid-form');
    if (bidForm) {
        bidForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const auctionId = this.getAttribute('data-auction-id');
            const bidAmount = document.getElementById('bid-amount').value;
            
            // AJAX request to place bid
            fetch('includes/place_bid.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `auction_id=${auctionId}&bid_amount=${bidAmount}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('current-price').textContent = data.new_price;
                    document.getElementById('bid-amount').value = '';
                    
                    // Update the minimum bid amount
                    const newMinBid = parseFloat(data.raw_price) + parseFloat(document.getElementById('bid-form').getAttribute('data-increment'));
                    document.getElementById('bid-amount').setAttribute('min', newMinBid);
                    document.querySelector('.form-text').innerHTML = `Minimum bid: $${newMinBid.toFixed(2)}`;
                    
                    // Add bid to history without refreshing
                    const bidsList = document.querySelector('.card .list-group');
                    if (bidsList) {
                        // If there was "no bids" message, remove it
                        if (bidsList.parentElement.querySelector('.text-muted')) {
                            bidsList.parentElement.querySelector('.text-muted').remove();
                            // Create list group if it doesn't exist
                            if (!document.querySelector('.card .list-group')) {
                                const newList = document.createElement('div');
                                newList.className = 'list-group';
                                bidsList.parentElement.appendChild(newList);
                            }
                        }
                        
                        // Add the new bid to the top of the list
                        const newBid = document.createElement('div');
                        newBid.className = 'list-group-item';
                        newBid.innerHTML = `
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-user me-2"></i> ${document.querySelector('#userDropdown').textContent.trim()}</span>
                                <span class="fw-bold">${data.new_price}</span>
                            </div>
                            <small class="text-muted">just now</small>
                        `;
                        
                        // Add to the top of the list
                        const listGroup = document.querySelector('.card .list-group');
                        if (listGroup) {
                            listGroup.insertBefore(newBid, listGroup.firstChild);
                        }
                    }
                    
                    // Show success message
                    const alertContainer = document.getElementById('alert-container');
                    alertContainer.innerHTML = '<div class="alert alert-success">Your bid was successful!</div>';
                    
                    // Auto-dismiss alert after 3 seconds
                    setTimeout(() => {
                        alertContainer.innerHTML = '';
                    }, 3000);
                } else {
                    // Show error message
                    const alertContainer = document.getElementById('alert-container');
                    alertContainer.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    }
    
    // Live comments section
    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const auctionId = this.getAttribute('data-auction-id');
            const commentText = document.getElementById('comment-text').value;
            
            // Only proceed if user is logged in
            if (!document.body.classList.contains('logged-in')) {
                alert('Please log in to post a comment.');
                window.location.href = 'login.php';
                return;
            }
            
            // AJAX request to post comment
            fetch('includes/post_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `auction_id=${auctionId}&comment=${commentText}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('comment-text').value = '';
                    
                    // Add the new comment to the list
                    const commentsList = document.getElementById('comments-list');
                    const newComment = document.createElement('div');
                    newComment.className = 'comment';
                    newComment.innerHTML = `
                        <div class="comment-header">
                            <span class="comment-author">${data.username}</span>
                            <span class="comment-time">Just now</span>
                        </div>
                        <div class="comment-body">
                            ${data.comment}
                        </div>
                    `;
                    commentsList.prepend(newComment);
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    }
    
    // Real-time auction updates with polling
    function pollLiveAuction() {
        const auctionContainer = document.getElementById('live-auction-container');
        if (auctionContainer) {
            const auctionId = auctionContainer.getAttribute('data-auction-id');
            
            fetch(`includes/get_auction_updates.php?auction_id=${auctionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update price
                    document.getElementById('current-price').textContent = data.current_price;
                    
                    // Update bids
                    if (data.latest_bids && data.latest_bids.length > 0) {
                        const bidsList = document.getElementById('bids-list');
                        if (bidsList) {
                            // Only update if there are new bids
                            const lastBidId = bidsList.getAttribute('data-last-bid-id');
                            if (data.latest_bids[0].bid_id != lastBidId) {
                                bidsList.innerHTML = '';
                                data.latest_bids.forEach(bid => {
                                    const bidEl = document.createElement('div');
                                    bidEl.className = 'alert alert-light';
                                    bidEl.innerHTML = `<strong>${bid.username}</strong> placed a bid of ${bid.amount} ${getTimeAgo(new Date(bid.created_at))}`;
                                    bidsList.appendChild(bidEl);
                                });
                                bidsList.setAttribute('data-last-bid-id', data.latest_bids[0].bid_id);
                            }
                        }
                    }
                    
                    // Update comments if available
                    const commentsList = document.getElementById('comments-list');
                    if (data.latest_comments && commentsList) {
                        // Only update if there are new comments
                        const lastCommentId = commentsList.getAttribute("data-last-comment-id");
                        if (!lastCommentId || data.latest_comments[0].comment_id != lastCommentId) {
                            commentsList.innerHTML = "";
                            data.latest_comments.forEach(comment => {
                                const commentEl = document.createElement("div");
                                commentEl.className = "comment mb-3";
                                commentEl.innerHTML = `
                                    <div class="comment-header">
                                        <span class="comment-author"><i class="fas fa-user me-1"></i> ${comment.username}</span>
                                        <span class="comment-time">${getTimeAgo(new Date(comment.created_at))}</span>
                                    </div>
                                    <div class="comment-body mt-2">
                                        ${comment.text.replace(/\n/g, '<br>')}
                                    </div>
                                `;
                                commentsList.appendChild(commentEl);
                            });
                            
                            if (data.latest_comments.length > 0) {
                                commentsList.setAttribute("data-last-comment-id", data.latest_comments[0].comment_id);
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error polling auction updates:', error);
            });
        }
    }
    
    // If we're on a live auction page, poll for updates every 5 seconds
    if (document.getElementById('live-auction-container')) {
        setInterval(pollLiveAuction, 5000);
    }
    
    // Helper function to format time ago
    function getTimeAgo(dateInput) {
        // Ensure we're working with a Date object
        const date = dateInput instanceof Date ? dateInput : new Date(dateInput);
        
        // Check if the date is valid
        if (isNaN(date.getTime())) {
            return 'invalid date';
        }
        
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 5) {
            return 'just now';
        }
        
        if (diffInSeconds < 60) {
            return `${diffInSeconds} second${diffInSeconds !== 1 ? 's' : ''} ago`;
        }
        
        const diffInMinutes = Math.floor(diffInSeconds / 60);
        if (diffInMinutes < 60) {
            return `${diffInMinutes} minute${diffInMinutes !== 1 ? 's' : ''} ago`;
        }
        
        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) {
            return `${diffInHours} hour${diffInHours !== 1 ? 's' : ''} ago`;
        }
        
        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays < 30) {
            return `${diffInDays} day${diffInDays !== 1 ? 's' : ''} ago`;
        }
        
        const diffInMonths = Math.floor(diffInDays / 30);
        if (diffInMonths < 12) {
            return `${diffInMonths} month${diffInMonths !== 1 ? 's' : ''} ago`;
        }
        
        const diffInYears = Math.floor(diffInMonths / 12);
        return `${diffInYears} year${diffInYears !== 1 ? 's' : ''} ago`;
    }
}); 