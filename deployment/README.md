# Riya Collections - Deployment Guide

This directory contains all deployment scripts, configuration templates, and documentation for deploying the Riya Collections integrated application to various hosting environments.

## Project Structure

The project follows a clean, unified structure:

```
project/
├── public/                             # Web root (ONLY entry point)
│   ├── index.php                      # Unified entry point
│   ├── .htaccess                      # Apache configuration
│   ├── index.html                     # Frontend main HTML
│   ├── assets/                        # Static assets (CSS, JS, images)
│   └── uploads/                       # User uploaded files
├── app/                               # Application logic (SINGLE source)
│   ├── controllers/                   # PHP controllers
│   ├── models/                        # Data models
│   ├── services/                      # Business logic services
│   ├── middleware/                    # Request middleware
│   ├── config/                        # Configuration files
│   └── utils/                         # Utility classes
├── tests/                             # All tests (consolidated)
├── docs/                              # Documentation (single location)
├── deployment/                        # Deployment scripts and guides
├── storage/                           # Storage directory (logs, cache, backups)
├── database/                          # Database migrations
├── composer.json                      # Single dependency file
└── .env.example                       # Single environment template
```

## Deployment Directory Structure

```
deployment/
├── README.md                           # This file
├── infinityfree/                       # InfinityFree specific deployment
│   ├── deploy.md                       # InfinityFree deployment guide
│   ├── .htaccess.production           # Production .htaccess
│   └── .env.infinityfree              # InfinityFree environment template
├── shared/                             # Shared deployment resources
│   ├── database_migration.php         # Database migration script
│   ├── deployment_checklist.md        # Pre/post deployment checklist
│   ├── environment_validator.php      # Environment validation script
│   └── backup_before_deploy.php       # Pre-deployment backup
├── templates/                          # Configuration templates
│   ├── .env.production                # Production environment template
│   └── .env.staging                   # Staging environment template
└── scripts/                           # Deployment automation scripts
    ├── deploy.sh                      # Main deployment script
    ├── rollback.sh                    # Rollback script
    ├── health_check.php               # Post-deployment health check
    └── verify_deployment.php          # Comprehensive deployment verification
```

## Quick Start

1. **Choose your hosting environment:**
   - [InfinityFree Deployment](infinityfree/deploy.md) - For InfinityFree hosting
   - [Generic PHP Hosting](shared/deployment_checklist.md) - For other PHP hosts

2. **Prepare your environment:**
   - Copy `.env.example` to `.env` and configure
   - Set database credentials and other settings
   - Ensure web server points to `public/` directory as document root

3. **Deploy the application:**
   - Upload entire project structure to server
   - Set web server document root to `public/` directory
   - Run database migrations from `database/migrations/`
   - Follow the specific guide for your hosting provider

## Key Deployment Notes

- **Web Root**: Always set `public/` as your web server's document root
- **Single Entry Point**: All requests go through `public/index.php`
- **Unified Structure**: No duplicate files - everything is in its proper place
- **Environment Config**: Use `.env` file for all configuration (copy from `.env.example`)

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher  
- Apache with mod_rewrite enabled (or Nginx with proper configuration)
- SSL certificate (recommended for production)
- File upload permissions
- URL rewriting support (.htaccess)

## Support

For deployment issues, check the troubleshooting sections in the specific deployment guides or contact support.