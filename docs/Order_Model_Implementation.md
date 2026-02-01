# Order Model Implementation

## Overview

The Order model provides comprehensive order processing functionality with transaction support to ensure data consistency. It handles the complete order lifecycle from creation to delivery tracking, maintaining API compatibility with the existing Node.js backend.

## Features

### Core Functionality
- **Order Creation**: Complete order processing with order items and transaction support
- **Order Retrieval**: Get orders by ID, order number, user, with filtering and pagination
- **Status Management**: Track order status with valid state transitions
- **Order Cancellation**: Cancel orders with stock restoration
- **Order Statistics**: Comprehensive reporting and analytics

### Transaction Support
- **ACID Compliance**: All order operations use database transactions
- **Rollback Protection**: Failed operations don't leave partial data
- **Stock Management**: Automatic stock updates with consistency checks
- **Data Integrity**: Foreign key relationships maintained

### Order Number Generation
- **Unique Format**: RC + YYYYMMDD + 4-digit random number
- **Collision Handling**: Automatic retry with fallback mechanisms
- **Date-based**: Easy identification and sorting by creation date

## Class Structure

```php
class Order extends DatabaseModel {
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    
    // Payment method constants
    const PAYMENT_COD = 'cod';
    const PAYMENT_ONLINE = 'online';
    const PAYMENT_RAZORPAY = 'razorpay';
}
```

## API Methods

### Order Creation

```php
public function createOrder($orderData)
```

Creates a new order with transaction support.

**Parameters:**
- `$orderData` (array): Order data including items

**Required Fields:**
- `user_id` (int): User placing the order
- `payment_method` (string): Payment method (cod, online, razorpay)
- `items` (array): Array of order items

**Item Structure:**
```php
[
    'product_id' => 123,
    'quantity' => 2,
    'unit_price' => 150.00,
    'product_name' => 'Product Name', // Optional
    'product_sku' => 'SKU123' // Optional
]
```

**Returns:** Created order data with calculated totals

**Example:**
```php
$orderData = [
    'user_id' => 1,
    'payment_method' => Order::PAYMENT_COD,
    'currency' => 'INR',
    'notes' => 'Special delivery instructions',
    'items' => [
        [
            'product_id' => 1,
            'quantity' => 2,
            'unit_price' => 100.00
        ]
    ]
];

$order = $orderModel->createOrder($orderData);
```

### Order Retrieval

```php
public function getOrderById($orderId)
public function getOrderByNumber($orderNumber)
public function getOrdersByUser($userId, $filters = [], $page = 1, $perPage = 20)
public function getAllOrders($filters = [], $page = 1, $perPage = 20)
```

**Filters Available:**
- `status`: Filter by order status
- `payment_method`: Filter by payment method
- `date_from`: Orders from date
- `date_to`: Orders to date
- `search`: Search in order number, user name, email

### Status Management

```php
public function updateOrderStatus($orderId, $status, $notes = null)
```

Updates order status with validation of state transitions.

**Valid Transitions:**
- `pending` → `confirmed`, `cancelled`
- `confirmed` → `processing`, `cancelled`
- `processing` → `shipped`, `cancelled`
- `shipped` → `delivered`
- `delivered` → `refunded`

### Order Cancellation

```php
public function cancelOrder($orderId, $reason = null)
```

Cancels an order and restores product stock.

**Cancellable Statuses:**
- `pending`
- `confirmed`

## Database Schema

### Orders Table
```sql
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method ENUM('cod', 'online', 'razorpay') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    shipping_amount DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'INR',
    shipping_address_id INT,
    billing_address_id INT,
    notes TEXT,
    expected_delivery_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (shipping_address_id) REFERENCES addresses(id),
    FOREIGN KEY (billing_address_id) REFERENCES addresses(id),
    INDEX idx_user_id (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

### Order Items Table
```sql
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    product_name VARCHAR(255),
    product_sku VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id)
);
```

### Order Status History Table
```sql
CREATE TABLE order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_created_at (created_at)
);
```

## Pricing Calculations

### Subtotal
Sum of all item totals (quantity × unit_price)

### Tax Calculation
18% GST applied to subtotal (configurable)

### Shipping Calculation
- Free shipping for orders ≥ ₹500
- ₹50 shipping charge for orders < ₹500

### Total Amount
Subtotal + Tax + Shipping - Discount

## Error Handling

### Validation Errors
- Missing required fields
- Invalid data types
- Invalid enum values
- Empty order items

### Business Logic Errors
- Insufficient stock
- Invalid status transitions
- Order not found
- User not found

### Transaction Errors
- Database connection failures
- Constraint violations
- Rollback scenarios

## Security Features

### Input Validation
- SQL injection prevention through prepared statements
- Data type validation
- Range validation for quantities and prices
- Enum validation for status and payment methods

### Access Control
- User-based order access
- Admin-only operations
- Status transition permissions

### Data Integrity
- Foreign key constraints
- Transaction consistency
- Stock level validation
- Unique order number enforcement

## Performance Optimizations

### Database Indexing
- Primary keys on all tables
- Foreign key indexes
- Search field indexes (user_id, order_number, status)
- Date-based indexes for reporting

### Query Optimization
- Efficient JOIN operations
- Pagination support
- Selective field retrieval
- Bulk operations for order items

### Caching Strategy
- Order statistics caching
- Product information caching
- User data caching

## Testing

### Unit Tests
- Order creation scenarios
- Status transition validation
- Calculation accuracy
- Error handling

### Property-Based Tests
- Order number uniqueness
- Transaction consistency
- Data integrity
- Calculation properties

### Integration Tests
- End-to-end order flow
- Payment integration
- Stock management
- Email notifications

## Usage Examples

### Basic Order Creation
```php
$order = new Order();

$orderData = [
    'user_id' => 123,
    'payment_method' => Order::PAYMENT_COD,
    'items' => [
        [
            'product_id' => 456,
            'quantity' => 2,
            'unit_price' => 299.99
        ]
    ]
];

try {
    $createdOrder = $order->createOrder($orderData);
    echo "Order created: " . $createdOrder['order_number'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Order Status Update
```php
try {
    $order->updateOrderStatus(123, Order::STATUS_CONFIRMED, 'Payment verified');
    echo "Order status updated successfully";
} catch (Exception $e) {
    echo "Status update failed: " . $e->getMessage();
}
```

### Get User Orders
```php
$filters = [
    'status' => Order::STATUS_DELIVERED,
    'date_from' => '2024-01-01'
];

$result = $order->getOrdersByUser(123, $filters, 1, 20);

foreach ($result['orders'] as $userOrder) {
    echo "Order: " . $userOrder['order_number'] . "\n";
}
```

## Migration from Node.js

### API Compatibility
- Same endpoint structure
- Identical response formats
- Compatible error codes
- Matching field names

### Data Migration
- Direct database compatibility
- Same foreign key relationships
- Preserved data types
- Maintained constraints

### Business Logic
- Identical calculation formulas
- Same status workflow
- Compatible validation rules
- Matching order number format

## Monitoring and Logging

### Order Events
- Order creation
- Status changes
- Cancellations
- Payment updates

### Performance Metrics
- Order processing time
- Database query performance
- Transaction success rates
- Error frequencies

### Business Metrics
- Order volume
- Revenue tracking
- Conversion rates
- Customer behavior

## Future Enhancements

### Planned Features
- Order splitting
- Partial cancellations
- Advanced pricing rules
- Multi-currency support

### Scalability Improvements
- Database sharding
- Read replicas
- Caching layers
- Queue processing

### Integration Enhancements
- Real-time notifications
- Advanced reporting
- Third-party logistics
- Inventory management