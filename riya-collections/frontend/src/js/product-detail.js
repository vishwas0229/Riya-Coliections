/**
 * Product Detail Page functionality for Riya Collections
 * Handles product display, image gallery, add-to-cart, and related products
 */

class ProductDetailPage {
  constructor() {
    this.productId = null;
    this.product = null;
    this.currentImageIndex = 0;
    this.images = [];
    this.relatedProducts = [];
    this.isLoading = false;
    this.quantity = 1;
    
    this.init();
  }

  /**
   * Initialize product detail page
   */
  init() {
    this.getProductIdFromUrl();
    
    if (!this.productId) {
      this.showError('Invalid product ID');
      return;
    }

    this.setupEventListeners();
    this.loadProduct();
  }

  /**
   * Get product ID from URL parameters
   */
  getProductIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    
    if (id && !isNaN(id)) {
      this.productId = parseInt(id);
    }
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Quantity controls
    const decreaseBtn = DOMUtils.getId('quantity-decrease');
    const increaseBtn = DOMUtils.getId('quantity-increase');
    const quantityInput = DOMUtils.getId('quantity-input');

    if (decreaseBtn) {
      DOMUtils.addEventListener(decreaseBtn, 'click', () => {
        this.updateQuantity(this.quantity - 1);
      });
    }

    if (increaseBtn) {
      DOMUtils.addEventListener(increaseBtn, 'click', () => {
        this.updateQuantity(this.quantity + 1);
      });
    }

    if (quantityInput) {
      DOMUtils.addEventListener(quantityInput, 'change', (e) => {
        this.updateQuantity(parseInt(e.target.value) || 1);
      });
    }

    // Add to cart button
    const addToCartBtn = DOMUtils.getId('add-to-cart-btn');
    if (addToCartBtn) {
      DOMUtils.addEventListener(addToCartBtn, 'click', () => {
        this.addToCart();
      });
    }

    // Wishlist button
    const wishlistBtn = DOMUtils.getId('wishlist-btn');
    if (wishlistBtn) {
      DOMUtils.addEventListener(wishlistBtn, 'click', () => {
        this.toggleWishlist();
      });
    }

    // Share button
    const shareBtn = DOMUtils.getId('share-btn');
    if (shareBtn) {
      DOMUtils.addEventListener(shareBtn, 'click', () => {
        this.shareProduct();
      });
    }

    // Compare button
    const compareBtn = DOMUtils.getId('compare-btn');
    if (compareBtn) {
      DOMUtils.addEventListener(compareBtn, 'click', () => {
        this.addToCompare();
      });
    }

    // Image zoom
    const zoomBtn = DOMUtils.getId('zoom-btn');
    const mainImageContainer = DOMUtils.getElement('.main-image-container');
    
    if (zoomBtn) {
      DOMUtils.addEventListener(zoomBtn, 'click', () => {
        this.openImageZoom();
      });
    }

    if (mainImageContainer) {
      DOMUtils.addEventListener(mainImageContainer, 'click', () => {
        this.openImageZoom();
      });
    }

    // Zoom modal controls
    this.setupZoomModalListeners();

    // Tab navigation
    this.setupTabListeners();

    // Thumbnail navigation
    this.setupThumbnailNavigation();
  }

  /**
   * Setup zoom modal event listeners
   */
  setupZoomModalListeners() {
    const zoomModal = DOMUtils.getId('zoom-modal');
    const zoomClose = DOMUtils.getId('zoom-modal-close');
    const zoomOverlay = DOMUtils.getElement('.zoom-modal__overlay');
    const zoomPrev = DOMUtils.getId('zoom-prev');
    const zoomNext = DOMUtils.getId('zoom-next');

    if (zoomModal) {
      // Close modal
      if (zoomClose) {
        DOMUtils.addEventListener(zoomClose, 'click', () => {
          this.closeImageZoom();
        });
      }

      if (zoomOverlay) {
        DOMUtils.addEventListener(zoomOverlay, 'click', () => {
          this.closeImageZoom();
        });
      }

      // Navigation
      if (zoomPrev) {
        DOMUtils.addEventListener(zoomPrev, 'click', () => {
          this.previousImage();
        });
      }

      if (zoomNext) {
        DOMUtils.addEventListener(zoomNext, 'click', () => {
          this.nextImage();
        });
      }

      // Keyboard navigation
      DOMUtils.addEventListener(document, 'keydown', (e) => {
        if (zoomModal.classList.contains('show')) {
          switch (e.key) {
            case 'Escape':
              this.closeImageZoom();
              break;
            case 'ArrowLeft':
              this.previousImage();
              break;
            case 'ArrowRight':
              this.nextImage();
              break;
          }
        }
      });
    }
  }

  /**
   * Setup tab event listeners
   */
  setupTabListeners() {
    const tabButtons = DOMUtils.getElements('.tab-btn');
    
    tabButtons.forEach(btn => {
      DOMUtils.addEventListener(btn, 'click', () => {
        const tabId = btn.dataset.tab;
        this.switchTab(tabId);
      });
    });
  }

  /**
   * Setup thumbnail navigation
   */
  setupThumbnailNavigation() {
    const thumbnailPrev = DOMUtils.getId('thumbnail-prev');
    const thumbnailNext = DOMUtils.getId('thumbnail-next');

    if (thumbnailPrev) {
      DOMUtils.addEventListener(thumbnailPrev, 'click', () => {
        this.scrollThumbnails('prev');
      });
    }

    if (thumbnailNext) {
      DOMUtils.addEventListener(thumbnailNext, 'click', () => {
        this.scrollThumbnails('next');
      });
    }
  }

  /**
   * Load product data from API
   */
  async loadProduct() {
    try {
      this.showLoading();

      const response = await ApiService.products.getById(this.productId, {
        includeRelated: true,
        relatedLimit: 8
      });

      if (response.success) {
        this.product = response.data.product;
        this.images = this.product.images || [];
        this.relatedProducts = this.product.relatedProducts || [];
        
        this.renderProduct();
        this.hideLoading();
      } else {
        this.showError(response.message || 'Product not found');
      }
    } catch (error) {
      console.error('Error loading product:', error);
      this.showError('Failed to load product. Please try again.');
    }
  }

  /**
   * Render product information
   */
  renderProduct() {
    this.updatePageTitle();
    this.renderBreadcrumb();
    this.renderProductInfo();
    this.renderProductImages();
    this.renderProductSpecs();
    this.renderRelatedProducts();
    this.updateQuantityLimits();
    
    // Show product detail section
    const productDetail = DOMUtils.getId('product-detail');
    if (productDetail) {
      productDetail.style.display = 'block';
    }
  }

  /**
   * Update page title and meta
   */
  updatePageTitle() {
    if (this.product) {
      document.title = `${this.product.name} - Riya Collections`;
      
      // Update meta description
      const metaDesc = document.querySelector('meta[name="description"]');
      if (metaDesc && this.product.description) {
        metaDesc.setAttribute('content', this.product.description.substring(0, 160));
      }
    }
  }

  /**
   * Render breadcrumb navigation
   */
  renderBreadcrumb() {
    const categoryElement = DOMUtils.getId('breadcrumb-category');
    const productElement = DOMUtils.getId('breadcrumb-product');

    if (categoryElement && this.product.category) {
      categoryElement.textContent = this.product.category.name;
      
      // Make category clickable
      const categoryLink = DOMUtils.createElement('a', {
        href: `products.html?category=${this.product.category.id}`,
        className: 'breadcrumb__link'
      });
      categoryLink.textContent = this.product.category.name;
      categoryElement.parentNode.replaceChild(categoryLink, categoryElement);
    }

    if (productElement) {
      productElement.textContent = this.product.name;
    }
  }

  /**
   * Render product information
   */
  renderProductInfo() {
    // Brand
    const brandElement = DOMUtils.getId('product-brand');
    if (brandElement && this.product.brand) {
      brandElement.textContent = this.product.brand;
    }

    // Title
    const titleElement = DOMUtils.getId('product-title');
    if (titleElement) {
      titleElement.textContent = this.product.name;
    }

    // SKU
    const skuElement = DOMUtils.getId('product-sku');
    if (skuElement && this.product.sku) {
      skuElement.textContent = this.product.sku;
    }

    // Price
    this.renderPrice();

    // Stock
    this.renderStock();

    // Description
    const descElement = DOMUtils.getId('product-description');
    if (descElement && this.product.description) {
      descElement.textContent = this.product.description;
    }

    // Rating (if available)
    this.renderRating();
  }

  /**
   * Render product price
   */
  renderPrice() {
    const currentPriceElement = DOMUtils.getId('current-price');
    const originalPriceElement = DOMUtils.getId('original-price');
    const discountElement = DOMUtils.getId('price-discount');

    if (currentPriceElement) {
      currentPriceElement.textContent = FormatUtils.currency(this.product.price);
    }

    // Handle discount pricing
    if (this.product.originalPrice && this.product.originalPrice > this.product.price) {
      if (originalPriceElement) {
        originalPriceElement.textContent = FormatUtils.currency(this.product.originalPrice);
        originalPriceElement.style.display = 'inline';
      }

      if (discountElement) {
        const discountPercent = Math.round(((this.product.originalPrice - this.product.price) / this.product.originalPrice) * 100);
        const savings = this.product.originalPrice - this.product.price;
        
        discountElement.innerHTML = `
          <span class="discount-badge">${discountPercent}% OFF</span>
          <span class="savings-text">Save ${FormatUtils.currency(savings)}</span>
        `;
        discountElement.style.display = 'flex';
      }
    }
  }

  /**
   * Render stock information
   */
  renderStock() {
    const stockStatusElement = DOMUtils.getId('stock-status');
    const stockQuantityElement = DOMUtils.getId('stock-quantity');
    const stockFillElement = DOMUtils.getId('stock-fill');

    const stockQuantity = this.product.stockQuantity || 0;
    let stockStatus, stockLevel, stockText, fillWidth;

    if (stockQuantity === 0) {
      stockStatus = 'out-of-stock';
      stockLevel = 'out';
      stockText = 'Out of Stock';
      fillWidth = 0;
    } else if (stockQuantity <= 5) {
      stockStatus = 'low-stock';
      stockLevel = 'low';
      stockText = 'Low Stock';
      fillWidth = 25;
    } else if (stockQuantity <= 10) {
      stockStatus = 'low-stock';
      stockLevel = 'medium';
      stockText = 'Limited Stock';
      fillWidth = 50;
    } else {
      stockStatus = 'in-stock';
      stockLevel = 'high';
      stockText = 'In Stock';
      fillWidth = 100;
    }

    if (stockStatusElement) {
      stockStatusElement.textContent = stockText;
      stockStatusElement.className = `stock-status ${stockStatus}`;
    }

    if (stockQuantityElement) {
      if (stockQuantity > 0 && stockQuantity <= 10) {
        stockQuantityElement.textContent = `Only ${stockQuantity} left`;
      } else if (stockQuantity > 10) {
        stockQuantityElement.textContent = `${stockQuantity} available`;
      } else {
        stockQuantityElement.textContent = '';
      }
    }

    if (stockFillElement) {
      stockFillElement.style.width = `${fillWidth}%`;
      stockFillElement.className = `stock-fill ${stockLevel}`;
    }

    // Update add to cart button
    const addToCartBtn = DOMUtils.getId('add-to-cart-btn');
    if (addToCartBtn) {
      if (stockQuantity === 0) {
        addToCartBtn.disabled = true;
        addToCartBtn.querySelector('.btn-text').textContent = 'Out of Stock';
      } else {
        addToCartBtn.disabled = false;
        addToCartBtn.querySelector('.btn-text').textContent = 'Add to Cart';
      }
    }
  }

  /**
   * Render product rating (if available)
   */
  renderRating() {
    if (!this.product.rating) return;

    const ratingElement = DOMUtils.getId('product-rating');
    const starsElement = DOMUtils.getId('rating-stars');
    const textElement = DOMUtils.getId('rating-text');
    const linkElement = DOMUtils.getId('rating-link');

    if (ratingElement) {
      ratingElement.style.display = 'flex';
    }

    if (starsElement) {
      starsElement.innerHTML = this.renderStars(this.product.rating);
    }

    if (textElement) {
      textElement.textContent = `${this.product.rating.toFixed(1)} out of 5`;
    }

    if (linkElement && this.product.reviewCount) {
      linkElement.textContent = `${this.product.reviewCount} reviews`;
    }
  }

  /**
   * Render star rating
   */
  renderStars(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 !== 0;
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);

    let starsHTML = '';
    
    // Full stars
    for (let i = 0; i < fullStars; i++) {
      starsHTML += '<i class="ri-star-fill rating-star filled"></i>';
    }
    
    // Half star
    if (hasHalfStar) {
      starsHTML += '<i class="ri-star-half-line rating-star filled"></i>';
    }
    
    // Empty stars
    for (let i = 0; i < emptyStars; i++) {
      starsHTML += '<i class="ri-star-line rating-star"></i>';
    }

    return starsHTML;
  }

  /**
   * Render product images and gallery
   */
  renderProductImages() {
    if (!this.images || this.images.length === 0) {
      // Use placeholder if no images
      this.images = [{
        url: '../assets/placeholder.jpg',
        altText: this.product.name,
        isPrimary: true
      }];
    }

    this.renderMainImage();
    this.renderThumbnails();
    this.renderImageBadges();
  }

  /**
   * Render main product image
   */
  renderMainImage() {
    const mainImage = DOMUtils.getId('main-image');
    if (mainImage && this.images[this.currentImageIndex]) {
      const image = this.images[this.currentImageIndex];
      mainImage.src = image.url;
      mainImage.alt = image.altText || this.product.name;
    }
  }

  /**
   * Render thumbnail gallery
   */
  renderThumbnails() {
    const container = DOMUtils.getId('thumbnails-container');
    if (!container) return;

    const thumbnailsHTML = this.images.map((image, index) => `
      <div class="thumbnail-item ${index === this.currentImageIndex ? 'active' : ''}" 
           data-index="${index}">
        <img src="${image.url}" 
             alt="${image.altText || this.product.name}" 
             class="thumbnail-image">
      </div>
    `).join('');

    container.innerHTML = thumbnailsHTML;

    // Add click listeners
    const thumbnails = container.querySelectorAll('.thumbnail-item');
    thumbnails.forEach((thumb, index) => {
      DOMUtils.addEventListener(thumb, 'click', () => {
        this.setCurrentImage(index);
      });
    });
  }

  /**
   * Render image badges
   */
  renderImageBadges() {
    const badgesContainer = DOMUtils.getId('image-badges');
    if (!badgesContainer) return;

    const badges = [];

    // Out of stock badge
    if (this.product.stockQuantity === 0) {
      badges.push('<div class="image-badge image-badge--out-of-stock">Out of Stock</div>');
    }

    // Sale badge
    if (this.product.originalPrice && this.product.originalPrice > this.product.price) {
      const discountPercent = Math.round(((this.product.originalPrice - this.product.price) / this.product.originalPrice) * 100);
      badges.push(`<div class="image-badge image-badge--sale">${discountPercent}% Off</div>`);
    }

    // New badge (if product is less than 30 days old)
    if (this.product.createdAt) {
      const createdDate = new Date(this.product.createdAt);
      const daysSinceCreated = (Date.now() - createdDate.getTime()) / (1000 * 60 * 60 * 24);
      if (daysSinceCreated <= 30) {
        badges.push('<div class="image-badge image-badge--new">New</div>');
      }
    }

    badgesContainer.innerHTML = badges.join('');
  }

  /**
   * Set current image
   */
  setCurrentImage(index) {
    if (index >= 0 && index < this.images.length) {
      this.currentImageIndex = index;
      this.renderMainImage();
      this.updateThumbnailActive();
    }
  }

  /**
   * Update active thumbnail
   */
  updateThumbnailActive() {
    const thumbnails = DOMUtils.getElements('.thumbnail-item');
    thumbnails.forEach((thumb, index) => {
      thumb.classList.toggle('active', index === this.currentImageIndex);
    });
  }

  /**
   * Scroll thumbnails
   */
  scrollThumbnails(direction) {
    const container = DOMUtils.getId('thumbnails-container');
    if (!container) return;

    const scrollAmount = 200;
    const currentScroll = container.scrollLeft;
    
    if (direction === 'prev') {
      container.scrollTo({
        left: currentScroll - scrollAmount,
        behavior: 'smooth'
      });
    } else {
      container.scrollTo({
        left: currentScroll + scrollAmount,
        behavior: 'smooth'
      });
    }
  }

  /**
   * Open image zoom modal
   */
  openImageZoom() {
    const zoomModal = DOMUtils.getId('zoom-modal');
    const zoomImage = DOMUtils.getId('zoom-modal-image');
    const zoomThumbnails = DOMUtils.getId('zoom-modal-thumbnails');

    if (!zoomModal || !zoomImage) return;

    // Set current image
    const currentImage = this.images[this.currentImageIndex];
    zoomImage.src = currentImage.url;
    zoomImage.alt = currentImage.altText || this.product.name;

    // Render zoom thumbnails
    if (zoomThumbnails && this.images.length > 1) {
      const thumbnailsHTML = this.images.map((image, index) => `
        <div class="zoom-thumbnail ${index === this.currentImageIndex ? 'active' : ''}" 
             data-index="${index}">
          <img src="${image.url}" alt="${image.altText || this.product.name}">
        </div>
      `).join('');

      zoomThumbnails.innerHTML = thumbnailsHTML;

      // Add click listeners
      const zoomThumbs = zoomThumbnails.querySelectorAll('.zoom-thumbnail');
      zoomThumbs.forEach((thumb, index) => {
        DOMUtils.addEventListener(thumb, 'click', () => {
          this.setCurrentImage(index);
          this.updateZoomImage();
        });
      });
    }

    // Update navigation buttons
    this.updateZoomNavigation();

    // Show modal
    zoomModal.classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  /**
   * Close image zoom modal
   */
  closeImageZoom() {
    const zoomModal = DOMUtils.getId('zoom-modal');
    if (zoomModal) {
      zoomModal.classList.remove('show');
      document.body.style.overflow = '';
    }
  }

  /**
   * Update zoom modal image
   */
  updateZoomImage() {
    const zoomImage = DOMUtils.getId('zoom-modal-image');
    if (zoomImage && this.images[this.currentImageIndex]) {
      const image = this.images[this.currentImageIndex];
      zoomImage.src = image.url;
      zoomImage.alt = image.altText || this.product.name;
    }

    // Update zoom thumbnails
    const zoomThumbnails = DOMUtils.getElements('.zoom-thumbnail');
    zoomThumbnails.forEach((thumb, index) => {
      thumb.classList.toggle('active', index === this.currentImageIndex);
    });

    this.updateZoomNavigation();
  }

  /**
   * Update zoom navigation buttons
   */
  updateZoomNavigation() {
    const prevBtn = DOMUtils.getId('zoom-prev');
    const nextBtn = DOMUtils.getId('zoom-next');

    if (prevBtn) {
      prevBtn.disabled = this.currentImageIndex === 0;
    }

    if (nextBtn) {
      nextBtn.disabled = this.currentImageIndex === this.images.length - 1;
    }
  }

  /**
   * Navigate to previous image
   */
  previousImage() {
    if (this.currentImageIndex > 0) {
      this.setCurrentImage(this.currentImageIndex - 1);
      this.updateZoomImage();
    }
  }

  /**
   * Navigate to next image
   */
  nextImage() {
    if (this.currentImageIndex < this.images.length - 1) {
      this.setCurrentImage(this.currentImageIndex + 1);
      this.updateZoomImage();
    }
  }

  /**
   * Update quantity
   */
  updateQuantity(newQuantity) {
    const maxQuantity = Math.min(this.product.stockQuantity, APP_CONFIG.CART.MAX_QUANTITY);
    const minQuantity = APP_CONFIG.CART.MIN_QUANTITY;

    // Validate quantity
    if (newQuantity < minQuantity) {
      newQuantity = minQuantity;
    } else if (newQuantity > maxQuantity) {
      newQuantity = maxQuantity;
    }

    this.quantity = newQuantity;

    // Update UI
    const quantityInput = DOMUtils.getId('quantity-input');
    const decreaseBtn = DOMUtils.getId('quantity-decrease');
    const increaseBtn = DOMUtils.getId('quantity-increase');

    if (quantityInput) {
      quantityInput.value = this.quantity;
    }

    if (decreaseBtn) {
      decreaseBtn.disabled = this.quantity <= minQuantity;
    }

    if (increaseBtn) {
      increaseBtn.disabled = this.quantity >= maxQuantity;
    }
  }

  /**
   * Update quantity limits based on stock
   */
  updateQuantityLimits() {
    const quantityInput = DOMUtils.getId('quantity-input');
    if (quantityInput && this.product) {
      const maxQuantity = Math.min(this.product.stockQuantity, APP_CONFIG.CART.MAX_QUANTITY);
      quantityInput.max = maxQuantity;
      
      // Reset quantity if it exceeds stock
      if (this.quantity > maxQuantity) {
        this.updateQuantity(maxQuantity);
      }
    }
  }

  /**
   * Add product to cart
   */
  async addToCart() {
    if (this.isLoading || !this.product || this.product.stockQuantity === 0) {
      return;
    }

    const addToCartBtn = DOMUtils.getId('add-to-cart-btn');
    
    try {
      this.isLoading = true;
      
      // Show loading state
      if (addToCartBtn) {
        addToCartBtn.classList.add('loading');
        addToCartBtn.disabled = true;
      }

      // Add to cart via Cart component
      if (window.Cart) {
        await window.Cart.addItem(this.productId, this.quantity);
        this.showNotification(`${this.product.name} added to cart!`, 'success');
      } else {
        throw new Error('Cart system not available');
      }

    } catch (error) {
      console.error('Add to cart error:', error);
      this.showNotification(error.message || 'Failed to add product to cart', 'error');
    } finally {
      this.isLoading = false;
      
      // Hide loading state
      if (addToCartBtn) {
        addToCartBtn.classList.remove('loading');
        addToCartBtn.disabled = this.product.stockQuantity === 0;
      }
    }
  }

  /**
   * Toggle wishlist
   */
  async toggleWishlist() {
    const wishlistBtn = DOMUtils.getId('wishlist-btn');
    
    if (!isAuthenticated()) {
      this.showNotification('Please log in to manage wishlist', 'warning');
      return;
    }

    if (wishlistBtn) {
      const isActive = wishlistBtn.classList.contains('active');
      wishlistBtn.classList.toggle('active');
      
      // TODO: Implement wishlist API calls
      this.showNotification(
        isActive ? 'Removed from wishlist' : 'Added to wishlist', 
        'success'
      );
    }
  }

  /**
   * Share product
   */
  shareProduct() {
    if (navigator.share && this.product) {
      navigator.share({
        title: this.product.name,
        text: this.product.description,
        url: window.location.href
      }).catch(console.error);
    } else {
      // Fallback: copy URL to clipboard
      navigator.clipboard.writeText(window.location.href).then(() => {
        this.showNotification('Product link copied to clipboard!', 'success');
      }).catch(() => {
        this.showNotification('Unable to share product', 'error');
      });
    }
  }

  /**
   * Add to compare
   */
  addToCompare() {
    // TODO: Implement product comparison functionality
    this.showNotification('Compare feature coming soon!', 'info');
  }

  /**
   * Switch tab
   */
  switchTab(tabId) {
    // Update tab buttons
    const tabButtons = DOMUtils.getElements('.tab-btn');
    tabButtons.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabId);
    });

    // Update tab panels
    const tabPanels = DOMUtils.getElements('.tab-panel');
    tabPanels.forEach(panel => {
      panel.classList.toggle('active', panel.id === `${tabId}-tab`);
    });

    // Load tab content if needed
    if (tabId === 'specifications') {
      this.loadSpecifications();
    }
  }

  /**
   * Render product specifications
   */
  renderProductSpecs() {
    // This will be called when specifications tab is activated
    this.loadSpecifications();
  }

  /**
   * Load specifications content
   */
  loadSpecifications() {
    const specsContent = DOMUtils.getId('specifications-content');
    if (!specsContent || !this.product) return;

    // Create specifications from product data
    const specs = {
      'Product Information': {
        'Brand': this.product.brand || 'N/A',
        'SKU': this.product.sku || 'N/A',
        'Category': this.product.category?.name || 'N/A'
      },
      'Availability': {
        'Stock Status': this.product.stockQuantity > 0 ? 'In Stock' : 'Out of Stock',
        'Quantity Available': this.product.stockQuantity || 0
      }
    };

    // Add price information
    if (this.product.price) {
      specs['Pricing'] = {
        'Current Price': FormatUtils.currency(this.product.price)
      };
      
      if (this.product.originalPrice && this.product.originalPrice > this.product.price) {
        specs['Pricing']['Original Price'] = FormatUtils.currency(this.product.originalPrice);
        const discount = Math.round(((this.product.originalPrice - this.product.price) / this.product.originalPrice) * 100);
        specs['Pricing']['Discount'] = `${discount}%`;
      }
    }

    const specsHTML = `
      <div class="specifications-grid">
        ${Object.entries(specs).map(([groupName, groupSpecs]) => `
          <div class="spec-group">
            <h4>${groupName}</h4>
            ${Object.entries(groupSpecs).map(([label, value]) => `
              <div class="spec-item">
                <span class="spec-label">${label}</span>
                <span class="spec-value">${value}</span>
              </div>
            `).join('')}
          </div>
        `).join('')}
      </div>
    `;

    specsContent.innerHTML = specsHTML;
  }

  /**
   * Render related products
   */
  renderRelatedProducts() {
    if (!this.relatedProducts || this.relatedProducts.length === 0) {
      return;
    }

    const section = DOMUtils.getId('related-products-section');
    const grid = DOMUtils.getId('related-products-grid');

    if (!section || !grid) return;

    const productsHTML = this.relatedProducts.map(product => this.renderRelatedProductCard(product)).join('');
    grid.innerHTML = productsHTML;

    // Add event listeners
    const productCards = grid.querySelectorAll('.product-card');
    productCards.forEach(card => {
      const productId = card.dataset.productId;

      // Card click
      DOMUtils.addEventListener(card, 'click', (e) => {
        if (!e.target.closest('button')) {
          window.location.href = `product.html?id=${productId}`;
        }
      });

      // Add to cart button
      const addToCartBtn = card.querySelector('.product-card__add-to-cart');
      if (addToCartBtn) {
        DOMUtils.addEventListener(addToCartBtn, 'click', async (e) => {
          e.stopPropagation();
          try {
            if (window.Cart) {
              await window.Cart.addItem(parseInt(productId), 1);
              this.showNotification('Product added to cart!', 'success');
            }
          } catch (error) {
            this.showNotification('Failed to add product to cart', 'error');
          }
        });
      }
    });

    section.style.display = 'block';
  }

  /**
   * Render related product card
   */
  renderRelatedProductCard(product) {
    const isOutOfStock = product.stockQuantity <= 0;
    const hasDiscount = product.originalPrice && product.originalPrice > product.price;
    const discountPercent = hasDiscount ? Math.round(((product.originalPrice - product.price) / product.originalPrice) * 100) : 0;

    return `
      <div class="product-card" data-product-id="${product.id}">
        <div class="product-card__image-container">
          <img src="${product.primaryImage || '../assets/placeholder.jpg'}" 
               alt="${product.name}" 
               class="product-card__image"
               loading="lazy">
          
          ${isOutOfStock ? '<div class="product-card__badge product-card__badge--out-of-stock">Out of Stock</div>' : ''}
          ${hasDiscount ? `<div class="product-card__badge product-card__badge--sale">${discountPercent}% Off</div>` : ''}
        </div>
        
        <div class="product-card__content">
          ${product.brand ? `<div class="product-card__brand">${product.brand}</div>` : ''}
          
          <h3 class="product-card__title">${product.name}</h3>
          
          <div class="product-card__price">
            <span class="product-card__current-price">${FormatUtils.currency(product.price)}</span>
            ${hasDiscount ? `<span class="product-card__original-price">${FormatUtils.currency(product.originalPrice)}</span>` : ''}
          </div>
          
          <div class="product-card__footer">
            <button class="product-card__add-to-cart" 
                    data-product-id="${product.id}"
                    ${isOutOfStock ? 'disabled' : ''}>
              <i class="ri-shopping-cart-line"></i>
              ${isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
            </button>
          </div>
        </div>
      </div>
    `;
  }

  /**
   * Show loading state
   */
  showLoading() {
    const loadingElement = DOMUtils.getId('product-loading');
    const errorElement = DOMUtils.getId('product-error');
    const detailElement = DOMUtils.getId('product-detail');

    if (loadingElement) loadingElement.style.display = 'block';
    if (errorElement) errorElement.style.display = 'none';
    if (detailElement) detailElement.style.display = 'none';
  }

  /**
   * Hide loading state
   */
  hideLoading() {
    const loadingElement = DOMUtils.getId('product-loading');
    if (loadingElement) {
      loadingElement.style.display = 'none';
    }
  }

  /**
   * Show error state
   */
  showError(message) {
    const loadingElement = DOMUtils.getId('product-loading');
    const errorElement = DOMUtils.getId('product-error');
    const errorMessageElement = DOMUtils.getId('error-message');
    const detailElement = DOMUtils.getId('product-detail');

    if (loadingElement) loadingElement.style.display = 'none';
    if (detailElement) detailElement.style.display = 'none';
    
    if (errorElement) {
      errorElement.style.display = 'block';
    }
    
    if (errorMessageElement) {
      errorMessageElement.textContent = message;
    }

    // Update page title
    document.title = 'Product Not Found - Riya Collections';
  }

  /**
   * Show notification
   */
  showNotification(message, type = 'info') {
    // This will be handled by the main notification system
    console.log(`${type.toUpperCase()}: ${message}`);
  }
}

// Initialize product detail page when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  // Only initialize on product detail page
  if (window.location.pathname.includes('product.html')) {
    const productDetailPage = new ProductDetailPage();
    
    // Export for global access
    window.productDetailPage = productDetailPage;
  }
});

// Export class for testing
window.ProductDetailPageClass = ProductDetailPage;