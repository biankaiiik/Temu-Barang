<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Cek sesi login
$loggedIn = isset($_SESSION['id_pengguna']);
$role = $_SESSION['role'] ?? '';

// Ambil laporan yang sudah disetujui admin
$sql = "SELECT id_laporan, jenis, nama_barang, kategori,
               deskripsi, lokasi, tanggal_kejadian, foto
        FROM laporan
        WHERE status = 'approved'
        ORDER BY created_at DESC
        LIMIT 6";

$result = mysqli_query($conn, $sql);

// Hitung jumlah laporan yang sudah disetujui
$countResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM laporan
     WHERE status = 'approved'"
);

$totalBarang = $countResult
    ? (int) mysqli_fetch_assoc($countResult)['total']
    : 0;

// Kategori untuk ditampilkan
$kategoriList = [
    'Elektronik',
    'Alat Tulis',
    'Pakaian',
    'Aksesori',
    'Kartu Identitas',
    'Lainnya'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TemuBarang — Temukan Barangmu</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="navbar">
    <a href="index.php" class="brand">
        <span class="brand-icon">T</span>
        <span>TemuBarang<span class="brand-dot">.</span></span>
    </a>

    <nav class="nav-links">
        <a href="index.php" class="active">Beranda</a>
        <a href="barang.php">Jelajahi Barang</a>
    </nav>

    <div class="nav-actions">
        <?php if ($loggedIn && $role === 'pengguna'): ?>

            <a href="pengguna/index.php" class="btn btn-primary btn-small">
                Dashboard Saya
            </a>
            <a href="auth/logout.php" class="login-link">
                Logout
            </a>

        <?php elseif ($loggedIn && $role === 'admin'): ?>

            <a href="admin/index.php" class="btn btn-primary btn-small">
                Dashboard Admin
            </a>
            <a href="auth/logout.php" class="login-link">
                Keluar
            </a>

        <?php else: ?>

            <a href="auth/login.php" class="login-link">Masuk</a>
            <a href="auth/register.php" class="btn btn-primary btn-small">
                Daftar
            </a>

        <?php endif; ?>
    </div>
</header>

<main>
    <section class="hero">
        <div class="hero-content">
            <span class="eyebrow">
                <span class="eyebrow-dot"></span>
                PLATFORM KEHILANGAN BARANG SEKOLAH
            </span>

            <h1>Barang hilang?<br>
                <span>Temukan kembali.</span>
            </h1>

            <p class="hero-description">
                Tempat untuk melaporkan barang hilang dan menemukan
                barang yang tertinggal di lingkungan sekolah.
                Siapa tahu, barangmu sedang menunggu untuk ditemukan.
            </p>

            <form action="barang.php" method="GET" class="search-form">
                <span class="search-icon">⌕</span>
                <input
                    type="text"
                    name="q"
                    placeholder="Cari nama barang, misalnya botol minum..."
                    aria-label="Cari barang"
                >
                <button type="submit" class="btn btn-primary">
                    Cari Barang
                </button>
            </form>

            <div class="hero-note">
                <span class="note-check">✓</span>
                Informasi laporan dapat dilihat publik setelah disetujui admin.
            </div>
        </div>

        <div class="hero-visual">
            <div class="visual-orbit orbit-one"></div>
            <div class="visual-orbit orbit-two"></div>

            <div class="visual-card">
                <div class="visual-top">
                    <span class="visual-label">TEMUBARANG</span>
                    <span class="visual-status">● AKTIF</span>
                </div>

                <div class="visual-symbol">⌕</div>

                <h3>Satu barang,<br>satu cerita.</h3>
                <p>Bantu barang yang hilang menemukan pemiliknya kembali.</p>

                <div class="visual-bottom">
                    <span>Lingkungan sekolah</span>
                    <span>↗</span>
                </div>
            </div>

            <div class="floating-tag tag-top">
                <span class="tag-icon green">✓</span>
                <span>
                    <strong>Laporan terverifikasi</strong>
                    <small>Ditinjau oleh admin</small>
                </span>
            </div>

            <div class="floating-tag tag-bottom">
                <span class="tag-icon orange">⌕</span>
                <span>
                    <strong>Lebih mudah mencari</strong>
                    <small>Barang berdasarkan kategori</small>
                </span>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="stat-item">
            <span class="stat-number">
                <?= number_format($totalBarang, 0, ',', '.') ?>
            </span>
            <span class="stat-label">Laporan terpublikasi</span>
        </div>

        <div class="stat-divider"></div>

        <div class="stat-item">
            <span class="stat-number">01</span>
            <span class="stat-label">Tujuan utama: lingkungan sekolah</span>
        </div>

        <div class="stat-divider"></div>

        <div class="stat-item">
            <span class="stat-number">24/7</span>
            <span class="stat-label">Akses informasi publik</span>
        </div>
    </section>

    <section class="category-section">
        <div class="section-heading">
            <div>
                <span class="section-kicker">JELAJAHI</span>
                <h2>Cari berdasarkan kategori</h2>
                <p>Pilih kategori untuk menemukan barang yang kamu cari.</p>
            </div>

            <a href="barang.php" class="text-link">
                Lihat semua barang <span>→</span>
            </a>
        </div>

        <div class="category-grid">
            <?php foreach ($kategoriList as $kategori): ?>
                <a
                    class="category-card"
                    href="barang.php?kategori=<?= urlencode($kategori) ?>"
                >
                    <span class="category-icon">
                        <?php
                        $icons = [
                            'Elektronik' => '▣',
                            'Alat Tulis' => '✎',
                            'Pakaian' => '◇',
                            'Aksesori' => '✧',
                            'Kartu Identitas' => '▤',
                            'Lainnya' => '•••'
                        ];

                        echo $icons[$kategori];
                        ?>
                    </span>

                    <span class="category-name">
                        <?= e($kategori) ?>
                    </span>

                    <span class="category-arrow">↗</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="latest-section">
        <div class="section-heading">
            <div>
                <span class="section-kicker">LAPORAN TERBARU</span>
                <h2>Barang yang baru dilaporkan</h2>
                <p>Lihat laporan kehilangan dan temuan yang telah disetujui.</p>
            </div>

            <a href="barang.php" class="text-link">
                Jelajahi semua <span>→</span>
            </a>
        </div>

        <div class="report-grid">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>

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
                            <span class="report-category">
                                <?= e($barang['kategori']) ?>
                            </span>

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
                                <span>
                                    <?= date('d M Y', strtotime($barang['tanggal_kejadian'])) ?>
                                </span>
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
                    <h3>Belum ada laporan terbaru</h3>
                    <p>
                        Laporan yang sudah disetujui admin akan muncul di sini.
                        Silakan cek kembali nanti.
                    </p>
                    <a href="barang.php" class="btn btn-outline">
                        Jelajahi barang
                    </a>
                </div>

            <?php endif; ?>
        </div>
    </section>

    <section class="cta-section">
        <div class="cta-content">
            <span class="section-kicker">PUNYA INFORMASI?</span>
            <h2>Bantu temukan pemilik barangnya.</h2>
            <p>
                Menemukan barang atau kehilangan sesuatu di sekolah?
                Buat laporan agar informasinya bisa menjangkau orang yang tepat.
            </p>
        </div>

        <a
            href="<?= $loggedIn && $role === 'pengguna'
                ? 'pengguna/tambah-laporan.php'
                : 'auth/login.php' ?>"
            class="btn btn-light"
        >
            Buat Laporan <span>→</span>
        </a>
    </section>
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