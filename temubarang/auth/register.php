<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$nama = '';
$username = '';
$kelas = '';

if (isset($_SESSION['id_pengguna'])) {
    header('Location: ../pengguna/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    if ($nama === '' || $username === '' || $kelas === '' ||
        $password === '' || $konfirmasi === '') {
        $error = 'Semua kolom wajib diisi.';
    } elseif (mb_strlen($nama) > 100) {
        $error = 'Nama maksimal 100 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
        $error = 'Username harus 4–50 karakter dan hanya boleh berisi huruf, angka, atau underscore.';
    } elseif (mb_strlen($kelas) > 30) {
        $error = 'Nama kelas maksimal 30 karakter.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif (strlen($password) > 72) {
        $error = 'Password terlalu panjang. Maksimal 72 byte.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        $check = mysqli_prepare(
            $conn,
            'SELECT id_pengguna FROM pengguna WHERE username = ? LIMIT 1'
        );

        mysqli_stmt_bind_param($check, 's', $username);
        mysqli_stmt_execute($check);
        $existing = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($existing) > 0) {
            $error = 'Username sudah digunakan. Coba username lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'pengguna';

            $insert = mysqli_prepare(
                $conn,
                'INSERT INTO pengguna
                    (nama_lengkap, username, password, kelas, role)
                 VALUES (?, ?, ?, ?, ?)'
            );

            mysqli_stmt_bind_param(
                $insert,
                'sssss',
                $nama,
                $username,
                $hash,
                $kelas,
                $role
            );

            if (mysqli_stmt_execute($insert)) {
                header('Location: login.php?registered=1');
                exit;
            }

            $error = 'Registrasi gagal. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun — TemuBarang</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            min-height: 100vh;
            background: #f5f8f5;
        }

        .auth-navbar {
            padding: 18px 28px;
            background: white;
            border-bottom: 1px solid #e7ece8;
        }

        .auth-navbar-inner {
            max-width: 1150px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .auth-navbar a:last-child {
            color: #176b55;
            font-size: 13px;
            font-weight: 700;
        }

        .auth-wrapper {
            max-width: 490px;
            margin: 45px auto;
            padding: 0 18px;
        }

        .auth-card {
            padding: 34px;
            background: white;
            border: 1px solid #e7ece8;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(28, 66, 43, .05);
        }

        .auth-symbol {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            color: white;
            background: #176b55;
            border-radius: 14px;
            font-size: 25px;
        }

        .auth-card h1 {
            margin-top: 20px;
            font-family: 'Manrope', sans-serif;
            font-size: 28px;
            letter-spacing: -.8px;
        }

        .auth-subtitle {
            margin: 7px 0 25px;
            color: #77857f;
            font-size: 13px;
            line-height: 1.8;
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 12px;
            font-weight: 700;
        }

        .form-group input {
            width: 100%;
            min-height: 46px;
            padding: 12px 13px;
            border: 1px solid #dce5de;
            border-radius: 9px;
            outline: none;
            color: #1e302b;
            background: white;
            font-size: 13px;
        }

        .form-group input:focus {
            border-color: #176b55;
            box-shadow: 0 0 0 3px rgba(23, 107, 85, .08);
        }

        .form-hint {
            display: block;
            margin-top: 6px;
            color: #829087;
            font-size: 10px;
        }

        .auth-error {
            margin-bottom: 18px;
            padding: 12px;
            color: #a23d31;
            background: #fff0ed;
            border: 1px solid #f5d5cf;
            border-radius: 9px;
            font-size: 12px;
            line-height: 1.7;
        }

        .auth-submit {
            width: 100%;
            margin-top: 5px;
        }

        .auth-switch {
            margin-top: 22px;
            text-align: center;
            color: #77857f;
            font-size: 12px;
        }

        .auth-switch a {
            color: #176b55;
            font-weight: 800;
        }

        .auth-footer {
            margin-top: 25px;
            text-align: center;
            color: #829087;
            font-size: 11px;
        }

        @media (max-width: 480px) {
            .auth-navbar {
                padding: 15px 18px;
            }

            .auth-card {
                padding: 25px 20px;
            }

            .auth-card h1 {
                font-size: 25px;
            }
        }
    </style>
</head>
<body>

<header class="auth-navbar">
    <div class="auth-navbar-inner">
        <a href="../index.php" class="brand">
            <span class="brand-icon">T</span>
            <span>TemuBarang<span class="brand-dot">.</span></span>
        </a>
        <a href="../index.php">← Kembali ke beranda</a>
    </div>
</header>

<main class="auth-wrapper">
    <section class="auth-card">
        <div class="auth-symbol">＋</div>

        <h1>Buat akun baru</h1>
        <p class="auth-subtitle">
            Bergabung dengan TemuBarang untuk melaporkan barang hilang,
            membagikan temuan, dan mengajukan klaim.
        </p>

        <?php if ($error !== ''): ?>
            <div class="auth-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="nama_lengkap">Nama lengkap</label>
                <input
                    type="text"
                    id="nama_lengkap"
                    name="nama_lengkap"
                    value="<?= e($nama) ?>"
                    maxlength="100"
                    autocomplete="name"
                    placeholder="Masukkan nama lengkap"
                    required
                >
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= e($username) ?>"
                    minlength="4"
                    maxlength="50"
                    pattern="[a-zA-Z0-9_]{4,50}"
                    autocomplete="username"
                    placeholder="Contoh: malik_123"
                    required
                >
                <small class="form-hint">
                    Minimal 4 karakter. Gunakan huruf, angka, atau underscore.
                </small>
            </div>

            <div class="form-group">
                <label for="kelas">Kelas</label>
                <input
                    type="text"
                    id="kelas"
                    name="kelas"
                    value="<?= e($kelas) ?>"
                    maxlength="30"
                    placeholder="Contoh: XI RPL 1"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                    required
                >
            </div>

            <div class="form-group">
                <label for="konfirmasi_password">Konfirmasi password</label>
                <input
                    type="password"
                    id="konfirmasi_password"
                    name="konfirmasi_password"
                    minlength="8"
                    maxlength="72"
                    autocomplete="new-password"
                    placeholder="Masukkan password sekali lagi"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary auth-submit">
                Daftar Sekarang →
            </button>
        </form>

        <p class="auth-switch">
            Sudah punya akun?
            <a href="login.php">Masuk di sini</a>
        </p>
    </section>

    <p class="auth-footer">
        © <?= date('Y') ?> TemuBarang · Lingkungan sekolah yang lebih peduli.
    </p>
</main>

</body>
</html>