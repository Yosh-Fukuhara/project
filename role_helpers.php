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

// ── Shared flat-file store for employer applications ──────────────────────
// Using a JSON file so ALL users/sessions see the same data.
// In production, swap for a DB table.

function cs_eapp_file(): string {
    $dir = defined('EMPLOYER_APPS_DIR') ? EMPLOYER_APPS_DIR : __DIR__ . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    return $dir . '/employer_applications.json';
}

function cs_read_employer_apps(): array {
    $f = cs_eapp_file();
    if (!file_exists($f)) return [];
    $raw = @file_get_contents($f);
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function cs_write_employer_apps(array $apps): void {
    file_put_contents(cs_eapp_file(), json_encode(array_values($apps), JSON_PRETTY_PRINT), LOCK_EX);
}

function cs_init_employer_applications(): void {
    // No-op — file store is always available
}

function cs_get_employer_applications(): array {
    return cs_read_employer_apps();
}

function cs_get_employer_application_by_id(string $id): ?array {
    foreach (cs_read_employer_apps() as $app) {
        if (($app['id'] ?? '') === $id) return $app;
    }
    return null;
}

function cs_get_employer_application_by_email(string $email): ?array {
    foreach (cs_read_employer_apps() as $app) {
        if (($app['email'] ?? '') === $email) return $app;
    }
    return null;
}

function cs_save_employer_application(array $app): void {
    $apps = cs_read_employer_apps();
    $found = false;
    foreach ($apps as &$a) {
        if (($a['id'] ?? '') === $app['id']) { $a = $app; $found = true; break; }
    }
    unset($a);
    if (!$found) $apps[] = $app;
    cs_write_employer_apps($apps);
}

function cs_update_employer_application_status(string $id, string $status): void {
    $apps = cs_read_employer_apps();
    foreach ($apps as &$app) {
        if (($app['id'] ?? '') === $id) {
            $app['status']      = $status;
            $app['reviewed_at'] = date('M j, Y g:i A');
            break;
        }
    }
    unset($app);
    cs_write_employer_apps($apps);
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
    return $_SESSION['applications'][$postId] ?? [];
}
function cs_get_all_employer_post_applications(string $employerEmail): array {
    $result = [];
    foreach ($_SESSION['posts'] ?? [] as $post) {
        if (($post['email'] ?? '') !== $employerEmail) continue;
        $pid = $post['id'] ?? '';
        if (!$pid) continue;
        foreach (cs_get_applications_for_post($pid) as $app) {
            $result[] = array_merge($app, [
                'post_id'      => $pid,
                'post_content' => mb_strimwidth($post['content'] ?? '', 0, 80, '…'),
            ]);
        }
    }
    return $result;
}
