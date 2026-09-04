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