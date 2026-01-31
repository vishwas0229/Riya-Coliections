/**
 * Product Image Optimization Script
 * 
 * This script copies product images from frontend assets to backend uploads
 * and creates optimized versions (thumbnails) for different screen sizes.
 * 
 * Requirements: 13.1, 13.2, 13.3 - Optimize images for different screen sizes
 */

const path = require('path');
const fs = require('fs').promises;
const sharp = require('sharp');
const { processImage, UPLOAD_CONFIG } = require('../middleware/image-upload');

// Source and destination paths
const SOURCE_DIR = path.join(__dirname, '../../frontend/assets/Products');
const DEST_DIR = path.join(__dirname, '../uploads/products');

// Image optimization settings
const OPTIMIZATION_CONFIG = {
  // Quality settings for different sizes
  quality: {
    original: 90,
    large: 85,
    medium: 80,
    small: 75
  },
  
  // Size configurations
  sizes: {
    small: { width: 150, height: 150, fit: 'cover' },
    medium: { width: 300, height: 300, fit: 'cover' },
    large: { width: 600, height: 600, fit: 'cover' }
  },
  
  // Format settings
  formats: {
    jpeg: { quality: 85, progressive: true },
    png: { quality: 85, progressive: true },
    webp: { quality: 80, effort: 6 }
  }
};

/**
 * Main optimization function
 */
async function optimizeProductImages() {
  try {
    console.log('🖼️ Starting product image optimization...');
    
    // Ensure destination directories exist
    await ensureDirectories();
    
    // Get list of source images
    const imageFiles = await getImageFiles();
    console.log(`📁 Found ${imageFiles.length} images to optimize`);
    
    // Process each image
    let processed = 0;
    let errors = 0;
    
    for (const imageFile of imageFiles) {
      try {
        await optimizeImage(imageFile);
        processed++;
        console.log(`✅ Optimized: ${imageFile} (${processed}/${imageFiles.length})`);
      } catch (error) {
        errors++;
        console.error(`❌ Failed to optimize ${imageFile}:`, error.message);
      }
    }
    
    console.log(`\n🎉 Optimization completed!`);
    console.log(`✅ Successfully processed: ${processed} images`);
    if (errors > 0) {
      console.log(`❌ Failed: ${errors} images`);
    }
    
    // Generate optimization report
    await generateOptimizationReport(imageFiles);
    
  } catch (error) {
    console.error('❌ Image optimization failed:', error);
    throw error;
  }
}

/**
 * Ensure all required directories exist
 */
async function ensureDirectories() {
  const directories = [
    DEST_DIR,
    path.join(DEST_DIR, 'small'),
    path.join(DEST_DIR, 'medium'),
    path.join(DEST_DIR, 'large')
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
  
  console.log('📁 Created optimization directories');
}

/**
 * Get list of image files from source directory
 */
async function getImageFiles() {
  try {
    const files = await fs.readdir(SOURCE_DIR);
    return files.filter(file => {
      const ext = path.extname(file).toLowerCase();
      return ['.jpg', '.jpeg', '.png', '.webp'].includes(ext);
    });
  } catch (error) {
    console.error('❌ Error reading source directory:', error);
    throw error;
  }
}

/**
 * Optimize a single image file
 */
async function optimizeImage(filename) {
  const sourcePath = path.join(SOURCE_DIR, filename);
  const destPath = path.join(DEST_DIR, filename);
  
  try {
    // Read source image
    const imageBuffer = await fs.readFile(sourcePath);
    
    // Get image metadata
    const metadata = await sharp(imageBuffer).metadata();
    
    // Create optimized original
    await createOptimizedOriginal(imageBuffer, destPath, metadata);
    
    // Create thumbnails
    await createThumbnails(imageBuffer, filename, metadata);
    
  } catch (error) {
    console.error(`Error optimizing ${filename}:`, error);
    throw error;
  }
}

/**
 * Create optimized original image
 */
async function createOptimizedOriginal(imageBuffer, destPath, metadata) {
  const sharpInstance = sharp(imageBuffer);
  
  // Apply format-specific optimizations
  if (metadata.format === 'jpeg' || metadata.format === 'jpg') {
    sharpInstance.jpeg(OPTIMIZATION_CONFIG.formats.jpeg);
  } else if (metadata.format === 'png') {
    sharpInstance.png(OPTIMIZATION_CONFIG.formats.png);
  } else if (metadata.format === 'webp') {
    sharpInstance.webp(OPTIMIZATION_CONFIG.formats.webp);
  }
  
  // Ensure reasonable maximum size for originals
  if (metadata.width > 1200 || metadata.height > 1200) {
    sharpInstance.resize(1200, 1200, {
      fit: 'inside',
      withoutEnlargement: true
    });
  }
  
  await sharpInstance.toFile(destPath);
}

/**
 * Create thumbnail versions
 */
async function createThumbnails(imageBuffer, filename, metadata) {
  for (const [sizeName, sizeConfig] of Object.entries(OPTIMIZATION_CONFIG.sizes)) {
    const thumbnailPath = path.join(DEST_DIR, sizeName, filename);
    
    let sharpInstance = sharp(imageBuffer)
      .resize(sizeConfig.width, sizeConfig.height, {
        fit: sizeConfig.fit,
        position: 'center',
        background: { r: 255, g: 255, b: 255, alpha: 1 }
      });
    
    // Apply format-specific optimizations for thumbnails
    if (metadata.format === 'jpeg' || metadata.format === 'jpg') {
      sharpInstance.jpeg({
        quality: OPTIMIZATION_CONFIG.quality[sizeName],
        progressive: true
      });
    } else if (metadata.format === 'png') {
      sharpInstance.png({
        quality: OPTIMIZATION_CONFIG.quality[sizeName],
        progressive: true
      });
    } else if (metadata.format === 'webp') {
      sharpInstance.webp({
        quality: OPTIMIZATION_CONFIG.quality[sizeName],
        effort: 6
      });
    }
    
    await sharpInstance.toFile(thumbnailPath);
  }
}

/**
 * Generate WebP versions for modern browsers
 */
async function generateWebPVersions() {
  console.log('🔄 Generating WebP versions for modern browsers...');
  
  const imageFiles = await getImageFiles();
  
  for (const filename of imageFiles) {
    const sourcePath = path.join(DEST_DIR, filename);
    const webpFilename = path.parse(filename).name + '.webp';
    
    try {
      // Original WebP
      await sharp(sourcePath)
        .webp(OPTIMIZATION_CONFIG.formats.webp)
        .toFile(path.join(DEST_DIR, webpFilename));
      
      // Thumbnail WebP versions
      for (const sizeName of Object.keys(OPTIMIZATION_CONFIG.sizes)) {
        const thumbnailSource = path.join(DEST_DIR, sizeName, filename);
        const thumbnailWebP = path.join(DEST_DIR, sizeName, webpFilename);
        
        await sharp(thumbnailSource)
          .webp({
            quality: OPTIMIZATION_CONFIG.quality[sizeName],
            effort: 6
          })
          .toFile(thumbnailWebP);
      }
      
      console.log(`✅ Generated WebP: ${webpFilename}`);
    } catch (error) {
      console.error(`❌ Failed to generate WebP for ${filename}:`, error.message);
    }
  }
}

/**
 * Generate optimization report
 */
async function generateOptimizationReport(imageFiles) {
  console.log('\n📊 Generating optimization report...');
  
  const report = {
    timestamp: new Date().toISOString(),
    totalImages: imageFiles.length,
    sizes: ['original', 'small', 'medium', 'large'],
    totalFiles: 0,
    totalSizeBytes: 0,
    averageCompressionRatio: 0,
    details: []
  };
  
  for (const filename of imageFiles) {
    try {
      const originalPath = path.join(SOURCE_DIR, filename);
      const optimizedPath = path.join(DEST_DIR, filename);
      
      const originalStats = await fs.stat(originalPath);
      const optimizedStats = await fs.stat(optimizedPath);
      
      const compressionRatio = (1 - (optimizedStats.size / originalStats.size)) * 100;
      
      const imageReport = {
        filename,
        originalSize: originalStats.size,
        optimizedSize: optimizedStats.size,
        compressionRatio: Math.round(compressionRatio * 100) / 100,
        thumbnails: {}
      };
      
      // Check thumbnail sizes
      for (const sizeName of Object.keys(OPTIMIZATION_CONFIG.sizes)) {
        const thumbnailPath = path.join(DEST_DIR, sizeName, filename);
        try {
          const thumbnailStats = await fs.stat(thumbnailPath);
          imageReport.thumbnails[sizeName] = thumbnailStats.size;
          report.totalFiles++;
          report.totalSizeBytes += thumbnailStats.size;
        } catch (error) {
          // Thumbnail might not exist
        }
      }
      
      report.details.push(imageReport);
      report.totalFiles++; // For original
      report.totalSizeBytes += optimizedStats.size;
      
    } catch (error) {
      console.error(`Error generating report for ${filename}:`, error.message);
    }
  }
  
  // Calculate average compression ratio
  const totalCompression = report.details.reduce((sum, item) => sum + item.compressionRatio, 0);
  report.averageCompressionRatio = Math.round((totalCompression / report.details.length) * 100) / 100;
  
  // Save report
  const reportPath = path.join(DEST_DIR, 'optimization-report.json');
  await fs.writeFile(reportPath, JSON.stringify(report, null, 2));
  
  // Display summary
  console.log(`\n📈 Optimization Summary:`);
  console.log(`   Total images processed: ${report.totalImages}`);
  console.log(`   Total files created: ${report.totalFiles}`);
  console.log(`   Total size: ${formatBytes(report.totalSizeBytes)}`);
  console.log(`   Average compression: ${report.averageCompressionRatio}%`);
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
 * Clean up optimized images
 */
async function cleanupOptimizedImages() {
  console.log('🧹 Cleaning up optimized images...');
  
  try {
    // Remove all files in destination directory
    const files = await fs.readdir(DEST_DIR);
    
    for (const file of files) {
      const filePath = path.join(DEST_DIR, file);
      const stat = await fs.stat(filePath);
      
      if (stat.isDirectory()) {
        // Remove directory contents
        const subFiles = await fs.readdir(filePath);
        for (const subFile of subFiles) {
          await fs.unlink(path.join(filePath, subFile));
        }
      } else {
        // Remove file
        await fs.unlink(filePath);
      }
    }
    
    console.log('✅ Cleanup completed');
  } catch (error) {
    console.error('❌ Error during cleanup:', error);
    throw error;
  }
}

/**
 * Verify optimization results
 */
async function verifyOptimization() {
  console.log('🔍 Verifying optimization results...');
  
  const imageFiles = await getImageFiles();
  const issues = [];
  
  for (const filename of imageFiles) {
    // Check if original exists
    const originalPath = path.join(DEST_DIR, filename);
    try {
      await fs.access(originalPath);
    } catch (error) {
      issues.push(`Missing optimized original: ${filename}`);
    }
    
    // Check if all thumbnails exist
    for (const sizeName of Object.keys(OPTIMIZATION_CONFIG.sizes)) {
      const thumbnailPath = path.join(DEST_DIR, sizeName, filename);
      try {
        await fs.access(thumbnailPath);
      } catch (error) {
        issues.push(`Missing ${sizeName} thumbnail: ${filename}`);
      }
    }
  }
  
  if (issues.length === 0) {
    console.log('✅ All optimized images verified successfully');
  } else {
    console.log('⚠️ Verification issues found:');
    issues.forEach(issue => console.log(`   - ${issue}`));
  }
  
  return issues;
}

// CLI interface
if (require.main === module) {
  const args = process.argv.slice(2);
  const command = args[0];

  (async () => {
    try {
      switch (command) {
        case 'webp':
          await generateWebPVersions();
          break;
        case 'verify':
          await verifyOptimization();
          break;
        case 'cleanup':
          await cleanupOptimizedImages();
          break;
        case 'report':
          const imageFiles = await getImageFiles();
          await generateOptimizationReport(imageFiles);
          break;
        case 'optimize':
        default:
          await optimizeProductImages();
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
  optimizeProductImages,
  generateWebPVersions,
  verifyOptimization,
  cleanupOptimizedImages,
  OPTIMIZATION_CONFIG
};