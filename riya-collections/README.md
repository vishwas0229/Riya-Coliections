# Riya Collections

Modern e-commerce platform for cosmetics and beauty products.

## Tech Stack
- **Backend:** Node.js, Express.js, MySQL
- **Frontend:** HTML5, CSS3, JavaScript
- **Authentication:** JWT + bcrypt
- **Payments:** Razorpay
- **Email:** Nodemailer

## Quick Start

### Prerequisites
- Node.js (v16+)
- MySQL (v8.0+)

### Installation
```bash
# Install all dependencies
npm run install:all

# Setup environment
cp backend/.env.example backend/.env
# Update database credentials in backend/.env

# Start development servers
npm run dev
```

### Access
- Frontend: http://localhost:3000
- Backend API: http://localhost:5000

## Production Deployment
```bash
# Build and deploy
npm run build
npm run deploy
```

## License
MIT