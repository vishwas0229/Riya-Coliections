#!/usr/bin/env node

/**
 * Create Admin User Script
 * Creates an admin user for testing the admin panel
 */

const bcrypt = require('bcrypt');
const { executeQuery } = require('../config/database');

async function createAdmin() {
  try {
    console.log('Creating admin user...');

    // Admin credentials
    const adminData = {
      email: 'admin@riyacollections.com',
      password: 'Admin@123',
      name: 'Admin User',
      role: 'admin'
    };

    // Check if admin already exists
    const existingAdmin = await executeQuery(
      'SELECT id FROM admins WHERE email = ?',
      [adminData.email]
    );

    if (existingAdmin.length > 0) {
      console.log('Admin user already exists with email:', adminData.email);
      return;
    }

    // Hash password
    const saltRounds = 12;
    const passwordHash = await bcrypt.hash(adminData.password, saltRounds);

    // Insert admin user
    const result = await executeQuery(
      `INSERT INTO admins (email, password_hash, name, role) 
       VALUES (?, ?, ?, ?)`,
      [adminData.email, passwordHash, adminData.name, adminData.role]
    );

    console.log('Admin user created successfully!');
    console.log('Email:', adminData.email);
    console.log('Password:', adminData.password);
    console.log('Role:', adminData.role);
    console.log('Admin ID:', result.insertId);

  } catch (error) {
    console.error('Error creating admin user:', error);
    process.exit(1);
  }
}

// Run the script
if (require.main === module) {
  createAdmin().then(() => {
    console.log('Script completed successfully');
    process.exit(0);
  }).catch(error => {
    console.error('Script failed:', error);
    process.exit(1);
  });
}

module.exports = createAdmin;