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

$id_pengguna = (int) $_SESSION['id_pengguna'];
$nama = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Pengguna';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Menghitung jumlah laporan berdasarkan kondisi
function hitungLaporan($conn, $id_pengguna, $kondisi = '') {
    $sql = "SELECT COUNT(*) AS jumlah
            FROM laporan
            WHERE id_pengguna = ?";

    if ($kondisi !== '') {
        $sql .= " AND status = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $id_pengguna, $kondisi);
    } else {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id_pengguna);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return (int) ($data['jumlah'] ?? 0);
}

$total = hitungLaporan($conn, $id_pengguna);
$pending = hitungLaporan($conn, $id_pengguna, 'pending');
$approved = hitungLaporan($conn, $id_pengguna, 'approved');
$rejected = hitungLaporan($conn, $id_pengguna, 'rejected');
$dikembalikan = hitungLaporan($conn, $id_pengguna, 'dikembalikan');

// Mengambil laporan terbaru milik pengguna
$stmt = mysqli_prepare(
    $conn,
    "SELECT id_laporan, jenis, nama_barang, kategori,
            lokasi, tanggal_kejadian, foto, status, created_at
     FROM laporan
     WHERE id_pengguna = ?
     ORDER BY created_at DESC
     LIMIT 5"
);

mysqli_stmt_bind_param($stmt, 'i', $id_pengguna);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$laporan = [];

while ($row = mysqli_fetch_assoc($result)) {
    $laporan[] = $row;
}

mysqli_stmt_close($stmt);

function labelStatus($status) {
    switch ($status) {
        case 'pending':
            return 'Menunggu';
        case 'approved':
            return 'Disetujui';
        case 'rejected':
            return 'Ditolak';
        case 'dikembalikan':
            return 'Dikembalikan';
        default:
            return ucfirst($status);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pengguna - TemuBarang</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            margin: 0;
            background: #f5f8f5;
            color: #20332b;
            font-family: Arial, sans-serif;
        }

        .dashboard-nav {
            background: #fff;
            border-bottom: 1px solid #e5ebe6;
            padding: 16px 5%;
        }

        .nav-inner {
            max-width: 1150px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .brand-dashboard {
            color: #176b55;
            font-size: 21px;
            font-weight: 800;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: #40564b;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .nav-links .logout {
            color: #b4473d;
        }

        .dashboard-container {
            max-width: 1150px;
            margin: 35px auto;
            padding: 0 22px 40px;
        }

        .welcome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .welcome h1 {
            margin: 0 0 8px;
            font-size: 27px;
        }

        .welcome p {
            margin: 0;
            color: #78877e;
            font-size: 14px;
            line-height: 1.6;
        }

        .btn-main {
            display: inline-block;
            padding: 13px 18px;
            background: #176b55;
            color: #fff;
            border-radius: 9px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .stat-card {
            padding: 22px;
            background: #fff;
            border: 1px solid #e5ebe6;
            border-radius: 14px;
        }

        .stat-card span {
            color: #78877e;
            font-size: 12px;
        }

        .stat-card h2 {
            margin: 12px 0 0;
            font-size: 30px;
        }

        .section-card {
            background: #fff;
            border: 1px solid #e5ebe6;
            border-radius: 14px;
            overflow: hidden;
        }

        .section-heading {
            padding: 22px;
            border-bottom: 1px solid #edf0ed;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 18px;
        }

        .section-heading a {
            color: #176b55;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .report-list {
            padding: 0 22px;
        }

        .report-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 0;
            border-bottom: 1px solid #edf0ed;
        }

        .report-item:last-child {
            border-bottom: none;
        }

        .report-image {
            width: 66px;
            height: 66px;
            flex-shrink: 0;
            border-radius: 10px;
            object-fit: cover;
            background: #edf2ed;
        }

        .report-placeholder {
            display: grid;
            place-items: center;
            font-size: 24px;
        }

        .report-info {
            flex: 1;
            min-width: 0;
        }

        .report-info h3 {
            margin: 0 0 7px;
            font-size: 15px;
            overflow-wrap: anywhere;
        }

        .report-info p {
            margin: 0;
            color: #78877e;
            font-size: 12px;
            line-height: 1.7;
        }

        .report-type {
            display: inline-block;
            margin-top: 7px;
            color: #176b55;
            background: #e9f5ee;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff3d8;
            color: #946300;
        }

        .status-approved {
            background: #e3f5e9;
            color: #207342;
        }

        .status-rejected {
            background: #fde9e6;
            color: #b13e32;
        }

        .status-dikembalikan {
            background: #e7edff;
            color: #344f9c;
        }

        .empty-state {
            padding: 45px 20px;
            text-align: center;
            color: #78877e;
            font-size: 13px;
            line-height: 1.8;
        }

        .empty-state strong {
            display: block;
            margin-bottom: 8px;
            color: #20332b;
            font-size: 16px;
        }

        .extra-stats {
            margin-top: 20px;
            color: #78877e;
            font-size: 12px;
            line-height: 1.8;
        }

        @media (max-width: 760px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .stat-card {
                padding: 17px;
            }

            .welcome {
                align-items: flex-start;
                flex-direction: column;
            }

            .welcome h1 {
                font-size: 23px;
            }

            .report-item {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .report-info {
                width: calc(100% - 85px);
                flex: initial;
            }

            .report-item > .status {
                margin-left: 81px;
            }
        }

        @media (max-width: 430px) {
            .nav-inner {
                align-items: flex-start;
                flex-direction: column;
            }

            .nav-links {
                gap: 13px;
            }

            .dashboard-container {
                margin-top: 25px;
                padding: 0 15px 30px;
            }

            .section-heading,
            .report-list {
                padding-left: 15px;
                padding-right: 15px;
            }

            .report-image {
                width: 55px;
                height: 55px;
            }

            .report-info {
                width: calc(100% - 70px);
            }

            .report-item > .status {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>

<header class="dashboard-nav">
    <div class="nav-inner">
        <a href="index.php" class="brand-dashboard">TemuBarang.</a>

        <nav class="nav-links">
            <a href="../index.php">Beranda</a>
            <a href="tambah-laporan.php">Buat Laporan</a>
            <a href="klaim.php">Klaim Barang</a>
            <a href="../auth/logout.php" class="logout"
               onclick="return confirm('Yakin ingin keluar dari akun?')">
                Logout
            </a>
        </nav>
    </div>
</header>

<main class="dashboard-container">

    <section class="welcome">
        <div>
            <h1>Halo, <?= e($nama) ?>!</h1>
            <p>Kelola laporan barang hilang dan temuanmu di sini.</p>
        </div>

        <a href="tambah-laporan.php" class="btn-main">
            + Buat Laporan
        </a>
    </section>

    <section class="stats">
        <div class="stat-card">
            <span>Total Laporan</span>
            <h2><?= $total ?></h2>
        </div>

        <div class="stat-card">
            <span>Menunggu</span>
            <h2><?= $pending ?></h2>
        </div>

        <div class="stat-card">
            <span>Disetujui</span>
            <h2><?= $approved ?></h2>
        </div>

        <div class="stat-card">
            <span>Dikembalikan</span>
            <h2><?= $dikembalikan ?></h2>
        </div>
    </section>

    <section class="section-card">
        <div class="section-heading">
            <h2>Laporan Terbaru</h2>
            <a href="riwayat.php">Lihat Riwayat →</a>
        </div>

        <?php if (count($laporan) > 0): ?>
            <div class="report-list">
                <?php foreach ($laporan as $item): ?>
                    <article class="report-item">

                        <?php
                        $foto = $item['foto'] ?? '';
                        $fotoPath = '../assets/uploads/barang/' . basename($foto);
                        ?>

                        <?php if ($foto !== '' && is_file(__DIR__ . '/../assets/uploads/barang/' . basename($foto))): ?>
                            <img
                                class="report-image"
                                src="<?= e($fotoPath) ?>"
                                alt="Foto barang"
                            >
                        <?php else: ?>
                            <div class="report-image report-placeholder">📦</div>
                        <?php endif; ?>

                        <div class="report-info">
                            <h3><?= e($item['nama_barang']) ?></h3>
                            <p>
                                <?= e($item['kategori']) ?> ·
                                <?= e($item['lokasi']) ?><br>
                                <?= e(date('d-m-Y', strtotime($item['tanggal_kejadian']))) ?>
                            </p>

                            <span class="report-type">
                                <?= $item['jenis'] === 'hilang' ? 'BARANG HILANG' : 'BARANG TEMUAN' ?>
                            </span>
                        </div>

                        <span class="status status-<?= e($item['status']) ?>">
                            <?= e(labelStatus($item['status'])) ?>
                        </span>

                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <strong>Belum ada laporan</strong>
                Kamu belum membuat laporan barang hilang atau temuan.
                <br>
                <a href="tambah-laporan.php" class="btn-main"
                   style="margin-top:15px">
                    Buat Laporan Pertama
                </a>
            </div>
        <?php endif; ?>
    </section>

    <p class="extra-stats">
        Laporan ditolak: <?= $rejected ?>.
        Status laporan dapat berubah setelah diperiksa oleh admin.
    </p>

</main>

</body>
</html>