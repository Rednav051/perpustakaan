<?php
require_once '../includes/config.php';
requireAdmin();

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Tambah buku ──────────────────────────────────────────────
    if ($action === 'tambah') {
        $id        = trim($_POST['id_buku']);
        $judul     = trim($_POST['judul']);
        $pengarang = trim($_POST['pengarang']);
        $penerbit  = trim($_POST['penerbit']);
        $tahun     = $_POST['tahun_terbit'];
        $kategori  = trim($_POST['kategori']);
        $stok      = (int)$_POST['stok'];

        if (empty($id) || empty($judul)) {
            $error = 'ID Buku dan Judul wajib diisi.';
        } else {
            $stmt = $conn->prepare("INSERT INTO buku (id_buku, judul, pengarang, penerbit, tahun_terbit, kategori, stok) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssi", $id, $judul, $pengarang, $penerbit, $tahun, $kategori, $stok);
            if ($stmt->execute()) $msg = "Buku \"$judul\" berhasil ditambahkan.";
            else $error = 'Gagal: ' . $conn->error;
        }
    }

    // ── Edit buku ────────────────────────────────────────────────
    if ($action === 'edit') {
        $id        = trim($_POST['id_buku']);
        $judul     = trim($_POST['judul']);
        $pengarang = trim($_POST['pengarang']);
        $penerbit  = trim($_POST['penerbit']);
        $tahun     = $_POST['tahun_terbit'];
        $kategori  = trim($_POST['kategori']);
        $stok      = (int)$_POST['stok'];

        $stmt = $conn->prepare("UPDATE buku SET judul=?, pengarang=?, penerbit=?, tahun_terbit=?, kategori=?, stok=? WHERE id_buku=?");
        $stmt->bind_param("sssssis", $judul, $pengarang, $penerbit, $tahun, $kategori, $stok, $id);
        if ($stmt->execute()) $msg = "Buku \"$judul\" berhasil diperbarui.";
        else $error = 'Gagal memperbarui: ' . $conn->error;
    }

    // ── Hapus buku ───────────────────────────────────────────────
    if ($action === 'hapus') {
        $id  = trim($_POST['id_buku']);
        $cek = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_buku='$id' AND status='dipinjam'")->fetch_assoc()['c'];
        if ($cek > 0) {
            $error = 'Tidak bisa menghapus buku yang masih dipinjam.';
        } else {
            $stmt = $conn->prepare("DELETE FROM buku WHERE id_buku = ?");
            $stmt->bind_param("s", $id);
            if ($stmt->execute()) $msg = 'Buku berhasil dihapus.';
            else $error = 'Gagal menghapus: ' . $conn->error;
        }
    }

    // ── Beri peminjaman ──────────────────────────────────────────
    if ($action === 'pinjam') {
        $id_anggota  = (int)$_POST['id_anggota'];
        $id_buku     = trim($_POST['id_buku_pinjam']);
        $tgl_kembali = trim($_POST['tanggal_kembali']);

        // cek stok
        $stok     = $conn->query("SELECT stok FROM buku WHERE id_buku='$id_buku'")->fetch_assoc()['stok'] ?? 0;
        $dipinjam = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_buku='$id_buku' AND status='dipinjam'")->fetch_assoc()['c'];

        if ($stok - $dipinjam <= 0) {
            $error = 'Stok buku tidak tersedia.';
        } elseif (empty($tgl_kembali) || $tgl_kembali <= date('Y-m-d')) {
            $error = 'Tanggal kembali harus lebih dari hari ini.';
        } else {
            // cek apakah anggota sudah meminjam buku yang sama
            $sudah = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_anggota=$id_anggota AND id_buku='$id_buku' AND status='dipinjam'")->fetch_assoc()['c'];
            if ($sudah > 0) {
                $error = 'Anggota ini sudah meminjam buku tersebut dan belum dikembalikan.';
            } else {
                $stmt = $conn->prepare("INSERT INTO peminjaman (id_anggota, id_buku, tanggal_kembali) VALUES (?,?,?)");
                $stmt->bind_param("iss", $id_anggota, $id_buku, $tgl_kembali);
                if ($stmt->execute()) {
                    $judul_pinjam = $conn->query("SELECT judul FROM buku WHERE id_buku='$id_buku'")->fetch_assoc()['judul'];
                    $nama_anggota = $conn->query("SELECT nama FROM anggota WHERE id=$id_anggota")->fetch_assoc()['nama'];
                    $msg = "Peminjaman berhasil! \"$judul_pinjam\" dipinjam oleh $nama_anggota.";
                } else {
                    $error = 'Gagal menyimpan peminjaman: ' . $conn->error;
                }
            }
        }
    }

    // ── Kembalikan buku ──────────────────────────────────────────
    if ($action === 'kembalikan') {
        $id_pinjam = (int)$_POST['id_pinjam'];
        $today     = date('Y-m-d');
        $pinjam    = $conn->query("SELECT * FROM peminjaman WHERE id_pinjam=$id_pinjam")->fetch_assoc();
        $denda     = 0;

        if ($pinjam && $today > $pinjam['tanggal_kembali']) {
            $diff  = (strtotime($today) - strtotime($pinjam['tanggal_kembali'])) / 86400;
            $denda = $diff * 1000; // Rp 1.000/hari
        }

        $stmt = $conn->prepare("UPDATE peminjaman SET status='dikembalikan', tanggal_dikembalikan=?, denda=? WHERE id_pinjam=?");
        $stmt->bind_param("sdi", $today, $denda, $id_pinjam);
        if ($stmt->execute()) {
            $msg = 'Buku berhasil dikembalikan.' . ($denda > 0 ? ' Denda: Rp ' . number_format($denda, 0, ',', '.') : ' Tidak ada denda.');
        } else {
            $error = 'Gagal memproses pengembalian.';
        }
    }
}

// ── Data buku ────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$tab    = $_GET['tab']    ?? 'buku'; // buku | peminjaman

$buku_query = "SELECT b.*,
    (b.stok - COALESCE((SELECT COUNT(*) FROM peminjaman p WHERE p.id_buku=b.id_buku AND p.status='dipinjam'),0)) AS tersedia,
    (SELECT COUNT(*) FROM peminjaman p WHERE p.id_buku=b.id_buku AND p.status='dipinjam') AS sedang_dipinjam
    FROM buku b";
if ($search) {
    $s = $conn->real_escape_string($search);
    $buku_query .= " WHERE b.judul LIKE '%$s%' OR b.pengarang LIKE '%$s%' OR b.id_buku LIKE '%$s%'";
}
$buku_query .= " ORDER BY b.created_at DESC";
$buku_list  = $conn->query($buku_query);

// ── Data peminjaman (aktif + riwayat berdasarkan filter tab) ─────
$filter_pinjam = $_GET['filter'] ?? 'dipinjam';
$where_pinjam  = '';
if ($filter_pinjam === 'dipinjam')    $where_pinjam = "WHERE p.status='dipinjam'";
elseif ($filter_pinjam === 'terlambat') $where_pinjam = "WHERE p.status='dipinjam' AND p.tanggal_kembali < CURDATE()";
elseif ($filter_pinjam === 'dikembalikan') $where_pinjam = "WHERE p.status='dikembalikan'";

$peminjaman_list = $conn->query("
    SELECT p.*, a.nama AS nama_anggota, b.judul, b.id_buku
    FROM peminjaman p
    JOIN anggota a ON p.id_anggota = a.id
    JOIN buku b ON p.id_buku = b.id_buku
    $where_pinjam
    ORDER BY p.id_pinjam DESC
");

// ── Untuk dropdown modal pinjam ──────────────────────────────────
$anggota_list = $conn->query("SELECT id, nama FROM anggota WHERE status='aktif' ORDER BY nama");
$buku_pinjam  = $conn->query("
    SELECT id_buku, judul,
    (stok - COALESCE((SELECT COUNT(*) FROM peminjaman p WHERE p.id_buku=buku.id_buku AND p.status='dipinjam'),0)) AS tersedia
    FROM buku ORDER BY judul
");

// ── Stats ────────────────────────────────────────────────────────
$stat_buku     = $conn->query("SELECT COUNT(*) as c FROM buku")->fetch_assoc()['c'];
$stat_pinjam   = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE status='dipinjam'")->fetch_assoc()['c'];
$stat_terlambat= $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE status='dipinjam' AND tanggal_kembali < CURDATE()")->fetch_assoc()['c'];
$stat_kembali  = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE status='dikembalikan'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Buku & Peminjaman — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #1a1a14; --card: #242418; --border: #3a3a2a;
            --gold: #c9a84c; --gold-light: #e8c97a;
            --green: #4caf82; --green-dark: #3a9168;
            --text: #e8e4d8; --text-muted: #8a8672;
            --input-bg: #1e1e18; --error: #e07070;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 1300px; margin: 0 auto; }

        /* Page header */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-title  { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); }

        /* Stats */
        .stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 24px; }
        .stat-card  { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 16px 18px; }
        .stat-num   { font-size: 26px; font-weight: 700; font-family: 'Playfair Display', serif; color: var(--gold); }
        .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 3px; }

        /* Alert */
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: rgba(76,175,130,0.12); border: 1px solid rgba(76,175,130,0.35); color: var(--green); }
        .alert-error   { background: rgba(224,112,112,0.12); border: 1px solid rgba(224,112,112,0.35); color: var(--error); }

        /* Tab switcher */
        .tab-bar { display: flex; gap: 4px; margin-bottom: 20px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 10px; padding: 4px; width: fit-content; }
        .tab-btn { padding: 9px 20px; border: none; border-radius: 7px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; color: var(--text-muted); background: transparent; transition: all 0.2s; display: flex; align-items: center; gap: 7px; }
        .tab-btn.active { background: var(--card); color: var(--text); box-shadow: 0 1px 4px rgba(0,0,0,0.3); }
        .tab-btn.active.tab-buku    { color: var(--gold); }
        .tab-btn.active.tab-pinjam  { color: var(--green); }
        .badge-count { background: var(--green); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px; }
        .badge-red   { background: var(--error); }

        /* Toolbar row */
        .toolbar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; align-items: center; }
        .toolbar input  { flex: 1; min-width: 200px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 9px 14px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        .toolbar input:focus { border-color: var(--gold); }

        /* Buttons */
        .btn         { padding: 9px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.15s; }
        .btn-green   { background: var(--green); color: #fff; }
        .btn-green:hover { background: var(--green-dark); }
        .btn-gold    { background: var(--gold); color: #1a1a14; }
        .btn-gold:hover { background: var(--gold-light); }
        .btn-ghost   { background: var(--card); border: 1px solid var(--border); color: var(--text-muted); }
        .btn-sm      { padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; font-family: 'DM Sans', sans-serif; }
        .btn-edit    { background: rgba(201,168,76,0.2); color: var(--gold); }
        .btn-del     { background: rgba(224,112,112,0.2); color: var(--error); }
        .btn-return  { background: rgba(76,175,130,0.2); color: var(--green); }

        /* Filter tabs for peminjaman */
        .filter-tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .filter-tab  { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; color: var(--text-muted); border: 1px solid var(--border); transition: all 0.2s; }
        .filter-tab:hover, .filter-tab.active { border-color: var(--gold); color: var(--gold); background: rgba(201,168,76,0.08); }
        .filter-tab.f-terlambat.active { border-color: var(--error); color: var(--error); background: rgba(224,112,112,0.08); }
        .filter-tab.f-kembali.active   { border-color: var(--green); color: var(--green); background: rgba(76,175,130,0.08); }

        /* Table */
        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e1e18; padding: 11px 14px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 11px 14px; font-size: 13px; border-bottom: 1px solid rgba(58,58,42,0.5); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.015); }

        /* Badges */
        .badge  { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .b-green { background: rgba(76,175,130,0.18); color: var(--green); }
        .b-gold  { background: rgba(201,168,76,0.18); color: var(--gold); }
        .b-red   { background: rgba(224,112,112,0.18); color: var(--error); }
        .b-grey  { background: rgba(138,134,114,0.15); color: var(--text-muted); }

        .stok-ok   { color: var(--green); font-weight: 700; }
        .stok-warn { color: var(--gold);  font-weight: 700; }
        .stok-zero { color: var(--error); font-weight: 700; }

        .denda-val { color: var(--error); font-weight: 600; }

        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.72); z-index: 200; align-items: center; justify-content: center; padding: 20px; }
        .modal.open { display: flex; }
        .modal-box { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; }
        .modal-box.wide { max-width: 560px; }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--gold); margin-bottom: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        .field input, .field select { width: 100%; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        .field input:focus, .field select:focus { border-color: var(--green); }
        .field input::placeholder { color: var(--text-muted); }
        .field select option { background: var(--card); }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { padding: 10px 18px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 8px; cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 14px; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* Konfirmasi kembalikan */
        .confirm-info { background: var(--input-bg); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; font-size: 13px; }
        .confirm-info .ci-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid rgba(58,58,42,0.4); }
        .confirm-info .ci-row:last-child { border-bottom: none; }
        .confirm-info .ci-label { color: var(--text-muted); }
        .confirm-info .ci-val   { color: var(--text); font-weight: 600; }
        .denda-box { background: rgba(224,112,112,0.08); border: 1px solid rgba(224,112,112,0.25); border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 13px; color: var(--error); }
        .denda-box strong { font-size: 16px; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">

    <div class="page-header">
        <div class="page-title">📚 Buku & Peminjaman</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button class="btn btn-green" onclick="openModal('modal-pinjam')">📤 Beri Pinjaman</button>
            <button class="btn btn-gold"  onclick="openModal('modal-tambah')">➕ Tambah Buku</button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-num"><?= $stat_buku ?></div>
            <div class="stat-label">Total Judul Buku</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:var(--gold)"><?= $stat_pinjam ?></div>
            <div class="stat-label">Sedang Dipinjam</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:<?= $stat_terlambat>0?'var(--error)':'var(--gold)' ?>"><?= $stat_terlambat ?></div>
            <div class="stat-label">Terlambat Dikembalikan</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color:var(--green)"><?= $stat_kembali ?></div>
            <div class="stat-label">Total Dikembalikan</div>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Tab switcher -->
    <div class="tab-bar">
        <button class="tab-btn tab-buku  <?= $tab==='buku'?'active':'' ?>"  onclick="switchTab('buku')">📚 Data Buku</button>
        <button class="tab-btn tab-pinjam <?= $tab!=='buku'?'active':'' ?>" onclick="switchTab('peminjaman')">
            📋 Peminjaman
            <?php if ($stat_pinjam > 0): ?>
                <span class="badge-count <?= $stat_terlambat>0?'badge-red':'' ?>"><?= $stat_pinjam ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ══════════════════════════════════════════
         TAB: DATA BUKU
    ═══════════════════════════════════════════ -->
    <div id="tab-buku" class="tab-content <?= $tab==='buku'?'active':'' ?>">
        <form class="toolbar" method="GET">
            <input type="hidden" name="tab" value="buku">
            <input type="text" name="search" placeholder="Cari judul, pengarang, atau ID buku..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-gold">Cari</button>
            <?php if ($search): ?><a href="?tab=buku" class="btn btn-ghost">Reset</a><?php endif; ?>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID Buku</th><th>Judul</th><th>Pengarang</th><th>Penerbit</th>
                        <th>Tahun</th><th>Kategori</th><th>Stok</th><th>Tersedia</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($b = $buku_list->fetch_assoc()):
                        $sc = $b['stok']==0 ? 'stok-zero' : ($b['tersedia']<=1 ? 'stok-warn' : 'stok-ok');
                    ?>
                    <tr>
                        <td><code style="color:var(--gold);font-size:12px"><?= htmlspecialchars($b['id_buku']) ?></code></td>
                        <td><strong><?= htmlspecialchars($b['judul']) ?></strong></td>
                        <td style="color:var(--text-muted)"><?= htmlspecialchars($b['pengarang']) ?></td>
                        <td style="color:var(--text-muted)"><?= htmlspecialchars($b['penerbit']) ?></td>
                        <td><?= $b['tahun_terbit'] ?></td>
                        <td><span class="badge b-gold"><?= htmlspecialchars($b['kategori']) ?></span></td>
                        <td><span class="<?= $sc ?>"><?= $b['stok'] ?></span></td>
                        <td>
                            <span class="badge <?= $b['tersedia']>0?'b-green':'b-red' ?>">
                                <?= $b['tersedia'] > 0 ? "✓ {$b['tersedia']}" : "✗ Habis" ?>
                            </span>
                            <?php if ($b['sedang_dipinjam']>0): ?>
                            <span style="font-size:11px;color:var(--text-muted);display:block;margin-top:3px"><?= $b['sedang_dipinjam'] ?> dipinjam</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <button class="btn-sm btn-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($b)) ?>)">✏️ Edit</button>
                                <form method="POST" onsubmit="return confirm('Hapus buku ini?')">
                                    <input type="hidden" name="action"  value="hapus">
                                    <input type="hidden" name="id_buku" value="<?= $b['id_buku'] ?>">
                                    <button type="submit" class="btn-sm btn-del">🗑️ Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /tab-buku -->

    <!-- ══════════════════════════════════════════
         TAB: PEMINJAMAN
    ═══════════════════════════════════════════ -->
    <div id="tab-peminjaman" class="tab-content <?= $tab!=='buku'?'active':'' ?>">

        <div class="filter-tabs">
            <a href="?tab=peminjaman&filter=dipinjam"     class="filter-tab <?= $filter_pinjam==='dipinjam'?'active':'' ?>">📤 Dipinjam (<?= $stat_pinjam ?>)</a>
            <a href="?tab=peminjaman&filter=terlambat"    class="filter-tab f-terlambat <?= $filter_pinjam==='terlambat'?'active':'' ?>">⚠️ Terlambat (<?= $stat_terlambat ?>)</a>
            <a href="?tab=peminjaman&filter=dikembalikan" class="filter-tab f-kembali <?= $filter_pinjam==='dikembalikan'?'active':'' ?>">✅ Dikembalikan</a>
            <a href="?tab=peminjaman&filter=semua"        class="filter-tab <?= $filter_pinjam==='semua'?'active':'' ?>">📋 Semua</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Anggota</th><th>Buku</th><th>Tgl Pinjam</th>
                        <th>Tgl Kembali</th><th>Dikembalikan</th><th>Status</th><th>Denda</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = $peminjaman_list->fetch_assoc()):
                        $late   = ($r['status']==='dipinjam' && $r['tanggal_kembali'] < date('Y-m-d'));
                        $sc     = $late ? 'b-red' : ($r['status']==='dikembalikan' ? 'b-green' : 'b-gold');
                        $label  = $late ? 'Terlambat' : ucfirst($r['status']);
                    ?>
                    <tr>
                        <td><?= $r['id_pinjam'] ?></td>
                        <td><strong><?= htmlspecialchars($r['nama_anggota']) ?></strong></td>
                        <td>
                            <span style="font-size:11px;color:var(--gold);font-family:monospace"><?= $r['id_buku'] ?></span><br>
                            <?= htmlspecialchars($r['judul']) ?>
                        </td>
                        <td><?= $r['tanggal_pinjam'] ?></td>
                        <td><?= $r['tanggal_kembali'] ?>
                            <?php if ($late): ?>
                            <span style="display:block;font-size:11px;color:var(--error)">
                                <?= ceil((strtotime(date('Y-m-d'))-strtotime($r['tanggal_kembali']))/86400) ?> hari terlambat
                            </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['tanggal_dikembalikan'] ?? '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td><span class="badge <?= $sc ?>"><?= $label ?></span></td>
                        <td>
                            <?php if ($r['denda'] > 0): ?>
                                <span class="denda-val">Rp <?= number_format($r['denda'],0,',','.') ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['status']==='dipinjam'): ?>
                            <button class="btn-sm btn-return"
                                onclick="openKembalikan(<?= htmlspecialchars(json_encode([
                                    'id_pinjam'      => $r['id_pinjam'],
                                    'nama'           => $r['nama_anggota'],
                                    'judul'          => $r['judul'],
                                    'tgl_pinjam'     => $r['tanggal_pinjam'],
                                    'tgl_kembali'    => $r['tanggal_kembali'],
                                    'terlambat'      => $late,
                                    'hari_terlambat' => $late ? ceil((strtotime(date('Y-m-d'))-strtotime($r['tanggal_kembali']))/86400) : 0
                                ])) ?>)">
                                ↩️ Kembalikan
                            </button>
                            <?php else: ?>
                            <span style="color:var(--text-muted);font-size:12px">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div><!-- /tab-peminjaman -->

</div><!-- /main -->

<!-- ═══════════════════════════════════════════════════
     MODAL: TAMBAH BUKU
════════════════════════════════════════════════════ -->
<div id="modal-tambah" class="modal">
    <div class="modal-box">
        <div class="modal-title">➕ Tambah Buku Baru</div>
        <form method="POST">
            <input type="hidden" name="action" value="tambah">
            <div class="row2">
                <div class="field"><label>ID Buku *</label><input name="id_buku" placeholder="BK-004" required></div>
                <div class="field"><label>Tahun Terbit</label><input name="tahun_terbit" type="number" placeholder="<?= date('Y') ?>" min="1900" max="<?= date('Y') ?>"></div>
            </div>
            <div class="field"><label>Judul *</label><input name="judul" placeholder="Judul buku" required></div>
            <div class="row2">
                <div class="field"><label>Pengarang</label><input name="pengarang" placeholder="Nama pengarang"></div>
                <div class="field"><label>Penerbit</label><input name="penerbit" placeholder="Nama penerbit"></div>
            </div>
            <div class="row2">
                <div class="field">
                    <label>Kategori</label>
                    <input name="kategori" placeholder="Teknologi" list="kat-list">
                    <datalist id="kat-list">
                        <?php
                        $conn->query("SELECT DISTINCT kategori FROM buku ORDER BY kategori")->data_seek(0);
                        $kats2 = $conn->query("SELECT DISTINCT kategori FROM buku ORDER BY kategori");
                        while ($k = $kats2->fetch_assoc()) echo "<option value=\"{$k['kategori']}\">";
                        ?>
                    </datalist>
                </div>
                <div class="field"><label>Stok Awal</label><input name="stok" type="number" value="1" min="0" max="999"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-tambah')">Batal</button>
                <button type="submit" class="btn btn-green">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     MODAL: EDIT BUKU
════════════════════════════════════════════════════ -->
<div id="modal-edit" class="modal">
    <div class="modal-box">
        <div class="modal-title">✏️ Edit Buku</div>
        <form method="POST">
            <input type="hidden" name="action"  value="edit">
            <input type="hidden" name="id_buku" id="edit_id">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:16px">
                ID: <code id="edit_id_show" style="color:var(--gold)"></code>
            </div>
            <div class="field"><label>Judul</label><input name="judul" id="edit_judul"></div>
            <div class="row2">
                <div class="field"><label>Pengarang</label><input name="pengarang" id="edit_pengarang"></div>
                <div class="field"><label>Penerbit</label><input name="penerbit" id="edit_penerbit"></div>
            </div>
            <div class="row2">
                <div class="field"><label>Tahun</label><input name="tahun_terbit" id="edit_tahun" type="number"></div>
                <div class="field"><label>Kategori</label><input name="kategori" id="edit_kategori" list="kat-list"></div>
            </div>
            <div class="field"><label>Stok</label><input name="stok" id="edit_stok" type="number" min="0"></div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-edit')">Batal</button>
                <button type="submit" class="btn btn-green">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     MODAL: BERI PINJAMAN
════════════════════════════════════════════════════ -->
<div id="modal-pinjam" class="modal">
    <div class="modal-box wide">
        <div class="modal-title">📤 Beri Pinjaman Buku</div>
        <form method="POST">
            <input type="hidden" name="action" value="pinjam">

            <div class="field">
                <label>Anggota *</label>
                <select name="id_anggota" required>
                    <option value="">— Pilih Anggota Aktif —</option>
                    <?php while ($a = $anggota_list->fetch_assoc()): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nama']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="field">
                <label>Buku *</label>
                <select name="id_buku_pinjam" required onchange="updateStokInfo(this)">
                    <option value="">— Pilih Buku —</option>
                    <?php while ($bp = $buku_pinjam->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($bp['id_buku']) ?>"
                            data-tersedia="<?= $bp['tersedia'] ?>"
                            <?= $bp['tersedia'] <= 0 ? 'style="color:#888"' : '' ?>>
                        [<?= $bp['id_buku'] ?>] <?= htmlspecialchars($bp['judul']) ?>
                        — Tersedia: <?= $bp['tersedia'] ?>
                    </option>
                    <?php endwhile; ?>
                </select>
                <div id="stok-info" style="margin-top:6px;font-size:12px;min-height:18px;color:var(--text-muted)"></div>
            </div>

            <div class="field">
                <label>Tanggal Kembali *</label>
                <input type="date" name="tanggal_kembali"
                       value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                       max="<?= date('Y-m-d', strtotime('+60 days')) ?>"
                       required>
                <div style="font-size:11px;color:var(--text-muted);margin-top:5px">Maksimal 60 hari dari sekarang</div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-pinjam')">Batal</button>
                <button type="submit" class="btn btn-green">✅ Proses Peminjaman</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     MODAL: KEMBALIKAN BUKU
════════════════════════════════════════════════════ -->
<div id="modal-kembalikan" class="modal">
    <div class="modal-box" style="max-width:420px">
        <div class="modal-title">↩️ Kembalikan Buku</div>

        <div class="confirm-info">
            <div class="ci-row"><span class="ci-label">Anggota</span>    <span class="ci-val" id="k_nama"></span></div>
            <div class="ci-row"><span class="ci-label">Buku</span>       <span class="ci-val" id="k_judul"></span></div>
            <div class="ci-row"><span class="ci-label">Tgl Pinjam</span> <span class="ci-val" id="k_tgl_pinjam"></span></div>
            <div class="ci-row"><span class="ci-label">Batas Kembali</span><span class="ci-val" id="k_tgl_kembali"></span></div>
            <div class="ci-row"><span class="ci-label">Dikembalikan</span><span class="ci-val" style="color:var(--green)"><?= date('d M Y') ?></span></div>
        </div>

        <div id="k_denda_box" class="denda-box" style="display:none">
            ⚠️ Buku terlambat <strong id="k_hari_terlambat"></strong> hari.<br>
            Denda: <strong id="k_nominal_denda"></strong> (Rp 1.000/hari)
        </div>

        <form method="POST">
            <input type="hidden" name="action"    value="kembalikan">
            <input type="hidden" name="id_pinjam" id="k_id_pinjam">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-kembalikan')">Batal</button>
                <button type="submit" class="btn btn-green">✅ Konfirmasi Pengembalian</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Modal helpers ─────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── Tab switcher ──────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    document.querySelector('.tab-btn.tab-' + (tab === 'buku' ? 'buku' : 'pinjam')).classList.add('active');
}

// ── Edit modal ────────────────────────────────────────────────
function openEdit(b) {
    document.getElementById('edit_id').value        = b.id_buku;
    document.getElementById('edit_id_show').textContent = b.id_buku;
    document.getElementById('edit_judul').value     = b.judul       || '';
    document.getElementById('edit_pengarang').value = b.pengarang   || '';
    document.getElementById('edit_penerbit').value  = b.penerbit    || '';
    document.getElementById('edit_tahun').value     = b.tahun_terbit|| '';
    document.getElementById('edit_kategori').value  = b.kategori    || '';
    document.getElementById('edit_stok').value      = b.stok;
    openModal('modal-edit');
}

// ── Stok info saat pilih buku di modal pinjam ─────────────────
function updateStokInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    const el  = document.getElementById('stok-info');
    if (!opt.value) { el.innerHTML = ''; return; }
    const tersedia = parseInt(opt.dataset.tersedia);
    if (tersedia <= 0) {
        el.innerHTML = '<span style="color:var(--error)">⚠️ Stok habis — tidak bisa dipinjam</span>';
    } else {
        el.innerHTML = `<span style="color:var(--green)">✓ ${tersedia} eksemplar tersedia</span>`;
    }
}

// ── Modal kembalikan ──────────────────────────────────────────
function openKembalikan(data) {
    document.getElementById('k_id_pinjam').value       = data.id_pinjam;
    document.getElementById('k_nama').textContent      = data.nama;
    document.getElementById('k_judul').textContent     = data.judul;
    document.getElementById('k_tgl_pinjam').textContent= data.tgl_pinjam;
    document.getElementById('k_tgl_kembali').textContent = data.tgl_kembali;

    const dendaBox = document.getElementById('k_denda_box');
    if (data.terlambat && data.hari_terlambat > 0) {
        const nominal = data.hari_terlambat * 1000;
        document.getElementById('k_hari_terlambat').textContent  = data.hari_terlambat;
        document.getElementById('k_nominal_denda').textContent   = 'Rp ' + nominal.toLocaleString('id-ID');
        dendaBox.style.display = 'block';
    } else {
        dendaBox.style.display = 'none';
    }
    openModal('modal-kembalikan');
}
</script>
</body>
</html>