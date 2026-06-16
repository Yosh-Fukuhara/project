<?php
require_once __DIR__ . '/admin_auth.php';
admin_require_login();

$active = 'users';
$pageTitle = 'Manage Users - Admin';

$pdo = get_db_connection();
$success = '';
$error = '';

function admin_profile_payload(): array
{
    return [
        trim($_POST['profile_pic'] ?? ''),
        trim($_POST['cover_pic'] ?? ''),
        trim($_POST['bio'] ?? ''),
        trim($_POST['location'] ?? ''),
        trim($_POST['website'] ?? ''),
        trim($_POST['phone'] ?? ''),
    ];
}

function admin_save_user_profile(PDO $pdo, int $userId, array $profileData): void
{
    $stmt = $pdo->prepare('SELECT profile_id FROM user_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $profileId = $stmt->fetchColumn();

    if ($profileId) {
        $stmt = $pdo->prepare('
            UPDATE user_profiles
            SET profile_pic = ?, cover_pic = ?, bio = ?, location = ?, website = ?, phone = ?
            WHERE user_id = ?
        ');
        $stmt->execute(array_merge($profileData, [$userId]));
        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO user_profiles (user_id, profile_pic, cover_pic, bio, location, website, phone)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute(array_merge([$userId], $profileData));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $passwordInput = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        $status = trim($_POST['status'] ?? 'active');
        $profileData = admin_profile_payload();

        if ($firstName === '' || $lastName === '' || $email === '' || $passwordInput === '') {
            $error = 'First name, last name, email, and password are required.';
        } else {
            try {
                $pdo->beginTransaction();

                $passwordHash = password_hash($passwordInput, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('
                    INSERT INTO users (first_name, last_name, email, password, role, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$firstName, $lastName, $email, $passwordHash, $role, $status]);

                $userId = (int)$pdo->lastInsertId();
                admin_save_user_profile($pdo, $userId, $profileData);

                $pdo->commit();
                $success = 'User created successfully!';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error creating user: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        $status = trim($_POST['status'] ?? 'active');
        $newPassword = trim($_POST['new_password'] ?? '');
        $profileData = admin_profile_payload();

        if ($userId <= 0 || $firstName === '' || $lastName === '' || $email === '') {
            $error = 'User, first name, last name, and email are required.';
        } else {
            try {
                $pdo->beginTransaction();

                if ($newPassword !== '') {
                    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare('
                        UPDATE users
                        SET first_name = ?, last_name = ?, email = ?, password = ?, role = ?, status = ?
                        WHERE user_id = ?
                    ');
                    $stmt->execute([$firstName, $lastName, $email, $passwordHash, $role, $status, $userId]);
                } else {
                    $stmt = $pdo->prepare('
                        UPDATE users
                        SET first_name = ?, last_name = ?, email = ?, role = ?, status = ?
                        WHERE user_id = ?
                    ');
                    $stmt->execute([$firstName, $lastName, $email, $role, $status, $userId]);
                }

                admin_save_user_profile($pdo, $userId, $profileData);

                $pdo->commit();
                $success = 'User updated successfully!';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error updating user: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];

        try {
            $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
            $stmt->execute([$userId]);
            $success = 'User deleted successfully!';
        } catch (PDOException $e) {
            $error = 'Error deleting user: ' . $e->getMessage();
        }
    }
}

$users = $pdo->query('
    SELECT
        u.*,
        p.profile_pic,
        p.cover_pic,
        p.bio,
        p.location,
        p.website,
        p.phone
    FROM users u
    LEFT JOIN user_profiles p ON u.user_id = p.user_id
    ORDER BY u.created_at DESC
')->fetchAll();
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
        <a href="users.php" class="block px-3 py-2 rounded-lg font-semibold bg-blue-50 text-blue-900">Users</a>
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
                <h1 class="text-xl font-extrabold text-slate-900">Users</h1>
                <p class="text-sm text-slate-500">Manage all user accounts</p>
            </div>
        </div>
        <button onclick="openCreateModal()" class="bg-blue-900 hover:bg-blue-800 text-white px-4 py-2 rounded-lg font-semibold transition">
            Add User
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
                <h2 class="font-extrabold text-slate-900">All Users</h2>
            </div>
            <div class="p-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                        <tr>
                            <th class="text-left px-5 py-3">ID</th>
                            <th class="text-left px-5 py-3">Name</th>
                            <th class="text-left px-5 py-3">Email</th>
                            <th class="text-left px-5 py-3">Role</th>
                            <th class="text-left px-5 py-3">Status</th>
                            <th class="text-left px-5 py-3">Created</th>
                            <th class="text-center px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 font-mono text-xs"><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td class="px-5 py-4 font-semibold text-slate-900"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td class="px-5 py-4"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : ($user['role'] === 'employer' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'); ?>">
                                        <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($user['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-500"><?php echo htmlspecialchars(date('M j, Y', strtotime($user['created_at']))); ?></td>
                                <td class="px-5 py-4 text-center">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="text-blue-700 hover:text-blue-900 font-semibold mr-2">View</button>
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="text-slate-700 hover:text-slate-900 font-semibold mr-2">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
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

<!-- Create User Modal -->
<div id="createModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Create User</h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">First Name</label>
                    <input type="text" name="first_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Last Name</label>
                    <input type="text" name="last_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
                <input type="email" name="email" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Role</label>
                    <select name="role" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="user">User</option>
                        <option value="employer">Employer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Location</label>
                    <input type="text" name="location" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Website</label>
                <input type="url" name="website" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Profile Picture Path or URL</label>
                    <input type="text" name="profile_pic" placeholder="uploads/profile.jpg or https://example.com/profile.jpg" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Cover Picture Path or URL</label>
                    <input type="text" name="cover_pic" placeholder="uploads/cover.jpg or https://example.com/cover.jpg" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Bio</label>
                <textarea name="bio" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="pt-4 flex gap-3 justify-end">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg font-semibold hover:bg-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-900 text-white rounded-lg font-semibold hover:bg-blue-800">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- View User Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">User Details</h3>
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

<!-- Edit User Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-extrabold text-slate-900">Edit User</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">First Name</label>
                    <input type="text" name="first_name" id="edit_first_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Last Name</label>
                    <input type="text" name="last_name" id="edit_last_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
                <input type="email" name="email" id="edit_email" required class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">New Password (leave blank to keep current)</label>
                <input type="password" name="new_password" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Role</label>
                    <select name="role" id="edit_role" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="user">User</option>
                        <option value="employer">Employer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                    <select name="status" id="edit_status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Phone</label>
                    <input type="text" name="phone" id="edit_phone" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Location</label>
                    <input type="text" name="location" id="edit_location" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Website</label>
                <input type="url" name="website" id="edit_website" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Profile Picture Path or URL</label>
                    <input type="text" name="profile_pic" id="edit_profile_pic" placeholder="uploads/profile.jpg or https://example.com/profile.jpg" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Cover Picture Path or URL</label>
                    <input type="text" name="cover_pic" id="edit_cover_pic" placeholder="uploads/cover.jpg or https://example.com/cover.jpg" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Bio</label>
                <textarea name="bio" id="edit_bio" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
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

function openViewModal(user) {
    const content = document.getElementById('viewModalContent');
    content.innerHTML = `
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <span class="text-slate-500 font-semibold">ID:</span>
                <p class="text-slate-900 font-bold">${user.user_id}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Role:</span>
                <p class="text-slate-900 font-bold capitalize">${user.role}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">First Name:</span>
                <p class="text-slate-900">${user.first_name}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Last Name:</span>
                <p class="text-slate-900">${user.last_name}</p>
            </div>
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Email:</span>
                <p class="text-slate-900">${user.email}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Status:</span>
                <p class="text-slate-900 capitalize">${user.status}</p>
            </div>
            <div>
                <span class="text-slate-500 font-semibold">Created:</span>
                <p class="text-slate-900">${user.created_at}</p>
            </div>
            ${user.phone ? `
            <div>
                <span class="text-slate-500 font-semibold">Phone:</span>
                <p class="text-slate-900">${user.phone}</p>
            </div>` : ''}
            ${user.bio ? `
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Bio:</span>
                <p class="text-slate-900">${user.bio}</p>
            </div>` : ''}
            ${user.location ? `
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Location:</span>
                <p class="text-slate-900">${user.location}</p>
            </div>` : ''}
            ${user.website ? `
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Website:</span>
                <a href="${user.website}" target="_blank" class="text-blue-700 hover:underline">${user.website}</a>
            </div>` : ''}
            ${user.profile_pic ? `
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Profile Picture:</span>
                <a href="${user.profile_pic}" target="_blank" class="text-blue-700 hover:underline">${user.profile_pic}</a>
            </div>` : ''}
            ${user.cover_pic ? `
            <div class="col-span-2">
                <span class="text-slate-500 font-semibold">Cover Picture:</span>
                <a href="${user.cover_pic}" target="_blank" class="text-blue-700 hover:underline">${user.cover_pic}</a>
            </div>` : ''}
        </div>
    `;
    document.getElementById('viewModal').classList.remove('hidden');
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
}

function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.user_id;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_location').value = user.location || '';
    document.getElementById('edit_website').value = user.website || '';
    document.getElementById('edit_profile_pic').value = user.profile_pic || '';
    document.getElementById('edit_cover_pic').value = user.cover_pic || '';
    document.getElementById('edit_bio').value = user.bio || '';
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php include __DIR__ . '/partials/bottom.php'; ?>
