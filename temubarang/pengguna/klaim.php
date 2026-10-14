<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['id_pengguna'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') === 'admin') {
    header('Location: ../admin/index.php');
    exit;
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id_pengguna = (int)$_SESSION['id_pengguna'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_laporan = filter_input(INPUT_POST, 'id_laporan', FILTER_VALIDATE_INT);
    $alasan = trim($_POST['alasan'] ?? '');

    if (!$id_laporan || $alasan === '' || strlen($alasan) > 3000) {
        $error = 'Pilih barang dan isi alasan klaim dengan benar.';
    } else {
        // Pastikan barang memang laporan temuan yang disetujui
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_laporan
             FROM laporan
             WHERE id_laporan = ?
               AND jenis = 'temuan'
               AND status = 'approved'
               AND id_pengguna <> ?"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $id_laporan, $id_pengguna);
        mysqli_stmt_execute($stmt);
        $barang = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);

        if (!$barang) {
            $error = 'Barang tidak tersedia untuk diklaim.';
        } else {
            // Cegah klaim berulang oleh pengguna yang sama
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_klaim FROM klaim
                 WHERE id_laporan = ? AND id_pengguna = ?
                   AND status IN ('pending', 'approved')
                 LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt, 'ii', $id_laporan, $id_pengguna);
            mysqli_stmt_execute($stmt);
            $sudah = mysqli_stmt_get_result($stmt)->fetch_assoc();
            mysqli_stmt_close($stmt);

            if ($sudah) {
                $error = 'Kamu sudah memiliki klaim aktif untuk barang ini.';
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO klaim (id_laporan, id_pengguna, alasan, status)
                     VALUES (?, ?, ?, 'pending')"
                );
                mysqli_stmt_bind_param(
                    $stmt, 'iis', $id_laporan, $id_pengguna, $alasan
                );

                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Klaim berhasil diajukan dan sedang menunggu pemeriksaan admin.';
                } else {
                    $error = 'Klaim gagal disimpan. Silakan coba lagi.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Barang temuan yang boleh diklaim, kecuali milik sendiri
$stmt = mysqli_prepare(
    $conn,
    "SELECT l.id_laporan, l.nama_barang, l.kategori,
            l.deskripsi, l.lokasi, l.tanggal_kejadian, l.foto
     FROM laporan l
     WHERE l.jenis = 'temuan'
       AND l.status = 'approved'
       AND l.id_pengguna <> ?
       AND NOT EXISTS (
           SELECT 1 FROM klaim k
           WHERE k.id_laporan = l.id_laporan
             AND k.status = 'approved'
       )
     ORDER BY l.created_at DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $id_pengguna);
mysqli_stmt_execute($stmt);
$barang_result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Klaim Barang - TemuBarang</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
body{margin:0;background:#f5f8f5;color:#20332b;font-family:Arial,sans-serif}
header{background:white;padding:17px 5%;border-bottom:1px solid #e3eae4}
header a{color:#176b55;text-decoration:none;font-weight:bold}
main{max-width:950px;margin:32px auto;padding:0 18px}
h1{font-size:26px;margin-bottom:8px}
.muted{font-size:13px;color:#748279;line-height:1.7}
.alert{padding:13px;border-radius:8px;margin:18px 0;font-size:13px}
.error{background:#fde9e6;color:#a33b31}
.success{background:#e2f5e8;color:#247342}
.list{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;margin-top:25px}
.card{background:white;border:1px solid #e3eae4;border-radius:13px;overflow:hidden}
.card img{width:100%;height:180px;object-fit:cover;background:#edf2ed}
.content{padding:18px}
.content h2{font-size:17px;margin:0 0 10px}
.content p{font-size:12px;color:#748279;line-height:1.7;overflow-wrap:anywhere}
label{display:block;font-size:12px;font-weight:bold;margin:14px 0 7px}
textarea{width:100%;box-sizing:border-box;min-height:95px;padding:11px;border:1px solid #dce5de;border-radius:8px;resize:vertical;font:13px Arial}
button{width:100%;margin-top:12px;padding:12px;background:#176b55;color:white;border:0;border-radius:8px;font-weight:bold;cursor:pointer}
.empty{grid-column:1/-1;padding:35px;background:white;border-radius:12px;text-align:center;color:#748279;font-size:13px}
</style>
</head>
<body>
<header><a href="index.php">← Dashboard TemuBarang</a></header>
<main>
<h1>Klaim Barang</h1>
<p class="muted">Kenali barang yang kamu klaim dan jelaskan alasan kepemilikanmu. Admin akan memeriksa pengajuan sebelum barang dikembalikan.</p>

<?php if ($error): ?>
<div class="alert error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert success"><?= e($success) ?></div>
<?php endif; ?>

<div class="list">
<?php if (mysqli_num_rows($barang_result) > 0): ?>
    <?php while ($b = mysqli_fetch_assoc($barang_result)): ?>
    <article class="card">
        <?php
        $foto = basename($b['foto'] ?? '');
        $path = __DIR__ . '/../assets/uploads/barang/' . $foto;
        ?>
        <?php if ($foto !== '' && is_file($path)): ?>
            <img src="../assets/uploads/barang/<?= e($foto) ?>" alt="Foto barang">
        <?php else: ?>
            <div style="height:180px;display:grid;place-items:center;background:#edf2ed;font-size:40px">📦</div>
        <?php endif; ?>

        <div class="content">
            <h2><?= e($b['nama_barang']) ?></h2>
            <p>
                Kategori: <?= e($b['kategori']) ?><br>
                Lokasi: <?= e($b['lokasi']) ?><br>
                Tanggal: <?= e(date('d-m-Y', strtotime($b['tanggal_kejadian']))) ?>
            </p>
            <p><?= e($b['deskripsi']) ?></p>

            <form method="POST" action="klaim.php">
                <input type="hidden" name="id_laporan" value="<?= (int)$b['id_laporan'] ?>">
                <label for="alasan-<?= (int)$b['id_laporan'] ?>">Alasan klaim</label>
                <textarea
                    id="alasan-<?= (int)$b['id_laporan'] ?>"
                    name="alasan"
                    maxlength="3000"
                    required
                    placeholder="Jelaskan ciri kepemilikan barang yang tidak dipublikasikan..."
                ></textarea>
                <button type="submit">Ajukan Klaim</button>
            </form>
        </div>
    </article>
    <?php endwhile; ?>
<?php else: ?>
    <div class="empty">Belum ada barang temuan yang tersedia untuk diklaim.</div>
<?php endif; ?>
</div>
</main>
</body>
</html>
<?php mysqli_stmt_close($stmt); ?>