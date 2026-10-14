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

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $id_klaim = filter_input(INPUT_POST, 'id_klaim', FILTER_VALIDATE_INT);
    $aksi = $_POST['aksi'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Permintaan tidak valid. Muat ulang halaman.';
    } elseif (!$id_klaim || !in_array($aksi, ['approved', 'rejected'], true)) {
        $error = 'Data tindakan tidak valid.';
    } else {
        mysqli_begin_transaction($conn);

        try {
            // Kunci klaim dan laporan selama diproses
            $stmt = mysqli_prepare(
                $conn,
                "SELECT k.id_klaim, k.id_laporan, k.status,
                        l.status AS status_laporan, l.jenis
                 FROM klaim k
                 JOIN laporan l ON l.id_laporan = k.id_laporan
                 WHERE k.id_klaim = ?
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($stmt, 'i', $id_klaim);
            mysqli_stmt_execute($stmt);
            $klaim = mysqli_stmt_get_result($stmt)->fetch_assoc();
            mysqli_stmt_close($stmt);

            if (
                !$klaim ||
                $klaim['status'] !== 'pending' ||
                $klaim['status_laporan'] !== 'approved' ||
                $klaim['jenis'] !== 'temuan'
            ) {
                throw new Exception(
                    'Klaim tidak tersedia atau laporan barang tidak memenuhi syarat.'
                );
            }

            if ($aksi === 'approved') {
                // Cegah dua klaim disetujui untuk laporan yang sama
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id_klaim FROM klaim
                     WHERE id_laporan = ?
                       AND status = 'approved'
                     LIMIT 1
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param(
                    $stmt, 'i', $klaim['id_laporan']
                );
                mysqli_stmt_execute($stmt);
                $klaimLain = mysqli_stmt_get_result($stmt)->fetch_assoc();
                mysqli_stmt_close($stmt);

                if ($klaimLain) {
                    throw new Exception(
                        'Barang sudah memiliki klaim yang disetujui.'
                    );
                }

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE klaim SET status = 'approved'
                     WHERE id_klaim = ? AND status = 'pending'"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id_klaim);
                mysqli_stmt_execute($stmt);
                $berubah = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($berubah !== 1) {
                    throw new Exception('Status klaim gagal diperbarui.');
                }

                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE laporan SET status = 'dikembalikan'
                     WHERE id_laporan = ?
                       AND status = 'approved'
                       AND jenis = 'temuan'"
                );
                mysqli_stmt_bind_param(
                    $stmt, 'i', $klaim['id_laporan']
                );
                mysqli_stmt_execute($stmt);
                $berubah = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($berubah !== 1) {
                    throw new Exception('Status barang gagal diperbarui.');
                }

                // Tolak klaim lain yang masih menunggu untuk barang ini
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE klaim SET status = 'rejected'
                     WHERE id_laporan = ?
                       AND id_klaim <> ?
                       AND status = 'pending'"
                );
                mysqli_stmt_bind_param(
                    $stmt, 'ii', $klaim['id_laporan'], $id_klaim
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $pesan = 'Klaim disetujui. Status barang menjadi dikembalikan.';
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE klaim SET status = 'rejected'
                     WHERE id_klaim = ? AND status = 'pending'"
                );
                mysqli_stmt_bind_param($stmt, 'i', $id_klaim);
                mysqli_stmt_execute($stmt);
                $berubah = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($berubah !== 1) {
                    throw new Exception('Status klaim gagal diperbarui.');
                }

                $pesan = 'Klaim berhasil ditolak. Status laporan barang tidak berubah.';
            }

            mysqli_commit($conn);
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            $error = $ex instanceof Exception
                ? $ex->getMessage()
                : 'Terjadi kesalahan saat memproses klaim.';
        }
    }
}

$filter = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected', 'semua'];

if (!in_array($filter, $allowed, true)) {
    $filter = 'pending';
}

$sql = "SELECT k.id_klaim, k.id_laporan, k.id_pengguna,
               k.alasan, k.status, k.created_at,
               l.nama_barang, l.kategori, l.lokasi, l.status AS status_laporan,
               p.nama_lengkap AS nama_pemilik,
               c.nama_lengkap AS nama_pengklaim
        FROM klaim k
        JOIN laporan l ON l.id_laporan = k.id_laporan
        JOIN pengguna p ON p.id_pengguna = l.id_pengguna
        JOIN pengguna c ON c.id_pengguna = k.id_pengguna";

if ($filter !== 'semua') {
    $sql .= " WHERE k.status = ?";
}

$sql .= " ORDER BY k.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);

if ($filter !== 'semua') {
    mysqli_stmt_bind_param($stmt, 's', $filter);
}

mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt);

function labelStatus($status) {
    return [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak'
    ][$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kelola Klaim - TemuBarang</title>
<style>
body{margin:0;background:#f5f8f5;color:#20332b;font-family:Arial,sans-serif}
header{background:white;padding:17px 5%;border-bottom:1px solid #e3eae4}
header a{color:#176b55;text-decoration:none;font-weight:bold}
main{max-width:1150px;margin:30px auto;padding:0 18px 40px}
h1{font-size:26px;margin-bottom:8px}
.muted{font-size:13px;color:#748279;line-height:1.7}
.alert{padding:13px;margin:17px 0;border-radius:8px;font-size:13px}
.ok{background:#e2f5e8;color:#247342}
.bad{background:#fde9e6;color:#a33b31}
.filters{display:flex;gap:8px;flex-wrap:wrap;margin:20px 0}
.filters a{padding:10px 13px;border:1px solid #dce5de;border-radius:7px;background:white;color:#365044;text-decoration:none;font-size:12px}
.filters a.active{background:#176b55;color:white}
.table-wrap{overflow-x:auto;background:white;border:1px solid #e3eae4;border-radius:12px}
table{width:100%;min-width:850px;border-collapse:collapse}
th,td{padding:14px;text-align:left;vertical-align:top;border-bottom:1px solid #edf0ed;font-size:12px}
th{background:#f8faf8}
td p{line-height:1.7;overflow-wrap:anywhere}
button{padding:9px 11px;border:0;border-radius:6px;color:white;font-weight:bold;cursor:pointer;margin:3px 2px}
.approve{background:#176b55}
.reject{background:#b4473d}
.badge{display:inline-block;padding:5px 8px;background:#fff2d5;color:#8a6100;border-radius:5px;font-size:11px;font-weight:bold}
.empty{text-align:center;padding:30px;color:#748279}
</style>
</head>
<body>
<header><a href="index.php">← Dashboard Admin</a></header>

<main>
<h1>Kelola Klaim Barang</h1>
<p class="muted">
Periksa alasan klaim dan pastikan kepemilikan barang sudah diverifikasi
sebelum menyetujui pengembalian.
</p>

<?php if ($pesan): ?>
<div class="alert ok"><?= e($pesan) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert bad"><?= e($error) ?></div>
<?php endif; ?>

<nav class="filters">
<?php foreach ([
    'pending' => 'Menunggu',
    'approved' => 'Disetujui',
    'rejected' => 'Ditolak',
    'semua' => 'Semua'
] as $key => $label): ?>
<a class="<?= $filter === $key ? 'active' : '' ?>"
   href="?status=<?= e($key) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
</nav>

<div class="table-wrap">
<table>
<thead>
<tr>
<th>Barang</th>
<th>Pemilik Laporan</th>
<th>Pengaju Klaim</th>
<th>Alasan Klaim</th>
<th>Status</th>
<th>Tindakan</th>
</tr>
</thead>
<tbody>
<?php if (mysqli_num_rows($rows) > 0): ?>
<?php while ($r = mysqli_fetch_assoc($rows)): ?>
<tr>
<td>
<strong><?= e($r['nama_barang']) ?></strong>
<p><?= e($r['kategori']) ?><br>Lokasi: <?= e($r['lokasi']) ?></p>
</td>
<td><?= e($r['nama_pemilik']) ?></td>
<td><?= e($r['nama_pengklaim']) ?></td>
<td><?= nl2br(e($r['alasan'])) ?></td>
<td>
    <?php if ($r['status'] === 'pending'): ?>
        <span class="badge">Menunggu</span>
    <?php else: ?>
        <?= e(labelStatus($r['status'])) ?>
    <?php endif; ?>
</td>
<td>
<?php if ($r['status'] === 'pending'): ?>
<form method="POST" onsubmit="return confirm('Yakin memproses klaim ini?')">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
<input type="hidden" name="id_klaim" value="<?= (int)$r['id_klaim'] ?>">
<button class="approve" name="aksi" value="approved">Setujui</button>
<button class="reject" name="aksi" value="rejected">Tolak</button>
</form>
<?php else: ?>
Sudah diproses
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6" class="empty">Belum ada klaim pada filter ini.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</main>
</body>
</html>
<?php mysqli_stmt_close($stmt); ?>