<?php
require_once __DIR__ . '/config/database.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    $barang = null;
} else {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id_laporan, jenis, nama_barang, kategori,
                deskripsi, lokasi, tanggal_kejadian, foto, created_at
         FROM laporan
         WHERE id_laporan = ? AND status = 'approved'
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $barang = mysqli_fetch_assoc($result);

    if (!$barang) {
        http_response_code(404);
    }
}

function tanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April',
        'Mei', 'Juni', 'Juli', 'Agustus', 'September',
        'Oktober', 'November', 'Desember'
    ];

    $waktu = strtotime($tanggal);

    if (!$waktu) {
        return '-';
    }

    return date('j', $waktu) . ' ' .
        $bulan[(int) date('n', $waktu)] . ' ' .
        date('Y', $waktu);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $barang ? e($barang['nama_barang']) . ' — TemuBarang' : 'Barang Tidak Ditemukan — TemuBarang' ?>
    </title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .detail-container {
            max-width: 1050px;
            margin: auto;
            padding: 35px 25px 75px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
            color: #176b55;
            font-size: 13px;
            font-weight: 700;
        }

        .detail-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 35px;
            align-items: start;
        }

        .detail-photo {
            min-height: 330px;
            overflow: hidden;
            border: 1px solid #e7ece8;
            border-radius: 17px;
            background: #f1f5f1;
        }

        .detail-photo img {
            width: 100%;
            min-height: 330px;
            max-height: 490px;
            display: block;
            object-fit: contain;
            background: #f1f5f1;
        }

        .detail-no-photo {
            min-height: 330px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #829389;
        }

        .detail-no-photo span {
            font-size: 55px;
        }

        .detail-no-photo p {
            font-size: 12px;
        }

        .detail-content {
            padding: 5px 0;
        }

        .detail-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-bottom: 15px;
        }

        .detail-badge {
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 800;
        }

        .detail-approved {
            color: #176b55;
            background: #e9f7ed;
        }

        .detail-content h1 {
            margin: 10px 0;
            font-family: 'Manrope', sans-serif;
            font-size: clamp(28px, 4vw, 38px);
            line-height: 1.25;
            letter-spacing: -1px;
            overflow-wrap: anywhere;
        }

        .detail-category {
            color: #176b55;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .detail-description {
            margin-top: 22px;
            color: #65756c;
            font-size: 14px;
            line-height: 1.9;
            overflow-wrap: anywhere;
        }

        .detail-information {
            margin-top: 25px;
            padding: 19px;
            border: 1px solid #e7ece8;
            border-radius: 13px;
        }

        .information-row {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            padding: 12px 0;
            border-bottom: 1px solid #edf1ed;
        }

        .information-row:first-child {
            padding-top: 0;
        }

        .information-row:last-child {
            padding-bottom: 0;
            border-bottom: none;
        }

        .information-icon {
            width: 35px;
            height: 35px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            color: #176b55;
            background: #e9f4ef;
            border-radius: 9px;
        }

        .information-label {
            display: block;
            color: #829087;
            font-size: 11px;
        }

        .information-value {
            display: block;
            margin-top: 3px;
            font-size: 13px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .detail-notice {
            margin-top: 20px;
            padding: 15px;
            color: #52685b;
            background: #f1f7f2;
            border: 1px solid #e0ece2;
            border-radius: 11px;
            font-size: 12px;
            line-height: 1.8;
        }

        .detail-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .not-found {
            max-width: 540px;
            margin: 55px auto 80px;
            padding: 35px 22px;
            text-align: center;
            border: 1px solid #e7ece8;
            border-radius: 17px;
            background: #f8faf8;
        }

        .not-found h1 {
            margin-top: 10px;
            font-family: 'Manrope', sans-serif;
            font-size: 25px;
        }

        .not-found p {
            margin: 10px 0 20px;
            color: #77857f;
            font-size: 13px;
        }

        @media (max-width: 700px) {
            .detail-container {
                padding: 25px 18px 50px;
            }

            .detail-layout {
                grid-template-columns: 1fr;
                gap: 25px;
            }

            .detail-photo,
            .detail-photo img,
            .detail-no-photo {
                min-height: 250px;
            }

            .detail-photo img {
                max-height: 370px;
            }

            .detail-information {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<header class="navbar">
    <a href="index.php" class="brand">
        <span class="brand-icon">T</span>
        <span>TemuBarang<span class="brand-dot">.</span></span>
    </a>

    <nav class="nav-links">
        <a href="index.php">Beranda</a>
        <a href="barang.php" class="active">Jelajahi Barang</a>
    </nav>

    <div class="nav-actions">
        <a href="auth/login.php" class="login-link">Masuk</a>
        <a href="auth/register.php" class="btn btn-primary btn-small">Daftar</a>
    </div>
</header>

<main class="detail-container">
    <?php if ($barang): ?>

        <a href="barang.php" class="back-link">← Kembali ke daftar barang</a>

        <div class="detail-layout">
            <div class="detail-photo">
                <?php if (!empty($barang['foto'])): ?>
                    <img
                        src="assets/uploads/barang/<?= rawurlencode(basename($barang['foto'])) ?>"
                        alt="<?= e($barang['nama_barang']) ?>"
                    >
                <?php else: ?>
                    <div class="detail-no-photo">
                        <span>▧</span>
                        <p>Foto barang tidak tersedia</p>
                    </div>
                <?php endif; ?>
            </div>

            <section class="detail-content">
                <div class="detail-badges">
                    <span class="detail-badge <?= $barang['jenis'] === 'hilang' ? 'badge-lost' : 'badge-found' ?>">
                        <?= $barang['jenis'] === 'hilang' ? 'Barang Hilang' : 'Barang Temuan' ?>
                    </span>

                    <span class="detail-badge detail-approved">
                        ✓ Terverifikasi
                    </span>
                </div>

                <span class="detail-category"><?= e($barang['kategori']) ?></span>

                <h1><?= e($barang['nama_barang']) ?></h1>

                <p class="detail-description">
                    <?= nl2br(e($barang['deskripsi'])) ?>
                </p>

                <div class="detail-information">
                    <div class="information-row">
                        <span class="information-icon">⌖</span>
                        <div>
                            <span class="information-label">Lokasi kejadian</span>
                            <span class="information-value"><?= e($barang['lokasi']) ?></span>
                        </div>
                    </div>

                    <div class="information-row">
                        <span class="information-icon">▦</span>
                        <div>
                            <span class="information-label">Tanggal kejadian</span>
                            <span class="information-value">
                                <?= tanggalIndonesia($barang['tanggal_kejadian']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="information-row">
                        <span class="information-icon">◷</span>
                        <div>
                            <span class="information-label">Tanggal laporan dibuat</span>
                            <span class="information-value">
                                <?= tanggalIndonesia($barang['created_at']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="detail-notice">
                    <strong>Perlu bantuan?</strong><br>
                    Untuk menjaga privasi warga sekolah, kontak pelapor
                    tidak ditampilkan secara publik. Silakan masuk ke akun
                    untuk melanjutkan proses klaim barang temuan.
                </div>

                <div class="detail-actions">
                    <?php if ($barang['jenis'] === 'temuan'): ?>
                        <a href="auth/login.php" class="btn btn-primary">
                            Ajukan Klaim Barang →
                        </a>
                    <?php else: ?>
                        <a href="auth/login.php" class="btn btn-primary">
                            Masuk untuk Melanjutkan →
                        </a>
                    <?php endif; ?>

                    <a href="barang.php" class="btn btn-outline">
                        Lihat Barang Lain
                    </a>
                </div>
            </section>
        </div>

    <?php else: ?>

        <div class="not-found">
            <div class="empty-icon">⌕</div>
            <h1>Barang tidak ditemukan</h1>
            <p>
                Laporan mungkin tidak tersedia atau belum disetujui admin.
                Coba cari barang lain melalui daftar laporan.
            </p>
            <a href="barang.php" class="btn btn-primary">
                Kembali ke Daftar Barang
            </a>
        </div>

    <?php endif; ?>
</main>

<footer class="footer">
    <a href="index.php" class="brand footer-brand">
        <span class="brand-icon">T</span>
        <span>TemuBarang<span class="brand-dot">.</span></span>
    </a>

    <p>Mempertemukan barang dengan pemiliknya di lingkungan sekolah.</p>
    <span class="footer-copy">© <?= date('Y') ?> TemuBarang</span>
</footer>

</body>
</html>