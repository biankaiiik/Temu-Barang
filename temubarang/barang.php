<?php
require_once __DIR__ . '/config/database.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$q = trim($_GET['q'] ?? '');
$kategori = trim($_GET['kategori'] ?? '');
$jenis = trim($_GET['jenis'] ?? '');

$kategoriList = [
    'Elektronik',
    'Alat Tulis',
    'Pakaian',
    'Aksesori',
    'Kartu Identitas',
    'Lainnya'
];

$jenisValid = ['hilang', 'temuan'];

$where = ["status = 'approved'"];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = '(nama_barang LIKE ? OR deskripsi LIKE ? OR lokasi LIKE ?)';
    $keyword = '%' . $q . '%';

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= 'sss';
}

if ($kategori !== '' && in_array($kategori, $kategoriList, true)) {
    $where[] = 'kategori = ?';
    $params[] = $kategori;
    $types .= 's';
}

if ($jenis !== '' && in_array($jenis, $jenisValid, true)) {
    $where[] = 'jenis = ?';
    $params[] = $jenis;
    $types .= 's';
}

$whereSQL = implode(' AND ', $where);

// Pagination
$perPage = 9;
$page = max(1, (int) ($_GET['page'] ?? 1));

$countSQL = "SELECT COUNT(*) AS total FROM laporan WHERE $whereSQL";
$countStmt = mysqli_prepare($conn, $countSQL);

if ($params) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}

mysqli_stmt_execute($countStmt);
$total = (int) mysqli_fetch_assoc(
    mysqli_stmt_get_result($countStmt)
)['total'];

$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$dataSQL = "SELECT id_laporan, jenis, nama_barang, kategori,
                   deskripsi, lokasi, tanggal_kejadian, foto
            FROM laporan
            WHERE $whereSQL
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";

$dataStmt = mysqli_prepare($conn, $dataSQL);
$dataTypes = $types . 'ii';
$dataParams = array_merge($params, [$perPage, $offset]);

mysqli_stmt_bind_param($dataStmt, $dataTypes, ...$dataParams);
mysqli_stmt_execute($dataStmt);
$result = mysqli_stmt_get_result($dataStmt);

function pageUrl($pageNumber) {
    $query = $_GET;
    $query['page'] = $pageNumber;

    return 'barang.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jelajahi Barang — TemuBarang</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .browse-header {
            padding: 55px 28px 35px;
            background: #f5f8f5;
            border-bottom: 1px solid #e7ece8;
        }

        .browse-header-inner,
        .browse-container {
            max-width: 1144px;
            margin: auto;
        }

        .browse-header h1 {
            margin-top: 12px;
            font-family: 'Manrope', sans-serif;
            font-size: clamp(30px, 4vw, 42px);
            letter-spacing: -1.5px;
        }

        .browse-header p {
            margin-top: 8px;
            color: #77857f;
            font-size: 14px;
        }

        .browse-container {
            padding: 35px 28px 75px;
        }

        .filter-panel {
            padding: 20px;
            border: 1px solid #e7ece8;
            border-radius: 14px;
            background: #fff;
        }

        .filter-search {
            display: flex;
            gap: 10px;
        }

        .filter-search input,
        .filter-row select {
            width: 100%;
            min-width: 0;
            min-height: 45px;
            padding: 10px 13px;
            color: #1e302b;
            background: #fff;
            border: 1px solid #dce5de;
            border-radius: 9px;
            outline: none;
        }

        .filter-search input:focus,
        .filter-row select:focus {
            border-color: #176b55;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 12px;
            margin-top: 13px;
        }

        .filter-row button {
            min-height: 45px;
        }

        .filter-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 30px 0 20px;
        }

        .filter-bottom h2 {
            font-family: 'Manrope', sans-serif;
            font-size: 22px;
        }

        .filter-count {
            color: #77857f;
            font-size: 12px;
        }

        .clear-filter {
            color: #176b55;
            font-size: 12px;
            font-weight: 700;
        }

        .pagination {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 35px;
        }

        .pagination a,
        .pagination span {
            min-width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            padding: 0 12px;
            border: 1px solid #e1e9e2;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
        }

        .pagination a:hover,
        .pagination .current {
            color: white;
            background: #176b55;
            border-color: #176b55;
        }

        .pagination .disabled {
            color: #aab4ad;
            background: #f7f9f7;
        }

        @media (max-width: 600px) {
            .browse-header {
                padding: 38px 18px 28px;
            }

            .browse-container {
                padding: 25px 18px 50px;
            }

            .filter-panel {
                padding: 14px;
            }

            .filter-search {
                flex-direction: column;
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .filter-bottom h2 {
                font-size: 18px;
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

<section class="browse-header">
    <div class="browse-header-inner">
        <span class="section-kicker">PUSAT INFORMASI BARANG</span>
        <h1>Jelajahi Barang</h1>
        <p>Cari barang hilang atau temuan di lingkungan sekolah.</p>
    </div>
</section>

<main class="browse-container">
    <form action="barang.php" method="GET" class="filter-panel">
        <div class="filter-search">
            <input
                type="search"
                name="q"
                value="<?= e($q) ?>"
                placeholder="Cari nama barang, deskripsi, atau lokasi..."
            >
            <button class="btn btn-primary" type="submit">Cari</button>
        </div>

        <div class="filter-row">
            <select name="kategori" aria-label="Pilih kategori">
                <option value="">Semua kategori</option>
                <?php foreach ($kategoriList as $item): ?>
                    <option
                        value="<?= e($item) ?>"
                        <?= $kategori === $item ? 'selected' : '' ?>
                    >
                        <?= e($item) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="jenis" aria-label="Pilih jenis laporan">
                <option value="">Barang hilang & temuan</option>
                <option value="hilang" <?= $jenis === 'hilang' ? 'selected' : '' ?>>
                    Barang hilang
                </option>
                <option value="temuan" <?= $jenis === 'temuan' ? 'selected' : '' ?>>
                    Barang temuan
                </option>
            </select>

            <button class="btn btn-primary" type="submit">Terapkan Filter</button>
        </div>
    </form>

    <div class="filter-bottom">
        <div>
            <h2>Daftar Barang</h2>
            <p class="filter-count">
                Ditemukan <?= number_format($total, 0, ',', '.') ?> laporan
            </p>
        </div>

        <?php if ($q !== '' || $kategori !== '' || $jenis !== ''): ?>
            <a href="barang.php" class="clear-filter">Hapus filter ×</a>
        <?php endif; ?>
    </div>

    <div class="report-grid">
        <?php if ($total > 0 && mysqli_num_rows($result) > 0): ?>
            <?php while ($barang = mysqli_fetch_assoc($result)): ?>
                <article class="report-card">
                    <a
                        href="detail.php?id=<?= (int) $barang['id_laporan'] ?>"
                        class="report-image"
                    >
                        <?php if (!empty($barang['foto'])): ?>
                            <img
                                src="assets/uploads/barang/<?= rawurlencode(basename($barang['foto'])) ?>"
                                alt="<?= e($barang['nama_barang']) ?>"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="no-image">
                                <span>▧</span>
                                <small>Foto tidak tersedia</small>
                            </div>
                        <?php endif; ?>

                        <span class="report-badge <?= $barang['jenis'] === 'hilang' ? 'badge-lost' : 'badge-found' ?>">
                            <?= $barang['jenis'] === 'hilang' ? 'Barang Hilang' : 'Barang Temuan' ?>
                        </span>
                    </a>

                    <div class="report-info">
                        <span class="report-category"><?= e($barang['kategori']) ?></span>

                        <h3>
                            <a href="detail.php?id=<?= (int) $barang['id_laporan'] ?>">
                                <?= e($barang['nama_barang']) ?>
                            </a>
                        </h3>

                        <p class="report-description">
                            <?= e(mb_strimwidth($barang['deskripsi'], 0, 105, '...')) ?>
                        </p>

                        <div class="report-meta">
                            <span>⌖ <?= e($barang['lokasi']) ?></span>
                            <span><?= date('d M Y', strtotime($barang['tanggal_kejadian'])) ?></span>
                        </div>

                        <a
                            href="detail.php?id=<?= (int) $barang['id_laporan'] ?>"
                            class="report-link"
                        >
                            Lihat detail <span>→</span>
                        </a>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">⌕</div>
                <h3>
                    <?= $q !== '' || $kategori !== '' || $jenis !== ''
                        ? 'Barang tidak ditemukan'
                        : 'Belum ada laporan barang' ?>
                </h3>
                <p>
                    <?= $q !== '' || $kategori !== '' || $jenis !== ''
                        ? 'Coba ubah kata kunci atau filter pencarianmu.'
                        : 'Laporan yang sudah disetujui admin akan ditampilkan di halaman ini.' ?>
                </p>

                <?php if ($q !== '' || $kategori !== '' || $jenis !== ''): ?>
                    <a href="barang.php" class="btn btn-outline">Lihat semua barang</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Navigasi halaman">
            <?php if ($page > 1): ?>
                <a href="<?= e(pageUrl($page - 1)) ?>">←</a>
            <?php else: ?>
                <span class="disabled">←</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e(pageUrl($i)) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= e(pageUrl($page + 1)) ?>">→</a>
            <?php else: ?>
                <span class="disabled">→</span>
            <?php endif; ?>
        </nav>
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