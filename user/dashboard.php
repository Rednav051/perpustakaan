<?php
require_once '../includes/config.php';
requireMahasiswa();

$nim = $_SESSION['nim'];

// Get mahasiswa data + anggota
$mhs = $conn->query("SELECT m.*, a.id as anggota_id, a.status as status_anggota, a.masa_berlaku FROM mahasiswa m LEFT JOIN anggota a ON m.nim=a.nim WHERE m.nim='$nim'")->fetch_assoc();

$total_pinjam = 0;
$total_terlambat = 0;

if ($mhs['anggota_id']) {
    $aid = $mhs['anggota_id'];
    $total_pinjam = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_anggota=$aid AND status='dipinjam'")->fetch_assoc()['c'];
    $total_terlambat = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_anggota=$aid AND status='dipinjam' AND tanggal_kembali < CURDATE()")->fetch_assoc()['c'];
    $riwayat = $conn->query("SELECT p.*, b.judul FROM peminjaman p JOIN buku b ON p.id_buku=b.id_buku WHERE p.id_anggota=$aid ORDER BY p.id_pinjam DESC LIMIT 5");
}

$total_buku = $conn->query("SELECT COUNT(*) as c FROM buku")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg: #1a1a14; --card: #242418; --border: #3a3a2a; --gold: #c9a84c; --green: #4caf82; --green-dark: #3a9168; --text: #e8e4d8; --text-muted: #8a8672; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 1100px; margin: 0 auto; }
        .welcome { margin-bottom: 28px; }
        .welcome h1 { font-family: 'Playfair Display', serif; font-size: 26px; color: var(--gold); }
        .welcome p { color: var(--text-muted); font-size: 14px; margin-top: 4px; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .profile-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
        .profile-avatar { width: 56px; height: 56px; background: rgba(76,175,130,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 14px; }
        .profile-name { font-size: 18px; font-weight: 600; margin-bottom: 4px; }
        .profile-nim { font-size: 13px; color: var(--gold); font-family: monospace; }
        .profile-detail { margin-top: 12px; font-size: 13px; color: var(--text-muted); }
        .profile-detail span { color: var(--text); }
        .membership-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
        .member-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 14px; }
        .ms-aktif { background: rgba(76,175,130,0.15); color: var(--green); border: 1px solid rgba(76,175,130,0.3); }
        .ms-nonaktif { background: rgba(224,112,112,0.15); color: #e07070; border: 1px solid rgba(224,112,112,0.3); }
        .ms-none { background: rgba(138,134,114,0.15); color: var(--text-muted); border: 1px solid var(--border); }
        .stats-mini { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
        .stat-mini { background: #1e1e18; border-radius: 8px; padding: 12px; text-align: center; }
        .stat-mini-num { font-size: 24px; font-weight: 700; font-family: 'Playfair Display', serif; color: var(--gold); }
        .stat-mini-label { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 14px; }
        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e1e18; padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        td { padding: 11px 16px; font-size: 13px; border-bottom: 1px solid rgba(58,58,42,0.5); }
        tr:last-child td { border-bottom: none; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .s-dipinjam { background: rgba(201,168,76,0.2); color: var(--gold); }
        .s-dikembalikan { background: rgba(76,175,130,0.2); color: var(--green); }
        .s-terlambat { background: rgba(224,112,112,0.2); color: #e07070; }
        .quick-links { display: flex; gap: 12px; margin-bottom: 28px; }
        .quick-link { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 16px 20px; text-decoration: none; color: var(--text); transition: all 0.2s; display: flex; align-items: center; gap: 10px; }
        .quick-link:hover { border-color: var(--green); }
        .no-member { background: rgba(201,168,76,0.1); border: 1px solid rgba(201,168,76,0.3); border-radius: 10px; padding: 20px; text-align: center; color: var(--text-muted); font-size: 14px; }
        .no-member strong { color: var(--gold); display: block; font-size: 16px; margin-bottom: 6px; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">
    <div class="welcome">
        <h1>Selamat Datang, <?= htmlspecialchars($mhs['nama']) ?>!</h1>
        <p>Berikut informasi akun perpustakaan Anda.</p>
    </div>

    <div class="quick-links">
        <a href="katalog.php" class="quick-link">📚 <span>Katalog Buku</span></a>
        <a href="peminjaman.php" class="quick-link">📋 <span>Peminjaman Saya</span></a>
        <a href="profil.php" class="quick-link">👤 <span>Profil</span></a>
    </div>

    <div class="grid2">
        <div class="profile-card">
            <div class="profile-avatar">🎓</div>
            <div class="profile-name"><?= htmlspecialchars($mhs['nama']) ?></div>
            <div class="profile-nim"><?= $mhs['nim'] ?></div>
            <div class="profile-detail" style="margin-top:10px">
                Jurusan: <span><?= htmlspecialchars($mhs['jurusan']) ?></span><br>
                Email: <span><?= htmlspecialchars($mhs['email']) ?></span><br>
                Angkatan: <span><?= $mhs['angkatan'] ?></span>
            </div>
        </div>

        <div class="membership-card">
            <div style="font-size:15px;font-weight:600;margin-bottom:12px">Status Keanggotaan</div>
            <?php if ($mhs['anggota_id']): ?>
                <div class="member-status ms-<?= $mhs['status_anggota'] ?>">
                    <?= $mhs['status_anggota'] === 'aktif' ? '✓ Anggota Aktif' : '✗ Nonaktif' ?>
                </div>
                <div style="font-size:13px;color:var(--text-muted)">Masa berlaku: <span style="color:var(--text)"><?= $mhs['masa_berlaku'] ?></span></div>
                <div class="stats-mini">
                    <div class="stat-mini">
                        <div class="stat-mini-num"><?= $total_pinjam ?></div>
                        <div class="stat-mini-label">Sedang Dipinjam</div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-mini-num" style="<?= $total_terlambat > 0 ? 'color:#e07070' : '' ?>"><?= $total_terlambat ?></div>
                        <div class="stat-mini-label">Terlambat</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-member">
                    <strong>Belum Terdaftar sebagai Anggota</strong>
                    Hubungi admin perpustakaan untuk mendaftar sebagai anggota.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mhs['anggota_id'] && isset($riwayat)): ?>
    <div class="section-title">Riwayat Peminjaman Terbaru</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Judul Buku</th><th>Tgl Pinjam</th><th>Tgl Kembali</th><th>Status</th><th>Denda</th></tr>
            </thead>
            <tbody>
                <?php while ($r = $riwayat->fetch_assoc()):
                    $late = ($r['status'] === 'dipinjam' && $r['tanggal_kembali'] < date('Y-m-d'));
                    $sc = $late ? 's-terlambat' : 's-' . $r['status'];
                    $sl = $late ? 'Terlambat' : ucfirst($r['status']);
                ?>
                <tr>
                    <td><?= $r['id_pinjam'] ?></td>
                    <td><?= htmlspecialchars($r['judul']) ?></td>
                    <td><?= $r['tanggal_pinjam'] ?></td>
                    <td><?= $r['tanggal_kembali'] ?></td>
                    <td><span class="status <?= $sc ?>"><?= $sl ?></span></td>
                    <td><?= $r['denda'] > 0 ? 'Rp '.number_format($r['denda'],0,',','.') : '-' ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
