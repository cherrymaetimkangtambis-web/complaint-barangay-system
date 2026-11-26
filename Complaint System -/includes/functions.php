<?php
// ---------- Basic helpers ----------

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (BASE_URL_OVERRIDE !== '') {
        return $base = rtrim(BASE_URL_OVERRIDE, '/');
    }
    $root = realpath(__DIR__ . '/..');
    $doc  = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($root && $doc) {
        $root = str_replace('\\', '/', $root);
        $doc  = str_replace('\\', '/', $doc);
        if (stripos($root, $doc) === 0) {
            return $base = rtrim(substr($root, strlen($doc)), '/');
        }
    }
    return $base = '';
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

// ---------- Database shortcuts ----------

function rows(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function row(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r === false ? null : $r;
}

function scalar(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function run(string $sql, array $params = []): void
{
    db()->prepare($sql)->execute($params);
}

function site_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $settings = [];
    $tableExists = (int)scalar(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        ['site_settings']
    );
    if ($tableExists) {
        foreach (rows('SELECT setting_key, setting_value FROM site_settings') as $setting) {
            $settings[$setting['setting_key']] = $setting['setting_value'];
        }
    }
    return $settings;
}

function site_setting(string $key, string $fallback = ''): string
{
    $settings = site_settings();
    return array_key_exists($key, $settings) ? (string)$settings[$key] : $fallback;
}

function site_asset_url(string $key): string
{
    $path = site_setting($key);
    if (!preg_match('#^uploads/site/[a-f0-9]{32}\.(jpg|png)$#D', $path)) {
        return '';
    }
    return url($path);
}

function site_officials(): array
{
    $json = site_setting('officials_json');
    if ($json === '') {
        return BARANGAY_OFFICIALS;
    }
    $officials = json_decode($json, true);
    return is_array($officials) ? $officials : BARANGAY_OFFICIALS;
}

function site_faq(): array
{
    $json = site_setting('faq_json');
    if ($json === '') {
        return PUBLIC_FAQ;
    }
    $faq = json_decode($json, true);
    return is_array($faq) ? $faq : PUBLIC_FAQ;
}

function save_site_image(array $file, &$error = null)
{
    $error = null;
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'The image is too large (maximum 5 MB).'
            : 'Image upload failed. Please try again.';
        return null;
    }
    if ($file['size'] > MAX_SITE_IMAGE_BYTES) {
        $error = 'The image is too large (maximum 5 MB).';
        return null;
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $error = 'The uploaded image could not be verified. Please select it again.';
        return null;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($extensions[$mime])) {
        $error = 'Choose a JPG or PNG image.';
        return null;
    }
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
        $error = 'Image processing is unavailable on this server. Contact the administrator.';
        return null;
    }

    $warning = null;
    set_error_handler(static function ($severity, $message) use (&$warning) {
        $warning = $message;
        return true;
    });
    try {
        $info = getimagesize($file['tmp_name']);
    } finally {
        restore_error_handler();
    }
    if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000 ||
        $info[0] * $info[1] > 20000000) {
        $error = 'Choose a valid image no larger than 6000 by 6000 pixels.';
        return null;
    }

    $warning = null;
    set_error_handler(static function ($severity, $message) use (&$warning) {
        $warning = $message;
        return true;
    });
    try {
        $image = $mime === 'image/jpeg'
            ? imagecreatefromjpeg($file['tmp_name'])
            : imagecreatefrompng($file['tmp_name']);
    } finally {
        restore_error_handler();
    }
    if ($image === false) {
        $error = 'The image could not be processed. Choose another file.';
        return null;
    }

    $directory = __DIR__ . '/../uploads/site';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        imagedestroy($image);
        $error = 'Could not create the image folder. Check the uploads folder permissions.';
        return null;
    }
    $extension = $extensions[$mime];
    $name = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $directory . DIRECTORY_SEPARATOR . $name;

    $warning = null;
    set_error_handler(static function ($severity, $message) use (&$warning) {
        $warning = $message;
        return true;
    });
    try {
        if ($mime === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = imagepng($image, $destination, 6);
        } else {
            $saved = imagejpeg($image, $destination, 88);
        }
    } finally {
        restore_error_handler();
        imagedestroy($image);
    }
    if (!$saved) {
        $error = 'Could not save the image. Check the uploads folder permissions.';
        return null;
    }
    if (filesize($destination) > MAX_SITE_IMAGE_BYTES) {
        if (!unlink($destination)) {
            error_log('Could not remove oversized processed site image: ' . $destination);
        }
        $error = 'The processed image is too large. Choose a smaller image.';
        return null;
    }
    return 'uploads/site/' . $name;
}

function delete_site_image(string $path): void
{
    if (!preg_match('#^uploads/site/[a-f0-9]{32}\.(jpg|png)$#D', $path)) {
        return;
    }
    $file = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (is_file($file) && !unlink($file)) {
        error_log('Could not remove replaced site image: ' . $path);
    }
}

// ---------- Flash messages ----------

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- CSRF protection ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['csrf_token'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Invalid or expired form token. Please go back, refresh the page and try again.');
    }
}

// ---------- Login / roles ----------

function current_user()
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        return $user = null;
    }
    $u = row('SELECT id, full_name, email, contact_number, purok, role, is_active FROM users WHERE id = ?',
             [$_SESSION['user_id']]);
    if (!$u || !(int)$u['is_active']) {
        unset($_SESSION['user_id']);
        return $user = null;
    }
    return $user = $u;
}

function is_staff_role(string $role): bool
{
    return in_array($role, ['admin', 'official'], true);
}

function home_for_role(string $role): string
{
    return $role === 'resident' ? 'resident/dashboard.php' : 'admin/dashboard.php';
}

function require_login(array $roles = []): array
{
    $u = current_user();
    if (!$u) {
        flash('error', 'Please login first.');
        redirect('auth/login.php');
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        flash('error', 'You do not have access to that page.');
        redirect(home_for_role($u['role']));
    }
    return $u;
}

// ---------- Logs & notifications ----------

function log_activity(string $action, $userId = null): void
{
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? null;
    }
    run('INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)',
        [$userId, $action, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function notify(int $userId, $complaintId, string $message): void
{
    run('INSERT INTO notifications (user_id, complaint_id, message) VALUES (?, ?, ?)',
        [$userId, $complaintId, $message]);
}

// New unassigned complaints are visible to admins until an official is assigned.
function notify_admins($complaintId, string $message): void
{
    run("INSERT INTO notifications (user_id, complaint_id, message)
         SELECT id, ?, ? FROM users WHERE role = 'admin' AND is_active = 1",
        [$complaintId, $message]);
}

function unread_count(int $userId): int
{
    static $hasArchiveColumn = null;
    if ($hasArchiveColumn === null) {
        $hasArchiveColumn = (bool)scalar(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['complaints', 'archived_at']
        );
    }
    $officialScope = $hasArchiveColumn ? '(c.assigned_to = u.id AND c.archived_at IS NULL)' : 'c.assigned_to = u.id';
    return (int)scalar(
        'SELECT COUNT(*) FROM notifications n
         JOIN users u ON u.id = n.user_id
         LEFT JOIN complaints c ON c.id = n.complaint_id
         WHERE n.user_id = ? AND n.is_read = 0
           AND (u.role <> "official" OR n.complaint_id IS NULL OR ' . $officialScope . ')',
        [$userId]
    );
}

// ---------- Complaint helpers ----------

function make_reference(int $id): string
{
    return 'BSR-' . date('Y') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

function status_badge(string $s): string
{
    $map = [
        'Pending'     => 'b-pending',
        'In Progress' => 'b-progress',
        'Resolved'    => 'b-resolved',
        'Cancelled'   => 'b-cancelled',
    ];
    return '<span class="badge ' . ($map[$s] ?? 'b-pending') . '">' . e($s) . '</span>';
}

function priority_badge(string $p): string
{
    $map = ['Low' => 'p-low', 'Medium' => 'p-medium', 'Urgent' => 'p-urgent'];
    return '<span class="badge ' . ($map[$p] ?? 'p-medium') . '">' . e($p) . '</span>';
}

function fmt_date($d): string
{
    return $d ? date('M j, Y g:i A', strtotime($d)) : '-';
}

function valid_date(string $d): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $parts)) {
        return false;
    }
    return checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]);
}

// Save an uploaded JPG/PNG photo. Returns the new file name, or null (and sets $error if there was a problem).
function save_photo(array $file, &$error = null)
{
    $error = null;
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $error = 'The photo is too large (maximum 2 MB).';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Photo upload failed. Please try again.';
        return null;
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        $error = 'The photo is too large (maximum 2 MB).';
        return null;
    }
    $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        $error = 'Only JPG or PNG photos are allowed.';
        return null;
    }
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        $error = 'Could not save the photo. Check the uploads folder permissions.';
        return null;
    }
    return $name;
}

function delete_photo($name): void
{
    if ($name && preg_match('/^[a-f0-9]{32}\.(jpg|png)$/', $name)) {
        @unlink(__DIR__ . '/../uploads/' . $name);
    }
}

// ---------- Pagination ----------

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage,
            'perPage' => $perPage, 'total' => $total];
}

function pager(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $q    = $_GET;
    $html = '<div class="pager">';
    $link = function ($page, $label, $active = false) use ($q) {
        $q['page'] = $page;
        return '<a' . ($active ? ' class="active"' : '') . ' href="?' . e(http_build_query($q)) . '">' . $label . '</a>';
    };
    if ($p['page'] > 1) {
        $html .= $link($p['page'] - 1, '&laquo; Prev');
    }
    for ($i = max(1, $p['page'] - 2); $i <= min($p['pages'], $p['page'] + 2); $i++) {
        $html .= $link($i, (string)$i, $i === $p['page']);
    }
    if ($p['page'] < $p['pages']) {
        $html .= $link($p['page'] + 1, 'Next &raquo;');
    }
    return $html . '</div>';
}

// Prevent CSV/Excel formula injection
function csv_safe($v): string
{
    $v = (string)$v;
    return ($v !== '' && strpos('=+-@', $v[0]) !== false) ? "'" . $v : $v;
}
