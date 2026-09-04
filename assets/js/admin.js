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
