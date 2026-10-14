<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION["id_pengguna"])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION["role"] ?? "") !== "pengguna") {
    header("Location: ../admin/index.php");
    exit;
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";

$jenis = "";
$nama_barang = "";
$kategori = "";
$deskripsi = "";
$lokasi = "";
$tanggal_kejadian = "";

$kategoriList = [
    "Elektronik",
    "Alat Tulis",
    "Pakaian",
    "Aksesori",
    "Kartu Identitas",
    "Tas",
    "Kunci",
    "Lainnya"
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } else {
        $jenis = $_POST["jenis"] ?? "";
        $nama_barang = trim($_POST["nama_barang"] ?? "");
        $kategori = $_POST["kategori"] ?? "";
        $deskripsi = trim($_POST["deskripsi"] ?? "");
        $lokasi = trim($_POST["lokasi"] ?? "");
        $tanggal_kejadian = $_POST["tanggal_kejadian"] ?? "";

        if (!in_array($jenis, ["hilang", "temuan"], true)) {
            $error = "Pilih jenis laporan yang benar.";
        } elseif ($nama_barang === "" || strlen($nama_barang) > 150) {
            $error = "Nama barang wajib diisi, maksimal 150 karakter.";
        } elseif (!in_array($kategori, $kategoriList, true)) {
            $error = "Pilih kategori barang yang tersedia.";
        } elseif ($deskripsi === "") {
            $error = "Deskripsi barang wajib diisi.";
        } elseif ($lokasi === "" || strlen($lokasi) > 150) {
            $error = "Lokasi wajib diisi, maksimal 150 karakter.";
        } elseif (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_kejadian) ||
            !checkdate(
                (int) substr($tanggal_kejadian, 5, 2),
                (int) substr($tanggal_kejadian, 8, 2),
                (int) substr($tanggal_kejadian, 0, 4)
            ) ||
            $tanggal_kejadian > date("Y-m-d")
        ) {
            $error = "Tanggal kejadian tidak valid.";
        }

        $namaFoto = null;
        $fotoDipindahkan = false;

        if ($error === "" && isset($_FILES["foto"]) &&
            $_FILES["foto"]["error"] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
                $error = "Foto gagal diunggah.";
            } elseif ($_FILES["foto"]["size"] > 2 * 1024 * 1024) {
                $error = "Ukuran foto maksimal 2 MB.";
            } else {
                $tmp = $_FILES["foto"]["tmp_name"];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmp);

                $allowed = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($allowed[$mime]) || getimagesize($tmp) === false) {
                    $error = "Foto harus berformat JPG, PNG, atau WEBP.";
                } else {
                    $folder = "../assets/uploads/barang/";

                    if (!is_dir($folder) &&
                        !mkdir($folder, 0755, true) &&
                        !is_dir($folder)) {
                        $error = "Folder penyimpanan foto gagal dibuat.";
                    } else {
                        $namaFoto = bin2hex(random_bytes(16))
                            . "." . $allowed[$mime];

                        if (!move_uploaded_file(
                            $tmp,
                            $folder . $namaFoto
                        )) {
                            $error = "Foto gagal disimpan.";
                        } else {
                            $fotoDipindahkan = true;
                        }
                    }
                }
            }
        }

        if ($error === "") {
            $idPengguna = (int) $_SESSION["id_pengguna"];

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO laporan
                (id_pengguna, jenis, nama_barang, kategori,
                 deskripsi, lokasi, tanggal_kejadian, foto, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );

            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt,
                    "isssssss",
                    $idPengguna,
                    $jenis,
                    $nama_barang,
                    $kategori,
                    $deskripsi,
                    $lokasi,
                    $tanggal_kejadian,
                    $namaFoto
                );

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    unset($_SESSION["csrf_token"]);

                    header("Location: riwayat.php?success=1");
                    exit;
                } else {
                    $error = "Laporan gagal disimpan. Silakan coba lagi.";
                    mysqli_stmt_close($stmt);

                    if ($fotoDipindahkan && $namaFoto) {
                        @unlink("../assets/uploads/barang/" . $namaFoto);
                    }
                }
            } else {
                $error = "Terjadi kesalahan saat menyiapkan laporan.";

                if ($fotoDipindahkan && $namaFoto) {
                    @unlink("../assets/uploads/barang/" . $namaFoto);
                }
            }
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
    <title>Buat Laporan | TemuBarang</title>

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
    background: var(--page-bg);
    color: var(--text-main);
    font-family: Arial, sans-serif;
}

/* Header TemuBarang */
header {
    background: var(--primary);
    color: #FFFFFF;
    padding: 20px;
}

header .inner {
    max-width: 850px;
    margin: auto;
}

header h2 {
    margin: 0 0 5px;
}

header p {
    margin: 0;
    font-size: 14px;
    opacity: .9;
}

/* Area formulir */
main {
    max-width: 850px;
    margin: 25px auto;
    padding: 0 16px;
}

.card {
    padding: 25px;
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(24, 59, 50, .05);
}

h3 {
    margin-top: 0;
    color: var(--primary);
}

label {
    display: block;
    margin: 18px 0 8px;
    font-weight: bold;
    font-size: 14px;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font: inherit;
    background: #FFFFFF;
    color: var(--text-main);
}

textarea {
    min-height: 110px;
    resize: vertical;
}

input:focus,
select:focus,
textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(24, 59, 50, .12);
}

/* Pesan error */
.error {
    padding: 12px;
    background: #FFF0F0;
    color: #A52A2A;
    border: 1px solid #F2C5C5;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 14px;
}

.note {
    margin-top: 8px;
    color: var(--text-muted);
    font-size: 12px;
}

/* Tombol */
.actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.btn {
    display: inline-block;
    padding: 12px 18px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    text-align: center;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    font-family: inherit;
}

.primary {
    background: var(--primary);
    color: #FFFFFF;
    flex: 1;
}

.primary:hover {
    background: var(--primary-hover);
}

.secondary {
    background: var(--primary-light);
    color: var(--primary);
}

.secondary:hover {
    background: #D5E5DC;
}

@media (max-width: 480px) {
    .card {
        padding: 18px;
    }

    .actions {
        flex-direction: column;
    }
}

</style>
</head>

<body>
<header>
    <div class="inner">
        <h2>📦 TemuBarang</h2>
        <p>Buat laporan barang hilang atau ditemukan</p>
    </div>
</header>

<main>
    <div class="card">
        <h3>Formulir Laporan</h3>
        <p>Isi informasi barang dengan benar agar mudah ditemukan.</p>

        <?php if ($error !== ""): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["csrf_token"]) ?>"
            >

            <label for="jenis">Jenis Laporan</label>
            <select name="jenis" id="jenis" required>
                <option value="">-- Pilih jenis laporan --</option>
                <option value="hilang"
                    <?= $jenis === "hilang" ? "selected" : "" ?>>
                    Barang Hilang
                </option>
                <option value="temuan"
                    <?= $jenis === "temuan" ? "selected" : "" ?>>
                    Barang Temuan
                </option>
            </select>

            <label for="nama_barang">Nama Barang</label>
            <input
                type="text"
                id="nama_barang"
                name="nama_barang"
                maxlength="150"
                value="<?= e($nama_barang) ?>"
                placeholder="Contoh: Dompet hitam"
                required
            >

            <label for="kategori">Kategori</label>
            <select name="kategori" id="kategori" required>
                <option value="">-- Pilih kategori --</option>
                <?php foreach ($kategoriList as $item): ?>
                    <option value="<?= e($item) ?>"
                        <?= $kategori === $item ? "selected" : "" ?>>
                        <?= e($item) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="deskripsi">Deskripsi Barang</label>
            <textarea
                id="deskripsi"
                name="deskripsi"
                placeholder="Jelaskan ciri-ciri barang..."
                required
            ><?= e($deskripsi) ?></textarea>

            <label for="lokasi">Lokasi Kejadian</label>
            <input
                type="text"
                id="lokasi"
                name="lokasi"
                maxlength="150"
                value="<?= e($lokasi) ?>"
                placeholder="Contoh: Ruang kelas X RPL 1"
                required
            >

            <label for="tanggal_kejadian">Tanggal Kejadian</label>
            <input
                type="date"
                id="tanggal_kejadian"
                name="tanggal_kejadian"
                max="<?= date("Y-m-d") ?>"
                value="<?= e($tanggal_kejadian) ?>"
                required
            >

            <label for="foto">Foto Barang (Opsional)</label>
            <input
                type="file"
                id="foto"
                name="foto"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            >
            <div class="note">
                Format JPG, PNG, atau WEBP. Maksimal 2 MB.
            </div>

            <div class="actions">
                <a href="index.php" class="btn secondary">Kembali</a>
                <button type="submit" class="btn primary">
                    Kirim Laporan
                </button>
            </div>
        </form>
    </div>
</main>
</body>
</html>