/**
 * Accessibility Manager
 * Handles keyboard navigation, screen reader support, and ARIA management
 */

class AccessibilityManager {
    constructor() {
        this.isKeyboardUser = false;
        this.focusableElements = [
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"])',
            '[contenteditable="true"]'
        ].join(', ');
        
        this.init();
    }

    init() {
        this.setupKeyboardDetection();
        this.setupSkipLinks();
        this.setupFocusManagement();
        this.setupAriaLiveRegions();
        this.setupModalAccessibility();
        this.setupFormAccessibility();
        this.setupNavigationAccessibility();
        this.setupImageAccessibility();
        this.setupLoadingStates();
    }

    /**
     * Detect keyboard usage for focus management
     */
    setupKeyboardDetection() {
        // Detect keyboard usage
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                this.isKeyboardUser = true;
                document.body.classList.add('keyboard-navigation');
            }
        });

        // Detect mouse usage
        document.addEventListener('mousedown', () => {
            this.isKeyboardUser = false;
            document.body.classList.remove('keyboard-navigation');
        });

        // Focus-visible polyfill
        this.setupFocusVisible();
    }

    /**
     * Setup focus-visible polyfill for better keyboard navigation
     */
    setupFocusVisible() {
        if (!('focus-visible' in document.documentElement.style)) {
            document.body.classList.add('js-focus-visible');
            
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Tab' || e.key === 'Enter' || e.key === ' ' || e.key.startsWith('Arrow')) {
                    document.body.classList.add('keyboard-mode');
                }
            });

            document.addEventListener('mousedown', () => {
                document.body.classList.remove('keyboard-mode');
            });
        }
    }

    /**
     * Setup skip links for keyboard navigation
     */
    setupSkipLinks() {
        const skipLinks = document.createElement('div');
        skipLinks.className = 'skip-links';
        skipLinks.innerHTML = `
            <a href="#main-content" class="skip-link">Skip to main content</a>
            <a href="#navigation" class="skip-link">Skip to navigation</a>
            <a href="#footer" class="skip-link">Skip to footer</a>
        `;
        
        document.body.insertBefore(skipLinks, document.body.firstChild);

        // Add IDs to target elements if they don't exist
        const main = document.querySelector('main') || document.querySelector('.main');
        if (main && !main.id) {
            main.id = 'main-content';
        }

        const nav = document.querySelector('nav') || document.querySelector('.nav');
        if (nav && !nav.id) {
            nav.id = 'navigation';
        }

        const footer = document.querySelector('footer');
        if (footer && !footer.id) {
            footer.id = 'footer';
        }
    }

    /**
     * Setup ARIA live regions for dynamic content updates
     */
    setupAriaLiveRegions() {
        // Create live regions if they don't exist
        if (!document.getElementById('aria-live-polite')) {
            const politeRegion = document.createElement('div');
            politeRegion.id = 'aria-live-polite';
            politeRegion.className = 'live-region';
            politeRegion.setAttribute('aria-live', 'polite');
            politeRegion.setAttribute('aria-atomic', 'true');
            document.body.appendChild(politeRegion);
        }

        if (!document.getElementById('aria-live-assertive')) {
            const assertiveRegion = document.createElement('div');
            assertiveRegion.id = 'aria-live-assertive';
            assertiveRegion.className = 'live-region';
            assertiveRegion.setAttribute('aria-live', 'assertive');
            assertiveRegion.setAttribute('aria-atomic', 'true');
            document.body.appendChild(assertiveRegion);
        }
    }

    /**
     * Announce message to screen readers
     */
    announceToScreenReader(message, priority = 'polite') {
        const regionId = priority === 'assertive' ? 'aria-live-assertive' : 'aria-live-polite';
        const region = document.getElementById(regionId);
        
        if (region) {
            region.textContent = '';
            setTimeout(() => {
                region.textContent = message;
            }, 100);
        }
    }

    /**
     * Setup focus management for interactive elements
     */
    setupFocusManagement() {
        // Trap focus in modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                const modal = document.querySelector('.modal[aria-hidden="false"]');
                if (modal) {
                    this.trapFocus(e, modal);
                }
            }
        });

        // Escape key handling
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.handleEscapeKey();
            }
        });
    }

    /**
     * Trap focus within a container
     */
    trapFocus(event, container) {
        const focusableElements = container.querySelectorAll(this.focusableElements);
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.shiftKey) {
            if (document.activeElement === firstElement) {
                event.preventDefault();
                lastElement.focus();
            }
        } else {
            if (document.activeElement === lastElement) {
                event.preventDefault();
                firstElement.focus();
            }
        }
    }

    /**
     * Handle escape key for closing modals and dropdowns
     */
    handleEscapeKey() {
        // Close modals
        const openModal = document.querySelector('.modal[aria-hidden="false"]');
        if (openModal) {
            this.closeModal(openModal);
            return;
        }

        // Close dropdowns
        const openDropdown = document.querySelector('[aria-expanded="true"]');
        if (openDropdown) {
            this.toggleExpanded(openDropdown, false);
            openDropdown.focus();
        }
    }

    /**
     * Setup modal accessibility
     */
    setupModalAccessibility() {
        // Initialize modal states
        document.querySelectorAll('.modal').forEach(modal => {
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-hidden', 'true');
            
            // Add aria-labelledby if modal has a title
            const title = modal.querySelector('.modal-title');
            if (title && !title.id) {
                title.id = `modal-title-${Date.now()}`;
                modal.setAttribute('aria-labelledby', title.id);
            }
        });

        // Modal trigger buttons
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal-target]');
            if (trigger) {
                const modalId = trigger.getAttribute('data-modal-target');
                const modal = document.getElementById(modalId);
                if (modal) {
                    this.openModal(modal, trigger);
                }
            }
        });

        // Modal close buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.modal-close') || e.target.closest('.modal-overlay')) {
                const modal = e.target.closest('.modal');
                if (modal) {
                    this.closeModal(modal);
                }
            }
        });
    }

    /**
     * Open modal with accessibility features
     */
    openModal(modal, trigger = null) {
        // Store the trigger element to return focus later
        modal.dataset.triggerElement = trigger ? trigger.id || this.generateId(trigger) : '';
        
        // Show modal
        modal.setAttribute('aria-hidden', 'false');
        modal.style.display = 'flex';
        
        // Focus first focusable element in modal
        setTimeout(() => {
            const firstFocusable = modal.querySelector(this.focusableElements);
            if (firstFocusable) {
                firstFocusable.focus();
            }
        }, 100);

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        this.announceToScreenReader('Dialog opened');
    }

    /**
     * Close modal and restore focus
     */
    closeModal(modal) {
        modal.setAttribute('aria-hidden', 'true');
        modal.style.display = 'none';
        
        // Restore focus to trigger element
        const triggerId = modal.dataset.triggerElement;
        if (triggerId) {
            const trigger = document.getElementById(triggerId);
            if (trigger) {
                trigger.focus();
            }
        }
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        this.announceToScreenReader('Dialog closed');
    }

    /**
     * Setup form accessibility
     */
    setupFormAccessibility() {
        // Associate labels with form controls
        document.querySelectorAll('input, select, textarea').forEach(input => {
            if (!input.getAttribute('aria-label') && !input.getAttribute('aria-labelledby')) {
                const label = document.querySelector(`label[for="${input.id}"]`);
                if (label) {
                    input.setAttribute('aria-labelledby', label.id || this.generateId(label));
                }
            }
        });

        // Setup form validation
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.tagName === 'FORM') {
                this.validateForm(form);
            }
        });

        // Real-time validation
        document.addEventListener('blur', (e) => {
            if (e.target.matches('input, select, textarea')) {
                this.validateField(e.target);
            }
        }, true);
    }

    /**
     * Validate form and announce errors
     */
    validateForm(form) {
        const errors = [];
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.setFieldError(field, 'This field is required');
                errors.push(field);
            } else {
                this.clearFieldError(field);
            }
        });

        if (errors.length > 0) {
            const errorMessage = `Form has ${errors.length} error${errors.length > 1 ? 's' : ''}. Please correct and try again.`;
            this.announceToScreenReader(errorMessage, 'assertive');
            errors[0].focus();
        }
    }

    /**
     * Validate individual field
     */
    validateField(field) {
        if (field.hasAttribute('required') && !field.value.trim()) {
            this.setFieldError(field, 'This field is required');
        } else if (field.type === 'email' && field.value && !this.isValidEmail(field.value)) {
            this.setFieldError(field, 'Please enter a valid email address');
        } else {
            this.clearFieldError(field);
        }
    }

    /**
     * Set field error state
     */
    setFieldError(field, message) {
        field.setAttribute('aria-invalid', 'true');
        
        let errorElement = document.getElementById(`${field.id}-error`);
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.id = `${field.id}-error`;
            errorElement.className = 'form-error';
            errorElement.setAttribute('role', 'alert');
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        field.setAttribute('aria-describedby', errorElement.id);
    }

    /**
     * Clear field error state
     */
    clearFieldError(field) {
        field.setAttribute('aria-invalid', 'false');
        const errorElement = document.getElementById(`${field.id}-error`);
        if (errorElement) {
            errorElement.textContent = '';
        }
    }

    /**
     * Setup navigation accessibility
     */
    setupNavigationAccessibility() {
        // Mobile menu toggle
        const menuToggle = document.getElementById('nav-toggle');
        const menu = document.getElementById('nav-menu');
        
        if (menuToggle && menu) {
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-controls', 'nav-menu');
            menu.setAttribute('aria-hidden', 'true');
            
            menuToggle.addEventListener('click', () => {
                const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
                this.toggleExpanded(menuToggle, !isExpanded);
                menu.setAttribute('aria-hidden', isExpanded ? 'true' : 'false');
            });
        }

        // Dropdown menus
        document.querySelectorAll('[data-dropdown-toggle]').forEach(toggle => {
            const targetId = toggle.getAttribute('data-dropdown-toggle');
            const target = document.getElementById(targetId);
            
            if (target) {
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-haspopup', 'true');
                toggle.setAttribute('aria-controls', targetId);
                
                toggle.addEventListener('click', () => {
                    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                    this.toggleExpanded(toggle, !isExpanded);
                });
            }
        });

        // Current page indication
        const currentPath = window.location.pathname;
        document.querySelectorAll('.nav__link').forEach(link => {
            if (link.getAttribute('href') === currentPath || 
                (currentPath.includes(link.getAttribute('href')) && link.getAttribute('href') !== '/')) {
                link.setAttribute('aria-current', 'page');
            }
        });
    }

    /**
     * Toggle expanded state for dropdowns and menus
     */
    toggleExpanded(element, expanded) {
        element.setAttribute('aria-expanded', expanded.toString());
        
        const targetId = element.getAttribute('aria-controls');
        if (targetId) {
            const target = document.getElementById(targetId);
            if (target) {
                target.setAttribute('aria-hidden', (!expanded).toString());
            }
        }
    }

    /**
     * Setup image accessibility
     */
    setupImageAccessibility() {
        // Add alt text to images that don't have it
        document.querySelectorAll('img').forEach(img => {
            if (!img.hasAttribute('alt')) {
                // Try to get alt text from data attributes or nearby text
                const altText = img.dataset.alt || 
                              img.getAttribute('title') || 
                              this.generateAltTextFromContext(img);
                img.setAttribute('alt', altText || '');
            }
            
            // Mark decorative images
            if (img.getAttribute('alt') === '' || img.classList.contains('decorative')) {
                img.setAttribute('role', 'presentation');
                img.setAttribute('aria-hidden', 'true');
            }
        });

        // Lazy loading images
        if ('IntersectionObserver' in window) {
            this.setupLazyLoadingAccessibility();
        }
    }

    /**
     * Generate alt text from image context
     */
    generateAltTextFromContext(img) {
        // Look for nearby text that might describe the image
        const parent = img.parentElement;
        const caption = parent.querySelector('figcaption, .caption');
        if (caption) {
            return caption.textContent.trim();
        }
        
        // Check for product name or title
        const productName = parent.querySelector('.product-name, .item-name, h1, h2, h3');
        if (productName) {
            return productName.textContent.trim();
        }
        
        return '';
    }

    /**
     * Setup lazy loading accessibility
     */
    setupLazyLoadingAccessibility() {
        const lazyImages = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                    
                    // Announce to screen reader when image loads
                    img.addEventListener('load', () => {
                        if (img.alt && !img.hasAttribute('aria-hidden')) {
                            this.announceToScreenReader(`Image loaded: ${img.alt}`);
                        }
                    });
                }
            });
        });
        
        lazyImages.forEach(img => imageObserver.observe(img));
    }

    /**
     * Setup loading states accessibility
     */
    setupLoadingStates() {
        // Loading spinners
        document.querySelectorAll('.loading-spinner').forEach(spinner => {
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-label', 'Loading');
        });

        // Loading overlays
        document.querySelectorAll('.loading-overlay').forEach(overlay => {
            overlay.setAttribute('role', 'status');
            overlay.setAttribute('aria-label', 'Loading content');
            overlay.setAttribute('aria-live', 'polite');
        });
    }

    /**
     * Utility functions
     */
    generateId(element) {
        const id = `element-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        element.id = id;
        return id;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Public API methods
     */
    
    // Focus management
    focusElement(selector) {
        const element = document.querySelector(selector);
        if (element) {
            element.focus();
        }
    }

    // Announce messages
    announce(message, priority = 'polite') {
        this.announceToScreenReader(message, priority);
    }

    // Update loading state
    setLoadingState(element, isLoading, message = 'Loading') {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (element) {
            element.setAttribute('aria-busy', isLoading.toString());
            if (isLoading) {
                element.setAttribute('aria-label', message);
                this.announceToScreenReader(message);
            } else {
                element.removeAttribute('aria-label');
                this.announceToScreenReader('Loading complete');
            }
        }
    }

    // Update page title for SPA navigation
    updatePageTitle(title) {
        document.title = title;
        this.announceToScreenReader(`Page changed to ${title}`);
    }
}

// Initialize accessibility manager
const accessibilityManager = new AccessibilityManager();

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AccessibilityManager;
} else if (typeof window !== 'undefined') {
    window.AccessibilityManager = AccessibilityManager;
    window.accessibility = accessibilityManager;
}