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

                    <!-- Right Actions -->
                <div class="header-actions">
                    <!-- Wishlist Trigger -->
                    <button class="action-icon-btn" id="wishlistTrigger" title="View Wishlist">
                        <span>🤍</span>
                        <span class="action-badge" id="wishlistBadge" style="display:none;">0</span>
                    </button>

                    <!-- Cart Trigger -->
                    <button class="action-icon-btn" id="cartTrigger" title="View Cart">
                        <span>🛍️</span>
                        <span class="action-badge" id="cartBadge" style="display:none;">0</span>
                        <span class="cart-total-badge" id="cartTotalBadge"></span>
                    </button>

                    <!-- User Profile / Auth Area -->
                    <div id="authActionArea">
                        <button class="btn btn-secondary btn-sm" id="openAuthModalBtn">Sign In</button>
                    </div>
                </div>
            </div>
        </header>

            <!-- ======================== MINIMALIST HERO ======================== -->
        <section class="hero-minimal">
            <div class="container hero-content">
                <span class="hero-subtitle">Curated Collection</span>
                <h1 class="hero-title serif-heading">Stories, crafted thoughts &amp; timeless literature.</h1>
                <p class="hero-description">
                    Explore handpicked engineering classics, philosophical masterworks, and thought-provoking fiction.
                </p>
            </div>
        </section>

            <!-- ======================== GENRE FILTER TRACK ======================== -->
        <nav class="genre-filter-nav">
            <div class="container">
                <div class="genre-filter-track" id="genreFilterTrack">
                    <button class="genre-pill active" data-id="0">All Books</button>
                </div>
            </div>
        </nav>

            <!-- ======================== CATALOG SECTION ======================== -->
        <main class="container catalog-section">
            <div class="catalog-toolbar">
                <div class="results-count" id="resultsCount">
                    Loading catalog...
                </div>
                <div class="toolbar-controls">
                    <label style="font-size:13px; color:var(--text-muted);">Sort by:</label>
                    <select id="sortSelect" class="sort-select">
                        <option value="featured">Featured</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="rating">Top Rated</option>
                        <option value="title">Title: A to Z</option>
                    </select>
                </div>
            </div>

            <!-- Books Grid Container -->
            <div class="book-grid" id="bookGrid">
                <!-- Books dynamically injected by assets/js/app.js -->
            </div>
        </main>