-- =========================================================
-- PLAYBOX Toy Store Management System - Database Schema
-- =========================================================

-- ---------------------------------------------------------
-- 1. Users (Authentication / Role-Based Access)
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('Admin','Manager','Cashier','Store Staff') NOT NULL DEFAULT 'Cashier',
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin user -> username: admin / password: admin123
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$5C5tK0m0F5b3H2rWJmYQdOe8yQeYQ6f8Zt1n8Rr0m7T9ZC0m4o1uK', 'Alex Admin', 'admin@playbox.com', 'Admin');
-- NOTE: run database/reset_admin_password.php OR see php/login.php seeding note if hash above doesn't match.

-- ---------------------------------------------------------
-- 2. Categories
-- ---------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, description) VALUES
('Educational Toys','Toys that help children learn'),
('Building Blocks','Construction and building sets'),
('Dolls','Dolls and accessories'),
('Action Figures','Action figures and collectibles'),
('Remote Control Toys','RC cars, drones, etc.'),
('Board Games','Family and strategy board games'),
('Puzzles','Jigsaw and logic puzzles'),
('Outdoor Toys','Toys for outdoor play'),
('Musical Toys','Instruments and musical toys'),
('Baby Toys','Toys for infants and toddlers');

-- ---------------------------------------------------------
-- 3. Brands
-- ---------------------------------------------------------
CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO brands (name) VALUES
('LEGO'),('Mattel'),('Hasbro'),('Fisher-Price'),('Hot Wheels'),('Barbie'),('Nerf'),('Playmobil');

-- ---------------------------------------------------------
-- 4. Suppliers
-- ---------------------------------------------------------
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(20) UNIQUE,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(30),
    email VARCHAR(100),
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- 5. Customers
-- ---------------------------------------------------------
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(20) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(100),
    address VARCHAR(255),
    membership_level ENUM('Bronze','Silver','Gold','Platinum') DEFAULT 'Bronze',
    loyalty_points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- 6. Toys (Products)
-- ---------------------------------------------------------
CREATE TABLE toys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(30) NOT NULL UNIQUE,
    barcode VARCHAR(50) UNIQUE,
    name VARCHAR(150) NOT NULL,
    brand_id INT,
    category_id INT,
    age_group VARCHAR(30),
    material VARCHAR(100),
    color VARCHAR(50),
    purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_qty INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 5,
    warranty VARCHAR(50),
    image VARCHAR(255),
    status ENUM('Available','Out of Stock','Discontinued') DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- 7. Purchases (Purchase Orders from Suppliers)
-- ---------------------------------------------------------
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_code VARCHAR(30) UNIQUE,
    supplier_id INT,
    purchase_date DATE NOT NULL,
    status ENUM('Pending','Received','Cancelled') DEFAULT 'Pending',
    total_amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

CREATE TABLE purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    toy_id INT NOT NULL,
    qty INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (toy_id) REFERENCES toys(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 8. Inventory Log (Stock In / Out / Adjustment)
-- ---------------------------------------------------------
CREATE TABLE inventory_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    toy_id INT NOT NULL,
    type ENUM('Stock In','Stock Out','Adjustment') NOT NULL,
    qty INT NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (toy_id) REFERENCES toys(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 9. Employees
-- ---------------------------------------------------------
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('Manager','Cashier','Sales Staff','Storekeeper') NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(100),
    salary DECIMAL(10,2) DEFAULT 0,
    hire_date DATE,
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- 10. Promotions
-- ---------------------------------------------------------
CREATE TABLE promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('Percentage','Fixed Amount','BOGO','Member Discount') NOT NULL,
    value DECIMAL(10,2) DEFAULT 0,
    coupon_code VARCHAR(30) UNIQUE,
    start_date DATE,
    end_date DATE,
    status ENUM('Active','Inactive','Expired') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- 11. Sales (POS)
-- ---------------------------------------------------------
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) UNIQUE,
    customer_id INT,
    employee_id INT,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method ENUM('Cash','Credit/Debit Card','Bank Transfer','QR Payment') DEFAULT 'Cash',
    coupon_code VARCHAR(30),
    status ENUM('Completed','Returned','Exchanged') DEFAULT 'Completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    toy_id INT NOT NULL,
    qty INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (toy_id) REFERENCES toys(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 12. Notifications
-- ---------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('Low Stock','New Product','Promotion','Supplier Delivery','Customer Birthday') NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- 13. Settings
-- ---------------------------------------------------------
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_name VARCHAR(150) DEFAULT 'PLAYBOX Toy Store',
    tax_rate DECIMAL(5,2) DEFAULT 10.00,
    currency VARCHAR(10) DEFAULT 'USD',
    business_hours VARCHAR(100) DEFAULT '09:00 - 21:00',
    address VARCHAR(255),
    phone VARCHAR(30)
);

INSERT INTO settings (store_name, tax_rate, currency, business_hours) VALUES
('PLAYBOX Toy Store', 10.00, 'USD', '09:00 - 21:00');

-- ---------------------------------------------------------
-- 14. Attendance
-- ---------------------------------------------------------
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    work_date DATE NOT NULL,
    clock_in TIME,
    clock_out TIME,
    status ENUM('Present','Late','Absent','On Leave') DEFAULT 'Present',
    notes VARCHAR(255),
    UNIQUE KEY uniq_emp_day (employee_id, work_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 15. Work Schedule
-- ---------------------------------------------------------
CREATE TABLE work_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    day_of_week ENUM('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL,
    shift_start TIME NOT NULL,
    shift_end TIME NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

ALTER TABLE sales
    ADD COLUMN redeem_points INT NOT NULL DEFAULT 0 AFTER coupon_code,
    ADD COLUMN exchanged_to_sale_id INT NULL AFTER status,
    ADD COLUMN exchanged_from_sale_id INT NULL AFTER exchanged_to_sale_id;
INSERT INTO toys (sku, barcode, name, brand_id, category_id, age_group, material, color, purchase_price, selling_price, stock_qty, low_stock_threshold, warranty, status) VALUES
('TOY-0001','8801234560001','LEGO City Fire Truck',1,2,'6+','Plastic','Red',18.00,29.99,40,5,'6 Months','Available'),
('TOY-0002','8801234560002','Barbie Dream House',6,3,'3+','Plastic/Fabric','Pink',45.00,79.99,15,5,'No Warranty','Available'),
('TOY-0003','8801234560003','Hot Wheels 5-Car Pack',5,4,'4+','Metal','Multicolor',6.00,12.99,3,5,'No Warranty','Available'),
('TOY-0004','8801234560004','Nerf Elite Blaster',7,4,'8+','Plastic/Foam',' Orange',12.00,24.99,0,5,'No Warranty','Out of Stock'),
('TOY-0005','8801234560005','Fisher-Price Baby Rattle',4,10,'0-2','Plastic','Yellow',3.50,8.99,60,10,'No Warranty','Available');
