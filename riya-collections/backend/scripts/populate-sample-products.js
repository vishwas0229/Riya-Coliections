/**
 * Sample Product Data Population Script
 * 
 * This script populates the database with sample cosmetic products
 * and their corresponding images for development and testing purposes.
 * 
 * Requirements: 21.1 - Add sample product images to match existing product data
 */

const path = require('path');
const fs = require('fs').promises;
const { secureExecuteQuery, secureExecuteTransaction } = require('../middleware/database-security');

// Sample product data matching the existing images in frontend/assets/Products
const SAMPLE_PRODUCTS = [
  // Face Makeup Category
  {
    name: 'Sugar Pop Liquid Foundation',
    description: 'Long-lasting liquid foundation with full coverage and natural finish. Perfect for all-day wear with SPF protection.',
    price: 899.00,
    stock_quantity: 25,
    category_name: 'Face Makeup',
    brand: 'Sugar Cosmetics',
    sku: 'SUGAR-LF-001',
    images: ['Sugar_Pop_Liquid_Foundation.png']
  },
  {
    name: 'Sugar Pop Mousse Foundation',
    description: 'Lightweight mousse foundation that blends seamlessly for a flawless, airbrushed finish.',
    price: 1099.00,
    stock_quantity: 20,
    category_name: 'Face Makeup',
    brand: 'Sugar Cosmetics',
    sku: 'SUGAR-MF-001',
    images: ['Sugar_Pop_Mousse_Foundation.png']
  },
  {
    name: 'Sugar Highlighter Plate',
    description: 'Multi-shade highlighter palette for a radiant, luminous glow. Perfect for contouring and highlighting.',
    price: 1299.00,
    stock_quantity: 15,
    category_name: 'Face Makeup',
    brand: 'Sugar Cosmetics',
    sku: 'SUGAR-HL-001',
    images: ['Sugar_Highlighter_Plate.png']
  },
  {
    name: 'Z&M Liquid Highlighter',
    description: 'Liquid highlighter for an instant glow. Easy to blend and long-lasting formula.',
    price: 599.00,
    stock_quantity: 30,
    category_name: 'Face Makeup',
    brand: 'Z&M',
    sku: 'ZM-LH-001',
    images: ['Z&M_Liquid_Highlighter.png']
  },

  // Lip Care Category
  {
    name: 'Sugar Liquid Lipstick',
    description: 'Matte liquid lipstick with intense color payoff and long-lasting formula. Transfer-proof and comfortable wear.',
    price: 699.00,
    stock_quantity: 40,
    category_name: 'Lip Care',
    brand: 'Sugar Cosmetics',
    sku: 'SUGAR-LL-001',
    images: ['Sugar_Liquid_Lipstick.png']
  },
  {
    name: 'Sugar Pop Lipstick',
    description: 'Creamy lipstick with rich pigmentation and moisturizing formula. Available in vibrant shades.',
    price: 599.00,
    stock_quantity: 35,
    category_name: 'Lip Care',
    brand: 'Sugar Cosmetics',
    sku: 'SUGAR-PL-001',
    images: ['Sugar_Pop_Lipstick.png']
  },
  {
    name: 'Sugar Lip Cream',
    description: 'Nourishing lip cream with natural ingredients. Provides hydration and subtle color.',
    price: 399.00,
    stock_quantity: 50,
    category_name: 'Lip Care',
    brand: 'Sugar Cosmetics',
    sku: 'SUGAR-LC-001',
    images: ['Sugar_Lip_Cream.png']
  },
  {
    name: 'Renee Crystal Lip Gloss',
    description: 'High-shine lip gloss with crystal-clear finish. Non-sticky formula with long-lasting wear.',
    price: 449.00,
    stock_quantity: 45,
    category_name: 'Lip Care',
    brand: 'Renee',
    sku: 'RENEE-CLG-001',
    images: ['Renee_Crystal_Lip_Gloss.png']
  },
  {
    name: 'Renee Fab Face Lip 3ml',
    description: 'Compact lip color with intense pigmentation. Perfect for on-the-go touch-ups.',
    price: 299.00,
    stock_quantity: 60,
    category_name: 'Lip Care',
    brand: 'Renee',
    sku: 'RENEE-FFL-001',
    images: ['Renee_Fab_Face_Lip_3ml.png']
  },
  {
    name: 'Naturally Kissable Lip Kit',
    description: 'Complete lip care kit with natural ingredients. Includes lip balm, scrub, and color.',
    price: 899.00,
    stock_quantity: 25,
    category_name: 'Lip Care',
    brand: 'Natural Beauty',
    sku: 'NB-LK-001',
    images: ['Naturally_Kissable_Lip_Kit.png']
  },
  {
    name: 'Premium Lip Care Set',
    description: 'Luxury lip care collection with multiple shades and finishes.',
    price: 1299.00,
    stock_quantity: 20,
    category_name: 'Lip Care',
    brand: 'Premium Beauty',
    sku: 'PB-LCS-001',
    images: ['Lip_Care.png']
  },
  {
    name: 'Lip Care Essential',
    description: 'Essential lip care product for daily use. Moisturizing and protective formula.',
    price: 199.00,
    stock_quantity: 80,
    category_name: 'Lip Care',
    brand: 'Essential Care',
    sku: 'EC-LCE-001',
    images: ['Lip_Care-1.png']
  },
  {
    name: 'Lip Care Liquid Treatment',
    description: 'Liquid lip treatment for intensive care and repair. Suitable for all lip types.',
    price: 349.00,
    stock_quantity: 55,
    category_name: 'Lip Care',
    brand: 'Liquid Care',
    sku: 'LC-LLT-001',
    images: ['Lip_Care_liquid-2.png']
  },

  // Hair Care Category
  {
    name: 'WOW Onion Shampoo',
    description: 'Onion shampoo for hair growth and strengthening. Reduces hair fall and promotes healthy hair.',
    price: 599.00,
    stock_quantity: 40,
    category_name: 'Hair Care',
    brand: 'WOW',
    sku: 'WOW-OS-001',
    images: ['WOW_Onion_Shampoo.png']
  },
  {
    name: 'WOW Apple Cider Shampoo',
    description: 'Apple cider vinegar shampoo for deep cleansing and pH balance. Adds natural shine.',
    price: 649.00,
    stock_quantity: 35,
    category_name: 'Hair Care',
    brand: 'WOW',
    sku: 'WOW-ACS-001',
    images: ['WOW_Apple_Cider_Shampoo.png']
  },
  {
    name: 'WOW Apple Cider Vinegar',
    description: 'Pure apple cider vinegar for hair and scalp treatment. Natural and organic formula.',
    price: 399.00,
    stock_quantity: 50,
    category_name: 'Hair Care',
    brand: 'WOW',
    sku: 'WOW-ACV-001',
    images: ['WOW_Apple_Cider_Vinegar.png']
  },
  {
    name: 'WOW Argan Hair Oil',
    description: 'Pure argan oil for hair nourishment and repair. Adds shine and reduces frizz.',
    price: 799.00,
    stock_quantity: 30,
    category_name: 'Hair Care',
    brand: 'WOW',
    sku: 'WOW-AHO-001',
    images: ['WOW_Argan_Hair_Oil.png']
  },
  {
    name: 'VLCC Hairfall Control Shampoo',
    description: 'Specialized shampoo for hair fall control with natural extracts and vitamins.',
    price: 299.00,
    stock_quantity: 60,
    category_name: 'Hair Care',
    brand: 'VLCC',
    sku: 'VLCC-HCS-001',
    images: ['VLCC_Hairfall_Control_Shampoo.png']
  },
  {
    name: 'Park Avenue Beer Shampoo',
    description: 'Beer-based shampoo for volume and strength. Unique formula for healthy hair.',
    price: 199.00,
    stock_quantity: 70,
    category_name: 'Hair Care',
    brand: 'Park Avenue',
    sku: 'PA-BS-001',
    images: ['Park_Avenue_Beer_Shampoo.png']
  },
  {
    name: 'Nykaa Hair Mask Apple Cider',
    description: 'Deep conditioning hair mask with apple cider vinegar. Repairs and strengthens hair.',
    price: 549.00,
    stock_quantity: 25,
    category_name: 'Hair Care',
    brand: 'Nykaa',
    sku: 'NYKAA-HMAC-001',
    images: ['Nykaa_Hair_Mask_Apple_Cider.png']
  },
  {
    name: 'Mamaearth Argan Hair Oil',
    description: 'Natural argan hair oil for deep nourishment. Chemical-free and toxin-free formula.',
    price: 699.00,
    stock_quantity: 35,
    category_name: 'Hair Care',
    brand: 'Mamaearth',
    sku: 'ME-AHO-001',
    images: ['Mamaearth_Argan_Hair_Oil.png']
  },
  {
    name: 'Livon Hair Serum',
    description: 'Hair serum for smooth and silky hair. Reduces frizz and adds shine instantly.',
    price: 199.00,
    stock_quantity: 80,
    category_name: 'Hair Care',
    brand: 'Livon',
    sku: 'LIVON-HS-001',
    images: ['Livon_Hair_Serum.png']
  },
  {
    name: 'LAFZ 10 in 1 Hair Oil',
    description: 'Multi-benefit hair oil with 10 natural ingredients. Complete hair care solution.',
    price: 899.00,
    stock_quantity: 20,
    category_name: 'Hair Care',
    brand: 'LAFZ',
    sku: 'LAFZ-10HO-001',
    images: ['LAFZ_10_in_1_Hair_Oil.png']
  },
  {
    name: 'Dove Shaping Cream',
    description: 'Hair shaping cream for styling and hold. Provides texture and definition.',
    price: 349.00,
    stock_quantity: 45,
    category_name: 'Hair Care',
    brand: 'Dove',
    sku: 'DOVE-SC-001',
    images: ['Dove_Shaping_Cream.png']
  },
  {
    name: 'Ayouthveda Hair Oil',
    description: 'Ayurvedic hair oil with traditional herbs. Promotes hair growth and scalp health.',
    price: 599.00,
    stock_quantity: 30,
    category_name: 'Hair Care',
    brand: 'Ayouthveda',
    sku: 'AYU-HO-001',
    images: ['Ayouthveda_Hair_Oil.png']
  },

  // Skin Care Category
  {
    name: 'VLCC Sunscreen Lotion',
    description: 'Broad spectrum sunscreen with SPF 30. Protects against UVA and UVB rays.',
    price: 299.00,
    stock_quantity: 50,
    category_name: 'Skin Care',
    brand: 'VLCC',
    sku: 'VLCC-SL-001',
    images: ['VLCC_Sunscreen_Lotion.png', 'VLCC_Sunscreen_Lotion(1).png']
  },
  {
    name: 'VLCC Honey Moisturiser',
    description: 'Natural honey-based moisturizer for soft and supple skin. Suitable for all skin types.',
    price: 249.00,
    stock_quantity: 60,
    category_name: 'Skin Care',
    brand: 'VLCC',
    sku: 'VLCC-HM-001',
    images: ['VLCC_Honey_Moisturiser.png']
  },
  {
    name: 'VLCC Sandal Cleansing Milk',
    description: 'Gentle cleansing milk with sandalwood extracts. Removes makeup and impurities.',
    price: 199.00,
    stock_quantity: 70,
    category_name: 'Skin Care',
    brand: 'VLCC',
    sku: 'VLCC-SCM-001',
    images: ['VLCC_Sandal_Cleansing_Milk.png']
  },
  {
    name: 'VLCC Anti Tan Face Wash',
    description: 'Anti-tan face wash with natural ingredients. Removes tan and brightens skin.',
    price: 179.00,
    stock_quantity: 80,
    category_name: 'Skin Care',
    brand: 'VLCC',
    sku: 'VLCC-ATFW-001',
    images: ['VLCC_Anti_Tan_Face_Wash.png']
  },
  {
    name: 'Z&M Vitamin C Face Wash',
    description: 'Vitamin C enriched face wash for glowing skin. Antioxidant protection and brightening.',
    price: 299.00,
    stock_quantity: 55,
    category_name: 'Skin Care',
    brand: 'Z&M',
    sku: 'ZM-VCFW-001',
    images: ['Z&M_Vitamin_C_Face_Wash.png']
  },
  {
    name: 'St Ives Tea Tree Face Wash',
    description: 'Tea tree oil face wash for acne-prone skin. Deep cleansing and purifying formula.',
    price: 399.00,
    stock_quantity: 40,
    category_name: 'Skin Care',
    brand: 'St. Ives',
    sku: 'SI-TTFW-001',
    images: ['St_Ives_Tea_Tree_Face_Wash.png']
  },
  {
    name: 'Parachute Shower Gel',
    description: 'Moisturizing shower gel with coconut extracts. Gentle on skin and long-lasting fragrance.',
    price: 149.00,
    stock_quantity: 90,
    category_name: 'Skin Care',
    brand: 'Parachute',
    sku: 'PARA-SG-001',
    images: ['Parachute_Shower_Gel.png']
  },
  {
    name: 'LAFZ Apple Cider Face Wash',
    description: 'Apple cider vinegar face wash for deep cleansing and pH balance. Natural and gentle.',
    price: 249.00,
    stock_quantity: 65,
    category_name: 'Skin Care',
    brand: 'LAFZ',
    sku: 'LAFZ-ACFW-001',
    images: ['LAFZ_Apple_Cider_Face_Wash.png']
  },
  {
    name: 'Charmis Cold Cream',
    description: 'Classic cold cream for dry skin protection. Moisturizes and nourishes deeply.',
    price: 99.00,
    stock_quantity: 100,
    category_name: 'Skin Care',
    brand: 'Charmis',
    sku: 'CHARMIS-CC-001',
    images: ['Charmis_Cold_Cream.png']
  },
  {
    name: 'Ayouthveda Night Cream',
    description: 'Ayurvedic night cream for skin repair and rejuvenation. Anti-aging formula.',
    price: 799.00,
    stock_quantity: 25,
    category_name: 'Skin Care',
    brand: 'Ayouthveda',
    sku: 'AYU-NC-001',
    images: ['Ayouthveda_Night_Cream.png']
  },
  {
    name: 'Ayouthveda Massage Oil',
    description: 'Herbal massage oil for body relaxation and skin nourishment. Traditional Ayurvedic blend.',
    price: 649.00,
    stock_quantity: 30,
    category_name: 'Skin Care',
    brand: 'Ayouthveda',
    sku: 'AYU-MO-001',
    images: ['Ayouthveda_Massage_Oil.png']
  },
  {
    name: 'Ayouthveda Gold Face Wash',
    description: 'Luxury face wash with gold particles. Deep cleansing with anti-aging benefits.',
    price: 899.00,
    stock_quantity: 20,
    category_name: 'Skin Care',
    brand: 'Ayouthveda',
    sku: 'AYU-GFW-001',
    images: ['Ayouthveda_Gold_Face_Wash.png']
  },
  {
    name: 'Ayouthveda Day Cream',
    description: 'Daily moisturizing cream with SPF protection. Keeps skin hydrated all day.',
    price: 699.00,
    stock_quantity: 35,
    category_name: 'Skin Care',
    brand: 'Ayouthveda',
    sku: 'AYU-DC-001',
    images: ['Ayouthveda_Day_Cream.png']
  },
  {
    name: 'Ayouthveda Body Butter',
    description: 'Rich body butter for intense moisturization. Perfect for dry and rough skin.',
    price: 549.00,
    stock_quantity: 40,
    category_name: 'Skin Care',
    brand: 'Ayouthveda',
    sku: 'AYU-BB-001',
    images: ['Ayouthveda_Body_Butter.png']
  },

  // Deodorants (can be categorized under Personal Care or create new category)
  {
    name: 'Urban Gabru Deo',
    description: 'Long-lasting deodorant for men with masculine fragrance. 24-hour protection.',
    price: 199.00,
    stock_quantity: 75,
    category_name: 'Skin Care', // or Personal Care
    brand: 'Urban Gabru',
    sku: 'UG-DEO-001',
    images: ['Urban_Gabru_Deo.png']
  },
  {
    name: 'Park Avenue Deo',
    description: 'Premium deodorant with sophisticated fragrance. Long-lasting freshness.',
    price: 149.00,
    stock_quantity: 85,
    category_name: 'Skin Care',
    brand: 'Park Avenue',
    sku: 'PA-DEO-001',
    images: ['Park_Avenue_Deo.png']
  },
  {
    name: 'Fogg Deo',
    description: 'Popular deodorant with intense fragrance. No gas, only perfume formula.',
    price: 179.00,
    stock_quantity: 90,
    category_name: 'Skin Care',
    brand: 'Fogg',
    sku: 'FOGG-DEO-001',
    images: ['Fogg_Deo.png']
  },
  {
    name: 'Beverly Hills Deo',
    description: 'Luxury deodorant with premium fragrance. Elegant and long-lasting.',
    price: 299.00,
    stock_quantity: 50,
    category_name: 'Skin Care',
    brand: 'Beverly Hills',
    sku: 'BH-DEO-001',
    images: ['Beverly_Hills_Deo.png']
  }
];

// Categories mapping
const CATEGORIES = [
  { name: 'Face Makeup', description: 'Complete range of face makeup products including foundations, highlighters, and more' },
  { name: 'Lip Care', description: 'Lipsticks, lip glosses, lip balms and complete lip care solutions' },
  { name: 'Hair Care', description: 'Shampoos, conditioners, hair oils and styling products for healthy hair' },
  { name: 'Skin Care', description: 'Face washes, moisturizers, sunscreens and complete skincare routine products' }
];

/**
 * Main function to populate sample products
 */
async function populateSampleProducts() {
  try {
    console.log('🚀 Starting sample product population...');

    // First, create categories
    console.log('📁 Creating categories...');
    const categoryMap = await createCategories();

    // Then, create products with images
    console.log('🛍️ Creating products...');
    await createProducts(categoryMap);

    console.log('✅ Sample product population completed successfully!');
    console.log(`📊 Created ${CATEGORIES.length} categories and ${SAMPLE_PRODUCTS.length} products`);

  } catch (error) {
    console.error('❌ Error populating sample products:', error);
    throw error;
  }
}

/**
 * Create categories and return mapping
 */
async function createCategories() {
  const categoryMap = {};

  for (const category of CATEGORIES) {
    try {
      // Check if category already exists
      const existing = await secureExecuteQuery(
        'SELECT id FROM categories WHERE name = ?',
        [category.name]
      );

      let categoryId;
      if (existing.length > 0) {
        categoryId = existing[0].id;
        console.log(`📁 Category "${category.name}" already exists (ID: ${categoryId})`);
      } else {
        // Create new category
        const result = await secureExecuteQuery(
          'INSERT INTO categories (name, description, image_url, is_active) VALUES (?, ?, ?, ?)',
          [
            category.name,
            category.description,
            `/assets/categories/${category.name.replace(' ', '_')}.png`,
            true
          ]
        );
        categoryId = result.insertId;
        console.log(`📁 Created category "${category.name}" (ID: ${categoryId})`);
      }

      categoryMap[category.name] = categoryId;
    } catch (error) {
      console.error(`❌ Error creating category "${category.name}":`, error);
      throw error;
    }
  }

  return categoryMap;
}

/**
 * Create products with images
 */
async function createProducts(categoryMap) {
  for (const product of SAMPLE_PRODUCTS) {
    try {
      // Check if product already exists
      const existing = await secureExecuteQuery(
        'SELECT id FROM products WHERE sku = ?',
        [product.sku]
      );

      let productId;
      if (existing.length > 0) {
        productId = existing[0].id;
        console.log(`🛍️ Product "${product.name}" already exists (ID: ${productId})`);
        continue; // Skip if already exists
      }

      // Create product
      const productResult = await secureExecuteQuery(
        `INSERT INTO products (name, description, price, stock_quantity, category_id, brand, sku, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          product.name,
          product.description,
          product.price,
          product.stock_quantity,
          categoryMap[product.category_name],
          product.brand,
          product.sku,
          true
        ]
      );

      productId = productResult.insertId;
      console.log(`🛍️ Created product "${product.name}" (ID: ${productId})`);

      // Add product images
      for (let i = 0; i < product.images.length; i++) {
        const imageName = product.images[i];
        const isPrimary = i === 0; // First image is primary

        await secureExecuteQuery(
          `INSERT INTO product_images (product_id, image_url, alt_text, is_primary, sort_order)
           VALUES (?, ?, ?, ?, ?)`,
          [
            productId,
            `/uploads/products/${imageName}`,
            `${product.name} - Image ${i + 1}`,
            isPrimary,
            i
          ]
        );

        console.log(`🖼️ Added image "${imageName}" for product "${product.name}"`);
      }
    } catch (error) {
      console.error(`❌ Error creating product "${product.name}":`, error);
      throw error;
    }
  }
}

/**
 * Verify image files exist
 */
async function verifyImageFiles() {
  console.log('🔍 Verifying image files...');
  
  const frontendAssetsPath = path.join(__dirname, '../../frontend/assets/Products');
  const missingImages = [];

  for (const product of SAMPLE_PRODUCTS) {
    for (const imageName of product.images) {
      const imagePath = path.join(frontendAssetsPath, imageName);
      try {
        await fs.access(imagePath);
      } catch (error) {
        missingImages.push(imageName);
      }
    }
  }

  if (missingImages.length > 0) {
    console.warn('⚠️ Missing image files:');
    missingImages.forEach(img => console.warn(`   - ${img}`));
  } else {
    console.log('✅ All image files verified');
  }

  return missingImages;
}

/**
 * Clean up existing data (for development/testing)
 */
async function cleanupExistingData() {
  console.log('🧹 Cleaning up existing sample data...');
  
  try {
    // Delete in correct order due to foreign key constraints
    await secureExecuteQuery('DELETE FROM product_images WHERE 1=1');
    await secureExecuteQuery('DELETE FROM products WHERE sku LIKE "%-001"'); // Only sample products
    // Don't delete categories as they might be used by other products
    
    console.log('✅ Cleanup completed');
  } catch (error) {
    console.error('❌ Error during cleanup:', error);
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
        case 'verify':
          await verifyImageFiles();
          break;
        case 'cleanup':
          await cleanupExistingData();
          break;
        case 'populate':
        default:
          await verifyImageFiles();
          await populateSampleProducts();
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
  populateSampleProducts,
  createCategories,
  createProducts,
  verifyImageFiles,
  cleanupExistingData,
  SAMPLE_PRODUCTS,
  CATEGORIES
};