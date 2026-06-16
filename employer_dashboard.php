<?php
require_once 'includes/bootstrap.php';
require_once 'role_helpers.php';
cs_require_auth();
cs_init_assessments();

// Quick check to ensure tables exist
function ensure_tables_exist() {
    try {
        $pdo = get_db_connection();
        
        // Check if conversations table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'conversations'");
        if (!$stmt->fetch()) {
            // Create conversations table
            $pdo->exec("CREATE TABLE conversations (
                conversation_id INT AUTO_INCREMENT PRIMARY KEY,
                user_a INT NOT NULL,
                user_b INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_a) REFERENCES users(user_id) ON DELETE CASCADE,
                FOREIGN KEY (user_b) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Check if messages table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE TABLE messages (
                message_id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                sender_id INT NOT NULL,
                body TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
                FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            // Check if is_read column exists, add if not
            $checkCol = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_read'");
            if (!$checkCol->fetch()) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN is_read BOOLEAN DEFAULT FALSE");
            }
            // Check if sent_at column exists, add if not
            $checkCol = $pdo->query("SHOW COLUMNS FROM messages LIKE 'sent_at'");
            if (!$checkCol->fetch()) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            }
        }
        
        // Check if message_attachments table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'message_attachments'");
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE TABLE message_attachments (
                attachment_id INT AUTO_INCREMENT PRIMARY KEY,
                message_id INT NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_url VARCHAR(255) NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Check assessment_sessions table - fix post_id to allow NULL
        $stmt = $pdo->query("SHOW TABLES LIKE 'assessment_sessions'");
        if ($stmt->fetch()) {
            // Check if post_id is nullable
            $checkCol = $pdo->query("SHOW COLUMNS FROM assessment_sessions LIKE 'post_id'");
            $colInfo = $checkCol->fetch(PDO::FETCH_ASSOC);
            if ($colInfo && strpos(strtolower($colInfo['Null']), 'no') !== false) {
                // Make post_id nullable
                $pdo->exec("ALTER TABLE assessment_sessions MODIFY COLUMN post_id INT NULL, DROP FOREIGN KEY IF EXISTS assessment_sessions_ibfk_2, ADD CONSTRAINT assessment_sessions_ibfk_2 FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE SET NULL");
            }
        }
    } catch (Exception $e) {
        // Silently ignore table errors in production
    }
}
ensure_tables_exist();

// Auto-upgrade session role if file store shows approval
if (isset($_SESSION['user']) && !cs_is_employer() && !cs_is_admin()) {
    $myApp = cs_get_employer_application_by_email($_SESSION['user']['email']);
    if ($myApp && $myApp['status'] === 'approved') {
        $_SESSION['user']['role']              = 'employer';
        $_SESSION['user']['employer_verified'] = true;
        $_SESSION['user']['company_name']      = $myApp['company_name'] ?? $_SESSION['user']['username'];
    }
}

if (!cs_is_employer() && !cs_is_admin()) {
    $_SESSION['flash_error'] = 'Your employer account is not yet verified.';
    header('Location: employer_apply.php'); exit;
}

$pageTitle   = 'Employer Dashboard - CyberSphere';
$currentPage = 'employer_dashboard';
$tab         = $_GET['tab'] ?? 'applicants';
$flash       = '';
$flashType   = 'green';
$myEmail     = $_SESSION['user']['email'] ?? '';
$companyName = $_SESSION['user']['company_name'] ?? $_SESSION['user']['username'] ?? 'Your Company';

// ── Handle Create Assessment POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_assessment') {
    $title      = trim($_POST['assess_title'] ?? '');
    $role       = trim($_POST['assess_role'] ?? '');
    $timeLimit  = max(5, min(180, (int)($_POST['assess_time'] ?? 30)));
    $instructions = trim($_POST['assess_instructions'] ?? '');

    $challengesRaw = $_POST['challenges'] ?? [];
        $challenges = [];
        // We'll collect attachments per challenge index
        $challengeAttachments = [];
        $challengeOptions = [];
        foreach ($challengesRaw as $index => $c) {
            $ctype  = trim($c['type'] ?? 'flag');
            // Normalize the type name
            if (in_array($ctype, ['multiple-choice', 'multiple_choice', 'mcq'])) {
                $ctype = 'multiple_choice';
            }
            $ctitle = trim($c['title'] ?? '');
            $cbody  = trim($c['body'] ?? '');
            $cpts   = max(10, min(500, (int)($c['points'] ?? 100)));
            $chint  = trim($c['hint'] ?? '');
            $cflag  = trim($c['correct_flag'] ?? '');
            if (!$ctitle || !$cbody) continue;
            
            // Get options for multiple choice
            $options = $c['options'] ?? [];
            $correctOption = $c['correct_option'] ?? 'A';
            
            // Check for uploaded files and notes for this challenge
            $attachments = [];
            $attachmentNotes = $c['attachment_notes'] ?? [];
            
            // Function to get file icon based on extension
            $getFileIcon = function($fileName) {
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $iconMap = [
                    'pdf' => '📕',
                    'doc' => '📄', 'docx' => '📄', 'odt' => '📄', 'rtf' => '📄', 'txt' => '📄',
                    'png' => '🖼️', 'jpg' => '🖼️', 'jpeg' => '🖼️', 'gif' => '🖼️', 'svg' => '🖼️', 'ico' => '🖼️',
                    'ppt' => '📊', 'pptx' => '📊', 'xls' => '📊', 'xlsx' => '📊', 'csv' => '📊',
                    'zip' => '📦', 'rar' => '📦', '7z' => '📦', 'tar' => '📦', 'gz' => '📦', 'xz' => '📦', 'bz2' => '📦',
                    'mp3' => '🎵', 'wav' => '🎵', 'ogg' => '🎵', 'm4a' => '🎵',
                    'mp4' => '🎬', 'webm' => '🎬', 'avi' => '🎬', 'mov' => '🎬',
                    'pcap' => '🔍', 'pcapng' => '🔍',
                    'pka' => '🔬', 'pkt' => '🔬', // Packet Tracer
                    'md' => '📝',
                    'html' => '🌐',
                    'css' => '🎨', 'scss' => '🎨', 'less' => '🎨',
                    'js' => '💻', 'ts' => '💻',
                    'json' => '📋', 'xml' => '📋', 'yaml' => '📋', 'yml' => '📋',
                    'php' => '🐘',
                    'py' => '🐍', 'rb' => '💎',
                    'java' => '☕', 'class' => '☕', 'jar' => '☕',
                    'cpp' => '⚙️', 'c' => '⚙️', 'h' => '⚙️',
                    'cs' => '🔷',
                    'go' => '🐹', 'rs' => '🦀', 'kt' => '📱'
                ];
                return $iconMap[$ext] ?? '📄';
            };
            
            if (isset($_FILES['challenges']) && isset($_FILES['challenges']['name'][$index]) && isset($_FILES['challenges']['name'][$index]['attachments'])) {
                $fileNames = $_FILES['challenges']['name'][$index]['attachments'];
                $fileTmpPaths = $_FILES['challenges']['tmp_name'][$index]['attachments'];
                $fileErrors = $_FILES['challenges']['error'][$index]['attachments'];
                
                foreach ($fileNames as $fileIdx => $fileName) {
                    if ($fileErrors[$fileIdx] === UPLOAD_ERR_OK && !empty($fileName)) {
                        $attachments[] = [
                            'name' => $fileName,
                            'tmp_name' => $fileTmpPaths[$fileIdx],
                            'type' => $_FILES['challenges']['type'][$index]['attachments'][$fileIdx],
                            'icon' => $getFileIcon($fileName),
                            'note' => $attachmentNotes[$fileIdx] ?? ''
                        ];
                    }
                }
            }
            $challengeAttachments[] = $attachments;
            $challengeOptions[] = [
                'options' => $options,
                'correct_option' => $correctOption
            ];
            
            $challenges[] = [
                'id'           => 'c_' . uniqid('', true),
                'type'         => $ctype,
                'title'        => $ctitle,
                'body'         => $cbody,
                'points'       => $cpts,
                'hint'         => $chint,
                'correct_flag' => $cflag,
                'attachments'  => $attachments,
                'options'      => $options,
                'correct_option' => $correctOption
            ];
        }

    $assessErrors = [];
    if (!$title) $assessErrors[] = 'Assessment title is required.';
    if (empty($challenges)) $assessErrors[] = 'Add at least one challenge.';

    if (empty($assessErrors)) {
        $pdo = get_db_connection();
        try {
            $pdo->beginTransaction();
            // Get user id
            $stmtUserId = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
            $stmtUserId->execute([$myEmail]);
            $userId = $stmtUserId->fetchColumn();
            if (!$userId) throw new Exception('User not found in database');
            
            // Create upload directory if it doesn't exist
            $uploadDir = __DIR__ . '/uploads/challenges/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Insert assessment
            $stmt = $pdo->prepare('INSERT INTO assessments (title, instructions, time_limit_mins, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$title, $instructions, $timeLimit, $userId]);
            $dbAssessId = $pdo->lastInsertId();
            
            // Insert challenges
            foreach ($challenges as $index => $challenge) {
                $stmt = $pdo->prepare('INSERT INTO assessment_challenges (assessment_id, type, title, body, points_available, correct_answer, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$dbAssessId, $challenge['type'], $challenge['title'], $challenge['body'], $challenge['points'], $challenge['correct_flag'], $index]);
                $challengeId = $pdo->lastInsertId();
                
                // Process and save attachments for this challenge
                if (isset($challengeAttachments[$index])) {
                    foreach ($challengeAttachments[$index] as $attachment) {
                        // Generate a unique filename to avoid conflicts
                        $fileExt = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                        $uniqueFileName = uniqid('challenge_', true) . '.' . $fileExt;
                        $destinationPath = $uploadDir . $uniqueFileName;
                        
                        // Move the uploaded file
                        if (move_uploaded_file($attachment['tmp_name'], $destinationPath)) {
                            // Insert into challenge_attachments table
                            $stmtAttach = $pdo->prepare('INSERT INTO challenge_attachments (challenge_id, file_name, file_path, icon, note) VALUES (?, ?, ?, ?, ?)');
                            $stmtAttach->execute([
                                $challengeId,
                                $attachment['name'],
                                'uploads/challenges/' . $uniqueFileName,
                                $attachment['icon'] ?? 'file',
                                $attachment['note'] ?? null
                            ]);
                        }
                    }
                }
                
                // Insert multiple choice options if type is multiple_choice
                if ($challenge['type'] === 'multiple_choice' && isset($challengeOptions[$index])) {
                    $options = $challengeOptions[$index]['options'];
                    $correctLetter = $challengeOptions[$index]['correct_option'];
                    $optionLetters = ['A', 'B', 'C', 'D', 'E'];
                    foreach ($optionLetters as $optIndex => $letter) {
                        if (isset($options[$letter])) {
                            $optionText = trim($options[$letter]);
                            $isCorrect = ($letter === $correctLetter) ? 1 : 0;
                            $stmtOption = $pdo->prepare('INSERT INTO challenge_options (challenge_id, option_text, is_correct) VALUES (?, ?, ?)');
                            $stmtOption->execute([$challengeId, $optionText, $isCorrect]);
                        }
                    }
                }
            }
            $pdo->commit();
            
            // Also save to session for backwards compatibility
            $assessId = 'assess_' . $dbAssessId; // Use DB ID as part of session ID
            $_SESSION['cs_assessments'][] = [
                'id'            => $assessId,
                'db_id'         => $dbAssessId,
                'employer_email'=> $myEmail,
                'employer_name' => $companyName,
                'title'         => $title,
                'role'          => $role,
                'time_limit'    => $timeLimit,
                'instructions'  => $instructions,
                'challenges'    => $challenges,
                'created_at'    => date('M j, Y g:i A'),
                'total_pts'     => array_sum(array_column($challenges, 'points')),
            ];
            
            $flash = 'Assessment "' . htmlspecialchars($title) . '" created successfully!';
            header('Location: employer_dashboard.php?tab=assessments&flash=' . urlencode($flash));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $assessErrors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Helper function to get user info from DB
function get_user_info($user_id) {
    static $cache = [];
    if (isset($cache[$user_id])) return $cache[$user_id];
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $cache[$user_id] = $user;
            return $user;
        }
    } catch (Exception $e) {
        // Fallback
    }
    $cache[$user_id] = null;
    return null;
}

// Helper function to get or create conversation
function get_or_create_conversation($my_id, $other_id) {
    if ($my_id == $other_id) return null;
    $low = min($my_id, $other_id);
    $high = max($my_id, $other_id);
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT conversation_id, user_a, user_b, created_at FROM conversations WHERE (user_a = ? AND user_b = ?) OR (user_a = ? AND user_b = ?)");
    $stmt->execute([$low, $high, $low, $high]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($conv) return $conv;
    
    $stmt = $pdo->prepare("INSERT INTO conversations (user_a, user_b) VALUES (?, ?)");
    $stmt->execute([$low, $high]);
    return [
        'conversation_id' => $pdo->lastInsertId(),
        'user_a' => $low,
        'user_b' => $high,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

// ── Handle Send Assessment via message ────────────────────────────────────
$immediateLog = __DIR__ . '/debug-immediate.log';
file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - Checking send_assessment_msg handler\n", FILE_APPEND);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - POST RECEIVED: " . print_r($_POST, true) . "\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_assessment_msg') {
    file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - send_assessment_msg handler ACTIVATED!\n", FILE_APPEND);
    $assessId    = $_POST['assess_id'] ?? '';
    $recipEmail  = trim($_POST['recipient_email'] ?? '');
    $customMsg   = trim($_POST['custom_message'] ?? '');
    $assessment  = cs_get_assessment_by_id($assessId);
    $dbAssessId  = $assessment['db_id'] ?? null;
    
    file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - assessId: $assessId, recipEmail: $recipEmail, assessment exists? " . ($assessment ? 'YES' : 'NO') . "\n", FILE_APPEND);

    if ($assessment && $recipEmail) {
        // Use session values directly to avoid variable order issues
        $myEmail = $_SESSION['user']['email'] ?? '';
        $companyName = $_SESSION['user']['company_name'] ?? $_SESSION['user']['username'] ?? 'Your Company';
        $senderUserId = $_SESSION['user']['user_id'] ?? null;

        file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - senderUserId: $senderUserId, myEmail: $myEmail\n", FILE_APPEND);

        $pdo = get_db_connection();
        try {
            $pdo->beginTransaction();
            
            // Get recipient user ID
            $stmtUserId = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
            $stmtUserId->execute([$recipEmail]);
            $recipUserId = $stmtUserId->fetchColumn();
            file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - recipUserId: " . ($recipUserId ?? 'NULL') . "\n", FILE_APPEND);

            $link = 'assessment_session.php?session=' . urlencode($assessId);
            // Use http instead of https for localhost, and get correct base path
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
            $scriptName = $_SERVER['SCRIPT_NAME'];
            $basePath = dirname($scriptName);
            $fullLink = $protocol . $_SERVER['HTTP_HOST'] . $basePath . '/' . $link;
            $msgText = ($customMsg ? $customMsg . "\n\n" : '')
                 . '📋 Skill Assessment: ' . $assessment['title'] . "\n"
                 . 'Role: ' . ($assessment['role'] ?: 'General') . "\n"
                 . 'Time Limit: ' . $assessment['time_limit'] . " minutes\n"
                 . 'Start here: ' . $fullLink;
            file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - msgText: $msgText\n", FILE_APPEND);

            // 1. Find or create conversation in database
            $convId = null;
            if ($senderUserId && $recipUserId) {
                $conv = get_or_create_conversation($senderUserId, $recipUserId);
                $convId = $conv['conversation_id'] ?? null;
                file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - convId: " . ($convId ?? 'NULL') . "\n", FILE_APPEND);

                // 2. Add message to conversation
                $stmtMsg = $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, body, is_read) VALUES (?, ?, ?, ?)');
                $stmtMsg->execute([$convId, $senderUserId, $msgText, false]);
                $messageId = $pdo->lastInsertId();
                file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - message inserted with ID: $messageId\n", FILE_APPEND);
            } else {
                file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - Skipping DB message: sender or recipient ID missing\n", FILE_APPEND);
            }

            // 3. Create assessment session if we have db assess id
            $sessionId = null;
            if ($dbAssessId && $recipUserId) {
                $stmtSession = $pdo->prepare('INSERT INTO assessment_sessions (assessment_id, post_id, deadline, status) VALUES (?, ?, ?, ?)');
                $stmtSession->execute([$dbAssessId, null, null, 'active']);
                $sessionId = $pdo->lastInsertId();
                file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - assessment session created with ID: $sessionId\n", FILE_APPEND);
            }

            $pdo->commit();
            file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - Transaction committed successfully!\n", FILE_APPEND);

            // Also update session for backwards compatibility
            if (!isset($_SESSION['messages'])) $_SESSION['messages'] = [];
            $sessionConvId = null;
            foreach ($_SESSION['messages'] as $conv) {
                $parts = $conv['participants'] ?? [];
                if (in_array($myEmail, $parts) && in_array($recipEmail, $parts)) {
                    $sessionConvId = $conv['id'];
                    break;
                }
            }
            if (!$sessionConvId) {
                $sessionConvId = 'conv_' . uniqid('', true);
                $_SESSION['messages'][] = [
                    'id'           => $sessionConvId,
                    'participants' => [$myEmail, $recipEmail],
                    'messages'     => [],
                ];
            }
            foreach ($_SESSION['messages'] as &$conv) {
                if ($conv['id'] === $sessionConvId) {
                    $conv['messages'][] = [
                        'id'   => 'msg_' . uniqid('', true),
                        'from' => $myEmail,
                        'text' => $msgText,
                        'time' => date('g:i A'),
                        'ts'   => time(),
                    ];
                    break;
                }
            }
            unset($conv);

            // Notify recipient
            if ($recipUserId) {
                cs_save_notification($recipUserId, $companyName . ' sent you a skill assessment: ' . $assessment['title'], 'messages.php?conv=' . urlencode($convId));
                file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - Notification saved for user ID: $recipUserId\n", FILE_APPEND);
            } else {
                array_unshift($_SESSION['notifications'], [
                    'msg'  => $companyName . ' sent you a skill assessment: ' . $assessment['title'],
                    'time' => date('M j, Y g:i A'),
                    'read' => false,
                    'link' => 'messages.php',
                ]);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            file_put_contents($immediateLog, date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            // Fall back to old session method
            if (!isset($_SESSION['messages'])) $_SESSION['messages'] = [];
            $sessionConvId = null;
            foreach ($_SESSION['messages'] as $conv) {
                $parts = $conv['participants'] ?? [];
                if (in_array($myEmail, $parts) && in_array($recipEmail, $parts)) {
                    $sessionConvId = $conv['id'];
                    break;
                }
            }
            if (!$sessionConvId) {
                $sessionConvId = 'conv_' . uniqid('', true);
                $_SESSION['messages'][] = [
                    'id'           => $sessionConvId,
                    'participants' => [$myEmail, $recipEmail],
                    'messages'     => [],
                ];
            }
            foreach ($_SESSION['messages'] as &$conv) {
                if ($conv['id'] === $sessionConvId) {
                    $conv['messages'][] = [
                        'id'   => 'msg_' . uniqid('', true),
                        'from' => $myEmail,
                        'text' => $msgText,
                        'time' => date('g:i A'),
                        'ts'   => time(),
                    ];
                    break;
                }
            }
            unset($conv);

            array_unshift($_SESSION['notifications'], [
                'msg'  => $companyName . ' sent you a skill assessment: ' . $assessment['title'],
                'time' => date('M j, Y g:i A'),
                'read' => false,
                'link' => 'messages.php',
            ]);
        }

        $flash = 'Assessment link sent to ' . htmlspecialchars($recipEmail) . ' via messages.';
        header('Location: employer_dashboard.php?tab=assessments&flash=' . urlencode($flash));
        exit;
    }
}

if (isset($_GET['flash'])) { $flash = $_GET['flash']; $flashType = $_GET['ft'] ?? 'green'; }

// ── Data ───────────────────────────────────────────────────────────────────
$myApplications  = cs_get_all_employer_post_applications($myEmail);
$myAssessments   = cs_get_assessments_by_employer($myEmail);

// Load $myPosts from database
try {
    $pdo = get_db_connection();
    // Get user id from email first
    $stmtUserId = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
    $stmtUserId->execute([$myEmail]);
    $userId = $stmtUserId->fetchColumn();
    
    if ($userId) {
        $stmtPosts = $pdo->prepare('SELECT post_id, user_id, type, content, created_at FROM posts WHERE user_id = ? AND (type = "job") ORDER BY created_at DESC');
        $stmtPosts->execute([$userId]);
        $myPostsFromDB = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);
        
        // Load job details
        $jobStmt = $pdo->prepare('SELECT post_id, is_hiring, enable_apply FROM job_post_details WHERE post_id = ?');
        $myPosts = [];
        foreach ($myPostsFromDB as $post) {
            $jobStmt->execute([$post['post_id']]);
            $jobDetails = $jobStmt->fetch(PDO::FETCH_ASSOC);
            $myPosts[] = array_merge($post, [
                'is_hiring' => (bool)($jobDetails['is_hiring'] ?? true),
                'enable_apply' => (bool)($jobDetails['enable_apply'] ?? false),
                'id' => $post['post_id'],
                'time' => date('M j, Y g:i A', strtotime($post['created_at']))
            ]);
        }
    } else {
        // Fall back to session
        $myPosts = array_values(array_filter($_SESSION['posts'] ?? [], fn($p) => ($p['email'] ?? '') === $myEmail && !empty($p['hiring'])));
    }
} catch (Exception $e) {
    // Fall back to session
    $myPosts = array_values(array_filter($_SESSION['posts'] ?? [], fn($p) => ($p['email'] ?? '') === $myEmail && !empty($p['hiring'])));
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 pb-12">
<div class="max-w-7xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-extrabold text-blue-900"><?php echo htmlspecialchars($companyName); ?></h1>
            <p class="text-gray-500 text-sm mt-1">Employer Dashboard</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="bg-purple-100 text-purple-700 text-xs font-bold px-3 py-1.5 rounded-full uppercase tracking-wider">Employer</span>
            <a href="index.php" class="text-sm text-blue-700 font-semibold hover:underline">← Feed</a>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-semibold
        <?php echo $flashType === 'red' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
        <?php echo htmlspecialchars($flash); ?>
    </div>
    <?php endif; ?>

    <!-- Stats row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <?php
        $stats = [
            ['Hiring Posts', count($myPosts), '📝'],
            ['Total Applicants', count($myApplications), '👥'],
            ['Assessments', count($myAssessments), '📋'],
            ['New This Week', count(array_filter($myApplications, fn($a) => (strtotime($a['time'] ?? '0') > strtotime('-7 days')))), '🔔'],
        ];
        foreach ($stats as [$label, $val, $icon]): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm text-center">
            <div class="text-2xl mb-1"><?php echo $icon; ?></div>
            <div class="text-3xl font-extrabold text-blue-900"><?php echo $val; ?></div>
            <div class="text-xs text-gray-500 mt-1"><?php echo $label; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 bg-white rounded-2xl shadow-sm border border-gray-200 p-1 mb-8 overflow-x-auto">
        <?php foreach (['applicants'=>'Applicants','assessments'=>'Assessments','create_assessment'=>'+ New Assessment'] as $key=>$label): ?>
        <a href="?tab=<?php echo $key; ?>"
           class="flex-shrink-0 px-4 py-2 rounded-xl text-sm font-semibold transition
           <?php echo $tab === $key ? 'bg-blue-900 text-white' : 'text-gray-600 hover:bg-gray-100'; ?>">
            <?php echo $label; ?>
            <?php if ($key === 'applicants' && count($myApplications) > 0): ?>
            <span class="ml-1 bg-blue-700 text-white text-xs rounded-full px-1.5 py-0.5"><?php echo count($myApplications); ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── APPLICANTS TAB ── -->
    <?php if ($tab === 'applicants'): ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900">All Applicants (<?php echo count($myApplications); ?>)</h2>
        </div>
        <?php if (empty($myApplications)): ?>
        <div class="p-12 text-center text-gray-400">
            <div class="text-5xl mb-3">👥</div>
            <p class="font-medium">No applicants yet.</p>
            <p class="text-xs mt-1">Post a hiring job on the feed and enable the "Apply" button to start receiving applications.</p>
            <a href="index.php" class="inline-block mt-4 bg-blue-900 text-white font-bold px-5 py-2.5 rounded-xl text-sm hover:bg-blue-800 transition">Go to Feed</a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="text-left px-5 py-3">Applicant</th>
                        <th class="text-left px-4 py-3">Post</th>
                        <th class="text-left px-4 py-3">Applied</th>
                        <th class="text-left px-4 py-3">Resume</th>
                        <th class="text-center px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($myApplications as $app): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-900 font-bold text-sm flex-shrink-0">
                                    <?php echo strtoupper(substr($app['name'] ?? '?', 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($app['name'] ?? '—'); ?></p>
                                    <p class="text-gray-400 text-xs"><?php echo htmlspecialchars($app['email'] ?? ''); ?></p>
                                    <?php if (!empty($app['phone'])): ?>
                                    <p class="text-gray-400 text-xs"><?php echo htmlspecialchars($app['phone']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <p class="text-gray-600 text-xs max-w-[160px] truncate"><?php echo htmlspecialchars($app['post_content'] ?? '—'); ?></p>
                        </td>
                        <td class="px-4 py-4">
                            <p class="text-gray-500 text-xs"><?php echo htmlspecialchars($app['time'] ?? '—'); ?></p>
                        </td>
                        <td class="px-4 py-4">
                            <?php if (!empty($app['resume'])): ?>
                            <a href="<?php echo htmlspecialchars($app['resume']); ?>" target="_blank"
                               class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                                📎 View Resume
                            </a>
                            <?php else: ?>
                            <span class="text-gray-400 text-xs">No resume</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <button
                                class="send-assess-btn bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold px-3 py-1.5 rounded-xl transition"
                                data-name="<?php echo htmlspecialchars($app['name'] ?? ''); ?>"
                                data-email="<?php echo htmlspecialchars($app['email'] ?? ''); ?>">
                                Send Assessment
                            </button>
                        </td>
                    </tr>
                    <?php if (!empty($app['message'])): ?>
                    <tr class="bg-gray-50">
                        <td colspan="5" class="px-5 py-3">
                            <p class="text-xs text-gray-600"><strong class="text-gray-700">Cover message:</strong> <?php echo nl2br(htmlspecialchars($app['message'])); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── ASSESSMENTS TAB ── -->
    <?php elseif ($tab === 'assessments'): ?>
    <div class="space-y-4">
        <?php if (empty($myAssessments)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-400 shadow-sm">
            <div class="text-5xl mb-3">📋</div>
            <p class="font-medium">No assessments yet.</p>
            <a href="?tab=create_assessment" class="inline-block mt-4 bg-blue-900 text-white font-bold px-5 py-2.5 rounded-xl text-sm hover:bg-blue-800 transition">Create Your First Assessment</a>
        </div>
        <?php else: ?>
        <?php foreach ($myAssessments as $a): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <h3 class="font-bold text-lg text-gray-900"><?php echo htmlspecialchars($a['title']); ?></h3>
                    <p class="text-gray-500 text-sm mt-0.5">
                        <?php if ($a['role']): ?><span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full mr-2"><?php echo htmlspecialchars($a['role']); ?></span><?php endif; ?>
                        <?php echo count($a['challenges']); ?> challenges &bull; <?php echo $a['time_limit']; ?> min &bull; <?php echo $a['total_pts']; ?> pts total
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Created <?php echo htmlspecialchars($a['created_at']); ?></p>
                </div>
                <div class="flex flex-wrap gap-2 flex-shrink-0">
                    <a href="assessment_session.php?session=<?php echo urlencode($a['id']); ?>" target="_blank"
                       class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-3 py-2 rounded-xl transition">Preview</a>
                    <a href="assessment_dashboard.php?assess_id=<?php echo urlencode($a['id']); ?>"
                       class="text-xs bg-blue-900 hover:bg-blue-800 text-white font-semibold px-3 py-2 rounded-xl transition">View Results</a>
                    <button class="send-assess-btn text-xs bg-purple-700 hover:bg-purple-800 text-white font-semibold px-3 py-2 rounded-xl transition"
                            data-assess-id="<?php echo htmlspecialchars($a['id']); ?>"
                            data-assess-title="<?php echo htmlspecialchars($a['title']); ?>">
                        Send to Applicant
                    </button>
                </div>
            </div>

            <!-- Challenge list -->
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($a['challenges'] as $ch): ?>
                <div class="bg-gray-50 rounded-xl p-3 text-xs">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-gray-700 truncate"><?php echo htmlspecialchars($ch['title']); ?></span>
                        <span class="bg-blue-100 text-blue-700 font-bold px-1.5 rounded-full ml-2 flex-shrink-0"><?php echo $ch['points']; ?>pts</span>
                    </div>
                    <span class="<?php echo ['flag'=>'bg-orange-100 text-orange-700','code'=>'bg-green-100 text-green-700','short'=>'bg-gray-200 text-gray-600'][$ch['type']] ?? 'bg-gray-100 text-gray-600'; ?> px-1.5 py-0.5 rounded-full font-semibold"><?php echo ucfirst($ch['type']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── CREATE ASSESSMENT TAB ── -->
    <?php elseif ($tab === 'create_assessment'): ?>
    <?php if (!empty($assessErrors ?? [])): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
        <ul class="list-disc list-inside space-y-1">
            <?php foreach ($assessErrors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-1">Create Skill Assessment</h2>
        <p class="text-gray-500 text-sm mb-6">Build a custom assessment to send to applicants. You can add flag-style, coding, or short-answer challenges.</p>

        <form method="POST" id="assessForm" class="space-y-6" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create_assessment">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Assessment Title <span class="text-red-500">*</span></label>
                    <input name="assess_title" required maxlength="100" placeholder="e.g. SOC Analyst Skill Assessment"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Role / Position</label>
                    <input name="assess_role" maxlength="80" placeholder="e.g. SOC Analyst, Penetration Tester"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Time Limit (minutes)</label>
                    <input name="assess_time" type="number" min="5" max="180" value="30"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Instructions</label>
                    <input name="assess_instructions" maxlength="300" placeholder="Optional instructions for applicants…"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
            </div>

            <!-- Challenges builder -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-gray-800">Challenges <span class="text-red-500">*</span></h3>
                    <button type="button" id="addChallengeBtn"
                            class="bg-blue-100 hover:bg-blue-200 text-blue-900 text-xs font-bold px-4 py-2 rounded-xl transition">
                        + Add Challenge
                    </button>
                </div>
                <div id="challengesList" class="space-y-4">
                    <!-- Injected by JS -->
                </div>
                <p id="noChallengesMsg" class="text-gray-400 text-sm py-4 text-center border-2 border-dashed border-gray-200 rounded-xl">
                    Click "Add Challenge" to build your assessment.
                </p>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="submit" class="flex-1 bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 rounded-xl transition">
                    Create Assessment
                </button>
                <a href="?tab=assessments" class="px-5 py-3 rounded-xl border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition text-sm flex items-center">Cancel</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>
</div>

<!-- Send Assessment Modal -->
<div id="sendAssessModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <h3 class="font-bold text-gray-900">Send Assessment</h3>
            <button onclick="closeSendModal()" class="text-gray-500 hover:text-gray-700 text-xl">&times;</button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="send_assessment_msg">
            <input type="hidden" id="sendAssessId" name="assess_id" value="">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Assessment</label>
                <p id="sendAssessTitle" class="text-blue-900 font-bold text-sm"></p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Recipient Email <span class="text-red-500">*</span></label>
                <input type="email" name="recipient_email" id="sendAssessEmail" required
                       class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm" placeholder="applicant@email.com">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Personal Message (optional)</label>
                <textarea name="custom_message" rows="3"
                          class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm resize-none"
                          placeholder="Hi [Name], we'd like you to complete this assessment…"></textarea>
            </div>
            <button type="submit" class="w-full bg-purple-700 hover:bg-purple-800 text-white font-bold py-3 rounded-xl transition">
                Send via Messages
            </button>
        </form>
    </div>
</div>

<script>
// ── Send Assessment Modal ─────────────────────────────────────────────────
const sendModal = document.getElementById('sendAssessModal');
const sendAssessId    = document.getElementById('sendAssessId');
const sendAssessTitle = document.getElementById('sendAssessTitle');
const sendAssessEmail = document.getElementById('sendAssessEmail');

<?php
// Build an assessment ID→title map for JS
$assessMap = [];
foreach ($myAssessments as $a) { $assessMap[$a['id']] = $a['title']; }
?>
const assessTitles = <?php echo json_encode($assessMap); ?>;

document.addEventListener('click', e => {
    const btn = e.target.closest('.send-assess-btn');
    if (!btn) return;
    const id    = btn.dataset.assessId  || '';
    const title = btn.dataset.assessTitle || (id ? (assessTitles[id] || '') : '');
    const email = btn.dataset.email || '';
    const name  = btn.dataset.name  || '';

    // If no assess id given (coming from applicant row), pick first available
    const firstId = id || Object.keys(assessTitles)[0] || '';
    sendAssessId.value    = firstId;
    sendAssessTitle.textContent = title || (firstId ? (assessTitles[firstId] || 'Assessment') : 'No assessments yet — create one first.');
    sendAssessEmail.value = email;

    // If email is provided (from applicant row), make it readonly
    if (email) {
        sendAssessEmail.readOnly = true;
        sendAssessEmail.classList.add('bg-gray-100', 'cursor-not-allowed');
    } else {
        sendAssessEmail.readOnly = false;
        sendAssessEmail.classList.remove('bg-gray-100', 'cursor-not-allowed');
    }

    <?php if (empty($myAssessments)): ?>
    alert('Create an assessment first in the "Assessments" tab.');
    return;
    <?php endif; ?>

    sendModal.classList.remove('hidden');
    sendModal.classList.add('flex');
});

function closeSendModal() {
    sendModal.classList.add('hidden');
    sendModal.classList.remove('flex');
}
sendModal.addEventListener('click', e => { if (e.target === sendModal) closeSendModal(); });

// ── Challenge Builder ─────────────────────────────────────────────────────
let challengeCount = 0;
const challengesList = document.getElementById('challengesList');
const noChallengesMsg = document.getElementById('noChallengesMsg');
const addBtn = document.getElementById('addChallengeBtn');

if (addBtn) {
    addBtn.addEventListener('click', addChallenge);
}

function addChallenge() {
    noChallengesMsg?.classList.add('hidden');
    const i = challengeCount++;
    const card = document.createElement('div');
    card.className = 'bg-gray-50 border border-gray-200 rounded-2xl p-5 relative';
    card.innerHTML = `
        <button type="button" onclick="this.closest('.bg-gray-50').remove(); checkEmpty();"
                class="absolute top-3 right-3 text-red-400 hover:text-red-600 font-bold text-lg leading-none">&times;</button>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Challenge Title *</label>
                <input name="challenges[${i}][title]" required maxlength="120" placeholder="e.g. Subnet Calculation"
                       class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Type</label>
                    <select name="challenges[${i}][type]" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 challenge-type-sel">
                        <option value="flag">Flag (exact answer)</option>
                        <option value="code">Code (open-ended)</option>
                        <option value="short">Short Answer</option>
                        <option value="multiple-choice">Multiple Choice</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Points</label>
                    <input name="challenges[${i}][points]" type="number" min="10" max="500" value="100"
                           class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Challenge Description *</label>
            <textarea name="challenges[${i}][body]" rows="3" required placeholder="Describe the challenge…"
                      class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
        </div>
        
        <!-- Multiple Choice Options Panel -->
        <div id="mc-options-${i}" class="mb-4 hidden">
            <label class="block text-xs font-semibold text-gray-600 mb-2">Options (A, B, C, D) *</label>
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-blue-900 text-white flex items-center justify-center text-xs font-bold">A</span>
                    <input name="challenges[${i}][options][A]" required placeholder="Option A" class="flex-1 px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-blue-900 text-white flex items-center justify-center text-xs font-bold">B</span>
                    <input name="challenges[${i}][options][B]" required placeholder="Option B" class="flex-1 px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-blue-900 text-white flex items-center justify-center text-xs font-bold">C</span>
                    <input name="challenges[${i}][options][C]" required placeholder="Option C" class="flex-1 px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-blue-900 text-white flex items-center justify-center text-xs font-bold">D</span>
                    <input name="challenges[${i}][options][D]" required placeholder="Option D" class="flex-1 px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="mt-3">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Correct Answer (A, B, C, or D) *</label>
                <select name="challenges[${i}][correct_option]" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div class="flag-field-wrap">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Correct Flag / Answer</label>
                <input name="challenges[${i}][correct_flag]" placeholder="FLAG{answer} or exact answer"
                       class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-400 mt-1">Leave empty for code/short answers (manual review).</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Hint (optional)</label>
                <input name="challenges[${i}][hint]" maxlength="200" placeholder="Optional hint for applicants"
                       class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Attachments (optional)</label>
            <input type="file" name="challenges[${i}][attachments][]" multiple data-challenge-index="${i}"
                   class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 attachment-input">
            <div id="attachments-container-${i}" class="mt-3 space-y-3"></div>
            <p class="text-xs text-gray-400 mt-1">Add files related to this challenge (e.g., PCAPs, data files).</p>
        </div>
    `;

    challengesList.appendChild(card);

    // Show/hide flag field and multiple choice options based on type
    const sel = card.querySelector('.challenge-type-sel');
    const flagWrap = card.querySelector('.flag-field-wrap');
    const mcOptions = card.querySelector(`#mc-options-${i}`);
    
    function updateTypeFields() {
        if (sel.value === 'multiple-choice') {
            flagWrap.style.opacity = '0.4';
            mcOptions.classList.remove('hidden');
        } else {
            flagWrap.style.opacity = (sel.value === 'flag') ? '1' : '0.4';
            mcOptions.classList.add('hidden');
        }
    }
    
    updateTypeFields();
    sel.addEventListener('change', updateTypeFields);
}

// Define file icon map once
const iconMap = {
    pdf: '📕',
    doc: '📄', docx: '📄', odt: '📄', rtf: '📄', txt: '📄',
    png: '🖼️', jpg: '🖼️', jpeg: '🖼️', gif: '🖼️', svg: '🖼️', ico: '🖼️',
    ppt: '📊', pptx: '📊', xls: '📊', xlsx: '📊', csv: '📊',
    zip: '📦', rar: '📦', '7z': '📦', tar: '📦', gz: '📦', xz: '📦', bz2: '📦',
    mp3: '🎵', wav: '🎵', ogg: '🎵', m4a: '🎵',
    mp4: '🎬', webm: '🎬', avi: '🎬', mov: '🎬',
    pcap: '🔍', pcapng: '🔍',
    pka: '🔬', pkt: '🔬', // Packet Tracer
    md: '📝',
    html: '🌐',
    css: '🎨', scss: '🎨', less: '🎨',
    js: '💻', ts: '💻',
    json: '📋', xml: '📋', yaml: '📋', yml: '📋',
    php: '🐘',
    py: '🐍', rb: '💎',
    java: '☕', class: '☕', jar: '☕',
    cpp: '⚙️', c: '⚙️', h: '⚙️',
    cs: '🔷',
    go: '🐹', rs: '🦀', kt: '📱'
};

function getFileIcon(fileName) {
    const ext = fileName.split('.').pop().toLowerCase();
    return iconMap[ext] || '📄';
}

// Handle attachment file selection to add note inputs
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('attachment-input')) {
        const challengeIndex = e.target.dataset.challengeIndex;
        const container = document.getElementById(`attachments-container-${challengeIndex}`);
        if (!container) return;
        
        // Clear previous content
        container.innerHTML = '';
        
        const files = e.target.files;
        for (let fileIdx = 0; fileIdx < files.length; fileIdx++) {
            const file = files[fileIdx];
            const wrapper = document.createElement('div');
            wrapper.className = 'bg-white p-4 rounded-xl border border-gray-200';
            
            wrapper.innerHTML = `
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-2xl">${getFileIcon(file.name)}</span>
                    <div>
                        <span class="text-sm font-medium text-gray-700">${file.name}</span>
                        <span class="text-xs text-gray-400">(${(file.size / 1024).toFixed(1)} KB)</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Note (optional)</label>
                    <input type="text" name="challenges[${challengeIndex}][attachment_notes][]" 
                           placeholder="e.g., Capture file for analysis"
                           class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            `;
            
            container.appendChild(wrapper);
        }
    }
});

function checkEmpty() {
    if (challengesList.children.length === 0) {
        noChallengesMsg?.classList.remove('hidden');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
