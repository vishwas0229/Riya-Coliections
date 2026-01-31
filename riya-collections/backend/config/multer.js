const multer = require('multer');
const path = require('path');
const fs = require('fs');
require('dotenv').config();

// Ensure upload directory exists
const uploadPath = process.env.UPLOAD_PATH || 'uploads';
const productImagesPath = path.join(uploadPath, 'products');

// Create directories if they don't exist
[uploadPath, productImagesPath].forEach(dir => {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
    console.log(`📁 Created directory: ${dir}`);
  }
});

// Configure storage
const storage = multer.diskStorage({
  destination: function (req, file, cb) {
    let uploadDir = uploadPath;
    
    // Determine upload directory based on field name
    if (file.fieldname === 'productImages' || file.fieldname === 'productImage') {
      uploadDir = productImagesPath;
    }
    
    cb(null, uploadDir);
  },
  filename: function (req, file, cb) {
    // Generate unique filename with timestamp and random string
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    const extension = path.extname(file.originalname).toLowerCase();
    const baseName = path.basename(file.originalname, extension)
      .replace(/[^a-zA-Z0-9]/g, '_')
      .substring(0, 20);
    
    cb(null, `${baseName}_${uniqueSuffix}${extension}`);
  }
});

// File filter function
const fileFilter = (req, file, cb) => {
  const allowedTypes = (process.env.ALLOWED_FILE_TYPES || 'image/jpeg,image/png,image/webp')
    .split(',')
    .map(type => type.trim());
  
  if (allowedTypes.includes(file.mimetype)) {
    cb(null, true);
  } else {
    cb(new Error(`Invalid file type. Allowed types: ${allowedTypes.join(', ')}`), false);
  }
};

// Configure multer
const upload = multer({
  storage: storage,
  fileFilter: fileFilter,
  limits: {
    fileSize: parseInt(process.env.MAX_FILE_SIZE) || 5 * 1024 * 1024, // 5MB default
    files: 10 // Maximum 10 files per request
  }
});

// Middleware for single file upload
const uploadSingle = (fieldName) => {
  return (req, res, next) => {
    upload.single(fieldName)(req, res, (err) => {
      if (err instanceof multer.MulterError) {
        if (err.code === 'LIMIT_FILE_SIZE') {
          return res.status(400).json({
            error: 'File too large',
            message: `Maximum file size is ${(parseInt(process.env.MAX_FILE_SIZE) || 5242880) / 1024 / 1024}MB`
          });
        }
        if (err.code === 'LIMIT_FILE_COUNT') {
          return res.status(400).json({
            error: 'Too many files',
            message: 'Maximum 10 files allowed per request'
          });
        }
        return res.status(400).json({
          error: 'File upload error',
          message: err.message
        });
      } else if (err) {
        return res.status(400).json({
          error: 'File validation error',
          message: err.message
        });
      }
      next();
    });
  };
};

// Middleware for multiple file upload
const uploadMultiple = (fieldName, maxCount = 5) => {
  return (req, res, next) => {
    upload.array(fieldName, maxCount)(req, res, (err) => {
      if (err instanceof multer.MulterError) {
        if (err.code === 'LIMIT_FILE_SIZE') {
          return res.status(400).json({
            error: 'File too large',
            message: `Maximum file size is ${(parseInt(process.env.MAX_FILE_SIZE) || 5242880) / 1024 / 1024}MB`
          });
        }
        if (err.code === 'LIMIT_FILE_COUNT') {
          return res.status(400).json({
            error: 'Too many files',
            message: `Maximum ${maxCount} files allowed`
          });
        }
        return res.status(400).json({
          error: 'File upload error',
          message: err.message
        });
      } else if (err) {
        return res.status(400).json({
          error: 'File validation error',
          message: err.message
        });
      }
      next();
    });
  };
};

// Helper function to delete file
const deleteFile = (filePath) => {
  try {
    if (fs.existsSync(filePath)) {
      fs.unlinkSync(filePath);
      console.log(`🗑️ Deleted file: ${filePath}`);
      return true;
    }
    return false;
  } catch (error) {
    console.error(`Error deleting file ${filePath}:`, error.message);
    return false;
  }
};

// Helper function to get file URL
const getFileUrl = (filename, type = 'products') => {
  const baseUrl = process.env.BASE_URL || 'http://localhost:5000';
  return `${baseUrl}/uploads/${type}/${filename}`;
};

module.exports = {
  upload,
  uploadSingle,
  uploadMultiple,
  deleteFile,
  getFileUrl,
  uploadPath,
  productImagesPath
};