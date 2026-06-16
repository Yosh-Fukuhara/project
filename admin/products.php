<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'products';
$pageTitle = 'Manage Products - Admin';

// Use the original marketplace products from data/products.php
require_once __DIR__ . '/../data/products.php';

$success = '';
$error = '';

// Note: For simplicity, we're using session to store edits (since marketplace uses array data)
if (!isset($_SESSION['admin_products'])) {
    $_SESSION['admin_products'] = $products;
}
$currentProducts = $_SESSION['admin_products'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        // Simple create, but since marketplace uses fixed IDs, we'll just add with next ID
        $newId = max(array_column($currentProducts, 'id')) + 1;
        $newProduct = [
            'id' => $newId,
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'price' => (float)$_POST['price'],
            'category' => trim($_POST['category']),
            'image' => trim($_POST['image_url']),
            'badge' => trim($_POST['badge']) ?: null
        ];
        $currentProducts[] = $newProduct;
        $_SESSION['admin_products'] = $currentProducts;
        $success = 'Product created successfully!';
    } elseif ($action === 'update') {
        $prodId = (int)$_POST['product_id'];
        foreach ($currentProducts as $idx => $p) {
            if ($p['id'] === $prodId) {
                $currentProducts[$idx]['name'] = trim($_POST['name']);
                $currentProducts[$idx]['description'] = trim($_POST['description']);
                $currentProducts[$idx]['price'] = (float)$_POST['price'];
                $currentProducts[$idx]['category'] = trim($_POST['category']);
                $currentProducts[$idx]['image'] = trim($_POST['image_url']);
                $currentProducts[$idx]['badge'] = trim($_POST['badge']) ?: null;
                $_SESSION['admin_products'] = $currentProducts;
                $success = 'Product updated successfully!';
                break;
            }
        }
    } elseif ($action === 'delete' && isset($_POST['product_id'])) {
        $prodId = (int)$_POST['product_id'];
        $currentProducts = array_filter($currentProducts, function($p) use ($prodId) {
            return $p['id'] !== $prodId;
        });
        $_SESSION['admin_products'] = array_values($currentProducts); // Reindex
        $success = 'Product deleted successfully!';
    }
}

// Use the current products (possibly edited)
$displayProducts = $_SESSION['admin_products'];

$categories = ['Courses', 'Books', 'Resources'];

include __DIR__ . '/partials/top.php';
include __DIR__ . '/partials/sidebar.php';
?>

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
        <a href="products.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Products</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Products</h1>
                <p class="text-sm text-slate-500">Manage all marketplace products</p>
            </div>
        </div>
        <button onclick="openCreateModal()" class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg font-semibold transition">
            Add Product
        </button>
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
                <h2 class="font-extrabold text-slate-900">All Products</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">ID</th>
                            <th class="text-left px-5 py-3">Name</th>
                            <th class="text-left px-5 py-3">Category</th>
                            <th class="text-left px-5 py-3">Price</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($displayProducts as $product): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-mono text-xs"><?php echo htmlspecialchars($product['id']); ?></td>
                                <td class="px-5 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($product['category']); ?></td>
                                <td class="px-5 py-4 font-semibold text-slate-900">$<?php echo number_format($product['price'], 2); ?></td>
                                <td class="px-5 py-4 text-center">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" class="text-blue-700 hover:text-blue-900 font-semibold mr-2">View</button>
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" class="text-slate-700 hover:text-slate-900 font-semibold mr-2">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
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

<!-- Create Product Modal -->
<div id="createModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Create Product</h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Name</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Category</label>
                    <select name="category" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Price</label>
                    <input type="number" name="price" step="0.01" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Image URL</label>
                    <input type="url" name="image_url" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Badge (optional)</label>
                    <input type="text" name="badge" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- View Product Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Product Details</h3>
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

<!-- Edit Product Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Edit Product</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="product_id" id="edit_product_id">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Name</label>
                    <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" id="edit_description" rows="3" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Category</label>
                    <select name="category" id="edit_category" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Price</label>
                    <input type="number" name="price" step="0.01" id="edit_price" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Image URL</label>
                    <input type="url" name="image_url" id="edit_image_url" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Badge (optional)</label>
                    <input type="text" name="badge" id="edit_badge" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
}

function openViewModal(product) {
    const content = document.getElementById('viewModalContent');
    content.innerHTML = `
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <span class="text-slate-500 font-semibold">ID:</span>
                    <p class="text-slate-900 font-bold">${product.id}</p>
                </div>
                <div>
                    <span class="text-slate-500 font-semibold">Category:</span>
                    <p class="text-slate-900">${product.category}</p>
                </div>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Name:</span>
                <p class="text-slate-900 font-bold text-lg">${product.name}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Description:</span>
                <p class="text-slate-900 mt-1 p-3 bg-slate-50 rounded-lg">${product.description}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <span class="text-slate-500 font-semibold">Price:</span>
                    <p class="text-slate-900 font-bold">$${product.price.toFixed(2)}</p>
                </div>
                ${product.badge ? `
                <div>
                    <span class="text-slate-500 font-semibold">Badge:</span>
                    <p class="text-slate-900">${product.badge}</p>
                </div>` : ''}
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Image:</span>
                <div class="mt-2">
                    <img src="${product.image}" alt="${product.name}" class="w-full h-40 object-cover rounded-lg border border-slate-200">
                </div>
            </div>
        </div>
    `;
    document.getElementById('viewModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function openEditModal(product) {
    document.getElementById('edit_product_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_description').value = product.description;
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_category').value = product.category;
    document.getElementById('edit_image_url').value = product.image;
    document.getElementById('edit_badge').value = product.badge || '';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/partials/bottom.php'; ?>
