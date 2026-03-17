<?php
require_once '../includes/config.php';
requireAdmin();

$msg   = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Tambah admin ─────────────────────────────────────────────
    if ($action === 'tambah') {
        $id_admin = trim($_POST['id_admin']);
        $nama     = trim($_POST['nama']);
        $password = $_POST['password'];
        $confirm  = $_POST['confirm_password'];

        if (empty($id_admin) || empty($nama) || empty($password)) {
            $error = 'ID Admin, Nama, dan Password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            // Cek apakah ID sudah ada
            $cek = $conn->prepare("SELECT id FROM admin WHERE id_admin = ?");
            $cek->bind_param("s", $id_admin);
            $cek->execute();
            if ($cek->get_result()->num_rows > 0) {
                $error = 'ID Admin sudah digunakan, pilih ID lain.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admin (id_admin, nama, password) VALUES (?,?,?)");
                $stmt->bind_param("sss", $id_admin, $nama, $hash);
                if ($stmt->execute()) {
                    $msg = "Akun admin \"$nama\" (ID: $id_admin) berhasil dibuat.";
                } else {
                    $error = 'Gagal membuat akun: ' . $conn->error;
                }
            }
        }
    }

    // ── Hapus admin ──────────────────────────────────────────────
    if ($action === 'hapus') {
        $id = (int)$_POST['id'];
        // Jangan hapus akun diri sendiri
        if ($id === (int)$_SESSION['user_id']) {
            $error = 'Tidak bisa menghapus akun Anda sendiri.';
        } else {
            $stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) $msg = 'Akun admin berhasil dihapus.';
            else $error = 'Gagal menghapus: ' . $conn->error;
        }
    }

    // ── Ganti password ───────────────────────────────────────────
    if ($action === 'ganti_password') {
        $id       = (int)$_POST['id'];
        $new_pass = $_POST['new_password'];
        $confirm  = $_POST['confirm_new'];

        if (strlen($new_pass) < 6) {
            $error = 'Password minimal 6 karakter.';
        } elseif ($new_pass !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin SET password=? WHERE id=?");
            $stmt->bind_param("si", $hash, $id);
            if ($stmt->execute()) $msg = 'Password admin berhasil diperbarui.';
            else $error = 'Gagal memperbarui password.';
        }
    }
}

$admin_list = $conn->query("SELECT * FROM admin ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akun Admin — Perpustakaan</title>
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
        .main { padding: 32px 28px; max-width: 960px; margin: 0 auto; }

        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 12px; }
        .page-title  { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); }
        .page-sub    { font-size: 13px; color: var(--text-muted); margin-top: 4px; }

        .alert { padding: 13px 16px; border-radius: 9px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: rgba(76,175,130,0.12); border: 1px solid rgba(76,175,130,0.35); color: var(--green); }
        .alert-error   { background: rgba(224,112,112,0.12); border: 1px solid rgba(224,112,112,0.35); color: var(--error); }

        /* Form tambah */
        .form-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 28px 28px 24px; margin-bottom: 28px; }
        .form-title { font-size: 16px; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        .field { margin-bottom: 0; }
        .field label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1.1px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 7px; }
        .field input { width: 100%; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; padding: 11px 13px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .field input:focus { border-color: var(--green); }
        .field input::placeholder { color: var(--text-muted); }
        .field .hint { font-size: 11px; color: var(--text-muted); margin-top: 5px; }

        .form-footer { display: flex; justify-content: flex-end; margin-top: 20px; }
        .btn-green { padding: 11px 22px; background: var(--green); color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-green:hover { background: var(--green-dark); }

        /* Divider */
        .divider { height: 1px; background: var(--border); margin: 24px 0; }

        /* Tabel admin */
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e1e18; padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        td { padding: 13px 16px; font-size: 14px; border-bottom: 1px solid rgba(58,58,42,0.5); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.015); }

        .you-badge { font-size: 10px; background: rgba(201,168,76,0.2); color: var(--gold); padding: 2px 7px; border-radius: 10px; font-weight: 700; margin-left: 6px; vertical-align: middle; }

        /* Buttons */
        .btn-sm   { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; font-family: 'DM Sans', sans-serif; }
        .btn-pass { background: rgba(201,168,76,0.2); color: var(--gold); }
        .btn-pass:hover { background: rgba(201,168,76,0.35); }
        .btn-del  { background: rgba(224,112,112,0.2); color: var(--error); }
        .btn-del:hover  { background: rgba(224,112,112,0.35); }
        .btn-disabled { background: rgba(138,134,114,0.1); color: var(--text-muted); cursor: not-allowed; }
        .td-actions { display: flex; gap: 8px; }

        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.72); z-index: 200; align-items: center; justify-content: center; padding: 20px; }
        .modal.open { display: flex; }
        .modal-box { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 32px; width: 100%; max-width: 400px; }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 20px; margin-bottom: 20px; }
        .modal-field { margin-bottom: 14px; }
        .modal-field label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        .modal-field input { width: 100%; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; }
        .modal-field input:focus { border-color: var(--green); }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { padding: 10px 18px; background: transparent; border: 1px solid var(--border); color: var(--text-muted); border-radius: 8px; cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: 14px; }
        .warn-text { font-size: 13px; color: var(--error); background: rgba(224,112,112,0.08); border: 1px solid rgba(224,112,112,0.2); border-radius: 8px; padding: 11px 13px; margin-bottom: 16px; }

        /* Password strength */
        .strength-bar { height: 4px; border-radius: 2px; margin-top: 6px; transition: all 0.3s; background: var(--border); }
        .strength-text { font-size: 11px; margin-top: 4px; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">

    <div class="page-header">
        <div>
            <div class="page-title">🛡️ Kelola Akun Admin</div>
            <div class="page-sub">Tambah dan kelola akun admin perpustakaan</div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ── Form Tambah Admin ─────────────────────────────────── -->
    <div class="form-card">
        <div class="form-title">➕ Tambah Akun Admin Baru</div>
        <form method="POST">
            <input type="hidden" name="action" value="tambah">
            <div class="row2" style="margin-bottom:14px">
                <div class="field">
                    <label>ID Admin *</label>
                    <input type="text" name="id_admin"
                           placeholder="Contoh: admin002"
                           value="<?= htmlspecialchars($_POST['id_admin'] ?? '') ?>"
                           required>
                    <div class="hint">Digunakan untuk login, tidak bisa diubah</div>
                </div>
                <div class="field">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="nama"
                           placeholder="Nama admin"
                           value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>"
                           required>
                </div>
            </div>
            <div class="row2">
                <div class="field">
                    <label>Password *</label>
                    <input type="password" name="password" id="new_pass"
                           placeholder="Min. 6 karakter"
                           oninput="checkStrength(this.value)"
                           required>
                    <div class="strength-bar" id="strength-bar"></div>
                    <div class="strength-text" id="strength-text"></div>
                </div>
                <div class="field">
                    <label>Konfirmasi Password *</label>
                    <input type="password" name="confirm_password" id="confirm_pass"
                           placeholder="Ulangi password"
                           oninput="checkMatch()"
                           required>
                    <div class="strength-text" id="match-text"></div>
                </div>
            </div>
            <div class="form-footer">
                <button type="submit" class="btn-green">✅ Buat Akun Admin</button>
            </div>
        </form>
    </div>

    <!-- ── Daftar Admin ──────────────────────────────────────── -->
    <div class="section-title">👥 Daftar Akun Admin (<?= $admin_list->num_rows ?>)</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ID Admin</th>
                    <th>Nama</th>
                    <th>Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = $admin_list->fetch_assoc()):
                    $isSelf = ($a['id'] == $_SESSION['user_id']);
                ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td>
                        <code style="color:var(--gold);font-size:13px"><?= htmlspecialchars($a['id_admin']) ?></code>
                        <?php if ($isSelf): ?>
                            <span class="you-badge">Anda</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($a['nama']) ?></strong></td>
                    <td style="color:var(--text-muted);font-size:12px"><?= date('d M Y, H:i', strtotime($a['created_at'])) ?></td>
                    <td>
                        <div class="td-actions">
                            <!-- Ganti password -->
                            <button class="btn-sm btn-pass"
                                onclick="openGantiPass(<?= $a['id'] ?>, '<?= htmlspecialchars($a['nama'], ENT_QUOTES) ?>')">
                                🔑 Ganti Password
                            </button>
                            <!-- Hapus -->
                            <?php if ($isSelf): ?>
                                <button class="btn-sm btn-disabled" disabled title="Tidak bisa hapus akun sendiri">🗑️ Hapus</button>
                            <?php else: ?>
                                <button class="btn-sm btn-del"
                                    onclick="openHapus(<?= $a['id'] ?>, '<?= htmlspecialchars($a['nama'], ENT_QUOTES) ?>')">
                                    🗑️ Hapus
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- ── Modal Ganti Password ──────────────────────────────────── -->
<div id="modal-pass" class="modal">
    <div class="modal-box">
        <div class="modal-title" style="color:var(--gold)">🔑 Ganti Password</div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
            Admin: <strong id="pass_nama" style="color:var(--text)"></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="ganti_password">
            <input type="hidden" name="id" id="pass_id">
            <div class="modal-field">
                <label>Password Baru *</label>
                <input type="password" name="new_password" placeholder="Min. 6 karakter" required>
            </div>
            <div class="modal-field">
                <label>Konfirmasi Password *</label>
                <input type="password" name="confirm_new" placeholder="Ulangi password baru" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-pass')">Batal</button>
                <button type="submit" class="btn-green" style="padding:10px 20px;font-size:14px">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal Konfirmasi Hapus ────────────────────────────────── -->
<div id="modal-hapus" class="modal">
    <div class="modal-box">
        <div class="modal-title" style="color:var(--error)">🗑️ Hapus Akun Admin</div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px">Anda akan menghapus akun:</p>
        <p id="hapus_nama" style="font-size:16px;font-weight:600;margin-bottom:16px"></p>
        <div class="warn-text">
            ⚠️ Akun admin ini akan dihapus permanen dan tidak bisa login lagi. Tindakan ini tidak bisa dibatalkan.
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="id" id="hapus_id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-hapus')">Batal</button>
                <button type="submit" class="btn-sm btn-del" style="padding:10px 20px;font-size:14px">Ya, Hapus</button>
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

function openGantiPass(id, nama) {
    document.getElementById('pass_id').value = id;
    document.getElementById('pass_nama').textContent = nama;
    openModal('modal-pass');
}

function openHapus(id, nama) {
    document.getElementById('hapus_id').value = id;
    document.getElementById('hapus_nama').textContent = nama;
    openModal('modal-hapus');
}

// Password strength checker
function checkStrength(val) {
    const bar  = document.getElementById('strength-bar');
    const text = document.getElementById('strength-text');
    if (!val) { bar.style.width='0'; bar.style.background=''; text.textContent=''; return; }
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { color:'#e07070', label:'Sangat Lemah' },
        { color:'#e07070', label:'Lemah' },
        { color:'#c9a84c', label:'Cukup' },
        { color:'#4caf82', label:'Kuat' },
        { color:'#4caf82', label:'Sangat Kuat' },
    ];
    const l = levels[Math.min(score, 4)];
    bar.style.width  = ((score / 5) * 100) + '%';
    bar.style.background = l.color;
    text.style.color = l.color;
    text.textContent = l.label;
    checkMatch();
}

function checkMatch() {
    const p1   = document.getElementById('new_pass')?.value || document.querySelector('[name="password"]')?.value || '';
    const p2   = document.getElementById('confirm_pass')?.value || '';
    const text = document.getElementById('match-text');
    if (!text || !p2) return;
    if (p1 === p2) {
        text.style.color = '#4caf82';
        text.textContent = '✓ Password cocok';
    } else {
        text.style.color = '#e07070';
        text.textContent = '✗ Password tidak cocok';
    }
}
</script>
</body>
</html>