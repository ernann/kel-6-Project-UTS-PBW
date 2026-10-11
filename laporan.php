
<?php
require_once 'functions.php';

// ============================================
// 1. RINGKASAN PRODUK
// ============================================

$queryProduk = $conn->query(
    "SELECT
        COUNT(*) AS jumlah_produk,
        COALESCE(SUM(stok), 0) AS total_stok
     FROM produk"
);

$ringkasanProduk = $queryProduk->fetch_assoc();

$jumlahProduk = (int) $ringkasanProduk['jumlah_produk'];
$totalStok = (int) $ringkasanProduk['total_stok'];

// ============================================
// 2. RINGKASAN TRANSAKSI
// ============================================

$queryTransaksi = $conn->query(
    "SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(total), 0) AS total_penjualan,
        COALESCE(SUM(diskon), 0) AS total_diskon,
        COALESCE(SUM(total_bayar), 0) AS total_pendapatan
     FROM transaksi"
);

$ringkasanTransaksi = $queryTransaksi->fetch_assoc();

$jumlahTransaksi = (int) $ringkasanTransaksi['jumlah_transaksi'];
$totalPenjualan = (float) $ringkasanTransaksi['total_penjualan'];
$totalDiskon = (float) $ringkasanTransaksi['total_diskon'];
$totalPendapatan = (float) $ringkasanTransaksi['total_pendapatan'];

// ============================================
// 3. RIWAYAT TRANSAKSI
// ============================================

$queryRiwayat = $conn->query(
    "SELECT
        t.id_transaksi,
        t.tanggal,
        t.total,
        t.diskon,
        t.total_bayar,
        COALESCE(SUM(dt.jumlah), 0) AS total_item
     FROM transaksi t
     LEFT JOIN detail_transaksi dt
        ON t.id_transaksi = dt.id_transaksi
     GROUP BY
        t.id_transaksi,
        t.tanggal,
        t.total,
        t.diskon,
        t.total_bayar
     ORDER BY t.id_transaksi DESC"
);

// ============================================
// 4. RINCIAN PRODUK TERJUAL
// ============================================

$queryTerjual = $conn->query(
    "SELECT
        p.kode_produk,
        p.nama_produk,
        COALESCE(SUM(dt.jumlah), 0) AS jumlah_terjual,
        COALESCE(SUM(dt.subtotal), 0) AS nilai_penjualan
     FROM detail_transaksi dt
     INNER JOIN produk p
        ON dt.id_produk = p.id_produk
     GROUP BY p.id_produk, p.kode_produk, p.nama_produk
     ORDER BY jumlah_terjual DESC"
);

// ============================================
// 5. PRODUK DENGAN STOK RENDAH
// ============================================

$queryStok = $conn->query(
    "SELECT
        id_produk,
        kode_produk,
        nama_produk,
        kategori,
        harga,
        stok
     FROM produk
     WHERE stok <= " . BATAS_STOK_MINIMUM . "
     ORDER BY stok ASC, nama_produk ASC"
);

// ============================================
// 6. FUNGSI ESCAPE HTML
// ============================================

function aman($nilai)
{
    return htmlspecialchars(
        (string) $nilai,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan - STEPORA</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .report-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .report-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .report-heading h1 {
            margin: 8px 0;
        }

        .report-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .report-actions a {
            text-decoration: none;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 35px;
        }

        .summary-card {
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid #eee;
        }

        .summary-card p {
            margin: 0 0 10px;
            color: #666;
        }

        .summary-card h2 {
            margin: 0;
            font-size: 22px;
            overflow-wrap: anywhere;
        }

        .report-section {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 25px;
        }

        .report-section h2 {
            margin-top: 0;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        .report-table th,
        .report-table td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .report-table th {
            background: #f7f7f7;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-habis {
            background: #ffe5e5;
            color: #a00000;
        }

        .status-menipis {
            background: #fff1cf;
            color: #805700;
        }

        .empty-message {
            padding: 20px 0;
            color: #666;
        }

        @media (max-width: 900px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 480px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .report-container {
                padding: 0 12px;
            }

            .report-section {
                padding: 15px;
            }
        }

        @media print {
            .navbar,
            .report-actions,
            .footer {
                display: none !important;
            }

            body {
                background: white;
            }

            .report-container {
                margin: 0;
                max-width: none;
            }

            .report-section,
            .summary-card {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

<!-- NAVIGASI -->

<header class="navbar">
    <a href="index.php" class="logo">
        STEPORA<span>.</span>
    </a>

    <nav>
        <a href="index.php">Beranda</a>
        <a href="index.php#produk">Koleksi</a>
        <a href="laporan.php" class="active">Laporan</a>
    </nav>

    <a href="index.php" class="nav-button">
        Kembali ke Toko
    </a>
</header>

<main class="report-container">

    <!-- JUDUL LAPORAN -->

    <div class="report-heading">
        <div>
            <p class="eyebrow">STEPORA ADMIN</p>
            <h1>Laporan Toko</h1>
            <p>Ringkasan produk, stok, dan transaksi penjualan.</p>
        </div>

        <div class="report-actions">
            <a href="index.php" class="button-reset">
                Kembali
            </a>

            <button
                type="button"
                class="button-primary"
                onclick="window.print()"
            >
                Cetak Laporan
            </button>
        </div>
    </div>

    <!-- RINGKASAN -->

    <section class="summary-grid">

        <article class="summary-card">
            <p>Total Jenis Produk</p>
            <h2><?= $jumlahProduk ?></h2>
        </article>

        <article class="summary-card">
            <p>Total Stok Tersedia</p>
            <h2><?= $totalStok ?></h2>
        </article>

        <article class="summary-card">
            <p>Total Transaksi</p>
            <h2><?= $jumlahTransaksi ?></h2>
        </article>

        <article class="summary-card">
            <p>Total Pendapatan</p>
            <h2><?= formatRupiah($totalPendapatan) ?></h2>
        </article>

    </section>

    <!-- RINGKASAN KEUANGAN -->

    <section class="report-section">
        <h2>Ringkasan Keuangan</h2>

        <div class="table-wrapper">
            <table class="report-table">
                <tbody>
                    <tr>
                        <th>Total Penjualan Sebelum Diskon</th>
                        <td><?= formatRupiah($totalPenjualan) ?></td>
                    </tr>

                    <tr>
                        <th>Total Diskon</th>
                        <td><?= formatRupiah($totalDiskon) ?></td>
                    </tr>

                    <tr>
                        <th>Total Pendapatan Setelah Diskon</th>
                        <td><strong><?= formatRupiah($totalPendapatan) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- RIWAYAT TRANSAKSI -->

    <section class="report-section">
        <h2>Riwayat Transaksi</h2>

        <?php if ($queryRiwayat->num_rows > 0): ?>

            <div class="table-wrapper">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Total Item</th>
                            <th>Subtotal</th>
                            <th>Diskon</th>
                            <th>Total Bayar</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($transaksi = $queryRiwayat->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= (int) $transaksi['id_transaksi'] ?></td>

                                <td>
                                    <?= aman(formatTanggal($transaksi['tanggal'])) ?>
                                </td>

                                <td>
                                    <?= (int) $transaksi['total_item'] ?>
                                </td>

                                <td>
                                    <?= formatRupiah($transaksi['total']) ?>
                                </td>

                                <td>
                                    <?= formatRupiah($transaksi['diskon']) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= formatRupiah($transaksi['total_bayar']) ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <p class="empty-message">
                Belum ada transaksi. Transaksi yang berhasil dilakukan
                akan muncul di sini.
            </p>

        <?php endif; ?>
    </section>

    <!-- PRODUK TERJUAL -->

    <section class="report-section">
        <h2>Produk Terjual</h2>

        <?php if ($queryTerjual->num_rows > 0): ?>

            <div class="table-wrapper">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Kode Produk</th>
                            <th>Nama Produk</th>
                            <th>Jumlah Terjual</th>
                            <th>Nilai Penjualan</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($item = $queryTerjual->fetch_assoc()): ?>
                            <tr>
                                <td><?= aman($item['kode_produk']) ?></td>

                                <td><?= aman($item['nama_produk']) ?></td>

                                <td><?= (int) $item['jumlah_terjual'] ?></td>

                                <td>
                                    <?= formatRupiah($item['nilai_penjualan']) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <p class="empty-message">
                Belum ada produk yang terjual.
            </p>

        <?php endif; ?>
    </section>

    <!-- STOK MENIPIS -->

    <section class="report-section">
        <h2>Peringatan Stok</h2>

        <?php if ($queryStok->num_rows > 0): ?>

            <div class="table-wrapper">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Kode Produk</th>
                            <th>Nama Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($item = $queryStok->fetch_assoc()): ?>
                            <?php
                            $stok = (int) $item['stok'];
                            $kelasStatus = $stok <= 0
                                ? 'status-habis'
                                : 'status-menipis';
                            ?>

                            <tr>
                                <td><?= aman($item['kode_produk']) ?></td>

                                <td><?= aman($item['nama_produk']) ?></td>

                                <td><?= aman($item['kategori']) ?></td>

                                <td><?= formatRupiah($item['harga']) ?></td>

                                <td><?= $stok ?></td>

                                <td>
                                    <span class="status <?= $kelasStatus ?>">
                                        <?= aman(statusStok($stok)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <p class="empty-message">
                Semua produk memiliki stok di atas batas minimum.
            </p>

        <?php endif; ?>
    </section>

</main>

<footer class="footer">
    <a href="index.php" class="logo">
        STEPORA<span>.</span>
    </a>

    <p>STEP INTO YOUR STYLE.</p>

    <small>
        &copy; <?= date('Y') ?> STEPORA. All rights reserved.
    </small>
</footer>

</body>
</html>
