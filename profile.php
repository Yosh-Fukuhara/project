<?php
require_once 'includes/bootstrap.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// ── If ?user= is given and it's NOT the current logged-in user,
//    send them to the public profile page instead ────────────────────────
if (isset($_GET['user']) && trim($_GET['user']) !== '') {
    $requestedUser = trim($_GET['user']);
    $isSelf = (
        strcasecmp($requestedUser, $_SESSION['user']['email']    ?? '') === 0 ||
        strcasecmp($requestedUser, $_SESSION['user']['username'] ?? '') === 0 ||
        (is_numeric($requestedUser) && (int)$requestedUser === (int)($_SESSION['user']['id'] ?? 0))
    );
    if (!$isSelf) {
        try {
            $pdo  = get_db_connection();
            if (is_numeric($requestedUser)) {
                $st = $pdo->prepare('SELECT id FROM users WHERE id = ? AND status = "active" LIMIT 1');
                $st->execute([(int)$requestedUser]);
            } else {
                $st = $pdo->prepare('SELECT id FROM users WHERE (email = ? OR username = ?) AND status = "active" LIMIT 1');
                $st->execute([$requestedUser, $requestedUser]);
            }
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                header('Location: view_profile.php?id=' . (int)$row['id']);
            } else {
                header('Location: view_profile.php?username=' . urlencode($requestedUser));
            }
        } catch (Exception $e) {
            header('Location: view_profile.php?username=' . urlencode($requestedUser));
        }
        exit;
    }
}
// ─────────────────────────────────────────────────────────────────────────

$pageTitle = 'Profile - CyberSphere';
$currentPage = 'profile';

$errors = [];
$success = '';

if (!isset($_SESSION['posts']) || !is_array($_SESSION['posts'])) {
    $_SESSION['posts'] = [];
}
if (!isset($_SESSION['saved_jobs']) || !is_array($_SESSION['saved_jobs'])) {
    $_SESSION['saved_jobs'] = [];
}
if (!isset($_SESSION['my_applications']) || !is_array($_SESSION['my_applications'])) {
    $_SESSION['my_applications'] = [];
}

$detailDefaults = ['bio' => ''];
foreach ($detailDefaults as $k => $v) {
    if (!array_key_exists($k, $_SESSION['user'])) {
        $_SESSION['user'][$k] = $v;
    }
}

function saveProfilePhoto(?array $file, array &$errors): ?string {
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Profile photo upload failed.';
        return null;
    }
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $imgInfo = @getimagesize($file['tmp_name'] ?? '');
    $mime = is_array($imgInfo) ? ($imgInfo['mime'] ?? '') : '';
    if ($mime === '' || !isset($allowedTypes[$mime])) {
        $errors[] = 'Invalid file type. Please use an image file.';
        return null;
    }
    $maxSize = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        $errors[] = 'File is too large (max 5MB).';
        return null;
    }
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    $filename = 'profile_' . uniqid() . '.' . $allowedTypes[$mime];
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $errors[] = 'Could not save profile photo.';
        return null;
    }
    return 'uploads/' . $filename;
}

function saveCoverPhoto(?array $file, array &$errors): ?string {
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Cover photo upload failed.';
        return null;
    }
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $maxSize = 5 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        $errors[] = 'File is too large (max 5MB).';
        return null;
    }

    // Landscape-only restriction (cover must be wider than tall)
    $imgInfo = @getimagesize($file['tmp_name'] ?? '');
    if (!$imgInfo || empty($imgInfo[0]) || empty($imgInfo[1])) {
        $errors[] = 'Could not read image dimensions.';
        return null;
    }
    $mime = $imgInfo['mime'] ?? '';
    if ($mime === '' || !isset($allowedTypes[$mime])) {
        $errors[] = 'Invalid file type. Please use an image file.';
        return null;
    }
    $w = (int)$imgInfo[0];
    $h = (int)$imgInfo[1];
    if ($w <= $h) {
        $errors[] = 'Cover photo must be landscape (wider than tall).';
        return null;
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    $filename = 'cover_' . uniqid() . '.' . $allowedTypes[$mime];
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $errors[] = 'Could not save cover photo.';
        return null;
    }
    return 'uploads/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_bio') {
    $bio = trim($_POST['bio'] ?? '');
    $_SESSION['user']['bio'] = $bio;
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_details') {
    $_SESSION['user']['work']      = trim($_POST['work']      ?? '');
    $_SESSION['user']['location']  = trim($_POST['location']  ?? '');
    $_SESSION['user']['address']   = trim($_POST['address']   ?? '');
    $_SESSION['user']['website']   = trim($_POST['website']   ?? '');
    $_SESSION['user']['phone']     = trim($_POST['phone']     ?? '');
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_education') {
    if (!isset($_SESSION['user']['education_list'])) $_SESSION['user']['education_list'] = [];
    $school  = trim($_POST['edu_school']  ?? '');
    $degree  = trim($_POST['edu_degree']  ?? '');
    $year    = trim($_POST['edu_year']    ?? '');
    if ($school !== '') {
        array_unshift($_SESSION['user']['education_list'], [
            'id'     => uniqid('edu_'),
            'school' => $school,
            'degree' => $degree,
            'year'   => $year,
        ]);
    }
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_education') {
    $delId = $_POST['edu_id'] ?? '';
    $_SESSION['user']['education_list'] = array_values(array_filter(
        $_SESSION['user']['education_list'] ?? [],
        fn($e) => $e['id'] !== $delId
    ));
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_experience') {
    if (!isset($_SESSION['user']['experience_list'])) $_SESSION['user']['experience_list'] = [];
    $company  = trim($_POST['exp_company']  ?? '');
    $role     = trim($_POST['exp_role']     ?? '');
    $period   = trim($_POST['exp_period']   ?? '');
    $desc     = trim($_POST['exp_desc']     ?? '');
    if ($company !== '') {
        array_unshift($_SESSION['user']['experience_list'], [
            'id'      => uniqid('exp_'),
            'company' => $company,
            'role'    => $role,
            'period'  => $period,
            'desc'    => $desc,
        ]);
    }
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_experience') {
    $delId = $_POST['exp_id'] ?? '';
    $_SESSION['user']['experience_list'] = array_values(array_filter(
        $_SESSION['user']['experience_list'] ?? [],
        fn($e) => $e['id'] !== $delId
    ));
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_project') {
    if (!isset($_SESSION['user']['projects_list'])) $_SESSION['user']['projects_list'] = [];
    $title = trim($_POST['proj_title'] ?? '');
    $url   = trim($_POST['proj_url']   ?? '');
    $desc  = trim($_POST['proj_desc']  ?? '');
    if ($title !== '') {
        array_unshift($_SESSION['user']['projects_list'], [
            'id'    => uniqid('proj_'),
            'title' => $title,
            'url'   => $url,
            'desc'  => $desc,
        ]);
    }
    header('Location: profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_project') {
    $delId = $_POST['proj_id'] ?? '';
    $_SESSION['user']['projects_list'] = array_values(array_filter(
        $_SESSION['user']['projects_list'] ?? [],
        fn($e) => $e['id'] !== $delId
    ));
    header('Location: profile.php');
    exit;
}

$profileErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_profile') {
    $profilePath = saveProfilePhoto($_FILES['profile_photo'] ?? null, $profileErrors);
    if ($profilePath) {
        $_SESSION['user']['profile_pic'] = $profilePath;
        header('Location: profile.php');
        exit;
    }
}

$coverErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_cover') {
    $coverPath = saveCoverPhoto($_FILES['cover_photo'] ?? null, $coverErrors);
    if ($coverPath) {
        $_SESSION['user']['cover_pic'] = $coverPath;
        header('Location: profile.php');
        exit;
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">
        <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition mb-6">
            <?php if (!empty($profileErrors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3">
                    <?php foreach ($profileErrors as $e): ?>
                        <p><?php echo htmlspecialchars($e); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($coverErrors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3">
                    <?php foreach ($coverErrors as $e): ?>
                        <p><?php echo htmlspecialchars($e); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="relative">
                <div id="coverBox" class="w-full h-56 md:h-72 bg-gradient-to-r from-blue-900 to-cyan-800 overflow-hidden flex items-center justify-center">
                    <?php if (isset($_SESSION['user']) && !empty($_SESSION['user']['cover_pic'])): ?>
                        <img id="coverImg" src="<?php echo htmlspecialchars($_SESSION['user']['cover_pic']); ?>" alt="Cover photo" class="w-full h-full object-contain cursor-pointer" title="Click to view">
                    <?php endif; ?>
                </div>
                <!-- Cover Photo Viewer (Modal) -->
                <div id="coverModal" class="fixed inset-0 bg-black/70 hidden z-50">
                    <button type="button" id="coverModalClose" class="absolute top-4 right-4 text-white bg-white/10 hover:bg-white/20 rounded-full p-2" aria-label="Close">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                    <div class="w-full h-full flex items-center justify-center p-4">
                        <img id="coverModalImg" src="" alt="Cover photo" class="max-w-full max-h-full object-contain rounded-xl shadow-2xl bg-black/10">
                    </div>
                </div>
                <div class="absolute bottom-3 right-3 z-20 pointer-events-auto">
                    <form id="coverForm" method="POST" enctype="multipart/form-data" class="relative z-20 pointer-events-auto">
                        <input type="hidden" name="action" value="upload_cover">
                        <div id="coverInputContainer"></div>
                        <button type="button" id="triggerCoverBtn" class="relative z-20 pointer-events-auto bg-white text-gray-800 px-4 py-2 rounded-xl shadow hover:bg-gray-100 transition flex items-center gap-2 font-semibold text-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 0 00-2-2H6a2 2 0 002 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Change Cover
                        </button>
                    </form>
                </div>
            </div>
            <script>
            (function() {
                let coverInputCount = 0;
                
                function createCoverInput() {
                    const container = document.getElementById('coverInputContainer');
                    container.innerHTML = '';
                    
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.name = 'cover_photo';
                    input.accept = 'image/*';
                    input.className = 'hidden';
                    input.id = 'coverInput_' + (++coverInputCount);
                    
                    input.addEventListener('change', function() {
                        const file = this.files[0];
                        const maxSize = 5 * 1024 * 1024;
                        
                        if (!file) {
                            createCoverInput();
                            return;
                        }
                        
                        if (file.size > maxSize) {
                            alert("Image is too large. Maximum size is 5MB.");
                            createCoverInput();
                            return;
                        }

                        const objUrl = URL.createObjectURL(file);
                        const probe = new Image();
                        probe.onload = function() {
                            const w = probe.naturalWidth || 0;
                            const h = probe.naturalHeight || 0;
                            URL.revokeObjectURL(objUrl);

                            if (w <= h) {
                                alert(`Cover photo must be landscape (wider than tall). Your image is ${w}x${h}.`);
                                createCoverInput();
                                return;
                            }

                            const coverBox = document.getElementById('coverBox');
                            let coverImg = document.getElementById('coverImg');
                            
                            if (!coverImg) {
                                coverImg = document.createElement('img');
                                coverImg.id = 'coverImg';
                                coverImg.className = 'w-full h-full object-contain cursor-pointer';
                                coverBox.innerHTML = '';
                                coverBox.appendChild(coverImg);
                            }
                            
                            coverImg.src = URL.createObjectURL(file);
                            coverImg.classList.remove('hidden');
                            coverBox.classList.remove('bg-gradient-to-r', 'from-blue-900', 'to-cyan-800');
                            coverBox.classList.add('bg-gray-200');
                            
                            setTimeout(() => {
                                document.getElementById('coverForm').submit();
                            }, 100);
                        };
                        probe.onerror = function() {
                            URL.revokeObjectURL(objUrl);
                            alert('Invalid image file.');
                            createCoverInput();
                        };
                        probe.src = objUrl;
                    });
                    
                    container.appendChild(input);
                }
                
                // Create initial input
                createCoverInput();
                
                // Button click handler
                const triggerBtn = document.getElementById('triggerCoverBtn');
                if (triggerBtn) {
                    triggerBtn.addEventListener('click', function() {
                        const container = document.getElementById('coverInputContainer');
                        const input = container.querySelector('input[type="file"]');
                        if (input) {
                            input.click();
                        }
                    });
                }

                // View full cover photo on click
                const modal = document.getElementById('coverModal');
                const modalImg = document.getElementById('coverModalImg');
                const modalClose = document.getElementById('coverModalClose');
                const coverBox = document.getElementById('coverBox');

                function openCoverModal(src) {
                    if (!modal || !modalImg || !src) return;
                    modalImg.src = src;
                    modal.classList.remove('hidden');
                }
                function closeCoverModal() {
                    if (!modal) return;
                    modal.classList.add('hidden');
                    if (modalImg) modalImg.src = '';
                }

                function bindCoverClick() {
                    const img = document.getElementById('coverImg');
                    if (!img) return;
                    img.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        openCoverModal(img.src);
                    });
                }
                bindCoverClick();

                if (coverBox) {
                    // If the image is replaced/created later, re-bind click.
                    const observer = new MutationObserver(() => bindCoverClick());
                    observer.observe(coverBox, { childList: true, subtree: true });
                }

                if (modalClose) modalClose.addEventListener('click', closeCoverModal);
                if (modal) {
                    modal.addEventListener('click', (e) => {
                        if (e.target === modal) closeCoverModal();
                    });
                }
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') closeCoverModal();
                });
            })();
            </script>
            <div class="px-4 pb-4 -mt-10 relative z-10">
                <div class="flex items-center gap-4">
                    <form id="profileUploadForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_profile">
                        <input id="profilePhotoInput" type="file" name="profile_photo" accept="image/*" class="hidden">
                        <button type="button" id="profileUploadBtn" class="w-20 h-20 bg-gray-200 rounded-full border-4 border-white flex items-center justify-center text-3xl text-blue-800 overflow-hidden shadow-md relative cursor-pointer group">
                            <?php if (isset($_SESSION['user']) && !empty($_SESSION['user']['profile_pic'])): ?>
                                <img id="profilePhotoPreview" src="<?php echo htmlspecialchars($_SESSION['user']['profile_pic']); ?>" alt="Profile photo" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span id="profileInitial"><?php echo isset($_SESSION['user']) ? strtoupper(substr($_SESSION['user']['username'], 0, 1)) : 'G'; ?></span>
                            <?php endif; ?>
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                        </button>
                    </form>
                    <div class="mt-10">
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></h1>
                        <p class="text-gray-600"><?php echo htmlspecialchars($_SESSION['user']['email']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3 flex-1">
                    <svg class="w-5 h-5 text-gray-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <div id="bioStripView" class="flex-1">
                        <p class="text-gray-800">
                            <?php echo !empty($_SESSION['user']['bio']) ? htmlspecialchars($_SESSION['user']['bio']) : 'No bio yet.'; ?>
                        </p>
                    </div>
                    <form id="bioStripForm" method="POST" class="flex-1 hidden">
                        <input type="hidden" name="action" value="save_bio">
                        <div class="flex gap-3 w-full">
                            <textarea name="bio" rows="2" class="flex-1 px-3 py-2 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Write a bio..."><?php echo htmlspecialchars($_SESSION['user']['bio']); ?></textarea>
                            <button type="submit" class="bg-blue-900 text-white font-semibold px-4 py-2 rounded-xl hover:bg-blue-800 transition text-sm">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
                <button id="bioEditBtn" class="flex items-center gap-2 text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition text-sm flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <span id="bioEditBtnText">Edit</span>
                </button>
            </div>
        </div>

        <!-- ── Profile Details Strip ── -->
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-6">
            <div class="flex items-start justify-between gap-3">
                <!-- View mode -->
                <div id="detailsView" class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-6">
                    <?php
                    $detailRows = [
                        ['icon'=>'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'label'=>'Work',    'key'=>'work'],
                        ['icon'=>'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z', 'label'=>'Location','key'=>'location'],
                        ['icon'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label'=>'Address', 'key'=>'address'],
                        ['icon'=>'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9', 'label'=>'Website', 'key'=>'website'],
                        ['icon'=>'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', 'label'=>'Phone',   'key'=>'phone'],
                    ];
                    $hasAny = false;
                    foreach ($detailRows as $row):
                        $val = $_SESSION['user'][$row['key']] ?? '';
                        if (!$val) continue;
                        $hasAny = true;
                    ?>
                    <div class="flex items-center gap-2 text-sm text-gray-700">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $row['icon']; ?>"/>
                        </svg>
                        <?php if ($row['key'] === 'website'): ?>
                            <a href="<?php echo htmlspecialchars($val); ?>" target="_blank" rel="noopener"
                               class="text-blue-700 hover:underline truncate"><?php echo htmlspecialchars($val); ?></a>
                        <?php else: ?>
                            <span class="truncate"><?php echo htmlspecialchars($val); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$hasAny): ?>
                        <p class="text-gray-400 text-sm italic col-span-2">No details added yet — click Edit to add.</p>
                    <?php endif; ?>
                </div>

                <!-- Edit form (hidden by default) -->
                <form id="detailsForm" method="POST" class="flex-1 hidden">
                    <input type="hidden" name="action" value="save_details">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                        <?php
                        $detailFields = [
                            ['name'=>'work',     'label'=>'Work / Title',      'placeholder'=>'e.g. SOC Analyst at NetSentinel'],
                            ['name'=>'location', 'label'=>'City / Region',     'placeholder'=>'e.g. Manila, Philippines'],
                            ['name'=>'address',  'label'=>'Current Address',   'placeholder'=>'e.g. 123 Rizal St, Makati City'],
                            ['name'=>'website',  'label'=>'Website',           'placeholder'=>'https://yoursite.com'],
                            ['name'=>'phone',    'label'=>'Phone',             'placeholder'=>'e.g. 09XX XXX XXXX'],
                        ];
                        foreach ($detailFields as $f):
                        ?>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1"><?php echo $f['label']; ?></label>
                            <input type="text" name="<?php echo $f['name']; ?>"
                                   value="<?php echo htmlspecialchars($_SESSION['user'][$f['name']] ?? ''); ?>"
                                   placeholder="<?php echo htmlspecialchars($f['placeholder']); ?>"
                                   class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-900 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800 transition">Save</button>
                        <button type="button" id="detailsCancelBtn"
                                class="border border-gray-300 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                    </div>
                </form>

                <!-- Toggle button -->
                <button id="detailsEditBtn"
                        class="flex items-center gap-2 text-gray-700 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition text-sm flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span id="detailsEditBtnText">Edit</span>
                </button>
            </div>
        </div>

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 uppercase tracking-wider">Dashboard</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-lg text-gray-900 mb-2 flex items-center gap-2">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Applied Jobs
                    </h3>
                    <?php if (empty($_SESSION['my_applications'])): ?>
                        <p class="text-gray-500">No applications yet.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach (array_slice($_SESSION['my_applications'], 0, 2) as $app): ?>
                                <div class="text-sm">
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($app['postTitle'] ?: 'Job Posting'); ?></p>
                                    <span class="text-xs px-2 py-0.5 rounded-full <?php echo ($app['status'] ?? 'pending') === 'pending' ? 'bg-yellow-100 text-yellow-800' : (($app['status'] ?? 'pending') === 'accepted' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst($app['status'] ?? 'pending'); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-lg text-gray-900 mb-2 flex items-center gap-2">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg>
                        Saved Jobs
                    </h3>
                    <?php if (empty($_SESSION['saved_jobs'])): ?>
                        <p class="text-gray-500">No saved jobs yet.</p>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm"><?php echo count($_SESSION['saved_jobs']); ?> saved job<?php echo count($_SESSION['saved_jobs']) !== 1 ? 's' : ''; ?></p>
                    <?php endif; ?>
                </div>

                <!-- ── Purchase History ── -->
                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-lg text-gray-900 mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        Purchase History
                    </h3>
                    <?php
                    if (!isset($_SESSION['purchases']) || !is_array($_SESSION['purchases'])) {
                        $_SESSION['purchases'] = [];
                    }
                    $purchases = $_SESSION['purchases'];
                    ?>
                    <?php if (empty($purchases)): ?>
                        <p class="text-gray-500 text-sm">No purchases yet. <a href="market.php" class="text-blue-700 hover:underline font-semibold">Browse marketplace</a></p>
                    <?php else: ?>
                        <div class="space-y-3">
                        <?php foreach (array_slice($purchases, 0, 5) as $order): ?>
                            <div class="border border-gray-100 rounded-xl p-3 bg-gray-50">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-bold text-blue-900 font-mono"><?php echo htmlspecialchars($order['id']); ?></span>
                                    <span class="text-xs text-gray-400"><?php echo htmlspecialchars($order['ordered_at'] ?? ''); ?></span>
                                </div>
                                <ul class="space-y-0.5 mb-1.5">
                                <?php foreach ($order['items'] as $item): ?>
                                    <li class="text-xs text-gray-700 flex justify-between">
                                        <span class="truncate pr-2"><?php echo htmlspecialchars($item['name']); ?> &times;<?php echo (int)$item['quantity']; ?></span>
                                        <span class="flex-shrink-0 font-semibold">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                                <div class="flex items-center justify-between pt-1.5 border-t border-gray-200">
                                    <span class="text-xs text-gray-500"><?php echo htmlspecialchars($order['payment_method'] ?? ''); ?></span>
                                    <span class="text-xs font-bold text-gray-900">₱<?php echo number_format($order['grand_total'] ?? 0, 2); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <?php if (count($purchases) > 5): ?>
                            <p class="text-xs text-gray-400 mt-2 text-right"><?php echo count($purchases) - 5; ?> more order(s) not shown</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-lg text-gray-900 mb-2 flex items-center gap-2">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                        Skill Badges
                    </h3>
                    <p class="text-gray-500">Complete assessments to earn badges.</p>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl p-5">
                    <h3 class="font-semibold text-lg text-gray-900 mb-2 flex items-center gap-2">
                        <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Recent Messages
                    </h3>
                    <p class="text-gray-500">No messages yet.</p>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════
             WORK EXPERIENCE
        ══════════════════════════════════════════ -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Work Experience
                </h2>
                <button onclick="document.getElementById('expForm').classList.toggle('hidden')"
                        class="text-xs font-semibold text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add
                </button>
            </div>

            <!-- Add form (hidden by default) -->
            <form method="POST" id="expForm" class="hidden mb-5 bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
                <input type="hidden" name="action" value="save_experience">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Company *</label>
                        <input type="text" name="exp_company" required placeholder="e.g. NetSentinel Solutions"
                               class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Role / Title</label>
                        <input type="text" name="exp_role" placeholder="e.g. SOC Analyst"
                               class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Period</label>
                        <input type="text" name="exp_period" placeholder="e.g. Jan 2023 – Present"
                               class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
                        <input type="text" name="exp_desc" placeholder="Brief description..."
                               class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-900 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800 transition">Save</button>
                    <button type="button" onclick="document.getElementById('expForm').classList.add('hidden')"
                            class="border border-gray-300 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                </div>
            </form>

            <?php $expList = $_SESSION['user']['experience_list'] ?? []; ?>
            <?php if (empty($expList)): ?>
                <p class="text-gray-400 text-sm italic">No work experience added yet.</p>
            <?php else: ?>
                <div class="space-y-4">
                <?php foreach ($expList as $exp): ?>
                    <div class="flex gap-4 group">
                        <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-800 font-bold text-base flex-shrink-0 mt-0.5">
                            <?php echo strtoupper(substr($exp['company'], 0, 1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($exp['role'] ?: $exp['company']); ?></p>
                            <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($exp['company']); ?></p>
                            <?php if ($exp['period']): ?>
                                <p class="text-gray-400 text-xs mt-0.5"><?php echo htmlspecialchars($exp['period']); ?></p>
                            <?php endif; ?>
                            <?php if ($exp['desc']): ?>
                                <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($exp['desc']); ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="POST" class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition">
                            <input type="hidden" name="action" value="delete_experience">
                            <input type="hidden" name="exp_id" value="<?php echo htmlspecialchars($exp['id']); ?>">
                            <button type="submit" class="text-gray-300 hover:text-red-400 transition" title="Remove">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════════════
             EDUCATION
        ══════════════════════════════════════════ -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                    Education
                </h2>
                <button onclick="document.getElementById('eduForm').classList.toggle('hidden')"
                        class="text-xs font-semibold text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add
                </button>
            </div>

            <form method="POST" id="eduForm" class="hidden mb-5 bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
                <input type="hidden" name="action" value="save_education">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">School *</label>
                        <input type="text" name="edu_school" required placeholder="e.g. DLSU"
                               class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Degree / Course</label>
                        <input type="text" name="edu_degree" placeholder="e.g. BS Computer Science"
                               class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Year</label>
                        <input type="text" name="edu_year" placeholder="e.g. 2020 – 2024"
                               class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-900 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800 transition">Save</button>
                    <button type="button" onclick="document.getElementById('eduForm').classList.add('hidden')"
                            class="border border-gray-300 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                </div>
            </form>

            <?php $eduList = $_SESSION['user']['education_list'] ?? []; ?>
            <?php if (!empty($_SESSION['user']['education'])  && empty($eduList)): ?>
                <?php /* Show legacy single-field education string from DB */ ?>
                <p class="text-gray-700 text-sm"><?php echo htmlspecialchars($_SESSION['user']['education']); ?></p>
            <?php elseif (empty($eduList)): ?>
                <p class="text-gray-400 text-sm italic">No education added yet.</p>
            <?php else: ?>
                <div class="space-y-4">
                <?php foreach ($eduList as $edu): ?>
                    <div class="flex gap-4 group">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-800 font-bold text-base flex-shrink-0 mt-0.5">
                            🎓
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($edu['school']); ?></p>
                            <?php if ($edu['degree']): ?>
                                <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($edu['degree']); ?></p>
                            <?php endif; ?>
                            <?php if ($edu['year']): ?>
                                <p class="text-gray-400 text-xs mt-0.5"><?php echo htmlspecialchars($edu['year']); ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="POST" class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition">
                            <input type="hidden" name="action" value="delete_education">
                            <input type="hidden" name="edu_id" value="<?php echo htmlspecialchars($edu['id']); ?>">
                            <button type="submit" class="text-gray-300 hover:text-red-400 transition" title="Remove">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════════════
             PROJECTS
        ══════════════════════════════════════════ -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                    Projects
                </h2>
                <button onclick="document.getElementById('projForm').classList.toggle('hidden')"
                        class="text-xs font-semibold text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add
                </button>
            </div>

            <form method="POST" id="projForm" class="hidden mb-5 bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
                <input type="hidden" name="action" value="save_project">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Project Title *</label>
                        <input type="text" name="proj_title" required placeholder="e.g. CyberSphere Platform"
                               class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">URL (optional)</label>
                        <input type="url" name="proj_url" placeholder="https://..."
                               class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
                        <textarea name="proj_desc" rows="2" placeholder="What does it do? What tech did you use?"
                                  class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm resize-none"></textarea>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-900 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-800 transition">Save</button>
                    <button type="button" onclick="document.getElementById('projForm').classList.add('hidden')"
                            class="border border-gray-300 text-gray-600 text-sm font-semibold px-4 py-2 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                </div>
            </form>

            <?php $projList = $_SESSION['user']['projects_list'] ?? []; ?>
            <?php if (empty($projList)): ?>
                <p class="text-gray-400 text-sm italic">No projects added yet.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach ($projList as $proj): ?>
                    <div class="border border-gray-200 rounded-xl p-4 hover:shadow-sm transition group relative">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-lg">🖥️</span>
                                <?php if (!empty($proj['url'])): ?>
                                    <a href="<?php echo htmlspecialchars($proj['url']); ?>" target="_blank" rel="noopener"
                                       class="font-semibold text-blue-800 hover:underline text-sm truncate">
                                        <?php echo htmlspecialchars($proj['title']); ?>
                                    </a>
                                <?php else: ?>
                                    <p class="font-semibold text-gray-900 text-sm truncate"><?php echo htmlspecialchars($proj['title']); ?></p>
                                <?php endif; ?>
                            </div>
                            <form method="POST" class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition">
                                <input type="hidden" name="action" value="delete_project">
                                <input type="hidden" name="proj_id" value="<?php echo htmlspecialchars($proj['id']); ?>">
                                <button type="submit" class="text-gray-300 hover:text-red-400 transition" title="Remove">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                        <?php if (!empty($proj['url'])): ?>
                            <p class="text-xs text-gray-400 truncate mb-1"><?php echo htmlspecialchars($proj['url']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($proj['desc'])): ?>
                            <p class="text-gray-600 text-xs leading-relaxed"><?php echo htmlspecialchars($proj['desc']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- POSTS SECTION BELOW -->
        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Posts</h2>
            <?php
            // ── Fetch DB posts for this user ──────────────────────────────
            $myDbPosts = [];
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare(
                    'SELECT p.id, p.content, p.attachment_path, p.created_at,
                            u.username, u.profile_pic
                     FROM posts p
                     JOIN users u ON u.id = p.user_id
                     WHERE p.user_id = ?
                     ORDER BY p.created_at DESC
                     LIMIT 20'
                );
                $stmt->execute([$_SESSION['user']['id']]);
                $myDbPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $myDbPosts = [];
            }

            // ── Merge session posts (feed, not yet in DB) ──────────────
            $mySessionPosts = array_filter($_SESSION['posts'] ?? [], function($p) {
                return ($p['email'] ?? '') === ($_SESSION['user']['email'] ?? '');
            });

            $allMyPosts = [];

            // Feed session posts first
            foreach ($mySessionPosts as $sp) {
                $allMyPosts[] = [
                    'source'      => 'session',
                    'label'       => '',
                    'id'          => $sp['id'] ?? '',
                    'content'     => $sp['content'] ?? '',
                    'attachment'  => $sp['attachment']['path'] ?? null,
                    'time_label'  => $sp['time'] ?? 'Just now',
                    'username'    => $sp['username'] ?? $_SESSION['user']['username'],
                    'profile_pic' => $_SESSION['user']['profile_pic'] ?? null,
                ];
            }

            // DB posts
            foreach ($myDbPosts as $dp) {
                $allMyPosts[] = [
                    'source'      => 'db',
                    'label'       => '',
                    'id'          => $dp['id'],
                    'content'     => $dp['content'],
                    'attachment'  => $dp['attachment_path'],
                    'time_label'  => date('M j, Y g:i A', strtotime($dp['created_at'])),
                    'username'    => $dp['username'],
                    'profile_pic' => $dp['profile_pic'],
                ];
            }

            // ── Merge community posts ──────────────────────────────────────
            $allCommunityPosts = $_SESSION['community_posts'] ?? [];
            $myEmail = $_SESSION['user']['email'] ?? '';
            foreach ($allCommunityPosts as $communityId => $commPosts) {
                foreach ($commPosts as $cp) {
                    if (($cp['email'] ?? '') === $myEmail) {
                        // Find community name for the label
                        $commName = $communityId;
                        foreach ($_SESSION['communities'] ?? [] as $comm) {
                            if ($comm['id'] === $communityId) { $commName = $comm['name']; break; }
                        }
                        // Also check seeded communities
                        $seededComms = $_SESSION['seeded_communities'] ?? [];
                        foreach ($seededComms as $sc) {
                            if ($sc['id'] === $communityId) { $commName = $sc['name']; break; }
                        }
                        $allMyPosts[] = [
                            'source'      => 'community',
                            'label'       => $commName,
                            'id'          => $cp['id'] ?? '',
                            'content'     => $cp['content'] ?? '',
                            'attachment'  => null,
                            'time_label'  => $cp['time'] ?? 'Recently',
                            'username'    => $cp['user'] ?? $_SESSION['user']['username'],
                            'profile_pic' => $cp['avatar'] ?? ($_SESSION['user']['profile_pic'] ?? null),
                        ];
                    }
                }
            }

            // Sort all posts: DB posts by actual timestamp already ordered;
            // session/community posts are prepended so stay at top
            ?>
            <?php if (empty($allMyPosts)): ?>
                <div class="bg-gray-50 rounded-xl p-8 text-center">
                    <div class="text-2xl mb-2">📝</div>
                    <p class="text-gray-500 mb-3">You haven't posted anything yet.</p>
                    <a href="index.php" class="inline-block bg-blue-900 text-white text-sm font-semibold px-5 py-2 rounded-xl hover:bg-blue-800 transition">
                        Create your first post
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                <?php foreach ($allMyPosts as $post): ?>
                    <div class="border border-gray-200 rounded-xl p-4 hover:shadow-sm transition">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-900 font-bold text-sm flex items-center justify-center overflow-hidden flex-shrink-0">
                                <?php if (!empty($post['profile_pic'])): ?>
                                    <img src="<?php echo htmlspecialchars($post['profile_pic']); ?>" alt="" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($_SESSION['user']['username'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($_SESSION['user']['username']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($post['time_label']); ?></p>
                            </div>
                            <?php if ($post['source'] === 'community'): ?>
                                <span class="text-xs bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded-full font-medium flex-shrink-0">
                                    🏘 <?php echo htmlspecialchars($post['label']); ?>
                                </span>
                            <?php elseif ($post['source'] === 'session'): ?>
                                <span class="text-xs bg-amber-50 text-amber-600 border border-amber-200 px-2 py-0.5 rounded-full font-medium flex-shrink-0">Not yet saved</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($post['content'])): ?>
                            <p class="text-gray-800 text-sm leading-relaxed whitespace-pre-wrap break-words"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($post['attachment'])): ?>
                            <?php
                            $ap  = $post['attachment'];
                            $ext = strtolower(pathinfo(parse_url($ap, PHP_URL_PATH) ?: $ap, PATHINFO_EXTENSION));
                            $ext = strtok($ext, '?');
                            $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp'])
                                  || preg_match('#^https?://(images\.unsplash\.com|i\.imgur\.com)#i', $ap);
                            ?>
                            <?php if ($isImg): ?>
                                <img src="<?php echo htmlspecialchars($ap); ?>"
                                     alt="Post image"
                                     class="mt-3 rounded-xl max-h-64 object-cover w-full">
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($ap); ?>"
                                   class="mt-3 inline-flex items-center gap-2 text-blue-700 hover:underline text-sm"
                                   target="_blank" rel="noopener">
                                    📎 <?php echo htmlspecialchars(basename(parse_url($ap, PHP_URL_PATH) ?: $ap)); ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-400 mt-3 text-right"><?php echo count($allMyPosts); ?> post<?php echo count($allMyPosts) !== 1 ? 's' : ''; ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    const bioEditBtn = document.getElementById('bioEditBtn');
    const bioEditBtnText = document.getElementById('bioEditBtnText');
    const bioStripView = document.getElementById('bioStripView');
    const bioStripForm = document.getElementById('bioStripForm');
    let isEditing = false;

    function toggleBioEdit() {
        isEditing = !isEditing;
        if (isEditing) {
            bioStripView.classList.add('hidden');
            bioStripForm.classList.remove('hidden');
            bioEditBtnText.textContent = 'Cancel';
        } else {
            bioStripForm.classList.add('hidden');
            bioStripView.classList.remove('hidden');
            bioEditBtnText.textContent = 'Edit';
        }
    }

    // ── Details strip toggle ─────────────────────────────────────────────
    const detailsEditBtn    = document.getElementById('detailsEditBtn');
    const detailsEditText   = document.getElementById('detailsEditBtnText');
    const detailsView       = document.getElementById('detailsView');
    const detailsForm       = document.getElementById('detailsForm');
    const detailsCancelBtn  = document.getElementById('detailsCancelBtn');

    function openDetailsEdit() {
        detailsView.classList.add('hidden');
        detailsForm.classList.remove('hidden');
        detailsEditText.textContent = 'Cancel';
    }
    function closeDetailsEdit() {
        detailsForm.classList.add('hidden');
        detailsView.classList.remove('hidden');
        detailsEditText.textContent = 'Edit';
    }
    if (detailsEditBtn) {
        detailsEditBtn.addEventListener('click', () => {
            detailsForm.classList.contains('hidden') ? openDetailsEdit() : closeDetailsEdit();
        });
    }
    if (detailsCancelBtn) {
        detailsCancelBtn.addEventListener('click', closeDetailsEdit);
    }

    if (bioEditBtn) {
        bioEditBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleBioEdit();
        });
    }



    const profileUploadBtn = document.getElementById('profileUploadBtn');
    const profilePhotoInput = document.getElementById('profilePhotoInput');
    const profileUploadForm = document.getElementById('profileUploadForm');
    const profilePhotoPreview = document.getElementById('profilePhotoPreview');
    const profileInitial = document.getElementById('profileInitial');

    if (profileUploadBtn && profilePhotoInput && profileUploadForm) {
        profileUploadBtn.addEventListener('click', () => {
            profilePhotoInput.click();
        });

        profilePhotoInput.addEventListener('change', () => {
            if (profilePhotoInput.files && profilePhotoInput.files.length > 0) {
                const file = profilePhotoInput.files[0];
                const maxSize = 5 * 1024 * 1024;
                
                if (file.size > maxSize) {
                    alert("Image is too large. Maximum size is 5MB.");
                    profilePhotoInput.value = '';
                    return;
                }
                
                const objectURL = URL.createObjectURL(file);
                
                if (profilePhotoPreview) {
                    profilePhotoPreview.src = objectURL;
                    profilePhotoPreview.classList.remove('hidden');
                } else {
                    const newImg = document.createElement('img');
                    newImg.id = 'profilePhotoPreview';
                    newImg.src = objectURL;
                    newImg.alt = 'Profile photo';
                    newImg.className = 'w-full h-full object-cover';
                    if (profileInitial) {
                        profileInitial.classList.add('hidden');
                    }
                    profileUploadBtn.insertBefore(newImg, profileUploadBtn.firstChild);
                }
                
                profileUploadForm.submit();
            }
        });
    }
})();
</script>

<?php include 'includes/footer.php'; ?>
