/**
 * Image serving routes for optimized assets
 * 
 * Serves optimized category and product images with proper caching
 * Requirements: 11.2, 11.4, 13.1, 13.2, 13.3
 */

const express = require('express');
const path = require('path');
const fs = require('fs').promises;
const router = express.Router();

// Image directories
const UPLOADS_DIR = path.join(__dirname, '../uploads');
const CATEGORIES_DIR = path.join(UPLOADS_DIR, 'categories');
const PRODUCTS_DIR = path.join(UPLOADS_DIR, 'products');

// Cache control settings
const CACHE_DURATION = {
  images: 7 * 24 * 60 * 60, // 7 days
  thumbnails: 30 * 24 * 60 * 60, // 30 days
  hero: 24 * 60 * 60 // 1 day
};

/**
 * Serve category images
 * GET /api/images/categories/:size/:filename
 */
router.get('/categories/:size/:filename', async (req, res) => {
  try {
    const { size, filename } = req.params;
    
    // Validate size parameter
    const validSizes = ['hero', 'banner', 'card', 'thumbnail'];
    if (!validSizes.includes(size)) {
      return res.status(400).json({
        success: false,
        message: 'Invalid image size'
      });
    }
    
    // Validate filename
    if (!filename || !isValidFilename(filename)) {
      return res.status(400).json({
        success: false,
        message: 'Invalid filename'
      });
    }
    
    const imagePath = path.join(CATEGORIES_DIR, size, filename);
    
    // Check if file exists
    try {
      await fs.access(imagePath);
    } catch (error) {
      // Try fallback to original assets
      const fallbackPath = await findFallbackImage('categories', filename);
      if (fallbackPath) {
        return serveImage(res, fallbackPath, getCacheDuration(size));
      }
      
      return res.status(404).json({
        success: false,
        message: 'Image not found'
      });
    }
    
    // Serve the image
    await serveImage(res, imagePath, getCacheDuration(size));
    
  } catch (error) {
    console.error('Error serving category image:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error'
    });
  }
});

/**
 * Serve product images
 * GET /api/images/products/:size/:filename
 */
router.get('/products/:size?/:filename', async (req, res) => {
  try {
    const { size, filename } = req.params;
    
    // If no size specified, serve original
    const imageSize = size || 'original';
    
    // Validate size parameter for products
    const validSizes = ['original', 'large', 'medium', 'small'];
    if (size && !validSizes.includes(size)) {
      return res.status(400).json({
        success: false,
        message: 'Invalid image size'
      });
    }
    
    // Validate filename
    const targetFilename = size ? filename : size; // If no size, size is actually filename
    if (!targetFilename || !isValidFilename(targetFilename)) {
      return res.status(400).json({
        success: false,
        message: 'Invalid filename'
      });
    }
    
    let imagePath;
    if (imageSize === 'original') {
      imagePath = path.join(PRODUCTS_DIR, targetFilename);
    } else {
      imagePath = path.join(PRODUCTS_DIR, imageSize, targetFilename);
    }
    
    // Check if file exists
    try {
      await fs.access(imagePath);
    } catch (error) {
      // Try fallback to original assets
      const fallbackPath = await findFallbackImage('products', targetFilename);
      if (fallbackPath) {
        return serveImage(res, fallbackPath, getCacheDuration(imageSize));
      }
      
      return res.status(404).json({
        success: false,
        message: 'Image not found'
      });
    }
    
    // Serve the image
    await serveImage(res, imagePath, getCacheDuration(imageSize));
    
  } catch (error) {
    console.error('Error serving product image:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error'
    });
  }
});

/**
 * Serve hero images
 * GET /api/images/hero/:filename
 */
router.get('/hero/:filename', async (req, res) => {
  try {
    const { filename } = req.params;
    
    // Validate filename
    if (!filename || !isValidFilename(filename)) {
      return res.status(400).json({
        success: false,
        message: 'Invalid filename'
      });
    }
    
    const imagePath = path.join(CATEGORIES_DIR, 'hero', filename);
    
    // Check if file exists
    try {
      await fs.access(imagePath);
    } catch (error) {
      return res.status(404).json({
        success: false,
        message: 'Hero image not found'
      });
    }
    
    // Serve the image
    await serveImage(res, imagePath, CACHE_DURATION.hero);
    
  } catch (error) {
    console.error('Error serving hero image:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error'
    });
  }
});

/**
 * Serve promotional banners
 * GET /api/images/promotional/:filename
 */
router.get('/promotional/:filename', async (req, res) => {
  try {
    const { filename } = req.params;
    
    // Validate filename
    if (!filename || !isValidFilename(filename)) {
      return res.status(400).json({
        success: false,
        message: 'Invalid filename'
      });
    }
    
    const imagePath = path.join(CATEGORIES_DIR, 'promotional', filename);
    
    // Check if file exists
    try {
      await fs.access(imagePath);
    } catch (error) {
      return res.status(404).json({
        success: false,
        message: 'Promotional image not found'
      });
    }
    
    // Serve the image
    await serveImage(res, imagePath, CACHE_DURATION.images);
    
  } catch (error) {
    console.error('Error serving promotional image:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error'
    });
  }
});

/**
 * Get image metadata
 * GET /api/images/metadata/:type/:filename
 */
router.get('/metadata/:type/:filename', async (req, res) => {
  try {
    const { type, filename } = req.params;
    
    // Validate type
    const validTypes = ['categories', 'products', 'hero', 'promotional'];
    if (!validTypes.includes(type)) {
      return res.status(400).json({
        success: false,
        message: 'Invalid image type'
      });
    }
    
    // Validate filename
    if (!filename || !isValidFilename(filename)) {
      return res.status(400).json({
        success: false,
        message: 'Invalid filename'
      });
    }
    
    const metadata = await getImageMetadata(type, filename);
    
    res.json({
      success: true,
      data: metadata
    });
    
  } catch (error) {
    console.error('Error getting image metadata:', error);
    res.status(500).json({
      success: false,
      message: 'Internal server error'
    });
  }
});

/**
 * Serve image file with proper headers
 */
async function serveImage(res, imagePath, cacheDuration) {
  try {
    const stats = await fs.stat(imagePath);
    const ext = path.extname(imagePath).toLowerCase();
    
    // Set content type based on extension
    const contentType = getContentType(ext);
    if (!contentType) {
      throw new Error('Unsupported image format');
    }
    
    // Set cache headers
    res.set({
      'Content-Type': contentType,
      'Content-Length': stats.size,
      'Cache-Control': `public, max-age=${cacheDuration}`,
      'ETag': `"${stats.mtime.getTime()}-${stats.size}"`,
      'Last-Modified': stats.mtime.toUTCString()
    });
    
    // Check if client has cached version
    const clientETag = req.get('If-None-Match');
    const serverETag = `"${stats.mtime.getTime()}-${stats.size}"`;
    
    if (clientETag === serverETag) {
      return res.status(304).end();
    }
    
    // Stream the file
    const readStream = require('fs').createReadStream(imagePath);
    readStream.pipe(res);
    
  } catch (error) {
    throw error;
  }
}

/**
 * Get content type for file extension
 */
function getContentType(ext) {
  const contentTypes = {
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.png': 'image/png',
    '.webp': 'image/webp',
    '.gif': 'image/gif',
    '.svg': 'image/svg+xml'
  };
  
  return contentTypes[ext];
}

/**
 * Get cache duration based on image type
 */
function getCacheDuration(size) {
  if (size === 'hero') {
    return CACHE_DURATION.hero;
  } else if (size === 'thumbnail' || size === 'small') {
    return CACHE_DURATION.thumbnails;
  } else {
    return CACHE_DURATION.images;
  }
}

/**
 * Validate filename for security
 */
function isValidFilename(filename) {
  // Allow alphanumeric, hyphens, underscores, and dots
  const validPattern = /^[a-zA-Z0-9._-]+$/;
  
  // Prevent directory traversal
  if (filename.includes('..') || filename.includes('/') || filename.includes('\\')) {
    return false;
  }
  
  // Check pattern
  return validPattern.test(filename);
}

/**
 * Find fallback image in original assets
 */
async function findFallbackImage(type, filename) {
  try {
    const frontendAssetsDir = path.join(__dirname, '../../frontend/assets');
    let fallbackPath;
    
    if (type === 'categories') {
      fallbackPath = path.join(frontendAssetsDir, 'categories', filename);
    } else if (type === 'products') {
      fallbackPath = path.join(frontendAssetsDir, 'Products', filename);
    }
    
    if (fallbackPath) {
      await fs.access(fallbackPath);
      return fallbackPath;
    }
    
    return null;
  } catch (error) {
    return null;
  }
}

/**
 * Get image metadata
 */
async function getImageMetadata(type, filename) {
  const metadata = {
    filename,
    type,
    sizes: {},
    formats: [],
    totalSize: 0
  };
  
  try {
    let baseDir;
    let sizes;
    
    if (type === 'categories') {
      baseDir = CATEGORIES_DIR;
      sizes = ['hero', 'banner', 'card', 'thumbnail'];
    } else if (type === 'products') {
      baseDir = PRODUCTS_DIR;
      sizes = ['original', 'large', 'medium', 'small'];
    }
    
    if (baseDir && sizes) {
      for (const size of sizes) {
        const sizeDir = path.join(baseDir, size);
        
        try {
          const files = await fs.readdir(sizeDir);
          const matchingFiles = files.filter(file => 
            file.startsWith(path.parse(filename).name)
          );
          
          for (const file of matchingFiles) {
            const filePath = path.join(sizeDir, file);
            const stats = await fs.stat(filePath);
            const ext = path.extname(file).toLowerCase();
            
            if (!metadata.sizes[size]) {
              metadata.sizes[size] = {};
            }
            
            metadata.sizes[size][ext.substring(1)] = {
              size: stats.size,
              modified: stats.mtime
            };
            
            metadata.totalSize += stats.size;
            
            if (!metadata.formats.includes(ext.substring(1))) {
              metadata.formats.push(ext.substring(1));
            }
          }
        } catch (error) {
          // Size directory might not exist
        }
      }
    }
    
    return metadata;
  } catch (error) {
    throw error;
  }
}

module.exports = router;