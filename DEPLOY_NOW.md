# Deploy Riya Collections - Quick Start Guide

## Choose Your Deployment Method

### Option 1: Local Development (Fastest - Start Now!)
```bash
# 1. Configure environment
cp .env.example .env

# 2. Edit .env with your database credentials
nano .env  # or use any text editor

# 3. Start development server
cd public && php -S localhost:8000
```
**Access at:** http://localhost:8000

---

### Option 2: InfinityFree Hosting (Free)
**Best for:** Testing, small projects, learning

**Steps:**
1. Create account at https://infinityfree.net
2. Create MySQL database in control panel
3. Upload files via FTP or File Manager
4. Configure .env with database credentials
5. Import database migrations

**Detailed Guide:** [deployment/infinityfree/deploy.md](deployment/infinityfree/deploy.md)

---

### Option 3: Production Server (VPS/Shared Hosting)
**Best for:** Production applications

**Quick Deploy:**
```bash
# Automated deployment
./deployment/scripts/deploy.sh production --backup --test
```

**Manual Deploy:**
1. Upload files to server
2. Point document root to `public/` directory
3. Configure .env file
4. Import database migrations
5. Set file permissions

**Detailed Guide:** [deployment/DEPLOYMENT_GUIDE.md](deployment/DEPLOYMENT_GUIDE.md)

---

## Pre-Deployment Checklist

- [ ] PHP 7.4+ installed
- [ ] MySQL 5.7+ available
- [ ] Composer dependencies installed (`composer install`)
- [ ] Database created
- [ ] .env file configured
- [ ] Web server configured (Apache/Nginx)

---

## Quick Configuration

### 1. Environment File (.env)
```bash
# Copy template
cp .env.example .env

# Edit with your settings
nano .env
```

**Required Settings:**
```env
# Database
DB_HOST=localhost
DB_NAME=riya_collections
DB_USER=your_user
DB_PASSWORD=your_password

# Security (CHANGE THESE!)
JWT_SECRET=your-super-secret-key-minimum-32-characters
```

### 2. Database Setup
```sql
-- Create database
CREATE DATABASE riya_collections;

-- Import migrations (in order)
-- 1. database/migrations/001_create_auth_tables.sql
-- 2. database/migrations/002_create_payments_table.sql
-- 3. database/migrations/003_create_emails_table.sql
-- 4. database/migrations/004_create_admin_tables.sql
```

### 3. File Permissions
```bash
chmod -R 755 storage/
chmod -R 755 public/uploads/
chmod 600 .env
```

---

## Test Your Deployment

### 1. Health Check
```bash
curl http://localhost:8000/api/health
```

### 2. Frontend
Visit: http://localhost:8000

### 3. API Test
```bash
# Test products endpoint
curl http://localhost:8000/api/products
```

---

## Need Help?

**Documentation:**
- [README.md](README.md) - Project overview
- [STRUCTURE.md](STRUCTURE.md) - Directory structure
- [deployment/README.md](deployment/README.md) - Deployment overview
- [deployment/DEPLOYMENT_GUIDE.md](deployment/DEPLOYMENT_GUIDE.md) - Complete guide

**Troubleshooting:**
- Check logs in `storage/logs/`
- Verify .env configuration
- Check file permissions
- Review deployment guides

---

## What Would You Like to Do?

1. **Start Local Development** → Run commands in "Option 1" above
2. **Deploy to InfinityFree** → Follow [infinityfree/deploy.md](deployment/infinityfree/deploy.md)
3. **Deploy to Production** → Run `./deployment/scripts/deploy.sh production`
4. **Need Custom Setup** → Check [deployment/DEPLOYMENT_GUIDE.md](deployment/DEPLOYMENT_GUIDE.md)

---

**Ready to deploy? Let me know which option you'd like and I'll guide you through it!**
