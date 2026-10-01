document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    loadShopOrders();
    loadShopInventory();

    document.getElementById('shopLogoutBtn').addEventListener('click', async () => {
        await fetch('../auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout' })
        });
        window.location.href = '../index.php';
    });

    document.getElementById('inventoryRequestForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            action: 'submit_inventory_request',
            bookid: document.getElementById('reqBookId').value || null,
            action_type: document.getElementById('reqActionType').value,
            title: document.getElementById('reqTitle').value.trim(),
            ISBN: document.getElementById('reqIsbn').value.trim(),
            price: parseFloat(document.getElementById('reqPrice').value),
            stockQuantity: parseInt(document.getElementById('reqStock').value),
            coverImageUrl: document.getElementById('reqCover').value.trim()
        };

        const res = await fetch('../admin/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        showShopAlert(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            closeRequestModal();
        }
    });
});

function setupTabs() {
    document.querySelectorAll('.admin-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.admin-panel').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
        });
    });
}

function showShopAlert(msg, type = 'success') {
    const alert = document.getElementById('shopAlert');
    if (!alert) return;
    alert.textContent = msg;
    alert.className = `alert-box alert-${type}`;
    alert.classList.remove('hidden');
    setTimeout(() => alert.classList.add('hidden'), 4000);
}

async function loadShopOrders() {
    const tbody = document.getElementById('shopOrdersTableBody');
    const res = await fetch('../admin/api.php?action=get_orders');
    const data = await res.json();
    if (data.success && data.orders) {
        if (data.orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4">No orders found.</td></tr>';
            return;
        }
        tbody.innerHTML = data.orders.map(o => {
            const items = (o.items || []).map(i => `${i.title} &times; ${i.quantity}`).join('<br>');
            const addr = o.street ? `${o.no}, ${o.street} (${o.zipCode})` : '';
            const status = o.deliveryStatus || 'Processing';

            return `
                <tr>
                    <td><strong>#${o.orderid}</strong></td>
                    <td style="font-size:12px; color:var(--text-muted);">${o.orderDate}</td>
                    <td>${escapeHtml(o.firstName + ' ' + o.lastName)}</td>
                    <td style="font-size:12px; color:var(--text-muted);">${addr}</td>
                    <td style="font-size:12px;">${items}</td>
                    <td>$${Number(o.subTotal).toFixed(2)}</td>
                    <td><span class="badge ${status==='Delivered'?'badge-success':'badge-neutral'}">${status}</span></td>
                    <td>
                        <select class="form-control" style="padding:4px 8px; font-size:12px;" onchange="updateDeliveryStatus(${o.orderid}, this.value)">
                            <option value="Processing" ${status==='Processing'?'selected':''}>Processing</option>
                            <option value="Dispatched" ${status==='Dispatched'?'selected':''}>Dispatched</option>
                            <option value="Out for Delivery" ${status==='Out for Delivery'?'selected':''}>Out for Delivery</option>
                            <option value="Delivered" ${status==='Delivered'?'selected':''}>Delivered</option>
                        </select>
                    </td>
                </tr>
            `;
        }).join('');
    }
}

async function updateDeliveryStatus(orderId, deliveryStatus) {
    const res = await fetch('../admin/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_delivery_status', orderid: orderId, deliveryStatus })
    });
    const data = await res.json();
    showShopAlert(data.message, data.success ? 'success' : 'error');
}

async function loadShopInventory() {
    const tbody = document.getElementById('shopInventoryTableBody');
    const res = await fetch('../admin/api.php?action=get_books');
    const data = await res.json();
    if (data.success && data.books) {
        tbody.innerHTML = data.books.map(b => `
            <tr>
                <td style="width:50px;"><img src="${b.coverImageUrl}" class="table-cover-thumb"></td>
                <td>
                    <div style="font-weight:600;">${escapeHtml(b.title)}</div>
                    <div style="font-size:11px; color:var(--text-muted);">ISBN: ${escapeHtml(b.ISBN)}</div>
                </td>
                <td>$${Number(b.price).toFixed(2)}</td>
                <td>${b.stockQuantity}</td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick='openRequestModal("update", ${JSON.stringify(b)})'>Request Change</button>
                </td>
            </tr>
        `).join('');
    }
}

function openRequestModal(type, book = null) {
    document.getElementById('requestModal').classList.remove('hidden');
    document.getElementById('reqActionType').value = type;
    if (type === 'update' && book) {
        document.getElementById('requestModalTitle').textContent = `Request Change for: ${book.title}`;
        document.getElementById('reqBookId').value = book.bookid;
        document.getElementById('reqTitle').value = book.title;
        document.getElementById('reqIsbn').value = book.ISBN;
        document.getElementById('reqPrice').value = book.price;
        document.getElementById('reqStock').value = book.stockQuantity;
        document.getElementById('reqCover').value = book.coverImageUrl || '';
    } else {
        document.getElementById('requestModalTitle').textContent = 'Request New Book Addition';
        document.getElementById('inventoryRequestForm').reset();
        document.getElementById('reqBookId').value = '';
        document.getElementById('reqActionType').value = 'add';
    }
}

function closeRequestModal() {
    document.getElementById('requestModal').classList.add('hidden');
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}