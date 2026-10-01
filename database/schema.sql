CREATE DATABASE IF NOT EXISTS bookstore_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bookstore_db;

-- 1. Users Table (Supports 'customer', 'admin', and 'shopkeeper' roles)
CREATE TABLE IF NOT EXISTS Users (
    userid INT AUTO_INCREMENT PRIMARY KEY,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    isAdmin BOOLEAN DEFAULT FALSE,
    role VARCHAR(20) DEFAULT 'customer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Address Book Table
CREATE TABLE IF NOT EXISTS AddressBook (
    addressid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT NOT NULL,
    no VARCHAR(50) NOT NULL,
    street VARCHAR(255) NOT NULL,
    zipCode VARCHAR(20) NOT NULL,
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Promo Codes Table
CREATE TABLE IF NOT EXISTS PromoCode (
    promoCodeld INT AUTO_INCREMENT PRIMARY KEY,
    userid INT NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    type VARCHAR(20) NOT NULL, -- 'percentage' or 'fixed'
    price DECIMAL(10,2) NOT NULL,
    isValid BOOLEAN DEFAULT TRUE,
    exp_date DATE NOT NULL,
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Book Catalog Table
CREATE TABLE IF NOT EXISTS Book (
    bookid INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    ISBN VARCHAR(50) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stockQuantity INT NOT NULL DEFAULT 0,
    coverImageUrl VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Author Table
CREATE TABLE IF NOT EXISTS Author (
    authorid INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    biography TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Genre Table
CREATE TABLE IF NOT EXISTS Genre (
    genreid INT AUTO_INCREMENT PRIMARY KEY,
    genreName VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Bridge Table: Book_Author
CREATE TABLE IF NOT EXISTS Book_Author (
    bookid INT NOT NULL,
    authorid INT NOT NULL,
    PRIMARY KEY (bookid, authorid),
    FOREIGN KEY (bookid) REFERENCES Book(bookid) ON DELETE CASCADE,
    FOREIGN KEY (authorid) REFERENCES Author(authorid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Bridge Table: Book_Genre
CREATE TABLE IF NOT EXISTS Book_Genre (
    bookid INT NOT NULL,
    genreid INT NOT NULL,
    PRIMARY KEY (bookid, genreid),
    FOREIGN KEY (bookid) REFERENCES Book(bookid) ON DELETE CASCADE,
    FOREIGN KEY (genreid) REFERENCES Genre(genreid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Cart Table
CREATE TABLE IF NOT EXISTS Cart (
    cartid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT UNIQUE NOT NULL,
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Cart Item Table
CREATE TABLE IF NOT EXISTS Cart_Item (
    cartid INT NOT NULL,
    bookid INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    PRIMARY KEY (cartid, bookid),
    FOREIGN KEY (cartid) REFERENCES Cart(cartid) ON DELETE CASCADE,
    FOREIGN KEY (bookid) REFERENCES Book(bookid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Wishlist Table
CREATE TABLE IF NOT EXISTS Wishlist (
    userid INT NOT NULL,
    bookid INT NOT NULL,
    PRIMARY KEY (userid, bookid),
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE,
    FOREIGN KEY (bookid) REFERENCES Book(bookid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Orders Table (With Delivery Tracking & Confirmation)
CREATE TABLE IF NOT EXISTS Orders (
    orderid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT NOT NULL,
    addressid INT NULL,
    promoCodeld INT NULL,
    subTotal DECIMAL(10,2) NOT NULL,
    orderStatus VARCHAR(50) DEFAULT 'Completed',
    deliveryStatus VARCHAR(50) DEFAULT 'Processing',
    isDeliveredConfirmed BOOLEAN DEFAULT FALSE,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE,
    FOREIGN KEY (addressid) REFERENCES AddressBook(addressid) ON DELETE SET NULL,
    FOREIGN KEY (promoCodeld) REFERENCES PromoCode(promoCodeld) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Order Item Table (Locked Historical Unit Prices)
CREATE TABLE IF NOT EXISTS Order_Item (
    orderid INT NOT NULL,
    bookid INT NOT NULL,
    unitPrice DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    PRIMARY KEY (orderid, bookid),
    FOREIGN KEY (orderid) REFERENCES Orders(orderid) ON DELETE CASCADE,
    FOREIGN KEY (bookid) REFERENCES Book(bookid) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Payment Table
CREATE TABLE IF NOT EXISTS Payment (
    paymentid INT AUTO_INCREMENT PRIMARY KEY,
    orderid INT NOT NULL,
    method VARCHAR(50) NOT NULL, -- 'COD' or 'card'
    status VARCHAR(50) NOT NULL DEFAULT 'Completed',
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orderid) REFERENCES Orders(orderid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Review Table
CREATE TABLE IF NOT EXISTS Review (
    reviewid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT NOT NULL,
    bookid INT NOT NULL,
    rate INT NOT NULL,
    description TEXT,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE,
    FOREIGN KEY (bookid) REFERENCES Book(bookid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Complaint Table
CREATE TABLE IF NOT EXISTS Complaint (
    complaintid INT AUTO_INCREMENT PRIMARY KEY,
    orderid INT NOT NULL,
    userid INT NOT NULL,
    message TEXT NOT NULL,
    reply TEXT NULL,
    status VARCHAR(20) DEFAULT 'Pending',
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (orderid) REFERENCES Orders(orderid) ON DELETE CASCADE,
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Inventory Request Table (Shopkeeper to Admin Workflow)
CREATE TABLE IF NOT EXISTS InventoryRequest (
    requestid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT NOT NULL,
    bookid INT NULL,
    action_type VARCHAR(20) NOT NULL, -- 'add' or 'update'
    title VARCHAR(255) NOT NULL,
    ISBN VARCHAR(20) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stockQuantity INT NOT NULL,
    coverImageUrl VARCHAR(255),
    status VARCHAR(20) DEFAULT 'Pending', -- 'Pending', 'Approved', 'Rejected'
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userid) REFERENCES Users(userid) ON DELETE CASCADE,
    FOREIGN KEY (bookid) REFERENCES Book(bookid) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;