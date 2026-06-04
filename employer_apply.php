<?php
require_once 'includes/bootstrap.php';
require_once 'role_helpers.php';

$pageTitle   = 'Apply for Employer Account - CyberSphere';
$currentPage = 'employer_apply';
$errors  = [];
$success = '';

// Already employer or admin — go to dashboard
if (isset($_SESSION['user']) && in_array($_SESSION['user']['role'] ?? '', ['employer', 'admin'])) {
    header('Location: employer_dashboard.php'); exit;
}

// Check for existing application by this user's email
$existingApp = null;
if (isset($_SESSION['user'])) {
    $existingApp = cs_get_employer_application_by_email($_SESSION['user']['email']);
    // If approved, upgrade session role automatically
    if ($existingApp && $existingApp['status'] === 'approved') {
        $_SESSION['user']['role']              = 'employer';
        $_SESSION['user']['employer_verified'] = true;
        $_SESSION['user']['company_name']      = $existingApp['company_name'] ?? $_SESSION['user']['username'];
        header('Location: employer_dashboard.php'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existingApp) {
    if (!isset($_SESSION['user'])) {
        $errors[] = 'You must be signed in to apply.';
    } else {
        $companyName  = trim($_POST['company_name']  ?? '');
        $industry     = trim($_POST['industry']      ?? '');
        $companySize  = trim($_POST['company_size']  ?? '');
        $website      = trim($_POST['website']       ?? '');
        $description  = trim($_POST['description']   ?? '');
        $contactName  = trim($_POST['contact_name']  ?? '');
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $email        = $_SESSION['user']['email'];

        if (!$companyName)  $errors[] = 'Company name is required.';
        if (!$industry)     $errors[] = 'Industry is required.';
        if (!$contactName)  $errors[] = 'Contact name is required.';
        if (mb_strlen($description) < 20) $errors[] = 'Please write at least a short company description (20+ characters).';

        // Handle document uploads (up to 3)
        $documents = [];
        if (!empty($_FILES['documents']['name'][0])) {
            $allowedExts = ['pdf','doc','docx','jpg','jpeg','png'];
            $uploadDir   = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
            foreach ($_FILES['documents']['name'] as $i => $fname) {
                if ($_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts)) { $errors[] = "Unsupported file type: $fname"; continue; }
                if ($_FILES['documents']['size'][$i] > 10 * 1024 * 1024) { $errors[] = "$fname is too large (max 10MB)."; continue; }
                $newName = 'emp_doc_' . uniqid('', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['documents']['tmp_name'][$i], $uploadDir . $newName)) {
                    $documents[] = ['name' => $fname, 'path' => 'uploads/' . $newName];
                }
            }
        }

        if (empty($errors)) {
            $appId = 'eapp_' . uniqid('', true);
            $app = [
                'id'            => $appId,
                'company_name'  => $companyName,
                'industry'      => $industry,
                'company_size'  => $companySize,
                'website'       => $website,
                'description'   => $description,
                'contact_name'  => $contactName,
                'contact_phone' => $contactPhone,
                'email'         => $email,
                'username'      => $_SESSION['user']['username'] ?? $contactName,
                'documents'     => $documents,
                'status'        => 'pending',
                'submitted_at'  => date('M j, Y g:i A'),
                'reviewed_at'   => null,
            ];
            cs_save_employer_application($app);

            // Notify admin (stored in session — admin will see on login)
            if (!isset($_SESSION['admin_notifications'])) $_SESSION['admin_notifications'] = [];
            array_unshift($_SESSION['admin_notifications'], [
                'msg'  => '🏢 New employer request from ' . htmlspecialchars($companyName) . '.',
                'time' => date('M j, Y g:i A'),
                'read' => false,
            ]);

            $success    = 'Your application has been submitted! An admin will review it shortly.';
            $existingApp = $app;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 flex items-start justify-center p-4 py-12">
<div class="w-full max-w-2xl">

    <a href="<?php echo isset($_SESSION['user']) ? 'index.php' : 'login.php'; ?>"
       class="flex items-center gap-2 text-blue-900 font-semibold hover:underline mb-6 text-sm">
        ← Back
    </a>

    <div class="bg-white rounded-2xl shadow-lg p-8">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4">🏢</div>
            <h1 class="text-2xl font-extrabold text-gray-900">Apply for Employer Account</h1>
            <p class="text-gray-500 mt-2 text-sm">Submit your company info for admin verification. Once approved, you can post hiring positions and run skill assessments.</p>
        </div>

        <?php if ($existingApp): ?>
        <div class="text-center py-8">
            <?php
            $statusMap = [
                'pending'  => ['bg-amber-100 text-amber-700',  '⏳', 'Application Pending',   'Your application is under review. An admin will approve or reject it soon.'],
                'approved' => ['bg-green-100 text-green-700',  '✅', 'Application Approved!',  'Your employer account is verified.'],
                'rejected' => ['bg-red-100 text-red-700',      '❌', 'Application Rejected',   'Your application was not approved. Please contact support.'],
            ];
            [$cls, $icon, $title, $msg] = $statusMap[$existingApp['status']] ?? $statusMap['pending'];
            ?>
            <div class="w-16 h-16 rounded-full <?php echo $cls; ?> flex items-center justify-center text-3xl mx-auto mb-4"><?php echo $icon; ?></div>
            <h2 class="text-xl font-bold text-gray-900"><?php echo $title; ?></h2>
            <p class="text-gray-500 mt-2"><?php echo $msg; ?></p>
            <p class="text-xs text-gray-400 mt-3">Submitted: <?php echo htmlspecialchars($existingApp['submitted_at'] ?? ''); ?></p>
            <?php if ($existingApp['status'] === 'approved'): ?>
            <a href="employer_dashboard.php" class="inline-block mt-6 bg-purple-700 hover:bg-purple-800 text-white font-bold px-6 py-3 rounded-xl transition">
                Go to Employer Dashboard →
            </a>
            <?php endif; ?>
        </div>

        <?php elseif (!isset($_SESSION['user'])): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
            <p class="text-blue-800 font-semibold">You must be signed in to apply for an employer account.</p>
            <a href="login.php" class="inline-block mt-3 bg-blue-900 text-white font-bold px-6 py-2 rounded-xl hover:bg-blue-800 transition">Sign In</a>
            <p class="text-sm text-gray-500 mt-2">No account? <a href="signup.php" class="text-blue-700 font-semibold hover:underline">Sign up first</a></p>
        </div>

        <?php else: ?>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6 text-sm font-semibold">
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-5">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Company Name <span class="text-red-500">*</span></label>
                    <input name="company_name" required maxlength="100"
                           value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm"
                           placeholder="e.g. NetSentinel Solutions">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Industry <span class="text-red-500">*</span></label>
                    <select name="industry" required
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm">
                        <option value="">Select industry</option>
                        <?php foreach (['Cybersecurity','Information Technology','Banking & Finance','Healthcare IT','Government / Defense','Telecom','Consulting','Education','Other'] as $ind): ?>
                        <option value="<?php echo $ind; ?>" <?php echo (($_POST['industry'] ?? '') === $ind) ? 'selected' : ''; ?>><?php echo $ind; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Company Size</label>
                    <select name="company_size"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm">
                        <option value="">Select size</option>
                        <?php foreach (['1–10','11–50','51–200','201–500','500+'] as $sz): ?>
                        <option value="<?php echo $sz; ?>" <?php echo (($_POST['company_size'] ?? '') === $sz) ? 'selected' : ''; ?>><?php echo $sz; ?> employees</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Company Website</label>
                    <input name="website" type="url" maxlength="120"
                           value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm"
                           placeholder="https://yourcompany.com">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Company Description <span class="text-red-500">*</span></label>
                <textarea name="description" rows="4" required minlength="20" maxlength="1000"
                          class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm resize-none"
                          placeholder="Describe your company, what you do, and the roles you're hiring for…"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Contact Person <span class="text-red-500">*</span></label>
                    <input name="contact_name" required maxlength="80"
                           value="<?php echo htmlspecialchars($_POST['contact_name'] ?? ($_SESSION['user']['username'] ?? '')); ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Contact Phone</label>
                    <input name="contact_phone" type="tel" maxlength="30"
                           value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 text-sm"
                           placeholder="+63 9xx xxx xxxx">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Supporting Documents <span class="text-gray-400 font-normal">(optional, up to 3)</span>
                </label>
                <p class="text-xs text-gray-400 mb-2">Upload your SEC registration, DTI permit, or other company verification docs. PDF, DOC, or image files, max 10MB each.</p>
                <input type="file" name="documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-purple-100 file:text-purple-700 file:font-semibold hover:file:bg-purple-200">
            </div>

            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-800">
                <strong>Account email:</strong> <?php echo htmlspecialchars($_SESSION['user']['email']); ?><br>
                <span class="text-xs text-blue-600">Your existing account will be upgraded to Employer status upon approval.</span>
            </div>

            <button type="submit" class="w-full bg-purple-700 hover:bg-purple-800 text-white font-bold py-3.5 rounded-xl transition text-sm">
                Submit Application for Review
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
