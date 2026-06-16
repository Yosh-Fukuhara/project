<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'orders';
$pageTitle = 'Manage Orders - Admin';

$pdo = get_db_connection();
$success = '';
$error = '';

// Ensure the orders table has a status column
try {
    $check = $pdo->query("SHOW COLUMNS FROM orders LIKE 'status'");
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN status VARCHAR(50) DEFAULT 'pending' AFTER payment_details");
    }
} catch (PDOException $e) {
    // Ignore errors if column already exists
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $order_id = (int)$_POST['order_id'];
        $status = trim($_POST['status']);
        
        try {
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
            $stmt->execute([$status, $order_id]);
            $success = 'Order status updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating order: ' . $e->getMessage();
        }
    } elseif ($action === 'delete' && isset($_POST['order_id'])) {
        $order_id = (int)$_POST['order_id'];
        try {
            $stmt = $pdo->prepare('DELETE FROM orders WHERE order_id = ?');
            $stmt->execute([$order_id]);
            $success = 'Order deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting order: ' . $e->getMessage();
        }
    }
}

// Fetch all orders with user info
$orders = $pdo->query('SELECT o.*, u.first_name, u.last_name, u.email FROM orders o JOIN users u ON o.user_id = u.user_id ORDER BY o.created_at DESC')->fetchAll();
?>

<?php include __DIR__ . '/partials/top.php'; ?>
<?php include __DIR__ . '/partials/sidebar.php'; ?>

<!-- Mobile drawer -->
<div id="adminMobileOverlay" class="fixed inset-0 bg-black/40 hidden z-40 md:hidden"></div>
<div id="adminMobileDrawer" class="fixed top-0 right-0 h-full w-72 bg-white z-50 translate-x-full transition-transform md:hidden border-l border-slate-200">
    <div class="p-5 border-b border-slate-200">
        <p class="font-extrabold text-slate-900">Admin Panel</p>
        <p class="text-xs text-slate-500">CyberSphere</p>
    </div>
    <div class="p-3 space-y-1">
        <a href="dashboard.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Dashboard</a>
        <a href="users.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Users</a>
        <a href="employers.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Employers</a>
        <a href="product_categories.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Product Categories</a>
        <a href="products.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Products</a>
        <a href="orders.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Orders</a>
        <a href="../index.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Back to site</a>
        <a href="logout.php" class="block px-3 py-2 rounded-lg font-semibold text-red-600 hover:bg-red-50">Logout</a>
    </div>
</div>

<main class="flex-1 min-w-0">
    <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button id="adminMobileMenuBtn" type="button" class="md:hidden p-2 rounded-lg hover:bg-slate-100 border border-slate-200" aria-label="Open menu">
                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900">Orders</h1>
                <p class="text-sm text-slate-500">Manage all orders</p>
            </div>
        </div>
    </header>

    <div class="p-4 sm:p-6 space-y-6">
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200">
                <h2 class="font-extrabold text-slate-900">All Orders</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">ID</th>
                            <th class="text-left px-5 py-3">Customer</th>
                            <th class="text-left px-5 py-3">Total</th>
                            <th class="text-left px-5 py-3">Status</th>
                            <th class="text-left px-5 py-3">Created</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-mono text-xs"><?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($order['email']); ?></p>
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-900">$<?php echo number_format($order['grand_total'], 2); ?></td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold 
                                        <?php echo ($order['status'] == 'pending') ? 'bg-amber-100 text-amber-700' : 
                                              (($order['status'] == 'processing') ? 'bg-blue-100 text-blue-700' : 
                                              (($order['status'] == 'shipped') ? 'bg-purple-100 text-purple-700' : 
                                              (($order['status'] == 'delivered') ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-700'))); ?>">
                                        <?php echo htmlspecialchars(ucfirst($order['status'] ?? 'pending')); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-500"><?php echo htmlspecialchars(date('M j, Y', strtotime($order['created_at']))) ?></td>
                                <td class="px-5 py-4 text-center">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($order)); ?>)" class="text-blue-700 hover:text-blue-900 font-semibold mr-2">View</button>
                                    <button onclick="openStatusModal(<?php echo htmlspecialchars(json_encode($order)); ?>)" class="text-slate-700 hover:text-slate-900 font-semibold mr-2">Update Status</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this order?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 font-semibold">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>

<!-- View Order Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Order Details</h3>
            <button onclick="closeViewModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="viewModalContent" class="space-y-4"></div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeViewModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Close</button>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div id="statusModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Update Order Status</h3>
            <button onclick="closeStatusModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="status_order_id">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                <select name="status" id="status_status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeStatusModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openViewModal(order) {
    const content = document.getElementById('viewModalContent');
    content.innerHTML = `
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <span class="text-slate-500 font-semibold">Order ID:</span>
                <p class="text-slate-900 font-bold">${order.order_id}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Status:</span>
                <p class="text-slate-900 font-bold capitalize">${order.status || 'pending'}</p>
            </div>
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Customer:</span>
                <p class="text-slate-900">${order.first_name} ${order.last_name} (${order.email})</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Subtotal:</span>
                <p class="text-slate-900">$${parseFloat(order.subtotal).toFixed(2)}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Tax:</span>
                <p class="text-slate-900">$${parseFloat(order.tax).toFixed(2)}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Total:</span>
                <p class="text-slate-900 font-bold text-lg">$${parseFloat(order.grand_total).toFixed(2)}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Payment Method:</span>
                <p class="text-slate-900">${order.payment_method || 'N/A'}</p>
            </div>
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Created:</span>
                <p class="text-slate-900">${order.created_at}</p>
            </div>
        </div>
    `;
    document.getElementById('viewModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function openStatusModal(order) {
    document.getElementById('status_order_id').value = order.order_id;
    document.getElementById('status_status').value = order.status || 'pending';
    document.getElementById('statusModal').classList.remove('hidden');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/partials/bottom.php'; ?>