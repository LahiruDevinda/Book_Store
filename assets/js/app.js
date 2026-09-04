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