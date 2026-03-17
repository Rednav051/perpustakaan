<?php
require_once '../includes/config.php';
requireAdmin();

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'nama_perpustakaan', 'denda_per_hari', 'max_pinjam',
        'durasi_default', 'max_durasi', 'sesi_timeout'
    ];
    $all_ok = true;

    // Validasi angka
    foreach (['denda_per_hari','max_pinjam','durasi_default','max_durasi','sesi_timeout'] as $f) {
        if (!is_numeric($_POST[$f] ?? '') || (int)$_POST[$f] < 1) {
            $error = "Nilai untuk field numerik harus angka positif.";
            $all_ok = false;
            break;
        }
    }

    if ($all_ok) {
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            $stmt = $conn->prepare("UPDATE pengaturan SET nilai=? WHERE kunci=?");
            $stmt->bind_param("ss", $val, $f);
            $stmt->execute();
            $stmt->close();
        }
        catatLog('Ubah Pengaturan', 'Admin memperbarui pengaturan sistem');
        $msg = 'Pengaturan berhasil disimpan.';
    }
}

// Ambil semua setting
$settings = [];
$res = $conn->query("SELECT kunci, nilai, label, keterangan FROM pengaturan ORDER BY id ASC");
while ($r = $res->fetch_assoc()) {
    $settings[$r['kunci']] = $r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem — Perpustakaan</title>
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
        .main { padding: 32px 28px; max-width: 760px; margin: 0 auto; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); margin-bottom: 6px; }
        .page-sub { font-size: 13px; color: var(--text-muted); margin-bottom: 28px; }

        .section-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 28px; margin-bottom: 22px; }
        .section-title { font-size: 15px; font-weight: 600; color: var(--text); margin-bottom: 22px; display: flex; align-items: center; gap: 8px; padding-bottom: 14px; border-bottom: 1px solid var(--border); }

        .setting-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 18px; }
        .setting-row:last-child { margin-bottom: 0; }

        .field { display: flex; flex-direction: column; gap: 7px; }
        .field label { font-size: 11px; font-weight: 600; letter-spacing: 1.1px; text-transform: uppercase; color: var(--text-muted); }
        .field input {
            background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px;
            padding: 11px 13px; color: var(--text); font-family: 'DM Sans', sans-serif;
            font-size: 14px; outline: none; transition: border-color 0.2s;
        }
        .field input:focus { border-color: var(--green); }
        .field .hint { font-size: 11px; color: var(--text-muted); line-height: 1.5; }

        .full { grid-column: 1 / -1; }

        .form-footer { display: flex; justify-content: flex-end; margin-top: 6px; }
        .btn-save {
            padding: 12px 28px; background: var(--green); color: #fff;
            border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif;
            font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-save:hover { background: var(--green-dark); }
        .btn-save:disabled { opacity: 0.6; cursor: not-allowed; }

        .alert { padding: 13px 16px; border-radius: 9px; font-size: 14px; margin-bottom: 22px; }
        .alert-success { background: rgba(76,175,130,0.12); border: 1px solid rgba(76,175,130,0.3); color: var(--green); }
        .alert-error   { background: rgba(224,112,112,0.12); border: 1px solid rgba(224,112,112,0.3); color: var(--error); }

        .info-box {
            background: rgba(201,168,76,0.07);
            border: 1px solid rgba(201,168,76,0.2);
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 22px;
            line-height: 1.7;
        }
        .info-box strong { color: var(--gold); }

        .spinner { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 600px) {
            .setting-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">
    <div class="page-title">⚙️ Pengaturan Sistem</div>
    <div class="page-sub">Konfigurasi parameter perpustakaan</div>

    <?php if ($msg): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="info-box">
        ℹ️ Perubahan pengaturan akan langsung berlaku. <strong>Denda per hari</strong> hanya berlaku untuk pengembalian <em>setelah</em> pengaturan diubah — transaksi lama tidak terpengaruh.
    </div>

    <form method="POST" id="settings-form" onsubmit="handleSubmit(event)">

        <!-- UMUM -->
        <div class="section-card">
            <div class="section-title">🏛️ Umum</div>
            <div class="setting-row">
                <div class="field full">
                    <label><?= htmlspecialchars($settings['nama_perpustakaan']['label']) ?></label>
                    <input type="text" name="nama_perpustakaan"
                           value="<?= htmlspecialchars($settings['nama_perpustakaan']['nilai']) ?>"
                           placeholder="Nama perpustakaan" required>
                    <span class="hint"><?= htmlspecialchars($settings['nama_perpustakaan']['keterangan']) ?></span>
                </div>
            </div>
        </div>

        <!-- PEMINJAMAN -->
        <div class="section-card">
            <div class="section-title">📋 Peminjaman</div>
            <div class="setting-row">
                <div class="field">
                    <label><?= htmlspecialchars($settings['max_pinjam']['label']) ?></label>
                    <input type="number" name="max_pinjam" min="1" max="20"
                           value="<?= htmlspecialchars($settings['max_pinjam']['nilai']) ?>" required>
                    <span class="hint"><?= htmlspecialchars($settings['max_pinjam']['keterangan']) ?></span>
                </div>
                <div class="field">
                    <label><?= htmlspecialchars($settings['denda_per_hari']['label']) ?></label>
                    <input type="number" name="denda_per_hari" min="0"
                           value="<?= htmlspecialchars($settings['denda_per_hari']['nilai']) ?>" required>
                    <span class="hint"><?= htmlspecialchars($settings['denda_per_hari']['keterangan']) ?></span>
                </div>
                <div class="field">
                    <label><?= htmlspecialchars($settings['durasi_default']['label']) ?></label>
                    <input type="number" name="durasi_default" min="1" max="365"
                           value="<?= htmlspecialchars($settings['durasi_default']['nilai']) ?>" required>
                    <span class="hint"><?= htmlspecialchars($settings['durasi_default']['keterangan']) ?></span>
                </div>
                <div class="field">
                    <label><?= htmlspecialchars($settings['max_durasi']['label']) ?></label>
                    <input type="number" name="max_durasi" min="1" max="365"
                           value="<?= htmlspecialchars($settings['max_durasi']['nilai']) ?>" required>
                    <span class="hint"><?= htmlspecialchars($settings['max_durasi']['keterangan']) ?></span>
                </div>
            </div>
        </div>

        <!-- KEAMANAN -->
        <div class="section-card">
            <div class="section-title">🔒 Keamanan</div>
            <div class="setting-row">
                <div class="field">
                    <label><?= htmlspecialchars($settings['sesi_timeout']['label']) ?></label>
                    <input type="number" name="sesi_timeout" min="300"
                           value="<?= htmlspecialchars($settings['sesi_timeout']['nilai']) ?>" required>
                    <span class="hint"><?= htmlspecialchars($settings['sesi_timeout']['keterangan']) ?>. Contoh: 3600 = 1 jam, 7200 = 2 jam.</span>
                </div>
            </div>
        </div>

        <div class="form-footer">
            <button type="submit" class="btn-save" id="btn-save">
                <div class="spinner" id="spinner"></div>
                <span id="btn-text">💾 Simpan Pengaturan</span>
            </button>
        </div>
    </form>
</div>

<script>
function handleSubmit(e) {
    const btn     = document.getElementById('btn-save');
    const spinner = document.getElementById('spinner');
    const text    = document.getElementById('btn-text');
    btn.disabled  = true;
    spinner.style.display = 'block';
    text.textContent = 'Menyimpan...';
    // Biarkan form submit normal, hanya tampilkan loading state
}
</script>
</body>
</html>