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

