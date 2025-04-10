// DOM Elements
const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
const nav = document.querySelector('nav');
const loginBtn = document.querySelector('.login-btn');
const signupBtn = document.querySelector('.signup-btn');
const loginModal = document.querySelector('.login-modal');
const signupModal = document.querySelector('.signup-modal');
const closeModalBtns = document.querySelectorAll('.close-modal');
const showSignupLinks = document.querySelectorAll('.show-signup');
const showLoginLinks = document.querySelectorAll('.show-login');
const filterBtns = document.querySelectorAll('.filter-btn');
const testimonialDots = document.querySelectorAll('.dot');
const prevBtn = document.querySelector('.prev-btn');
const nextBtn = document.querySelector('.next-btn');
const ctaBtn = document.querySelector('.cta-btn');

// Sample auction data
const auctionItems = [
    {
        id: 1,
        title: 'Vintage Mechanical Watch',
        category: 'collectibles',
        image: 'https://images.unsplash.com/photo-1587925358603-c2eea5305bbc?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
        currentBid: 250,
        timeLeft: '3h 25m',
    },
    {
        id: 2,
        title: 'Modern Abstract Painting',
        category: 'art',
        image: 'https://images.unsplash.com/photo-1523554888454-84137e72c3ce?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
        currentBid: 450,
        timeLeft: '5h 10m',
    },
    {
        id: 3,
        title: 'Professional Camera Kit',
        category: 'electronics',
        image: 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
        currentBid: 1250,
        timeLeft: '2h 05m',
    },
    {
        id: 4,
        title: 'Handcrafted Silver Necklace',
        category: 'jewelry',
        image: 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
        currentBid: 180,
        timeLeft: '6h 45m',
    },
    {
        id: 5,
        title: 'Vintage Vinyl Records Collection',
        category: 'collectibles',
        image: 'https://images.unsplash.com/photo-1603271856248-8a7fe79e1e97?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
        currentBid: 320,
        timeLeft: '1h 30m',
    },
    {
        id: 6,
        title: 'Limited Edition Sneakers',
        category: 'fashion',
        image: 'https://images.unsplash.com/photo-1549298916-b41d501d3772?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80',
        currentBid: 280,
        timeLeft: '4h 15m',
    }
];

// Testimonial data
const testimonials = [
    {
        content: "BidHub has completely changed how I collect vintage items. The platform is easy to use and I've found incredible deals!",
        author: "Sarah Johnson",
        role: "Art Collector",
        avatar: "https://randomuser.me/api/portraits/women/32.jpg",
        rating: 5
    },
    {
        content: "As a seller, I appreciate how BidHub connects me with serious buyers. The auction process is smooth and secure.",
        author: "Michael Rodriguez",
        role: "Antique Dealer",
        avatar: "https://randomuser.me/api/portraits/men/45.jpg",
        rating: 5
    },
    {
        content: "The variety of items available is amazing. I've discovered rare collectibles I couldn't find anywhere else.",
        author: "Emily Chen",
        role: "Enthusiast Collector",
        avatar: "https://randomuser.me/api/portraits/women/68.jpg",
        rating: 4
    }
];

// Initialize the page
document.addEventListener('DOMContentLoaded', () => {
    // Load featured items
    loadFeaturedItems();
    
    // Load auction items
    loadAuctionItems('all');
    
    // Load testimonials
    loadTestimonials();
    
    // Initialize countdown timers
    startCountdowns();
});

// Mobile menu toggle
mobileMenuBtn.addEventListener('click', () => {
    nav.classList.toggle('active');
});

// Modal functions
function openModal(modal) {
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Modal event listeners
loginBtn.addEventListener('click', () => openModal(loginModal));
signupBtn.addEventListener('click', () => openModal(signupModal));
ctaBtn.addEventListener('click', () => openModal(signupModal));

closeModalBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        closeModal(btn.closest('.modal'));
    });
});

showSignupLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        closeModal(loginModal);
        openModal(signupModal);
    });
});

showLoginLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        closeModal(signupModal);
        openModal(loginModal);
    });
});

// Close modal when clicking outside the content
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        closeModal(e.target);
    }
});

// Feature items loader
function loadFeaturedItems() {
    const featuredItems = document.querySelector('.featured-items');
    
    // Pick 3 random items to feature
    const featured = [...auctionItems].sort(() => 0.5 - Math.random()).slice(0, 3);
    
    let featuredHTML = '';
    
    featured.forEach(item => {
        featuredHTML += createAuctionCard(item);
    });
    
    featuredItems.innerHTML = featuredHTML;
}

// Auction items loader
function loadAuctionItems(filter) {
    const auctionItemsContainer = document.querySelector('.auction-items');
    
    let filteredItems = auctionItems;
    if (filter !== 'all') {
        filteredItems = auctionItems.filter(item => item.category === filter);
    }
    
    let auctionHTML = '';
    
    filteredItems.forEach(item => {
        auctionHTML += createAuctionCard(item);
    });
    
    auctionItemsContainer.innerHTML = auctionHTML;
}

// Create auction card HTML
function createAuctionCard(item) {
    return `
        <div class="auction-card" data-id="${item.id}" data-category="${item.category}">
            <img src="${item.image}" alt="${item.title}" class="auction-img">
            <div class="auction-details">
                <h3 class="auction-title">${item.title}</h3>
                <p class="auction-category">${item.category.charAt(0).toUpperCase() + item.category.slice(1)}</p>
                <div class="auction-info">
                    <div class="current-bid">
                        <span class="bid-label">Current Bid</span>
                        <span class="bid-amount">$${item.currentBid}</span>
                    </div>
                    <div class="time-left">
                        <span class="time-label">Time Left</span>
                        <span class="time-value" data-time="${item.timeLeft}">${item.timeLeft}</span>
                    </div>
                </div>
                <button class="btn bid-btn">Place Bid</button>
            </div>
        </div>
    `;
}

// Filter auctions
filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        // Update active button
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Filter items
        const filter = btn.getAttribute('data-filter');
        loadAuctionItems(filter);
        
        // Restart countdown
        startCountdowns();
    });
});

// Load testimonials
function loadTestimonials() {
    const testimonialSlider = document.querySelector('.testimonial-slider');
    let testimonialHTML = '';
    
    testimonials.forEach((testimonial, index) => {
        testimonialHTML += `
            <div class="testimonial-item ${index === 0 ? 'active' : ''}">
                <div class="testimonial-content">
                    <p>${testimonial.content}</p>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">
                        <img src="${testimonial.avatar}" alt="${testimonial.author}">
                    </div>
                    <div class="author-info">
                        <h4>${testimonial.author}</h4>
                        <p>${testimonial.role}</p>
                    </div>
                    <div class="rating">
                        ${createRatingStars(testimonial.rating)}
                    </div>
                </div>
            </div>
        `;
    });
    
    testimonialSlider.innerHTML = testimonialHTML;
}

// Create rating stars
function createRatingStars(rating) {
    let stars = '';
    for (let i = 0; i < 5; i++) {
        if (i < rating) {
            stars += '<i class="fas fa-star"></i>';
        } else {
            stars += '<i class="far fa-star"></i>';
        }
    }
    return stars;
}

// Testimonial slider
let currentTestimonial = 0;

function showTestimonial(index) {
    const testimonialItems = document.querySelectorAll('.testimonial-item');
    
    // Validate index
    if (index < 0) index = testimonialItems.length - 1;
    if (index >= testimonialItems.length) index = 0;
    
    // Update current index
    currentTestimonial = index;
    
    // Hide all testimonials
    testimonialItems.forEach(item => item.classList.remove('active'));
    
    // Show selected testimonial
    testimonialItems[index].classList.add('active');
    
    // Update dots
    testimonialDots.forEach((dot, i) => {
        dot.classList.toggle('active', i === index);
    });
}

// Next/Prev testimonial
nextBtn.addEventListener('click', () => {
    showTestimonial(currentTestimonial + 1);
});

prevBtn.addEventListener('click', () => {
    showTestimonial(currentTestimonial - 1);
});

// Testimonial dot navigation
testimonialDots.forEach((dot, index) => {
    dot.addEventListener('click', () => {
        showTestimonial(index);
    });
});

// Countdown timer
function startCountdowns() {
    const timeElements = document.querySelectorAll('.time-value');
    
    timeElements.forEach(element => {
        // Parse the time string (e.g., "3h 25m")
        let timeString = element.getAttribute('data-time');
        let hours = parseInt(timeString.split('h')[0]);
        let minutes = parseInt(timeString.split('h ')[1].split('m')[0]);
        
        // Convert to seconds
        let totalSeconds = hours * 3600 + minutes * 60;
        
        // Start countdown
        const countdown = setInterval(() => {
            totalSeconds--;
            
            if (totalSeconds <= 0) {
                clearInterval(countdown);
                element.textContent = 'Ended';
                element.closest('.auction-card').classList.add('ended');
            } else {
                const hoursLeft = Math.floor(totalSeconds / 3600);
                const minutesLeft = Math.floor((totalSeconds % 3600) / 60);
                const secondsLeft = totalSeconds % 60;
                
                element.textContent = `${hoursLeft}h ${minutesLeft}m ${secondsLeft}s`;
            }
        }, 1000);
    });
}

// Form submissions
const loginForm = document.querySelector('.login-form');
const signupForm = document.querySelector('.signup-form');

loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    // Get form values
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    
    // Here you would typically send these values to a server
    console.log('Login attempt:', { email, password });
    
    // Simulate successful login
    alert('Login successful!');
    closeModal(loginModal);
});

signupForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    // Get form values
    const name = document.getElementById('signup-name').value;
    const email = document.getElementById('signup-email').value;
    const password = document.getElementById('signup-password').value;
    const confirmPassword = document.getElementById('signup-confirm-password').value;
    
    // Simple validation
    if (password !== confirmPassword) {
        alert('Passwords do not match!');
        return;
    }
    
    // Here you would typically send these values to a server
    console.log('Signup attempt:', { name, email, password });
    
    // Simulate successful signup
    alert('Account created successfully!');
    closeModal(signupModal);
}); 

// Modal logic
const createAuctionBtn = document.querySelector('.create-auction-btn');
const createAuctionModal = document.querySelector('.create-auction-modal');
const closeModals = document.querySelectorAll('.close-modal');

// Open modal
createAuctionBtn.addEventListener('click', () => {
    createAuctionModal.style.display = 'flex';
});

// Close modal on X
closeModals.forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.modal').style.display = 'none';
    });
});

// Add auction to list
document.getElementById('create-auction-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const title = document.getElementById('auction-title').value;
    const category = document.getElementById('auction-category').value;
    const image = document.getElementById('auction-image').value;
    const description = document.getElementById('auction-description').value;
    const price = document.getElementById('auction-price').value;

    const auctionItem = document.createElement('div');
    auctionItem.className = `auction-item ${category}`;
    auctionItem.innerHTML = `
        <div class="auction-card">
            <img src="${image}" alt="${title}">
            <div class="auction-info">
                <h3>${title}</h3>
                <p>${description}</p>
                <span class="price">Starting at $${price}</span>
                <button class="btn">Bid Now</button>
            </div>
        </div>
    `;
    document.querySelector('.auction-items').appendChild(auctionItem);
    createAuctionModal.style.display = 'none';
    this.reset();
});

