<?php
require_once __DIR__ . '/includes/bootstrap.php';
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
            status,
            DATE_FORMAT(submitted_at, "%M %e, %Y %l:%i %p") AS submitted_at,
            DATE_FORMAT(reviewed_at, "%M %e, %Y %l:%i %p") AS reviewed_at
        FROM employer_applications ORDER BY submitted_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $apps = [];
        foreach ($rows as $row) {
            // Get documents from the new table
            $stmtDocs = $pdo->prepare('SELECT doc_id, file_name, file_url FROM EMPLOYER_APPLICATION_DOCUMENTS WHERE eapp_id = ?');
            $stmtDocs->execute([$row['eapp_id']]);
            $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
            $documents = [];
            foreach ($docs as $doc) {
                $documents[] = [
                    'id' => $doc['doc_id'],
                    'name' => $doc['file_name'],
                    'path' => $doc['file_url'],
                    'file_url' => $doc['file_url']
                ];
            }
            
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
                'documents' => $documents,
                'status' => $row['status'],
                'submitted_at' => $row['submitted_at'],
                'reviewed_at' => $row['reviewed_at']
            ];
            // Also get user info from users table
            $stmtUser = $pdo->prepare('SELECT email, first_name, last_name FROM users WHERE user_id = ?');
            $stmtUser->execute([$row['user_id']]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $apps[count($apps)-1]['email'] = $user['email'];
                $apps[count($apps)-1]['username'] = trim($user['first_name'] . ' ' . $user['last_name']);
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
            status,
            DATE_FORMAT(submitted_at, "%M %e, %Y %l:%i %p") AS submitted_at,
            DATE_FORMAT(reviewed_at, "%M %e, %Y %l:%i %p") AS reviewed_at
        FROM employer_applications WHERE eapp_id = ?');
        $stmt->execute([$idInt]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        
        // Get documents from new table
        $stmtDocs = $pdo->prepare('SELECT doc_id, file_name, file_url FROM EMPLOYER_APPLICATION_DOCUMENTS WHERE eapp_id = ?');
        $stmtDocs->execute([$row['eapp_id']]);
        $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
        $documents = [];
        foreach ($docs as $doc) {
            $documents[] = [
                'id' => $doc['doc_id'],
                'name' => $doc['file_name'],
                'path' => $doc['file_url'],
                'file_url' => $doc['file_url']
            ];
        }
        
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
            'documents' => $documents,
            'status' => $row['status'],
            'submitted_at' => $row['submitted_at'],
            'reviewed_at' => $row['reviewed_at']
        ];
        // Also get user info from users table
        $stmtUser = $pdo->prepare('SELECT email, first_name, last_name FROM users WHERE user_id = ?');
        $stmtUser->execute([$row['user_id']]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $app['email'] = $user['email'];
            $app['username'] = trim($user['first_name'] . ' ' . $user['last_name']);
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
            status,
            DATE_FORMAT(submitted_at, "%M %e, %Y %l:%i %p") AS submitted_at,
            DATE_FORMAT(reviewed_at, "%M %e, %Y %l:%i %p") AS reviewed_at
        FROM employer_applications WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 1');
        $stmt2->execute([$userId]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        
        // Get documents from new table
        $stmtDocs = $pdo->prepare('SELECT doc_id, file_name, file_url FROM EMPLOYER_APPLICATION_DOCUMENTS WHERE eapp_id = ?');
        $stmtDocs->execute([$row['eapp_id']]);
        $docs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
        $documents = [];
        foreach ($docs as $doc) {
            $documents[] = [
                'id' => $doc['doc_id'],
                'name' => $doc['file_name'],
                'path' => $doc['file_url'],
                'file_url' => $doc['file_url']
            ];
        }
        
        // Get user first and last name to build username
        $stmtUser = $pdo->prepare('SELECT first_name, last_name FROM users WHERE user_id = ?');
        $stmtUser->execute([$row['user_id']]);
        $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $username = $userInfo ? trim($userInfo['first_name'] . ' ' . $userInfo['last_name']) : ($_SESSION['user']['username'] ?? '');
        
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
            'documents' => $documents,
            'status' => $row['status'],
            'submitted_at' => $row['submitted_at'],
            'reviewed_at' => $row['reviewed_at'],
            'email' => $email,
            'username' => $username
        ];
    } catch (Exception $e) {
        return null;
    }
}

function cs_save_employer_application(array $app): ?array {
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
                $app['status'],
                $idInt
            ]);
            
            // Update documents in the new table
            if (isset($app['documents']) && is_array($app['documents'])) {
                // First delete existing documents for this application
                $stmtDelete = $pdo->prepare('DELETE FROM EMPLOYER_APPLICATION_DOCUMENTS WHERE eapp_id = ?');
                $stmtDelete->execute([$idInt]);
                
                // Then insert new documents
                foreach ($app['documents'] as $doc) {
                    $stmtDoc = $pdo->prepare('INSERT INTO EMPLOYER_APPLICATION_DOCUMENTS (eapp_id, file_name, file_url) VALUES (?, ?, ?)');
                    $stmtDoc->execute([
                        $idInt,
                        $doc['name'] ?? 'Document',
                        $doc['path'] ?? $doc['file_url'] ?? ''
                    ]);
                }
            }
            
            return cs_get_employer_application_by_id((string)$idInt);
        } else {
            $stmt = $pdo->prepare('INSERT INTO employer_applications (
                user_id, company_name, industry, company_size, website, 
                description, contact_name, contact_phone, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            
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
                    'pending'
                ]);
                $newId = $pdo->lastInsertId();
                
                // Insert documents into new table
                if (isset($app['documents']) && is_array($app['documents'])) {
                    foreach ($app['documents'] as $doc) {
                        $stmtDoc = $pdo->prepare('INSERT INTO EMPLOYER_APPLICATION_DOCUMENTS (eapp_id, file_name, file_url) VALUES (?, ?, ?)');
                        $stmtDoc->execute([
                            $newId,
                            $doc['name'] ?? 'Document',
                            $doc['path'] ?? $doc['file_url'] ?? ''
                        ]);
                    }
                }
                
                return cs_get_employer_application_by_id((string)$newId);
            }
        }
    } catch (Exception $e) {
        error_log("cs_save_employer_application error: " . $e->getMessage());
    }
    return null;
}

function cs_update_employer_application_status(string $id, string $status): void {
    $idInt = (int)$id;
    try {
        $pdo = get_db_connection();
        
        // First get the application details (including user_id)
        $stmtGetApp = $pdo->prepare('SELECT * FROM employer_applications WHERE eapp_id = ?');
        $stmtGetApp->execute([$idInt]);
        $app = $stmtGetApp->fetch(PDO::FETCH_ASSOC);
        if (!$app) return;
        
        // Update the application status
        $stmt = $pdo->prepare('UPDATE employer_applications SET status = ?, reviewed_at = CURRENT_TIMESTAMP WHERE eapp_id = ?');
        $stmt->execute([$status, $idInt]);
        
        // If approved, update user's role in users table and create employer record
        if ($status === 'approved' && $app['user_id']) {
            $stmt3 = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
            $stmt3->execute(['employer', $app['user_id']]);
            
            // Check if employer record already exists
            $stmtCheckEmp = $pdo->prepare('SELECT COUNT(*) FROM EMPLOYERS WHERE owner_user_id = ?');
            $stmtCheckEmp->execute([$app['user_id']]);
            if ($stmtCheckEmp->fetchColumn() == 0) {
                $stmtInsertEmp = $pdo->prepare('INSERT INTO EMPLOYERS (
                    owner_user_id, company_name, industry, company_size, website, description, contact_name, contact_phone
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmtInsertEmp->execute([
                    $app['user_id'],
                    $app['company_name'],
                    $app['industry'],
                    $app['company_size'],
                    $app['website'],
                    $app['description'],
                    $app['contact_name'],
                    $app['contact_phone']
                ]);
            }
            
            // Send notification to the user if the function exists
            if (function_exists('cs_save_notification')) {
                cs_save_notification($app['user_id'], '🎉 Your employer application has been approved! You can now post hiring jobs!', 'index.php');
            }
        } elseif ($status === 'rejected' && $app['user_id']) {
            // Send notification for rejection too if function exists
            if (function_exists('cs_save_notification')) {
                cs_save_notification($app['user_id'], 'Your employer application has been rejected. Please contact support for more information.', 'index.php');
            }
        }
    } catch (Exception $e) {
        error_log("cs_update_employer_application_status error: " . $e->getMessage());
    }
}

// ── Employer helpers ───────────────────────────────────────────────────
function cs_get_employer_by_user_id(int $userId): ?array {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT * FROM EMPLOYERS WHERE owner_user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

// ── Session-based assessments ─────────────────────────────────────────────
function cs_init_assessments(): void {
    if (!isset($_SESSION['cs_assessments'])) $_SESSION['cs_assessments'] = [];
}
function cs_get_assessments_by_employer(string $email): array {
    cs_init_assessments();
    try {
        $pdo = get_db_connection();
        // Get employer user id from email
        $stmtUserId = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmtUserId->execute([$email]);
        $userId = $stmtUserId->fetchColumn();
        if (!$userId) {
            // Fall back to session
            return array_values(array_filter($_SESSION['cs_assessments'], fn($a) => ($a['employer_email'] ?? '') === $email));
        }
        
        // Get assessments from database
        $stmt = $pdo->prepare('SELECT assessment_id, title, instructions, time_limit_mins, created_at FROM assessments WHERE created_by = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($assessments as $dbAssess) {
            // Get challenges
            $stmtChallenges = $pdo->prepare('SELECT challenge_id, type, title, body, points_available, correct_answer FROM assessment_challenges WHERE assessment_id = ? ORDER BY sort_order ASC');
            $stmtChallenges->execute([$dbAssess['assessment_id']]);
            $challenges = $stmtChallenges->fetchAll(PDO::FETCH_ASSOC);
            
            // Format challenges like session format
            $formattedChallenges = [];
            foreach ($challenges as $ch) {
                // Get attachments for this challenge
                $stmtAttach = $pdo->prepare('SELECT attachment_id, file_name, file_path, icon, note FROM challenge_attachments WHERE challenge_id = ?');
                $stmtAttach->execute([$ch['challenge_id']]);
                $attachments = $stmtAttach->fetchAll(PDO::FETCH_ASSOC);
                
                // Get options for this challenge
                $stmtOptions = $pdo->prepare('SELECT option_id, option_text, is_correct FROM challenge_options WHERE challenge_id = ?');
                $stmtOptions->execute([$ch['challenge_id']]);
                $options = $stmtOptions->fetchAll(PDO::FETCH_ASSOC);
                
                $formattedChallenges[] = [
                    'id' => 'c_' . $ch['challenge_id'],
                    'type' => $ch['type'],
                    'title' => $ch['title'],
                    'body' => $ch['body'],
                    'points' => $ch['points_available'],
                    'hint' => '', // Hint not in DB yet
                    'correct_flag' => $ch['correct_answer'],
                    'attachment' => null,
                    'attachments' => $attachments,
                    'options' => $options
                ];
            }
            
            // Get employer company name
            $companyName = $_SESSION['user']['company_name'] ?? $_SESSION['user']['username'] ?? 'Your Company';
            
            $result[] = [
                'id' => 'assess_' . $dbAssess['assessment_id'],
                'db_id' => $dbAssess['assessment_id'],
                'employer_email' => $email,
                'employer_name' => $companyName,
                'title' => $dbAssess['title'],
                'role' => '', // Role not in DB yet
                'time_limit' => $dbAssess['time_limit_mins'],
                'instructions' => $dbAssess['instructions'],
                'challenges' => $formattedChallenges,
                'created_at' => date('M j, Y g:i A', strtotime($dbAssess['created_at'])),
                'total_pts' => array_sum(array_column($formattedChallenges, 'points')),
            ];
        }
        
        // Merge with session assessments for backwards compatibility
        $sessionAssessments = array_values(array_filter($_SESSION['cs_assessments'], fn($a) => ($a['employer_email'] ?? '') === $email));
        $result = array_merge($result, $sessionAssessments);
        
        return $result;
    } catch (Exception $e) {
        error_log("cs_get_assessments_by_employer error: " . $e->getMessage());
        // Fall back to session
        return array_values(array_filter($_SESSION['cs_assessments'], fn($a) => ($a['employer_email'] ?? '') === $email));
    }
}
function cs_get_assessment_by_id(string $id): ?array {
    cs_init_assessments();
    try {
        // Check if id is in database format: "assess_{id}"
        if (str_starts_with($id, 'assess_')) {
            $dbAssessId = (int)substr($id, 7);
            if ($dbAssessId > 0) {
                $pdo = get_db_connection();
                // Get assessment
                $stmt = $pdo->prepare('SELECT assessment_id, title, instructions, time_limit_mins, created_at, created_by FROM assessments WHERE assessment_id = ?');
                $stmt->execute([$dbAssessId]);
                $dbAssess = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($dbAssess) {
                    // Get challenges
                    $stmtChallenges = $pdo->prepare('SELECT challenge_id, type, title, body, points_available, correct_answer FROM assessment_challenges WHERE assessment_id = ? ORDER BY sort_order ASC');
                    $stmtChallenges->execute([$dbAssessId]);
                    $challenges = $stmtChallenges->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Format challenges like session format
                    $formattedChallenges = [];
                    foreach ($challenges as $ch) {
                        // Get attachments for this challenge
                        $stmtAttach = $pdo->prepare('SELECT attachment_id, file_name, file_path, icon, note FROM challenge_attachments WHERE challenge_id = ?');
                        $stmtAttach->execute([$ch['challenge_id']]);
                        $attachments = $stmtAttach->fetchAll(PDO::FETCH_ASSOC);
                        
                        $formattedChallenges[] = [
                            'id' => 'c_' . $ch['challenge_id'],
                            'db_id' => $ch['challenge_id'],
                            'type' => $ch['type'],
                            'title' => $ch['title'],
                            'body' => $ch['body'],
                            'points' => $ch['points_available'],
                            'hint' => '', // Hint not in DB yet
                            'correct_flag' => $ch['correct_answer'],
                            'attachment' => null,
                            'attachments' => $attachments,
                        ];
                    }
                    
                    // Get employer email from created_by user id
                    $stmtUser = $pdo->prepare('SELECT email FROM users WHERE user_id = ?');
                    $stmtUser->execute([$dbAssess['created_by']]);
                    $employerEmail = $stmtUser->fetchColumn();
                    
                    $companyName = $_SESSION['user']['company_name'] ?? $_SESSION['user']['username'] ?? 'Your Company';
                    
                    return [
                        'id' => 'assess_' . $dbAssess['assessment_id'],
                        'db_id' => $dbAssess['assessment_id'],
                        'employer_email' => $employerEmail,
                        'employer_name' => $companyName,
                        'title' => $dbAssess['title'],
                        'role' => '', // Role not in DB yet
                        'time_limit' => $dbAssess['time_limit_mins'],
                        'instructions' => $dbAssess['instructions'],
                        'challenges' => $formattedChallenges,
                        'created_at' => date('M j, Y g:i A', strtotime($dbAssess['created_at'])),
                        'total_pts' => array_sum(array_column($formattedChallenges, 'points')),
                    ];
                }
            }
        }
        
        // Fall back to session
        foreach ($_SESSION['cs_assessments'] as $a) {
            if (($a['id'] ?? '') === $id) return $a;
        }
        return null;
    } catch (Exception $e) {
        error_log("cs_get_assessment_by_id error: " . $e->getMessage());
        // Fall back to session
        foreach ($_SESSION['cs_assessments'] as $a) {
            if (($a['id'] ?? '') === $id) return $a;
        }
        return null;
    }
}

// Get all assessment attempts for a specific assessment
function cs_get_assessment_attempts(int $assessmentId): array {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('
            SELECT 
                a.attempt_id, a.session_id, a.user_id, a.started_at, a.completed_at, 
                a.total_time_secs, a.total_score, a.challenges_solved, a.status,
                u.first_name, u.last_name, u.email
            FROM user_assessment_attempts a
            JOIN users u ON a.user_id = u.user_id
            JOIN assessment_sessions s ON a.session_id = s.session_id
            WHERE s.assessment_id = ?
            ORDER BY a.completed_at DESC
        ');
        $stmt->execute([$assessmentId]);
        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedAttempts = [];
        foreach ($attempts as $att) {
            // Get challenge answers for this attempt
            $stmtAnswers = $pdo->prepare('
                SELECT 
                    ca.answer_id, ca.challenge_id, ca.submitted_answer, ca.status, 
                    ca.points_awarded, ch.title, ch.points_available
                FROM user_challenge_answers ca
                JOIN assessment_challenges ch ON ca.challenge_id = ch.challenge_id
                WHERE ca.attempt_id = ?
                ORDER BY ch.sort_order ASC
            ');
            $stmtAnswers->execute([$att['attempt_id']]);
            $answers = $stmtAnswers->fetchAll(PDO::FETCH_ASSOC);

            $formattedAnswers = [];
            foreach ($answers as $ans) {
                $formattedAnswers[] = [
                    'id' => 'c_' . $ans['challenge_id'],
                    'title' => $ans['title'],
                    'pts' => (int) $ans['points_available'],
                    'earned' => (int) $ans['points_awarded'],
                    'time' => '—', // We don't track per-challenge time yet
                    'status' => $ans['status'],
                    'submitted_answer' => $ans['submitted_answer'],
                ];
            }

            $formattedAttempts[] = [
                'id' => 'app_' . $att['attempt_id'],
                'name' => trim(($att['first_name'] ?? '') . ' ' . ($att['last_name'] ?? '')) ?: 'Applicant',
                'email' => $att['email'],
                'avatar' => null,
                'applied' => date('M j, Y', strtotime($att['started_at'])),
                'completed_at' => $att['completed_at'] ? date('M j, Y g:i A', strtotime($att['completed_at'])) : null,
                'time_used' => $att['total_time_secs'] ? sprintf('%02d:%02d', floor($att['total_time_secs'] / 60), $att['total_time_secs'] % 60) : null,
                'time_secs' => $att['total_time_secs'],
                'score' => (int) $att['total_score'],
                'solved' => (int) $att['challenges_solved'],
                'challenge_results' => $formattedAnswers,
                'status' => $att['status'],
                'notes' => '',
            ];
        }
        return $formattedAttempts;
    } catch (Exception $e) {
        error_log("cs_get_assessment_attempts error: " . $e->getMessage());
        return [];
    }
}

// ── Job applications helpers ────────────────────────────────────────────────
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
