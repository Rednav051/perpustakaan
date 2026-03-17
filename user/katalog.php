<?php
require_once '../includes/config.php';
requireMahasiswa();

$nim = $_SESSION['nim'];
$msg = '';
$error = '';

// --- Handle form peminjaman ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pinjam') {
    $id_buku    = trim($_POST['id_buku']);
    $tgl_kembali = trim($_POST['tanggal_kembali']);

    // 1. Cek apakah mahasiswa adalah anggota aktif
    $anggota = $conn->query("SELECT * FROM anggota WHERE nim='$nim' AND status='aktif'")->fetch_assoc();

    if (!$anggota) {
        $error = 'Anda belum terdaftar sebagai anggota aktif. Hubungi admin untuk mendaftar.';
    } else {
        $id_anggota = $anggota['id'];

        // 2. Cek apakah buku sudah dipinjam oleh anggota ini dan belum dikembalikan
        $sudah = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_anggota=$id_anggota AND id_buku='$id_buku' AND status='dipinjam'")->fetch_assoc()['c'];
        if ($sudah > 0) {
            $error = 'Anda sudah meminjam buku ini dan belum mengembalikannya.';
        } else {
            // 3. Cek stok tersedia
            $stok     = $conn->query("SELECT stok FROM buku WHERE id_buku='$id_buku'")->fetch_assoc()['stok'] ?? 0;
            $dipinjam = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_buku='$id_buku' AND status='dipinjam'")->fetch_assoc()['c'];
            $tersedia = $stok - $dipinjam;

            if ($tersedia <= 0) {
                $error = 'Maaf, stok buku ini sedang habis.';
            } else {
                // 4. Validasi tanggal kembali
                if (empty($tgl_kembali) || $tgl_kembali <= date('Y-m-d')) {
                    $error = 'Tanggal kembali harus lebih dari hari ini.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO peminjaman (id_anggota, id_buku, tanggal_kembali) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $id_anggota, $id_buku, $tgl_kembali);
                    if ($stmt->execute()) {
                        $msg = 'Berhasil! Buku berhasil dipinjam. Harap kembalikan sebelum ' . date('d M Y', strtotime($tgl_kembali)) . '.';
                    } else {
                        $error = 'Gagal meminjam buku: ' . $conn->error;
                    }
                }
            }
        }
    }
}

// --- Cek status anggota mahasiswa ---
$anggota_info = $conn->query("SELECT * FROM anggota WHERE nim='$nim' AND status='aktif'")->fetch_assoc();

// --- Ambil daftar buku yang sedang dipinjam mahasiswa ini ---
$buku_dipinjam = [];
if ($anggota_info) {
    $aid = $anggota_info['id'];
    $res = $conn->query("SELECT id_buku FROM peminjaman WHERE id_anggota=$aid AND status='dipinjam'");
    while ($r = $res->fetch_assoc()) {
        $buku_dipinjam[] = $r['id_buku'];
    }
}

// --- Query katalog buku ---
$search   = $_GET['search']   ?? '';
$kategori = $_GET['kategori'] ?? '';

$query = "SELECT b.*,
    (b.stok - COALESCE((SELECT COUNT(*) FROM peminjaman p WHERE p.id_buku=b.id_buku AND p.status='dipinjam'), 0)) as tersedia
    FROM buku b WHERE 1=1";
if ($search)   $query .= " AND (b.judul LIKE '%" . $conn->real_escape_string($search) . "%' OR b.pengarang LIKE '%" . $conn->real_escape_string($search) . "%')";
if ($kategori) $query .= " AND b.kategori = '" . $conn->real_escape_string($kategori) . "'";
$query .= " ORDER BY b.judul ASC";

$buku_list     = $conn->query($query);
$kategori_list = $conn->query("SELECT DISTINCT kategori FROM buku ORDER BY kategori");

// Default tanggal kembali = 7 hari dari sekarang
$default_kembali = date('Y-m-d', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Buku — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 1200px; margin: 0 auto; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); margin-bottom: 6px; }
        .page-sub   { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }

        /* Alert */
        .alert { padding: 13px 16px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; }
        .alert-success { background: rgba(76,175,130,0.12); border: 1px solid rgba(76,175,130,0.35); color: var(--green); }
        .alert-error   { background: rgba(224,112,112,0.12); border: 1px solid rgba(224,112,112,0.35); color: var(--error); }

        /* Non-member warning */
        .non-member-banner {
            background: rgba(201,168,76,0.08);
            border: 1px solid rgba(201,168,76,0.3);
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .non-member-banner strong { color: var(--gold); }

        /* Filter */
        .filter-row { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
        .filter-row input  { flex: 1; min-width: 200px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        .filter-row input:focus { border-color: var(--gold); }
        .filter-row select { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        .filter-row select option { background: var(--card); }
        .btn-search { padding: 10px 18px; background: var(--gold); color: #1a1a14; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .btn-reset  { padding: 10px 14px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-muted); text-decoration: none; font-size: 14px; }

        /* Book grid */
        .books-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
        .book-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
        }
        .book-card:hover { border-color: var(--gold); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
        .book-id     { font-size: 11px; color: var(--gold); font-family: monospace; margin-bottom: 8px; }
        .book-title  { font-size: 15px; font-weight: 600; margin-bottom: 6px; line-height: 1.4; }
        .book-author { font-size: 13px; color: var(--text-muted); margin-bottom: 3px; }
        .book-pub    { font-size: 12px; color: var(--text-muted); margin-bottom: 12px; }
        .book-footer { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 6px; }
        .kategori-tag  { background: rgba(201,168,76,0.15); color: var(--gold); padding: 3px 8px; border-radius: 5px; font-size: 11px; font-weight: 600; }
        .avail-badge   { padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .avail-yes     { background: rgba(76,175,130,0.2); color: var(--green); }
        .avail-no      { background: rgba(224,112,112,0.2); color: var(--error); }
        .avail-mine    { background: rgba(201,168,76,0.2); color: var(--gold); }

        /* Pinjam section in card */
        .pinjam-section { margin-top: auto; padding-top: 14px; border-top: 1px solid var(--border); }
        .pinjam-label   { font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; display: block; }
        .pinjam-row     { display: flex; gap: 8px; }
        .pinjam-date    { flex: 1; background: var(--input-bg); border: 1px solid var(--border); border-radius: 7px; padding: 8px 10px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; min-width: 0; }
        .pinjam-date:focus { border-color: var(--green); }
        .btn-pinjam     { padding: 8px 14px; background: var(--green); color: #fff; border: none; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; white-space: nowrap; transition: background 0.2s; }
        .btn-pinjam:hover { background: var(--green-dark); }
        .btn-disabled   { padding: 8px 12px; background: rgba(138,134,114,0.12); color: var(--text-muted); border: 1px solid var(--border); border-radius: 7px; font-size: 13px; font-weight: 600; cursor: not-allowed; font-family: 'DM Sans', sans-serif; white-space: nowrap; text-align: center; width: 100%; }
        .already-badge  { font-size: 12px; color: var(--gold); background: rgba(201,168,76,0.1); border: 1px solid rgba(201,168,76,0.2); border-radius: 7px; padding: 9px 12px; text-align: center; }
        .already-badge a { color: var(--gold); font-weight: 700; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">
    <div class="page-title">📚 Katalog Buku</div>
    <div class="page-sub">Temukan buku yang ingin Anda pinjam</div>

    <?php if ($msg): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$anggota_info): ?>
    <div class="non-member-banner">
        ⚠️ <span><strong>Anda belum terdaftar sebagai anggota perpustakaan.</strong> Hubungi admin untuk mendaftar agar bisa meminjam buku.</span>
    </div>
    <?php endif; ?>

    <!-- Filter -->
    <form class="filter-row" method="GET">
        <input type="text" name="search" placeholder="Cari judul atau pengarang..." value="<?= htmlspecialchars($search) ?>">
        <select name="kategori">
            <option value="">Semua Kategori</option>
            <?php while ($k = $kategori_list->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($k['kategori']) ?>" <?= $kategori===$k['kategori']?'selected':'' ?>>
                <?= htmlspecialchars($k['kategori']) ?>
            </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn-search">Cari</button>
        <?php if ($search || $kategori): ?>
        <a href="katalog.php" class="btn-reset">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Grid buku -->
    <div class="books-grid">
        <?php while ($b = $buku_list->fetch_assoc()):
            $sudah_pinjam = in_array($b['id_buku'], $buku_dipinjam);
            $habis        = $b['tersedia'] <= 0 && !$sudah_pinjam;
        ?>
        <div class="book-card">
            <div class="book-id"><?= htmlspecialchars($b['id_buku']) ?></div>
            <div class="book-title"><?= htmlspecialchars($b['judul']) ?></div>
            <div class="book-author">✍️ <?= htmlspecialchars($b['pengarang']) ?></div>
            <div class="book-pub">🏢 <?= htmlspecialchars($b['penerbit']) ?> · <?= $b['tahun_terbit'] ?></div>
            <div class="book-footer">
                <span class="kategori-tag"><?= htmlspecialchars($b['kategori']) ?></span>
                <?php if ($sudah_pinjam): ?>
                    <span class="avail-badge avail-mine">📖 Sedang Kamu Pinjam</span>
                <?php elseif ($b['tersedia'] > 0): ?>
                    <span class="avail-badge avail-yes">✓ Tersedia (<?= $b['tersedia'] ?>)</span>
                <?php else: ?>
                    <span class="avail-badge avail-no">✗ Habis</span>
                <?php endif; ?>
            </div>

            <!-- Tombol pinjam -->
            <div class="pinjam-section">
                <?php if ($sudah_pinjam): ?>
                    <div class="already-badge">
                        📋 Sudah dipinjam — cek <a href="peminjaman.php">Peminjaman Saya</a>
                    </div>

                <?php elseif (!$anggota_info): ?>
                    <div class="btn-disabled">🔒 Daftar anggota dahulu</div>

                <?php elseif ($habis): ?>
                    <div class="btn-disabled">Stok Habis</div>

                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action"  value="pinjam">
                        <input type="hidden" name="id_buku" value="<?= htmlspecialchars($b['id_buku']) ?>">
                        <span class="pinjam-label">Tanggal Kembali</span>
                        <div class="pinjam-row">
                            <input type="date"
                                   class="pinjam-date"
                                   name="tanggal_kembali"
                                   value="<?= $default_kembali ?>"
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                   max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                                   required>
                            <button type="submit" class="btn-pinjam"
                                    onclick="return confirm('Pinjam buku ini?')">
                                Pinjam
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>
</body>
</html>