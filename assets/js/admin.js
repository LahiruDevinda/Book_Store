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

// Setup tab navigation for the admin dashboard
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

// Display an alert message in the admin dashboard
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

// Load and display statistics on the admin dashboard
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

// Load and display the inventory of books in the admin dashboard
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

// Open the edit book modal and populate it with the selected book's data
window.openEditModal = function(bookId) {
    const book = cachedBooks.find(b => b.bookid == bookId);
    if (!book) return;

    document.getElementById('editBookId').value = book.bookid;
    document.getElementById('editBookTitleLabel').textContent = book.title;
    document.getElementById('editBookPrice').value = book.price;
    document.getElementById('editBookStock').value = book.stockQuantity;

    document.getElementById('editBookModal').classList.remove('hidden');
};

// Delete a book from the inventory after confirmation
window.deleteBook = async function(bookId, title) {
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


