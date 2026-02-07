/**
 * Simple Home Page - Works without API
 * Quick fix for loading issue
 */

// Hide all loading spinners immediately
document.addEventListener('DOMContentLoaded', function() {
    console.log('Home page loading...');
    
    // Hide loading spinners
    hideLoadingStates();
    
    // Setup basic functionality
    setupNavigation();
    setupAnimations();
    setupScrollEffects();
    
    console.log('Home page loaded successfully!');
});

/**
 * Hide all loading states
 */
function hideLoadingStates() {
    // Hide categories loading
    const categoriesLoading = document.querySelector('.categories__loading');
    if (categoriesLoading) {
        categoriesLoading.style.display = 'none';
    }
    
    // Hide featured products loading
    const featuredLoading = document.querySelector('.featured__loading');
    if (featuredLoading) {
        featuredLoading.style.display = 'none';
    }
    
    // Hide main loading overlay
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
    }
    
    // Show empty state messages
    showEmptyStates();
}

/**
 * Show empty state messages
 */
function showEmptyStates() {
    // Categories empty state
    const categoriesContainer = document.getElementById('categories-container');
    if (categoriesContainer) {
        categoriesContainer.innerHTML = `
            <div class="empty-state">
                <p>Categories will be loaded from the API</p>
                <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                    Configure your database and start the backend to see categories
                </p>
            </div>
        `;
    }
    
    // Featured products empty state
    const featuredCarousel = document.getElementById('featured-carousel');
    if (featuredCarousel) {
        featuredCarousel.innerHTML = `
            <div class="empty-state">
                <p>Featured products will be loaded from the API</p>
                <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                    Configure your database and start the backend to see products
                </p>
            </div>
        `;
    }
}

/**
 * Setup navigation
 */
function setupNavigation() {
    // Mobile menu toggle
    const navToggle = document.getElementById('nav-toggle');
    const navMenu = document.getElementById('nav-menu');
    const navClose = document.getElementById('nav-close');
    
    if (navToggle) {
        navToggle.addEventListener('click', () => {
            navMenu?.classList.add('show-menu');
        });
    }
    
    if (navClose) {
        navClose.addEventListener('click', () => {
            navMenu?.classList.remove('show-menu');
        });
    }
    
    // Close menu when clicking nav links
    const navLinks = document.querySelectorAll('.nav__link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu?.classList.remove('show-menu');
        });
    });
    
    // User menu toggle
    const userBtn = document.getElementById('user-btn');
    const userMenu = document.getElementById('user-menu');
    
    if (userBtn && userMenu) {
        userBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.classList.toggle('show');
        });
        
        // Close user menu when clicking outside
        document.addEventListener('click', () => {
            userMenu.classList.remove('show');
        });
    }
    
    // Search overlay
    const searchBtn = document.getElementById('search-btn');
    const searchOverlay = document.getElementById('search-overlay');
    const searchClose = document.getElementById('search-close');
    
    if (searchBtn && searchOverlay) {
        searchBtn.addEventListener('click', () => {
            searchOverlay.classList.add('active');
            document.getElementById('search-input')?.focus();
        });
    }
    
    if (searchClose && searchOverlay) {
        searchClose.addEventListener('click', () => {
            searchOverlay.classList.remove('active');
        });
    }
}

/**
 * Setup animations
 */
function setupAnimations() {
    // Animate hero stats
    const statNumbers = document.querySelectorAll('.hero__stat-number');
    statNumbers.forEach(stat => {
        const target = parseInt(stat.getAttribute('data-count') || '0');
        animateCounter(stat, target);
    });
    
    // Scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);
    
    // Observe sections
    const sections = document.querySelectorAll('.section');
    sections.forEach(section => observer.observe(section));
}

/**
 * Animate counter
 */
function animateCounter(element, target) {
    let current = 0;
    const increment = target / 50;
    const duration = 2000;
    const stepTime = duration / 50;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target.toLocaleString();
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current).toLocaleString();
        }
    }, stepTime);
}

/**
 * Setup scroll effects
 */
function setupScrollEffects() {
    // Header scroll effect
    const header = document.getElementById('header');
    let lastScroll = 0;
    
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 100) {
            header?.classList.add('scroll-header');
        } else {
            header?.classList.remove('scroll-header');
        }
        
        lastScroll = currentScroll;
    });
    
    // Back to top button
    const backToTop = document.getElementById('back-to-top');
    if (backToTop) {
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 500) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });
        
        backToTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                const headerHeight = header?.offsetHeight || 80;
                const targetPosition = target.offsetTop - headerHeight;
                
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Newsletter form
document.getElementById('newsletter-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const email = this.querySelector('input[type="email"]').value;
    alert('Thank you for subscribing! (Email: ' + email + ')');
    this.reset();
});

console.log('Home page script loaded');
