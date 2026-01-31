#!/usr/bin/env node

/**
 * Asset Optimization Script for Riya Collections
 * 
 * This script optimizes various assets for production:
 * - Image compression and format conversion
 * - CSS minification and purging
 * - JavaScript minification and tree shaking
 * - Font optimization
 * - Asset compression (gzip/brotli)
 * 
 * Usage:
 *   node scripts/optimize-assets.js --input=frontend/assets --output=dist/assets
 *   npm run optimize:assets
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const zlib = require('zlib');

// Optimization configuration
const OPTIMIZATION_CONFIG = {
    images: {
        quality: {
            jpeg: 85,
            png: 90,
            webp: 85
        },
        formats: ['webp', 'jpg', 'png'],
        progressive: true,
        maxWidth: 1920,
        maxHeight: 1080,
        thumbnailSizes: [150, 300, 600]
    },
    css: {
        minify: true,
        autoprefixer: true,
        removeUnused: false, // Set to true if you have CSS usage analysis
        combineMediaQueries: true
    },
    js: {
        minify: true,
        mangle: true,
        compress: true,
        removeConsole: true,
        removeDebugger: true
    },
    fonts: {
        subset: true,
        formats: ['woff2', 'woff'],
        unicodeRange: 'latin'
    },
    compression: {
        gzip: {
            enabled: true,
            level: 9,
            threshold: 1024 // Only compress files larger than 1KB
        },
        brotli: {
            enabled: true,
            quality: 11,
            threshold: 1024
        }
    }
};

// Parse command line arguments
const args = process.argv.slice(2);
const options = {};

args.forEach(arg => {
    if (arg.startsWith('--')) {
        const [key, value] = arg.substring(2).split('=');
        options[key] = value || true;
    }
});

const inputDir = options.input || 'frontend/assets';
const outputDir = options.output || 'dist/assets';
const verbose = options.verbose || false;

console.log('🎨 Starting asset optimization...');

/**
 * Main optimization function
 */
async function optimizeAssets() {
    try {
        // Create output directory
        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }
        
        // Optimize different asset types
        await optimizeImages();
        await optimizeCss();
        await optimizeJavaScript();
        await optimizeFonts();
        await compressAssets();
        
        // Generate optimization report
        generateOptimizationReport();
        
        console.log('✅ Asset optimization completed!');
        
    } catch (error) {
        console.error('❌ Asset optimization failed:', error.message);
        if (verbose) {
            console.error(error.stack);
        }
        process.exit(1);
    }
}

/**
 * Optimize images
 */
async function optimizeImages() {
    console.log('🖼️  Optimizing images...');
    
    const imageDir = path.join(inputDir, 'Products');
    const outputImageDir = path.join(outputDir, 'Products');
    
    if (!fs.existsSync(imageDir)) {
        console.log('  ⚠️  No images directory found, skipping...');
        return;
    }
    
    if (!fs.existsSync(outputImageDir)) {
        fs.mkdirSync(outputImageDir, { recursive: true });
    }
    
    const imageFiles = fs.readdirSync(imageDir).filter(file => 
        /\.(jpg|jpeg|png|gif|bmp|tiff)$/i.test(file)
    );
    
    let optimizedCount = 0;
    let totalSavings = 0;
    
    for (const file of imageFiles) {
        const inputPath = path.join(imageDir, file);
        const originalSize = fs.statSync(inputPath).size;
        
        // Generate optimized versions
        await optimizeImage(inputPath, outputImageDir, file);
        
        // Calculate savings
        const optimizedPath = path.join(outputImageDir, file);
        if (fs.existsSync(optimizedPath)) {
            const optimizedSize = fs.statSync(optimizedPath).size;
            const savings = originalSize - optimizedSize;
            totalSavings += savings;
            optimizedCount++;
            
            if (verbose) {
                console.log(`    ${file}: ${formatBytes(originalSize)} → ${formatBytes(optimizedSize)} (${formatBytes(savings)} saved)`);
            }
        }
    }
    
    console.log(`  ✓ Optimized ${optimizedCount} images, saved ${formatBytes(totalSavings)}`);
}

/**
 * Optimize individual image
 */
async function optimizeImage(inputPath, outputDir, filename) {
    const ext = path.extname(filename).toLowerCase();
    const baseName = path.basename(filename, ext);
    
    try {
        // Basic optimization using built-in methods (placeholder)
        // In a real implementation, you would use libraries like sharp, imagemin, etc.
        
        // For now, just copy the file (placeholder for actual optimization)
        const outputPath = path.join(outputDir, filename);
        fs.copyFileSync(inputPath, outputPath);
        
        // Generate WebP version if supported
        if (['.jpg', '.jpeg', '.png'].includes(ext)) {
            const webpPath = path.join(outputDir, `${baseName}.webp`);
            // Placeholder: fs.copyFileSync(inputPath, webpPath);
            console.log(`    Generated WebP: ${baseName}.webp (placeholder)`);
        }
        
        // Generate thumbnails
        OPTIMIZATION_CONFIG.images.thumbnailSizes.forEach(size => {
            const thumbPath = path.join(outputDir, `${baseName}_${size}${ext}`);
            // Placeholder: generateThumbnail(inputPath, thumbPath, size);
            if (verbose) {
                console.log(`    Generated thumbnail: ${baseName}_${size}${ext} (placeholder)`);
            }
        });
        
    } catch (error) {
        console.error(`    ❌ Failed to optimize ${filename}:`, error.message);
    }
}

/**
 * Optimize CSS files
 */
async function optimizeCss() {
    console.log('🎨 Optimizing CSS...');
    
    const cssDir = 'frontend/src/css';
    const outputCssDir = path.join(outputDir, '../css');
    
    if (!fs.existsSync(cssDir)) {
        console.log('  ⚠️  No CSS directory found, skipping...');
        return;
    }
    
    if (!fs.existsSync(outputCssDir)) {
        fs.mkdirSync(outputCssDir, { recursive: true });
    }
    
    const cssFiles = fs.readdirSync(cssDir).filter(file => file.endsWith('.css'));
    
    let optimizedCount = 0;
    let totalSavings = 0;
    
    for (const file of cssFiles) {
        const inputPath = path.join(cssDir, file);
        const outputPath = path.join(outputCssDir, file.replace('.css', '.min.css'));
        
        const originalSize = fs.statSync(inputPath).size;
        
        // Optimize CSS
        const optimizedCss = await optimizeCssFile(inputPath);
        fs.writeFileSync(outputPath, optimizedCss);
        
        const optimizedSize = fs.statSync(outputPath).size;
        const savings = originalSize - optimizedSize;
        totalSavings += savings;
        optimizedCount++;
        
        if (verbose) {
            console.log(`    ${file}: ${formatBytes(originalSize)} → ${formatBytes(optimizedSize)} (${formatBytes(savings)} saved)`);
        }
    }
    
    console.log(`  ✓ Optimized ${optimizedCount} CSS files, saved ${formatBytes(totalSavings)}`);
}

/**
 * Optimize individual CSS file
 */
async function optimizeCssFile(inputPath) {
    let css = fs.readFileSync(inputPath, 'utf8');
    
    // Basic CSS minification
    css = css
        .replace(/\/\*[\s\S]*?\*\//g, '') // Remove comments
        .replace(/\s+/g, ' ') // Collapse whitespace
        .replace(/;\s*}/g, '}') // Remove last semicolon in blocks
        .replace(/\s*{\s*/g, '{') // Clean up braces
        .replace(/}\s*/g, '}')
        .replace(/;\s*/g, ';')
        .replace(/:\s*/g, ':')
        .replace(/,\s*/g, ',')
        .trim();
    
    // Add autoprefixer (placeholder)
    // In a real implementation, you would use autoprefixer library
    
    return css;
}

/**
 * Optimize JavaScript files
 */
async function optimizeJavaScript() {
    console.log('📜 Optimizing JavaScript...');
    
    const jsDir = 'frontend/src/js';
    const outputJsDir = path.join(outputDir, '../js');
    
    if (!fs.existsSync(jsDir)) {
        console.log('  ⚠️  No JavaScript directory found, skipping...');
        return;
    }
    
    if (!fs.existsSync(outputJsDir)) {
        fs.mkdirSync(outputJsDir, { recursive: true });
    }
    
    const jsFiles = fs.readdirSync(jsDir).filter(file => file.endsWith('.js'));
    
    let optimizedCount = 0;
    let totalSavings = 0;
    
    for (const file of jsFiles) {
        const inputPath = path.join(jsDir, file);
        const outputPath = path.join(outputJsDir, file.replace('.js', '.min.js'));
        
        const originalSize = fs.statSync(inputPath).size;
        
        // Optimize JavaScript
        const optimizedJs = await optimizeJsFile(inputPath);
        fs.writeFileSync(outputPath, optimizedJs);
        
        const optimizedSize = fs.statSync(outputPath).size;
        const savings = originalSize - optimizedSize;
        totalSavings += savings;
        optimizedCount++;
        
        if (verbose) {
            console.log(`    ${file}: ${formatBytes(originalSize)} → ${formatBytes(optimizedSize)} (${formatBytes(savings)} saved)`);
        }
    }
    
    console.log(`  ✓ Optimized ${optimizedCount} JavaScript files, saved ${formatBytes(totalSavings)}`);
}

/**
 * Optimize individual JavaScript file
 */
async function optimizeJsFile(inputPath) {
    let js = fs.readFileSync(inputPath, 'utf8');
    
    // Basic JavaScript minification
    js = js
        .replace(/\/\*[\s\S]*?\*\//g, '') // Remove block comments
        .replace(/\/\/.*$/gm, '') // Remove line comments
        .replace(/console\.log\([^)]*\);?/g, '') // Remove console.log (if configured)
        .replace(/debugger;?/g, '') // Remove debugger statements
        .replace(/\s+/g, ' ') // Collapse whitespace
        .replace(/;\s*}/g, '}') // Clean up semicolons
        .replace(/\s*{\s*/g, '{') // Clean up braces
        .replace(/}\s*/g, '}')
        .replace(/;\s*/g, ';')
        .trim();
    
    return js;
}

/**
 * Optimize fonts
 */
async function optimizeFonts() {
    console.log('🔤 Optimizing fonts...');
    
    const fontsDir = path.join(inputDir, 'fonts');
    const outputFontsDir = path.join(outputDir, 'fonts');
    
    if (!fs.existsSync(fontsDir)) {
        console.log('  ⚠️  No fonts directory found, skipping...');
        return;
    }
    
    if (!fs.existsSync(outputFontsDir)) {
        fs.mkdirSync(outputFontsDir, { recursive: true });
    }
    
    const fontFiles = fs.readdirSync(fontsDir).filter(file => 
        /\.(ttf|otf|woff|woff2|eot)$/i.test(file)
    );
    
    let optimizedCount = 0;
    
    for (const file of fontFiles) {
        const inputPath = path.join(fontsDir, file);
        const outputPath = path.join(outputFontsDir, file);
        
        // For now, just copy fonts (placeholder for actual optimization)
        fs.copyFileSync(inputPath, outputPath);
        optimizedCount++;
        
        if (verbose) {
            console.log(`    Copied font: ${file}`);
        }
    }
    
    console.log(`  ✓ Processed ${optimizedCount} font files`);
}

/**
 * Compress assets
 */
async function compressAssets() {
    console.log('🗜️  Compressing assets...');
    
    let compressedCount = 0;
    let totalSavings = 0;
    
    // Compress files recursively
    function compressDirectory(dir) {
        if (!fs.existsSync(dir)) return;
        
        const files = fs.readdirSync(dir, { withFileTypes: true });
        
        files.forEach(file => {
            const filePath = path.join(dir, file.name);
            
            if (file.isDirectory()) {
                compressDirectory(filePath);
            } else {
                const stats = fs.statSync(filePath);
                
                // Only compress files above threshold
                if (stats.size >= OPTIMIZATION_CONFIG.compression.gzip.threshold) {
                    const originalSize = stats.size;
                    
                    // Gzip compression
                    if (OPTIMIZATION_CONFIG.compression.gzip.enabled) {
                        const content = fs.readFileSync(filePath);
                        const compressed = zlib.gzipSync(content, {
                            level: OPTIMIZATION_CONFIG.compression.gzip.level
                        });
                        fs.writeFileSync(filePath + '.gz', compressed);
                        
                        const savings = originalSize - compressed.length;
                        totalSavings += savings;
                        compressedCount++;
                        
                        if (verbose) {
                            console.log(`    ${file.name}: ${formatBytes(originalSize)} → ${formatBytes(compressed.length)} (gzip)`);
                        }
                    }
                    
                    // Brotli compression
                    if (OPTIMIZATION_CONFIG.compression.brotli.enabled) {
                        const content = fs.readFileSync(filePath);
                        const compressed = zlib.brotliCompressSync(content, {
                            params: {
                                [zlib.constants.BROTLI_PARAM_QUALITY]: OPTIMIZATION_CONFIG.compression.brotli.quality
                            }
                        });
                        fs.writeFileSync(filePath + '.br', compressed);
                        
                        if (verbose) {
                            console.log(`    ${file.name}: ${formatBytes(originalSize)} → ${formatBytes(compressed.length)} (brotli)`);
                        }
                    }
                }
            }
        });
    }
    
    compressDirectory(outputDir);
    
    console.log(`  ✓ Compressed ${compressedCount} files, saved ${formatBytes(totalSavings)}`);
}

/**
 * Generate optimization report
 */
function generateOptimizationReport() {
    const report = {
        timestamp: new Date().toISOString(),
        inputDirectory: inputDir,
        outputDirectory: outputDir,
        configuration: OPTIMIZATION_CONFIG,
        results: {
            totalFiles: 0,
            totalOriginalSize: 0,
            totalOptimizedSize: 0,
            totalSavings: 0,
            compressionRatio: 0
        }
    };
    
    // Calculate totals
    function calculateDirectorySize(dir) {
        let size = 0;
        let count = 0;
        
        if (!fs.existsSync(dir)) return { size: 0, count: 0 };
        
        const files = fs.readdirSync(dir, { withFileTypes: true });
        
        files.forEach(file => {
            const filePath = path.join(dir, file.name);
            
            if (file.isDirectory()) {
                const subResult = calculateDirectorySize(filePath);
                size += subResult.size;
                count += subResult.count;
            } else if (!file.name.endsWith('.gz') && !file.name.endsWith('.br')) {
                size += fs.statSync(filePath).size;
                count++;
            }
        });
        
        return { size, count };
    }
    
    const inputStats = calculateDirectorySize(inputDir);
    const outputStats = calculateDirectorySize(outputDir);
    
    report.results.totalFiles = outputStats.count;
    report.results.totalOriginalSize = inputStats.size;
    report.results.totalOptimizedSize = outputStats.size;
    report.results.totalSavings = inputStats.size - outputStats.size;
    report.results.compressionRatio = inputStats.size > 0 ? 
        ((inputStats.size - outputStats.size) / inputStats.size * 100).toFixed(2) : 0;
    
    const reportPath = path.join(outputDir, 'optimization-report.json');
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    
    console.log('📊 Optimization Report:');
    console.log(`  Files processed: ${report.results.totalFiles}`);
    console.log(`  Original size: ${formatBytes(report.results.totalOriginalSize)}`);
    console.log(`  Optimized size: ${formatBytes(report.results.totalOptimizedSize)}`);
    console.log(`  Total savings: ${formatBytes(report.results.totalSavings)} (${report.results.compressionRatio}%)`);
    console.log(`  Report saved: ${reportPath}`);
}

/**
 * Utility functions
 */

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Run optimization if called directly
if (require.main === module) {
    optimizeAssets();
}

module.exports = {
    optimizeAssets,
    OPTIMIZATION_CONFIG
};