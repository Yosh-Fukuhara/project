<?php
require_once 'includes/bootstrap.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Messages - CyberSphere';
$currentPage = 'messages';

function cs_format_time($ts) {
    // Keep UI consistent with the existing design (e.g., "10:35 AM")
    return date('g:i A', $ts);
}

function cs_safe_filename($name) {
    $name = preg_replace('/[^\w\-. ]+/u', '_', $name);
    $name = trim($name);
    return $name === '' ? 'file' : $name;
}

function cs_render_attachment($att, $isMine) {
    $url = htmlspecialchars($att['url'] ?? '');
    $name = htmlspecialchars($att['name'] ?? 'Attachment');
    $kind = $att['kind'] ?? 'file';

    if ($kind === 'image') {
        return '<img src="'.$url.'" alt="'.$name.'" class="mt-2 rounded-xl max-h-56 object-cover border border-white/20" loading="lazy">';
    }
    if ($kind === 'video') {
        return '<video src="'.$url.'" controls class="mt-2 rounded-xl max-h-64 w-full border border-white/20"></video>';
    }

    $linkClass = $isMine ? 'text-blue-200 hover:text-white underline' : 'text-blue-700 hover:text-blue-900 underline';
    $size = isset($att['size']) ? (int)$att['size'] : null;
    $sizeLabel = $size ? ' • '.number_format($size / 1024, 0).' KB' : '';
    return '<div class="mt-2 text-sm"><a class="'.$linkClass.'" href="'.$url.'" target="_blank" rel="noopener">'.$name.'</a><span class="'.($isMine ? 'text-blue-200' : 'text-gray-500').'">'.$sizeLabel.'</span></div>';
}

function cs_render_message_html($msg, $userEmail) {
    $isMine = (($msg['from'] ?? '') === $userEmail);
    $wrapperAlign = $isMine ? 'justify-end' : 'justify-start';
    $bubbleClass = $isMine ? 'bg-blue-900 text-white' : 'bg-gray-100 text-gray-800';
    $metaClass = $isMine ? 'text-blue-200' : 'text-gray-400';

    $id = htmlspecialchars($msg['id'] ?? '');
    $text = htmlspecialchars($msg['text'] ?? '');
    $time = htmlspecialchars($msg['time'] ?? '');
    $pinned = !empty($msg['pinned']);
    $edited = !empty($msg['edited']);

    $actions = '';
    // Pin is available for any message; edit/delete only for your own messages
    $actions .= '<button type="button" class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50" data-msg-action="toggle_pin" data-message-id="'.$id.'">'.($pinned ? 'Unpin' : 'Pin').'</button>';
    if ($isMine) {
        $actions .= '<button type="button" class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50" data-msg-action="edit" data-message-id="'.$id.'">Edit</button>';
        $actions .= '<button type="button" class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50 text-red-600" data-msg-action="delete" data-message-id="'.$id.'">Delete</button>';
    }

    $pinBadge = $pinned
        ? '<div class="flex items-center gap-1 text-xs font-semibold '.($isMine ? 'text-blue-200' : 'text-gray-500').' mb-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7l-1.5-4h-5L8 7v5l-2 2v1h12v-1l-2-2V7zM12 15v6"></path></svg><span>Pinned</span></div>'
        : '';

    $attachmentsHtml = '';
    if (!empty($msg['attachments']) && is_array($msg['attachments'])) {
        foreach ($msg['attachments'] as $att) {
            $attachmentsHtml .= cs_render_attachment($att, $isMine);
        }
    }

    $editedLabel = $edited ? ' <span class="msgEdited">(edited)</span>' : '';

    return '
        <div class="flex '.$wrapperAlign.'" data-message-id="'.$id.'">
            <div class="group relative max-w-[70%] rounded-2xl px-4 py-2 '.$bubbleClass.'">
                <button type="button" class="msgMenuBtn absolute -top-2 '.($isMine ? '-left-2' : '-right-2').' opacity-0 group-hover:opacity-100 transition bg-white/90 hover:bg-white text-gray-700 border border-gray-200 shadow-sm rounded-full p-1" aria-label="Message actions" data-message-id="'.$id.'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6h.01M12 12h.01M12 18h.01"></path>
                    </svg>
                </button>
                <div class="msgMenu absolute top-6 '.($isMine ? 'left-0' : 'right-0').' w-36 bg-white text-gray-800 rounded-xl shadow-lg border border-gray-200 overflow-hidden hidden z-10" data-message-id="'.$id.'">
                    '.$actions.'
                </div>
                '.$pinBadge.'
                <div class="msgBody">
                    <p class="msgText text-sm whitespace-pre-wrap break-words">'.$text.'</p>
                    '.$attachmentsHtml.'
                </div>
                <p class="msgMeta text-xs '.$metaClass.' mt-1">'.$time.$editedLabel.'</p>
            </div>
        </div>
    ';
}

function cs_render_pinned_bar_html($conv) {
    $msgs = $conv['messages'] ?? [];
    if (!is_array($msgs)) $msgs = [];

    $pinned = [];
    foreach ($msgs as $m) {
        if (!empty($m['pinned'])) $pinned[] = $m;
    }
    if (count($pinned) === 0) return '';

    // Show up to 3 pinned items, newest first (Messenger-like quick access)
    usort($pinned, function ($a, $b) {
        return (int)($b['ts'] ?? 0) <=> (int)($a['ts'] ?? 0);
    });
    $pinned = array_slice($pinned, 0, 3);

    $items = '';
    foreach ($pinned as $m) {
        $id = htmlspecialchars($m['id'] ?? '');
        $text = trim((string)($m['text'] ?? ''));
        if ($text === '') {
            $atts = $m['attachments'] ?? [];
            if (is_array($atts) && !empty($atts[0]['name'])) $text = '[Attachment] '.$atts[0]['name'];
            else $text = '[Pinned message]';
        }
        $snippet = htmlspecialchars(mb_strimwidth($text, 0, 50, '…', 'UTF-8'));
        $items .= '<button type="button" class="pinnedJump inline-flex items-center max-w-[220px] truncate px-3 py-1.5 rounded-full bg-white border border-gray-200 text-xs text-gray-700 hover:bg-gray-50" data-target-message="'.$id.'">'.$snippet.'</button>';
    }

    return '
        <div class="px-4 py-2 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1 text-xs font-semibold text-gray-700 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7l-1.5-4h-5L8 7v5l-2 2v1h12v-1l-2-2V7zM12 15v6"></path>
                    </svg>
                    <span>Pinned</span>
                </div>
                <div class="flex flex-wrap gap-2">'.$items.'</div>
            </div>
        </div>
    ';
}

function cs_render_messages_area_html($conv, $userEmail) {
    $msgs = $conv['messages'] ?? [];
    if (!is_array($msgs)) $msgs = [];

    // Keep normal chronological order (Messenger-style: pin does not reorder the thread)
    usort($msgs, function ($a, $b) {
        $ats = (int)($a['ts'] ?? 0);
        $bts = (int)($b['ts'] ?? 0);
        return $ats <=> $bts;
    });

    $html = '';
    foreach ($msgs as $msg) {
        $html .= cs_render_message_html($msg, $userEmail);
    }
    return $html;
}

function cs_find_conversation_index($convId) {
    if (!isset($_SESSION['messages']) || !is_array($_SESSION['messages'])) return null;
    foreach ($_SESSION['messages'] as $i => $c) {
        if (($c['id'] ?? '') === $convId) return $i;
    }
    return null;
}

function cs_find_message_index($convIndex, $messageId) {
    if (!isset($_SESSION['messages'][$convIndex]['messages']) || !is_array($_SESSION['messages'][$convIndex]['messages'])) return null;
    foreach ($_SESSION['messages'][$convIndex]['messages'] as $i => $m) {
        if (($m['id'] ?? '') === $messageId) return $i;
    }
    return null;
}

function cs_handle_attachments_upload($files) {
    $result = [];
    if (empty($files) || empty($files['name']) || !is_array($files['name'])) return $result;

    $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0777, true);
    }

    $allowedExt = [
        'jpg','jpeg','png','gif','webp',
        'mp4','webm','mov',
        'pdf','txt',
        'doc','docx','xls','xlsx','ppt','pptx',
        'zip','rar'
    ];
    $maxSize = 25 * 1024 * 1024; // 25MB per file
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
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
    ];

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $err = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) continue;

        $tmp = $files['tmp_name'][$i] ?? '';
        $origName = $files['name'][$i] ?? 'file';
        $size = (int)($files['size'][$i] ?? 0);
        if (!$tmp || $size <= 0 || $size > $maxSize) continue;

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) continue;

        $mime = $mimeByExt[$ext] ?? 'application/octet-stream';
        if (class_exists('finfo')) {
            try {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detected = $finfo->file($tmp) ?: $mime;
                $mime = $detected;
            } catch (Throwable $e) {
                // ignore and keep extension-derived mime
            }
        }
        $kind = 'file';
        if (str_starts_with($mime, 'image/')) $kind = 'image';
        else if (str_starts_with($mime, 'video/')) $kind = 'video';

        $safeOrig = cs_safe_filename($origName);
        $newName = 'msg_' . uniqid('', true) . '.' . $ext;
        $destPath = $uploadsDir . DIRECTORY_SEPARATOR . $newName;
        if (!move_uploaded_file($tmp, $destPath)) continue;

        $result[] = [
            'url' => 'uploads/' . $newName,
            'name' => $safeOrig,
            'mime' => $mime,
            'size' => $size,
            'kind' => $kind,
        ];
    }

    return $result;
}

if (!isset($_SESSION['messages']) || !is_array($_SESSION['messages'])) {
    $_SESSION['messages'] = [
        [
            'id' => 'conv1',
            'otherUser' => ['email' => 'hr@netsentinel.com', 'username' => 'HR - NetSentinel', 'avatar' => '🛡️'],
            'messages' => [
                ['id' => 'msg_seed_1', 'from' => 'hr@netsentinel.com', 'text' => 'Hi there! We saw your application for the Penetration Tester position.', 'ts' => time() - 300, 'time' => cs_format_time(time() - 300), 'attachments' => [], 'pinned' => false, 'edited' => false],
                ['id' => 'msg_seed_2', 'from' => $_SESSION['user']['email'], 'text' => 'Hi! Thank you for reaching out. I\'m very interested in the role.', 'ts' => time() - 180, 'time' => cs_format_time(time() - 180), 'attachments' => [], 'pinned' => false, 'edited' => false],
                ['id' => 'msg_seed_3', 'from' => 'hr@netsentinel.com', 'text' => 'Great! We\'d like to schedule an interview. Are you available this week?', 'ts' => time() - 60, 'time' => cs_format_time(time() - 60), 'attachments' => [], 'pinned' => false, 'edited' => false],
            ],
            'unread' => 1
        ],
        [
            'id' => 'conv2',
            'otherUser' => ['email' => 'securebank@example.com', 'username' => 'SecureBank Hiring', 'avatar' => '🏦'],
            'messages' => [
                ['id' => 'msg_seed_4', 'from' => 'securebank@example.com', 'text' => 'Hello! We have a question about your GRC Specialist application.', 'ts' => time() - 86400, 'time' => 'Yesterday', 'attachments' => [], 'pinned' => false, 'edited' => false],
            ],
            'unread' => 1
        ]
    ];
}

// Ensure message schema exists for sessions created before these features
if (isset($_SESSION['messages']) && is_array($_SESSION['messages'])) {
    foreach ($_SESSION['messages'] as &$conv) {
        if (empty($conv['messages']) || !is_array($conv['messages'])) continue;
        $base = time() - (count($conv['messages']) * 60);
        foreach ($conv['messages'] as $idx => &$m) {
            if (!isset($m['id'])) $m['id'] = 'msg_' . uniqid('', true);
            if (!isset($m['ts'])) $m['ts'] = $base + ($idx * 60);
            if (!isset($m['time'])) $m['time'] = cs_format_time((int)$m['ts']);
            if (!isset($m['attachments']) || !is_array($m['attachments'])) $m['attachments'] = [];
            if (!isset($m['pinned'])) $m['pinned'] = false;
            if (!isset($m['edited'])) $m['edited'] = false;
        }
        unset($m);
    }
    unset($conv);
}

// ── AJAX actions (send/edit/delete/pin + fetch HTML) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    $convId = $_POST['conv'] ?? '';
    $userEmail = $_SESSION['user']['email'];
    $convIndex = $convId ? cs_find_conversation_index($convId) : null;

    if ($convId && $convIndex === null) {
        echo json_encode(['ok' => false, 'error' => 'Conversation not found.']);
        exit;
    }

    if ($action === 'fetch_messages_html') {
        $html = cs_render_messages_area_html($_SESSION['messages'][$convIndex], $userEmail);
        $pinnedBar = cs_render_pinned_bar_html($_SESSION['messages'][$convIndex]);
        echo json_encode(['ok' => true, 'messages_html' => $html, 'pinned_bar_html' => $pinnedBar]);
        exit;
    }

    if ($action === 'send_message') {
        $text = trim($_POST['text'] ?? '');
        $attachments = cs_handle_attachments_upload($_FILES['attachments'] ?? null);

        if ($text === '' && empty($attachments)) {
            echo json_encode(['ok' => false, 'error' => 'Message is empty.']);
            exit;
        }

        $ts = time();
        $msg = [
            'id' => 'msg_' . uniqid('', true),
            'from' => $userEmail,
            'text' => $text,
            'ts' => $ts,
            'time' => cs_format_time($ts),
            'attachments' => $attachments,
            'pinned' => false,
            'edited' => false,
        ];

        $_SESSION['messages'][$convIndex]['messages'][] = $msg;

        $previewText = $text !== '' ? $text : '[Attachment]';
        echo json_encode([
            'ok' => true,
            'message_html' => cs_render_message_html($msg, $userEmail),
            'preview_text' => $previewText,
            'preview_time' => cs_format_time($ts),
        ]);
        exit;
    }

    $messageId = $_POST['message_id'] ?? '';
    $msgIndex = ($convIndex !== null && $messageId) ? cs_find_message_index($convIndex, $messageId) : null;
    if ($msgIndex === null) {
        echo json_encode(['ok' => false, 'error' => 'Message not found.']);
        exit;
    }

    if ($action === 'edit_message') {
        if (($_SESSION['messages'][$convIndex]['messages'][$msgIndex]['from'] ?? '') !== $userEmail) {
            echo json_encode(['ok' => false, 'error' => 'You can only edit your own messages.']);
            exit;
        }

        $newText = trim($_POST['text'] ?? '');
        $hasAtt = !empty($_SESSION['messages'][$convIndex]['messages'][$msgIndex]['attachments']);
        if ($newText === '' && !$hasAtt) {
            echo json_encode(['ok' => false, 'error' => 'Message cannot be empty.']);
            exit;
        }

        $_SESSION['messages'][$convIndex]['messages'][$msgIndex]['text'] = $newText;
        $_SESSION['messages'][$convIndex]['messages'][$msgIndex]['edited'] = true;

        $msg = $_SESSION['messages'][$convIndex]['messages'][$msgIndex];
        echo json_encode([
            'ok' => true,
            'message_html' => cs_render_message_html($msg, $userEmail),
        ]);
        exit;
    }

    if ($action === 'delete_message') {
        if (($_SESSION['messages'][$convIndex]['messages'][$msgIndex]['from'] ?? '') !== $userEmail) {
            echo json_encode(['ok' => false, 'error' => 'You can only delete your own messages.']);
            exit;
        }
        array_splice($_SESSION['messages'][$convIndex]['messages'], $msgIndex, 1);
        $html = cs_render_messages_area_html($_SESSION['messages'][$convIndex], $userEmail);
        $pinnedBar = cs_render_pinned_bar_html($_SESSION['messages'][$convIndex]);
        echo json_encode(['ok' => true, 'messages_html' => $html, 'pinned_bar_html' => $pinnedBar]);
        exit;
    }

    if ($action === 'toggle_pin') {
        $cur = !empty($_SESSION['messages'][$convIndex]['messages'][$msgIndex]['pinned']);
        $_SESSION['messages'][$convIndex]['messages'][$msgIndex]['pinned'] = !$cur;
        $html = cs_render_messages_area_html($_SESSION['messages'][$convIndex], $userEmail);
        $pinnedBar = cs_render_pinned_bar_html($_SESSION['messages'][$convIndex]);
        echo json_encode(['ok' => true, 'messages_html' => $html, 'pinned_bar_html' => $pinnedBar]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

$activeConversation = $_GET['conv'] ?? 'conv1';
$currentConv = null;
// Mark conversation as read when opened
foreach ($_SESSION['messages'] as &$conv) {
    if ($conv['id'] === $activeConversation) {
        $conv['unread'] = 0;
        $currentConv = $conv;
        break;
    }
}
unset($conv);
if (!$currentConv) $currentConv = $_SESSION['messages'][0];

// Count total unread across all conversations
$totalUnread = 0;
foreach ($_SESSION['messages'] as $conv) {
    $totalUnread += (int)($conv['unread'] ?? 0);
}
?>
<?php include 'includes/header.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-3 h-[600px]">
            <!-- Conversations List -->
            <div class="border-r border-gray-200 flex flex-col min-h-0">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-xl font-bold text-gray-900">Messages</h2>
                    <?php if ($totalUnread > 0): ?>
                    <span id="msgSidebarUnread" class="bg-blue-900 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                        <?php echo $totalUnread; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="flex-1 overflow-y-auto min-h-0">
                    <?php foreach ($_SESSION['messages'] as $conv): ?>
                    <a href="messages.php?conv=<?php echo urlencode($conv['id']); ?>" data-conv-id="<?php echo htmlspecialchars($conv['id']); ?>" class="flex items-center gap-3 p-4 hover:bg-gray-50 cursor-pointer transition <?php echo $conv['id'] === $activeConversation ? 'bg-blue-50' : ''; ?>">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-2xl flex-shrink-0">
                            <?php echo htmlspecialchars($conv['otherUser']['avatar']); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($conv['otherUser']['username']); ?></span>
                                <span class="text-xs text-gray-400" data-conv-time><?php echo htmlspecialchars($conv['messages'][count($conv['messages'])-1]['time']); ?></span>
                            </div>
                            <p class="text-sm text-gray-500 truncate mt-1" data-conv-preview><?php echo htmlspecialchars($conv['messages'][count($conv['messages'])-1]['text']); ?></p>
                        </div>
                        <?php if (($conv['unread'] ?? 0) > 0): ?>
                        <span class="conv-unread-badge bg-blue-900 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0"><?php echo $conv['unread']; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="col-span-2 flex flex-col min-h-0">
                <!-- Chat Header -->
                <div class="p-4 border-b border-gray-200 flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-xl">
                        <?php echo htmlspecialchars($currentConv['otherUser']['avatar']); ?>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($currentConv['otherUser']['username']); ?></h3>
                        <p class="text-xs text-green-600">Online</p>
                    </div>
                </div>

                <!-- Pinned Bar -->
                <div id="pinnedBar" class="flex-shrink-0">
                    <?php echo cs_render_pinned_bar_html($currentConv); ?>
                </div>

                <!-- Messages -->
                <div id="messagesArea" class="flex-1 overflow-y-auto min-h-0 p-4 space-y-4">
                    <?php echo cs_render_messages_area_html($currentConv, $_SESSION['user']['email']); ?>
                </div>

                <!-- Input Area -->
                <div class="p-4 border-t border-gray-200">
                    <form id="messageForm" class="space-y-2" enctype="multipart/form-data">
                        <input type="hidden" name="conv" id="convInput" value="<?php echo htmlspecialchars($currentConv['id']); ?>">
                        <input type="file" id="attachmentInput" name="attachments[]" multiple class="hidden"
                               accept="image/*,video/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar">
                        <div id="attachmentPreview" class="flex flex-wrap gap-2"></div>
                        <div class="flex items-center gap-3">
                            <button id="attachBtn" type="button" class="text-gray-400 hover:text-gray-600" aria-label="Attach files">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                            </button>
                            <input type="text" name="text" id="messageInput" placeholder="Type a message..." class="flex-1 px-4 py-2 rounded-full border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button id="sendBtn" type="submit" class="bg-blue-900 hover:bg-blue-800 text-white p-2 rounded-full transition" aria-label="Send message">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                            </button>
                        </div>
                        <p id="msgError" class="text-xs text-red-600 hidden"></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const activeConvId = <?php echo json_encode($currentConv['id']); ?>;
    const messageInput = document.getElementById('messageInput');
    const messagesArea = document.getElementById('messagesArea');
    const pinnedBar = document.getElementById('pinnedBar');
    const form = document.getElementById('messageForm');
    const attachBtn = document.getElementById('attachBtn');
    const attachmentInput = document.getElementById('attachmentInput');
    const attachmentPreview = document.getElementById('attachmentPreview');
    const msgError = document.getElementById('msgError');

    let dt = new DataTransfer();

    function showError(text) {
        if (!msgError) return;
        if (!text) {
            msgError.classList.add('hidden');
            msgError.textContent = '';
            return;
        }
        msgError.textContent = text;
        msgError.classList.remove('hidden');
    }

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) return '';
        const kb = bytes / 1024;
        if (kb < 1024) return `${Math.round(kb)} KB`;
        return `${(kb/1024).toFixed(1)} MB`;
    }

    function renderAttachmentChips() {
        attachmentPreview.innerHTML = '';
        const files = Array.from(attachmentInput.files || []);
        files.forEach((f, idx) => {
            const chip = document.createElement('div');
            chip.className = 'flex items-center gap-2 bg-gray-100 border border-gray-200 rounded-full px-3 py-1 text-xs';
            const isImg = f.type && f.type.startsWith('image/');
            if (isImg) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                img.className = 'w-6 h-6 rounded-full object-cover';
                chip.appendChild(img);
            }
            const label = document.createElement('span');
            label.className = 'text-gray-700 max-w-[180px] truncate';
            label.textContent = `${f.name}${f.size ? ' • ' + formatBytes(f.size) : ''}`;
            chip.appendChild(label);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'text-gray-500 hover:text-gray-900';
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                const next = new DataTransfer();
                files.forEach((ff, j) => {
                    if (j !== idx) next.items.add(ff);
                });
                dt = next;
                attachmentInput.files = dt.files;
                renderAttachmentChips();
            });
            chip.appendChild(remove);

            attachmentPreview.appendChild(chip);
        });
    }

    function closeAllMenus() {
        document.querySelectorAll('.msgMenu').forEach(m => m.classList.add('hidden'));
    }

    async function postFormData(fd) {
        const res = await fetch('', { method: 'POST', body: fd });
        return res.json();
    }

    async function postUrlEncoded(params) {
        const res = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params).toString()
        });
        return res.json();
    }

    if (attachBtn && attachmentInput) {
        attachBtn.addEventListener('click', () => attachmentInput.click());
    }
    if (attachmentInput) {
        attachmentInput.addEventListener('change', () => {
            // Keep a mutable DataTransfer so we can remove items
            dt = new DataTransfer();
            Array.from(attachmentInput.files || []).forEach(f => dt.items.add(f));
            attachmentInput.files = dt.files;
            renderAttachmentChips();
        });
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            showError('');

            const text = (messageInput.value || '').trim();
            const hasFiles = (attachmentInput.files && attachmentInput.files.length > 0);
            if (!text && !hasFiles) return;

            const fd = new FormData(form);
            fd.append('action', 'send_message');
            try {
                const data = await postFormData(fd);
                if (!data.ok) {
                    showError(data.error || 'Failed to send message.');
                    return;
                }
                const temp = document.createElement('div');
                temp.innerHTML = data.message_html;
                messagesArea.appendChild(temp.firstElementChild);

                messageInput.value = '';
                dt = new DataTransfer();
                attachmentInput.value = '';
                attachmentInput.files = dt.files;
                renderAttachmentChips();

                // Update conversation preview on the left (active conv)
                const convLink = document.querySelector(`[data-conv-id="${CSS.escape(activeConvId)}"]`);
                if (convLink) {
                    const timeEl = convLink.querySelector('[data-conv-time]');
                    const textEl = convLink.querySelector('[data-conv-preview]');
                    if (timeEl && data.preview_time) timeEl.textContent = data.preview_time;
                    if (textEl && data.preview_text) textEl.textContent = data.preview_text;
                    // Clear unread badge for current conv since we just sent a message
                    const convBadge = document.querySelector(`a[data-conv-id="${activeConvId}"] .conv-unread-badge`);
                    if (convBadge) { convBadge.remove(); updateSidebarUnreadTotal(); }
                }

                messagesArea.scrollTop = messagesArea.scrollHeight;
            } catch (err) {
                showError('Failed to send message.');
            }
        });
    }

    // Enter to send
    if (messageInput) {
        messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                form?.requestSubmit();
            }
        });
    }

    // Message menu toggle + actions (event delegation)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.msgMenuBtn');
        if (btn) {
            const id = btn.getAttribute('data-message-id');
            const menu = document.querySelector(`.msgMenu[data-message-id="${CSS.escape(id)}"]`);
            if (!menu) return;
            const isHidden = menu.classList.contains('hidden');
            closeAllMenus();
            if (isHidden) menu.classList.remove('hidden');
            e.stopPropagation();
            return;
        }
        // Close menus when clicking elsewhere
        if (!e.target.closest('.msgMenu')) closeAllMenus();
    });

    messagesArea.addEventListener('click', async (e) => {
        const actionBtn = e.target.closest('[data-msg-action]');
        if (!actionBtn) return;
        const action = actionBtn.getAttribute('data-msg-action');
        const messageId = actionBtn.getAttribute('data-message-id');
        if (!messageId) return;
        closeAllMenus();

        if (action === 'toggle_pin') {
            const data = await postUrlEncoded({ action: 'toggle_pin', conv: activeConvId, message_id: messageId });
            if (data.ok && data.messages_html != null) {
                messagesArea.innerHTML = data.messages_html;
                if (pinnedBar && data.pinned_bar_html != null) pinnedBar.innerHTML = data.pinned_bar_html;
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
            return;
        }

        if (action === 'delete') {
            if (!confirm('Delete this message?')) return;
            const data = await postUrlEncoded({ action: 'delete_message', conv: activeConvId, message_id: messageId });
            if (data.ok && data.messages_html != null) {
                messagesArea.innerHTML = data.messages_html;
                if (pinnedBar && data.pinned_bar_html != null) pinnedBar.innerHTML = data.pinned_bar_html;
                messagesArea.scrollTop = messagesArea.scrollHeight;
            } else if (data.error) {
                alert(data.error);
            }
            return;
        }

        if (action === 'edit') {
            const wrapper = messagesArea.querySelector(`[data-message-id="${CSS.escape(messageId)}"]`);
            if (!wrapper) return;
            const textEl = wrapper.querySelector('.msgText');
            if (!textEl) return;

            const original = textEl.textContent;
            const input = document.createElement('textarea');
            input.className = 'w-full text-sm rounded-xl p-2 border border-gray-300 text-gray-900';
            input.rows = 3;
            input.value = original;

            const actions = document.createElement('div');
            actions.className = 'flex items-center gap-2 mt-2';
            actions.innerHTML = `
                <button type="button" class="px-3 py-1 rounded-lg bg-blue-900 text-white text-xs" data-edit-save>Save</button>
                <button type="button" class="px-3 py-1 rounded-lg bg-gray-200 text-gray-800 text-xs" data-edit-cancel>Cancel</button>
            `;

            const body = wrapper.querySelector('.msgBody');
            body.innerHTML = '';
            body.appendChild(input);
            body.appendChild(actions);
            input.focus();

            actions.querySelector('[data-edit-cancel]').addEventListener('click', () => {
                // Re-fetch the single message HTML by reloading the entire messages HTML (simple + consistent)
                postUrlEncoded({ action: 'fetch_messages_html', conv: activeConvId }).then(d => {
                    if (d.ok && d.messages_html != null) {
                        messagesArea.innerHTML = d.messages_html;
                        messagesArea.scrollTop = messagesArea.scrollHeight;
                    }
                });
            });

            actions.querySelector('[data-edit-save]').addEventListener('click', async () => {
                const fd = new FormData();
                fd.append('action', 'edit_message');
                fd.append('conv', activeConvId);
                fd.append('message_id', messageId);
                fd.append('text', input.value.trim());
                const data = await postFormData(fd);
                if (!data.ok) {
                    alert(data.error || 'Failed to edit message.');
                    return;
                }
                const temp = document.createElement('div');
                temp.innerHTML = data.message_html;
                wrapper.replaceWith(temp.firstElementChild);
            });
        }
    });

    // Jump to pinned message (Messenger-like)
    document.addEventListener('click', (e) => {
        const jump = e.target.closest('.pinnedJump');
        if (!jump) return;
        const targetId = jump.getAttribute('data-target-message');
        if (!targetId) return;
        const el = messagesArea.querySelector(`[data-message-id="${CSS.escape(targetId)}"]`);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Brief highlight
        const bubble = el.querySelector('.group');
        if (bubble) {
            bubble.classList.add('ring-2','ring-yellow-400','ring-offset-2');
            setTimeout(() => bubble.classList.remove('ring-2','ring-yellow-400','ring-offset-2'), 1200);
        }
    });

    messagesArea.scrollTop = messagesArea.scrollHeight;
    // ── Unread badge helpers ─────────────────────────────────────────────
    function updateSidebarUnreadTotal() {
        const badges = document.querySelectorAll('.conv-unread-badge');
        const total  = Array.from(badges).reduce((s, b) => s + (parseInt(b.textContent) || 0), 0);
        let sidebarBadge = document.getElementById('msgSidebarUnread');
        if (total > 0) {
            if (!sidebarBadge) {
                sidebarBadge = document.createElement('span');
                sidebarBadge.id = 'msgSidebarUnread';
                sidebarBadge.className = 'bg-blue-900 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center';
                document.querySelector('.p-4.border-b.border-gray-200.flex')?.appendChild(sidebarBadge);
            }
            sidebarBadge.textContent = total;
        } else if (sidebarBadge) {
            sidebarBadge.remove();
        }
    }
})();
</script>

<?php include 'includes/footer.php'; ?>
