const multer = require('multer');
const sharp = require('sharp');
const path = require('path');
const fs = require('fs').promises;
const crypto = require('crypto');

/**
 * Image Upload and Processing Middleware for Riya Collections
 * 
 * This middleware provides:
 * 1. File upload validation with type and size restrictions
 * 2. Image optimization and thumbnail generation
 * 3. Secure file storage with proper permissions
 * 
 * Requirements: 7.2, 13.1, 13.2, 13.3
 */

// Configuration constants
const UPLOAD_CONFIG = {
  // Maximum file size: 5MB
  maxFileSize: 5 * 1024 * 1024,
  
  // Allowed image formats
  allowedMimeTypes: [
    'image/jpeg',
    'image/jpg', 
    'image/png',
    'image/webp'
  ],
  
  // Allowed file extensions
  allowedExtensions: ['.jpg', '.jpeg', '.png', '.webp'],
  
  // Upload directories
  uploadDir: 'uploads',
  productImagesDir: 'uploads/products',
  
  // Image processing settings
  imageQuality: {
    original: 90,
    thumbnail: 80
  },
  
  // Thumbnail dimensions
  thumbnailSizes: {
    small: { width: 150, height: 150 },
    medium: { width: 300, height: 300 },
    large: { width: 600, height: 600 }
  }
};

/**
 * Ensure upload directories exist
 */
const ensureUploadDirectories = async () => {
  try {
    await fs.mkdir(UPLOAD_CONFIG.uploadDir, { recursive: true });
    await fs.mkdir(UPLOAD_CONFIG.productImagesDir, { recursive: true });
    
    // Create subdirectories for different thumbnail sizes
    for (const size of Object.keys(UPLOAD_CONFIG.thumbnailSizes)) {
      await fs.mkdir(path.join(UPLOAD_CONFIG.productImagesDir, size), { recursive: true });
    }
    
    console.log('✅ Upload directories created successfully');
  } catch (error) {
    console.error('❌ Error creating upload directories:', error);
    throw error;
  }
};

/**
 * Generate secure filename
 * @param {string} originalName - Original filename
 * @returns {string} - Secure filename with timestamp and random hash
 */
const generateSecureFilename = (originalName) => {
  const timestamp = Date.now();
  const randomHash = crypto.randomBytes(8).toString('hex');
  const extension = path.extname(originalName).toLowerCase();
  
  // Remove any potentially dangerous characters from original name
  const safeName = path.basename(originalName, extension)
    .replace(/[^a-zA-Z0-9\-_]/g, '') // Remove all non-alphanumeric except dash and underscore
    .substring(0, 50); // Limit length
  
  return `${safeName}_${timestamp}_${randomHash}${extension}`;
};

/**
 * Validate file type and size
 * @param {Object} file - Multer file object
 * @returns {Object} - Validation result
 */
const validateFile = (file) => {
  const errors = [];
  
  // Check if file exists
  if (!file) {
    errors.push('No file provided');
    return { isValid: false, errors };
  }
  
  // Check file size
  if (typeof file.size !== 'number' || file.size < 0 || file.size > UPLOAD_CONFIG.maxFileSize) {
    errors.push(`File size exceeds maximum limit of ${UPLOAD_CONFIG.maxFileSize / (1024 * 1024)}MB`);
  }
  
  // Check MIME type
  if (!file.mimetype || !UPLOAD_CONFIG.allowedMimeTypes.includes(file.mimetype)) {
    errors.push(`Invalid file type. Allowed types: ${UPLOAD_CONFIG.allowedMimeTypes.join(', ')}`);
  }
  
  // Check file extension
  if (!file.originalname) {
    errors.push('Original filename is required');
  } else {
    const extension = path.extname(file.originalname).toLowerCase();
    let effectiveExtension = extension;

    // If filename is like ".png" (empty basename) or extension is empty,
    // derive extension from mimetype when possible to be more tolerant
    if ((!effectiveExtension || effectiveExtension === '.') && file.mimetype) {
      try {
        const mimeExt = `.${file.mimetype.split('/')[1]}`.toLowerCase();
        if (UPLOAD_CONFIG.allowedExtensions.includes(mimeExt)) {
          effectiveExtension = mimeExt;
        }
      } catch (e) {
        // ignore and fall through to extension check
      }
    }

    if (!UPLOAD_CONFIG.allowedExtensions.includes(effectiveExtension)) {
      errors.push(`Invalid file extension. Allowed extensions: ${UPLOAD_CONFIG.allowedExtensions.join(', ')}`);
    }
  }
  
  // Additional security check: verify file signature
  if (file.buffer) {
    const isValidImage = validateImageSignature(file.buffer);
    if (!isValidImage) {
      errors.push('File does not appear to be a valid image');
    }
  } else {
    errors.push('File buffer is required for validation');
  }
  
  return {
    isValid: errors.length === 0,
    errors
  };
};

/**
 * Validate image file signature (magic numbers)
 * @param {Buffer} buffer - File buffer
 * @returns {boolean} - True if valid image signature
 */
const validateImageSignature = (buffer) => {
  if (!buffer || buffer.length < 4) {
    return false;
  }
  
  // Check for common image file signatures
  const signatures = {
    jpeg: [0xFF, 0xD8, 0xFF],
    png: [0x89, 0x50, 0x4E, 0x47],
    webp: [0x52, 0x49, 0x46, 0x46] // RIFF header for WebP
  };
  
  for (const [format, signature] of Object.entries(signatures)) {
    if (signature.every((byte, index) => buffer[index] === byte)) {
      return true;
    }
  }
  
  return false;
};

/**
 * Process and optimize image
 * @param {Buffer} imageBuffer - Original image buffer
 * @param {string} filename - Target filename
 * @param {string} outputDir - Output directory
 * @returns {Object} - Processing result with file paths
 */
const processImage = async (imageBuffer, filename, outputDir) => {
  try {
    const results = {
      original: null,
      thumbnails: {}
    };
    
    // Process original image (optimize but keep original size)
    const originalPath = path.join(outputDir, filename);
    await sharp(imageBuffer)
      .jpeg({ quality: UPLOAD_CONFIG.imageQuality.original, progressive: true })
      .png({ quality: UPLOAD_CONFIG.imageQuality.original, progressive: true })
      .webp({ quality: UPLOAD_CONFIG.imageQuality.original })
      .toFile(originalPath);
    
    results.original = originalPath;
    
    // Generate thumbnails
    for (const [sizeName, dimensions] of Object.entries(UPLOAD_CONFIG.thumbnailSizes)) {
      const thumbnailDir = path.join(outputDir, sizeName);
      const thumbnailPath = path.join(thumbnailDir, filename);
      
      await sharp(imageBuffer)
        .resize(dimensions.width, dimensions.height, {
          fit: 'cover',
          position: 'center'
        })
        .jpeg({ quality: UPLOAD_CONFIG.imageQuality.thumbnail, progressive: true })
        .png({ quality: UPLOAD_CONFIG.imageQuality.thumbnail, progressive: true })
        .webp({ quality: UPLOAD_CONFIG.imageQuality.thumbnail })
        .toFile(thumbnailPath);
      
      results.thumbnails[sizeName] = thumbnailPath;
    }
    
    return results;
  } catch (error) {
    console.error('Error processing image:', error);
    throw new Error('Failed to process image');
  }
};

/**
 * Delete image files (original and thumbnails)
 * @param {string} filename - Filename to delete
 * @param {string} baseDir - Base directory
 */
const deleteImageFiles = async (filename, baseDir = UPLOAD_CONFIG.productImagesDir) => {
  try {
    // Delete original file
    const originalPath = path.join(baseDir, filename);
    try {
      await fs.unlink(originalPath);
    } catch (error) {
      console.warn(`Could not delete original file: ${originalPath}`);
    }
    
    // Delete thumbnails
    for (const sizeName of Object.keys(UPLOAD_CONFIG.thumbnailSizes)) {
      const thumbnailPath = path.join(baseDir, sizeName, filename);
      try {
        await fs.unlink(thumbnailPath);
      } catch (error) {
        console.warn(`Could not delete thumbnail: ${thumbnailPath}`);
      }
    }
  } catch (error) {
    console.error('Error deleting image files:', error);
  }
};

/**
 * Configure multer for memory storage (we'll process files in memory)
 */
const storage = multer.memoryStorage();

/**
 * File filter function for multer
 */
const fileFilter = (req, file, cb) => {
  const validation = validateFile(file);
  
  if (validation.isValid) {
    cb(null, true);
  } else {
    cb(new Error(validation.errors.join('; ')), false);
  }
};

/**
 * Create multer upload middleware
 */
const upload = multer({
  storage,
  fileFilter,
  limits: {
    fileSize: UPLOAD_CONFIG.maxFileSize,
    files: 10 // Maximum 10 files per request
  }
});

/**
 * Middleware to handle single image upload
 */
const uploadSingleImage = (fieldName = 'image') => {
  return async (req, res, next) => {
    // Ensure directories exist
    await ensureUploadDirectories();
    
    // Use multer to handle the upload
    upload.single(fieldName)(req, res, async (err) => {
      if (err) {
        if (err instanceof multer.MulterError) {
          if (err.code === 'LIMIT_FILE_SIZE') {
            return res.status(400).json({
              success: false,
              message: `File size exceeds maximum limit of ${UPLOAD_CONFIG.maxFileSize / (1024 * 1024)}MB`
            });
          }
          return res.status(400).json({
            success: false,
            message: `Upload error: ${err.message}`
          });
        }
        
        return res.status(400).json({
          success: false,
          message: err.message
        });
      }
      
      // If no file uploaded, continue to next middleware
      if (!req.file) {
        return next();
      }
      
      try {
        // Generate secure filename
        const secureFilename = generateSecureFilename(req.file.originalname);
        
        // Process the image
        const processedImages = await processImage(
          req.file.buffer,
          secureFilename,
          UPLOAD_CONFIG.productImagesDir
        );
        
        // Add processed image info to request
        req.processedImage = {
          filename: secureFilename,
          originalName: req.file.originalname,
          size: req.file.size,
          mimetype: req.file.mimetype,
          paths: processedImages,
          url: `/uploads/products/${secureFilename}`,
          thumbnails: {
            small: `/uploads/products/small/${secureFilename}`,
            medium: `/uploads/products/medium/${secureFilename}`,
            large: `/uploads/products/large/${secureFilename}`
          }
        };
        
        next();
      } catch (error) {
        console.error('Error processing uploaded image:', error);
        res.status(500).json({
          success: false,
          message: 'Failed to process uploaded image'
        });
      }
    });
  };
};

/**
 * Middleware to handle multiple image uploads
 */
const uploadMultipleImages = (fieldName = 'images', maxCount = 10) => {
  return async (req, res, next) => {
    // Ensure directories exist
    await ensureUploadDirectories();
    
    // Use multer to handle the upload
    upload.array(fieldName, maxCount)(req, res, async (err) => {
      if (err) {
        if (err instanceof multer.MulterError) {
          if (err.code === 'LIMIT_FILE_SIZE') {
            return res.status(400).json({
              success: false,
              message: `File size exceeds maximum limit of ${UPLOAD_CONFIG.maxFileSize / (1024 * 1024)}MB`
            });
          }
          if (err.code === 'LIMIT_FILE_COUNT') {
            return res.status(400).json({
              success: false,
              message: `Too many files. Maximum ${maxCount} files allowed`
            });
          }
          return res.status(400).json({
            success: false,
            message: `Upload error: ${err.message}`
          });
        }
        
        return res.status(400).json({
          success: false,
          message: err.message
        });
      }
      
      // If no files uploaded, continue to next middleware
      if (!req.files || req.files.length === 0) {
        return next();
      }
      
      try {
        const processedImages = [];
        
        // Process each uploaded file
        for (const file of req.files) {
          // Generate secure filename
          const secureFilename = generateSecureFilename(file.originalname);
          
          // Process the image
          const processedImagePaths = await processImage(
            file.buffer,
            secureFilename,
            UPLOAD_CONFIG.productImagesDir
          );
          
          processedImages.push({
            filename: secureFilename,
            originalName: file.originalname,
            size: file.size,
            mimetype: file.mimetype,
            paths: processedImagePaths,
            url: `/uploads/products/${secureFilename}`,
            thumbnails: {
              small: `/uploads/products/small/${secureFilename}`,
              medium: `/uploads/products/medium/${secureFilename}`,
              large: `/uploads/products/large/${secureFilename}`
            }
          });
        }
        
        // Add processed images info to request
        req.processedImages = processedImages;
        
        next();
      } catch (error) {
        console.error('Error processing uploaded images:', error);
        res.status(500).json({
          success: false,
          message: 'Failed to process uploaded images'
        });
      }
    });
  };
};

/**
 * Cleanup middleware to remove uploaded files on error
 */
const cleanupOnError = (req, res, next) => {
  const originalSend = res.send;
  
  res.send = function(data) {
    // If response is an error and we have processed images, clean them up
    if (res.statusCode >= 400) {
      if (req.processedImage) {
        deleteImageFiles(req.processedImage.filename).catch(console.error);
      }
      
      if (req.processedImages) {
        req.processedImages.forEach(image => {
          deleteImageFiles(image.filename).catch(console.error);
        });
      }
    }
    
    originalSend.call(this, data);
  };
  
  next();
};

/**
 * Get image URL helper
 * @param {string} filename - Image filename
 * @param {string} size - Thumbnail size (optional)
 * @returns {string} - Image URL
 */
const getImageUrl = (filename, size = null) => {
  if (!filename) return null;
  
  if (size && UPLOAD_CONFIG.thumbnailSizes[size]) {
    return `/uploads/products/${size}/${filename}`;
  }
  
  return `/uploads/products/${filename}`;
};

/**
 * Initialize upload system
 */
const initializeUploadSystem = async () => {
  try {
    await ensureUploadDirectories();
    console.log('✅ Image upload system initialized successfully');
  } catch (error) {
    console.error('❌ Failed to initialize image upload system:', error);
    throw error;
  }
};

module.exports = {
  UPLOAD_CONFIG,
  uploadSingleImage,
  uploadMultipleImages,
  cleanupOnError,
  deleteImageFiles,
  getImageUrl,
  initializeUploadSystem,
  validateFile,
  generateSecureFilename,
  processImage
};