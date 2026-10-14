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

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function statusLabel($status) {
    $labels = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'dikembalikan' => 'Dikembalikan'
    ];

    return $labels[$status] ?? $status;
}

$id_pengguna = (int)$_SESSION['id_pengguna'];
$filter = $_GET['status'] ?? '';

$allowed = ['pending', 'approved', 'rejected', 'dikembalikan'];

if (!in_array($filter, $allowed, true)) {
    $filter = '';
}

$sql = "SELECT id_laporan, jenis, nama_barang, kategori,
               lokasi, tanggal_kejadian, foto, status, created_at
        FROM laporan
        WHERE id_pengguna = ?";

if ($filter !== '') {
    $sql .= " AND status = ?";
}

$sql .= " ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    exit('Terjadi kesalahan saat mengambil riwayat laporan.');
}

if ($filter !== '') {
    mysqli_stmt_bind_param($stmt, 'is', $id_pengguna, $filter);
} else {
    mysqli_stmt_bind_param($stmt, 'i', $id_pengguna);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Riwayat Laporan - TemuBarang</title>

<link rel="stylesheet" href="../assets/css/style.css">

<style>
body {
    margin: 0;
    background: #f5f8f5;
    color: #20332b;
    font-family: Arial, sans-serif;
}

header {
    background: white;
    padding: 18px 5%;
    border-bottom: 1px solid #e3eae4;
}

header a {
    color: #176b55;
    text-decoration: none;
    font-weight: bold;
}

main {
    max-width: 1000px;
    margin: 35px auto;
    padding: 0 18px;
}

h1 {
    font-size: 27px;
    margin-bottom: 8px;
}

.subtitle {
    color: #748279;
    font-size: 14px;
    margin-bottom: 24px;
}

.notice {
    padding: 12px 15px;
    margin-bottom: 18px;
    border-radius: 9px;
    font-size: 13px;
    background: #e2f5e8;
    color: #247342;
}

.filters {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.filters a {
    padding: 10px 13px;
    border: 1px solid #dce6dd;
    border-radius: 8px;
    background: white;
    color: #365044;
    text-decoration: none;
    font-size: 12px;
}

.filters a.active {
    background: #176b55;
    color: white;
    border-color: #176b55;
}

.list {
    background: white;
    border: 1px solid #e3eae4;
    border-radius: 13px;
    padding: 0 20px;
}

.item {
    display: flex;
    gap: 15px;
    align-items: center;
    padding: 18px 0;
    border-bottom: 1px solid #edf0ed;
}

.item:last-child {
    border-bottom: 0;
}

.photo {
    width: 70px;
    height: 70px;
    object-fit: cover;
    background: #edf2ed;
    border-radius: 9px;
    flex-shrink: 0;
}

.info {
    flex: 1;
    min-width: 0;
}

.info h3 {
    font-size: 15px;
    margin: 0 0 8px;
    overflow-wrap: anywhere;
}

.info p {
    font-size: 12px;
    line-height: 1.7;
    color: #748279;
    margin: 0;
}

.badge {
    display: inline-block;
    margin-top: 8px;
    padding: 6px 9px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: bold;
}

.pending {
    background: #fff2d5;
    color: #8a6100;
}

.approved {
    background: #e2f5e8;
    color: #247342;
}

.rejected {
    background: #fde9e6;
    color: #a33b31;
}

.dikembalikan {
    background: #e7edff;
    color: #344f9c;
}

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.btn {
    display: inline-block;
    padding: 9px 13px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
}

.btn-edit {
    background: #176b55;
    color: white;
}

.btn-hapus {
    background: #fde9e6;
    color: #a33b31;
}

.btn-detail {
    background: #edf2ed;
    color: #365044;
}

.empty {
    text-align: center;
    padding: 45px 15px;
    color: #748279;
    font-size: 13px;
}

@media(max-width:550px) {
    .item {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .photo {
        width: 58px;
        height: 58px;
    }

    .info {
        width: calc(100% - 75px);
        flex: initial;
    }
}
</style>
</head>

<body>

<header>
    <a href="index.php">← Dashboard TemuBarang</a>
</header>

<main>
    <h1>Riwayat Laporan</h1>

    <p class="subtitle">
        Pantau laporan barang hilang dan temuan yang kamu buat.
    </p>

    <?php if (isset($_GET['success'])): ?>
        <div class="notice">
            Laporan berhasil dikirim dan sedang menunggu persetujuan admin.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice">
            Perubahan laporan berhasil disimpan.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="notice">
        Laporan berhasil dihapus.
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="notice" style="background:#fde9e6;color:#a33b31">
        Laporan gagal dihapus atau sudah tidak dapat dihapus.
    </div>
<?php endif; ?>

    <nav class="filters">
        <a class="<?= $filter === '' ? 'active' : '' ?>"
           href="riwayat.php">Semua</a>

        <a class="<?= $filter === 'pending' ? 'active' : '' ?>"
           href="?status=pending">Menunggu</a>

        <a class="<?= $filter === 'approved' ? 'active' : '' ?>"
           href="?status=approved">Disetujui</a>

        <a class="<?= $filter === 'rejected' ? 'active' : '' ?>"
           href="?status=rejected">Ditolak</a>

        <a class="<?= $filter === 'dikembalikan' ? 'active' : '' ?>"
           href="?status=dikembalikan">Dikembalikan</a>
    </nav>

    <section class="list">
        <?php if (mysqli_num_rows($result) > 0): ?>

            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <article class="item">

                    <?php
                    $foto = basename($row['foto'] ?? '');
                    $path = __DIR__ . '/../assets/uploads/barang/' . $foto;
                    ?>

                    <?php if ($foto !== '' && is_file($path)): ?>
                        <img
                            class="photo"
                            src="../assets/uploads/barang/<?= e($foto) ?>"
                            alt="Foto barang"
                        >
                    <?php else: ?>
                        <div class="photo"
                             style="display:grid;place-items:center;font-size:25px">
                            📦
                        </div>
                    <?php endif; ?>

                    <div class="info">
                        <h3><?= e($row['nama_barang']) ?></h3>

                        <p>
                            Jenis: <?= e(ucfirst($row['jenis'])) ?><br>
                            <?= e($row['kategori']) ?> ·
                            <?= e($row['lokasi']) ?><br>
                            Tanggal kejadian:
                            <?= e(date('d-m-Y', strtotime($row['tanggal_kejadian']))) ?>
                        </p>

                        <span class="badge <?= e($row['status']) ?>">
                            <?= e(statusLabel($row['status'])) ?>
                        </span>

                        <div class="actions">
                            <?php if ($row['status'] === 'pending'): ?>
                                <a
                                    class="btn btn-edit"
                                    href="edit-laporan.php?id=<?= (int)$row['id_laporan'] ?>"
                                >
                                    ✏️ Edit Laporan
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                </article>
            <?php endwhile; ?>

        <?php else: ?>
            <div class="empty">
                Belum ada laporan untuk filter ini.
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>