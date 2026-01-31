/**
 * Category Image Optimization Script
 * 
 * This script optimizes category images and creates consistent branding assets
 * for the Riya Collections e-commerce platform.
 * 
 * Requirements: 11.2, 11.4 - Category images and consistent branding
 */

const path = require('path');
const fs = require('fs').promises;
const sharp = require('sharp');

// Source and destination paths
const SOURCE_DIR = path.join(__dirname, '../../frontend/assets/categories');
const DEST_DIR = path.join(__dirname, '../uploads/categories');
const HERO_SOURCE = path.join(__dirname, '../../frontend/assets');

// Category image optimization settings
const CATEGORY_CONFIG = {
  // Quality settings for different sizes
  quality: {
    hero: 95,
    banner: 90,
    card: 85,
    thumbnail: 80
  },
  
  // Size configurations for category images
  sizes: {
    hero: { width: 1920, height: 800, fit: 'cover' },
    banner: { width: 1200, height: 400, fit: 'cover' },
    card: { width: 400, height: 300, fit: 'cover' },
    thumbnail: { width: 150, height: 150, fit: 'cover' }
  },
  
  // Branding overlay settings
  branding: {
    logo: {
      width: 120,
      opacity: 0.8,
      position: 'bottom-right',
      margin: 30
    },
    gradient: {
      colors: ['#E91E63', '#FF6B9D'],
      opacity: 0.3,
      direction: 'to-bottom-right'
    }
  },
  
  // Format settings
  formats: {
    jpeg: { quality: 90, progressive: true },
    webp: { quality: 85, effort: 6 }
  }
};

// Category definitions with branding information
const CATEGORIES = [
  {
    id: 1,
    name: 'Face Makeup',
    filename: 'Face_Makeup.png',
    description: 'Premium face makeup products for a flawless look',
    color: '#E91E63',
    gradient: ['#E91E63', '#F8BBD9']
  },
  {
    id: 2,
    name: 'Hair Care',
    filename: 'Hair_Care.png',
    description: 'Nourishing hair care solutions for healthy hair',
    color: '#FF6B9D',
    gradient: ['#FF6B9D', '#FFB3D1']
  },
  {
    id: 3,
    name: 'Lip Care',
    filename: 'Lip_Care.png',
    description: 'Beautiful lip care and color products',
    color: '#FFC1CC',
    gradient: ['#FFC1CC', '#FFE4E6']
  },
  {
    id: 4,
    name: 'Skin Care',
    filename: 'Skin_Care.png',
    description: 'Advanced skincare for radiant, healthy skin',
    color: '#FF8A95',
    gradient: ['#FF8A95', '#FFC1CC']
  }
];

/**
 * Main category image optimization function
 */
async function optimizeCategoryImages() {
  try {
    console.log('🎨 Starting category image optimization and branding...');
    
    // Ensure destination directories exist
    await ensureDirectories();
    
    // Process each category
    let processed = 0;
    let errors = 0;
    
    for (const category of CATEGORIES) {
      try {
        await processCategoryImage(category);
        processed++;
        console.log(`✅ Processed: ${category.name} (${processed}/${CATEGORIES.length})`);
      } catch (error) {
        errors++;
        console.error(`❌ Failed to process ${category.name}:`, error.message);
      }
    }
    
    // Create hero images and promotional banners
    await createHeroImages();
    await createPromotionalBanners();
    
    console.log(`\n🎉 Category optimization completed!`);
    console.log(`✅ Successfully processed: ${processed} categories`);
    if (errors > 0) {
      console.log(`❌ Failed: ${errors} categories`);
    }
    
    // Generate branding report
    await generateBrandingReport();
    
  } catch (error) {
    console.error('❌ Category image optimization failed:', error);
    throw error;
  }
}

/**
 * Ensure all required directories exist
 */
async function ensureDirectories() {
  const directories = [
    DEST_DIR,
    path.join(DEST_DIR, 'hero'),
    path.join(DEST_DIR, 'banner'),
    path.join(DEST_DIR, 'card'),
    path.join(DEST_DIR, 'thumbnail'),
    path.join(DEST_DIR, 'promotional')
  ];
  
  for (const dir of directories) {
    try {
      await fs.mkdir(dir, { recursive: true });
    } catch (error) {
      if (error.code !== 'EEXIST') {
        throw error;
      }
    }
  }
  
  console.log('📁 Created category image directories');
}

/**
 * Process a single category image
 */
async function processCategoryImage(category) {
  const sourcePath = path.join(SOURCE_DIR, category.filename);
  
  try {
    // Check if source image exists
    await fs.access(sourcePath);
    
    // Read source image
    const imageBuffer = await fs.readFile(sourcePath);
    
    // Get image metadata
    const metadata = await sharp(imageBuffer).metadata();
    console.log(`📊 Processing ${category.name}: ${metadata.width}x${metadata.height} ${metadata.format}`);
    
    // Create optimized versions for each size
    for (const [sizeName, sizeConfig] of Object.entries(CATEGORY_CONFIG.sizes)) {
      await createOptimizedCategoryImage(
        imageBuffer, 
        category, 
        sizeName, 
        sizeConfig, 
        metadata
      );
    }
    
    // Create WebP versions
    await createWebPVersions(imageBuffer, category, metadata);
    
  } catch (error) {
    if (error.code === 'ENOENT') {
      console.warn(`⚠️ Source image not found: ${category.filename}, creating placeholder`);
      await createPlaceholderImage(category);
    } else {
      throw error;
    }
  }
}

/**
 * Create optimized category image with branding
 */
async function createOptimizedCategoryImage(imageBuffer, category, sizeName, sizeConfig, metadata) {
  const outputPath = path.join(DEST_DIR, sizeName, `${category.name.toLowerCase().replace(/\s+/g, '_')}.jpg`);
  
  // Create base image with proper sizing
  let sharpInstance = sharp(imageBuffer)
    .resize(sizeConfig.width, sizeConfig.height, {
      fit: sizeConfig.fit,
      position: 'center',
      background: { r: 248, g: 187, b: 217, alpha: 1 } // Light pink background
    });
  
  // Apply branding overlay for larger sizes
  if (sizeName === 'hero' || sizeName === 'banner') {
    // Create gradient overlay
    const gradientOverlay = await createGradientOverlay(
      sizeConfig.width, 
      sizeConfig.height, 
      category.gradient
    );
    
    sharpInstance = sharpInstance.composite([
      {
        input: gradientOverlay,
        blend: 'overlay'
      }
    ]);
  }
  
  // Apply format-specific optimizations
  sharpInstance = sharpInstance.jpeg({
    quality: CATEGORY_CONFIG.quality[sizeName],
    progressive: true
  });
  
  await sharpInstance.toFile(outputPath);
}

/**
 * Create gradient overlay buffer
 */
async function createGradientOverlay(width, height, gradientColors) {
  const svg = `
    <svg width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" style="stop-color:${gradientColors[0]};stop-opacity:0.3" />
          <stop offset="100%" style="stop-color:${gradientColors[1]};stop-opacity:0.1" />
        </linearGradient>
      </defs>
      <rect width="100%" height="100%" fill="url(#grad)" />
    </svg>
  `;
  
  return Buffer.from(svg);
}

/**
 * Create WebP versions for modern browsers
 */
async function createWebPVersions(imageBuffer, category, metadata) {
  for (const [sizeName, sizeConfig] of Object.entries(CATEGORY_CONFIG.sizes)) {
    const outputPath = path.join(DEST_DIR, sizeName, `${category.name.toLowerCase().replace(/\s+/g, '_')}.webp`);
    
    let sharpInstance = sharp(imageBuffer)
      .resize(sizeConfig.width, sizeConfig.height, {
        fit: sizeConfig.fit,
        position: 'center',
        background: { r: 248, g: 187, b: 217, alpha: 1 }
      });
    
    // Apply branding for larger sizes
    if (sizeName === 'hero' || sizeName === 'banner') {
      const gradientOverlay = await createGradientOverlay(
        sizeConfig.width, 
        sizeConfig.height, 
        category.gradient
      );
      
      sharpInstance = sharpInstance.composite([
        {
          input: gradientOverlay,
          blend: 'overlay'
        }
      ]);
    }
    
    await sharpInstance
      .webp({
        quality: CATEGORY_CONFIG.quality[sizeName],
        effort: 6
      })
      .toFile(outputPath);
  }
}

/**
 * Create placeholder image for missing categories
 */
async function createPlaceholderImage(category) {
  const placeholderSvg = `
    <svg width="400" height="300" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" style="stop-color:${category.gradient[0]};stop-opacity:1" />
          <stop offset="100%" style="stop-color:${category.gradient[1]};stop-opacity:1" />
        </linearGradient>
      </defs>
      <rect width="100%" height="100%" fill="url(#grad)" />
      <text x="50%" y="45%" text-anchor="middle" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white">${category.name}</text>
      <text x="50%" y="60%" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" fill="white" opacity="0.9">${category.description}</text>
    </svg>
  `;
  
  const placeholderBuffer = Buffer.from(placeholderSvg);
  
  // Create all sizes from placeholder
  for (const [sizeName, sizeConfig] of Object.entries(CATEGORY_CONFIG.sizes)) {
    const outputPath = path.join(DEST_DIR, sizeName, `${category.name.toLowerCase().replace(/\s+/g, '_')}.jpg`);
    
    await sharp(placeholderBuffer)
      .resize(sizeConfig.width, sizeConfig.height, {
        fit: sizeConfig.fit,
        position: 'center'
      })
      .jpeg({
        quality: CATEGORY_CONFIG.quality[sizeName],
        progressive: true
      })
      .toFile(outputPath);
  }
}

/**
 * Create hero images for home page
 */
async function createHeroImages() {
  console.log('🖼️ Creating hero images...');
  
  try {
    // Check if hero image exists
    const heroSourcePath = path.join(HERO_SOURCE, 'hero-img.jpeg');
    await fs.access(heroSourcePath);
    
    const heroBuffer = await fs.readFile(heroSourcePath);
    
    // Create optimized hero image
    const heroOutputPath = path.join(DEST_DIR, 'hero', 'main-hero.jpg');
    await sharp(heroBuffer)
      .resize(1920, 800, {
        fit: 'cover',
        position: 'center'
      })
      .jpeg({
        quality: CATEGORY_CONFIG.quality.hero,
        progressive: true
      })
      .toFile(heroOutputPath);
    
    // Create WebP version
    const heroWebPPath = path.join(DEST_DIR, 'hero', 'main-hero.webp');
    await sharp(heroBuffer)
      .resize(1920, 800, {
        fit: 'cover',
        position: 'center'
      })
      .webp({
        quality: CATEGORY_CONFIG.quality.hero,
        effort: 6
      })
      .toFile(heroWebPPath);
    
    console.log('✅ Hero images created');
    
  } catch (error) {
    console.warn('⚠️ Hero image not found, creating branded placeholder');
    await createBrandedHeroPlaceholder();
  }
}

/**
 * Create branded hero placeholder
 */
async function createBrandedHeroPlaceholder() {
  const heroSvg = `
    <svg width="1920" height="800" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="heroGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" style="stop-color:#E91E63;stop-opacity:1" />
          <stop offset="50%" style="stop-color:#FF6B9D;stop-opacity:0.9" />
          <stop offset="100%" style="stop-color:#FFC1CC;stop-opacity:0.8" />
        </linearGradient>
        <pattern id="dots" patternUnits="userSpaceOnUse" width="50" height="50">
          <circle cx="25" cy="25" r="2" fill="white" opacity="0.1"/>
        </pattern>
      </defs>
      <rect width="100%" height="100%" fill="url(#heroGrad)" />
      <rect width="100%" height="100%" fill="url(#dots)" />
      <text x="50%" y="45%" text-anchor="middle" font-family="Playfair Display, serif" font-size="72" font-weight="bold" fill="white">Riya Collections</text>
      <text x="50%" y="55%" text-anchor="middle" font-family="Inter, sans-serif" font-size="24" fill="white" opacity="0.9">Premium Beauty & Cosmetics</text>
    </svg>
  `;
  
  const heroBuffer = Buffer.from(heroSvg);
  
  // Create JPEG version
  await sharp(heroBuffer)
    .jpeg({
      quality: CATEGORY_CONFIG.quality.hero,
      progressive: true
    })
    .toFile(path.join(DEST_DIR, 'hero', 'main-hero.jpg'));
  
  // Create WebP version
  await sharp(heroBuffer)
    .webp({
      quality: CATEGORY_CONFIG.quality.hero,
      effort: 6
    })
    .toFile(path.join(DEST_DIR, 'hero', 'main-hero.webp'));
}

/**
 * Create promotional banners
 */
async function createPromotionalBanners() {
  console.log('🎯 Creating promotional banners...');
  
  const promotions = [
    {
      name: 'summer-sale',
      title: 'Summer Sale',
      subtitle: 'Up to 50% Off',
      colors: ['#E91E63', '#FF6B9D']
    },
    {
      name: 'new-arrivals',
      title: 'New Arrivals',
      subtitle: 'Latest Beauty Trends',
      colors: ['#FF6B9D', '#FFC1CC']
    },
    {
      name: 'free-shipping',
      title: 'Free Shipping',
      subtitle: 'On Orders Above ₹999',
      colors: ['#FFC1CC', '#FFE4E6']
    }
  ];
  
  for (const promo of promotions) {
    await createPromotionalBanner(promo);
  }
  
  console.log('✅ Promotional banners created');
}

/**
 * Create individual promotional banner
 */
async function createPromotionalBanner(promo) {
  const bannerSvg = `
    <svg width="600" height="200" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="promoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" style="stop-color:${promo.colors[0]};stop-opacity:1" />
          <stop offset="100%" style="stop-color:${promo.colors[1]};stop-opacity:1" />
        </linearGradient>
        <pattern id="promoDots" patternUnits="userSpaceOnUse" width="30" height="30">
          <circle cx="15" cy="15" r="1" fill="white" opacity="0.2"/>
        </pattern>
      </defs>
      <rect width="100%" height="100%" fill="url(#promoGrad)" rx="15" />
      <rect width="100%" height="100%" fill="url(#promoDots)" rx="15" />
      <text x="50%" y="40%" text-anchor="middle" font-family="Playfair Display, serif" font-size="36" font-weight="bold" fill="white">${promo.title}</text>
      <text x="50%" y="65%" text-anchor="middle" font-family="Inter, sans-serif" font-size="18" fill="white" opacity="0.9">${promo.subtitle}</text>
    </svg>
  `;
  
  const bannerBuffer = Buffer.from(bannerSvg);
  
  // Create JPEG version
  await sharp(bannerBuffer)
    .jpeg({
      quality: CATEGORY_CONFIG.quality.banner,
      progressive: true
    })
    .toFile(path.join(DEST_DIR, 'promotional', `${promo.name}.jpg`));
  
  // Create WebP version
  await sharp(bannerBuffer)
    .webp({
      quality: CATEGORY_CONFIG.quality.banner,
      effort: 6
    })
    .toFile(path.join(DEST_DIR, 'promotional', `${promo.name}.webp`));
}

/**
 * Generate branding report
 */
async function generateBrandingReport() {
  console.log('\n📊 Generating branding report...');
  
  const report = {
    timestamp: new Date().toISOString(),
    categories: CATEGORIES.length,
    sizes: Object.keys(CATEGORY_CONFIG.sizes),
    totalFiles: 0,
    totalSizeBytes: 0,
    brandingElements: {
      colorPalette: ['#E91E63', '#FF6B9D', '#FFC1CC', '#FFE4E6'],
      gradients: CATEGORIES.map(cat => cat.gradient),
      typography: ['Playfair Display', 'Inter'],
      logoPlacement: 'bottom-right'
    },
    details: []
  };
  
  // Calculate file statistics
  for (const category of CATEGORIES) {
    const categoryReport = {
      name: category.name,
      color: category.color,
      gradient: category.gradient,
      files: {}
    };
    
    for (const sizeName of Object.keys(CATEGORY_CONFIG.sizes)) {
      const jpegPath = path.join(DEST_DIR, sizeName, `${category.name.toLowerCase().replace(/\s+/g, '_')}.jpg`);
      const webpPath = path.join(DEST_DIR, sizeName, `${category.name.toLowerCase().replace(/\s+/g, '_')}.webp`);
      
      try {
        const jpegStats = await fs.stat(jpegPath);
        const webpStats = await fs.stat(webpPath);
        
        categoryReport.files[sizeName] = {
          jpeg: jpegStats.size,
          webp: webpStats.size
        };
        
        report.totalFiles += 2;
        report.totalSizeBytes += jpegStats.size + webpStats.size;
      } catch (error) {
        // File might not exist
      }
    }
    
    report.details.push(categoryReport);
  }
  
  // Save report
  const reportPath = path.join(DEST_DIR, 'branding-report.json');
  await fs.writeFile(reportPath, JSON.stringify(report, null, 2));
  
  // Display summary
  console.log(`\n🎨 Branding Summary:`);
  console.log(`   Categories processed: ${report.categories}`);
  console.log(`   Size variants: ${report.sizes.join(', ')}`);
  console.log(`   Total files created: ${report.totalFiles}`);
  console.log(`   Total size: ${formatBytes(report.totalSizeBytes)}`);
  console.log(`   Color palette: ${report.brandingElements.colorPalette.join(', ')}`);
  console.log(`   Report saved: ${reportPath}`);
}

/**
 * Format bytes to human readable format
 */
function formatBytes(bytes, decimals = 2) {
  if (bytes === 0) return '0 Bytes';
  
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Update frontend assets with optimized versions
 */
async function updateFrontendAssets() {
  console.log('🔄 Updating frontend assets with optimized versions...');
  
  try {
    // Copy optimized card-sized images back to frontend
    for (const category of CATEGORIES) {
      const sourcePath = path.join(DEST_DIR, 'card', `${category.name.toLowerCase().replace(/\s+/g, '_')}.jpg`);
      const destPath = path.join(SOURCE_DIR, category.filename);
      
      try {
        await fs.copyFile(sourcePath, destPath);
        console.log(`✅ Updated: ${category.filename}`);
      } catch (error) {
        console.warn(`⚠️ Could not update ${category.filename}:`, error.message);
      }
    }
    
    // Copy hero image
    const heroSource = path.join(DEST_DIR, 'hero', 'main-hero.jpg');
    const heroDest = path.join(HERO_SOURCE, 'hero-img-optimized.jpg');
    
    try {
      await fs.copyFile(heroSource, heroDest);
      console.log('✅ Updated hero image');
    } catch (error) {
      console.warn('⚠️ Could not update hero image:', error.message);
    }
    
    console.log('✅ Frontend assets updated');
    
  } catch (error) {
    console.error('❌ Error updating frontend assets:', error);
    throw error;
  }
}

// CLI interface
if (require.main === module) {
  const args = process.argv.slice(2);
  const command = args[0];

  (async () => {
    try {
      switch (command) {
        case 'hero':
          await createHeroImages();
          break;
        case 'banners':
          await createPromotionalBanners();
          break;
        case 'update':
          await updateFrontendAssets();
          break;
        case 'report':
          await generateBrandingReport();
          break;
        case 'optimize':
        default:
          await optimizeCategoryImages();
          await updateFrontendAssets();
          break;
      }
      process.exit(0);
    } catch (error) {
      console.error('❌ Script failed:', error);
      process.exit(1);
    }
  })();
}

module.exports = {
  optimizeCategoryImages,
  createHeroImages,
  createPromotionalBanners,
  updateFrontendAssets,
  CATEGORY_CONFIG,
  CATEGORIES
};