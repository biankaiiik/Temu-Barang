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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $id = filter_input(INPUT_POST, 'id_laporan', FILTER_VALIDATE_INT);
    $aksi = $_POST['aksi'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Permintaan tidak valid. Muat ulang halaman.';
    } elseif (!$id || !in_array($aksi, ['approved', 'rejected'], true)) {
        $error = 'Data tindakan tidak valid.';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE laporan SET status = ?
             WHERE id_laporan = ? AND status = 'pending'"
        );
        mysqli_stmt_bind_param($stmt, 'si', $aksi, $id);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) === 1) {
            $pesan = $aksi === 'approved'
                ? 'Laporan berhasil disetujui.'
                : 'Laporan berhasil ditolak.';
        } else {
            $error = 'Laporan tidak ditemukan atau sudah diproses.';
        }
        mysqli_stmt_close($stmt);
    }
}

$filter = $_GET['status'] ?? 'pending';
$validFilter = ['pending', 'approved', 'rejected', 'dikembalikan', 'semua'];
if (!in_array($filter, $validFilter, true)) {
    $filter = 'pending';
}

$sql = "SELECT l.*, p.nama_lengkap, p.kelas
        FROM laporan l
        JOIN pengguna p ON p.id_pengguna = l.id_pengguna";

if ($filter !== 'semua') {
    $sql .= " WHERE l.status = ?";
}
$sql .= " ORDER BY l.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($filter !== 'semua') {
    mysqli_stmt_bind_param($stmt, 's', $filter);
}
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt);

function statusText($s) {
    return [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'dikembalikan' => 'Dikembalikan'
    ][$s] ?? $s;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kelola Laporan - TemuBarang</title>
<style>
body{margin:0;background:#f5f8f5;color:#20332b;font-family:Arial,sans-serif}
header{padding:17px 5%;background:white;border-bottom:1px solid #e3eae4}
header a{color:#176b55;text-decoration:none;font-weight:bold}
main{max-width:1200px;margin:30px auto;padding:0 18px}
h1{font-size:26px}
.note{color:#748279;font-size:13px;line-height:1.7}
.alert{padding:12px;margin:15px 0;border-radius:8px;font-size:13px}
.ok{background:#e2f5e8;color:#247342}.bad{background:#fde9e6;color:#a33b31}
.filters{display:flex;gap:8px;flex-wrap:wrap;margin:20px 0}
.filters a{background:white;border:1px solid #dce5de;border-radius:7px;padding:9px 12px;color:#365044;text-decoration:none;font-size:12px}
.filters a.active{background:#176b55;color:white}
.table-wrap{overflow-x:auto;background:white;border:1px solid #e3eae4;border-radius:12px}
table{width:100%;border-collapse:collapse;min-width:850px}
th,td{padding:14px;text-align:left;border-bottom:1px solid #edf0ed;font-size:12px;vertical-align:top}
th{background:#f8faf8}
td p{line-height:1.7;color:#748279;margin:5px 0}
button{border:0;border-radius:6px;padding:9px 11px;color:white;font-weight:bold;cursor:pointer;margin:2px}
.approve{background:#176b55}.reject{background:#b4473d}
.photo{width:65px;height:65px;object-fit:cover;border-radius:7px}
.empty{text-align:center;padding:30px;color:#748279}
</style>
</head>
<body>
<header><a href="index.php">← Dashboard Admin</a></header>
<main>
<h1>Kelola Laporan</h1>
<p class="note">Periksa informasi barang sebelum menyetujui laporan. Pastikan informasi pribadi tidak dipublikasikan.</p>

<?php if ($pesan): ?><div class="alert ok"><?= e($pesan) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>

<nav class="filters">
<?php foreach (['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak','dikembalikan'=>'Dikembalikan','semua'=>'Semua'] as $key=>$label): ?>
<a class="<?= $filter===$key?'active':'' ?>" href="?status=<?= e($key) ?>"><?= e($label) ?></a>
<?php endforeach; ?>
</nav>

<div class="table-wrap">
<table>
<thead><tr><th>Foto</th><th>Informasi Barang</th><th>Pelapor</th><th>Lokasi</th><th>Tanggal</th><th>Status / Tindakan</th></tr></thead>
<tbody>
<?php if (mysqli_num_rows($rows) > 0): ?>
<?php while ($r = mysqli_fetch_assoc($rows)): ?>
<tr>
<td>
<?php
$foto = basename($r['foto'] ?? '');
$path = __DIR__ . '/../assets/uploads/barang/' . $foto;
?>
<?php if ($foto !== '' && is_file($path)): ?>
<img class="photo" src="../assets/uploads/barang/<?= e($foto) ?>" alt="Foto barang">
<?php else: ?>—<?php endif; ?>
</td>
<td>
<strong><?= e($r['nama_barang']) ?></strong>
<p><?= e($r['kategori']) ?> · <?= $r['jenis']==='hilang'?'Hilang':'Temuan' ?></p>
<p><?= e($r['deskripsi']) ?></p>
</td>
<td><?= e($r['nama_lengkap']) ?><br><?= e($r['kelas'] ?? '-') ?></td>
<td><?= e($r['lokasi']) ?></td>
<td><?= e(date('d-m-Y', strtotime($r['tanggal_kejadian']))) ?></td>
<td>
<strong><?= e(statusText($r['status'])) ?></strong>
<?php if ($r['status'] === 'pending'): ?>
<form method="POST" onsubmit="return confirm('Yakin memproses laporan ini?')">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
<input type="hidden" name="id_laporan" value="<?= (int)$r['id_laporan'] ?>">
<button class="approve" name="aksi" value="approved">Setujui</button>
<button class="reject" name="aksi" value="rejected">Tolak</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6" class="empty">Belum ada laporan pada kategori ini.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</main>
</body>
</html>
<?php mysqli_stmt_close($stmt); ?>