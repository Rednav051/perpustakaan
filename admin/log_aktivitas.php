<?php
require_once '../includes/config.php';
requireAdmin();

// Hapus log lama jika diminta
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus_log') {
    $hari = (int)($_POST['hari'] ?? 30);
    $conn->query("DELETE FROM log_aktivitas WHERE created_at < DATE_SUB(NOW(), INTERVAL $hari DAY)");
    $msg = "Log lebih dari $hari hari yang lalu berhasil dihapus.";
    catatLog('Hapus Log', "Menghapus log aktivitas lebih dari $hari hari");
}

// Filter
$filter_role   = $_GET['role']   ?? '';
$filter_aksi   = $_GET['aksi']   ?? '';
$search        = $_GET['search'] ?? '';

// Pagination
$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where = "WHERE 1=1";
if ($filter_role)  $where .= " AND role='"  . $conn->real_escape_string($filter_role) . "'";
if ($filter_aksi)  $where .= " AND aksi LIKE '%" . $conn->real_escape_string($filter_aksi) . "%'";
if ($search)       $where .= " AND (user_name LIKE '%" . $conn->real_escape_string($search) . "%' OR keterangan LIKE '%" . $conn->real_escape_string($search) . "%')";

$total_rows  = $conn->query("SELECT COUNT(*) as c FROM log_aktivitas $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_rows / $per_page));
$list        = $conn->query("SELECT * FROM log_aktivitas $where ORDER BY id DESC LIMIT $per_page OFFSET $offset");

// Daftar aksi unik untuk filter dropdown
$aksi_list = $conn->query("SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #1a1a14; --card: #242418; --border: #3a3a2a;
            --gold: #c9a84c; --green: #4caf82; --green-dark: #3a9168;
            --text: #e8e4d8; --text-muted: #8a8672;
            --input-bg: #1e1e18; --error: #e07070;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 1200px; margin: 0 auto; }

        .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); }
        .page-sub   { font-size: 13px; color: var(--text-muted); margin-top: 3px; }

        .filter-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-bar input, .filter-bar select {
            background: var(--card); border: 1px solid var(--border); border-radius: 8px;
            padding: 9px 13px; color: var(--text); font-family: 'DM Sans', sans-serif;
            font-size: 13px; outline: none;
        }
        .filter-bar input { flex: 1; min-width: 180px; }
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--gold); }
        .filter-bar select option { background: var(--card); }
        .btn-filter { padding: 9px 16px; background: var(--gold); color: #1a1a14; border: none; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .btn-reset  { padding: 9px 13px; background: var(--card); border: 1px solid var(--border); border-radius: 8px; color: var(--text-muted); text-decoration: none; font-size: 13px; }

        .stats-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-pill { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 8px 14px; font-size: 13px; }
        .stat-pill span { color: var(--gold); font-weight: 700; }

        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th { background: #1e1e18; padding: 11px 14px; text-align: left; font-size: 10px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: 11px 14px; font-size: 13px; border-bottom: 1px solid rgba(58,58,42,0.5); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.015); }

        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .rb-admin { background: rgba(201,168,76,0.2); color: var(--gold); }
        .rb-mahasiswa { background: rgba(76,175,130,0.2); color: var(--green); }

        .aksi-badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; background: rgba(138,134,114,0.15); color: var(--text-muted); }

        .keterangan { font-size: 12px; color: var(--text-muted); max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .time-cell { font-size: 12px; color: var(--text-muted); white-space: nowrap; }
        .ip-cell   { font-size: 11px; color: var(--text-muted); font-family: monospace; }

        .empty { text-align: center; padding: 48px; color: var(--text-muted); }

        /* Pagination */
        .pagination { display: flex; gap: 6px; justify-content: center; align-items: center; margin-top: 22px; flex-wrap: wrap; }
        .page-btn {
            padding: 7px 12px; border-radius: 7px; border: 1px solid var(--border);
            background: var(--card); color: var(--text-muted); text-decoration: none;
            font-size: 13px; transition: all 0.2s;
        }
        .page-btn:hover { border-color: var(--gold); color: var(--gold); }
        .page-btn.active { background: var(--gold); color: #1a1a14; border-color: var(--gold); font-weight: 700; }
        .page-btn.disabled { opacity: 0.4; pointer-events: none; }
        .page-info { font-size: 13px; color: var(--text-muted); padding: 7px 4px; }

        /* Hapus log */
        .danger-zone { background: rgba(224,112,112,0.05); border: 1px solid rgba(224,112,112,0.2); border-radius: 12px; padding: 20px 24px; margin-top: 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .danger-zone-title { font-size: 14px; font-weight: 600; color: var(--error); margin-bottom: 4px; }
        .danger-zone-sub   { font-size: 13px; color: var(--text-muted); }
        .hapus-form { display: flex; gap: 8px; align-items: center; }
        .hapus-form select { background: var(--input-bg); border: 1px solid var(--border); border-radius: 7px; padding: 8px 12px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; }
        .btn-hapus-log { padding: 9px 16px; background: rgba(224,112,112,0.2); color: var(--error); border: 1px solid rgba(224,112,112,0.3); border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; }
        .btn-hapus-log:hover { background: rgba(224,112,112,0.35); }

        .alert { padding: 13px 16px; border-radius: 9px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: rgba(76,175,130,0.12); border: 1px solid rgba(76,175,130,0.3); color: var(--green); }

        @media (max-width: 600px) {
            .main { padding: 20px 16px; }
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">
    <div class="page-header">
        <div>
            <div class="page-title">📜 Log Aktivitas</div>
            <div class="page-sub">Riwayat semua aksi di sistem</div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- Filter -->
    <form class="filter-bar" method="GET">
        <input type="text" name="search" placeholder="Cari nama atau keterangan..." value="<?= htmlspecialchars($search) ?>">
        <select name="role">
            <option value="">Semua Role</option>
            <option value="admin"     <?= $filter_role==='admin'     ?'selected':'' ?>>Admin</option>
            <option value="mahasiswa" <?= $filter_role==='mahasiswa' ?'selected':'' ?>>Mahasiswa</option>
        </select>
        <select name="aksi">
            <option value="">Semua Aksi</option>
            <?php while ($a = $aksi_list->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($a['aksi']) ?>" <?= $filter_aksi===$a['aksi']?'selected':'' ?>>
                <?= htmlspecialchars($a['aksi']) ?>
            </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="btn-filter">Filter</button>
        <?php if ($filter_role || $filter_aksi || $search): ?>
            <a href="log_aktivitas.php" class="btn-reset">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat-pill">Total log: <span><?= number_format($total_rows) ?></span></div>
        <div class="stat-pill">Halaman <span><?= $page ?></span> dari <span><?= $total_pages ?></span></div>
    </div>

    <!-- Tabel -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Waktu</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Aksi</th>
                    <th>Keterangan</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total_rows === 0): ?>
                <tr><td colspan="7"><div class="empty">📭 Tidak ada log yang ditemukan.</div></td></tr>
                <?php else: ?>
                <?php while ($r = $list->fetch_assoc()): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:12px"><?= $r['id'] ?></td>
                    <td class="time-cell"><?= date('d M Y', strtotime($r['created_at'])) ?><br><?= date('H:i:s', strtotime($r['created_at'])) ?></td>
                    <td>
                        <strong style="font-size:13px"><?= htmlspecialchars($r['user_name']) ?></strong>
                        <br><span style="font-size:11px;color:var(--text-muted);font-family:monospace"><?= htmlspecialchars($r['user_id']) ?></span>
                    </td>
                    <td><span class="role-badge rb-<?= $r['role'] ?>"><?= ucfirst($r['role']) ?></span></td>
                    <td><span class="aksi-badge"><?= htmlspecialchars($r['aksi']) ?></span></td>
                    <td><div class="keterangan" title="<?= htmlspecialchars($r['keterangan'] ?? '') ?>"><?= htmlspecialchars($r['keterangan'] ?? '-') ?></div></td>
                    <td class="ip-cell"><?= htmlspecialchars($r['ip_address'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1):
        $qs = http_build_query(['role' => $filter_role, 'aksi' => $filter_aksi, 'search' => $search]);
        $qs = $qs ? '&'.$qs : '';
    ?>
    <div class="pagination">
        <a href="?page=<?= max(1,$page-1) ?><?= $qs ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">← Prev</a>
        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1): ?><a href="?page=1<?= $qs ?>" class="page-btn">1</a><?php if ($start > 2): ?><span class="page-info">…</span><?php endif; endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="?page=<?= $i ?><?= $qs ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($end < $total_pages): ?><?php if ($end < $total_pages - 1): ?><span class="page-info">…</span><?php endif; ?><a href="?page=<?= $total_pages ?><?= $qs ?>" class="page-btn"><?= $total_pages ?></a><?php endif; ?>
        <a href="?page=<?= min($total_pages,$page+1) ?><?= $qs ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>">Next →</a>
    </div>
    <?php endif; ?>

    <!-- Danger zone: hapus log lama -->
    <div class="danger-zone">
        <div>
            <div class="danger-zone-title">🗑️ Bersihkan Log Lama</div>
            <div class="danger-zone-sub">Hapus log yang sudah lama untuk menjaga performa database</div>
        </div>
        <form method="POST" class="hapus-form" onsubmit="return confirm('Yakin ingin menghapus log lama?')">
            <input type="hidden" name="action" value="hapus_log">
            <select name="hari">
                <option value="30">Lebih dari 30 hari</option>
                <option value="60">Lebih dari 60 hari</option>
                <option value="90">Lebih dari 90 hari</option>
                <option value="180">Lebih dari 180 hari</option>
            </select>
            <button type="submit" class="btn-hapus-log">Hapus Log</button>
        </form>
    </div>
</div>
</body>
</html>