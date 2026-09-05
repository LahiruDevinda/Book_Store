// assets/js/admin.js 
document.addEventListener('DOMContentLoaded', () => {
  initAdmin();
});

// Initialize the admin dashboard functionalities
function initAdmin() {
  setupTabs();
  loadStats();
  loadInventory();
  loadOrders();
  loadUsersForPromoDropdown();
  setupPromoForm();
  loadTaxonomies();
  setupForms();

  const logoutBtn = document.getElementById('adminLogoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      await fetch('../auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' })
      });
      window.location.href = '../index.php';
    });
  }
}

// ======================== TABS ========================
function setupTabs() {
  const tabs = document.querySelectorAll('.admin-tab');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.admin-panel').forEach(p => p.classList.remove('active'));

      tab.classList.add('active');
      const target = document.getElementById('tab-' + tab.dataset.tab);
      if (target) {
        target.classList.add('active');
      }
    });
  });
}

function showAdminAlert(msg, type = 'success') {
  const alert = document.getElementById('adminAlert');
  if (!alert) return;
  alert.textContent = msg;
  alert.className = `alert-box alert-${type}`;
  alert.classList.remove('hidden');
  setTimeout(() => {
    alert.classList.add('hidden');
  }, 4000);
}

// ======================== STATS ========================
async function loadStats() {
  try {
    const res = await fetch('api.php?action=stats');
    const data = await res.json();
    if (data.success && data.stats) {
      document.getElementById('statTotalBooks').textContent = data.stats.totalBooks;
      document.getElementById('statTotalStock').textContent = data.stats.totalStock;
      document.getElementById('statTotalOrders').textContent = data.stats.totalOrders;
      document.getElementById('statTotalRevenue').textContent = '$' + Number(data.stats.totalRevenue).toFixed(2);
    }
  } catch (e) {
    console.error('Error loading stats', e);
  }
}

// ======================== INVENTORY ========================
let cachedBooks = [];
async function loadInventory() {
  const tbody = document.getElementById('inventoryTableBody');
  try {
    const res = await fetch('api.php?action=get_books');
    const data = await res.json();
    if (data.success && data.books) {
      cachedBooks = data.books;
      if (cachedBooks.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No books in catalog yet.</td></tr>';
        return;
      }

      tbody.innerHTML = cachedBooks.map(b => `
                <tr>
                    <td style="width:60px;">
                        <img src="${b.coverImageUrl || 'https://via.placeholder.com/60x80'}" alt="${b.title}" class="table-cover-thumb">
                    </td>
                    <td>
                        <div style="font-weight:600; color:var(--text-main);">${escapeHtml(b.title)}</div>
                        <div style="font-size:12px; color:var(--text-muted);">ISBN: ${escapeHtml(b.ISBN)}</div>
                    </td>
                    <td style="font-size:13px; color:var(--text-muted);">${escapeHtml(b.authors || 'None')}</td>
                    <td style="font-size:13px; color:var(--text-muted);">${escapeHtml(b.genres || 'None')}</td>
                    <td style="font-weight:600;">$${Number(b.price).toFixed(2)}</td>
                    <td>
                        <span class="badge ${b.stockQuantity < 10 ? 'badge-warning' : 'badge-neutral'}">
                            ${b.stockQuantity} in stock
                        </span>
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button class="btn btn-secondary btn-sm" onclick="openEditModal(${b.bookid})">Edit</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteBook(${b.bookid}, '${escapeJs(b.title)}')">Delete</button>
                        </div>
                    </td>
                </tr>
            `).join('');
    }
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load inventory.</td></tr>';
  }
}

// Edit Modal
window.openEditModal = function (bookId) {
  const book = cachedBooks.find(b => b.bookid == bookId);
  if (!book) return;

  document.getElementById('editBookId').value = book.bookid;
  document.getElementById('editBookTitleLabel').textContent = book.title;
  document.getElementById('editBookPrice').value = book.price;
  document.getElementById('editBookStock').value = book.stockQuantity;

  document.getElementById('editBookModal').classList.remove('hidden');
};

window.deleteBook = async function (bookId, title) {
  if (!confirm(`Are you sure you want to delete "${title}"? This cannot be undone.`)) {
    return;
  }
  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete_book', bookid: bookId })
    });
    const data = await res.json();
    if (data.success) {
      showAdminAlert('Book deleted successfully.');
      loadInventory();
      loadStats();
    } else {
      alert(data.message || 'Failed to delete book.');
    }
  } catch (e) {
    alert('An error occurred.');
  }
};

// ======================== ORDERS AUDIT ========================
async function loadOrders() {
  const tbody = document.getElementById('ordersTableBody');
  try {
    const res = await fetch('api.php?action=get_orders');
    const data = await res.json();
    if (data.success && data.orders) {
      if (data.orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4">No customer orders yet.</td></tr>';
        return;
      }

      tbody.innerHTML = data.orders.map(o => {
        const itemsHtml = (o.items || []).map(it => `
                    <div style="font-size:12px; margin-bottom:4px; line-height:1.4;">
                        <strong>${escapeHtml(it.title || 'Book #' + it.bookid)}</strong> &times; ${it.quantity}
                        <span style="color:var(--text-muted); font-size:11px;">(locked @ $${Number(it.unitPrice).toFixed(2)})</span>
                    </div>
                `).join('');

        const addr = o.street ? `${escapeHtml(o.no)}, ${escapeHtml(o.street)} (${escapeHtml(o.zipCode)})` : 'Default Address';

        return `
                    <tr>
                        <td style="font-weight:700;">#${o.orderid}</td>
                        <td style="font-size:12px; color:var(--text-muted); white-space:nowrap;">${o.orderDate || 'Recent'}</td>
                        <td>
                            <div style="font-weight:600;">${escapeHtml(o.firstName + ' ' + o.lastName)}</div>
                            <div style="font-size:12px; color:var(--text-muted);">${escapeHtml(o.email)}</div>
                        </td>
                        <td style="font-size:12px; color:var(--text-muted); max-width:180px;">${addr}</td>
                        <td>${itemsHtml}</td>
                        <td style="font-weight:700; font-size:15px; color:var(--text-main);">$${Number(o.subTotal).toFixed(2)}</td>
                        <td>
                            <span class="badge badge-neutral">${o.paymentMethod || 'COD'}</span>
                        </td>
                        <td>
                            <span class="badge badge-success">${o.orderStatus}</span>
                        </td>
                    </tr>
                `;
      }).join('');
    }
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Failed to load orders.</td></tr>';
  }
}

// ======================== TAXONOMIES ========================
let cachedAuthors = [];
let cachedGenres = [];

async function loadTaxonomies() {
  try {
    const [authRes, genRes] = await Promise.all([
      fetch('api.php?action=get_authors'),
      fetch('api.php?action=get_genres')
    ]);
    const authData = await authRes.json();
    const genData = await genRes.json();

    if (authData.success) {
      cachedAuthors = authData.authors;
      renderAuthorsList();
      renderModalAuthors();
    }

    if (genData.success) {
      cachedGenres = genData.genres;
      renderGenresList();
      renderModalGenres();
    }
  } catch (e) {
    console.error('Failed to load taxonomies', e);
  }
}

function renderAuthorsList() {
  const list = document.getElementById('authorsList');
  if (!list) return;
  list.innerHTML = cachedAuthors.map(a => `
        <div style="padding:8px 12px; background:var(--bg-canvas); border-radius:6px; font-size:13px; border:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
            <strong>${escapeHtml(a.name)}</strong>
            <span style="font-size:11px; color:var(--text-muted);">#${a.authorid}</span>
        </div>
    `).join('');
}

function renderGenresList() {
  const list = document.getElementById('genresList');
  if (!list) return;
  list.innerHTML = cachedGenres.map(g => `
        <span class="genre-tag">${escapeHtml(g.genreName)}</span>
    `).join('');
}

function renderModalAuthors() {
  const container = document.getElementById('bookModalAuthors');
  if (!container) return;
  container.innerHTML = cachedAuthors.map(a => `
        <label class="checkbox-pill">
            <input type="checkbox" name="authorIds[]" value="${a.authorid}">
            <span>${escapeHtml(a.name)}</span>
        </label>
    `).join('');
}

function renderModalGenres() {
  const container = document.getElementById('bookModalGenres');
  if (!container) return;
  container.innerHTML = cachedGenres.map(g => `
        <label class="checkbox-pill">
            <input type="checkbox" name="genreIds[]" value="${g.genreid}">
            <span>${escapeHtml(g.genreName)}</span>
        </label>
    `).join('');
}

// ======================== FORMS & MODALS ========================
function setupForms() {
  // Add Book Modal triggers
  const addBtn = document.getElementById('openAddBookModalBtn');
  const addModal = document.getElementById('addBookModal');
  const closeAddBtn = document.getElementById('closeAddBookModal');
  const cancelAddBtn = document.getElementById('cancelAddBookBtn');

  if (addBtn) addBtn.addEventListener('click', () => addModal.classList.remove('hidden'));
  if (closeAddBtn) closeAddBtn.addEventListener('click', () => addModal.classList.add('hidden'));
  if (cancelAddBtn) cancelAddBtn.addEventListener('click', () => addModal.classList.add('hidden'));

  // Edit Book Modal triggers
  const editModal = document.getElementById('editBookModal');
  const closeEditBtn = document.getElementById('closeEditBookModal');
  const cancelEditBtn = document.getElementById('cancelEditBookBtn');

  if (closeEditBtn) closeEditBtn.addEventListener('click', () => editModal.classList.add('hidden'));
  if (cancelEditBtn) cancelEditBtn.addEventListener('click', () => editModal.classList.add('hidden'));

  // Submit Add Book
  const addBookForm = document.getElementById('addBookForm');
  if (addBookForm) {
    addBookForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(addBookForm);
      const authorIds = Array.from(addBookForm.querySelectorAll('input[name="authorIds[]"]:checked')).map(cb => parseInt(cb.value));
      const genreIds = Array.from(addBookForm.querySelectorAll('input[name="genreIds[]"]:checked')).map(cb => parseInt(cb.value));

      const payload = {
        action: 'add_book',
        title: formData.get('title'),
        ISBN: formData.get('ISBN'),
        price: parseFloat(formData.get('price')),
        stockQuantity: parseInt(formData.get('stockQuantity')),
        coverImageUrl: formData.get('coverImageUrl'),
        authorIds: authorIds,
        genreIds: genreIds
      };

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          showAdminAlert('Book saved successfully!');
          addBookForm.reset();
          addModal.classList.add('hidden');
          loadInventory();
          loadStats();
        } else {
          alert(data.message || 'Failed to add book.');
        }
      } catch (err) {
        alert('An error occurred.');
      }
    });
  }

  // Submit Quick Edit Book
  const editBookForm = document.getElementById('editBookForm');
  if (editBookForm) {
    editBookForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const bookId = parseInt(document.getElementById('editBookId').value);
      const price = parseFloat(document.getElementById('editBookPrice').value);
      const stock = parseInt(document.getElementById('editBookStock').value);

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'update_book',
            bookid: bookId,
            price: price,
            stockQuantity: stock
          })
        });
        const data = await res.json();
        if (data.success) {
          showAdminAlert('Book updated!');
          editModal.classList.add('hidden');
          loadInventory();
          loadStats();
        } else {
          alert(data.message || 'Update failed.');
        }
      } catch (err) {
        alert('An error occurred.');
      }
    });
  }

  // Submit Add Author
  const addAuthorForm = document.getElementById('addAuthorForm');
  if (addAuthorForm) {
    addAuthorForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = document.getElementById('newAuthorName').value.trim();
      const bio = document.getElementById('newAuthorBio').value.trim();
      if (!name) return;

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add_author', name: name, biography: bio })
        });
        const data = await res.json();
        if (data.success) {
          showAdminAlert('Author added!');
          addAuthorForm.reset();
          loadTaxonomies();
        } else {
          alert(data.message || 'Failed to add author.');
        }
      } catch (err) {
        alert('An error occurred.');
      }
    });
  }

  // Submit Add Genre
  const addGenreForm = document.getElementById('addGenreForm');
  if (addGenreForm) {
    addGenreForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = document.getElementById('newGenreName').value.trim();
      if (!name) return;

      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'add_genre', genreName: name })
        });
        const data = await res.json();
        if (data.success) {
          showAdminAlert('Genre added!');
          addGenreForm.reset();
          loadTaxonomies();
        } else {
          alert(data.message || 'Failed to add genre.');
        }
      } catch (err) {
        alert('An error occurred.');
      }
    });
  }
}

async function loadUsersForPromoDropdown() {
  const select = document.getElementById('promoUserId');
  if (!select) return;

  try {
    const res = await fetch('../api/promos.php?action=get_users');
    const data = await res.json();
    if (data.success && data.users) {
      select.innerHTML = '<option value="">-- Choose a Customer --</option>' + 
        data.users.map(u => `<option value="${u.userid}">${escapeHtml(u.firstName + ' ' + u.lastName)} (${escapeHtml(u.email)})</option>`).join('');
    } else {
      select.innerHTML = '<option value="">Failed to load users</option>';
    }
  } catch (e) {
    select.innerHTML = '<option value="">Error loading users</option>';
  }
}

function setupPromoForm() {
  const form = document.getElementById('assignPromoForm');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const payload = {
      action: 'assign_promo',
      userid: parseInt(document.getElementById('promoUserId').value),
      code: document.getElementById('promoCodeString').value.trim(),
      type: document.getElementById('promoType').value,
      price: parseFloat(document.getElementById('promoValue').value),
      exp_date: document.getElementById('promoExpDate').value
    };

    try {
      const res = await fetch('../api/promos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.success) {
        showAdminAlert(data.message, 'success');
        form.reset();
        loadUsersForPromoDropdown();
      } else {
        alert(data.message || 'Failed to assign promo code.');
      }
    } catch (err) {
      alert('An error occurred while communicating with the server.');
    }
  });
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function escapeJs(str) {
  if (!str) return '';
  return String(str).replace(/'/g, "\\'");
}
