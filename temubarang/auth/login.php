<?php
session_start();
require_once "../config/database.php";

$error = "";

if (isset($_SESSION["id_pengguna"])) {
    if (($_SESSION["role"] ?? "") === "admin") {
        header("Location: ../admin/index.php");
    } else {
        header("Location: ../pengguna/index.php");
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Username dan password wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_pengguna, nama_lengkap, username, password, role
             FROM pengguna
             WHERE username = ?
             LIMIT 1"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, $user["password"])) {
                session_regenerate_id(true);

                $_SESSION["id_pengguna"] = $user["id_pengguna"];
                $_SESSION["nama_lengkap"] = $user["nama_lengkap"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];

                if ($user["role"] === "admin") {
                    header("Location: ../admin/index.php");
                } else {
                    header("Location: ../pengguna/index.php");
                }
                exit;
            } else {
                $error = "Username atau password salah.";
            }
        } else {
            $error = "Terjadi kesalahan saat memproses login.";
        }
    }
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TemuBarang</title>

    <style>
        :root {
            --primary: #183B32;
            --primary-hover: #245443;
            --primary-light: #E3EEE8;
            --page-bg: #F4F6F4;
            --text-main: #222222;
            --text-muted: #68756E;
            --border: #DCE4DF;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: var(--page-bg);
            color: var(--text-main);
            font-family: Arial, sans-serif;
        }

        .login-box {
            width: 100%;
            max-width: 410px;
            padding: 32px;
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(24, 59, 50, 0.08);
        }

        .icon {
            width: 76px;
            height: 76px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            border-radius: 20px;
            background: var(--primary-light);
            font-size: 40px;
        }

        h2 {
            text-align: center;
            margin: 0 0 8px;
            color: var(--primary);
            font-size: 23px;
        }

        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 25px;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-size: 14px;
            font-weight: bold;
            color: var(--text-main);
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #FFFFFF;
            color: var(--text-main);
            font-size: 15px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(24, 59, 50, 0.12);
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: var(--primary);
            color: #FFFFFF;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: var(--primary-hover);
        }

        .error {
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #F2C5C5;
            border-radius: 8px;
            background: #FFF0F0;
            color: #A52A2A;
            font-size: 14px;
            line-height: 1.5;
        }

        .links {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            line-height: 2;
            color: var(--text-muted);
        }

        a {
            color: var(--primary);
            font-weight: bold;
            text-decoration: none;
        }

        a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        .footer {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.5;
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 25px 22px;
            }
        }
    </style>
</head>

<body>
    <div class="login-box">
        <div class="icon">📦</div>

        <h2>LOGIN TEMUBARANG</h2>
        <p class="subtitle">
            Masuk untuk melaporkan atau menemukan barang.
        </p>

        <?php if ($error !== ""): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                placeholder="Masukkan username"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Masukkan password"
                autocomplete="current-password"
                required
            >

            <button type="submit">LOGIN</button>
        </form>

        <div class="links">
            Belum punya akun?
            <a href="register.php">Daftar sekarang</a>
            <br>
            <a href="../index.php">← Kembali ke halaman utama</a>
        </div>

        <div class="footer">
            Sistem Informasi Kehilangan dan Temuan Barang
        </div>
    </div>
</body>
</html>