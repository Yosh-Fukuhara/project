<?php
require_once 'includes/bootstrap.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

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

        <div class="bg-white border border-gray-200 rounded-xl p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-2">Posts</h2>
            <p class="text-gray-500 mb-4">Your recent posts will appear here.</p>
            <div class="bg-gray-800/5 rounded-xl p-6 text-center">
                <div class="text-2xl mb-2">📝</div>
                <p class="text-gray-500">This user hasn't posted anything yet.</p>
            </div>
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
