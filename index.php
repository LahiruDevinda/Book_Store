<?php
/**
 * BookStore - Storefront Entry Point
 * Minimalist, Modern & Fully Responsive
 */

require_once __DIR__ . '/config/db.php';
startSecureSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookStore - Curated Books & Timeless Literature</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- ======================== SIMPLE NAVIGATION BAR ======================== -->
        <header class="site-header">
            <div class="container header-inner">
                <!-- Brand Logo -->
                <a href="index.php" class="brand-logo">
                    <span class="brand-icon">📚</span>
                    <span class="brand-name">BookStore<span class="brand-dot">.</span></span>
                </a>

                <!-- Search Bar -->
                <div class="search-wrapper">
                    <div class="search-input-group">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="siteSearchInput" class="search-input" placeholder="Search by title, author, or ISBN..." autocomplete="off">
                        <button id="searchClearBtn" class="search-clear-btn" title="Clear search">&times;</button>
                    </div>
                </div>

                
