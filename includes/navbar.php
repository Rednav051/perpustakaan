<?php
// includes/navbar.php — with dark/light theme toggle
$role    = $_SESSION['role'] ?? '';
$name    = $_SESSION['user_name'] ?? '';
$base    = '';
$current = basename($_SERVER['PHP_SELF']);

function isActive($file) {
    global $current;
    return basename($file) === $current ? ' active' : '';
}
?>
<nav class="navbar">
    <a href="<?= $base ?>/<?= $role === 'admin' ? 'admin' : 'user' ?>/dashboard.php" class="nav-brand">
        <span>📚</span>
        <span class="brand-text">Perpustakaan</span>
    </a>

    <!-- Hamburger untuk mobile -->
    <button class="hamburger" id="hamburger" onclick="toggleMenu()" aria-label="Toggle menu">
        <span></span><span></span><span></span>
    </button>

    <div class="nav-links" id="nav-links">
        <?php if ($role === 'admin'): ?>
            <a href="<?= $base ?>/admin/dashboard.php"    class="nav-link<?= isActive('dashboard.php') ?>">Dashboard</a>
            <a href="<?= $base ?>/admin/buku.php"         class="nav-link<?= isActive('buku.php') ?>">Buku</a>
            <a href="<?= $base ?>/admin/anggota.php"      class="nav-link<?= isActive('anggota.php') ?>">Anggota</a>
            <a href="<?= $base ?>/admin/peminjaman.php"   class="nav-link<?= isActive('peminjaman.php') ?>">Peminjaman</a>
            <a href="<?= $base ?>/admin/mahasiswa.php"    class="nav-link<?= isActive('mahasiswa.php') ?>">Mahasiswa</a>
            <a href="<?= $base ?>/admin/kelola_admin.php" class="nav-link<?= isActive('kelola_admin.php') ?>">🛡️ Admin</a>
            <a href="<?= $base ?>/admin/settings.php"     class="nav-link<?= isActive('settings.php') ?>">⚙️ Pengaturan</a>
            <a href="<?= $base ?>/admin/log_aktivitas.php" class="nav-link<?= isActive('log_aktivitas.php') ?>">📜 Log</a>
        <?php else: ?>
            <a href="<?= $base ?>/user/dashboard.php"    class="nav-link<?= isActive('dashboard.php') ?>">Dashboard</a>
            <a href="<?= $base ?>/user/katalog.php"      class="nav-link<?= isActive('katalog.php') ?>">Katalog Buku</a>
            <a href="<?= $base ?>/user/peminjaman.php"   class="nav-link<?= isActive('peminjaman.php') ?>">Peminjaman Saya</a>
            <a href="<?= $base ?>/user/profil.php"       class="nav-link<?= isActive('profil.php') ?>">Profil</a>
        <?php endif; ?>
    </div>

    <div class="nav-user" id="nav-user">
        <!-- Tombol Dark/Light Mode -->
        <button class="theme-toggle" onclick="toggleTheme()" title="Ganti tema">
            <span class="t-dk">🌙</span>
            <span class="t-lt">☀️</span>
        </button>

        <span class="badge-role <?= $role === 'admin' ? 'badge-admin' : 'badge-mhs' ?>">
            <?= $role === 'admin' ? '🛡️ Admin' : '👤 Mahasiswa' ?>
        </span>
        <span class="nav-name"><?= htmlspecialchars($name) ?></span>
        <a href="<?= $base ?>/logout.php" class="btn-logout">Keluar</a>
    </div>
</nav>

<style>
.navbar {
    background: #1e1e18;
    border-bottom: 1px solid #3a3a2a;
    padding: 0 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    height: 60px;
    position: sticky;
    top: 0;
    z-index: 100;
}
.nav-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    color: #c9a84c;
    font-weight: 700;
    min-width: 150px;
    text-decoration: none;
    flex-shrink: 0;
}
.nav-links {
    display: flex;
    gap: 2px;
    flex: 1;
    flex-wrap: wrap;
}
.nav-link {
    color: #8a8672;
    text-decoration: none;
    padding: 6px 11px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    white-space: nowrap;
}
.nav-link:hover { background: #2a2a20; color: #e8e4d8; }
.nav-link.active {
    background: #2a2a20;
    color: #e8e4d8;
    border-bottom: 2px solid #c9a84c;
}
.nav-user {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
    flex-shrink: 0;
}
.nav-name { font-size: 13px; color: #e8e4d8; font-weight: 500; white-space: nowrap; }
.badge-role { font-size: 11px; padding: 3px 8px; border-radius: 20px; font-weight: 600; white-space: nowrap; }
.badge-admin { background: rgba(201,168,76,0.2); color: #c9a84c; }
.badge-mhs   { background: rgba(76,175,130,0.2); color: #4caf82; }
.btn-logout {
    font-size: 12px;
    padding: 6px 12px;
    background: rgba(224,112,112,0.15);
    color: #e07070;
    border: 1px solid rgba(224,112,112,0.3);
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
    white-space: nowrap;
}
.btn-logout:hover { background: rgba(224,112,112,0.25); }

/* Hamburger */
.hamburger {
    display: none;
    flex-direction: column;
    gap: 5px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 6px;
    margin-left: auto;
}
.hamburger span {
    display: block;
    width: 22px;
    height: 2px;
    background: #8a8672;
    border-radius: 2px;
    transition: all 0.3s;
}
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── Responsive ── */
@media (max-width: 900px) {
    .nav-name, .badge-role { display: none; }
}

@media (max-width: 700px) {
    .navbar {
        flex-wrap: wrap;
        height: auto;
        padding: 12px 16px;
        gap: 0;
    }
    .nav-brand { flex: 1; }
    .hamburger { display: flex; }

    .nav-links {
        display: none;
        flex-direction: column;
        width: 100%;
        padding: 8px 0 4px;
        gap: 2px;
        order: 3;
    }
    .nav-links.open { display: flex; }

    .nav-link {
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 14px;
    }

    .nav-user {
        display: none;
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
        padding: 8px 0 12px;
        order: 4;
        gap: 8px;
        border-top: 1px solid #3a3a2a;
        margin-top: 8px;
    }
    .nav-user.open { display: flex; }
    .nav-name, .badge-role { display: inline-flex; }
    .btn-logout { width: 100%; text-align: center; padding: 10px; }
}
</style>

<script>
function toggleMenu() {
    const btn   = document.getElementById('hamburger');
    const links = document.getElementById('nav-links');
    const user  = document.getElementById('nav-user');
    btn.classList.toggle('open');
    links.classList.toggle('open');
    user.classList.toggle('open');
}
// Tutup menu jika klik di luar
document.addEventListener('click', function(e) {
    const nav = document.querySelector('.navbar');
    if (!nav.contains(e.target)) {
        document.getElementById('hamburger').classList.remove('open');
        document.getElementById('nav-links').classList.remove('open');
        document.getElementById('nav-user').classList.remove('open');
    }
});

function toggleTheme() {
    var cur = document.documentElement.getAttribute('data-theme') || 'dark';
    var nxt = cur === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', nxt);
    localStorage.setItem('perp_theme', nxt);
}
</script>