<?php
require_once 'includes/config.php';

if (isLoggedIn()) redirect('/login.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim = trim($_POST['nim'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $jurusan = trim($_POST['jurusan'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($nim) || empty($nama) || empty($password)) {
        $error = 'NIM, Nama, dan Password wajib diisi.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        // Check if NIM exists
        $check = $conn->prepare("SELECT nim FROM mahasiswa WHERE nim = ?");
        $check->bind_param("s", $nim);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'NIM sudah terdaftar.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO mahasiswa (nim, nama, jurusan, email, angkatan, password) VALUES (?, ?, ?, ?, ?, ?)");
            $angkatan = substr($nim, 0, 2) ? '20' . substr($nim, 0, 2) : date('Y');
            $stmt->bind_param("ssssss", $nim, $nama, $jurusan, $email, $angkatan, $hash);
            if ($stmt->execute()) {
                $success = 'Akun berhasil dibuat! Silakan login.';
            } else {
                $error = 'Gagal membuat akun. Coba lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — Perpustakaan</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #1a1a14; --card: #242418; --border: #3a3a2a;
            --gold: #c9a84c; --green: #4caf82; --green-dark: #3a9168;
            --text: #e8e4d8; --text-muted: #8a8672; --input-bg: #1e1e18; --error: #e07070;
        }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 40px 36px; width: 100%; max-width: 440px; box-shadow: 0 32px 80px rgba(0,0,0,0.5); }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo h1 { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--gold); }
        .logo p { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 7px; }
        .field input, .field select { width: 100%; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; padding: 11px 14px; font-family: 'DM Sans', sans-serif; font-size: 14px; color: var(--text); outline: none; transition: border-color 0.2s; }
        .field input:focus, .field select:focus { border-color: var(--green); }
        .field input::placeholder { color: var(--text-muted); }
        .field select option { background: var(--card); }
        .btn { width: 100%; padding: 13px; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 15px; font-weight: 600; cursor: pointer; background: var(--green); color: #fff; margin-top: 4px; transition: background 0.2s; }
        .btn:hover { background: var(--green-dark); }
        .login-link { text-align: center; margin-top: 16px; font-size: 13px; color: var(--text-muted); }
        .login-link a { color: var(--gold); text-decoration: none; font-weight: 500; }
        .alert { padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .alert-error { background: rgba(224,112,112,0.15); border: 1px solid rgba(224,112,112,0.3); color: var(--error); }
        .alert-success { background: rgba(76,175,130,0.15); border: 1px solid rgba(76,175,130,0.3); color: var(--green); }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div style="font-size:36px;margin-bottom:10px">📚</div>
        <h1>Daftar Akun</h1>
        <p>Buat akun mahasiswa baru</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?> <a href="login.php" style="color:inherit;font-weight:700">Login sekarang →</a></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row">
            <div class="field">
                <label>NIM *</label>
                <input type="text" name="nim" placeholder="24024000" value="<?= htmlspecialchars($_POST['nim'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Jurusan</label>
                <input type="text" name="jurusan" placeholder="Teknik Informatika" value="<?= htmlspecialchars($_POST['jurusan'] ?? '') ?>">
            </div>
        </div>
        <div class="field">
            <label>Nama Lengkap *</label>
            <input type="text" name="nama" placeholder="Nama lengkap" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
        </div>
        <div class="field">
            <label>Email</label>
            <input type="email" name="email" placeholder="email@contoh.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="row">
            <div class="field">
                <label>Password *</label>
                <input type="password" name="password" placeholder="Min. 6 karakter">
            </div>
            <div class="field">
                <label>Konfirmasi</label>
                <input type="password" name="confirm_password" placeholder="Ulangi password">
            </div>
        </div>
        <button type="submit" class="btn">Buat Akun</button>
    </form>
    <div class="login-link">Sudah punya akun? <a href="login.php">Login di sini</a></div>
</div>
</body>
</html>
