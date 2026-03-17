<?php
require_once '../includes/config.php';
requireAdmin();

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nim = trim($_POST['nim']);
        $masa = $_POST['masa_berlaku'];
        // Check mahasiswa exists
        $check = $conn->query("SELECT nim FROM mahasiswa WHERE nim='$nim'");
        if ($check->num_rows === 0) { $error = 'NIM mahasiswa tidak ditemukan.'; }
        else {
            $nama = $conn->query("SELECT nama FROM mahasiswa WHERE nim='$nim'")->fetch_assoc()['nama'];
            $stmt = $conn->prepare("INSERT INTO anggota (nim, nama, masa_berlaku) VALUES (?,?,?)");
            $stmt->bind_param("sss", $nim, $nama, $masa);
            if ($stmt->execute()) $msg = 'Anggota berhasil ditambahkan.';
            else $error = 'NIM sudah terdaftar sebagai anggota.';
        }
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $new = $status === 'aktif' ? 'nonaktif' : 'aktif';
        $conn->query("UPDATE anggota SET status='$new' WHERE id=$id");
        $msg = 'Status anggota diperbarui.';
    }

    if ($action === 'hapus') {
        $id = (int)$_POST['id'];
        // Check if has active loans
        $cek = $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_anggota=$id AND status='dipinjam'")->fetch_assoc()['c'];
        if ($cek > 0) {
            $error = 'Tidak bisa menghapus anggota yang masih memiliki peminjaman aktif.';
        } else {
            // Delete peminjaman history first, then anggota
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM peminjaman WHERE id_anggota=$id");
                $stmt = $conn->prepare("DELETE FROM anggota WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $conn->commit();
                $msg = 'Anggota dan riwayat peminjaman berhasil dihapus.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Gagal menghapus anggota: ' . $e->getMessage();
            }
        }
    }
}

$list = $conn->query("SELECT a.*, m.jurusan, m.email FROM anggota a JOIN mahasiswa m ON a.nim = m.nim ORDER BY a.id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anggota — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg: #1a1a14; --card: #242418; --border: #3a3a2a; --gold: #c9a84c; --green: #4caf82; --green-dark: #3a9168; --text: #e8e4d8; --text-muted: #8a8672; --input-bg: #1e1e18; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 1100px; margin: 0 auto; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); }
        .btn-primary { padding: 10px 18px; background: var(--green); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e1e18; padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid rgba(58,58,42,0.5); }
        tr:last-child td { border-bottom: none; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .s-aktif { background: rgba(76,175,130,0.2); color: var(--green); }
        .s-nonaktif { background: rgba(224,112,112,0.2); color: #e07070; }
        .btn-sm { padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; font-family: 'DM Sans', sans-serif; }
        .btn-del { background: rgba(224,112,112,0.2); color: #e07070; }
        .td-actions { display: flex; gap: 6px; align-items: center; }
        .alert { padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: rgba(76,175,130,0.15); border: 1px solid rgba(76,175,130,0.3); color: var(--green); }
        .alert-error { background: rgba(224,112,112,0.15); border: 1px solid rgba(224,112,112,0.3); color: #e07070; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 200; align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-box { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; width: 100%; max-width: 400px; }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--gold); margin-bottom: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        .field input { width: 100%; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { padding: 10px 18px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 8px; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .expired { color: #e07070; font-size: 11px; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">
    <div class="page-header">
        <div class="page-title">👥 Kelola Anggota</div>
        <button class="btn-primary" onclick="document.getElementById('modal-tambah').classList.add('open')">+ Tambah Anggota</button>
    </div>

    <?php if ($msg): ?><div class="alert alert-success">✅ <?= $msg ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= $error ?></div><?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>NIM</th><th>Nama</th><th>Jurusan</th><th>Email</th><th>Tgl Daftar</th><th>Masa Berlaku</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php while ($r = $list->fetch_assoc()):
                    $expired = $r['masa_berlaku'] && $r['masa_berlaku'] < date('Y-m-d');
                ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><code style="color:var(--gold);font-size:12px"><?= $r['nim'] ?></code></td>
                    <td><strong><?= htmlspecialchars($r['nama']) ?></strong></td>
                    <td><?= htmlspecialchars($r['jurusan']) ?></td>
                    <td style="color:var(--text-muted)"><?= htmlspecialchars($r['email']) ?></td>
                    <td><?= $r['tanggal_daftar'] ?></td>
                    <td><?= $r['masa_berlaku'] ?> <?= $expired ? '<span class="expired">(kadaluarsa)</span>' : '' ?></td>
                    <td><span class="status s-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td>
                        <div class="td-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="status" value="<?= $r['status'] ?>">
                                <button type="submit" class="btn-sm" style="background:rgba(201,168,76,0.2);color:var(--gold)">
                                    <?= $r['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                            <button class="btn-sm btn-del" onclick="confirmHapus(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nama'], ENT_QUOTES) ?>')">Hapus</button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div id="modal-hapus" class="modal">
    <div class="modal-box" style="max-width:380px">
        <div class="modal-title" style="color:#e07070">🗑️ Hapus Anggota</div>
        <p style="font-size:14px;color:var(--text-muted);margin-bottom:6px">Anda akan menghapus anggota:</p>
        <p id="hapus-nama" style="font-size:16px;font-weight:600;margin-bottom:16px;color:var(--text)"></p>
        <p style="font-size:13px;color:#e07070;background:rgba(224,112,112,0.1);border:1px solid rgba(224,112,112,0.2);border-radius:8px;padding:10px">
            ⚠️ Tindakan ini tidak bisa dibatalkan. Semua <strong>riwayat peminjaman</strong> anggota ini juga akan ikut dihapus.
        </p>
        <form method="POST" style="margin-top:0">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="id" id="hapus-id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modal-hapus').classList.remove('open')">Batal</button>
                <button type="submit" class="btn-sm btn-del" style="padding:10px 18px;font-size:14px">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-tambah" class="modal">
    <div class="modal-box">
        <div class="modal-title">Tambah Anggota Baru</div>
        <form method="POST">
            <input type="hidden" name="action" value="tambah">
            <div class="field"><label>NIM Mahasiswa</label><input name="nim" placeholder="24024000" required></div>
            <div class="field"><label>Masa Berlaku</label><input type="date" name="masa_berlaku" value="<?= date('Y-m-d', strtotime('+1 year')) ?>"></div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('modal-tambah').classList.remove('open')">Batal</button>
                <button type="submit" class="btn-primary">Tambah</button>
            </div>
        </form>
    </div>
</div>
</body>
<script>
function confirmHapus(id, nama) {
    document.getElementById('hapus-id').value = id;
    document.getElementById('hapus-nama').textContent = nama;
    document.getElementById('modal-hapus').classList.add('open');
}
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});
</script>
</html>