<?php
require_once '../includes/config.php';
requireMahasiswa();

$nim = $_SESSION['nim'];
$msg = ''; $error = '';
$msg_profil = ''; $error_profil = '';

// Proses ganti password
if (isset($_POST['update_password'])) {
    $old     = $_POST['old_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $mhs_pw = $conn->query("SELECT password FROM mahasiswa WHERE nim='$nim'")->fetch_assoc();

    if (!password_verify($old, $mhs_pw['password'])) {
        $error = 'Password lama tidak sesuai.';
    } elseif (strlen($new) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($new !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $conn->query("UPDATE mahasiswa SET password='$hash' WHERE nim='$nim'");
        $msg = 'Password berhasil diperbarui.';
    }
}

// Proses ubah profil
if (isset($_POST['update_profil'])) {
    $nama    = trim($_POST['nama']);
    $jurusan = trim($_POST['jurusan']);
    $email   = trim($_POST['email']);
    $no_telp = trim($_POST['no_telp']);

    if (empty($nama) || empty($email)) {
        $error_profil = 'Nama dan email tidak boleh kosong.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_profil = 'Format email tidak valid.';
    } else {
        $stmt = $conn->prepare("UPDATE mahasiswa SET nama=?, jurusan=?, email=?, no_telp=? WHERE nim=?");
        $stmt->bind_param("sssss", $nama, $jurusan, $email, $no_telp, $nim);
        $stmt->execute();
        $stmt->close();
        $msg_profil = 'Profil berhasil diperbarui.';
    }
}

$mhs = $conn->query("SELECT m.*, a.status as status_anggota, a.masa_berlaku, a.tanggal_daftar FROM mahasiswa m LEFT JOIN anggota a ON m.nim=a.nim WHERE m.nim='$nim'")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #1a1a14; --card: #242418; --border: #3a3a2a;
            --gold: #c9a84c; --green: #4caf82; --green-dark: #3a9168;
            --text: #e8e4d8; --text-muted: #8a8672; --input-bg: #1e1e18;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        .main { padding: 32px 28px; max-width: 800px; margin: 0 auto; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); margin-bottom: 24px; }

        /* Grid atas: 2 kolom */
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        /* Kartu ubah profil: full width di bawah */
        .profil-card { margin-top: 20px; }

        .info-card, .pass-card, .edit-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }

        .card-title { font-size: 15px; font-weight: 600; margin-bottom: 18px; color: var(--text); }

        /* Info rows */
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(58,58,42,0.5); font-size: 14px; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); }
        .info-val { color: var(--text); font-weight: 500; text-align: right; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .b-aktif    { background: rgba(76,175,130,0.2); color: var(--green); }
        .b-nonaktif { background: rgba(224,112,112,0.2); color: #e07070; }
        .b-none     { background: rgba(138,134,114,0.2); color: var(--text-muted); }

        /* Form fields */
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
        .field input {
            width: 100%; background: var(--input-bg);
            border: 1px solid var(--border); border-radius: 8px;
            padding: 10px 12px; color: var(--text);
            font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none;
            transition: border-color 0.2s;
        }
        .field input:focus { border-color: var(--green); }
        .field input[readonly] { opacity: 0.5; cursor: not-allowed; }

        /* Grid 2 kolom di dalam edit-card */
        .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }

        .btn {
            padding: 11px 24px;
            background: var(--green); color: #fff;
            border: none; border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: background 0.2s;
        }
        .btn:hover { background: var(--green-dark); }
        .btn-full { width: 100%; margin-top: 4px; }

        /* Alerts */
        .alert { padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .alert-success { background: rgba(76,175,130,0.15); border: 1px solid rgba(76,175,130,0.3); color: var(--green); }
        .alert-error   { background: rgba(224,112,112,0.15); border: 1px solid rgba(224,112,112,0.3); color: #e07070; }

        .avatar { width: 64px; height: 64px; background: rgba(76,175,130,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 14px; }

        .divider { height: 1px; background: var(--border); margin: 18px 0; }

        .edit-card-footer { display: flex; justify-content: flex-end; margin-top: 4px; }

        @media (max-width: 640px) {
            .grid2 { grid-template-columns: 1fr; }
            .field-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main">
    <div class="page-title">👤 Profil Saya</div>

    <?php if ($msg): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Baris atas: Info akun + Ganti password -->
    <div class="grid2">

        <!-- Kartu Info Akun -->
        <div class="info-card">
            <div class="avatar">🎓</div>
            <div class="card-title">Informasi Akun</div>
            <div class="info-row">
                <span class="info-label">NIM</span>
                <span class="info-val" style="color:var(--gold);font-family:monospace"><?= htmlspecialchars($mhs['nim']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Nama</span>
                <span class="info-val"><?= htmlspecialchars($mhs['nama']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Jurusan</span>
                <span class="info-val"><?= htmlspecialchars($mhs['jurusan']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Angkatan</span>
                <span class="info-val"><?= htmlspecialchars($mhs['angkatan']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-val"><?= htmlspecialchars($mhs['email']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">No. Telepon</span>
                <span class="info-val"><?= htmlspecialchars($mhs['no_telp'] ?? '-') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Keanggotaan</span>
                <span class="info-val">
                    <?php if ($mhs['status_anggota']): ?>
                        <span class="badge b-<?= $mhs['status_anggota'] ?>"><?= ucfirst($mhs['status_anggota']) ?></span>
                    <?php else: ?>
                        <span class="badge b-none">Belum Daftar</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($mhs['masa_berlaku']): ?>
            <div class="info-row">
                <span class="info-label">Masa Berlaku</span>
                <span class="info-val"><?= htmlspecialchars($mhs['masa_berlaku']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Kartu Ganti Password -->
        <div class="pass-card">
            <div class="card-title">🔒 Ganti Password</div>
            <form method="POST">
                <div class="field">
                    <label>Password Lama</label>
                    <input type="password" name="old_password" placeholder="Password saat ini">
                </div>
                <div class="field">
                    <label>Password Baru</label>
                    <input type="password" name="new_password" placeholder="Min. 6 karakter">
                </div>
                <div class="field">
                    <label>Konfirmasi</label>
                    <input type="password" name="confirm_password" placeholder="Ulangi password baru">
                </div>
                <button type="submit" name="update_password" class="btn btn-full">Perbarui Password</button>
            </form>
        </div>

    </div><!-- /grid2 -->

    <!-- =============================================
         KARTU UBAH PROFIL — ditambahkan di bawah
         ============================================= -->
    <div class="edit-card profil-card">

        <?php if ($msg_profil): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($msg_profil) ?></div>
        <?php endif; ?>
        <?php if ($error_profil): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error_profil) ?></div>
        <?php endif; ?>

        <div class="card-title">✏️ Ubah Profil</div>

        <form method="POST">
            <div class="field-grid">
                <!-- NIM: readonly, tidak bisa diubah -->
                <div class="field">
                    <label>NIM</label>
                    <input type="text" value="<?= htmlspecialchars($mhs['nim']) ?>" readonly title="NIM tidak dapat diubah">
                </div>
                <!-- Angkatan: readonly -->
                <div class="field">
                    <label>Angkatan</label>
                    <input type="text" value="<?= htmlspecialchars($mhs['angkatan']) ?>" readonly title="Angkatan tidak dapat diubah">
                </div>
                <div class="field">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" placeholder="Nama lengkap" value="<?= htmlspecialchars($mhs['nama']) ?>" required>
                </div>
                <div class="field">
                    <label>Jurusan</label>
                    <input type="text" name="jurusan" placeholder="Program studi / jurusan" value="<?= htmlspecialchars($mhs['jurusan'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="contoh@email.com" value="<?= htmlspecialchars($mhs['email'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label>No. Telepon</label>
                    <input type="text" name="no_telp" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($mhs['no_telp'] ?? '') ?>">
                </div>
            </div>

            <div class="divider"></div>

            <div class="edit-card-footer">
                <button type="submit" name="update_profil" class="btn">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>
    <!-- /KARTU UBAH PROFIL -->

</div>
</body>
</html>