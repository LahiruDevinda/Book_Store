<?php
require_once __DIR__ . '/config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid'])) {
    header('Location: index.php');
    exit;
}

$user =$_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - BookStore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="brand-logo">
                <span class="brand-icon">📚</span>
                <span class="brand-name">BookStore<span class="brand-dot">.</span></span>
            </a>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary btn-sm">Return to Storefront</a>
            </div>
        </div>
    </header>

    <main class="container" style="padding: 40px 20px; max-width: 800px;">
        <h1 style="font-size: 24px; font-weight: 700; margin-bottom: 24px;">My Account Dashboard</h1>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <!-- Personal Info Form -->
            <div class="card p-4">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;">Personal Information</h3>
                <form id="updateProfileForm">
                    <div class="form-group-row">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" id="profileFirstName" class="form-control" value="<?= htmlspecialchars($user['firstName']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" id="profileLastName" class="form-control" value="<?= htmlspecialchars($user['lastName']); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" disabled style="background: var(--bg-subtle);">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Update Profile</button>
                </form>
            </div>

            <!-- Promo Codes Section -->
            <div class="card p-4">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 16px;">My Active Promo Codes</h3>
                <div id="userPromosList" style="display: flex; flex-direction: column; gap: 10px; max-height: 250px; overflow-y: auto;">
                    <div style="font-size: 13px; color: var(--text-muted);">Loading promo codes...</div>
                </div>
            </div>
        </div>

        <!-- Addresses Management -->
        <div class="card p-4" style="margin-top: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 16px; font-weight: 700;">Delivery Addresses</h3>
                <button class="btn btn-secondary btn-sm" onclick="openAddressModal()">+ Add New Address</button>
            </div>
            <div id="userAddressList" style="display: flex; flex-direction: column; gap: 12px;">
                <div style="font-size: 13px; color: var(--text-muted);">Loading addresses...</div>
            </div>
        </div>
    </main>

    <!-- Address Modal -->
    <div id="addressModal" class="modal-overlay hidden">
        <div class="modal-dialog" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title" id="addressModalTitle">Add Address</h3>
                <button class="modal-close" onclick="closeAddressModal()">&times;</button>
            </div>
            <form id="addressForm" class="modal-body">
                <input type="hidden" id="editAddressId">
                <div class="form-group-row">
                    <div class="form-group">
                        <label class="form-label">Unit / No *</label>
                        <input type="text" id="modalAddrNo" class="form-control" required placeholder="Apt 4B">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Postal Code *</label>
                        <input type="text" id="modalAddrZip" class="form-control" required placeholder="10001">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Street Address *</label>
                    <input type="text" id="modalAddrStreet" class="form-control" required placeholder="123 Main Street">
                </div>
                <div class="modal-footer" style="padding: 0; margin-top: 20px; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeAddressModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Address</button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadAddressesList();
            loadUserPromos();

            document.getElementById('updateProfileForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const firstName = document.getElementById('profileFirstName').value.trim();
                const lastName = document.getElementById('profileLastName').value.trim();

                const res = await fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_profile', firstName, lastName })
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
            });

            document.getElementById('addressForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const addressid = document.getElementById('editAddressId').value;
                const no = document.getElementById('modalAddrNo').value.trim();
                const zipCode = document.getElementById('modalAddrZip').value.trim();
                const street = document.getElementById('modalAddrStreet').value.trim();

                const payload = { action: addressid ? 'update' : 'add', no, zipCode, street };
                if (addressid) payload.addressid = addressid;

                const res = await fetch('api/addresses.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    closeAddressModal();
                    loadAddressesList();
                } else {
                    alert(data.message || 'Operation failed.');
                }
            });
        });

        async function loadAddressesList() {
            const container = document.getElementById('userAddressList');
            const res = await fetch('api/addresses.php');
            const data = await res.json();
            if (data.success && data.addresses) {
                if (data.addresses.length === 0) {
                    container.innerHTML = '<div style="font-size:13px; color:var(--text-muted);">No addresses saved yet.</div>';
                    return;
                }
                container.innerHTML = data.addresses.map(a => `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px; background:var(--bg-canvas); border:1px solid var(--border-color); border-radius:var(--radius-sm);">
                        <div style="font-size:13px; line-height:1.4;">
                            <strong>${escapeHtml(a.no)}, ${escapeHtml(a.street)}</strong>
                            <div style="color:var(--text-muted);">Postal Code: ${escapeHtml(a.zipCode)}</div>
                        </div>
                        <button class="btn btn-secondary btn-sm" onclick='openAddressModal(${JSON.stringify(a)})'>Edit</button>
                    </div>
                `).join('');
            }
        }

        async function loadUserPromos() {
            const container = document.getElementById('userPromosList');
            const res = await fetch('api/promos.php?action=get_user_promos');
            const data = await res.json();
            if (data.success && data.promos) {
                if (data.promos.length === 0) {
                    container.innerHTML = '<div style="font-size:13px; color:var(--text-muted);">No active promo codes available.</div>';
                    return;
                }
                container.innerHTML = data.promos.map(p => `
                    <div style="padding:10px 12px; background:var(--success-light); border:1px solid #bbf7d0; border-radius:var(--radius-sm);">
                        <strong style="color:var(--success); font-family:monospace; font-size:14px;">${escapeHtml(p.code)}</strong>
                        <div style="font-size:11px; color:var(--text-muted);">Value: ${p.type === 'percentage' ? p.price + '%' : '$' + p.price} OFF (Exp: ${p.exp_date})</div>
                    </div>
                `).join('');
            }
        }

        function openAddressModal(address = null) {
            document.getElementById('addressModal').classList.remove('hidden');
            if (address) {
                document.getElementById('addressModalTitle').textContent = 'Edit Address';
                document.getElementById('editAddressId').value = address.addressid;
                document.getElementById('modalAddrNo').value = address.no;
                document.getElementById('modalAddrZip').value = address.zipCode;
                document.getElementById('modalAddrStreet').value = address.street;
            } else {
                document.getElementById('addressModalTitle').textContent = 'Add Address';
                document.getElementById('addressForm').reset();
                document.getElementById('editAddressId').value = '';
            }
        }

        function closeAddressModal() {
            document.getElementById('addressModal').classList.add('hidden');
        }

        function showToast(msg, type = 'normal') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast ${type === 'error' ? 'toast-error' : ''}`;
            toast.textContent = msg;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    </script>
</body>
</html>