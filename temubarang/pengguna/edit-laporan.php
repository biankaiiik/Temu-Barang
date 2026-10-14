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

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$idPengguna = (int)$_SESSION["id_pengguna"];
$idLaporan = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$idLaporan || $idLaporan < 1) {
    header("Location: riwayat.php");
    exit;
}

// Ambil laporan milik pengguna yang masih pending
$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM laporan
     WHERE id_laporan = ? AND id_pengguna = ? AND status = 'pending'
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "ii", $idLaporan, $idPengguna);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$laporan = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$laporan) {
    http_response_code(404);
    exit("Laporan tidak ditemukan atau sudah tidak dapat diedit.");
}

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

$error = "";
$jenis = $laporan["jenis"];
$namaBarang = $laporan["nama_barang"];
$kategori = $laporan["kategori"];
$deskripsi = $laporan["deskripsi"];
$lokasi = $laporan["lokasi"];
$tanggal = $laporan["tanggal_kejadian"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $error = "Permintaan tidak valid. Muat ulang halaman.";
    } else {
        $jenis = $_POST["jenis"] ?? "";
        $namaBarang = trim($_POST["nama_barang"] ?? "");
        $kategori = $_POST["kategori"] ?? "";
        $deskripsi = trim($_POST["deskripsi"] ?? "");
        $lokasi = trim($_POST["lokasi"] ?? "");
        $tanggal = $_POST["tanggal_kejadian"] ?? "";

        if (!in_array($jenis, ["hilang", "temuan"], true)) {
            $error = "Jenis laporan tidak valid.";
        } elseif ($namaBarang === "" || strlen($namaBarang) > 150) {
            $error = "Nama barang wajib diisi, maksimal 150 karakter.";
        } elseif (!in_array($kategori, $kategoriList, true)) {
            $error = "Pilih kategori yang tersedia.";
        } elseif ($deskripsi === "") {
            $error = "Deskripsi wajib diisi.";
        } elseif ($lokasi === "" || strlen($lokasi) > 150) {
            $error = "Lokasi wajib diisi, maksimal 150 karakter.";
        } elseif (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) ||
            !checkdate(
                (int)substr($tanggal, 5, 2),
                (int)substr($tanggal, 8, 2),
                (int)substr($tanggal, 0, 4)
            ) ||
            $tanggal > date("Y-m-d")
        ) {
            $error = "Tanggal kejadian tidak valid.";
        }

        // Update hanya jika laporan masih pending dan milik pengguna ini
        if ($error === "") {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE laporan
                 SET jenis = ?, nama_barang = ?, kategori = ?,
                     deskripsi = ?, lokasi = ?, tanggal_kejadian = ?
                 WHERE id_laporan = ?
                   AND id_pengguna = ?
                   AND status = 'pending'"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssssssii",
                $jenis,
                $namaBarang,
                $kategori,
                $deskripsi,
                $lokasi,
                $tanggal,
                $idLaporan,
                $idPengguna
            );

            if (mysqli_stmt_execute($stmt)) {
                $berhasil = mysqli_stmt_affected_rows($stmt) >= 0;
                mysqli_stmt_close($stmt);

                if ($berhasil) {
                    unset($_SESSION["csrf_token"]);
                    header("Location: riwayat.php?updated=1");
                    exit;
                }
            } else {
                mysqli_stmt_close($stmt);
                $error = "Laporan gagal diperbarui. Coba lagi.";
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
<title>Edit Laporan | TemuBarang</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    background: #f1f5f9;
    color: #1e293b;
    font-family: Arial, sans-serif;
}

header {
    background: #2563eb;
    color: white;
    padding: 20px;
}

.header-inner, main {
    max-width: 800px;
    margin: auto;
}

header h2 { margin: 0 0 5px; }
header p { margin: 0; font-size: 14px; }

main { padding: 25px 16px; }

.card {
    background: white;
    padding: 25px;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(0,0,0,.05);
}

label {
    display: block;
    margin: 17px 0 8px;
    font-weight: bold;
    font-size: 14px;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
    background: white;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

.error {
    background: #fee2e2;
    color: #b91c1c;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.actions {
    display: flex;
    gap: 10px;
    margin-top: 24px;
}

.btn {
    display: inline-block;
    padding: 12px 18px;
    border: 0;
    border-radius: 8px;
    text-decoration: none;
    text-align: center;
    font-weight: bold;
    cursor: pointer;
    font-size: 14px;
}

.primary { background: #2563eb; color: white; flex: 1; }
.secondary { background: #e2e8f0; color: #334155; }

@media(max-width:480px) {
    .card { padding: 18px; }
    .actions { flex-direction: column; }
}
</style>
</head>

<body>
<header>
    <div class="header-inner">
        <h2>📦 TemuBarang</h2>
        <p>Edit laporan barang</p>
    </div>
</header>

<main>
<div class="card">
    <h3>Edit Laporan</h3>
    <p>Periksa kembali data sebelum menyimpan perubahan.</p>

    <?php if ($error !== ""): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token"
               value="<?= e($_SESSION["csrf_token"]) ?>">

        <label for="jenis">Jenis Laporan</label>
        <select name="jenis" id="jenis" required>
            <option value="hilang" <?= $jenis === "hilang" ? "selected" : "" ?>>
                Barang Hilang
            </option>
            <option value="temuan" <?= $jenis === "temuan" ? "selected" : "" ?>>
                Barang Temuan
            </option>
        </select>

        <label for="nama_barang">Nama Barang</label>
        <input type="text" id="nama_barang" name="nama_barang"
               maxlength="150" value="<?= e($namaBarang) ?>" required>

        <label for="kategori">Kategori</label>
        <select name="kategori" id="kategori" required>
            <?php foreach ($kategoriList as $item): ?>
                <option value="<?= e($item) ?>"
                    <?= $kategori === $item ? "selected" : "" ?>>
                    <?= e($item) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="deskripsi">Deskripsi</label>
        <textarea id="deskripsi" name="deskripsi" required><?= e($deskripsi) ?></textarea>

        <label for="lokasi">Lokasi Kejadian</label>
        <input type="text" id="lokasi" name="lokasi"
               maxlength="150" value="<?= e($lokasi) ?>" required>

        <label for="tanggal_kejadian">Tanggal Kejadian</label>
        <input type="date" id="tanggal_kejadian" name="tanggal_kejadian"
               max="<?= date("Y-m-d") ?>" value="<?= e($tanggal) ?>" required>

        <p style="color:#64748b;font-size:13px">
            Foto laporan yang lama tidak diubah melalui formulir ini.
        </p>

        <div class="actions">
            <a href="riwayat.php" class="btn secondary">Batal</a>
            <button type="submit" class="btn primary">Simpan Perubahan</button>
        </div>
    </form>
</div>
</main>
</body>
</html>