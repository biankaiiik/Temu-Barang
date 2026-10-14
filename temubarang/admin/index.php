<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['id_pengguna'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../pengguna/index.php');
    exit;
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function jumlah($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return (int)($row['jumlah'] ?? 0);
}

$total = jumlah($conn, "SELECT COUNT(*) jumlah FROM laporan");
$pending = jumlah($conn, "SELECT COUNT(*) jumlah FROM laporan WHERE status='pending'");
$approved = jumlah($conn, "SELECT COUNT(*) jumlah FROM laporan WHERE status='approved'");
$totalKlaim = jumlah($conn, "SELECT COUNT(*) jumlah FROM klaim WHERE status='pending'");

$stmt = mysqli_prepare(
    $conn,
    "SELECT l.id_laporan, l.jenis, l.nama_barang, l.kategori,
            l.lokasi, l.tanggal_kejadian, l.status, l.created_at,
            p.nama_lengkap
     FROM laporan l
     JOIN pengguna p ON p.id_pengguna = l.id_pengguna
     WHERE l.status = 'pending'
     ORDER BY l.created_at DESC
     LIMIT 10"
);
mysqli_stmt_execute($stmt);
$laporan = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - TemuBarang</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
body{margin:0;background:#f5f8f5;color:#20332b;font-family:Arial,sans-serif}
header{background:#fff;padding:17px 5%;border-bottom:1px solid #e3eae4}
.nav{max-width:1150px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}
.nav a{color:#176b55;text-decoration:none;font-weight:bold;font-size:13px;margin-right:13px}
.nav .logout{color:#b4473d}
main{max-width:1150px;margin:32px auto;padding:0 18px 40px}
h1{margin-bottom:8px;font-size:27px}
.muted{color:#748279;font-size:13px}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:25px 0}
.stat{background:white;border:1px solid #e3eae4;border-radius:12px;padding:20px}
.stat span{font-size:12px;color:#748279}
.stat h2{font-size:29px;margin:10px 0 0}
.panel{background:white;border:1px solid #e3eae4;border-radius:12px;overflow:hidden}
.panel-head{padding:20px;border-bottom:1px solid #edf0ed;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
.panel-head h2{font-size:18px;margin:0}
.panel-head a{color:#176b55;text-decoration:none;font-size:13px;font-weight:bold}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:700px}
th,td{text-align:left;padding:14px 16px;border-bottom:1px solid #edf0ed;font-size:12px}
th{background:#f8faf8;color:#66766c}
td{line-height:1.6}
.pill{display:inline-block;padding:5px 8px;background:#fff2d5;color:#8a6100;border-radius:5px;font-weight:bold;font-size:10px}
.empty{text-align:center;padding:35px;color:#748279;font-size:13px}
@media(max-width:700px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}main{margin-top:24px}.stat{padding:16px}}
</style>
</head>
<body>
<header>
<div class="nav">
    <a href="index.php">TemuBarang · Admin</a>
    <div>
        <a href="laporan.php">Kelola Laporan</a>
        <a href="klaim.php">Kelola Klaim</a>
        <a class="logout" href="../auth/logout.php">Logout</a>
    </div>
</div>
</header>

<main>
<h1>Dashboard Admin</h1>
<p class="muted">Halo, <?= e($_SESSION['nama_lengkap'] ?? 'Admin') ?>. Kelola laporan dan klaim barang di sini.</p>

<section class="stats">
    <div class="stat"><span>Total Laporan</span><h2><?= $total ?></h2></div>
    <div class="stat"><span>Menunggu Verifikasi</span><h2><?= $pending ?></h2></div>
    <div class="stat"><span>Laporan Disetujui</span><h2><?= $approved ?></h2></div>
    <div class="stat"><span>Klaim Menunggu</span><h2><?= $totalKlaim ?></h2></div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Laporan Menunggu Verifikasi</h2>
        <a href="laporan.php">Lihat semua →</a>
    </div>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Barang</th>
            <th>Jenis</th>
            <th>Pelapor</th>
            <th>Lokasi</th>
            <th>Tanggal</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($laporan) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($laporan)): ?>
            <tr>
                <td><?= e($row['nama_barang']) ?><br><?= e($row['kategori']) ?></td>
                <td><?= $row['jenis'] === 'hilang' ? 'Hilang' : 'Temuan' ?></td>
                <td><?= e($row['nama_lengkap']) ?></td>
                <td><?= e($row['lokasi']) ?></td>
                <td><?= e(date('d-m-Y', strtotime($row['tanggal_kejadian']))) ?></td>
                <td><span class="pill">Menunggu</span></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6" class="empty">Tidak ada laporan yang menunggu verifikasi.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
</main>
</body>
</html>
<?php mysqli_stmt_close($stmt); ?>