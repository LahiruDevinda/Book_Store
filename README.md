# Full-Stack Book Store Web Application

A secure, production-ready, and fully functional Book Store Web Application built strictly with **HTML5, CSS3, Vanilla JavaScript (ES6+), PHP (PDO), and MySQL**.

---

## 1. System Roles & Permissions

### Guest User
- Can freely browse books, search catalog, filter by genres, and inspect customer ratings.
- Adds items to **Wishlist** and **Cart** stored temporarily in the browser's `localStorage` (`guest_cart`, `guest_wishlist`).
- Cannot place orders or purchase books: clicking the checkout button in the cart drawer immediately triggers the **Login / Signup Modal** with an informative prompt.

### Authenticated User (Customer)
- Cart and Wishlist state automatically persists in the MySQL database (`Cart_Item`, `Wishlist`).
- Can manage saved addresses in `AddressBook`.
- Can apply single-use promotional codes (`PromoCode`) with automated expiration and ownership verification.
- Can place orders with guaranteed **historical price-locking** (`Order_Item.unitPrice` recorded at purchase time).
- Can submit book reviews and star ratings (1 to 5 stars).

### Administrator
- Accesses a protected route (`/admin/dashboard.php` and `/admin/api.php`) where **every script strictly verifies both session credentials and the `isAdmin` boolean flag**.
- Manages inventory (books, stock quantities, prices, ISBNs).
- Manages authors, genres, and **atomic bridge table associations** (`Book_Author` and `Book_Genre`).
- Audits customer orders, locked historical unit prices, and applied promo codes.

---

## 2. Core Workflows & Logic

### Guest-to-User Synchronization ("The Merge")
1. When a guest logs in or registers, frontend JavaScript reads `localStorage` keys `guest_wishlist` and `guest_cart`.
2. An asynchronous JSON payload is transmitted to `api/sync_local.php`.
3. The PHP backend utilizes `INSERT IGNORE` queries on composite primary keys:
   - `Wishlist`: `PRIMARY KEY (userid, bookid)`
   - `Cart_Item`: `PRIMARY KEY (cartid, bookid)`
4. This merges items into the user's permanent database cart and wishlist without duplicates or primary key collision errors.
5. On HTTP 200 response, the frontend clears `localStorage.removeItem('guest_cart')` and `localStorage.removeItem('guest_wishlist')`.

### Checkout & Order Flow
1. **Initiation**: User opens their cart and clicks "Proceed to Checkout".
2. **Address Verification**: The system queries `AddressBook`. The user can select an existing address or specify a new delivery address (automatically stored in `AddressBook`).
3. **Promo Code Validation**: The backend checks:
   - Ownership (`userid = :uid`)
   - Validity (`isValid = TRUE`)
   - Expiration (`exp_date >= CURDATE()`)
   - If valid, the calculated discount is deducted, and the promo code is invalidated (`UPDATE PromoCode SET isValid = FALSE`).
4. **Historical Price-Locking**:
   - Order is inserted into `Orders`.
   - Each book is inserted into `Order_Item (orderid, bookid, unitPrice, quantity)` using the exact `Book.price` at that instant.
   - Book stock is decremented in `Book.stockQuantity`.
   - Payment record is created in `Payment`.
   - The user's `Cart_Item` entries are cleared.
   - The entire checkout runs inside an atomic PDO database transaction (`beginTransaction` / `commit` / `rollBack`).

---

## 3. Database Schema Overview (MySQL)

- **Independent Entities**:
  - `Users (userid, firstName, lastName, email, password, isAdmin)`
  - `Book (bookid, title, ISBN, price, stockQuantity, coverImageUrl)`
  - `Author (authorid, name, biography)`
  - `Genre (genreid, genreName)`
- **User-Dependent Entities**:
  - `AddressBook (addressid, userid, no, street, zipCode)`
  - `Cart (cartid, userid UNIQUE)`
  - `PromoCode (promoCodeld, userid, code, type, price, isValid, exp_date)`
- **Transactions & Social**:
  - `Orders (orderid, userid, addressid, promoCodeld, subTotal, orderStatus, date)`
  - `Payment (paymentId, orderid UNIQUE, method, status)`
  - `Review (reviewld, userid, bookid, rate, review, date)`
- **Many-to-Many Bridge Tables**:
  - `Book_Author (bookid, authorid)` [Composite Primary Key]
  - `Book_Genre (bookid, genreid)` [Composite Primary Key]
  - `Wishlist (userid, bookid)` [Composite Primary Key]
  - `Cart_Item (cartid, bookid, quantity)` [Composite Primary Key]
  - `Order_Item (orderid, bookid, unitPrice, quantity)` [Composite Primary Key]

---

## 4. Default Seeded Credentials

| Role | Email | Password | Access Level |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@bookstore.com` | `Admin@1234` | Full Dashboard & Inventory CRUD (`isAdmin = 1`) |
| **Customer User** | `john@example.com` | `User@1234` | Shopping, Wishlist, Checkout (`isAdmin = 0`) |

### Sample Pre-Configured Promo Codes (for John Doe):
- `WELCOME10`: 10% percentage discount (Valid)
- `SAVE20`: $20.00 fixed discount (Valid)
- `EXPIRED5`: $5.00 fixed discount (Expired, for testing validity checks)

---

## 5. Running the Application

### 1. Database Provisioning
Run the setup script using PHP CLI:
```powershell
C:\xampp\php\php.exe database\setup.php
```

### 2. Start Local Web Server
Start PHP's built-in web server:
```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080
```
Then visit **http://127.0.0.1:8080** in your browser.
