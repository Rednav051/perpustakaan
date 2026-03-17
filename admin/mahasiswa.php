<?php
require_once '../includes/config.php';
requireAdmin();

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nim      = trim($_POST['nim']);
        $nama     = trim($_POST['nama']);
        $jurusan  = trim($_POST['jurusan']);
        $angkatan = trim($_POST['angkatan']);
        $email    = trim($_POST['email']);
        $no_telp  = trim($_POST['no_telp']);
        $password = $_POST['password'];

        if (empty($nim) || empty($nama) || empty($password)) {
            $error = 'NIM, Nama, dan Password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } else {
            $check = $conn->prepare("SELECT nim FROM mahasiswa WHERE nim = ?");
            $check->bind_param("s", $nim);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'NIM sudah terdaftar.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO mahasiswa (nim, nama, jurusan, angkatan, email, no_telp, password) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param("sssssss", $nim, $nama, $jurusan, $angkatan, $email, $no_telp, $hash);
                if ($stmt->execute()) {
                    $msg = "Mahasiswa $nama berhasil ditambahkan.";
                    catatLog('Tambah Mahasiswa', "NIM $nim — $nama");
                } else {
                    $error = 'Gagal: ' . $conn->error;
                }
            }
        }
    }

    if ($action === 'hapus') {
        $nim = trim($_POST['nim']);
        $cek = $conn->query("
            SELECT COUNT(*) as c FROM peminjaman p
            JOIN anggota a ON p.id_anggota = a.id
            WHERE a.nim = '$nim' AND p.status = 'dipinjam'
        ")->fetch_assoc()['c'];

        if ($cek > 0) {
            $error = 'Tidak bisa menghapus mahasiswa yang masih memiliki peminjaman aktif.';
        } else {
            $nama_mhs = $conn->query("SELECT nama FROM mahasiswa WHERE nim='$nim'")->fetch_assoc()['nama'] ?? $nim;
            $conn->begin_transaction();
            try {
                $conn->query("DELETE p FROM peminjaman p JOIN anggota a ON p.id_anggota = a.id WHERE a.nim = '$nim'");
                $conn->query("DELETE FROM anggota WHERE nim = '$nim'");
                $stmt = $conn->prepare("DELETE FROM mahasiswa WHERE nim = ?");
                $stmt->bind_param("s", $nim);
                $stmt->execute();
                $conn->commit();
                $msg = 'Mahasiswa berhasil dihapus.';
                catatLog('Hapus Mahasiswa', "NIM $nim — $nama_mhs");
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Gagal menghapus: ' . $e->getMessage();
            }
        }
    }
}

// Filter & Pagination
$search   = $_GET['search'] ?? '';
$per_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where = "WHERE 1=1";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (m.nim LIKE '%$s%' OR m.nama LIKE '%$s%' OR m.jurusan LIKE '%$s%')";
}

$total_rows  = $conn->query("SELECT COUNT(*) as c FROM mahasiswa m LEFT JOIN anggota a ON m.nim=a.nim $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_rows / $per_page));
$list        = $conn->query("SELECT m.*, a.status as status_anggota FROM mahasiswa m LEFT JOIN anggota a ON m.nim = a.nim $where ORDER BY m.nim ASC LIMIT $per_page OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Mahasiswa — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg: #1a1a14; --card: #242418; --border: #3a3a2a; --gold: #c9a84c; --green: #4caf82; --green-dark: #3a9168; --text: #e8e4d8; --text-muted: #8a8672; --input-bg: #1e1e18; --error: #e07070; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 1200px; margin: 0 auto; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); }
        .page-sub { font-size: 13px; color: var(--text-muted); margin-top: 3px; }
        .btn-primary { padding: 10px 18px; background: var(--green); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .btn-primary:hover { background: var(--green-dark); }

        .search-row { display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
        .search-row input { flex: 1; min-width: 200px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        .search-row input:focus { border-color: var(--gold); }
        .btn-search { padding: 10px 16px; background: var(--gold); color: #1a1a14; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .btn-reset  { padding: 10px 14px; background: var(--card); border: 1px solid var(--border); color: var(--text-muted); border-radius: 8px; font-size: 14px; cursor: pointer; font-family: 'DM Sans', sans-serif; text-decoration: none; }

        .count-label { font-size: 13px; color: var(--text-muted); margin-bottom: 14px; }
        .count-label span { color: var(--text); font-weight: 600; }

        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { background: #1e1e18; padding: 12px 14px; text-align: left; font-size: 10px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid rgba(58,58,42,0.5); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .b-anggota { background: rgba(76,175,130,0.2); color: var(--green); }
        .b-bukan   { background: rgba(138,134,114,0.15); color: var(--text-muted); }
        .btn-sm  { padding: 5px 11px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; font-family: 'DM Sans', sans-serif; }
        .btn-del { background: rgba(224,112,112,0.2); color: var(--error); }
        .btn-del:hover { background: rgba(224,112,112,0.35); }

        .alert { padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; }
        .alert-success { background: rgba(76,175,130,0.15); border: 1px solid rgba(76,175,130,0.3); color: var(--green); }
        .alert-error   { background: rgba(224,112,112,0.15); border: 1px solid rgba(224,112,112,0.3); color: var(--error); }

        /* Pagination */
        .pagination { display: flex; gap: 6px; justify-content: center; align-items: center; margin-top: 20px; flex-wrap: wrap; }
        .page-btn { padding: 7px 12px; border-radius: 7px; border: 1px solid var(--border); background: var(--card); color: var(--text-muted); text-decoration: none; font-size: 13px; transition: all 0.2s; }
        .page-btn:hover { border-color: var(--gold); color: var(--gold); }
        .page-btn.active { background: var(--gold); color: #1a1a14; border-color: var(--gold); font-weight: 700; }
        .page-btn.disabled { opacity: 0.4; pointer-events: none; }
        .page-info { font-size: 13px; color: var(--text-muted); padding: 7px 2px; }

        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.72); z-index: 200; align-items: center; justify-content: center; padding: 20px; }
        .modal.open { display: flex; }
        .modal-box { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; width: 100%; max-width: 480px; }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--gold); margin-bottom: 22px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1.1px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        .field input { width: 100%; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        .field input:focus { border-color: var(--green); }
        .field input::placeholder { color: var(--text-muted); }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 22px; }
        .btn-cancel { padding: 10px 18px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 8px; cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 14px; }
        .confirm-nama { font-size: 15px; font-weight: 600; color: var(--text); margin: 6px 0 16px; }
        .confirm-warn { font-size: 13px; color: var(--error); background: rgba(224,112,112,0.08); border: 1px solid rgba(224,112,112,0.2); border-radius: 8px; padding: 12px; line-height: 1.65; }

        /* Loading */
        .btn-spinner { display: none; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 600px) {
            .main { padding: 20px 16px; }
            .row2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">
    <div class="page-header">
        <div>
            <div class="page-title">🎓 Data Mahasiswa</div>
            <div class="page-sub">Kelola data mahasiswa terdaftar</div>
        </div>
        <button class="btn-primary" onclick="openModal('modal-tambah')">+ Tambah Mahasiswa</button>
    </div>

    <?php if ($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form class="search-row" method="GET">
        <input type="text" name="search" placeholder="Cari NIM, nama, atau jurusan..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-search">Cari</button>
        <?php if ($search): ?><a href="mahasiswa.php" class="btn-reset">Reset</a><?php endif; ?>
    </form>
    <div class="count-label">
        Menampilkan <span><?= number_format($total_rows) ?></span> mahasiswa
        <?= $search ? 'untuk "<strong>'.htmlspecialchars($search).'</strong>"' : '' ?>
        <?= $total_pages > 1 ? "— halaman <span>$page</span> dari <span>$total_pages</span>" : '' ?>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>NIM</th><th>Nama</th><th>Jurusan</th><th>Angkatan</th><th>Email</th><th>No. Telp</th><th>Keanggotaan</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php while ($r = $list->fetch_assoc()): ?>
                <tr>
                    <td><code style="color:var(--gold);font-size:12px"><?= htmlspecialchars($r['nim']) ?></code></td>
                    <td><strong><?= htmlspecialchars($r['nama']) ?></strong></td>
                    <td><?= htmlspecialchars($r['jurusan'] ?? '-') ?></td>
                    <td><?= $r['angkatan'] ?? '-' ?></td>
                    <td style="color:var(--text-muted)"><?= htmlspecialchars($r['email'] ?? '-') ?></td>
                    <td style="color:var(--text-muted)"><?= htmlspecialchars($r['no_telp'] ?? '-') ?></td>
                    <td>
                        <?php if ($r['status_anggota']): ?>
                        <span class="badge b-anggota">✓ Anggota <?= ucfirst($r['status_anggota']) ?></span>
                        <?php else: ?>
                        <span class="badge b-bukan">Belum Daftar</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn-sm btn-del"
                            onclick="confirmHapus('<?= htmlspecialchars($r['nim'],ENT_QUOTES) ?>','<?= htmlspecialchars($r['nama'],ENT_QUOTES) ?>',<?= $r['status_anggota']?'true':'false' ?>)">
                            Hapus
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1):
        $qs = $search ? '&search='.urlencode($search) : '';
    ?>
    <div class="pagination">
        <a href="?page=<?= max(1,$page-1) ?><?= $qs ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">← Prev</a>
        <?php
        $start = max(1,$page-2); $end = min($total_pages,$page+2);
        if ($start>1): ?><a href="?page=1<?= $qs ?>" class="page-btn">1</a><?php if ($start>2): ?><span class="page-info">…</span><?php endif; endif;
        for ($i=$start;$i<=$end;$i++): ?>
        <a href="?page=<?= $i ?><?= $qs ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor;
        if ($end<$total_pages): ?><?php if ($end<$total_pages-1): ?><span class="page-info">…</span><?php endif; ?><a href="?page=<?= $total_pages ?><?= $qs ?>" class="page-btn"><?= $total_pages ?></a><?php endif; ?>
        <a href="?page=<?= min($total_pages,$page+1) ?><?= $qs ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>">Next →</a>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah -->
<div id="modal-tambah" class="modal">
    <div class="modal-box">
        <div class="modal-title">Tambah Mahasiswa Baru</div>
        <form method="POST" onsubmit="btnLoading('btn-tambah','spinner-tambah','text-tambah')">
            <input type="hidden" name="action" value="tambah">
            <div class="row2">
                <div class="field"><label>NIM *</label><input name="nim" placeholder="24024000" required></div>
                <div class="field"><label>Angkatan</label><input name="angkatan" placeholder="<?= date('Y') ?>" type="number"></div>
            </div>
            <div class="field"><label>Nama Lengkap *</label><input name="nama" placeholder="Nama lengkap" required></div>
            <div class="field"><label>Jurusan</label><input name="jurusan" placeholder="Teknik Informatika"></div>
            <div class="row2">
                <div class="field"><label>Email</label><input name="email" type="email" placeholder="email@contoh.com"></div>
                <div class="field"><label>No. Telp</label><input name="no_telp" placeholder="08xxxxxxxxxx"></div>
            </div>
            <div class="field"><label>Password *</label><input name="password" type="password" placeholder="Min. 6 karakter" required></div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-tambah')">Batal</button>
                <button type="submit" class="btn-primary" id="btn-tambah" style="display:flex;align-items:center;gap:8px">
                    <div class="btn-spinner" id="spinner-tambah"></div>
                    <span id="text-tambah">Simpan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div id="modal-hapus" class="modal">
    <div class="modal-box" style="max-width:390px">
        <div class="modal-title" style="color:var(--error)">🗑️ Hapus Mahasiswa</div>
        <div style="font-size:13px;color:var(--text-muted)">Anda akan menghapus:</div>
        <div class="confirm-nama" id="hapus-nama"></div>
        <div class="confirm-warn" id="hapus-warn"></div>
        <form method="POST" onsubmit="btnLoading('btn-hapus','spinner-hapus','text-hapus')">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="nim" id="hapus-nim">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-hapus')">Batal</button>
                <button type="submit" class="btn-sm btn-del" id="btn-hapus" style="padding:10px 20px;font-size:14px;display:flex;align-items:center;gap:6px">
                    <div class="btn-spinner" id="spinner-hapus"></div>
                    <span id="text-hapus">Ya, Hapus</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

function confirmHapus(nim, nama, isAnggota) {
    document.getElementById('hapus-nim').value = nim;
    document.getElementById('hapus-nama').textContent = nama + ' (' + nim + ')';
    document.getElementById('hapus-warn').innerHTML = isAnggota
        ? '⚠️ Mahasiswa ini terdaftar sebagai <strong>anggota perpustakaan</strong>. Data anggota dan seluruh riwayat peminjaman juga akan dihapus.<br><br>Tindakan ini <strong>tidak bisa dibatalkan</strong>.'
        : '⚠️ Data mahasiswa akan dihapus permanen. Tindakan ini <strong>tidak bisa dibatalkan</strong>.';
    openModal('modal-hapus');
}

function btnLoading(btnId, spinnerId, textId) {
    const btn     = document.getElementById(btnId);
    const spinner = document.getElementById(spinnerId);
    const text    = document.getElementById(textId);
    if (btn) btn.disabled = true;
    if (spinner) spinner.style.display = 'inline-block';
    if (text) text.textContent = 'Memproses...';
}
</script>
</body>
</html>