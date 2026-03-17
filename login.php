<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) redirect('/admin/dashboard.php');
    else redirect('/user/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'mahasiswa';
    $password = $_POST['password'] ?? '';

    if ($role === 'admin') {
        $id_admin = trim($_POST['id_admin'] ?? '');
        if (empty($id_admin) || empty($password)) {
            $error = 'ID Admin dan password wajib diisi.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM admin WHERE id_admin = ?");
            $stmt->bind_param("s", $id_admin);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['user_name'] = $admin['nama'];
                $_SESSION['role'] = 'admin';
                $_SESSION['id_admin'] = $admin['id_admin'];
                redirect('/admin/dashboard.php');
            } else {
                $error = 'ID Admin atau password salah.';
            }
        }
    } else {
        $nim = trim($_POST['nim'] ?? '');
        if (empty($nim) || empty($password)) {
            $error = 'NIM dan password wajib diisi.';
        } else {
            $stmt = $conn->prepare("SELECT m.*, a.id as anggota_id, a.status as status_anggota FROM mahasiswa m LEFT JOIN anggota a ON m.nim = a.nim WHERE m.nim = ?");
            $stmt->bind_param("s", $nim);
            $stmt->execute();
            $result = $stmt->get_result();
            $mhs = $result->fetch_assoc();

            if ($mhs && password_verify($password, $mhs['password'])) {
                $_SESSION['user_id'] = $mhs['nim'];
                $_SESSION['user_name'] = $mhs['nama'];
                $_SESSION['role'] = 'mahasiswa';
                $_SESSION['nim'] = $mhs['nim'];
                $_SESSION['anggota_id'] = $mhs['anggota_id'];
                redirect('/user/dashboard.php');
            } else {
                $error = 'NIM atau password salah.';
            }
        }
    }
}

$tab = $_GET['tab'] ?? 'mahasiswa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Perpustakaan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-image: 
                radial-gradient(ellipse at 20% 50%, rgba(201,168,76,0.06) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(76,175,130,0.05) 0%, transparent 40%);
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.5);
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo-icon {
            font-size: 42px;
            line-height: 1;
            display: block;
            margin-bottom: 12px;
        }

        .logo h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--gold);
            letter-spacing: 0.5px;
        }

        .logo p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .tab-switcher {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--input-bg);
            border-radius: 10px;
            padding: 4px;
            margin: 24px 0;
            border: 1px solid var(--border);
        }

        .tab-btn {
            padding: 10px;
            border: none;
            background: transparent;
            border-radius: 7px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            color: var(--text-muted);
        }

        .tab-btn.active-mahasiswa {
            background: var(--green);
            color: #fff;
        }

        .tab-btn.active-admin {
            background: var(--gold);
            color: #1a1a14;
        }

        .form-section { display: none; }
        .form-section.active { display: block; }

        .field { margin-bottom: 18px; }

        .field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .field input {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: var(--text);
            transition: border-color 0.2s;
            outline: none;
        }

        .field input:focus {
            border-color: var(--gold);
        }

        .field input::placeholder { color: var(--text-muted); }

        .btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 4px;
        }

        .btn-mahasiswa {
            background: var(--green);
            color: #fff;
        }
        .btn-mahasiswa:hover { background: var(--green-dark); }

        .btn-admin {
            background: var(--gold);
            color: #1a1a14;
        }
        .btn-admin:hover { background: var(--gold-light); }

        .register-link {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .register-link a {
            color: var(--green);
            text-decoration: none;
            font-weight: 500;
        }

        .hint-box {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .hint-box span { color: var(--gold); font-weight: 600; }

        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .alert-error {
            background: rgba(224,112,112,0.15);
            border: 1px solid rgba(224,112,112,0.3);
            color: var(--error);
        }

        .alert-success {
            background: rgba(76,175,130,0.15);
            border: 1px solid rgba(76,175,130,0.3);
            color: var(--green);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
        }
        .badge-admin { background: rgba(201,168,76,0.2); color: var(--gold); }
        .badge-mhs { background: rgba(76,175,130,0.2); color: var(--green); }
    </style>
</head>
<body>

<div class="card">
    <div class="logo">
        <span class="logo-icon">📚</span>
        <h1>Perpustakaan</h1>
        <p>Politeknik Negeri Manado</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="tab-switcher">
        <button class="tab-btn <?= $tab === 'mahasiswa' ? 'active-mahasiswa' : '' ?>" onclick="switchTab('mahasiswa')">
            👤 Mahasiswa
        </button>
        <button class="tab-btn <?= $tab === 'admin' ? 'active-admin' : '' ?>" onclick="switchTab('admin')">
            🛡️ Admin
        </button>
    </div>

    <!-- Form Mahasiswa -->
    <div id="form-mahasiswa" class="form-section <?= $tab !== 'admin' ? 'active' : '' ?>">
        <form method="POST" action="?tab=mahasiswa">
            <input type="hidden" name="role" value="mahasiswa">
            <div class="field">
                <label>NIM</label>
                <input type="text" name="nim" placeholder="Masukan NIM" value="<?= htmlspecialchars($_POST['nim'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password">
            </div>
            <button type="submit" class="btn btn-mahasiswa">Masuk sebagai Mahasiswa</button>
        </form>
        <div class="register-link">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
        </div>
    </div>

    <!-- Form Admin -->
    <div id="form-admin" class="form-section <?= $tab === 'admin' ? 'active' : '' ?>">
        <form method="POST" action="?tab=admin">
            <input type="hidden" name="role" value="admin">
            <div class="field">
                <label>Username</label>
                <input type="text" name="id_admin" placeholder="Masukan Username" value="<?= htmlspecialchars($_POST['id_admin'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password">
            </div>
            <button type="submit" class="btn btn-admin">Masuk sebagai Admin</button>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('form-mahasiswa').classList.remove('active');
    document.getElementById('form-admin').classList.remove('active');
    document.getElementById('form-' + tab).classList.add('active');

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.className = 'tab-btn';
    });

    if (tab === 'mahasiswa') {
        document.querySelectorAll('.tab-btn')[0].className = 'tab-btn active-mahasiswa';
    } else {
        document.querySelectorAll('.tab-btn')[1].className = 'tab-btn active-admin';
    }
}
</script>
</body>
</html>