/**
 * Setup Product Images Script
 * 
 * This script combines image optimization and product population
 * to set up the complete product image system.
 */

const { optimizeProductImages } = require('./optimize-product-images');
const { populateSampleProducts } = require('./populate-sample-products');

async function setupProductImages() {
  try {
    console.log('🚀 Setting up product images system...');
    
    // Step 1: Optimize images
    console.log('\n📸 Step 1: Optimizing product images...');
    await optimizeProductImages();
    
    // Step 2: Populate database with products
    console.log('\n🛍️ Step 2: Populating sample products...');
    await populateSampleProducts();
    
    console.log('\n✅ Product images setup completed successfully!');
    console.log('🎉 Your e-commerce platform is ready with optimized images and sample products.');
    
  } catch (error) {
    console.error('❌ Setup failed:', error);
    process.exit(1);
  }
}

// Run if called directly
if (require.main === module) {
  setupProductImages();
}

module.exports = { setupProductImages };