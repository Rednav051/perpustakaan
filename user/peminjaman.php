<?php
require_once '../includes/config.php';
requireMahasiswa();

$nim = $_SESSION['nim'];
$anggota = $conn->query("SELECT * FROM anggota WHERE nim='$nim'")->fetch_assoc();

$list = [];
if ($anggota) {
    $aid = $anggota['id'];
    $result = $conn->query("SELECT p.*, b.judul, b.pengarang FROM peminjaman p JOIN buku b ON p.id_buku=b.id_buku WHERE p.id_anggota=$aid ORDER BY p.id_pinjam DESC");
    while ($r = $result->fetch_assoc()) $list[] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman Saya — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg: #1a1a14; --card: #242418; --border: #3a3a2a; --gold: #c9a84c; --green: #4caf82; --text: #e8e4d8; --text-muted: #8a8672; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 1000px; margin: 0 auto; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); margin-bottom: 24px; }
        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e1e18; padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid rgba(58,58,42,0.5); }
        tr:last-child td { border-bottom: none; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .s-dipinjam { background: rgba(201,168,76,0.2); color: var(--gold); }
        .s-dikembalikan { background: rgba(76,175,130,0.2); color: var(--green); }
        .s-terlambat { background: rgba(224,112,112,0.2); color: #e07070; }
        .denda { color: #e07070; font-weight: 600; }
        .no-member-box { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 40px; text-align: center; }
        .no-member-box h3 { font-family: 'Playfair Display', serif; color: var(--gold); font-size: 20px; margin-bottom: 10px; }
        .no-member-box p { color: var(--text-muted); font-size: 14px; }
        .empty { text-align: center; padding: 40px; color: var(--text-muted); }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">
    <div class="page-title">📋 Peminjaman Saya</div>

    <?php if (!$anggota): ?>
    <div class="no-member-box">
        <h3>Belum Terdaftar sebagai Anggota</h3>
        <p>Anda belum terdaftar sebagai anggota perpustakaan.<br>Hubungi admin untuk mendaftarkan diri.</p>
    </div>
    <?php elseif (empty($list)): ?>
    <div class="table-wrap">
        <div class="empty">📭 Belum ada riwayat peminjaman.</div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Judul Buku</th><th>Pengarang</th><th>Tgl Pinjam</th><th>Tgl Kembali</th><th>Dikembalikan</th><th>Status</th><th>Denda</th></tr>
            </thead>
            <tbody>
                <?php foreach ($list as $r):
                    $late = ($r['status'] === 'dipinjam' && $r['tanggal_kembali'] < date('Y-m-d'));
                    $sc = $late ? 's-terlambat' : 's-'.$r['status'];
                    $sl = $late ? 'Terlambat' : ucfirst($r['status']);
                ?>
                <tr>
                    <td><?= $r['id_pinjam'] ?></td>
                    <td><strong><?= htmlspecialchars($r['judul']) ?></strong></td>
                    <td><?= htmlspecialchars($r['pengarang']) ?></td>
                    <td><?= $r['tanggal_pinjam'] ?></td>
                    <td><?= $r['tanggal_kembali'] ?></td>
                    <td><?= $r['tanggal_dikembalikan'] ?? '-' ?></td>
                    <td><span class="status <?= $sc ?>"><?= $sl ?></span></td>
                    <td><?= $r['denda'] > 0 ? '<span class="denda">Rp '.number_format($r['denda'],0,',','.').'</span>' : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
