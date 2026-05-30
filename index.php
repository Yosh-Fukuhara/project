<?php
require_once 'includes/bootstrap.php';
require_once 'autoload.php';

$pageTitle = 'CyberSphere - Tech & Cybersecurity Marketplace';
$currentPage = 'home';

// Session-backed stores are initialized in includes/bootstrap.php

$postErrors = [];
$postSuccess = '';

// Show success after redirect (prevents duplicate posts on refresh)
if (isset($_GET['posted']) && $_GET['posted'] === '1') {
    $postSuccess = 'Post created successfully!';
}
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $postSuccess = 'Post deleted successfully!';
}
if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $postSuccess = 'Post updated successfully!';
}

// Ensure every session post has an id (needed for delete)
foreach ($_SESSION['posts'] as $i => $p) {
    if (isset($p['type']) && $p['type'] === 'user' && empty($p['id'])) {
        $_SESSION['posts'][$i]['id'] = 'p_' . uniqid('', true);
    }
}

function savePostAttachment(?array $file, string $uploadDirAbs, array &$postErrors): ?array
{
    if (!$file || !isset($file['error'])) {
        return null;
    }
    if (is_array($file['error'])) {
        $postErrors[] = 'Invalid upload.';
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $postErrors[] = 'Upload failed. Please try again.';
        return null;
    }

    // 25MB max for attachments
    if (($file['size'] ?? 0) > 25 * 1024 * 1024) {
        $postErrors[] = 'File is too large (max 25MB).';
        return null;
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        $postErrors[] = 'Invalid upload.';
        return null;
    }

    // Allow common types (we prefer MIME detection if available,
    // but this project may run without the "fileinfo" extension.)
    $allowedByMime = [
        // Images
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        // Videos
        'video/mp4'  => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        // Documents
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    // Fallback: extension-based validation (works without fileinfo)
    $extFromName = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $mimeByExt = [
        // Images
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        // Videos
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        // Documents
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    $mime = 'application/octet-stream';
    $ext = null;

    if (class_exists('finfo')) {
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($tmp) ?: 'application/octet-stream';
            if (isset($allowedByMime[$detected])) {
                $mime = $detected;
                $ext = $allowedByMime[$detected];
            }
        } catch (Throwable $e) {
            // ignore and fallback to extension checks
        }
    }

    // If MIME detection isn't available (or not allowed), use extension-based allow-list
    if ($ext === null) {
        if (!isset($mimeByExt[$extFromName])) {
            $postErrors[] = 'Unsupported file type.';
            return null;
        }
        $mime = $mimeByExt[$extFromName];
        // Normalize jpeg -> jpg to match older naming
        $ext = $extFromName === 'jpeg' ? 'jpg' : $extFromName;
    }

    if (!is_dir($uploadDirAbs)) {
        @mkdir($uploadDirAbs, 0777, true);
    }

    $filename = uniqid('post_', true) . '.' . $ext;
    $destAbs = rtrim($uploadDirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destAbs)) {
        $postErrors[] = 'Could not save the file. Please try again.';
        return null;
    }

    $kind = 'document';
    if (str_starts_with($mime, 'image/')) $kind = 'image';
    if (str_starts_with($mime, 'video/')) $kind = 'video';

    return [
        'path' => 'uploads/' . $filename,
        'mime' => $mime,
        'kind' => $kind,
        'name' => $file['name'] ?? $filename
    ];
}

function safeUnlinkUpload(?string $relativePath): void
{
    if (!$relativePath) return;
    // Only allow deleting files inside /uploads
    if (!str_starts_with($relativePath, 'uploads/')) return;
    $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($abs)) {
        @unlink($abs);
    }
}

// Create a post (text + optional attachment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
    if (!isset($_SESSION['user'])) {
        $postErrors[] = 'Please sign in to create a post.';
    } else {
        $content = trim($_POST['content'] ?? '');
        if ($content === '' && (empty($_FILES['attachment']) || ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
            $postErrors[] = 'Please write something or attach a file.';
        }

        $attachment = savePostAttachment($_FILES['attachment'] ?? null, __DIR__ . DIRECTORY_SEPARATOR . 'uploads', $postErrors);
        $attachmentPath = $attachment ? $attachment['path'] : null;

        if (empty($postErrors)) {
            // Save to database
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('INSERT INTO posts (user_id, content, attachment_path) VALUES (?, ?, ?)');
            $stmt->execute([
                $_SESSION['user']['id'],
                $content,
                $attachmentPath
            ]);
            $postId = $pdo->lastInsertId();

            // Also keep in session for backward compatibility
            $allowedTags = ['#Hiring', '#OpenToWork', '#Networking', '#OpenToCollaborate', '#JobSeeker', '#Freelance', '#Announcement'];
            $selectedTags = [];
            foreach (($_POST['tags'] ?? []) as $t) {
                if (in_array($t, $allowedTags)) $selectedTags[] = $t;
            }
            $isHiringPost = in_array('#Hiring', $selectedTags);
            $newPost = [
                'type' => 'user',
                'id' => $postId,
                'username' => $_SESSION['user']['username'],
                'email' => $_SESSION['user']['email'],
                'avatar' => $_SESSION['user']['profile_pic'] ?? null,
                'time' => date('M j, Y g:i A'),
                'content' => $content,
                'attachment' => $attachment,
                'tags' => $selectedTags,
                'hiring' => $isHiringPost,
                'enable_apply' => $isHiringPost && !empty($_POST['enable_apply']),
            ];
            array_unshift($_SESSION['posts'], $newPost);

            // Notification: own post uploaded
            array_unshift($_SESSION['notifications'], [
                'msg' => 'Your post was published successfully.',
                'time' => date('M j, Y g:i A'),
                'read' => false,
                'link' => 'index.php?post=' . urlencode($postId),
            ]);

            // Post/Redirect/Get to avoid duplicate submits on refresh
            header('Location: index.php?posted=1');
            exit;
        }
    }
}

// Update a post (text + optional new attachment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_post') {
    if (!isset($_SESSION['user'])) {
        $postErrors[] = 'Please sign in to edit posts.';
    } else {
        $postId = $_POST['post_id'] ?? '';
        $content = trim($_POST['content'] ?? '');

        if ($postId === '') {
            $postErrors[] = 'Missing post id.';
        }

        // Optional attachment replacement
        $newAttachment = savePostAttachment($_FILES['attachment'] ?? null, __DIR__ . DIRECTORY_SEPARATOR . 'uploads', $postErrors);

        if (empty($postErrors)) {
            $updated = false;
            foreach ($_SESSION['posts'] as $idx => $p) {
                if (($p['id'] ?? '') === $postId) {
                    $isOwner = ($p['type'] ?? '') === 'user'
                        && (($p['email'] ?? '') === ($_SESSION['user']['email'] ?? ''));

                    if (!$isOwner) {
                        $postErrors[] = 'You can only edit your own posts.';
                        break;
                    }

                    $_SESSION['posts'][$idx]['content'] = $content;

                    if ($newAttachment) {
                        $old = $_SESSION['posts'][$idx]['attachment']['path'] ?? null;
                        safeUnlinkUpload($old);
                        $_SESSION['posts'][$idx]['attachment'] = $newAttachment;
                    }

                    $updated = true;
                    break;
                }
            }

            if ($updated) {
                header('Location: index.php?updated=1');
                exit;
            } else {
                $postErrors[] = 'Post not found.';
            }
        }
    }
}

// Delete a post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_post') {
    if (!isset($_SESSION['user'])) {
        $postErrors[] = 'Please sign in to delete posts.';
    } else {
        $postId = $_POST['post_id'] ?? '';
        if ($postId === '') {
            $postErrors[] = 'Missing post id.';
        } else {
            $deleted = false;
            foreach ($_SESSION['posts'] as $idx => $p) {
                if (($p['id'] ?? '') === $postId) {
                    // Only allow deleting own posts
                    $isOwner = ($p['type'] ?? '') === 'user'
                        && (($p['email'] ?? '') === ($_SESSION['user']['email'] ?? ''));
                    if (!$isOwner) {
                        $postErrors[] = 'You can only delete your own posts.';
                    } else {
                        safeUnlinkUpload($p['attachment']['path'] ?? null);
                        array_splice($_SESSION['posts'], $idx, 1);
                        $deleted = true;
                    }
                    break;
                }
            }

            if ($deleted) {
                header('Location: index.php?deleted=1');
                exit;
            } elseif (empty($postErrors)) {
                $postErrors[] = 'Post not found.';
            }
        }
    }
}

// ── AJAX: React to a post ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'react_post') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user'])) {
        echo json_encode(['ok' => false, 'msg' => 'Login required']);
        exit;
    }
    $pid  = $_POST['post_id'] ?? '';
    $emoji = $_POST['emoji'] ?? '';
    $allowed = ['👍', '❤️', '🎉', '💡', '👏'];
    if (!$pid || !in_array($emoji, $allowed)) {
        echo json_encode(['ok' => false]);
        exit;
    }
    if (!isset($_SESSION['reactions'][$pid])) $_SESSION['reactions'][$pid] = [];
    // toggle off
    $prev = $_SESSION['my_reactions'][$pid] ?? null;
    if ($prev) {
        $_SESSION['reactions'][$pid][$prev] = max(0, ($_SESSION['reactions'][$pid][$prev] ?? 1) - 1);
    }
    if ($prev !== $emoji) {
        $_SESSION['reactions'][$pid][$emoji] = ($_SESSION['reactions'][$pid][$emoji] ?? 0) + 1;
        $_SESSION['my_reactions'][$pid] = $emoji;
        // find post owner and notify
        foreach ($_SESSION['posts'] as $p) {
            if (($p['id'] ?? '') === $pid) {
                if (($p['email'] ?? '') !== ($_SESSION['user']['email'] ?? '')) {
                    array_unshift($_SESSION['notifications'], [
                        'msg' => htmlspecialchars($_SESSION['user']['username']) . ' reacted ' . $emoji . ' to your post.',
                        'time' => date('M j, Y g:i A'),
                        'read' => false,
                        'link' => 'index.php?post=' . urlencode($pid),
                    ]);
                }
                break;
            }
        }
    } else {
        unset($_SESSION['my_reactions'][$pid]);
    }
    echo json_encode(['ok' => true, 'reactions' => $_SESSION['reactions'][$pid], 'mine' => $_SESSION['my_reactions'][$pid] ?? null]);
    exit;
}

// ── AJAX: Add a comment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user'])) {
        echo json_encode(['ok' => false, 'msg' => 'Login required']);
        exit;
    }
    $pid  = $_POST['post_id'] ?? '';
    $text = trim($_POST['text'] ?? '');
    if (!$pid || $text === '') {
        echo json_encode(['ok' => false]);
        exit;
    }
    $commentId = 'c_' . uniqid('', true);
    $comment = [
        'id'     => $commentId,
        'user'   => $_SESSION['user']['username'],
        'avatar' => $_SESSION['user']['profile_pic'] ?? null,
        'text'   => $text,
        'time'   => date('M j, Y g:i A'),
    ];
    if (!isset($_SESSION['comments'][$pid])) $_SESSION['comments'][$pid] = [];
    $_SESSION['comments'][$pid][] = $comment;
    // notify post owner
    foreach ($_SESSION['posts'] as $p) {
        if (($p['id'] ?? '') === $pid) {
            if (($p['email'] ?? '') !== ($_SESSION['user']['email'] ?? '')) {
                array_unshift($_SESSION['notifications'], [
                    'msg' => htmlspecialchars($_SESSION['user']['username']) . ' commented on your post.',
                    'time' => date('M j, Y g:i A'),
                    'read' => false,
                    'link' => 'index.php?post=' . urlencode($pid) . '&comment=' . urlencode($commentId),
                ]);
            }
            break;
        }
    }
    echo json_encode(['ok' => true, 'comment' => $comment, 'total' => count($_SESSION['comments'][$pid])]);
    exit;
}

// ── AJAX: Share a post ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'share_post') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user'])) {
        echo json_encode(['ok' => false, 'msg' => 'Login required']);
        exit;
    }
    $pid = $_POST['post_id'] ?? '';
    $shareText = trim($_POST['share_text'] ?? '');
    $postJson = $_POST['post_json'] ?? '';
    if (!$pid) {
        echo json_encode(['ok' => false]);
        exit;
    }

    // Optional caption length limit (keeps layout stable)
    if (cs_strlen($shareText) > 1000) {
        echo json_encode(['ok' => false, 'msg' => 'Share caption is too long.']);
        exit;
    }

    // Increment share counter for the *original* post id
    $_SESSION['shares'][$pid] = ($_SESSION['shares'][$pid] ?? 0) + 1;

    // Try to locate the original post in session (works for user-created posts)
    $original = null;
    foreach (($_SESSION['posts'] ?? []) as $p) {
        if (($p['id'] ?? '') === $pid) {
            $original = $p;
            break;
        }
    }

    // Fallback: accept a compact JSON snapshot from the client (works for static/x posts)
    if (!$original && $postJson) {
        $decoded = json_decode($postJson, true);
        if (is_array($decoded)) {
            // Only keep fields we render in the shared preview (avoid storing unexpected payload)
            $original = [
                'id' => $decoded['id'] ?? $pid,
                'type' => $decoded['type'] ?? null,
                'username' => $decoded['username'] ?? null,
                'email' => $decoded['email'] ?? null,
                'avatar' => $decoded['avatar'] ?? null,
                'company' => $decoded['company'] ?? null,
                'name' => $decoded['name'] ?? null,
                'logo' => $decoded['logo'] ?? null,
                'title' => $decoded['title'] ?? null,
                'time' => $decoded['time'] ?? null,
                'content' => $decoded['content'] ?? '',
                'attachment' => $decoded['attachment'] ?? null,
                'image' => $decoded['image'] ?? null,
                'tags' => $decoded['tags'] ?? null,
                'skills' => $decoded['skills'] ?? null,
                'badge' => $decoded['badge'] ?? null,
            ];
        }
    }

    // Create a "Facebook-style" share: a new post by the current user that contains a preview of the original post.
    $newPost = [
        'type' => 'user',
        'id' => 'p_' . uniqid('', true),
        'username' => $_SESSION['user']['username'],
        'email' => $_SESSION['user']['email'],
        'avatar' => $_SESSION['user']['profile_pic'] ?? null,
        'time' => date('M j, Y g:i A'),
        'content' => $shareText,
        'shared_from' => $pid,
        'shared_post' => $original,
    ];
    array_unshift($_SESSION['posts'], $newPost);

    // Notify original owner (if we know it)
    if ($original && !empty($original['email']) && ($original['email'] !== ($_SESSION['user']['email'] ?? ''))) {
        array_unshift($_SESSION['notifications'], [
            'msg' => htmlspecialchars($_SESSION['user']['username']) . ' shared your post.',
            'time' => date('M j, Y g:i A'),
            'read' => false,
            'link' => 'index.php?post=' . urlencode($pid),
        ]);
    }

    echo json_encode(['ok' => true, 'shares' => $_SESSION['shares'][$pid]]);
    exit;
}

// ── AJAX: Submit job application ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_job') {
    header('Content-Type: application/json');
    $pid      = $_POST['post_id'] ?? '';
    $appName  = trim($_POST['app_name'] ?? '');
    $appEmail = trim($_POST['app_email'] ?? '');
    $appPhone = trim($_POST['app_phone'] ?? '');
    $appMsg   = trim($_POST['app_message'] ?? '');
    if (!$pid || !$appName || !$appEmail) {
        echo json_encode(['ok' => false, 'msg' => 'Name and email are required.']);
        exit;
    }
    // Handle resume upload
    $resumePath = null;
    if (!empty($_FILES['app_resume']['tmp_name'])) {
        $resumeErrors = [];
        $resume = savePostAttachment($_FILES['app_resume'], __DIR__ . DIRECTORY_SEPARATOR . 'uploads', $resumeErrors);
        if ($resume) $resumePath = $resume['path'];
    }
    if (!isset($_SESSION['applications'][$pid])) $_SESSION['applications'][$pid] = [];
    $appId = 'app_' . uniqid('', true);
    $application = [
        'id'      => $appId,
        'name'    => $appName,
        'email'   => $appEmail,
        'phone'   => $appPhone,
        'message' => $appMsg,
        'resume'  => $resumePath,
        'time'    => date('M j, Y g:i A'),
        'status'  => 'pending',
    ];
    $_SESSION['applications'][$pid][] = $application;

    // Save to user's own applications
    if (isset($_SESSION['user'])) {
        $postTitle = '';
        foreach ($_SESSION['posts'] as $post) {
            if (($post['id'] ?? '') === $pid) {
                $postTitle = substr($post['content'] ?? 'Job Posting', 0, 50);
                break;
            }
        }
        $_SESSION['my_applications'][] = [
            'postId' => $pid,
            'postTitle' => $postTitle,
            'time' => $application['time'],
            'status' => $application['status'],
        ];
    }

    // Notify post owner if logged in
    if (isset($_SESSION['user'])) {
        array_unshift($_SESSION['notifications'], [
            'msg' => htmlspecialchars($appName) . ' applied to your job posting.',
            'time' => date('M j, Y g:i A'),
            'read' => false,
            'link' => 'index.php?post=' . urlencode($pid),
        ]);
    }
    echo json_encode(['ok' => true, 'msg' => 'Application submitted successfully!']);
    exit;
}

// ── AJAX: Update application status (accept/reject) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_app_status') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user'])) {
        echo json_encode(['ok' => false, 'msg' => 'Login required']);
        exit;
    }
    $pid = $_POST['post_id'] ?? '';
    $appId = $_POST['app_id'] ?? '';
    $status = $_POST['status'] ?? '';
    if (!$pid || !$appId || !in_array($status, ['accepted', 'rejected'])) {
        echo json_encode(['ok' => false]);
        exit;
    }
    $updated = false;
    if (isset($_SESSION['applications'][$pid])) {
        foreach ($_SESSION['applications'][$pid] as &$app) {
            if (($app['id'] ?? '') === $appId) {
                $app['status'] = $status;
                $updated = true;
                break;
            }
        }
        unset($app);
    }
    echo json_encode(['ok' => $updated]);
    exit;
}

// ── AJAX: Save/unsave job ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_save_job') {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user'])) {
        echo json_encode(['ok' => false, 'msg' => 'Login required']);
        exit;
    }
    $pid = $_POST['post_id'] ?? '';
    if (!$pid) {
        echo json_encode(['ok' => false]);
        exit;
    }
    $index = array_search($pid, $_SESSION['saved_jobs']);
    if ($index !== false) {
        array_splice($_SESSION['saved_jobs'], $index, 1);
        echo json_encode(['ok' => true, 'saved' => false]);
    } else {
        $_SESSION['saved_jobs'][] = $pid;
        echo json_encode(['ok' => true, 'saved' => true]);
    }
    exit;
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-3">
            <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition">
                <a href="profile.php" class="block">
                    <?php if (isset($_SESSION['user']) && !empty($_SESSION['user']['cover_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['user']['cover_pic']); ?>" alt="Cover photo" class="w-full h-20 object-cover">
                    <?php else: ?>
                        <div class="bg-gradient-to-r from-blue-900 to-cyan-800 h-20"></div>
                    <?php endif; ?>
                    <div class="px-4 pb-4 -mt-10 text-center">
                        <div class="w-20 h-20 mx-auto bg-gray-200 rounded-full border-4 border-white flex items-center justify-center text-3xl text-blue-800 overflow-hidden">
                            <?php if (isset($_SESSION['user']) && !empty($_SESSION['user']['profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['user']['profile_pic']); ?>" alt="Profile photo" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo isset($_SESSION['user']) ? strtoupper(substr($_SESSION['user']['username'], 0, 1)) : 'G'; ?>
                            <?php endif; ?>
                        </div>
                        <h3 class="mt-2 font-semibold text-gray-800 hover:underline">
                            <?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']['username']) : 'Guest User'; ?>
                        </h3>
                        <p class="text-gray-500 text-sm">
                            <?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']['email']) : 'Not logged in'; ?>
                        </p>
                    </div>
                </a>
                <div class="border-t border-gray-200 px-4 py-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Profile views</span>
                        <span class="font-semibold text-gray-800"><?php echo rand(100, 5000); ?></span>
                    </div>
                    <div class="flex justify-between text-sm mt-2">
                        <span class="text-gray-600">Post impressions</span>
                        <span class="font-semibold text-gray-800"><?php echo rand(500, 10000); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-6">
            <div class="bg-white rounded-xl shadow-md p-4 mb-6">
                <?php if (!empty($postSuccess)): ?>
                    <div data-autodismiss="5000" class="bg-green-100 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4">
                        <?php echo htmlspecialchars($postSuccess); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($postErrors)): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                        <ul class="list-disc list-inside">
                            <?php foreach ($postErrors as $e): ?>
                                <li><?php echo htmlspecialchars($e); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center text-xl text-blue-800 overflow-hidden">
                        <?php if (isset($_SESSION['user']) && !empty($_SESSION['user']['profile_pic'])): ?>
                            <img src="<?php echo htmlspecialchars($_SESSION['user']['profile_pic']); ?>" alt="Profile photo" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php echo isset($_SESSION['user']) ? strtoupper(substr($_SESSION['user']['username'], 0, 1)) : 'G'; ?>
                        <?php endif; ?>
                    </div>
                    <button
                        type="button"
                        id="startPostBtn"
                        class="flex-1 text-left px-4 py-2 bg-gray-100 rounded-full border border-gray-200 hover:border-blue-500 transition text-gray-500">
                        Start a post
                    </button>
                </div>
                <div class="flex gap-4 pt-3 border-t border-gray-200">
                    <button type="button" data-compose="photo" class="composeBtn flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-gray-600 font-medium">Photo</span>
                    </button>
                    <button type="button" data-compose="video" class="composeBtn flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-6 h-6 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-gray-600 font-medium">Video</span>
                    </button>
                    <button type="button" data-compose="document" class="composeBtn flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-gray-600 font-medium">Document</span>
                    </button>
                </div>
            </div>

            <div id="composerBackdrop" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
                <div class="bg-white w-full max-w-xl rounded-2xl shadow-xl overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                        <h3 id="composerTitle" class="text-lg font-bold text-gray-900">Create post</h3>
                        <button id="composerClose" type="button" class="text-gray-500 hover:text-gray-800">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form id="composerForm" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                        <input id="composerAction" type="hidden" name="action" value="create_post">
                        <input id="composerPostId" type="hidden" name="post_id" value="">

                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gray-200 rounded-full overflow-hidden flex items-center justify-center text-xl text-blue-800">
                                <?php if (isset($_SESSION['user']) && !empty($_SESSION['user']['profile_pic'])): ?>
                                    <img src="<?php echo htmlspecialchars($_SESSION['user']['profile_pic']); ?>" alt="Profile photo" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo isset($_SESSION['user']) ? strtoupper(substr($_SESSION['user']['username'], 0, 1)) : 'G'; ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">
                                    <?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']['username']) : 'Guest'; ?>
                                </p>
                                <p class="text-xs text-gray-500">Posting to Home</p>
                            </div>
                        </div>

                        <div>
                            <textarea
                                id="composerContent"
                                name="content"
                                rows="4"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="What's on your mind?"></textarea>
                        </div>

                        <div id="tagPickerWrap">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Add Tags</label>
                            <div class="flex flex-wrap gap-2" id="tagList">
                                <?php foreach (['#Hiring', '#OpenToWork', '#Networking', '#OpenToCollaborate', '#JobSeeker', '#Freelance', '#Announcement'] as $tag): ?>
                                    <label class="cursor-pointer">
                                        <input type="checkbox" name="tags[]" value="<?php echo $tag; ?>" class="hidden tagCheckbox" data-tag="<?php echo $tag; ?>">
                                        <span class="tag-pill inline-block px-3 py-1 rounded-full text-sm font-medium border border-gray-300 text-gray-600 hover:border-blue-500 hover:text-blue-700 transition select-none"><?php echo $tag; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="hiringApplyToggleWrap" class="hidden">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" id="enableApplyFeature" name="enable_apply" value="1" class="w-4 h-4 accent-blue-900">
                                <span class="text-sm font-semibold text-gray-700">Enable "Apply" button on this post (collect resumes &amp; info)</span>
                            </label>
                        </div>

                        <div id="attachmentWrap" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Attachment</label>
                            <input id="attachmentInput" type="file" name="attachment" class="w-full">
                            <p id="attachmentHelp" class="text-xs text-gray-400 mt-2"></p>
                        </div>

                        <button
                            id="composerSubmit"
                            type="submit"
                            class="w-full bg-blue-900 text-white font-bold py-3 rounded-xl hover:bg-blue-800 transition disabled:opacity-60"
                            <?php echo isset($_SESSION['user']) ? '' : 'disabled'; ?>>
                            Post
                        </button>

                        <?php if (!isset($_SESSION['user'])): ?>
                            <p class="text-sm text-gray-500 text-center">
                                Please <a class="text-blue-900 font-semibold hover:underline" href="login.php">sign in</a> to create a post.
                            </p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php
            $dbPosts = [];
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->query('SELECT p.*, u.username, u.email, u.profile_pic as avatar FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC');
                while ($row = $stmt->fetch()) {
                    $dbPosts[] = [
                        'type' => 'user',
                        'id' => $row['id'],
                        'username' => $row['username'],
                        'email' => $row['email'],
                        'avatar' => $row['avatar'],
                        'time' => date('M j, Y g:i A', strtotime($row['created_at'])),
                        'content' => $row['content'],
                        'attachment' => $row['attachment_path'] ? ['path' => $row['attachment_path']] : null,
                        'tags' => [],
                        'hiring' => false,
                        'enable_apply' => false,
                    ];
                }
            } catch (Exception $e) {
                // Fallback to session
            }

            $posts = array_merge($dbPosts, $_SESSION['posts'], [
                [
                    'type' => 'job',
                    'company' => 'NetSentinel Solutions',
                    'logo' => '🛡️',
                    'time' => '2h ago',
                    'followers' => '1,450',
                    'badge' => '#Hiring',
                    'content' => 'We are looking for: SOC analyst, Full stack Developer, Graphic Designer, and Data analyst. Submit your credentials and apply now!',
                    'image' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800&h=400&fit=crop'
                ],
                [
                    'type' => 'seeking',
                    'name' => 'Marcus Vane',
                    'title' => 'CISSP Certified gThreat Hunter',
                    'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop&crop=face',
                    'time' => '1d ago',
                    'badge' => '#OpenToWork',
                    'content' => 'I am currently seeking a new challenge in Digital Forensics and Incident Response (DFIR). 8+ years of experience in enterprise defense. Open to hybrid roles in London.',
                    'skills' => ['DFIR', 'Malware Analysis', 'Splunk']
                ]
            ]);

            // Search filter implementation
            $searchQuery = trim($_GET['q'] ?? '');
            if ($searchQuery !== '') {
                $posts = array_filter($posts, function ($post) use ($searchQuery) {
                    $content = $post['content'] ?? '';
                    $author = $post['username'] ?? $post['name'] ?? $post['company'] ?? '';
                    $tags = implode(' ', $post['tags'] ?? []);
                    $skills = implode(' ', $post['skills'] ?? []);
                    $searchable = $content . ' ' . $author . ' ' . $tags . ' ' . $skills;
                    return stripos($searchable, $searchQuery) !== false;
                });
            }

            foreach ($posts as $post):
                $pid = $post['id'] ?? ('static_' . md5($post['company'] ?? $post['name'] ?? '') . '_' . ($post['time'] ?? ''));
                // For shared posts: the Share button should share the original post
                $shareTargetId = $post['shared_from'] ?? $pid;

                // Build the correct profile URL:
                // - own posts → profile.php (edit access)
                // - other users → view_profile.php directly (no double-redirect)
                // - static job/seeking posts with no email → view_profile.php by name
                $authorId = isset($post['type']) && $post['type'] === 'user' ? ($post['email'] ?? '') : ($post['name'] ?? $post['company'] ?? '');
                $loggedInEmail = $_SESSION['user']['email'] ?? '';
                $isOwnPost = (
                    $post['type'] === 'user' &&
                    !empty($authorId) &&
                    strcasecmp($authorId, $loggedInEmail) === 0
                );
                if ($isOwnPost) {
                    $authorProfileUrl = 'profile.php';
                } elseif (!empty($post['email'])) {
                    $authorProfileUrl = 'view_profile.php?username=' . urlencode($post['username'] ?? $authorId);
                } else {
                    $authorProfileUrl = 'view_profile.php?username=' . urlencode($authorId);
                }

                // Compact JSON snapshot used by the Share modal and as a fallback on the server for non-session posts.
                // If this is already a shared post, use the *original* snapshot for the preview.
                $shareSource = (!empty($post['shared_post']) && is_array($post['shared_post'])) ? $post['shared_post'] : $post;
                $sharePayload = [
                    'id' => $shareTargetId,
                    'type' => $shareSource['type'] ?? null,
                    'username' => $shareSource['username'] ?? null,
                    'email' => $shareSource['email'] ?? null,
                    'avatar' => $shareSource['avatar'] ?? null,
                    'company' => $shareSource['company'] ?? null,
                    'name' => $shareSource['name'] ?? null,
                    'logo' => $shareSource['logo'] ?? null,
                    'title' => $shareSource['title'] ?? null,
                    'time' => $shareSource['time'] ?? null,
                    'content' => $shareSource['content'] ?? '',
                    'attachment' => $shareSource['attachment'] ?? null,
                    'image' => $shareSource['image'] ?? null,
                    'tags' => $shareSource['tags'] ?? null,
                    'skills' => $shareSource['skills'] ?? null,
                    'badge' => $shareSource['badge'] ?? null,
                ];
                $sharePayloadJson = htmlspecialchars(json_encode($sharePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="bg-white rounded-xl shadow-md mb-4 overflow-hidden" data-post-card data-post-id="<?php echo htmlspecialchars($pid); ?>">
                    <div class="p-4">
                        <div class="flex items-start gap-3">
                            <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="flex-shrink-0">
                                <?php if (isset($post['type']) && $post['type'] === 'user'): ?>
                                    <div class="w-12 h-12 bg-gray-200 rounded-full overflow-hidden flex items-center justify-center text-2xl text-blue-900 font-bold">
                                        <?php if (!empty($post['avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($post['avatar']); ?>" alt="Profile photo" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-2xl overflow-hidden">
                                        <?php if (!empty($post['avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($post['avatar']); ?>" alt="Avatar" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <?php echo $post['logo'] ?? '👤'; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <a href="<?php echo htmlspecialchars($authorProfileUrl); ?>" class="hover:underline">
                                            <h4 class="font-bold text-gray-800">
                                                <?php
                                                if (isset($post['type']) && $post['type'] === 'user') {
                                                    echo htmlspecialchars($post['username']);
                                                } else {
                                                    echo htmlspecialchars($post['company'] ?? $post['name']);
                                                }
                                                ?>
                                            </h4>
                                        </a>
                                        <p class="text-gray-500 text-sm">
                                            <?php
                                            if (isset($post['type']) && $post['type'] === 'user') {
                                                echo htmlspecialchars($post['email']) . ' • ' . htmlspecialchars($post['time']);
                                            } else {
                                                echo isset($post['followers']) ? htmlspecialchars($post['followers']) . ' followers' : htmlspecialchars($post['title']);
                                                echo ' • ' . htmlspecialchars($post['time']);
                                            }
                                            ?>
                                        </p>
                                    </div>
                                    <?php if (isset($post['type']) && $post['type'] === 'user' && isset($_SESSION['user']) && !empty($post['id']) && (($post['email'] ?? '') === ($_SESSION['user']['email'] ?? ''))): ?>
                                        <div class="relative">
                                            <button type="button" class="postOptionsBtn p-2 rounded-full hover:bg-gray-100 text-gray-600" aria-label="Options">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6h.01M12 12h.01M12 18h.01"></path>
                                                </svg>
                                            </button>
                                            <div class="postOptionsMenu absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-xl shadow-lg hidden overflow-hidden z-10">
                                                <button
                                                    type="button"
                                                    class="postEditBtn w-full text-left px-4 py-2 text-sm hover:bg-gray-50 text-gray-800"
                                                    data-post-id="<?php echo htmlspecialchars($post['id']); ?>"
                                                    data-post-content="<?php echo htmlspecialchars($post['content']); ?>">
                                                    Edit
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Delete this post?');">
                                                    <input type="hidden" name="action" value="delete_post">
                                                    <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post['id']); ?>">
                                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 text-red-600">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($post['badge'])): ?>
                                    <div class="mt-2">
                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">
                                            <?php echo htmlspecialchars($post['badge']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($post['shared_post'])): ?>
                                    <?php if (trim((string)($post['content'] ?? '')) !== ''): ?>
                                        <p class="mt-3 w-full text-gray-800 text-sm leading-relaxed whitespace-pre-wrap break-words !text-left">
                                            <?php echo nl2br(htmlspecialchars($post['content'] ?? '')); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php
                                    $orig = $post['shared_post'];
                                    $origIsUser = is_array($orig) && (($orig['type'] ?? 'user') === 'user');
                                    $origName = $origIsUser
                                        ? ($orig['username'] ?? 'User')
                                        : ($orig['company'] ?? ($orig['name'] ?? 'Post'));
                                    $origMeta = '';
                                    if ($origIsUser) {
                                        $origMeta = trim(($orig['email'] ?? '') . (empty($orig['time']) ? '' : ' • ' . $orig['time']));
                                    } else {
                                        $metaLeft = $orig['followers'] ?? ($orig['title'] ?? '');
                                        $origMeta = trim(($metaLeft ? ($metaLeft . ' • ') : '') . ($orig['time'] ?? ''));
                                    }
                                    $origText = nl2br(htmlspecialchars(strip_tags((string)($orig['content'] ?? ''))));
                                    ?>
                                    <div class="mt-3 border border-gray-200 rounded-xl overflow-hidden bg-gray-50">
                                        <div class="p-3 flex items-start gap-3">
                                            <div class="w-10 h-10 bg-gray-200 rounded-full overflow-hidden flex items-center justify-center text-lg text-blue-900 font-bold flex-shrink-0">
                                                <?php if ($origIsUser): ?>
                                                    <?php if (!empty($orig['avatar'])): ?>
                                                        <img src="<?php echo htmlspecialchars($orig['avatar']); ?>" alt="" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <?php echo strtoupper(substr((string)$origName, 0, 1)); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($orig['logo'] ?? '👤'); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-bold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($origName); ?></p>
                                                <?php if ($origMeta): ?>
                                                    <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($origMeta); ?></p>
                                                <?php endif; ?>
                                                <?php if (trim(strip_tags((string)($orig['content'] ?? ''))) !== ''): ?>
                                                    <p class="mt-2 text-gray-700 text-sm leading-relaxed line-clamp-4"><?php echo $origText; ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($orig['attachment']) && is_array($orig['attachment'])): ?>
                                            <?php $att = $orig['attachment']; ?>
                                            <?php if (($att['kind'] ?? '') === 'image'): ?>
                                                <div class="border-t border-gray-200 bg-white">
                                                    <img src="<?php echo htmlspecialchars($att['path']); ?>" alt="Shared image" class="w-full">
                                                </div>
                                            <?php elseif (($att['kind'] ?? '') === 'video'): ?>
                                                <div class="border-t border-gray-200 bg-black">
                                                    <video controls class="w-full">
                                                        <source src="<?php echo htmlspecialchars($att['path']); ?>" type="<?php echo htmlspecialchars($att['mime'] ?? 'video/mp4'); ?>">
                                                    </video>
                                                </div>
                                            <?php else: ?>
                                                <div class="border-t border-gray-200 bg-white p-3">
                                                    <a class="text-blue-900 font-semibold hover:underline break-all text-sm" href="<?php echo htmlspecialchars($att['path']); ?>" target="_blank" rel="noopener noreferrer">
                                                        <?php echo htmlspecialchars($att['name'] ?? 'View document'); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif (!empty($orig['image'])): ?>
                                            <div class="border-t border-gray-200 bg-white">
                                                <img src="<?php echo htmlspecialchars($orig['image']); ?>" alt="Shared image" class="w-full">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="mt-4 text-gray-700 leading-relaxed">
                                        <?php echo $post['content']; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (isset($post['type']) && $post['type'] === 'user' && !empty($post['attachment'])): ?>
                                    <?php $att = $post['attachment']; ?>
                                    <?php if (($att['kind'] ?? '') === 'image'): ?>
                                        <div class="mt-4 rounded-lg overflow-hidden border border-gray-200">
                                            <img src="<?php echo htmlspecialchars($att['path']); ?>" alt="Post image" class="w-full">
                                        </div>
                                    <?php elseif (($att['kind'] ?? '') === 'video'): ?>
                                        <div class="mt-4 rounded-lg overflow-hidden border border-gray-200 bg-black">
                                            <video controls class="w-full">
                                                <source src="<?php echo htmlspecialchars($att['path']); ?>" type="<?php echo htmlspecialchars($att['mime'] ?? 'video/mp4'); ?>">
                                                Your browser does not support the video tag.
                                            </video>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-4 p-4 rounded-lg border border-gray-200 bg-gray-50">
                                            <a class="text-blue-900 font-semibold hover:underline break-all" href="<?php echo htmlspecialchars($att['path']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo htmlspecialchars($att['name'] ?? 'View document'); ?>
                                            </a>
                                            <p class="text-xs text-gray-400 mt-1">Click to open</p>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (isset($post['image'])): ?>
                                    <div class="mt-4 rounded-lg overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Post image" class="w-full">
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($post['skills'])): ?>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <?php foreach ($post['skills'] as $skill): ?>
                                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                                <?php echo $skill; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($post['tags'])): ?>
                                    <div class="mt-3 flex flex-wrap gap-1">
                                        <?php foreach ($post['tags'] as $ptag): ?>
                                            <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo htmlspecialchars($ptag); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    $totalReactions = array_sum($_SESSION['reactions'][$pid] ?? []);
                    $myReaction = $_SESSION['my_reactions'][$pid] ?? null;
                    $totalComments = count($_SESSION['comments'][$pid] ?? []);
                    $totalShares = $_SESSION['shares'][$shareTargetId] ?? 0;
                    $isHiring = ($post['type'] ?? '') === 'job' || !empty($post['hiring']);
                    $applyEnabled = ($post['type'] ?? '') === 'job' || !empty($post['enable_apply']);
                    ?>
                    <div class="border-t border-gray-200 px-4 py-2 flex flex-wrap justify-around">
                        <div class="relative group">
                            <button
                                class="react-btn flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition font-medium <?php echo $myReaction ? 'text-blue-700' : 'text-gray-700'; ?>"
                                data-post-id="<?php echo htmlspecialchars($pid); ?>"
                                title="React">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path>
                                </svg>
                                <span class="react-label"><?php echo $myReaction ? $myReaction . ' ' . $totalReactions : 'React' . ($totalReactions > 0 ? ' ' . $totalReactions : ''); ?></span>
                            </button>
                            <div class="emoji-picker absolute bottom-full left-0 mb-1 bg-white border border-gray-200 rounded-xl shadow-xl p-2 flex gap-1 hidden z-20" data-post-id="<?php echo htmlspecialchars($pid); ?>">
                                <?php foreach (['👍', '❤️', '🎉', '💡', '👏'] as $em): ?>
                                    <button class="emoji-opt text-2xl hover:scale-125 transition-transform p-1 rounded-lg hover:bg-gray-100" data-post-id="<?php echo htmlspecialchars($pid); ?>" data-emoji="<?php echo $em; ?>"><?php echo $em; ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button
                            class="comment-toggle-btn flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition text-gray-700 font-medium"
                            data-post-id="<?php echo htmlspecialchars($pid); ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                            </svg>
                            Comment<?php echo $totalComments > 0 ? ' ' . $totalComments : ''; ?>
                        </button>
                        <button
                            class="share-btn flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition text-gray-700 font-medium"
                            data-post-id="<?php echo htmlspecialchars($shareTargetId); ?>"
                            data-post-json="<?php echo $sharePayloadJson; ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                            </svg>
                            Share<?php echo $totalShares > 0 ? ' ' . $totalShares : ''; ?>
                        </button>

                        <?php if (isset($_SESSION['user']) && ($post['email'] ?? '') === $_SESSION['user']['email'] && !empty($_SESSION['applications'][$pid])): ?>
                            <button
                                class="view-apps-toggle flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition text-gray-700 font-medium"
                                data-post-id="<?php echo htmlspecialchars($pid); ?>">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Apps (<?php echo count($_SESSION['applications'][$pid]); ?>)
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="comment-section hidden border-t border-gray-100 px-4 py-3 bg-gray-50" data-post-id="<?php echo htmlspecialchars($pid); ?>">
                        <div class="comments-list space-y-3 mb-3">
                            <?php foreach (($_SESSION['comments'][$pid] ?? []) as $cm): ?>
                                <?php $cmid = $cm['id'] ?? ''; ?>
                                <div <?php echo $cmid ? ('id="comment_' . htmlspecialchars($cmid) . '"') : ''; ?> class="flex gap-2 items-start">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-900 font-bold text-sm overflow-hidden flex-shrink-0">
                                        <?php if (!empty($cm['avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($cm['avatar']); ?>" class="w-full h-full object-cover" alt="">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($cm['user'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bg-white rounded-xl px-3 py-2 text-sm shadow-sm flex-1">
                                        <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($cm['user']); ?></span>
                                        <span class="text-gray-400 text-xs ml-2"><?php echo htmlspecialchars($cm['time']); ?></span>
                                        <p class="text-gray-700 mt-0.5"><?php echo htmlspecialchars($cm['text']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($_SESSION['user'])): ?>
                            <div class="flex gap-2">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-900 font-bold text-sm overflow-hidden flex-shrink-0">
                                    <?php if (!empty($_SESSION['user']['profile_pic'])): ?>
                                        <img src="<?php echo htmlspecialchars($_SESSION['user']['profile_pic']); ?>" class="w-full h-full object-cover" alt="">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['user']['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <input type="text" class="comment-input flex-1 px-3 py-2 rounded-full border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400" placeholder="Write a comment…" data-post-id="<?php echo htmlspecialchars($pid); ?>">
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">Please <a href="login.php" class="text-blue-900 font-semibold hover:underline">sign in</a> to comment.</p>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($_SESSION['user']) && ($post['email'] ?? '') === $_SESSION['user']['email'] && !empty($_SESSION['applications'][$pid])): ?>
                        <div class="apps-section hidden border-t border-gray-100 px-4 py-3 bg-blue-50" data-post-id="<?php echo htmlspecialchars($pid); ?>">
                            <h5 class="font-bold text-gray-800 text-sm mb-3">Job Applications</h5>
                            <div class="space-y-3">
                                <?php foreach ($_SESSION['applications'][$pid] as $app): ?>
                                    <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-200 text-sm">
                                        <div class="flex justify-between items-start mb-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-gray-800"><?php echo htmlspecialchars($app['name']); ?></span>
                                                <span class="text-xs px-2 py-0.5 rounded-full <?php echo ($app['status'] ?? 'pending') === 'pending' ? 'bg-yellow-100 text-yellow-800' : (($app['status'] ?? 'pending') === 'accepted' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo ucfirst($app['status'] ?? 'pending'); ?>
                                                </span>
                                            </div>
                                            <span class="text-xs text-gray-400"><?php echo htmlspecialchars($app['time']); ?></span>
                                        </div>
                                        <div class="text-gray-600 flex gap-4 mb-2">
                                            <span>📧 <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>" class="hover:underline text-blue-700"><?php echo htmlspecialchars($app['email']); ?></a></span>
                                            <?php if (!empty($app['phone'])): ?>
                                                <span>📱 <?php echo htmlspecialchars($app['phone']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($app['message'])): ?>
                                            <p class="text-gray-700 mb-2 italic bg-gray-50 p-2 rounded">"<?php echo nl2br(htmlspecialchars($app['message'])); ?>"</p>
                                        <?php endif; ?>
                                        <?php if (!empty($app['resume'])): ?>
                                            <a href="<?php echo htmlspecialchars($app['resume']); ?>" target="_blank" class="inline-block text-xs font-semibold bg-blue-100 text-blue-800 px-3 py-1 rounded hover:bg-blue-200 transition mb-2">📄 View Resume</a>
                                        <?php endif; ?>
                                        <?php if (($app['status'] ?? 'pending') === 'pending'): ?>
                                            <div class="flex gap-2 mt-2">
                                                <button class="accept-app-btn text-xs font-semibold bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition" data-post-id="<?php echo htmlspecialchars($pid); ?>" data-app-id="<?php echo htmlspecialchars($app['id'] ?? ''); ?>">Accept</button>
                                                <button class="reject-app-btn text-xs font-semibold bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition" data-post-id="<?php echo htmlspecialchars($pid); ?>" data-app-id="<?php echo htmlspecialchars($app['id'] ?? ''); ?>">Reject</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($applyEnabled): ?>
                        <div class="border-t border-gray-100 px-4 py-3 bg-blue-50 flex gap-3">
                            <button class="apply-modal-btn flex-1 bg-pink-700 hover:bg-pink-800 text-white font-bold py-2 rounded-xl transition text-sm" data-post-id="<?php echo htmlspecialchars($pid); ?>">
                                Apply Now
                            </button>
                            <button class="save-job-btn px-6 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 font-bold py-2 rounded-xl transition text-sm flex items-center justify-center gap-2" data-post-id="<?php echo htmlspecialchars($pid); ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                                </svg>
                                <span class="save-btn-text"><?php echo in_array($pid, $_SESSION['saved_jobs'] ?? []) ? 'Saved' : 'Save'; ?></span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($posts)): ?>
                <div class="text-center p-8 bg-white rounded-xl shadow-sm text-gray-500">
                    No posts matched your search. Try different keywords!
                </div>
            <?php endif; ?>
        </div>

        <div class="lg:col-span-3 space-y-6 hidden lg:block">
            <div class="bg-white rounded-xl shadow-md p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-lg text-gray-800">Jobs for You</h3>
                    <button class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </button>
                </div>
                <div class="space-y-4">
                    <?php
                    // Get dynamic jobs from user posts and static jobs
                    $recommendedJobs = [];
                    foreach ($_SESSION['posts'] as $post) {
                        if (($post['type'] ?? '') === 'user' && !empty($post['hiring'])) {
                            $recommendedJobs[] = $post;
                        }
                    }
                    // Add some static fallback jobs
                    if (empty($recommendedJobs)) {
                        $recommendedJobs = [
                            ['company' => 'NetSentinel Solutions', 'logo' => '🛡️', 'content' => 'Penetration Tester', 'time' => '2 days ago'],
                            ['company' => 'SecureBank', 'logo' => '🏦', 'content' => 'GRC Specialist', 'time' => '4h ago'],
                        ];
                    }
                    foreach (array_slice($recommendedJobs, 0, 3) as $job):
                        $jobTitle = htmlspecialchars(substr($job['content'] ?? 'Job Opening', 0, 30));
                        $jobCompany = htmlspecialchars($job['company'] ?? $job['username'] ?? 'Company');
                        $jobTime = htmlspecialchars($job['time'] ?? 'Active');
                        $jobLogo = htmlspecialchars($job['logo'] ?? '💼');
                    ?>
                        <div class="flex gap-3 cursor-pointer hover:bg-gray-50 p-2 rounded-lg transition">
                            <div class="w-8 h-8 bg-blue-100 rounded flex items-center justify-center flex-shrink-0"><?php echo $jobLogo; ?></div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-sm"><?php echo $jobTitle; ?></h4>
                                <p class="text-gray-500 text-xs"><?php echo $jobCompany; ?></p>
                                <p class="text-gray-400 text-xs mt-1"><?php echo $jobTime; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="index.php?filter=jobs" class="block w-full mt-4 text-center text-blue-800 font-semibold text-sm hover:underline">
                    See all jobs
                </a>
            </div>

            <div class="bg-white rounded-xl shadow-md p-4">
                <h3 class="font-bold text-lg text-gray-800 mb-4">Trending Skills</h3>
                <div class="space-y-3">
                    <?php
                    // Dynamic trending skills based on job posts
                    $trendingSkills = [
                        ['name' => 'Penetration Testing', 'count' => rand(5000, 20000)],
                        ['name' => 'Zero Trust Architecture', 'count' => rand(5000, 20000)],
                        ['name' => 'AI Threat Detection', 'count' => rand(5000, 20000)],
                    ];
                    foreach (array_slice($trendingSkills, 0, 3) as $skill):
                        $countFormatted = number_format($skill['count'] / 1000, 1) . 'k';
                    ?>
                        <div class="cursor-pointer hover:bg-gray-50 p-2 rounded-lg transition">
                            <p class="text-gray-800 font-medium text-sm"><?php echo htmlspecialchars($skill['name']); ?></p>
                            <p class="text-gray-400 text-xs"><?php echo $countFormatted; ?> professionals</p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="assessment.php" class="block w-full mt-4 text-center text-blue-800 font-semibold text-sm hover:underline">
                    Explore skills
                </a>
            </div>
        </div>
    </div>
</div>

<div id="applyModalBackdrop" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Apply for this position</h3>
            <button id="applyModalClose" type="button" class="text-gray-500 hover:text-gray-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="applyForm" class="p-5 space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="action" value="apply_job">
            <input type="hidden" id="applyPostId" name="post_id" value="">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="app_name" required class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Your full name">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                <input type="email" name="app_email" required class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="you@example.com">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Phone Number</label>
                <input type="tel" name="app_phone" class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="+1 555 000 0000">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Resume / CV</label>
                <input type="file" name="app_resume" accept=".pdf,.doc,.docx" class="w-full text-sm">
                <p class="text-xs text-gray-400 mt-1">PDF, DOC or DOCX (max 25MB)</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Cover Message</label>
                <textarea name="app_message" rows="3" class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Tell us why you're a great fit…"></textarea>
            </div>
            <div id="applyFormMsg" class="hidden text-sm font-semibold rounded-lg px-4 py-2"></div>
            <button type="submit" class="w-full bg-pink-700 hover:bg-pink-800 text-white font-bold py-3 rounded-xl transition">
                Submit Application
            </button>
        </form>
    </div>
</div>

<!-- Share Modal (Facebook-style) -->
<div id="shareModalBackdrop" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Share post</h3>
            <button id="shareModalClose" type="button" class="text-gray-500 hover:text-gray-800" aria-label="Close">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                    <?php if (!empty($_SESSION['user']['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['user']['profile_pic']); ?>" alt="" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="text-blue-900 font-bold"><?php echo strtoupper(substr($_SESSION['user']['username'] ?? 'U', 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['user']['username'] ?? ''); ?></p>
                    <p class="text-xs text-gray-500">Sharing to your feed</p>
                </div>
            </div>

            <textarea id="shareCaption" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Say something about this..."></textarea>

            <div class="border border-gray-200 rounded-xl overflow-hidden bg-gray-50">
                <div id="sharePreview" class="p-3 text-sm text-gray-600">
                    <!-- Filled by JS -->
                </div>
            </div>

            <div id="shareModalMsg" class="hidden text-sm font-semibold rounded-lg px-4 py-2"></div>

            <button id="shareSubmitBtn" type="button" class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 rounded-xl transition">
                Share now
            </button>
        </div>
    </div>
</div>

<script>
    (function() {
        // ── Composer ──
        const backdrop = document.getElementById('composerBackdrop');
        const closeBtn = document.getElementById('composerClose');
        const startBtn = document.getElementById('startPostBtn');
        const composeBtns = document.querySelectorAll('.composeBtn');
        const title = document.getElementById('composerTitle');
        const attachmentWrap = document.getElementById('attachmentWrap');
        const attachmentInput = document.getElementById('attachmentInput');
        const attachmentHelp = document.getElementById('attachmentHelp');
        const composerAction = document.getElementById('composerAction');
        const composerPostId = document.getElementById('composerPostId');
        const composerContent = document.getElementById('composerContent');
        const composerSubmit = document.getElementById('composerSubmit');
        const hiringToggleWrap = document.getElementById('hiringApplyToggleWrap');
        const tagCheckboxes = document.querySelectorAll('.tagCheckbox');

        // Tag pill toggle styling
        tagCheckboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const pill = cb.nextElementSibling;
                if (cb.checked) {
                    pill.classList.add('bg-blue-100', 'border-blue-500', 'text-blue-700');
                    pill.classList.remove('border-gray-300', 'text-gray-600');
                } else {
                    pill.classList.remove('bg-blue-100', 'border-blue-500', 'text-blue-700');
                    pill.classList.add('border-gray-300', 'text-gray-600');
                }
                // show hiring toggle if #Hiring is checked
                const hiringCb = document.querySelector('.tagCheckbox[data-tag="#Hiring"]');
                if (hiringToggleWrap) hiringToggleWrap.classList.toggle('hidden', !(hiringCb && hiringCb.checked));
            });
        });

        function resetComposer() {
            if (attachmentInput) attachmentInput.value = '';
            if (composerAction) composerAction.value = 'create_post';
            if (composerPostId) composerPostId.value = '';
            if (composerContent) composerContent.value = '';
            if (composerSubmit) composerSubmit.textContent = 'Post';
            if (attachmentWrap) attachmentWrap.classList.add('hidden');
            if (attachmentHelp) attachmentHelp.textContent = '';
            if (attachmentInput) attachmentInput.removeAttribute('accept');
            // reset tags
            tagCheckboxes.forEach(cb => {
                cb.checked = false;
                const pill = cb.nextElementSibling;
                pill.classList.remove('bg-blue-100', 'border-blue-500', 'text-blue-700');
                pill.classList.add('border-gray-300', 'text-gray-600');
            });
            if (hiringToggleWrap) hiringToggleWrap.classList.add('hidden');
        }

        function openComposer(mode, options) {
            if (!backdrop) return;
            backdrop.classList.remove('hidden');
            backdrop.classList.add('flex');
            resetComposer();

            if (mode === 'edit') {
                if (title) title.textContent = 'Edit post';
                if (composerAction) composerAction.value = 'update_post';
                if (composerPostId && options && options.postId) composerPostId.value = options.postId;
                if (composerContent && options && typeof options.content === 'string') composerContent.value = options.content;
                if (composerSubmit) composerSubmit.textContent = 'Save';
                if (attachmentWrap) attachmentWrap.classList.remove('hidden');
                if (attachmentHelp) attachmentHelp.textContent = 'Optional: upload a new file to replace the current one.';
                return;
            }

            if (!mode || mode === 'post') {
                if (title) title.textContent = 'Create post';
                return;
            }

            if (attachmentWrap) attachmentWrap.classList.remove('hidden');

            if (mode === 'photo') {
                if (title) title.textContent = 'Create photo post';
                if (attachmentHelp) attachmentHelp.textContent = 'Supported: JPG, PNG, GIF, WEBP (max 25MB)';
                if (attachmentInput) attachmentInput.setAttribute('accept', 'image/*');
            } else if (mode === 'video') {
                if (title) title.textContent = 'Create video post';
                if (attachmentHelp) attachmentHelp.textContent = 'Supported: MP4, WEBM, MOV (max 25MB)';
                if (attachmentInput) attachmentInput.setAttribute('accept', 'video/*');
            } else if (mode === 'document') {
                if (title) title.textContent = 'Create document post';
                if (attachmentHelp) attachmentHelp.textContent = 'Supported: PDF, DOC/DOCX, PPT/PPTX, XLS/XLSX, TXT (max 25MB)';
                if (attachmentInput) attachmentInput.setAttribute('accept', '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt');
            }

            if (attachmentInput) setTimeout(() => attachmentInput.click(), 50);
        }

        function closeComposer() {
            if (!backdrop) return;
            backdrop.classList.add('hidden');
            backdrop.classList.remove('flex');
        }

        if (startBtn) startBtn.addEventListener('click', () => openComposer('post'));
        composeBtns.forEach(btn => btn.addEventListener('click', () => openComposer(btn.getAttribute('data-compose'))));
        if (closeBtn) closeBtn.addEventListener('click', closeComposer);
        if (backdrop) backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeComposer();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeComposer();
                closeApplyModal();
                closeShareModal();
            }
        });

        // Post options dropdown
        function closeAllMenus() {
            document.querySelectorAll('.postOptionsMenu').forEach(m => m.classList.add('hidden'));
        }

        document.addEventListener('click', (e) => {
            const optionsBtn = e.target.closest('.postOptionsBtn');
            if (optionsBtn) {
                const wrapper = optionsBtn.parentElement;
                const menu = wrapper ? wrapper.querySelector('.postOptionsMenu') : null;
                if (!menu) return;
                const isHidden = menu.classList.contains('hidden');
                closeAllMenus();
                if (isHidden) menu.classList.remove('hidden');
                e.preventDefault();
                return;
            }

            const editBtn = e.target.closest('.postEditBtn');
            if (editBtn) {
                const postId = editBtn.getAttribute('data-post-id');
                const content = editBtn.getAttribute('data-post-content') || '';
                closeAllMenus();
                openComposer('edit', {
                    postId,
                    content
                });
                e.preventDefault();
                return;
            }

            if (!e.target.closest('.postOptionsMenu')) {
                closeAllMenus();
            }
        });

        // ── React ──
        document.addEventListener('mouseenter', (e) => {
            const btn = e.target.closest('.react-btn');
            if (btn) {
                const pid = btn.dataset.postId;
                const picker = document.querySelector(`.emoji-picker[data-post-id="${pid}"]`);
                if (picker) picker.classList.remove('hidden');
            }
        }, true);
        document.addEventListener('mouseleave', (e) => {
            const btn = e.target.closest('.react-btn');
            if (btn) {
                const pid = btn.dataset.postId;
                const picker = document.querySelector(`.emoji-picker[data-post-id="${pid}"]`);
                if (picker) setTimeout(() => {
                    if (!picker.matches(':hover')) picker.classList.add('hidden');
                }, 200);
            }
            const picker = e.target.closest('.emoji-picker');
            if (picker && !picker.matches(':hover')) {
                picker.classList.add('hidden');
            }
        }, true);

        document.addEventListener('click', (e) => {
            const opt = e.target.closest('.emoji-opt');
            if (opt) {
                const pid = opt.dataset.postId;
                const emoji = opt.dataset.emoji;
                const fd = new FormData();
                fd.append('action', 'react_post');
                fd.append('post_id', pid);
                fd.append('emoji', emoji);
                fetch('', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.ok) {
                            alert(data.msg || 'Please sign in to react.');
                            return;
                        }
                        const btn = document.querySelector(`.react-btn[data-post-id="${pid}"]`);
                        const label = btn ? btn.querySelector('.react-label') : null;
                        if (label) {
                            const total = Object.values(data.reactions || {}).reduce((a, b) => a + b, 0);
                            label.textContent = data.mine ? data.mine + ' ' + total : 'React' + (total > 0 ? ' ' + total : '');
                            btn.classList.toggle('text-blue-700', !!data.mine);
                            btn.classList.toggle('text-gray-700', !data.mine);
                        }
                        const picker = document.querySelector(`.emoji-picker[data-post-id="${pid}"]`);
                        if (picker) picker.classList.add('hidden');
                        // add notification badge
                        const badge = document.getElementById('notifBadge');
                        if (badge && window.updateBadge && window.prependNotif) {
                            const cur = parseInt(badge.textContent) || 0;
                            window.updateBadge(cur + 1);
                            // prepend to panel
                            window.prependNotif('Someone reacted to a post.', 'index.php?post=' + encodeURIComponent(pid));
                        }
                    });
                e.stopPropagation();
            }
        });

        // ── Comment & Apps toggles ──
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.comment-toggle-btn');
            if (btn) {
                const pid = btn.dataset.postId;
                const sec = document.querySelector(`.comment-section[data-post-id="${pid}"]`);
                if (sec) sec.classList.toggle('hidden');
            }

            const appBtn = e.target.closest('.view-apps-toggle');
            if (appBtn) {
                const pid = appBtn.dataset.postId;
                const sec = document.querySelector(`.apps-section[data-post-id="${pid}"]`);
                if (sec) sec.classList.toggle('hidden');
            }

            const acceptBtn = e.target.closest('.accept-app-btn');
            if (acceptBtn) {
                const pid = acceptBtn.dataset.postId;
                const appId = acceptBtn.dataset.appId;
                updateAppStatus(pid, appId, 'accepted');
            }

            const rejectBtn = e.target.closest('.reject-app-btn');
            if (rejectBtn) {
                const pid = rejectBtn.dataset.postId;
                const appId = rejectBtn.dataset.appId;
                updateAppStatus(pid, appId, 'rejected');
            }
        });

        function updateAppStatus(pid, appId, status) {
            const fd = new FormData();
            fd.append('action', 'update_app_status');
            fd.append('post_id', pid);
            fd.append('app_id', appId);
            fd.append('status', status);
            fetch('', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) location.reload();
                });
        }

        // Comment submit on Enter
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.matches('.comment-input')) {
                const input = e.target;
                const pid = input.dataset.postId;
                const text = input.value.trim();
                if (!text) return;
                const fd = new FormData();
                fd.append('action', 'add_comment');
                fd.append('post_id', pid);
                fd.append('text', text);
                fetch('', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.ok) {
                            alert(data.msg || 'Please sign in to comment.');
                            return;
                        }
                        input.value = '';
                        const sec = document.querySelector(`.comment-section[data-post-id="${pid}"]`);
                        const list = sec ? sec.querySelector('.comments-list') : null;
                        if (list) {
                            const c = data.comment;
                            const initials = c.user ? c.user[0].toUpperCase() : '?';
                            const el = document.createElement('div');
                            el.className = 'flex gap-2 items-start';
                            if (c.id) el.id = 'comment_' + c.id;
                            el.innerHTML = `<div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-900 font-bold text-sm flex-shrink-0">${initials}</div>
                            <div class="bg-white rounded-xl px-3 py-2 text-sm shadow-sm flex-1">
                                <span class="font-semibold text-gray-800">${c.user}</span>
                                <span class="text-gray-400 text-xs ml-2">${c.time}</span>
                                <p class="text-gray-700 mt-0.5">${c.text.replace(/</g,'&lt;')}</p>
                            </div>`;
                            list.appendChild(el);
                        }
                        // update button label
                        const btn2 = document.querySelector(`.comment-toggle-btn[data-post-id="${pid}"]`);
                        if (btn2) btn2.childNodes[btn2.childNodes.length - 1].textContent = ' Comment ' + data.total;
                        if (window.prependNotif && window.updateBadge) {
                            const link = 'index.php?post=' + encodeURIComponent(pid) + (data.comment && data.comment.id ? '&comment=' + encodeURIComponent(data.comment.id) : '');
                            window.prependNotif('You commented on a post.', link);
                            const badge = document.getElementById('notifBadge');
                            if (badge) window.updateBadge((parseInt(badge.textContent) || 0) + 1);
                        }
                    });
            }
        });

        // ── Share (Facebook-style modal with editable caption) ──
        const shareModalBackdrop = document.getElementById('shareModalBackdrop');
        const shareModalClose = document.getElementById('shareModalClose');
        const shareCaption = document.getElementById('shareCaption');
        const sharePreview = document.getElementById('sharePreview');
        const shareSubmitBtn = document.getElementById('shareSubmitBtn');
        const shareModalMsg = document.getElementById('shareModalMsg');

        let shareTargetId = null;
        let sharePostJson = null;
        let shareBtnRef = null;

        function escapeHtml(str) {
            return (str || '').replace(/[&<>"']/g, (c) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [c]));
        }

        function closeShareModal() {
            if (shareModalBackdrop) {
                shareModalBackdrop.classList.add('hidden');
                shareModalBackdrop.classList.remove('flex');
            }
            shareTargetId = null;
            sharePostJson = null;
            shareBtnRef = null;
            if (shareCaption) shareCaption.value = '';
            if (sharePreview) sharePreview.innerHTML = '';
            if (shareModalMsg) {
                shareModalMsg.classList.add('hidden');
                shareModalMsg.textContent = '';
            }
        }
        // Make accessible for the earlier Escape handler
        window.closeShareModal = closeShareModal;

        function openShareModal(pid, postJson, btnEl) {
            shareTargetId = pid;
            sharePostJson = postJson || '';
            shareBtnRef = btnEl || null;
            if (shareCaption) shareCaption.value = '';
            if (shareModalMsg) {
                shareModalMsg.classList.add('hidden');
                shareModalMsg.textContent = '';
            }

            let data = null;
            try {
                data = postJson ? JSON.parse(postJson) : null;
            } catch (e) {
                data = null;
            }
            if (sharePreview) {
                const name = escapeHtml((data && (data.username || data.company || data.name)) || 'Post');
                const meta = escapeHtml((data && data.time) || '');
                const content = escapeHtml((data && (data.content || '')).toString()).slice(0, 240);
                let mediaHtml = '';
                if (data) {
                    const att = data.attachment;
                    if (att && typeof att === 'object') {
                        const kind = att.kind || '';
                        const path = att.path || '';
                        const mime = att.mime || '';
                        const fname = att.name || 'Attachment';
                        if (kind === 'image' && path) {
                            mediaHtml = `<div class="mt-3 rounded-lg overflow-hidden border border-gray-200 bg-white"><img src="${escapeHtml(path)}" alt="" class="w-full"></div>`;
                        } else if (kind === 'video' && path) {
                            mediaHtml = `<div class="mt-3 rounded-lg border border-gray-200 bg-white p-3 text-gray-700 text-xs">🎬 Video attached (${escapeHtml(mime || 'video')})</div>`;
                        } else if (path) {
                            mediaHtml = `<div class="mt-3 rounded-lg border border-gray-200 bg-white p-3 text-gray-700 text-xs">📎 ${escapeHtml(fname)}</div>`;
                        }
                    } else if (data.image) {
                        mediaHtml = `<div class="mt-3 rounded-lg overflow-hidden border border-gray-200 bg-white"><img src="${escapeHtml(data.image)}" alt="" class="w-full"></div>`;
                    }
                }
                sharePreview.innerHTML = `
                    <div class="font-semibold text-gray-800">${name}</div>
                    ${meta ? `<div class="text-xs text-gray-500">${meta}</div>` : ``}
                    ${content ? `<div class="mt-2 text-gray-700">${content}${(data && data.content && data.content.length > 240) ? '…' : ''}</div>` : `<div class="mt-2 text-gray-400 italic">No text</div>`}
                    ${mediaHtml}
                `;
            }

            if (shareModalBackdrop) {
                shareModalBackdrop.classList.remove('hidden');
                shareModalBackdrop.classList.add('flex');
            }
            setTimeout(() => shareCaption && shareCaption.focus(), 50);
        }

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.share-btn');
            if (btn) {
                const pid = btn.dataset.postId;
                const postJson = btn.dataset.postJson || '';
                openShareModal(pid, postJson, btn);
                e.preventDefault();
            }
        });

        if (shareModalClose) shareModalClose.addEventListener('click', closeShareModal);
        if (shareModalBackdrop) shareModalBackdrop.addEventListener('click', (e) => {
            if (e.target === shareModalBackdrop) closeShareModal();
        });

        if (shareSubmitBtn) {
            shareSubmitBtn.addEventListener('click', () => {
                if (!shareTargetId) return;
                const fd = new FormData();
                fd.append('action', 'share_post');
                fd.append('post_id', shareTargetId);
                fd.append('share_text', (shareCaption ? shareCaption.value.trim() : ''));
                if (sharePostJson) fd.append('post_json', sharePostJson);

                fetch('', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.ok) {
                            if (shareModalMsg) {
                                shareModalMsg.classList.remove('hidden', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700');
                                shareModalMsg.classList.add('bg-red-100', 'text-red-700');
                                shareModalMsg.textContent = data.msg || 'Please sign in to share.';
                            } else {
                                alert(data.msg || 'Please sign in to share.');
                            }
                            return;
                        }

                        // Update share count on the clicked button immediately
                        if (shareBtnRef) {
                            shareBtnRef.childNodes[shareBtnRef.childNodes.length - 1].textContent = ' Share ' + data.shares;
                            shareBtnRef.classList.add('text-blue-700');
                        }

                        closeShareModal();
                        // Show the newly created shared post (simple + reliable)
                        location.reload();
                    });
            });
        }

        // ── Apply Modal ──
        const applyModalBackdrop = document.getElementById('applyModalBackdrop');
        const applyModalClose = document.getElementById('applyModalClose');
        const applyForm = document.getElementById('applyForm');
        const applyPostIdInput = document.getElementById('applyPostId');
        const applyFormMsg = document.getElementById('applyFormMsg');

        function closeApplyModal() {
            if (applyModalBackdrop) {
                applyModalBackdrop.classList.add('hidden');
                applyModalBackdrop.classList.remove('flex');
            }
        }

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.apply-modal-btn');
            if (btn) {
                const pid = btn.dataset.postId;
                if (applyPostIdInput) applyPostIdInput.value = pid;
                if (applyFormMsg) {
                    applyFormMsg.classList.add('hidden');
                    applyFormMsg.textContent = '';
                }
                if (applyForm) applyForm.reset();
                if (applyPostIdInput) applyPostIdInput.value = pid;
                if (applyModalBackdrop) {
                    applyModalBackdrop.classList.remove('hidden');
                    applyModalBackdrop.classList.add('flex');
                }
            }
        });
        if (applyModalClose) applyModalClose.addEventListener('click', closeApplyModal);
        if (applyModalBackdrop) applyModalBackdrop.addEventListener('click', (e) => {
            if (e.target === applyModalBackdrop) closeApplyModal();
        });

        if (applyForm) {
            applyForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const fd = new FormData(applyForm);
                fetch('', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (applyFormMsg) {
                            applyFormMsg.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700');
                            applyFormMsg.classList.add(data.ok ? 'bg-green-100' : 'bg-red-100', data.ok ? 'text-green-700' : 'text-red-700');
                            applyFormMsg.textContent = data.msg || (data.ok ? 'Applied!' : 'Error.');
                        }
                        if (data.ok) {
                            applyForm.reset();
                            applyPostIdInput.value = fd.get('post_id');
                            setTimeout(closeApplyModal, 2000);
                            if (window.prependNotif && window.updateBadge) {
                                const link = 'index.php?post=' + encodeURIComponent(fd.get('post_id') || '');
                                window.prependNotif('You submitted a job application.', link);
                                const badge = document.getElementById('notifBadge');
                                if (badge) window.updateBadge((parseInt(badge.textContent) || 0) + 1);
                            }
                        }
                    });
            });
        }

        // ── AJAX: Save Job Toggle ──
        document.addEventListener('click', (e) => {
            const saveBtn = e.target.closest('.save-job-btn');
            if (saveBtn) {
                const pid = saveBtn.dataset.postId;
                const textSpan = saveBtn.querySelector('.save-btn-text');

                const fd = new FormData();
                fd.append('action', 'toggle_save_job');
                fd.append('post_id', pid);

                fetch('', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.ok) {
                            alert(data.msg || 'Please sign in to save jobs.');
                            return;
                        }
                        // Update button text visually
                        if (textSpan) {
                            textSpan.textContent = data.saved ? 'Saved' : 'Save';
                        }
                    });
                e.preventDefault();
            }
        });

        // Auto-dismiss success alert
        document.querySelectorAll('[data-autodismiss]').forEach(el => {
            const ms = parseInt(el.getAttribute('data-autodismiss')) || 4000;
            setTimeout(() => el.remove(), ms);
        });

        // ── Notification deep links: ?post=ID[&comment=ID] ──
        (function() {
            const params = new URLSearchParams(window.location.search);
            const pid = params.get('post');
            if (!pid) return;

            // Find post card reliably (avoid CSS.escape dependency)
            const card = Array.from(document.querySelectorAll('[data-post-card][data-post-id]'))
                .find(el => (el.getAttribute('data-post-id') || '') === pid);
            if (!card) return;

            // Scroll to post and highlight
            setTimeout(() => {
                card.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                card.classList.add('ring-2', 'ring-yellow-400', 'ring-offset-2');
                setTimeout(() => card.classList.remove('ring-2', 'ring-yellow-400', 'ring-offset-2'), 1600);
            }, 150);

            const cid = params.get('comment');
            if (!cid) return;

            // Ensure comment section is visible, then scroll to specific comment
            const sec = Array.from(document.querySelectorAll('.comment-section[data-post-id]'))
                .find(el => (el.getAttribute('data-post-id') || '') === pid);
            if (sec) sec.classList.remove('hidden');

            setTimeout(() => {
                const commentEl = document.getElementById(`comment_${cid}`);
                if (commentEl) {
                    commentEl.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    commentEl.classList.add('ring-2', 'ring-blue-400', 'ring-offset-2');
                    setTimeout(() => commentEl.classList.remove('ring-2', 'ring-blue-400', 'ring-offset-2'), 1600);
                }
            }, 250);
        })();

    })();
</script>

<style>
    .tag-pill {
        transition: all 0.15s;
    }

    .emoji-picker {
        transition: opacity 0.1s;
    }

    .comment-section {
        transition: all 0.2s;
    }
</style>

<?php include 'includes/footer.php'; ?>