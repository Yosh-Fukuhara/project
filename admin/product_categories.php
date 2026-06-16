<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'product_categories';
$pageTitle = 'Manage Product Categories - Admin';

// Original marketplace categories
$categories = ['Courses', 'Books', 'Resources'];

$success = '';
$error = '';

// Note: Using session for any edits
if (!isset($_SESSION['admin_categories'])) {
    $_SESSION['admin_categories'] = $categories;
}
$displayCategories = $_SESSION['admin_categories'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $newCat = trim($_POST['name']);
        if (!in_array($newCat, $displayCategories)) {
            $displayCategories[] = $newCat;
            $_SESSION['admin_categories'] = $displayCategories;
            $success = 'Category created successfully!';
        } else {
            $error = 'Category already exists!';
        }
    } elseif ($action === 'update') {
        $oldName = trim($_POST['old_name']);
        $newName = trim($_POST['name']);
        foreach ($displayCategories as $idx => $cat) {
            if ($cat === $oldName) {
                $displayCategories[$idx] = $newName;
                $_SESSION['admin_categories'] = $displayCategories;
                $success = 'Category updated successfully!';
                break;
            }
        }
    } elseif ($action === 'delete' && isset($_POST['name'])) {
        $catName = trim($_POST['name']);
        $displayCategories = array_filter($displayCategories, function($c) use ($catName) {
            return $c !== $catName;
        });
        $_SESSION['admin_categories'] = array_values($displayCategories);
        $success = 'Category deleted successfully!';
    }
}

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
        <a href="product_categories.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Product Categories</a>
        <a href="products.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Products</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Product Categories</h1>
                <p class="text-sm text-slate-500">Manage all marketplace categories</p>
            </div>
        </div>
        <button onclick="openCreateModal()" class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg font-semibold transition">
            Add Category
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
                <h2 class="font-extrabold text-slate-900">All Categories</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">Name</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($displayCategories as $cat): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($cat); ?></td>
                                <td class="px-5 py-4 text-center">
                                    <button onclick="openEditModal('<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>')" class="text-slate-700 hover:text-slate-900 font-semibold mr-2">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="name" value="<?php echo htmlspecialchars($cat); ?>">
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

<!-- Create Category Modal -->
<div id="createModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Create Category</h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Name</label>
                <input type="text" name="name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Edit Category</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="old_name" id="edit_old_name">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Name</label>
                <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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

function openEditModal(catName) {
    document.getElementById('edit_old_name').value = catName;
    document.getElementById('edit_name').value = catName;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/partials/bottom.php'; ?>
