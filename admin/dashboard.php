<?php
require_once '../includes/config.php';
requireAdmin();

// Stats
$total_buku = $conn->query("SELECT COUNT(*) as c FROM buku")->fetch_assoc()['c'];
$total_anggota = $conn->query("SELECT COUNT(*) as c FROM anggota WHERE status='aktif'")->fetch_assoc()['c'];
$total_pinjam = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE status='dipinjam'")->fetch_assoc()['c'];
$total_terlambat = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE status='terlambat' OR (status='dipinjam' AND tanggal_kembali < CURDATE())")->fetch_assoc()['c'];

// Recent peminjaman
$recent = $conn->query("
    SELECT p.*, a.nama as nama_anggota, b.judul 
    FROM peminjaman p 
    JOIN anggota a ON p.id_anggota = a.id 
    JOIN buku b ON p.id_buku = b.id_buku 
    ORDER BY p.id_pinjam DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 1200px; margin: 0 auto; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 26px; color: var(--gold); margin-bottom: 6px; }
        .page-sub { color: var(--text-muted); font-size: 14px; margin-bottom: 28px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 22px; }
        .stat-icon { font-size: 28px; margin-bottom: 12px; }
        .stat-num { font-size: 32px; font-weight: 700; font-family: 'Playfair Display', serif; color: var(--gold); }
        .stat-label { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text); }
        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--bg2); padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        td { padding: 12px 16px; font-size: 14px; border-bottom: 1px solid var(--border); }
        tr:last-child td { border-bottom: none; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .s-dipinjam { background: rgba(201,168,76,0.2); color: var(--gold); }
        .s-dikembalikan { background: rgba(76,175,130,0.2); color: var(--green); }
        .s-terlambat { background: rgba(224,112,112,0.2); color: #e07070; }
        .quick-links { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 28px; }
        .quick-link { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 16px; text-decoration: none; color: var(--text); transition: all 0.2s; text-align: center; }
        .quick-link:hover { border-color: var(--gold); transform: translateY(-2px); }
        .quick-link-icon { font-size: 24px; display: block; margin-bottom: 8px; }
        .quick-link-text { font-size: 13px; font-weight: 500; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="main">
    <div class="page-title">Dashboard Admin</div>
    <div class="page-sub">Selamat datang, <?= htmlspecialchars($_SESSION['user_name']) ?>! Berikut ringkasan perpustakaan hari ini.</div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📖</div>
            <div class="stat-num"><?= $total_buku ?></div>
            <div class="stat-label">Total Judul Buku</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-num"><?= $total_anggota ?></div>
            <div class="stat-label">Anggota Aktif</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📤</div>
            <div class="stat-num"><?= $total_pinjam ?></div>
            <div class="stat-label">Sedang Dipinjam</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⚠️</div>
            <div class="stat-num" style="color:#e07070"><?= $total_terlambat ?></div>
            <div class="stat-label">Terlambat</div>
        </div>
    </div>

    <div class="quick-links">
        <a href="buku.php" class="quick-link"><span class="quick-link-icon">📚</span><span class="quick-link-text">Kelola Buku</span></a>
        <a href="anggota.php" class="quick-link"><span class="quick-link-icon">👥</span><span class="quick-link-text">Kelola Anggota</span></a>
        <a href="peminjaman.php" class="quick-link"><span class="quick-link-icon">📋</span><span class="quick-link-text">Peminjaman</span></a>
        <a href="mahasiswa.php" class="quick-link"><span class="quick-link-icon">🎓</span><span class="quick-link-text">Data Mahasiswa</span></a>
    </div>

    <div class="section-title">Peminjaman Terbaru</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Anggota</th>
                    <th>Judul Buku</th>
                    <th>Tgl Pinjam</th>
                    <th>Tgl Kembali</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $recent->fetch_assoc()): 
                    $late = ($row['status'] === 'dipinjam' && $row['tanggal_kembali'] < date('Y-m-d'));
                    $statusClass = $late ? 's-terlambat' : 's-' . $row['status'];
                    $statusLabel = $late ? 'Terlambat' : ucfirst($row['status']);
                ?>
                <tr>
                    <td><?= $row['id_pinjam'] ?></td>
                    <td><?= htmlspecialchars($row['nama_anggota']) ?></td>
                    <td><?= htmlspecialchars($row['judul']) ?></td>
                    <td><?= $row['tanggal_pinjam'] ?></td>
                    <td><?= $row['tanggal_kembali'] ?></td>
                    <td><span class="status <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>