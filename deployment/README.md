# Riya Collections PHP Backend - Deployment Guide

This directory contains all deployment scripts, configuration templates, and documentation for deploying the Riya Collections PHP backend to various hosting environments.

## Directory Structure

```
deployment/
├── README.md                           # This file
├── infinityfree/                       # InfinityFree specific deployment
│   ├── deploy.md                       # InfinityFree deployment guide
│   ├── .htaccess.production           # Production .htaccess
│   ├── .env.infinityfree              # InfinityFree environment template
│   └── upload_script.php              # File upload helper
├── shared/                             # Shared deployment resources
│   ├── database_migration.php         # Database migration script
│   ├── deployment_checklist.md        # Pre/post deployment checklist
│   ├── environment_validator.php      # Environment validation script
│   └── backup_before_deploy.php       # Pre-deployment backup
├── templates/                          # Configuration templates
│   ├── .env.production                # Production environment template
│   ├── .env.staging                   # Staging environment template
│   └── .htaccess.template             # .htaccess template
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
   - Copy appropriate `.env` template
   - Configure database credentials
   - Set up domain and SSL

3. **Run deployment:**
   - Follow the specific guide for your hosting provider
   - Use the deployment checklist to verify everything works

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- SSL certificate (recommended)
- File upload permissions
- URL rewriting support (.htaccess)

## Support

For deployment issues, check the troubleshooting sections in the specific deployment guides or contact support.