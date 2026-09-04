<?php

require_once __DIR__ . '/../config/db.php';

echo "=== Initializing Book Store Database Setup ===\n";

try {
    $pdoRoot = getDBConnection(false);
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "[OK] Database '" . DB_NAME . "' ensured.\n";

    $pdo = getDBConnection(true);
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schemaSql);
    echo "[OK] All schema tables ensured.\n";

    $adminEmail = 'admin@bookstore.com';
    $userEmail = 'john@example.com';

    $stmtCheck = $pdo->prepare("SELECT userid FROM Users WHERE email = ?");
    $stmtCheck->execute([$adminEmail]);
    $admin = $stmtCheck->fetch();

    if (!$admin) {
        $adminHash = password_hash('Admin@1234', PASSWORD_BCRYPT);
        $stmtAdmin = $pdo->prepare("INSERT INTO Users (firstName, lastName, email, password, isAdmin) VALUES (?, ?, ?, ?, 1)");
        $stmtAdmin->execute(['Admin', 'Master', $adminEmail, $adminHash]);
        $adminId = $pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO Cart (userid) VALUES (?)")->execute([$adminId]);
        echo "[OK] Admin user ensured.\n";
    }

    $stmtCheck->execute([$userEmail]);
    $user = $stmtCheck->fetch();
    if (!$user) {
        $userHash = password_hash('User@1234', PASSWORD_BCRYPT);
        $stmtUser = $pdo->prepare("INSERT INTO Users (firstName, lastName, email, password, isAdmin) VALUES (?, ?, ?, ?, 0)");
        $stmtUser->execute(['John', 'Doe', $userEmail, $userHash]);
        $userId = $pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO Cart (userid) VALUES (?)")->execute([$userId]);

        $pdo->prepare("INSERT INTO AddressBook (userid, no, street, zipCode) VALUES (?, ?, ?, ?)")
            ->execute([$userId, '104B', 'Silicon Valley Blvd, Suite 200', '94025']);

        $nextMonth = date('Y-m-d', strtotime('+30 days'));
        $lastMonth = date('Y-m-d', strtotime('-10 days'));

        $pdo->prepare("INSERT INTO PromoCode (userid, code, type, price, isValid, exp_date) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, 'WELCOME10', 'percentage', 10.00, 1, $nextMonth]);
        $pdo->prepare("INSERT INTO PromoCode (userid, code, type, price, isValid, exp_date) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, 'SAVE20', 'fixed', 20.00, 1, $nextMonth]);
        $pdo->prepare("INSERT INTO PromoCode (userid, code, type, price, isValid, exp_date) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, 'EXPIRED5', 'fixed', 5.00, 1, $lastMonth]);
        echo "[OK] Customer user and promos ensured.\n";
    } else {
        $userId = $user['userid'];
    }

    $authors = [
        ['Robert C. Martin', 'Uncle Bob is an author of renowned software craftsmanship books.'],
        ['George Orwell', 'Eric Arthur Blair was an English novelist and essayist.'],
        ['Frank Herbert', 'American science-fiction author best known for Dune.'],
        ['F. Scott Fitzgerald', 'American novelist of the Jazz Age.'],
        ['Yuval Noah Harari', 'Historian, philosopher and author of Sapiens.'],
        ['Martin Fowler', 'Author and international speaker on software architecture.']
    ];

    $authorMap = [];
    $stmtAuthor = $pdo->prepare("INSERT INTO Author (name, biography) VALUES (?, ?)");
    foreach ($authors as $a) {
        $chk = $pdo->prepare("SELECT authorid FROM Author WHERE name = ?");
        $chk->execute([$a[0]]);
        $row = $chk->fetch();
        if ($row) {
            $authorMap[$a[0]] = $row['authorid'];
        } else {
            $stmtAuthor->execute([$a[0], $a[1]]);
            $authorMap[$a[0]] = $pdo->lastInsertId();
        }
    }

  $genres = ['Software Engineering', 'Science Fiction', 'Classic Literature', 'History', 'Philosophy', 'Dystopian'];
    $genreMap = [];
    $stmtGenre = $pdo->prepare("INSERT INTO Genre (genreName) VALUES (?)");
    foreach ($genres as $g) {
        $chk = $pdo->prepare("SELECT genreid FROM Genre WHERE genreName = ?");
        $chk->execute([$g]);
        $row = $chk->fetch();
        if ($row) {
            $genreMap[$g] = $row['genreid'];
        } else {
            $stmtGenre->execute([$g]);
            $genreMap[$g] = $pdo->lastInsertId();
        }
    }  

     $books = [
        [
            'title' => 'Clean Code: A Handbook of Agile Software Craftsmanship',
            'ISBN' => '978-0132350884',
            'price' => 42.99,
            'stock' => 15,
            'cover' => 'https://images.unsplash.com/photo-1532012164546-f432f2e3777f?w=600&auto=format&fit=crop&q=80',
            'authors' => ['Robert C. Martin'],
            'genres' => ['Software Engineering']
        ],
        [
            'title' => 'Clean Architecture: A Craftsman\'s Guide to Software Structure',
            'ISBN' => '978-0134494166',
            'price' => 38.50,
            'stock' => 10,
            'cover' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=600&auto=format&fit=crop&q=80',
            'authors' => ['Robert C. Martin'],
            'genres' => ['Software Engineering']
        ],
        [
            'title' => 'Refactoring: Improving the Design of Existing Code',
            'ISBN' => '978-0134757599',
            'price' => 49.99,
            'stock' => 8,
            'cover' => 'https://images.unsplash.com/photo-1512820790803-83ca734da794?w=600&auto=format&fit=crop&q=80',
            'authors' => ['Martin Fowler'],
            'genres' => ['Software Engineering']
        ],
        [
            'title' => '1984',
            'ISBN' => '978-0451524935',
            'price' => 14.95,
            'stock' => 25,
            'cover' => 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=600&auto=format&fit=crop&q=80',
            'authors' => ['George Orwell'],
            'genres' => ['Classic Literature', 'Dystopian']
        ],
        [
            'title' => 'Animal Farm',
            'ISBN' => '978-0451526342',
            'price' => 11.50,
            'stock' => 30,
            'cover' => 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=600&auto=format&fit=crop&q=80',
            'authors' => ['George Orwell'],
            'genres' => ['Classic Literature', 'Dystopian']
        ],
        [
            'title' => 'Dune',
            'ISBN' => '978-0441172719',
            'price' => 18.99,
            'stock' => 18,
            'cover' => 'https://images.unsplash.com/photo-1516979187457-637abb4f9353?w=600&auto=format&fit=crop&q=80',
            'authors' => ['Frank Herbert'],
            'genres' => ['Science Fiction']
        ],
        [
            'title' => 'The Great Gatsby',
            'ISBN' => '978-0743273565',
            'price' => 15.20,
            'stock' => 12,
            'cover' => 'https://images.unsplash.com/photo-1495640388908-05fa85288e61?w=600&auto=format&fit=crop&q=80',
            'authors' => ['F. Scott Fitzgerald'],
            'genres' => ['Classic Literature']
        ],
        [
            'title' => 'Sapiens: A Brief History of Humankind',
            'ISBN' => '978-0062316097',
            'price' => 24.00,
            'stock' => 14,
            'cover' => 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?w=600&auto=format&fit=crop&q=80',
            'authors' => ['Yuval Noah Harari'],
            'genres' => ['History', 'Philosophy']
        ]
    ];

    $stmtInsertBook = $pdo->prepare("INSERT INTO Book (title, ISBN, price, stockQuantity, coverImageUrl) VALUES (?, ?, ?, ?, ?)");
    $stmtBridgeAuthor = $pdo->prepare("INSERT IGNORE INTO Book_Author (bookid, authorid) VALUES (?, ?)");
    $stmtBridgeGenre = $pdo->prepare("INSERT IGNORE INTO Book_Genre (bookid, genreid) VALUES (?, ?)");

    foreach ($books as $b) {
        $chk = $pdo->prepare("SELECT bookid FROM Book WHERE ISBN = ?");
        $chk->execute([$b['ISBN']]);
        $row = $chk->fetch();

        if ($row) {
            $bookId = $row['bookid'];
        } else {
            $stmtInsertBook->execute([$b['title'], $b['ISBN'], $b['price'], $b['stock'], $b['cover']]);
            $bookId = $pdo->lastInsertId();
        }

        foreach ($b['authors'] as $authName) {
            if (isset($authorMap[$authName])) {
                $stmtBridgeAuthor->execute([$bookId, $authorMap[$authName]]);
            }
        }

        foreach ($b['genres'] as $genName) {
            if (isset($genreMap[$genName])) {
                $stmtBridgeGenre->execute([$bookId, $genreMap[$genName]]);
            }
        }
    }

    echo "=== Database setup completed successfully! ===\n";

}