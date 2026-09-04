/**
 * BookStore - Minimalist Frontend Application Engine
 * Vanilla JavaScript (ES6+) - Zero Frameworks
 */

// Application Global State
const state = {
    user: null,               // null if guest, or user object
    guestCart: [],            // [{bookid, quantity}] in localStorage
    guestWishlist: [],        // [bookid] in localStorage
    activeCart: [],           // Hydrated cart items
    activeWishlist: [],       // Hydrated wishlist items
    genres: [],
    books: [],
    currentGenre: 0,
    searchQuery: '',
    currentSort: 'featured',
    appliedPromo: null,       // {code, type, price, discountAmount}
    selectedAddressId: null,
    isCheckingOut: false      // flag to resume checkout after auth
};

document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

async function initApp() {
    loadLocalGuestData();
    setupEventListeners();
    await checkAuthStatus();
    await Promise.all([
        loadGenres(),
        loadBooks()
    ]);
    await refreshCart();
    await refreshWishlist();
}

// ======================== LOCAL STORAGE (GUEST) ========================
function loadLocalGuestData() {
    try {
        state.guestCart = JSON.parse(localStorage.getItem('guest_cart')) || [];
    } catch (e) {
        state.guestCart = [];
    }
    try {
        state.guestWishlist = JSON.parse(localStorage.getItem('guest_wishlist')) || [];
    } catch (e) {
        state.guestWishlist = [];
    }
}

function saveLocalGuestCart() {
    localStorage.setItem('guest_cart', JSON.stringify(state.guestCart));
}

function saveLocalGuestWishlist() {
    localStorage.setItem('guest_wishlist', JSON.stringify(state.guestWishlist));
}

// ======================== AUTHENTICATION & SYNC ========================
async function checkAuthStatus() {
    try {
        const res = await fetch('auth.php?action=status');
        const data = await res.json();
        if (data.success && data.loggedIn) {
            state.user = data.user;
        } else {
            state.user = null;
        }
        renderHeaderUserUI();
    } catch (e) {
        console.error('Failed to check auth status', e);
    }
}

function renderHeaderUserUI() {
    const authActionArea = document.getElementById('authActionArea');
    if (!authActionArea) return;

    if (state.user) {
        const initials = (state.user.firstName.charAt(0) + state.user.lastName.charAt(0)).toUpperCase();
        authActionArea.innerHTML = `
            <div class="user-menu-container">
                <button class="user-profile-btn" id="userMenuTrigger">
                    <span class="user-avatar">${initials}</span>
                    <span>${escapeHtml(state.user.firstName)}</span>
                    <span style="font-size:10px;">▼</span>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <div class="dropdown-header">
                        <div class="dropdown-user-name">${escapeHtml(state.user.firstName + ' ' + state.user.lastName)}</div>
                        <div class="dropdown-user-email">${escapeHtml(state.user.email)}</div>
                    </div>
                    ${state.user.isAdmin ? `<a href="admin/dashboard.php" class="dropdown-item">⚙️ Admin Control</a>` : ''}
                    <button class="dropdown-item" id="navLogoutBtn">🚪 Sign Out</button>
                </div>
            </div>
        `;

        const trigger = document.getElementById('userMenuTrigger');
        const dropdown = document.getElementById('userDropdown');
        if (trigger && dropdown) {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('show');
            });
            document.addEventListener('click', () => dropdown.classList.remove('show'));
        }

        const logoutBtn = document.getElementById('navLogoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', handleLogout);
        }
    } else {
        authActionArea.innerHTML = `
            <button class="btn btn-secondary btn-sm" id="openAuthModalBtn">Sign In</button>
        `;
        document.getElementById('openAuthModalBtn').addEventListener('click', () => openAuthModal('login'));
    }
}

async function handleLogout() {
    try {
        await fetch('auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout' })
        });
        state.user = null;
        renderHeaderUserUI();
        showToast('Signed out successfully.');
        await refreshCart();
        await refreshWishlist();
    } catch (e) {
        console.error('Logout error', e);
    }
}

// "The Merge" - Sync local storage to database after authentication
async function performGuestSync() {
    loadLocalGuestData();
    if (state.guestCart.length === 0 && state.guestWishlist.length === 0) {
        return;
    }

    try {
        const res = await fetch('api/sync_local.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                guest_cart: state.guestCart,
                guest_wishlist: state.guestWishlist
            })
        });
        const data = await res.json();
        if (data.success) {
            localStorage.removeItem('guest_cart');
            localStorage.removeItem('guest_wishlist');
            state.guestCart = [];
            state.guestWishlist = [];
            showToast('Your saved items have been synchronized.');
        }
    } catch (e) {
        console.error('Sync failed', e);
    }
}

// ======================== CATALOG & SEARCH ========================
async function loadGenres() {
    try {
        const res = await fetch('api/books.php?action=genres');
        const data = await res.json();
        if (data.success && data.genres) {
            state.genres = data.genres;
            renderGenrePills();
        }
    } catch (e) {
        console.error('Error loading genres', e);
    }
}

function renderGenrePills() {
    const track = document.getElementById('genreFilterTrack');
    if (!track) return;

    let html = `<button class="genre-pill ${state.currentGenre === 0 ? 'active' : ''}" data-id="0">All Books</button>`;
    state.genres.forEach(g => {
        html += `<button class="genre-pill ${state.currentGenre === g.genreid ? 'active' : ''}" data-id="${g.genreid}">${escapeHtml(g.genreName)}</button>`;
    });
    track.innerHTML = html;

    track.querySelectorAll('.genre-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            state.currentGenre = parseInt(btn.dataset.id);
            track.querySelectorAll('.genre-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            loadBooks();
        });
    });
}

async function loadBooks() {
    const grid = document.getElementById('bookGrid');
    if (!grid) return;

    grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 40px 0; color:var(--text-muted);">Loading books...</div>';

    let url = `api/books.php?sort=${encodeURIComponent(state.currentSort)}`;
    if (state.currentGenre > 0) {
        url += `&genre=${state.currentGenre}`;
    }
    if (state.searchQuery.trim()) {
        url += `&search=${encodeURIComponent(state.searchQuery.trim())}`;
    }

    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data.success) {
            state.books = data.books || [];
            renderBooks();
        }
    } catch (e) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding: 40px 0; color:var(--danger);">Failed to load books.</div>';
    }
}

function renderBooks() {
    const grid = document.getElementById('bookGrid');
    const countEl = document.getElementById('resultsCount');
    if (!grid) return;

    if (countEl) {
        countEl.innerHTML = `Showing <strong>${state.books.length}</strong> title${state.books.length === 1 ? '' : 's'}`;
    }

    if (state.books.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align:center; padding: 60px 20px;">
                <div style="font-size:32px; margin-bottom:12px;">📖</div>
                <h3 style="font-size:18px; font-weight:700; margin-bottom:6px;">No books found</h3>
                <p style="color:var(--text-muted); font-size:14px;">Try refining your search terms or genre filter.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = state.books.map(b => {
        const isWishlisted = isBookInWishlist(b.bookid);
        const isOutOfStock = b.stockQuantity <= 0;

        return `
            <div class="book-card" data-id="${b.bookid}">
                <div class="book-card-media" onclick="openBookDetailsModal(${b.bookid})">
                    <img src="${b.coverImageUrl || 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=600&auto=format&fit=crop&q=80'}" alt="${escapeHtml(b.title)}" class="book-card-cover" loading="lazy">
                    <button class="book-wishlist-btn ${isWishlisted ? 'active' : ''}" onclick="event.stopPropagation(); toggleWishlist(${b.bookid})" title="Wishlist">
                        ${isWishlisted ? '❤️' : '🤍'}
                    </button>
                    ${isOutOfStock ? `<span class="stock-tag out-of-stock">Out of Stock</span>` : ''}
                </div>
                <div class="book-card-body">
                    <div class="book-genres">${escapeHtml(b.genres || 'Literature')}</div>
                    <h3 class="book-title" onclick="openBookDetailsModal(${b.bookid})">${escapeHtml(b.title)}</h3>
                    <div class="book-authors">${escapeHtml(b.authors || 'Unknown Author')}</div>
                    <div class="book-rating">
                        <span class="star-icon">★</span>
                        <strong style="color:var(--text-main);">${b.avgRating > 0 ? b.avgRating : 'New'}</strong>
                        ${b.reviewCount > 0 ? `<span>(${b.reviewCount})</span>` : ''}
                    </div>
                    <div class="book-card-footer">
                        <div class="book-price">$${Number(b.price).toFixed(2)}</div>
                        <button class="btn btn-secondary btn-sm" ${isOutOfStock ? 'disabled' : ''} onclick="addToCart(${b.bookid})">
                            ${isOutOfStock ? 'Sold Out' : '+ Add'}
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ======================== CART LOGIC ========================
async function addToCart(bookId) {
    if (state.user) {
        try {
            const res = await fetch('api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'add', bookid: bookId, quantity: 1 })
            });
            const data = await res.json();
            if (data.success) {
                showToast('Added to cart.');
                await refreshCart();
            } else {
                showToast(data.message || 'Could not add to cart.', 'error');
            }
        } catch (e) {
            showToast('Error adding to cart.', 'error');
        }
    } else {
        // Guest cart
        loadLocalGuestData();
        const existing = state.guestCart.find(i => i.bookid == bookId);
        if (existing) {
            existing.quantity += 1;
        } else {
            state.guestCart.push({ bookid: bookId, quantity: 1 });
        }
        saveLocalGuestCart();
        showToast('Added to cart.');
        await refreshCart();
    }
}

async function updateCartQty(bookId, delta) {
    if (state.user) {
        const item = state.activeCart.find(i => i.bookid == bookId);
        if (!item) return;
        const newQty = item.quantity + delta;
        try {
            const res = await fetch('api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update', bookid: bookId, quantity: newQty })
            });
            const data = await res.json();
            if (data.success) {
                await refreshCart();
            } else {
                showToast(data.message || 'Update failed', 'error');
            }
        } catch (e) {
            showToast('Error updating cart', 'error');
        }
    } else {
        loadLocalGuestData();
        const item = state.guestCart.find(i => i.bookid == bookId);
        if (!item) return;
        item.quantity += delta;
        if (item.quantity <= 0) {
            state.guestCart = state.guestCart.filter(i => i.bookid != bookId);
        }
        saveLocalGuestCart();
        await refreshCart();
    }
}

async function removeFromCart(bookId) {
    if (state.user) {
        try {
            const res = await fetch('api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'remove', bookid: bookId })
            });
            await refreshCart();
        } catch (e) {
            showToast('Failed to remove item', 'error');
        }
    } else {
        loadLocalGuestData();
        state.guestCart = state.guestCart.filter(i => i.bookid != bookId);
        saveLocalGuestCart();
        await refreshCart();
    }
}

async function refreshCart() {
    if (state.user) {
        try {
            const res = await fetch('api/cart.php?action=get');
            const data = await res.json();
            if (data.success) {
                state.activeCart = data.items || [];
                renderCartUI(data.subTotal || 0, data.count || 0);
            }
        } catch (e) {
            console.error('Error fetching auth cart', e);
        }
    } else {
        loadLocalGuestData();
        if (state.guestCart.length === 0) {
            state.activeCart = [];
            renderCartUI(0, 0);
            return;
        }
        try {
            const res = await fetch('api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'hydrate', items: state.guestCart })
            });
            const data = await res.json();
            if (data.success) {
                state.activeCart = data.items || [];
                renderCartUI(data.subTotal || 0, data.count || 0);
            }
        } catch (e) {
            console.error('Error hydrating guest cart', e);
        }
    }
}

function renderCartUI(subTotal, count) {
    // Badges
    const badge = document.getElementById('cartBadge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }

    const priceBadge = document.getElementById('cartTotalBadge');
    if (priceBadge) {
        priceBadge.textContent = count > 0 ? `$${Number(subTotal).toFixed(2)}` : '';
    }

    // Drawer content
    const container = document.getElementById('cartDrawerItems');
    const subtotalEl = document.getElementById('cartDrawerSubtotal');
    if (!container) return;

    if (subtotalEl) {
        subtotalEl.textContent = `$${Number(subTotal).toFixed(2)}`;
    }

    if (state.activeCart.length === 0) {
        container.innerHTML = `
            <div class="drawer-empty">
                <div class="drawer-empty-icon">🛍️</div>
                <div style="font-weight:600; font-size:15px; margin-bottom:4px;">Your cart is empty</div>
                <div style="font-size:13px;">Find something inspiring to read.</div>
            </div>
        `;
        return;
    }

    container.innerHTML = state.activeCart.map(item => `
        <div class="drawer-item">
            <img src="${item.coverImageUrl || 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=600&auto=format&fit=crop&q=80'}" alt="${escapeHtml(item.title)}" class="drawer-item-thumb">
            <div class="drawer-item-info">
                <div>
                    <div class="drawer-item-title">${escapeHtml(item.title)}</div>
                    <div class="drawer-item-price">$${Number(item.price).toFixed(2)}</div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="qty-control">
                        <button class="qty-btn" onclick="updateCartQty(${item.bookid}, -1)">−</button>
                        <span class="qty-val">${item.quantity}</span>
                        <button class="qty-btn" onclick="updateCartQty(${item.bookid}, 1)">+</button>
                    </div>
                    <button class="drawer-item-remove" onclick="removeFromCart(${item.bookid})">Remove</button>
                </div>
            </div>
        </div>
    `).join('');
}

// ======================== WISHLIST LOGIC ========================
function isBookInWishlist(bookId) {
    if (state.user) {
        return state.activeWishlist.some(i => i.bookid == bookId);
    } else {
        return state.guestWishlist.includes(bookId);
    }
}

async function toggleWishlist(bookId) {
    if (state.user) {
        try {
            const res = await fetch('api/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle', bookid: bookId })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message);
                await refreshWishlist();
                renderBooks();
            }
        } catch (e) {
            showToast('Error updating wishlist.', 'error');
        }
    } else {
        loadLocalGuestData();
        const index = state.guestWishlist.indexOf(bookId);
        if (index > -1) {
            state.guestWishlist.splice(index, 1);
            showToast('Removed from wishlist.');
        } else {
            state.guestWishlist.push(bookId);
            showToast('Added to wishlist.');
        }
        saveLocalGuestWishlist();
        await refreshWishlist();
        renderBooks();
    }
}

