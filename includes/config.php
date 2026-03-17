<?php
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'perpustakaan');
define('DB_PORT', (int)(getenv('MYSQLPORT') ?: 3306));

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

session_start();

// ── Session timeout: 2 jam tidak aktif akan logout otomatis ──
define('SESSION_TIMEOUT', 7200); // detik
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        header("Location: /login.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// ── Helper: ambil pengaturan sistem ──────────────────────────
function getSetting($key, $default = '') {
    global $conn;
    $k   = $conn->real_escape_string($key);
    $res = $conn->query("SELECT nilai FROM pengaturan WHERE kunci='$k' LIMIT 1");
    if ($res && $res->num_rows > 0) return $res->fetch_assoc()['nilai'];
    return $default;
}

// ── Helper: catat log aktivitas ──────────────────────────────
function catatLog($aksi, $keterangan = '') {
    global $conn;
    $user_id   = $_SESSION['user_id']   ?? '-';
    $user_name = $_SESSION['user_name'] ?? '-';
    $role      = $_SESSION['role']      ?? '-';
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '-';

    $stmt = $conn->prepare("INSERT INTO log_aktivitas (user_id, user_name, role, aksi, keterangan, ip_address) VALUES (?,?,?,?,?,?)");
    if ($stmt) {
        $stmt->bind_param("ssssss", $user_id, $user_name, $role, $aksi, $keterangan, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// ── Auto-inject theme CSS ke semua halaman ───────────────────
// Cukup di config.php ini saja, tidak perlu edit halaman lain
ob_start(function($buffer) {
    $css = '<style id="perp-theme">
:root{--bg:#1a1a14;--bg2:#1e1e18;--card:#242418;--border:#3a3a2a;--gold:#c9a84c;--gold-light:#e8c97a;--green:#4caf82;--green-dark:#3a9168;--text:#e8e4d8;--text-muted:#8a8672;--input-bg:#1e1e18;--error:#e07070;--navbar-bg:#1e1e18;--shadow:rgba(0,0,0,.45);--overlay:rgba(0,0,0,.72)}
[data-theme="light"]{--bg:#d6e4ed;--bg2:#c2d6e3;--card:#eaf3f8;--border:#9ab8cc;--gold:#1a5c80;--gold-light:#2a7aa8;--green:#1a6b4a;--green-dark:#0e4e34;--text:#0d2a38;--text-muted:#4a7a96;--input-bg:#daeaf4;--error:#8b2020;--navbar-bg:#eaf3f8;--shadow:rgba(13,42,56,.12);--overlay:rgba(13,42,56,.55)}
*,*::before,*::after{transition:background-color .25s,border-color .25s,color .15s}
.modal,.modal *,.hamburger span,.btn-spinner{transition:none!important}
.theme-toggle{width:34px;height:34px;border-radius:8px;border:1px solid var(--border);background:var(--bg2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;position:relative;overflow:hidden}
.theme-toggle:hover{border-color:var(--gold);transform:scale(1.08)}
.t-dk,.t-lt{position:absolute;transition:opacity .25s,transform .3s}
.t-dk{opacity:1;transform:rotate(0) scale(1)}
.t-lt{opacity:0;transform:rotate(90deg) scale(.6)}
[data-theme="light"] body{background:var(--bg);color:var(--text)}
[data-theme="light"] .navbar{background:var(--navbar-bg)!important;border-bottom-color:var(--border)!important;box-shadow:0 1px 10px var(--shadow)}
[data-theme="light"] .nav-link{color:var(--text-muted)}
[data-theme="light"] .nav-link:hover,[data-theme="light"] .nav-link.active{background:var(--bg2);color:var(--text)}
[data-theme="light"] .nav-brand{color:var(--gold)}
[data-theme="light"] .nav-name{color:var(--text)}
[data-theme="light"] .badge-admin{background:rgba(26,92,128,.18);color:var(--gold)}
[data-theme="light"] .badge-mhs{background:rgba(26,107,74,.18);color:var(--green)}
[data-theme="light"] th{background:var(--bg2)!important;color:var(--text-muted)!important;border-bottom-color:var(--border)!important}
[data-theme="light"] td{border-bottom-color:rgba(154,184,204,.4)!important;color:var(--text)!important}
[data-theme="light"] tr:hover td{background:rgba(26,92,128,.06)!important}
[data-theme="light"] .table-wrap,[data-theme="light"] .card,[data-theme="light"] .stat-card,[data-theme="light"] .profile-card,[data-theme="light"] .membership-card,[data-theme="light"] .info-card,[data-theme="light"] .pass-card,[data-theme="light"] .edit-card,[data-theme="light"] .form-card,[data-theme="light"] .section-card,[data-theme="light"] .modal-box{background:var(--card)!important;border-color:var(--border)!important;box-shadow:0 2px 10px var(--shadow)}
[data-theme="light"] .field input,[data-theme="light"] .field select,[data-theme="light"] .modal-field input,[data-theme="light"] .pinjam-date,[data-theme="light"] .search-input,[data-theme="light"] .search-row input,[data-theme="light"] .filter-bar input,[data-theme="light"] .filter-bar select{background:var(--input-bg)!important;border-color:var(--border)!important;color:var(--text)!important}
[data-theme="light"] .field input:focus,[data-theme="light"] .field select:focus{border-color:var(--gold)!important}
[data-theme="light"] .filter-tab,[data-theme="light"] .page-btn,[data-theme="light"] .btn-reset,[data-theme="light"] .btn-cancel{background:var(--card)!important;border-color:var(--border)!important;color:var(--text-muted)!important}
[data-theme="light"] .filter-tab.active,[data-theme="light"] .filter-tab:hover{background:rgba(26,92,128,.12)!important;border-color:var(--gold)!important;color:var(--gold)!important}
[data-theme="light"] .page-btn.active{background:var(--gold)!important;color:#fff!important;border-color:var(--gold)!important}
[data-theme="light"] .page-btn:hover{border-color:var(--gold)!important;color:var(--gold)!important}
[data-theme="light"] .quick-link{background:var(--card)!important;border-color:var(--border)!important;color:var(--text)!important}
[data-theme="light"] .quick-link:hover{border-color:var(--gold)!important}
[data-theme="light"] .stat-mini{background:var(--bg2)!important}
[data-theme="light"] .alert-success{background:rgba(26,107,74,.12)!important;border-color:rgba(26,107,74,.3)!important;color:var(--green)!important}
[data-theme="light"] .alert-error{background:rgba(139,32,32,.1)!important;border-color:rgba(139,32,32,.28)!important;color:var(--error)!important}
[data-theme="light"] .stat-label,[data-theme="light"] .page-sub,[data-theme="light"] .count-label,[data-theme="light"] .count-info,[data-theme="light"] .book-author,[data-theme="light"] .book-pub,[data-theme="light"] .keterangan,[data-theme="light"] .ip-cell,[data-theme="light"] .time-cell,[data-theme="light"] .profile-detail{color:var(--text-muted)!important}
[data-theme="light"] .stat-num{color:var(--gold)!important}
[data-theme="light"] .page-title,[data-theme="light"] .modal-title,[data-theme="light"] .section-title,[data-theme="light"] .card-title,[data-theme="light"] .form-title,[data-theme="light"] .book-title,[data-theme="light"] .profile-name,[data-theme="light"] .confirm-nama{color:var(--text)!important}
[data-theme="light"] code{color:var(--gold)!important}
[data-theme="light"] .s-dipinjam{background:rgba(26,92,128,.18)!important;color:#0e3d5c!important}
[data-theme="light"] .s-dikembalikan{background:rgba(26,107,74,.18)!important;color:#0e3d28!important}
[data-theme="light"] .s-terlambat{background:rgba(139,32,32,.18)!important;color:#5c0e0e!important}
[data-theme="light"] .kategori-tag{background:rgba(26,92,128,.15)!important;color:var(--gold)!important}
[data-theme="light"] .avail-yes{background:rgba(26,107,74,.15)!important;color:var(--green)!important}
[data-theme="light"] .avail-no{background:rgba(139,32,32,.15)!important;color:var(--error)!important}
[data-theme="light"] .avail-mine{background:rgba(26,92,128,.15)!important;color:var(--gold)!important}
[data-theme="light"] .book-card{background:var(--card)!important;border-color:var(--border)!important}
[data-theme="light"] .book-card:hover{border-color:var(--gold)!important;box-shadow:0 8px 24px var(--shadow)!important}
[data-theme="light"] .already-badge{background:rgba(26,92,128,.1)!important;border-color:rgba(26,92,128,.2)!important;color:var(--gold)!important}
[data-theme="light"] .btn-disabled{background:rgba(74,122,150,.1)!important;color:var(--text-muted)!important;border-color:var(--border)!important}
[data-theme="light"] .profile-nim{color:var(--gold)!important}
[data-theme="light"] .info-row{border-bottom-color:var(--border)!important}
[data-theme="light"] .info-label{color:var(--text-muted)!important}
[data-theme="light"] .info-val{color:var(--text)!important}
[data-theme="light"] .b-aktif{background:rgba(26,107,74,.15)!important;color:#0e3d28!important}
[data-theme="light"] .b-nonaktif{background:rgba(139,32,32,.15)!important;color:#5c0e0e!important}
[data-theme="light"] .b-none{background:rgba(74,122,150,.12)!important;color:var(--text-muted)!important}
[data-theme="light"] .b-anggota{background:rgba(26,107,74,.15)!important;color:#0e3d28!important}
[data-theme="light"] .b-bukan{background:rgba(74,122,150,.12)!important;color:var(--text-muted)!important}
[data-theme="light"] .btn-del{background:rgba(139,32,32,.15)!important;color:var(--error)!important}
[data-theme="light"] .btn-return{background:rgba(26,107,74,.15)!important;color:var(--green)!important}
[data-theme="light"] .btn-pass{background:rgba(26,92,128,.15)!important;color:var(--gold)!important}
[data-theme="light"] .warn-text,[data-theme="light"] .confirm-warn{background:rgba(139,32,32,.07)!important;border-color:rgba(139,32,32,.2)!important;color:var(--error)!important}
[data-theme="light"] .danger-zone{background:rgba(139,32,32,.04)!important;border-color:rgba(139,32,32,.18)!important}
[data-theme="light"] .info-box{background:rgba(26,92,128,.07)!important;border-color:rgba(26,92,128,.2)!important}
[data-theme="light"] .non-member-banner{background:rgba(26,92,128,.08)!important;border-color:rgba(26,92,128,.25)!important}
[data-theme="light"] .ci-grid{background:var(--bg2)!important}
[data-theme="light"] .ci-row{border-bottom-color:var(--border)!important}
[data-theme="light"] .denda-warning{background:rgba(139,32,32,.08)!important;border-color:rgba(139,32,32,.22)!important;color:var(--error)!important}
[data-theme="light"] .no-denda{background:rgba(26,107,74,.08)!important;border-color:rgba(26,107,74,.22)!important;color:var(--green)!important}
[data-theme="light"] .rb-admin{background:rgba(26,92,128,.15)!important;color:var(--gold)!important}
[data-theme="light"] .rb-mahasiswa{background:rgba(26,107,74,.15)!important;color:var(--green)!important}
[data-theme="light"] .aksi-badge{background:rgba(74,122,150,.12)!important;color:var(--text-muted)!important}
[data-theme="light"] .you-badge{background:rgba(26,92,128,.18)!important;color:var(--gold)!important}
[data-theme="light"] .expired{color:var(--error)!important}
[data-theme="light"] .denda{color:var(--error)!important}
[data-theme="light"] .hint-box{background:rgba(26,92,128,.06)!important;border-color:var(--border)!important;color:var(--text-muted)!important}
[data-theme="light"] .hint-box span{color:var(--gold)!important}
[data-theme="light"] .tab-switcher{background:var(--input-bg)!important;border-color:var(--border)!important}
[data-theme="light"] .tab-btn{color:var(--text-muted)!important}
[data-theme="light"] .register-link{color:var(--text-muted)!important}
[data-theme="light"] .divider{background:var(--border)!important}
[data-theme="light"] .stat-mini-num{color:var(--gold)!important}
[data-theme="light"] .modal{background:var(--overlay)!important}
[data-theme="light"] ::-webkit-scrollbar-track{background:var(--bg2)}
[data-theme="light"] ::-webkit-scrollbar-thumb{background:var(--border)}
[data-theme="light"] ::-webkit-scrollbar-thumb:hover{background:var(--gold)}
</style>
<script>(function(){var t=localStorage.getItem("perp_theme")||"dark";document.documentElement.setAttribute("data-theme",t)})()</script>';
    return str_replace('</head>', $css.'</head>', $buffer);
});


// ── Auth helpers ─────────────────────────────────────────────
function isLoggedIn()    { return isset($_SESSION['user_id']); }
function isAdmin()       { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isMahasiswa()   { return isset($_SESSION['role']) && $_SESSION['role'] === 'mahasiswa'; }

function redirect($url) {
    header("Location: $url");
    exit();
}

function requireLogin() {
    if (!isLoggedIn()) redirect('/login.php');
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) redirect('/user/dashboard.php');
}

function requireMahasiswa() {
    requireLogin();
    if (!isMahasiswa()) redirect('/admin/dashboard.php');
}
?>