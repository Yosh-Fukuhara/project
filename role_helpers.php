<?php
// ── Role helpers ──────────────────────────────────────────────────────────
// Roles: 'applicant' (default), 'employer', 'admin'

function cs_role(): string {
    return $_SESSION['user']['role'] ?? 'applicant';
}
function cs_is_admin(): bool    { return cs_role() === 'admin'; }
function cs_is_employer(): bool { return cs_role() === 'employer'; }
function cs_is_applicant(): bool{ return cs_role() === 'applicant'; }
function cs_employer_verified(): bool {
    return cs_is_employer() && !empty($_SESSION['user']['employer_verified']);
}

function cs_require_role(string $role, string $redirect = 'index.php'): void {
    if (cs_role() !== $role) {
        $_SESSION['flash_error'] = 'You do not have permission to access that page.';
        header('Location: ' . $redirect); exit;
    }
}
function cs_require_auth(string $redirect = 'login.php'): void {
    if (!isset($_SESSION['user'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . $redirect); exit;
    }
}

function cs_role_badge(string $role, bool $verified = false): string {
    $map = [
        'admin'     => ['bg-red-100 text-red-700',      'Admin'],
        'employer'  => ['bg-purple-100 text-purple-700', 'Employer'],
        'applicant' => ['bg-blue-100 text-blue-700',     'Applicant'],
    ];
    [$cls, $label] = $map[$role] ?? ['bg-gray-100 text-gray-600', ucfirst($role)];
    $extra = ($role === 'employer' && !$verified) ? ' <span class="text-amber-600 font-normal text-xs">(pending)</span>' : '';
    return '<span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full ' . $cls . '">' . $label . $extra . '</span>';
}

// ── Database store for employer applications ──────────────────────────────
function cs_read_employer_apps(): array {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query('SELECT 
            eapp_id,
            user_id,
            company_name,
            industry,
            company_size,
            website,
            description,
            contact_name,
            contact_phone,
            documents,
            status,
            DATE_FORMAT(submitted_at, "%M %e, %Y %l:%i %p") AS submitted_at,
            DATE_FORMAT(reviewed_at, "%M %e, %Y %l:%i %p") AS reviewed_at
        FROM employer_applications ORDER BY submitted_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $apps = [];
        foreach ($rows as $row) {
            $apps[] = [
                'id' => (string)$row['eapp_id'],
                'user_id' => $row['user_id'],
                'company_name' => $row['company_name'],
                'industry' => $row['industry'],
                'company_size' => $row['company_size'],
                'website' => $row['website'],
                'description' => $row['description'],
                'contact_name' => $row['contact_name'],
                'contact_phone' => $row['contact_phone'],
                'documents' => json_decode($row['documents'], true) ?? [],
                'status' => $row['status'],
                'submitted_at' => $row['submitted_at'],
                'reviewed_at' => $row['reviewed_at']
            ];
            // Also get email from users table
            $stmtUser = $pdo->prepare('SELECT email, username FROM users WHERE user_id = ?');
            $stmtUser->execute([$row['user_id']]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $apps[count($apps)-1]['email'] = $user['email'];
                $apps[count($apps)-1]['username'] = $user['username'];
            }
        }
        return $apps;
    } catch (Exception $e) {
        return [];
    }
}

function cs_get_employer_applications(): array {
    return cs_read_employer_apps();
}

function cs_get_employer_application_by_id(string $id): ?array {
    $idInt = (int)$id;
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT 
            eapp_id,
            user_id,
            company_name,
            industry,
            company_size,
            website,
            description,
            contact_name,
            contact_phone,
            documents,
            status,
            DATE_FORMAT(submitted_at, "%M %e, %Y %l:%i %p") AS submitted_at,
            DATE_FORMAT(reviewed_at, "%M %e, %Y %l:%i %p") AS reviewed_at
        FROM employer_applications WHERE eapp_id = ?');
        $stmt->execute([$idInt]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $app = [
            'id' => (string)$row['eapp_id'],
            'user_id' => $row['user_id'],
            'company_name' => $row['company_name'],
            'industry' => $row['industry'],
            'company_size' => $row['company_size'],
            'website' => $row['website'],
            'description' => $row['description'],
            'contact_name' => $row['contact_name'],
            'contact_phone' => $row['contact_phone'],
            'documents' => json_decode($row['documents'], true) ?? [],
            'status' => $row['status'],
            'submitted_at' => $row['submitted_at'],
            'reviewed_at' => $row['reviewed_at']
        ];
        // Also get email from users table
        $stmtUser = $pdo->prepare('SELECT email, username FROM users WHERE user_id = ?');
        $stmtUser->execute([$row['user_id']]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $app['email'] = $user['email'];
            $app['username'] = $user['username'];
        }
        return $app;
    } catch (Exception $e) {
        return null;
    }
}

function cs_get_employer_application_by_email(string $email): ?array {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $userId = $stmt->fetchColumn();
        if (!$userId) return null;
        
        $stmt2 = $pdo->prepare('SELECT 
            eapp_id,
            user_id,
            company_name,
            industry,
            company_size,
            website,
            description,
            contact_name,
            contact_phone,
            documents,
            status,
            DATE_FORMAT(submitted_at, "%M %e, %Y %l:%i %p") AS submitted_at,
            DATE_FORMAT(reviewed_at, "%M %e, %Y %l:%i %p") AS reviewed_at
        FROM employer_applications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
        $stmt2->execute([$userId]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        
        return [
            'id' => (string)$row['eapp_id'],
            'user_id' => $row['user_id'],
            'company_name' => $row['company_name'],
            'industry' => $row['industry'],
            'company_size' => $row['company_size'],
            'website' => $row['website'],
            'description' => $row['description'],
            'contact_name' => $row['contact_name'],
            'contact_phone' => $row['contact_phone'],
            'documents' => json_decode($row['documents'], true) ?? [],
            'status' => $row['status'],
            'submitted_at' => $row['submitted_at'],
            'reviewed_at' => $row['reviewed_at'],
            'email' => $email,
            'username' => $_SESSION['user']['username'] ?? ''
        ];
    } catch (Exception $e) {
        return null;
    }
}

function cs_save_employer_application(array $app): void {
    try {
        $pdo = get_db_connection();
        
        if (isset($app['id'])) {
            $idInt = (int)$app['id'];
            $stmt = $pdo->prepare('UPDATE employer_applications SET 
                company_name = ?,
                industry = ?,
                company_size = ?,
                website = ?,
                description = ?,
                contact_name = ?,
                contact_phone = ?,
                documents = ?,
                status = ?
            WHERE eapp_id = ?');
            $stmt->execute([
                $app['company_name'],
                $app['industry'],
                $app['company_size'] ?? null,
                $app['website'] ?? null,
                $app['description'],
                $app['contact_name'],
                $app['contact_phone'] ?? null,
                json_encode($app['documents'] ?? []),
                $app['status'],
                $idInt
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO employer_applications (
                user_id, company_name, industry, company_size, website, 
                description, contact_name, contact_phone, documents, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            
            // Get user_id from email
            $userId = null;
            if (isset($_SESSION['user']['user_id'])) {
                $userId = $_SESSION['user']['user_id'];
            } else {
                $stmtUser = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
                $stmtUser->execute([$app['email']]);
                $userId = $stmtUser->fetchColumn();
            }
            
            if ($userId) {
                $stmt->execute([
                    $userId,
                    $app['company_name'],
                    $app['industry'],
                    $app['company_size'] ?? null,
                    $app['website'] ?? null,
                    $app['description'],
                    $app['contact_name'],
                    $app['contact_phone'] ?? null,
                    json_encode($app['documents'] ?? []),
                    'pending'
                ]);
            }
        }
    } catch (Exception $e) {
        // Ignore errors for now
    }
}

function cs_update_employer_application_status(string $id, string $status): void {
    $idInt = (int)$id;
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('UPDATE employer_applications SET status = ?, reviewed_at = CURRENT_TIMESTAMP WHERE eapp_id = ?');
        $stmt->execute([$status, $idInt]);
        
        // If approved, update user's role in users table
        if ($status === 'approved') {
            $stmt2 = $pdo->prepare('SELECT user_id FROM employer_applications WHERE eapp_id = ?');
            $stmt2->execute([$idInt]);
            $userId = $stmt2->fetchColumn();
            if ($userId) {
                $stmt3 = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
                $stmt3->execute(['employer', $userId]);
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }
}

// ── Session-based assessments ─────────────────────────────────────────────
function cs_init_assessments(): void {
    if (!isset($_SESSION['cs_assessments'])) $_SESSION['cs_assessments'] = [];
}
function cs_get_assessments_by_employer(string $email): array {
    cs_init_assessments();
    return array_values(array_filter($_SESSION['cs_assessments'], fn($a) => ($a['employer_email'] ?? '') === $email));
}
function cs_get_assessment_by_id(string $id): ?array {
    cs_init_assessments();
    foreach ($_SESSION['cs_assessments'] as $a) {
        if (($a['id'] ?? '') === $id) return $a;
    }
    return null;
}

// ── Job applications helpers ───────────────────────────────────────────────
function cs_get_applications_for_post(string $postId): array {
    try {
        $pdo = get_db_connection();
        $postIdInt = (int)$postId;
        $stmt = $pdo->prepare('SELECT 
            application_id,
            post_id,
            applicant_id,
            app_first_name,
            app_last_name,
            app_email,
            app_phone,
            app_message,
            resume_path,
            status,
            DATE_FORMAT(created_at, "%M %e, %Y %l:%i %p") AS created_at
        FROM job_applications WHERE post_id = ? ORDER BY created_at DESC');
        $stmt->execute([$postIdInt]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $apps = [];
        foreach ($rows as $row) {
            $fullName = trim($row['app_first_name'] . ' ' . $row['app_last_name']);
            $apps[] = [
                'id' => (string)$row['application_id'],
                'post_id' => (string)$row['post_id'],
                'applicant_id' => $row['applicant_id'],
                'first_name' => $row['app_first_name'],
                'last_name' => $row['app_last_name'],
                'name' => $fullName,
                'email' => $row['app_email'],
                'phone' => $row['app_phone'],
                'message' => $row['app_message'],
                'resume' => $row['resume_path'],
                'status' => $row['status'],
                'time' => $row['created_at']
            ];
        }
        return $apps;
    } catch (Exception $e) {
        return [];
    }
}
function cs_get_all_employer_post_applications(string $employerEmail): array {
    try {
        $pdo = get_db_connection();
        // First get employer user id
        $stmtUser = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmtUser->execute([$employerEmail]);
        $employerUserId = $stmtUser->fetchColumn();
        if (!$employerUserId) return [];
        
        $stmt = $pdo->prepare('SELECT 
            ja.application_id,
            ja.post_id,
            ja.applicant_id,
            ja.app_first_name,
            ja.app_last_name,
            ja.app_email,
            ja.app_phone,
            ja.app_message,
            ja.resume_path,
            ja.status,
            DATE_FORMAT(ja.created_at, "%M %e, %Y %l:%i %p") AS created_at,
            p.content AS post_content
        FROM job_applications ja
        JOIN posts p ON ja.post_id = p.post_id
        WHERE p.user_id = ?
        ORDER BY ja.created_at DESC');
        $stmt->execute([$employerUserId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $apps = [];
        foreach ($rows as $row) {
            $fullName = trim($row['app_first_name'] . ' ' . $row['app_last_name']);
            $apps[] = [
                'id' => (string)$row['application_id'],
                'post_id' => (string)$row['post_id'],
                'applicant_id' => $row['applicant_id'],
                'first_name' => $row['app_first_name'],
                'last_name' => $row['app_last_name'],
                'name' => $fullName,
                'email' => $row['app_email'],
                'phone' => $row['app_phone'],
                'message' => $row['app_message'],
                'resume' => $row['resume_path'],
                'status' => $row['status'],
                'time' => $row['created_at'],
                'post_content' => mb_strimwidth($row['post_content'] ?? '', 0, 80, '…')
            ];
        }
        return $apps;
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("cs_get_all_employer_post_applications error: " . $e->getMessage());
        // Fall back to session
        $result = [];
        foreach ($_SESSION['posts'] ?? [] as $post) {
            if (($post['email'] ?? '') !== $employerEmail) continue;
            $pid = $post['id'] ?? '';
            if (!$pid) continue;
            foreach (($_SESSION['applications'][$pid] ?? []) as $app) {
                $result[] = array_merge($app, [
                    'post_id' => $pid,
                    'post_content' => mb_strimwidth($post['content'] ?? '', 0, 80, '…')
                ]);
            }
        }
        return $result;
    }
}
