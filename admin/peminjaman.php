<?php
require_once '../includes/config.php';
requireAdmin();

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $id_anggota  = (int)$_POST['id_anggota'];
        $id_buku     = trim($_POST['id_buku']);
        $tgl_kembali = $_POST['tanggal_kembali'];

        $stok    = $conn->query("SELECT stok FROM buku WHERE id_buku='$id_buku'")->fetch_assoc()['stok'] ?? 0;
        $dipinjam= $conn->query("SELECT COUNT(*) as c FROM peminjaman WHERE id_buku='$id_buku' AND status='dipinjam'")->fetch_assoc()['c'];
        if ($stok - $dipinjam <= 0) {
            $error = 'Stok buku tidak tersedia.';
        } else {
            $stmt = $conn->prepare("INSERT INTO peminjaman (id_anggota, id_buku, tanggal_kembali) VALUES (?,?,?)");
            $stmt->bind_param("iss", $id_anggota, $id_buku, $tgl_kembali);
            if ($stmt->execute()) {
                $nama_a = $conn->query("SELECT nama FROM anggota WHERE id=$id_anggota")->fetch_assoc()['nama'];
                $judul  = $conn->query("SELECT judul FROM buku WHERE id_buku='$id_buku'")->fetch_assoc()['judul'];
                $msg = 'Peminjaman berhasil ditambahkan.';
                catatLog('Tambah Peminjaman', "\"$judul\" dipinjam oleh $nama_a");
            } else {
                $error = $conn->error;
            }
        }
    }

    if ($action === 'kembalikan') {
        $id_pinjam = (int)$_POST['id_pinjam'];
        $today     = date('Y-m-d');
        $pinjam    = $conn->query("SELECT p.*, b.judul, a.nama as nama_a FROM peminjaman p JOIN buku b ON p.id_buku=b.id_buku JOIN anggota a ON p.id_anggota=a.id WHERE p.id_pinjam=$id_pinjam")->fetch_assoc();
        $denda     = 0;
        if ($pinjam && $today > $pinjam['tanggal_kembali']) {
            $diff  = (strtotime($today) - strtotime($pinjam['tanggal_kembali'])) / 86400;
            $denda_per_hari = (int)getSetting('denda_per_hari', 1000);
            $denda = $diff * $denda_per_hari;
        }
        $stmt = $conn->prepare("UPDATE peminjaman SET status='dikembalikan', tanggal_dikembalikan=?, denda=? WHERE id_pinjam=?");
        $stmt->bind_param("sdi", $today, $denda, $id_pinjam);
        if ($stmt->execute()) {
            $msg = 'Buku berhasil dikembalikan.' . ($denda > 0 ? ' Denda: Rp ' . number_format($denda, 0, ',', '.') : ' Tidak ada denda.');
            catatLog('Kembalikan Buku', "ID pinjam #$id_pinjam — \"{$pinjam['judul']}\" oleh {$pinjam['nama_a']}" . ($denda > 0 ? ", denda Rp $denda" : ''));
        }
    }
}

// Filter & Pagination
$filter   = $_GET['filter'] ?? 'semua';
$search   = $_GET['search'] ?? '';
$per_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where = "WHERE 1=1";
if ($filter === 'dipinjam')     $where .= " AND p.status='dipinjam'";
elseif ($filter === 'terlambat') $where .= " AND p.status='dipinjam' AND p.tanggal_kembali < CURDATE()";
elseif ($filter === 'dikembalikan') $where .= " AND p.status='dikembalikan'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (a.nama LIKE '%$s%' OR b.judul LIKE '%$s%')";
}

$total_rows  = $conn->query("SELECT COUNT(*) as c FROM peminjaman p JOIN anggota a ON p.id_anggota=a.id JOIN buku b ON p.id_buku=b.id_buku $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_rows / $per_page));
$list        = $conn->query("SELECT p.*, a.nama as nama_anggota, b.judul FROM peminjaman p JOIN anggota a ON p.id_anggota=a.id JOIN buku b ON p.id_buku=b.id_buku $where ORDER BY p.id_pinjam DESC LIMIT $per_page OFFSET $offset");

$anggota_list = $conn->query("SELECT id, nama FROM anggota WHERE status='aktif' ORDER BY nama");
$buku_list    = $conn->query("SELECT id_buku, judul, stok FROM buku ORDER BY judul");

$durasi_default = (int)getSetting('durasi_default', 7);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peminjaman — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg: #1a1a14; --card: #242418; --border: #3a3a2a; --gold: #c9a84c; --green: #4caf82; --green-dark: #3a9168; --text: #e8e4d8; --text-muted: #8a8672; --input-bg: #1e1e18; --error: #e07070; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 1200px; margin: 0 auto; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); }
        .btn-primary { padding: 10px 18px; background: var(--green); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .btn-primary:hover { background: var(--green-dark); }

        /* Filter & Search */
        .toolbar { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; align-items: center; }
        .filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
        .filter-tab { padding: 7px 13px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; color: var(--text-muted); border: 1px solid var(--border); transition: all 0.2s; white-space: nowrap; }
        .filter-tab.active, .filter-tab:hover { border-color: var(--gold); color: var(--gold); background: rgba(201,168,76,0.1); }
        .search-input { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 8px 13px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; width: 220px; }
        .search-input:focus { border-color: var(--gold); }
        .btn-search { padding: 8px 14px; background: var(--gold); color: #1a1a14; border: none; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .btn-reset  { padding: 8px 12px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-muted); text-decoration: none; font-size: 13px; }

        .count-info { font-size: 13px; color: var(--text-muted); margin-bottom: 14px; }
        .count-info span { color: var(--text); font-weight: 600; }

        /* Table */
        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 750px; }
        th { background: #1e1e18; padding: 12px 14px; text-align: left; font-size: 10px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 12px 14px; font-size: 13px; border-bottom: 1px solid rgba(58,58,42,0.5); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.015); }
        .status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .s-dipinjam { background: rgba(201,168,76,0.2); color: var(--gold); }
        .s-dikembalikan { background: rgba(76,175,130,0.2); color: var(--green); }
        .s-terlambat { background: rgba(224,112,112,0.2); color: var(--error); }
        .btn-sm { padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; font-family: 'DM Sans', sans-serif; }
        .btn-return { background: rgba(76,175,130,0.2); color: var(--green); transition: background 0.2s; }
        .btn-return:hover { background: rgba(76,175,130,0.4); }
        .denda { color: var(--error); font-weight: 600; }

        /* Alerts */
        .alert { padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
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
        .modal-box { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; width: 100%; max-width: 440px; }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--gold); margin-bottom: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        .field select, .field input { width: 100%; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        .field select option { background: var(--card); }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { padding: 10px 18px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 8px; cursor: pointer; font-family: 'DM Sans', sans-serif; }

        /* Konfirmasi kembalikan */
        .ci-grid { background: #1e1e18; border-radius: 10px; padding: 16px; margin-bottom: 14px; }
        .ci-row  { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid rgba(58,58,42,0.5); font-size: 13px; }
        .ci-row:last-child { border-bottom: none; }
        .ci-label { color: var(--text-muted); }
        .ci-val   { font-weight: 500; text-align: right; }
        .denda-warning { background: rgba(224,112,112,0.1); border: 1px solid rgba(224,112,112,0.25); border-radius: 8px; padding: 12px 14px; font-size: 13px; color: var(--error); margin-bottom: 14px; }
        .no-denda { background: rgba(76,175,130,0.1); border: 1px solid rgba(76,175,130,0.25); border-radius: 8px; padding: 12px 14px; font-size: 13px; color: var(--green); margin-bottom: 14px; }

        /* Loading spinner on button */
        .btn-spinner { display: none; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 600px) {
            .main { padding: 20px 16px; }
            .search-input { width: 100%; }
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">
    <div class="page-header">
        <div class="page-title">📋 Peminjaman</div>
        <button class="btn-primary" onclick="openModal('modal-tambah')">+ Tambah Peminjaman</button>
    </div>

    <?php if ($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Toolbar: filter tabs + search -->
    <form method="GET" id="filter-form">
        <div class="toolbar">
            <div class="filter-tabs">
                <?php
                $tabs = ['semua'=>'Semua','dipinjam'=>'Dipinjam','terlambat'=>'Terlambat','dikembalikan'=>'Dikembalikan'];
                foreach ($tabs as $k => $v):
                ?>
                <a href="?filter=<?= $k ?>&search=<?= urlencode($search) ?>"
                   class="filter-tab <?= $filter===$k?'active':'' ?>"><?= $v ?></a>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <input type="text" name="search" class="search-input" placeholder="Cari anggota / judul..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-search">Cari</button>
            <?php if ($search): ?><a href="?filter=<?= $filter ?>" class="btn-reset">Reset</a><?php endif; ?>
        </div>
    </form>

    <div class="count-info">Menampilkan <span><?= number_format($total_rows) ?></span> data<?= $total_pages > 1 ? " — halaman $page dari $total_pages" : '' ?></div>

    <!-- Tabel -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Anggota</th><th>Buku</th><th>Tgl Pinjam</th><th>Tgl Kembali</th><th>Dikembalikan</th><th>Status</th><th>Denda</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php if ($total_rows === 0): ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">📭 Tidak ada data.</td></tr>
            <?php else: ?>
            <?php while ($r = $list->fetch_assoc()):
                $late = ($r['status'] === 'dipinjam' && $r['tanggal_kembali'] < date('Y-m-d'));
                $sc   = $late ? 's-terlambat' : 's-'.$r['status'];
                $sl   = $late ? 'Terlambat' : ucfirst($r['status']);
                $hari_terlambat = 0;
                if ($late) {
                    $hari_terlambat = (int)floor((strtotime(date('Y-m-d')) - strtotime($r['tanggal_kembali'])) / 86400);
                }
            ?>
            <tr>
                <td style="color:var(--text-muted)"><?= $r['id_pinjam'] ?></td>
                <td><strong><?= htmlspecialchars($r['nama_anggota']) ?></strong></td>
                <td><?= htmlspecialchars($r['judul']) ?></td>
                <td><?= $r['tanggal_pinjam'] ?></td>
                <td><?= $r['tanggal_kembali'] ?></td>
                <td><?= $r['tanggal_dikembalikan'] ?? '-' ?></td>
                <td><span class="status <?= $sc ?>"><?= $sl ?></span></td>
                <td><?= $r['denda'] > 0 ? '<span class="denda">Rp '.number_format($r['denda'],0,',','.').'</span>' : '-' ?></td>
                <td>
                    <?php if ($r['status'] === 'dipinjam'): ?>
                    <button type="button" class="btn-sm btn-return"
                        onclick="openKembalikan({
                            id: <?= $r['id_pinjam'] ?>,
                            anggota: '<?= htmlspecialchars($r['nama_anggota'], ENT_QUOTES) ?>',
                            judul: '<?= htmlspecialchars($r['judul'], ENT_QUOTES) ?>',
                            tgl_pinjam: '<?= $r['tanggal_pinjam'] ?>',
                            tgl_kembali: '<?= $r['tanggal_kembali'] ?>',
                            terlambat: <?= $late ? 'true' : 'false' ?>,
                            hari: <?= $hari_terlambat ?>
                        })">Kembalikan</button>
                    <?php else: ?>
                    <span style="color:var(--text-muted);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1):
        $qs = http_build_query(['filter'=>$filter,'search'=>$search]);
        $qs = $qs ? '&'.$qs : '';
    ?>
    <div class="pagination">
        <a href="?page=<?= max(1,$page-1) ?><?= $qs ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">← Prev</a>
        <?php
        $start = max(1, $page-2); $end = min($total_pages, $page+2);
        if ($start>1): ?><a href="?page=1<?= $qs ?>" class="page-btn">1</a><?php if ($start>2): ?><span class="page-info">…</span><?php endif; endif; ?>
        <?php for ($i=$start;$i<=$end;$i++): ?>
        <a href="?page=<?= $i ?><?= $qs ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($end<$total_pages): ?><?php if ($end<$total_pages-1): ?><span class="page-info">…</span><?php endif; ?><a href="?page=<?= $total_pages ?><?= $qs ?>" class="page-btn"><?= $total_pages ?></a><?php endif; ?>
        <a href="?page=<?= min($total_pages,$page+1) ?><?= $qs ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>">Next →</a>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Peminjaman -->
<div id="modal-tambah" class="modal">
    <div class="modal-box">
        <div class="modal-title">+ Tambah Peminjaman</div>
        <form method="POST" onsubmit="showLoading(this)">
            <input type="hidden" name="action" value="tambah">
            <div class="field">
                <label>Anggota Aktif</label>
                <select name="id_anggota" required>
                    <option value="">— Pilih Anggota —</option>
                    <?php while ($a = $anggota_list->fetch_assoc()): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nama']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="field">
                <label>Buku</label>
                <select name="id_buku" required>
                    <option value="">— Pilih Buku —</option>
                    <?php while ($b = $buku_list->fetch_assoc()): ?>
                    <option value="<?= $b['id_buku'] ?>">[<?= $b['id_buku'] ?>] <?= htmlspecialchars($b['judul']) ?> (Stok: <?= $b['stok'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="field">
                <label>Tanggal Kembali</label>
                <input type="date" name="tanggal_kembali"
                       value="<?= date('Y-m-d', strtotime("+{$durasi_default} days")) ?>"
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-tambah')">Batal</button>
                <button type="submit" class="btn-primary" id="btn-tambah-submit">
                    <div class="btn-spinner" id="spinner-tambah"></div>
                    <span id="text-tambah">Simpan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Kembalikan -->
<div id="modal-kembalikan" class="modal">
    <div class="modal-box">
        <div class="modal-title">↩️ Konfirmasi Pengembalian</div>
        <div class="ci-grid">
            <div class="ci-row"><span class="ci-label">Anggota</span><span class="ci-val" id="k-anggota"></span></div>
            <div class="ci-row"><span class="ci-label">Buku</span><span class="ci-val" id="k-judul"></span></div>
            <div class="ci-row"><span class="ci-label">Tgl Pinjam</span><span class="ci-val" id="k-tgl-pinjam"></span></div>
            <div class="ci-row"><span class="ci-label">Batas Kembali</span><span class="ci-val" id="k-tgl-kembali"></span></div>
            <div class="ci-row"><span class="ci-label">Dikembalikan</span><span class="ci-val" style="color:var(--green)"><?= date('d M Y') ?></span></div>
        </div>
        <div id="k-denda-box"></div>
        <form method="POST" onsubmit="showLoading(this, 'btn-kembali', 'spinner-kembali', 'text-kembali')">
            <input type="hidden" name="action" value="kembalikan">
            <input type="hidden" name="id_pinjam" id="k-id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-kembalikan')">Batal</button>
                <button type="submit" class="btn-primary" id="btn-kembali">
                    <div class="btn-spinner" id="spinner-kembali"></div>
                    <span id="text-kembali">✅ Konfirmasi</span>
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

function openKembalikan(data) {
    document.getElementById('k-id').value = data.id;
    document.getElementById('k-anggota').textContent   = data.anggota;
    document.getElementById('k-judul').textContent     = data.judul;
    document.getElementById('k-tgl-pinjam').textContent= data.tgl_pinjam;
    document.getElementById('k-tgl-kembali').textContent = data.tgl_kembali;

    const box = document.getElementById('k-denda-box');
    if (data.terlambat && data.hari > 0) {
        const nominal = data.hari * <?= (int)getSetting('denda_per_hari', 1000) ?>;
        box.className = 'denda-warning';
        box.innerHTML = `⚠️ Terlambat <strong>${data.hari} hari</strong>. Denda: <strong>Rp ${nominal.toLocaleString('id-ID')}</strong> (Rp <?= number_format((int)getSetting('denda_per_hari',1000),0,',','.') ?>/hari)`;
    } else {
        box.className = 'no-denda';
        box.innerHTML = '✅ Pengembalian tepat waktu. Tidak ada denda.';
    }
    openModal('modal-kembalikan');
}

function showLoading(form, btnId='btn-tambah-submit', spinnerId='spinner-tambah', textId='text-tambah') {
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