const { emailUtils, brandingConfig } = require('../config/email');

/**
 * Email Templates System
 * 
 * This module provides HTML and text email templates for various order events
 * and customer communications with professional branding and responsive design.
 * 
 * Requirements: 12.1, 12.2, 12.3, 12.4
 */

/**
 * Base HTML template with responsive design and branding
 */
function getBaseTemplate(content, title = 'Riya Collections') {
  return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${title}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: ${brandingConfig.colors.text};
            background-color: #f5f5f5;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: ${brandingConfig.colors.background};
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            background: linear-gradient(135deg, ${brandingConfig.colors.primary} 0%, ${brandingConfig.colors.secondary} 100%);
            padding: 30px 20px;
            text-align: center;
            color: white;
        }
        
        .logo {
            max-width: 150px;
            height: auto;
            margin-bottom: 10px;
        }
        
        .company-name {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .tagline {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: ${brandingConfig.colors.accent};
        }
        
        .order-info {
            background-color: #f8f9fa;
            border-left: 4px solid ${brandingConfig.colors.primary};
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .order-number {
            font-size: 20px;
            font-weight: bold;
            color: ${brandingConfig.colors.primary};
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-placed { background-color: #e3f2fd; color: #1976d2; }
        .status-processing { background-color: #fff3e0; color: #f57c00; }
        .status-shipped { background-color: #e8f5e8; color: #388e3c; }
        .status-delivered { background-color: #e8f5e8; color: #2e7d32; }
        .status-cancelled { background-color: #ffebee; color: #d32f2f; }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .items-table th {
            background-color: ${brandingConfig.colors.primary};
            color: white;
            padding: 15px 10px;
            text-align: left;
            font-weight: 600;
        }
        
        .items-table td {
            padding: 15px 10px;
            border-bottom: 1px solid #eee;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .total-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .total-row.final {
            font-size: 18px;
            font-weight: bold;
            color: ${brandingConfig.colors.primary};
            border-top: 2px solid ${brandingConfig.colors.primary};
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .button {
            display: inline-block;
            background: linear-gradient(135deg, ${brandingConfig.colors.primary} 0%, #c2185b 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 4px 8px rgba(233, 30, 99, 0.3);
            transition: all 0.3s ease;
        }
        
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(233, 30, 99, 0.4);
        }
        
        .address-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .address-title {
            font-weight: bold;
            color: ${brandingConfig.colors.accent};
            margin-bottom: 10px;
        }
        
        .footer {
            background-color: ${brandingConfig.colors.accent};
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .footer-links {
            margin-bottom: 20px;
        }
        
        .footer-links a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            font-size: 14px;
        }
        
        .social-links {
            margin: 20px 0;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: white;
            text-decoration: none;
        }
        
        .copyright {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 20px;
        }
        
        @media only screen and (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            
            .content {
                padding: 20px 15px;
            }
            
            .items-table {
                font-size: 14px;
            }
            
            .items-table th,
            .items-table td {
                padding: 10px 5px;
            }
            
            .button {
                display: block;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="company-name">${brandingConfig.companyName}</div>
            <div class="tagline">Premium Cosmetics & Beauty Products</div>
        </div>
        
        <div class="content">
            ${content}
        </div>
        
        <div class="footer">
            <div class="footer-links">
                <a href="${brandingConfig.websiteUrl}">Shop Now</a>
                <a href="${brandingConfig.websiteUrl}/track-order">Track Order</a>
                <a href="${brandingConfig.websiteUrl}/contact">Contact Us</a>
                <a href="${brandingConfig.websiteUrl}/returns">Returns</a>
            </div>
            
            <div class="social-links">
                <a href="#">📘 Facebook</a>
                <a href="#">📷 Instagram</a>
                <a href="#">🐦 Twitter</a>
            </div>
            
            <div class="copyright">
                © ${new Date().getFullYear()} ${brandingConfig.companyName}. All rights reserved.<br>
                This email was sent to you because you placed an order with us.<br>
                If you have any questions, contact us at ${brandingConfig.supportEmail}
            </div>
        </div>
    </div>
</body>
</html>`;
}

/**
 * Generate order items table HTML
 */
function generateItemsTable(items) {
  const itemsHtml = items.map(item => `
    <tr>
        <td>${emailUtils.sanitizeContent(item.product_name || item.name || 'Product')}</td>
        <td style="text-align: center;">${item.quantity || 0}</td>
        <td style="text-align: right;">${emailUtils.formatCurrency(item.unit_price || 0)}</td>
        <td style="text-align: right; font-weight: bold;">${emailUtils.formatCurrency(item.total_price || 0)}</td>
    </tr>
  `).join('');

  return `
    <table class="items-table">
        <thead>
            <tr>
                <th>Product</th>
                <th style="text-align: center;">Quantity</th>
                <th style="text-align: right;">Price</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            ${itemsHtml}
        </tbody>
    </table>
  `;
}

/**
 * Generate order totals section
 */
function generateTotalsSection(order) {
  const subtotal = order.subtotal || (order.total_amount - (order.shipping_amount || 0) - (order.tax_amount || 0) + (order.discount_amount || 0));
  
  return `
    <div class="total-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>${emailUtils.formatCurrency(subtotal)}</span>
        </div>
        ${(order.discount_amount || 0) > 0 ? `
        <div class="total-row">
            <span>Discount ${order.coupon_code ? `(${order.coupon_code})` : ''}:</span>
            <span style="color: #4caf50;">-${emailUtils.formatCurrency(order.discount_amount)}</span>
        </div>
        ` : ''}
        <div class="total-row">
            <span>Shipping:</span>
            <span>${(order.shipping_amount || 0) > 0 ? emailUtils.formatCurrency(order.shipping_amount) : 'FREE'}</span>
        </div>
        <div class="total-row">
            <span>Tax (GST):</span>
            <span>${emailUtils.formatCurrency(order.tax_amount || 0)}</span>
        </div>
        <div class="total-row final">
            <span>Total Amount:</span>
            <span>${emailUtils.formatCurrency(order.total_amount || 0)}</span>
        </div>
    </div>
  `;
}

/**
 * Generate shipping address section
 */
function generateAddressSection(address) {
  return `
    <div class="address-section">
        <div class="address-title">Shipping Address:</div>
        <div>
            ${emailUtils.sanitizeContent(address.first_name || '')} ${emailUtils.sanitizeContent(address.last_name || '')}<br>
            ${emailUtils.sanitizeContent(address.address_line1 || '')}<br>
            ${address.address_line2 ? emailUtils.sanitizeContent(address.address_line2) + '<br>' : ''}
            ${emailUtils.sanitizeContent(address.city || '')}, ${emailUtils.sanitizeContent(address.state || '')} ${emailUtils.sanitizeContent(address.postal_code || '')}<br>
            ${emailUtils.sanitizeContent(address.country || 'India')}<br>
            Phone: ${emailUtils.sanitizeContent(address.phone || '')}
        </div>
    </div>
  `;
}

/**
 * Order Confirmation Email Template
 * Requirements: 12.2
 */
function generateOrderConfirmationEmail(order, customer, items, address) {
  const trackingUrl = emailUtils.generateTrackingUrl(order.order_number);
  
  const content = `
    <div class="greeting">
        Hello ${emailUtils.sanitizeContent(customer.first_name)},
    </div>
    
    <p>Thank you for your order! We're excited to get your beautiful cosmetics ready for you.</p>
    
    <div class="order-info">
        <div class="order-number">Order #${order.order_number}</div>
        <div>Order Date: ${emailUtils.formatDate(order.created_at)}</div>
        <div>Payment Method: ${order.payment_method === 'cod' ? 'Cash on Delivery' : 'Online Payment'}</div>
        <div>Status: <span class="status-badge status-${order.status}">${order.status.replace('_', ' ')}</span></div>
    </div>
    
    <h3 style="color: ${brandingConfig.colors.primary}; margin: 30px 0 15px 0;">Order Details</h3>
    ${generateItemsTable(items)}
    
    ${generateTotalsSection(order)}
    
    ${generateAddressSection(address)}
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="${trackingUrl}" class="button">Track Your Order</a>
    </div>
    
    <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h4 style="color: #1976d2; margin-bottom: 10px;">What's Next?</h4>
        <ul style="margin-left: 20px; color: #555;">
            <li>We'll process your order within 1-2 business days</li>
            <li>You'll receive shipping confirmation with tracking details</li>
            <li>Expected delivery: 3-7 business days</li>
            ${order.payment_method === 'cod' ? '<li>Please keep the exact amount ready for cash on delivery</li>' : ''}
        </ul>
    </div>
    
    <p>If you have any questions about your order, please don't hesitate to contact our customer support team.</p>
    
    <p style="margin-top: 30px;">
        Best regards,<br>
        <strong>The ${brandingConfig.companyName} Team</strong>
    </p>
  `;

  const textContent = `
Order Confirmation - ${brandingConfig.companyName}

Hello ${customer.first_name},

Thank you for your order! We're excited to get your beautiful cosmetics ready for you.

Order Details:
- Order Number: ${order.order_number}
- Order Date: ${emailUtils.formatDate(order.created_at)}
- Payment Method: ${order.payment_method === 'cod' ? 'Cash on Delivery' : 'Online Payment'}
- Status: ${order.status.replace('_', ' ')}

Items Ordered:
${items.map(item => `- ${item.product_name || item.name} (Qty: ${item.quantity}) - ${emailUtils.formatCurrency(item.total_price)}`).join('\n')}

Order Total: ${emailUtils.formatCurrency(order.total_amount)}

Shipping Address:
${address.first_name} ${address.last_name}
${address.address_line1}
${address.address_line2 ? address.address_line2 + '\n' : ''}${address.city}, ${address.state} ${address.postal_code}
${address.country || 'India'}
Phone: ${address.phone}

Track your order: ${trackingUrl}

What's Next?
- We'll process your order within 1-2 business days
- You'll receive shipping confirmation with tracking details
- Expected delivery: 3-7 business days
${order.payment_method === 'cod' ? '- Please keep the exact amount ready for cash on delivery' : ''}

If you have any questions, contact us at ${brandingConfig.supportEmail}

Best regards,
The ${brandingConfig.companyName} Team
  `;

  return {
    html: getBaseTemplate(content, `Order Confirmation - ${order.order_number}`),
    text: textContent
  };
}

/**
 * Order Status Update Email Template
 * Requirements: 12.3
 */
function generateOrderStatusEmail(order, customer, newStatus, previousStatus, notes = null) {
  const trackingUrl = emailUtils.generateTrackingUrl(order.order_number);
  
  const statusMessages = {
    processing: {
      title: 'Your Order is Being Processed',
      message: 'Great news! We\'ve received your payment and are now preparing your order.',
      icon: '📦'
    },
    shipped: {
      title: 'Your Order Has Been Shipped',
      message: 'Your order is on its way! You should receive it within 3-5 business days.',
      icon: '🚚'
    },
    out_for_delivery: {
      title: 'Your Order is Out for Delivery',
      message: 'Your order is out for delivery and should arrive today!',
      icon: '🏃‍♂️'
    },
    delivered: {
      title: 'Your Order Has Been Delivered',
      message: 'Your order has been successfully delivered. We hope you love your new cosmetics!',
      icon: '✅'
    },
    cancelled: {
      title: 'Your Order Has Been Cancelled',
      message: 'Your order has been cancelled. If you didn\'t request this, please contact our support team.',
      icon: '❌'
    }
  };

  const statusInfo = statusMessages[newStatus] || {
    title: 'Order Status Update',
    message: `Your order status has been updated to: ${newStatus.replace('_', ' ')}`,
    icon: '📋'
  };

  const content = `
    <div class="greeting">
        Hello ${emailUtils.sanitizeContent(customer.first_name)},
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <div style="font-size: 48px; margin-bottom: 15px;">${statusInfo.icon}</div>
        <h2 style="color: ${brandingConfig.colors.primary}; margin-bottom: 10px;">${statusInfo.title}</h2>
        <p style="font-size: 16px; color: #666;">${statusInfo.message}</p>
    </div>
    
    <div class="order-info">
        <div class="order-number">Order #${order.order_number}</div>
        <div>Previous Status: <span class="status-badge status-${previousStatus}">${previousStatus.replace('_', ' ')}</span></div>
        <div>Current Status: <span class="status-badge status-${newStatus}">${newStatus.replace('_', ' ')}</span></div>
        <div>Updated: ${emailUtils.formatDate(new Date())}</div>
    </div>
    
    ${notes ? `
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h4 style="color: ${brandingConfig.colors.accent}; margin-bottom: 10px;">Additional Notes:</h4>
        <p>${emailUtils.sanitizeContent(notes)}</p>
    </div>
    ` : ''}
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="${trackingUrl}" class="button">Track Your Order</a>
    </div>
    
    ${newStatus === 'delivered' ? `
    <div style="background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">🌟 How was your experience?</h4>
        <p>We'd love to hear about your experience with ${brandingConfig.companyName}. Your feedback helps us improve!</p>
        <div style="text-align: center; margin-top: 15px;">
            <a href="${brandingConfig.websiteUrl}/review?order=${order.order_number}" style="color: #2e7d32; text-decoration: none; font-weight: bold;">Leave a Review →</a>
        </div>
    </div>
    ` : ''}
    
    <p>Thank you for choosing ${brandingConfig.companyName}. If you have any questions, please don't hesitate to contact us.</p>
    
    <p style="margin-top: 30px;">
        Best regards,<br>
        <strong>The ${brandingConfig.companyName} Team</strong>
    </p>
  `;

  const textContent = `
Order Status Update - ${brandingConfig.companyName}

Hello ${customer.first_name},

${statusInfo.title}
${statusInfo.message}

Order Details:
- Order Number: ${order.order_number}
- Previous Status: ${previousStatus.replace('_', ' ')}
- Current Status: ${newStatus.replace('_', ' ')}
- Updated: ${emailUtils.formatDate(new Date())}

${notes ? `Additional Notes: ${notes}\n` : ''}

Track your order: ${trackingUrl}

${newStatus === 'delivered' ? `
How was your experience?
We'd love to hear about your experience with ${brandingConfig.companyName}. 
Leave a review: ${brandingConfig.websiteUrl}/review?order=${order.order_number}
` : ''}

Thank you for choosing ${brandingConfig.companyName}.

Best regards,
The ${brandingConfig.companyName} Team
  `;

  return {
    html: getBaseTemplate(content, `Order Update - ${order.order_number}`),
    text: textContent
  };
}

/**
 * Payment Confirmation Email Template
 * Requirements: 12.2
 */
function generatePaymentConfirmationEmail(order, customer, paymentDetails) {
  const content = `
    <div class="greeting">
        Hello ${emailUtils.sanitizeContent(customer.first_name)},
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <div style="font-size: 48px; margin-bottom: 15px;">💳</div>
        <h2 style="color: ${brandingConfig.colors.primary}; margin-bottom: 10px;">Payment Confirmed!</h2>
        <p style="font-size: 16px; color: #666;">Your payment has been successfully processed.</p>
    </div>
    
    <div class="order-info">
        <div class="order-number">Order #${order.order_number}</div>
        <div>Payment Amount: ${emailUtils.formatCurrency(order.total_amount)}</div>
        <div>Payment Method: ${paymentDetails.method || 'Online Payment'}</div>
        <div>Transaction ID: ${paymentDetails.transaction_id}</div>
        <div>Payment Date: ${emailUtils.formatDate(paymentDetails.created_at || new Date())}</div>
    </div>
    
    <div style="background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">✅ What's Next?</h4>
        <ul style="margin-left: 20px; color: #555;">
            <li>Your order is now being processed</li>
            <li>You'll receive a shipping confirmation soon</li>
            <li>Expected delivery: 3-7 business days</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="${emailUtils.generateOrderUrl(order.id)}" class="button">View Order Details</a>
    </div>
    
    <p>Keep this email as your payment receipt. If you have any questions about your payment or order, please contact our support team.</p>
    
    <p style="margin-top: 30px;">
        Best regards,<br>
        <strong>The ${brandingConfig.companyName} Team</strong>
    </p>
  `;

  const textContent = `
Payment Confirmation - ${brandingConfig.companyName}

Hello ${customer.first_name},

Payment Confirmed!
Your payment has been successfully processed.

Payment Details:
- Order Number: ${order.order_number}
- Payment Amount: ${emailUtils.formatCurrency(order.total_amount)}
- Payment Method: ${paymentDetails.method || 'Online Payment'}
- Transaction ID: ${paymentDetails.transaction_id}
- Payment Date: ${emailUtils.formatDate(paymentDetails.created_at || new Date())}

What's Next?
- Your order is now being processed
- You'll receive a shipping confirmation soon
- Expected delivery: 3-7 business days

View order details: ${emailUtils.generateOrderUrl(order.id)}

Keep this email as your payment receipt.

Best regards,
The ${brandingConfig.companyName} Team
  `;

  return {
    html: getBaseTemplate(content, `Payment Confirmed - ${order.order_number}`),
    text: textContent
  };
}

/**
 * COD Delivery Confirmation Email Template
 * Requirements: 12.3
 */
function generateCODDeliveryEmail(order, customer, codDetails) {
  const content = `
    <div class="greeting">
        Hello ${emailUtils.sanitizeContent(customer.first_name)},
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <div style="font-size: 48px; margin-bottom: 15px;">💰</div>
        <h2 style="color: ${brandingConfig.colors.primary}; margin-bottom: 10px;">COD Payment Collected</h2>
        <p style="font-size: 16px; color: #666;">Your cash on delivery payment has been successfully collected.</p>
    </div>
    
    <div class="order-info">
        <div class="order-number">Order #${order.order_number}</div>
        <div>Amount Collected: ${emailUtils.formatCurrency(codDetails.collection_amount)}</div>
        <div>Delivery Person: ${codDetails.delivery_person_name}</div>
        <div>Collection Date: ${emailUtils.formatDate(codDetails.delivery_confirmed_at)}</div>
        <div>Status: <span class="status-badge status-delivered">Delivered & Paid</span></div>
    </div>
    
    ${codDetails.delivery_notes ? `
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h4 style="color: ${brandingConfig.colors.accent}; margin-bottom: 10px;">Delivery Notes:</h4>
        <p>${emailUtils.sanitizeContent(codDetails.delivery_notes)}</p>
    </div>
    ` : ''}
    
    <div style="background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">🌟 Thank You!</h4>
        <p>Your order has been successfully delivered and payment collected. We hope you love your new cosmetics from ${brandingConfig.companyName}!</p>
    </div>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="${brandingConfig.websiteUrl}/review?order=${order.order_number}" class="button">Leave a Review</a>
    </div>
    
    <p>This email serves as your receipt for the cash on delivery payment. Thank you for choosing ${brandingConfig.companyName}!</p>
    
    <p style="margin-top: 30px;">
        Best regards,<br>
        <strong>The ${brandingConfig.companyName} Team</strong>
    </p>
  `;

  const textContent = `
COD Payment Collected - ${brandingConfig.companyName}

Hello ${customer.first_name},

COD Payment Collected!
Your cash on delivery payment has been successfully collected.

Delivery Details:
- Order Number: ${order.order_number}
- Amount Collected: ${emailUtils.formatCurrency(codDetails.collection_amount)}
- Delivery Person: ${codDetails.delivery_person_name}
- Collection Date: ${emailUtils.formatDate(codDetails.delivery_confirmed_at)}
- Status: Delivered & Paid

${codDetails.delivery_notes ? `Delivery Notes: ${codDetails.delivery_notes}\n` : ''}

Thank you for choosing ${brandingConfig.companyName}!
We hope you love your new cosmetics.

Leave a review: ${brandingConfig.websiteUrl}/review?order=${order.order_number}

This email serves as your receipt for the COD payment.

Best regards,
The ${brandingConfig.companyName} Team
  `;

  return {
    html: getBaseTemplate(content, `COD Payment Collected - ${order.order_number}`),
    text: textContent
  };
}

module.exports = {
  generateOrderConfirmationEmail,
  generateOrderStatusEmail,
  generatePaymentConfirmationEmail,
  generateCODDeliveryEmail,
  getBaseTemplate,
  generateItemsTable,
  generateTotalsSection,
  generateAddressSection
};