<?php
require_once 'includes/bootstrap.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Communities - CyberSphere';
$currentPage = 'communities';

$meEmail = $_SESSION['user']['email'] ?? '';
$meName  = $_SESSION['user']['username'] ?? 'User';
$meId    = $_SESSION['user']['user_id'];

function cs_now_label(): string {
    return date('M j, Y g:i A');
}

function cs_make_id(string $prefix): string {
    return $prefix . '_' . uniqid('', true);
}

// Load communities from database into session (for backwards compatibility)
try {
    $pdo = get_db_connection();
    
    // Get all communities with member counts
    $stmt = $pdo->query("
        SELECT 
            c.community_id, 
            c.name, 
            c.description, 
            c.type, 
            c.created_by, 
            c.created_at, 
            COUNT(cm.user_id) AS members 
        FROM communities c
        LEFT JOIN community_members cm ON c.community_id = cm.community_id
        GROUP BY c.community_id
        ORDER BY members DESC
    ");
    $dbCommunities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $_SESSION['communities'] = [];
    foreach ($dbCommunities as $c) {
        $_SESSION['communities'][] = [
            'id' => (string)$c['community_id'],
            'name' => $c['name'],
            'type' => $c['type'],
            'description' => $c['description'],
            'created_by' => $c['created_by'],
            'created_at' => date('M j, Y g:i A', strtotime($c['created_at'])),
            'members' => (int)$c['members']
        ];
    }
    
    // Get user's joined communities
    $stmtJoined = $pdo->prepare("SELECT community_id FROM community_members WHERE user_id = ?");
    $stmtJoined->execute([$meId]);
    $joinedIds = $stmtJoined->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION['joined_communities'][$meEmail] = array_map('strval', $joinedIds);
    
    // If no communities exist in DB, seed some basic ones
    if (empty($_SESSION['communities'])) {
        $seedCommunities = [
            [
                'name' => 'Cyber News & Updates',
                'type' => 'group',
                'description' => 'Daily cybersecurity news, CVEs, and threat intel discussions.',
            ],
            [
                'name' => 'DFIR Philippines',
                'type' => 'organization',
                'description' => 'Incident response and forensics community for PH practitioners.',
            ],
            [
                'name' => 'PenTest Lab',
                'type' => 'group',
                'description' => 'Share writeups, tools, and lab tips. Beginner-friendly.',
            ],
        ];
        
        foreach ($seedCommunities as $seed) {
            $stmtInsert = $pdo->prepare("INSERT INTO communities (created_by, name, type, description) VALUES (?, ?, ?, ?)");
            $stmtInsert->execute([1, $seed['name'], $seed['type'], $seed['description']]);
        }
        
        // Refresh the communities list
        $stmt = $pdo->query("
            SELECT 
                c.community_id, 
                c.name, 
                c.description, 
                c.type, 
                c.created_by, 
                c.created_at, 
                COUNT(cm.user_id) AS members 
            FROM communities c
            LEFT JOIN community_members cm ON c.community_id = cm.community_id
            GROUP BY c.community_id
            ORDER BY members DESC
        ");
        $dbCommunities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $_SESSION['communities'] = [];
        foreach ($dbCommunities as $c) {
            $_SESSION['communities'][] = [
                'id' => (string)$c['community_id'],
                'name' => $c['name'],
                'type' => $c['type'],
                'description' => $c['description'],
                'created_by' => $c['created_by'],
                'created_at' => date('M j, Y g:i A', strtotime($c['created_at'])),
                'members' => (int)$c['members']
            ];
        }
    }
} catch (Exception $e) {
    // Fallback to session-only if DB fails
    if (!isset($_SESSION['communities']) || !is_array($_SESSION['communities'])) {
        $_SESSION['communities'] = [
            [
                'id' => 'c_cybernews',
                'name' => 'Cyber News & Updates',
                'type' => 'group',
                'description' => 'Daily cybersecurity news, CVEs, and threat intel discussions.',
                'created_by' => 'system',
                'created_at' => 'May 1, 2026',
                'members' => 1240,
            ],
            [
                'id' => 'c_dfir',
                'name' => 'DFIR Philippines',
                'type' => 'organization',
                'description' => 'Incident response and forensics community for PH practitioners.',
                'created_by' => 'system',
                'created_at' => 'Apr 19, 2026',
                'members' => 620,
            ],
            [
                'id' => 'c_pentest',
                'name' => 'PenTest Lab',
                'type' => 'group',
                'description' => 'Share writeups, tools, and lab tips. Beginner-friendly.',
                'created_by' => 'system',
                'created_at' => 'Mar 12, 2026',
                'members' => 980,
            ],
        ];
    }
}

if (!isset($_SESSION['joined_communities']) || !is_array($_SESSION['joined_communities'])) {
    $_SESSION['joined_communities'] = [];
}
if (!isset($_SESSION['community_posts']) || !is_array($_SESSION['community_posts'])) {
    $_SESSION['community_posts'] = [];
}

// Ensure current user has a joined list
if (!isset($_SESSION['joined_communities'][$meEmail]) || !is_array($_SESSION['joined_communities'][$meEmail])) {
    $_SESSION['joined_communities'][$meEmail] = [];
}

function cs_find_community_index(string $cid): ?int {
    foreach (($_SESSION['communities'] ?? []) as $i => $c) {
        if (($c['id'] ?? '') === $cid) return $i;
    }
    return null;
}

function cs_is_joined(string $email, string $cid): bool {
    return in_array($cid, $_SESSION['joined_communities'][$email] ?? [], true);
}

function savePostAttachment(?array $file, string $uploadDirAbs, array &$postErrors): ?array {
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

    // Allow common types
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

    $extFromName = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    $mimeByExt = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
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

    if ($ext === null) {
        if (!isset($mimeByExt[$extFromName])) {
            $postErrors[] = 'Unsupported file type.';
            return null;
        }
        $mime = $mimeByExt[$extFromName];
        $ext = $extFromName === 'jpeg' ? 'jpg' : $extFromName;
    }

    if (!is_dir($uploadDirAbs)) {
        mkdir($uploadDirAbs, 0777, true);
    }

    $filename = uniqid('', true) . '.' . $ext;
    $destination = $uploadDirAbs . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $destination)) {
        $postErrors[] = 'Upload failed. Please try again.';
        return null;
    }

    $kind = 'document';
    if (str_starts_with($mime, 'image/')) $kind = 'image';
    if (str_starts_with($mime, 'video/')) $kind = 'video';

    return [
        'path' => 'uploads/' . $filename,
        'mime' => $mime,
        'name' => $file['name'] ?? 'attachment',
        'kind' => $kind
    ];
}

function safeUnlinkUpload(?string $path): void {
    if (!$path) return;
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    if (file_exists($fullPath) && is_file($fullPath)) {
        unlink($fullPath);
    }
}

$errors = [];

// ── Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_community') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'group';
        $desc = trim($_POST['description'] ?? '');

        if ($name === '') $errors[] = 'Community name is required.';
        if (!in_array($type, ['group','organization'], true)) $errors[] = 'Invalid community type.';
        if (cs_strlen($name) > 100) $errors[] = 'Community name is too long (max 100 characters).';
        if (cs_strlen($desc) > 240) $errors[] = 'Description is too long (max 240 characters).';

        // Ensure unique name (check database)
        try {
            $pdo = get_db_connection();
            $checkStmt = $pdo->prepare("SELECT community_id FROM communities WHERE name = ?");
            $checkStmt->execute([$name]);
            if ($checkStmt->fetch()) {
                $errors[] = 'A community with that name already exists.';
            }
        } catch (Exception $e) {
            // Fallback to session check if DB fails
            foreach ($_SESSION['communities'] as $c) {
                if (strcasecmp($c['name'] ?? '', $name) === 0) {
                    $errors[] = 'A community with that name already exists.';
                    break;
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo = get_db_connection();
                $stmtInsert = $pdo->prepare("INSERT INTO communities (created_by, name, type, description) VALUES (?, ?, ?, ?)");
                $stmtInsert->execute([$meId, $name, $type, $desc]);
                $newCommunityId = $pdo->lastInsertId();
                
                // Add creator as member
                $stmtMember = $pdo->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)");
                $stmtMember->execute([$newCommunityId, $meId]);
                
                // Refresh communities in session
                $stmt = $pdo->query("
                    SELECT 
                        c.community_id, 
                        c.name, 
                        c.description, 
                        c.type, 
                        c.created_by, 
                        c.created_at, 
                        COUNT(cm.user_id) AS members 
                    FROM communities c
                    LEFT JOIN community_members cm ON c.community_id = cm.community_id
                    GROUP BY c.community_id
                    ORDER BY members DESC
                ");
                $dbCommunities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $_SESSION['communities'] = [];
                foreach ($dbCommunities as $c) {
                    $_SESSION['communities'][] = [
                        'id' => (string)$c['community_id'],
                        'name' => $c['name'],
                        'type' => $c['type'],
                        'description' => $c['description'],
                        'created_by' => $c['created_by'],
                        'created_at' => date('M j, Y g:i A', strtotime($c['created_at'])),
                        'members' => (int)$c['members']
                    ];
                }
                
                // Refresh joined communities
                $stmtJoined = $pdo->prepare("SELECT community_id FROM community_members WHERE user_id = ?");
                $stmtJoined->execute([$meId]);
                $joinedIds = $stmtJoined->fetchAll(PDO::FETCH_COLUMN);
                $_SESSION['joined_communities'][$meEmail] = array_map('strval', $joinedIds);
                
                $cid = (string)$newCommunityId;
                
                // Notification
                cs_save_notification($meId, 'You created a new community: ' . htmlspecialchars($name), 'communities.php?c=' . urlencode($cid));
                
            } catch (Exception $e) {
                // Fallback to session-only
                $cid = cs_make_id('c');
                array_unshift($_SESSION['communities'], [
                    'id' => $cid,
                    'name' => $name,
                    'type' => $type,
                    'description' => $desc,
                    'created_by' => $meEmail,
                    'created_at' => cs_now_label(),
                    'members' => 1,
                ]);
                $_SESSION['joined_communities'][$meEmail][] = $cid;
                cs_save_notification($meId, 'You created a new community: ' . htmlspecialchars($name), 'communities.php?c=' . urlencode($cid));
            }
            
            header('Location: communities.php?c=' . urlencode($cid) . '&created=1');
            exit;
        }
    }

    if ($action === 'join_community') {
        $cid = $_POST['community_id'] ?? '';
        $idx = $cid ? cs_find_community_index($cid) : null;
        if ($idx === null) $errors[] = 'Community not found.';

        if (empty($errors)) {
            try {
                $pdo = get_db_connection();
                $cidInt = (int)$cid;
                
                // Check if already joined
                $checkStmt = $pdo->prepare("SELECT member_id FROM community_members WHERE community_id = ? AND user_id = ?");
                $checkStmt->execute([$cidInt, $meId]);
                if (!$checkStmt->fetch()) {
                    $stmtInsert = $pdo->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)");
                    $stmtInsert->execute([$cidInt, $meId]);
                    
                    // Refresh communities in session
                    $stmt = $pdo->query("
                        SELECT 
                            c.community_id, 
                            c.name, 
                            c.description, 
                            c.type, 
                            c.created_by, 
                            c.created_at, 
                            COUNT(cm.user_id) AS members 
                        FROM communities c
                        LEFT JOIN community_members cm ON c.community_id = cm.community_id
                        GROUP BY c.community_id
                        ORDER BY members DESC
                    ");
                    $dbCommunities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $_SESSION['communities'] = [];
                    foreach ($dbCommunities as $c) {
                        $_SESSION['communities'][] = [
                            'id' => (string)$c['community_id'],
                            'name' => $c['name'],
                            'type' => $c['type'],
                            'description' => $c['description'],
                            'created_by' => $c['created_by'],
                            'created_at' => date('M j, Y g:i A', strtotime($c['created_at'])),
                            'members' => (int)$c['members']
                        ];
                    }
                    
                    // Refresh joined communities
                    $stmtJoined = $pdo->prepare("SELECT community_id FROM community_members WHERE user_id = ?");
                    $stmtJoined->execute([$meId]);
                    $joinedIds = $stmtJoined->fetchAll(PDO::FETCH_COLUMN);
                    $_SESSION['joined_communities'][$meEmail] = array_map('strval', $joinedIds);
                }
            } catch (Exception $e) {
                // Fallback to session-only
                if (!cs_is_joined($meEmail, $cid)) {
                    $_SESSION['joined_communities'][$meEmail][] = $cid;
                    $_SESSION['communities'][$idx]['members'] = (int)($_SESSION['communities'][$idx]['members'] ?? 0) + 1;
                }
            }
            
            header('Location: communities.php?c=' . urlencode($cid));
            exit;
        }
    }

    if ($action === 'leave_community') {
        $cid = $_POST['community_id'] ?? '';
        $idx = $cid ? cs_find_community_index($cid) : null;
        if ($idx === null) $errors[] = 'Community not found.';

        if (empty($errors)) {
            try {
                $pdo = get_db_connection();
                $cidInt = (int)$cid;
                
                $stmtDelete = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
                $stmtDelete->execute([$cidInt, $meId]);
                
                // Refresh communities in session
                $stmt = $pdo->query("
                    SELECT 
                        c.community_id, 
                        c.name, 
                        c.description, 
                        c.type, 
                        c.created_by, 
                        c.created_at, 
                        COUNT(cm.user_id) AS members 
                    FROM communities c
                    LEFT JOIN community_members cm ON c.community_id = cm.community_id
                    GROUP BY c.community_id
                    ORDER BY members DESC
                ");
                $dbCommunities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $_SESSION['communities'] = [];
                foreach ($dbCommunities as $c) {
                    $_SESSION['communities'][] = [
                        'id' => (string)$c['community_id'],
                        'name' => $c['name'],
                        'type' => $c['type'],
                        'description' => $c['description'],
                        'created_by' => $c['created_by'],
                        'created_at' => date('M j, Y g:i A', strtotime($c['created_at'])),
                        'members' => (int)$c['members']
                    ];
                }
                
                // Refresh joined communities
                $stmtJoined = $pdo->prepare("SELECT community_id FROM community_members WHERE user_id = ?");
                $stmtJoined->execute([$meId]);
                $joinedIds = $stmtJoined->fetchAll(PDO::FETCH_COLUMN);
                $_SESSION['joined_communities'][$meEmail] = array_map('strval', $joinedIds);
                
            } catch (Exception $e) {
                // Fallback to session-only
                $list = $_SESSION['joined_communities'][$meEmail] ?? [];
                $pos = array_search($cid, $list, true);
                if ($pos !== false) {
                    array_splice($_SESSION['joined_communities'][$meEmail], $pos, 1);
                    $_SESSION['communities'][$idx]['members'] = max(0, (int)($_SESSION['communities'][$idx]['members'] ?? 0) - 1);
                }
            }
            
            header('Location: communities.php');
            exit;
        }
    }

    if ($action === 'create_community_post') {
        $cid = $_POST['community_id'] ?? '';
        $idx = $cid ? cs_find_community_index($cid) : null;
        $content = trim($_POST['content'] ?? '');
        $attachment = savePostAttachment($_FILES['attachment'] ?? null, __DIR__ . DIRECTORY_SEPARATOR . 'uploads', $errors);

        if ($idx === null) $errors[] = 'Community not found.';
        if (!cs_is_joined($meEmail, $cid)) $errors[] = 'Join the community to post.';
        if ($content === '') $errors[] = 'Post content is empty.';
        if (cs_strlen($content) > 2000) $errors[] = 'Post is too long (max 2000 characters).';

        if (empty($errors)) {
            try {
                $pdo = get_db_connection();
                $cidInt = (int)$cid;
                $stmtInsert = $pdo->prepare("INSERT INTO community_posts (community_id, post_author_id, content) VALUES (?, ?, ?)");
                $stmtInsert->execute([$cidInt, $meId, $content]);
                $postId = (string)$pdo->lastInsertId();
                
                // Save attachment if any
                if ($attachment) {
                    $stmtAttach = $pdo->prepare('INSERT INTO community_post_attachments (post_id, file_path, mime_type) VALUES (?, ?, ?)');
                    $stmtAttach->execute([$postId, $attachment['path'], $attachment['mime']]);
                }
                
                // Refresh community posts in session
                cs_load_community_posts($pdo, $cid, $meEmail);
                
                header('Location: communities.php?c=' . urlencode($cid) . '&post=' . urlencode($postId));
                exit;
                
            } catch (Exception $e) {
                // Fallback to session-only
                if (!isset($_SESSION['community_posts'][$cid]) || !is_array($_SESSION['community_posts'][$cid])) {
                    $_SESSION['community_posts'][$cid] = [];
                }
                $postId = cs_make_id('cp');
                array_unshift($_SESSION['community_posts'][$cid], [
                    'id' => $postId,
                    'community_id' => $cid,
                    'user' => $meName,
                    'email' => $meEmail,
                    'avatar' => $_SESSION['user']['profile_pic'] ?? null,
                    'time' => cs_now_label(),
                    'content' => $content,
                    'attachment' => $attachment,
                ]);
                
                header('Location: communities.php?c=' . urlencode($cid) . '&post=' . urlencode($postId));
                exit;
            }
        }
    }
}

// Helper function to load community posts into session
function cs_load_community_posts($pdo, $cid, $meEmail) {
    try {
        $cidInt = (int)$cid;
        $stmt = $pdo->prepare("
            SELECT 
                cp.post_id,
                cp.community_id,
                cp.post_author_id,
                cp.content,
                cp.created_at,
                u.first_name,
                u.last_name,
                up.profile_pic
            FROM community_posts cp
            JOIN users u ON cp.post_author_id = u.user_id
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            WHERE cp.community_id = ?
            ORDER BY cp.created_at DESC
        ");
        $stmt->execute([$cidInt]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attachments for all posts
        $postIds = array_column($posts, 'post_id');
        $attachments = [];
        if (!empty($postIds)) {
            $inClause = implode(',', array_fill(0, count($postIds), '?'));
            $stmtAttach = $pdo->prepare("
                SELECT post_id, file_path, mime_type 
                FROM community_post_attachments 
                WHERE post_id IN ($inClause)
            ");
            $stmtAttach->execute($postIds);
            while ($attach = $stmtAttach->fetch(PDO::FETCH_ASSOC)) {
                $kind = 'document';
                if (str_starts_with($attach['mime_type'], 'image/')) $kind = 'image';
                if (str_starts_with($attach['mime_type'], 'video/')) $kind = 'video';
                $attachments[$attach['post_id']] = [
                    'path' => $attach['file_path'],
                    'mime' => $attach['mime_type'],
                    'name' => basename($attach['file_path']),
                    'kind' => $kind
                ];
            }
        }
        
        $_SESSION['community_posts'][$cid] = [];
        foreach ($posts as $p) {
            $_SESSION['community_posts'][$cid][] = [
                'id' => (string)$p['post_id'],
                'community_id' => (string)$p['community_id'],
                'user' => $p['first_name'] . ' ' . $p['last_name'],
                'email' => '',
                'avatar' => $p['profile_pic'],
                'time' => date('M j, Y g:i A', strtotime($p['created_at'])),
                'content' => $p['content'],
                'attachment' => isset($attachments[$p['post_id']]) ? $attachments[$p['post_id']] : null
            ];
        }
    } catch (Exception $e) {
        // Do nothing if fails
    }
}

// ── View ──
$activeCommunityId = $_GET['c'] ?? '';
$activeCommunity = null;
if ($activeCommunityId) {
    $idx = cs_find_community_index($activeCommunityId);
    if ($idx !== null) {
        $activeCommunity = $_SESSION['communities'][$idx];
        
        // Load community posts from database
        try {
            $pdo = get_db_connection();
            cs_load_community_posts($pdo, $activeCommunityId, $meEmail);
        } catch (Exception $e) {
            // Do nothing if fails
        }
    }
}

$search = trim($_GET['q'] ?? '');
$view = $_GET['view'] ?? 'all';
$allowedViews = ['all','joined','recommended','groups','organizations'];
if (!in_array($view, $allowedViews, true)) $view = 'all';

$communitiesAll = $_SESSION['communities'];
if ($search !== '') {
    $communitiesAll = array_values(array_filter($communitiesAll, function ($c) use ($search) {
        $name = $c['name'] ?? '';
        $desc = $c['description'] ?? '';
        return stripos($name . ' ' . $desc, $search) !== false;
    }));
}

$joined = $_SESSION['joined_communities'][$meEmail] ?? [];

// Helper: build links while preserving current search/community selection
function cs_build_communities_url(array $params): string {
    $q = array_filter($params, function ($v) { return $v !== null && $v !== ''; });
    return 'communities.php' . (empty($q) ? '' : ('?' . http_build_query($q)));
}

// Filter list by view (Reddit-like sidebar)
$communities = $communitiesAll;
if ($view === 'joined') {
    $communities = array_values(array_filter($communities, function ($c) use ($joined) {
        return in_array($c['id'] ?? '', $joined, true);
    }));
} elseif ($view === 'recommended') {
    $communities = array_values(array_filter($communities, function ($c) use ($joined) {
        return !in_array($c['id'] ?? '', $joined, true);
    }));
} elseif ($view === 'groups') {
    $communities = array_values(array_filter($communities, function ($c) {
        return (($c['type'] ?? 'group') === 'group');
    }));
} elseif ($view === 'organizations') {
    $communities = array_values(array_filter($communities, function ($c) {
        return (($c['type'] ?? 'group') === 'organization');
    }));
}

// Sorting:
// - recommended: by members desc
// - others: joined first, then members desc
usort($communities, function ($a, $b) use ($joined, $view) {
    if ($view !== 'recommended') {
        $aj = in_array($a['id'] ?? '', $joined, true) ? 1 : 0;
        $bj = in_array($b['id'] ?? '', $joined, true) ? 1 : 0;
        if ($aj !== $bj) return $bj <=> $aj;
    }
    return (int)($b['members'] ?? 0) <=> (int)($a['members'] ?? 0);
});

// Counts for sidebar chips
$countAll = count($communitiesAll);
$countJoined = 0;
$countRecommended = 0;
$countGroups = 0;
$countOrgs = 0;
foreach ($communitiesAll as $c) {
    $cid = $c['id'] ?? '';
    $isJ = $cid && in_array($cid, $joined, true);
    if ($isJ) $countJoined++;
    else $countRecommended++;
    if (($c['type'] ?? 'group') === 'group') $countGroups++;
    if (($c['type'] ?? 'group') === 'organization') $countOrgs++;
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 h-[calc(100vh-140px)] md:h-auto">

        <!-- Left: List -->
        <div class="lg:col-span-4 flex flex-col min-h-0 <?php echo $activeCommunityId ? 'hidden lg:flex' : 'flex'; ?>">
            <div class="bg-white rounded-2xl shadow-md overflow-hidden flex flex-col flex-1 min-h-0">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between gap-3">
                    <h2 class="text-xl font-bold text-gray-900">Communities</h2>
                    <button id="openCreateCommunity" type="button" class="bg-blue-900 hover:bg-blue-800 text-white text-sm font-semibold px-3 py-2 rounded-xl transition">
                        Create
                    </button>
                </div>

                <div class="p-4 border-b border-gray-200">
                    <form method="GET" class="flex gap-2">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search..." class="flex-1 px-3 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm min-w-0">
                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                        <?php if (!empty($activeCommunityId)): ?>
                            <input type="hidden" name="c" value="<?php echo htmlspecialchars($activeCommunityId); ?>">
                        <?php endif; ?>
                        <button class="bg-gray-900 hover:bg-gray-800 text-white px-3 py-2 rounded-xl text-xs font-semibold flex-shrink-0">Search</button>
                    </form>
                </div>

                <!-- Sidebar tabs (Reddit-like) -->
                <div class="p-3 md:p-4 border-b border-gray-200 bg-gray-50 overflow-x-auto">
                    <?php
                        $base = ['q' => $search, 'c' => $activeCommunityId ?: null];
                        $tabClass = function($key) use ($view) {
                            return $view === $key
                                ? 'bg-blue-900 text-white border-blue-900'
                                : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50';
                        };
                    ?>
                    <div class="flex lg:grid lg:grid-cols-2 gap-2 min-w-max lg:min-w-0">
                        <a href="<?php echo cs_build_communities_url(array_merge($base, ['view' => 'all'])); ?>"
                           class="text-[10px] md:text-xs font-semibold px-2 py-1.5 md:px-3 md:py-2 rounded-xl border transition <?php echo $tabClass('all'); ?>">
                            All (<?php echo $countAll; ?>)
                        </a>
                        <a href="<?php echo cs_build_communities_url(array_merge($base, ['view' => 'joined'])); ?>"
                           class="text-[10px] md:text-xs font-semibold px-2 py-1.5 md:px-3 md:py-2 rounded-xl border transition <?php echo $tabClass('joined'); ?>">
                            Joined (<?php echo $countJoined; ?>)
                        </a>
                        <a href="<?php echo cs_build_communities_url(array_merge($base, ['view' => 'recommended'])); ?>"
                           class="text-[10px] md:text-xs font-semibold px-2 py-1.5 md:px-3 md:py-2 rounded-xl border transition <?php echo $tabClass('recommended'); ?>">
                            Recs (<?php echo $countRecommended; ?>)
                        </a>
                        <a href="<?php echo cs_build_communities_url(array_merge($base, ['view' => 'groups'])); ?>"
                           class="text-[10px] md:text-xs font-semibold px-2 py-1.5 md:px-3 md:py-2 rounded-xl border transition <?php echo $tabClass('groups'); ?>">
                            Groups (<?php echo $countGroups; ?>)
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="p-4 bg-red-50 border-b border-red-200 text-red-700 text-sm">
                        <?php foreach ($errors as $e): ?>
                            <p><?php echo htmlspecialchars($e); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="divide-y divide-gray-100 flex-1 overflow-y-auto min-h-0">
                    <?php if (empty($communities)): ?>
                        <div class="p-6 text-center text-sm text-gray-500">
                            No communities found.
                        </div>
                    <?php endif; ?>
                    <?php foreach ($communities as $c): ?>
                        <?php
                            $cid = $c['id'];
                            $isJoined = cs_is_joined($meEmail, $cid);
                            $isActive = $activeCommunityId === $cid;
                            $typeLabel = ($c['type'] ?? 'group') === 'organization' ? 'Organization' : 'Group';
                        ?>
                        <a href="communities.php?c=<?php echo urlencode($cid); ?>" class="block p-4 hover:bg-gray-50 transition <?php echo $isActive ? 'bg-blue-50' : ''; ?>">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center justify-center w-8 h-8 md:w-9 md:h-9 rounded-xl bg-blue-100 text-blue-900 font-bold flex-shrink-0">
                                            <?php echo strtoupper(substr($c['name'], 0, 1)); ?>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-bold text-gray-900 truncate text-sm md:text-base"><?php echo htmlspecialchars($c['name']); ?></p>
                                            <p class="text-[10px] md:text-xs text-gray-500"><?php echo $typeLabel; ?> • <?php echo number_format((int)($c['members'] ?? 0)); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($isJoined): ?>
                                    <span class="text-[10px] font-bold text-green-700 bg-green-100 px-2 py-0.5 rounded-full flex-shrink-0">Joined</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right: Detail -->
        <div class="lg:col-span-8 flex flex-col min-h-0 <?php echo $activeCommunityId ? 'flex' : 'hidden lg:flex'; ?>">
            <?php if (!$activeCommunity): ?>
                <div class="bg-white rounded-2xl shadow-md p-10 text-center flex-1 flex flex-col items-center justify-center">
                    <div class="text-4xl mb-3">👥</div>
                    <h3 class="text-xl font-bold text-gray-900">Pick a community</h3>
                    <p class="text-gray-500 mt-2">Join groups or organizations and start posting like Reddit communities.</p>
                </div>
            <?php else: ?>
                <?php
                    $cid = $activeCommunity['id'];
                    $isJoined = cs_is_joined($meEmail, $cid);
                    $typeLabel = ($activeCommunity['type'] ?? 'group') === 'organization' ? 'Organization' : 'Group';
                    $posts = $_SESSION['community_posts'][$cid] ?? [];
                ?>
                <div class="bg-white rounded-2xl shadow-md overflow-hidden flex flex-col flex-1 min-h-0">
                    <div class="p-4 md:p-5 border-b border-gray-200 flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <a href="communities.php" class="lg:hidden p-2 -ml-2 text-gray-500 hover:text-blue-900 flex-shrink-0" aria-label="Back to communities">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </a>
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-2xl bg-gradient-to-br from-blue-900 to-cyan-800 text-white flex items-center justify-center font-black text-lg md:text-xl flex-shrink-0">
                                        <?php echo strtoupper(substr($activeCommunity['name'], 0, 1)); ?>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-lg md:text-2xl font-bold text-gray-900 truncate"><?php echo htmlspecialchars($activeCommunity['name']); ?></h3>
                                        <p class="text-[10px] md:text-sm text-gray-500 truncate"><?php echo $typeLabel; ?> • <?php echo number_format((int)($activeCommunity['members'] ?? 0)); ?> members</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <?php if (!$isJoined): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="join_community">
                                    <input type="hidden" name="community_id" value="<?php echo htmlspecialchars($cid); ?>">
                                    <button class="bg-blue-900 hover:bg-blue-800 text-white font-semibold px-3 py-1.5 md:px-4 md:py-2 rounded-xl transition text-xs md:text-sm">Join</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" onsubmit="return confirm('Leave this community?');">
                                    <input type="hidden" name="action" value="leave_community">
                                    <input type="hidden" name="community_id" value="<?php echo htmlspecialchars($cid); ?>">
                                    <button class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-800 font-semibold px-3 py-1.5 md:px-4 md:py-2 rounded-xl transition text-xs md:text-sm">Joined ✓</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto min-h-0">
                        <div class="p-4 md:p-5 border-b border-gray-100 bg-gray-50">
                            <?php if (!$isJoined): ?>
                                <p class="text-sm text-gray-600">Join this community to create posts.</p>
                            <?php else: ?>
                                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                                    <input type="hidden" name="action" value="create_community_post">
                                    <input type="hidden" name="community_id" value="<?php echo htmlspecialchars($cid); ?>">
                                    <textarea name="content" rows="3" class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="Create a post..."></textarea>
                                    <div>
                                        <label for="community_attachment" class="text-sm font-medium text-gray-700 cursor-pointer flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                            Attach a file
                                        </label>
                                        <input type="file" name="attachment" id="community_attachment" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-900 hover:file:bg-blue-100">
                                    </div>
                                    <div class="flex justify-end">
                                        <button class="bg-blue-900 hover:bg-blue-800 text-white font-bold px-5 py-2 rounded-xl transition text-sm">Post</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>

                        <div class="p-4 md:p-5 space-y-4">
                            <?php if (empty($posts)): ?>
                                <div class="text-center py-10">
                                    <div class="text-3xl mb-2">🗨️</div>
                                    <p class="text-gray-500">No posts yet. Be the first to post!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($posts as $p): ?>
                                    <div class="border border-gray-200 rounded-2xl p-4 bg-white">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <div class="w-8 h-8 md:w-9 md:h-9 rounded-full bg-blue-100 text-blue-900 font-bold flex items-center justify-center overflow-hidden flex-shrink-0">
                                                    <?php if (!empty($p['avatar'])): ?>
                                                        <img src="<?php echo htmlspecialchars($p['avatar']); ?>" alt="" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <?php echo strtoupper(substr($p['user'] ?? 'U', 0, 1)); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-bold text-gray-900 truncate text-sm"><?php echo htmlspecialchars($p['user'] ?? 'User'); ?></p>
                                                    <p class="text-[10px] text-gray-500 truncate"><?php echo htmlspecialchars($p['time'] ?? ''); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="mt-3 text-gray-800 whitespace-pre-wrap break-words text-sm"><?php echo nl2br(htmlspecialchars($p['content'] ?? '')); ?></p>
                                        <?php if (!empty($p['attachment'])): ?>
                                            <?php $att = $p['attachment']; ?>
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
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Community Modal -->
<div id="createCommunityBackdrop" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Create a community</h3>
            <button id="closeCreateCommunity" type="button" class="text-gray-500 hover:text-gray-800" aria-label="Close">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="create_community">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Name</label>
                <input type="text" name="name" maxlength="60" required class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="e.g., Blue Team PH">
                <p class="text-xs text-gray-400 mt-1">Max 60 characters.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Type</label>
                <select name="type" class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value="group">Group</option>
                    <option value="organization">Organization</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3" maxlength="240" class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="What is this community about?"></textarea>
                <p class="text-xs text-gray-400 mt-1">Optional. Max 240 characters.</p>
            </div>
            <button type="submit" class="w-full bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 rounded-xl transition">
                Create
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    const openBtn = document.getElementById('openCreateCommunity');
    const closeBtn = document.getElementById('closeCreateCommunity');
    const backdrop = document.getElementById('createCommunityBackdrop');

    function openModal() {
        if (!backdrop) return;
        backdrop.classList.remove('hidden');
        backdrop.classList.add('flex');
        const first = backdrop.querySelector('input[name="name"]');
        if (first) setTimeout(() => first.focus(), 50);
    }
    function closeModal() {
        if (!backdrop) return;
        backdrop.classList.add('hidden');
        backdrop.classList.remove('flex');
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
})();
</script>

<?php include 'includes/footer.php'; ?>
