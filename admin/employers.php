<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'employers';
$pageTitle = 'Manage Employers - Admin';

$pdo = get_db_connection();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $owner_user_id = (int)$_POST['owner_user_id'];
        $company_name = trim($_POST['company_name']);
        $industry = trim($_POST['industry'] ?? '');
        $company_size = trim($_POST['company_size'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $contact_name = trim($_POST['contact_name'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $logo_url = trim($_POST['logo_url'] ?? '');
        
        try {
            $stmt = $pdo->prepare('INSERT INTO EMPLOYERS (owner_user_id, company_name, industry, company_size, website, description, contact_name, contact_phone, logo_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$owner_user_id, $company_name, $industry, $company_size, $website, $description, $contact_name, $contact_phone, $logo_url]);
            $success = 'Employer created successfully!';
        } catch (PDOException $e) {
            $error = 'Error creating employer: ' . $e->getMessage();
        }
    } elseif ($action === 'update') {
        $employer_id = (int)$_POST['employer_id'];
        $owner_user_id = (int)$_POST['owner_user_id'];
        $company_name = trim($_POST['company_name']);
        $industry = trim($_POST['industry'] ?? '');
        $company_size = trim($_POST['company_size'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $contact_name = trim($_POST['contact_name'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $logo_url = trim($_POST['logo_url'] ?? '');
        
        try {
            $stmt = $pdo->prepare('
                UPDATE EMPLOYERS
                SET owner_user_id = ?, company_name = ?, industry = ?, company_size = ?, website = ?, description = ?, contact_name = ?, contact_phone = ?, logo_url = ?
                WHERE employer_id = ?
            ');
            $stmt->execute([$owner_user_id, $company_name, $industry, $company_size, $website, $description, $contact_name, $contact_phone, $logo_url, $employer_id]);
            $success = 'Employer updated successfully!';
        } catch (PDOException $e) {
            $error = 'Error updating employer: ' . $e->getMessage();
        }
    } elseif ($action === 'delete' && isset($_POST['employer_id'])) {
        $employer_id = (int)$_POST['employer_id'];
        try {
            $stmt = $pdo->prepare('DELETE FROM EMPLOYERS WHERE employer_id = ?');
            $stmt->execute([$employer_id]);
            $success = 'Employer deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting employer: ' . $e->getMessage();
        }
    }
}

// Fetch employers with owner user info
$employers = $pdo->query('
    SELECT e.*, u.first_name, u.last_name, u.email 
    FROM EMPLOYERS e 
    JOIN users u ON e.owner_user_id = u.user_id 
    ORDER BY e.created_at DESC
')->fetchAll();

// Fetch all users to be owners
$all_users = $pdo->query('SELECT user_id, first_name, last_name, email FROM users ORDER BY first_name, last_name')->fetchAll();
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
        <a href="employers.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Employers</a>
        <a href="posts.php" class="block px-3 py-2 rounded-lg font-semibold text-slate-700 hover:bg-slate-50">Posts</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Employers</h1>
                <p class="text-sm text-slate-500">Manage all employer accounts</p>
            </div>
        </div>
        <button onclick="openCreateModal()" class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg font-semibold transition">
            Add Employer
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
                <h2 class="font-extrabold text-slate-900">All Employers</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">ID</th>
                            <th class="text-left px-5 py-3">Company</th>
                            <th class="text-left px-5 py-3">Owner</th>
                            <th class="text-left px-5 py-3">Industry</th>
                            <th class="text-left px-5 py-3">Created</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($employers as $emp): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-mono text-xs"><?php echo htmlspecialchars($emp['employer_id']); ?></td>
                                <td class="px-5 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($emp['company_name']); ?></td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo htmlspecialchars($emp['email']); ?></p>
                                </td>
                                <td class="px-5 py-4 text-slate-600"><?php echo htmlspecialchars($emp['industry'] ?? 'N/A'); ?></td>
                                <td class="px-5 py-4 text-xs text-slate-500"><?php echo htmlspecialchars(date('M j, Y', strtotime($emp['created_at']))); ?></td>
                                <td class="px-5 py-4 text-center">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)" class="text-blue-700 hover:text-blue-900 font-semibold mr-2">View</button>
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)" class="text-slate-700 hover:text-slate-900 font-semibold mr-2">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this employer?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="employer_id" value="<?php echo $emp['employer_id']; ?>">
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

<!-- Create Employer Modal -->
<div id="createModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Create Employer</h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Owner User</label>
                <select name="owner_user_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Company Name</label>
                <input type="text" name="company_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Industry</label>
                    <input type="text" name="industry" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Company Size</label>
                    <input type="text" name="company_size" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Website</label>
                <input type="url" name="website" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Contact Name</label>
                    <input type="text" name="contact_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Contact Phone</label>
                    <input type="tel" name="contact_phone" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Logo URL</label>
                <input type="url" name="logo_url" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- View Employer Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Employer Details</h3>
            <button onclick="closeViewModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="viewModalContent" class="space-y-3"></div>
        <div class="mt-6 flex justify-end">
            <button onclick="closeViewModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Close</button>
        </div>
    </div>
</div>

<!-- Edit Employer Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Edit Employer</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="employer_id" id="edit_employer_id">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Owner User</label>
                <select name="owner_user_id" id="edit_owner_user_id" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Company Name</label>
                <input type="text" name="company_name" id="edit_company_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Industry</label>
                    <input type="text" name="industry" id="edit_industry" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Company Size</label>
                    <input type="text" name="company_size" id="edit_company_size" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Website</label>
                <input type="url" name="website" id="edit_website" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                <textarea name="description" id="edit_description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Contact Name</label>
                    <input type="text" name="contact_name" id="edit_contact_name" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Contact Phone</label>
                    <input type="tel" name="contact_phone" id="edit_contact_phone" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Logo URL</label>
                <input type="url" name="logo_url" id="edit_logo_url" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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

function openViewModal(emp) {
    const content = document.getElementById('viewModalContent');
    content.innerHTML = `
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <span class="text-slate-500 font-semibold">ID:</span>
                <p class="text-slate-900 font-bold">${emp.employer_id}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Created:</span>
                <p class="text-slate-900">${emp.created_at}</p>
            </div>
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Company:</span>
                <p class="text-slate-900 font-bold">${emp.company_name}</p>
            </div>
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Owner:</span>
                <p class="text-slate-900">${emp.first_name} ${emp.last_name} (${emp.email})</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Industry:</span>
                <p class="text-slate-900">${emp.industry || 'N/A'}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Size:</span>
                <p class="text-slate-900">${emp.company_size || 'N/A'}</p>
            </div>
            ${emp.website ? `
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Website:</span>
                <a href="${emp.website}" target="_blank" class="text-blue-700 hover:underline">${emp.website}</a>
            </div>` : ''}
            ${emp.description ? `
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Description:</span>
                <p class="text-slate-900">${emp.description}</p>
            </div>` : ''}
            ${emp.contact_name ? `
            <div>
                <span class="text-slate-500 font-semibold">Contact Name:</span>
                <p class="text-slate-900">${emp.contact_name}</p>
            </div>` : ''}
            ${emp.contact_phone ? `
            <div>
                <span class="text-slate-500 font-semibold">Contact Phone:</span>
                <p class="text-slate-900">${emp.contact_phone}</p>
            </div>` : ''}
            ${emp.logo_url ? `
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Logo URL:</span>
                <a href="${emp.logo_url}" target="_blank" class="text-blue-700 hover:underline">${emp.logo_url}</a>
            </div>` : ''}
        </div>
    `;
    document.getElementById('viewModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function openEditModal(emp) {
    document.getElementById('edit_employer_id').value = emp.employer_id;
    document.getElementById('edit_owner_user_id').value = emp.owner_user_id;
    document.getElementById('edit_company_name').value = emp.company_name;
    document.getElementById('edit_industry').value = emp.industry || '';
    document.getElementById('edit_company_size').value = emp.company_size || '';
    document.getElementById('edit_website').value = emp.website || '';
    document.getElementById('edit_description').value = emp.description || '';
    document.getElementById('edit_contact_name').value = emp.contact_name || '';
    document.getElementById('edit_contact_phone').value = emp.contact_phone || '';
    document.getElementById('edit_logo_url').value = emp.logo_url || '';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/partials/bottom.php'; ?>
