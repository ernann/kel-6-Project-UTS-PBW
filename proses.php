<?php
session_start();
require_once 'functions.php';

// ============================================
// TAMPILKAN STRUK SETELAH CHECKOUT
// ============================================
if (($_GET['status'] ?? '') === 'berhasil') {
    $struk = $_SESSION['transaksi_terakhir'] ?? null;
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Checkout Berhasil | STEPORA</title>
        <style>
        body{font-family:Arial,sans-serif;background:#f5f7f5;margin:0;color:#222}
        .box{max-width:720px;margin:45px auto;background:white;border-radius:14px;padding:28px;box-shadow:0 5px 25px #0000000d}
        h1{color:#198754}
        .muted{color:#666}
        table{width:100%;border-collapse:collapse;margin:20px 0}
        th,td{text-align:left;padding:12px 8px;border-bottom:1px solid #eee}
        .row{display:flex;justify-content:space-between;gap:15px;margin:12px 0}
        .total{font-size:20px;font-weight:bold;border-top:1px solid #ddd;padding-top:15px}
        .btn{display:inline-block;padding:12px 17px;background:#182b24;color:white;text-decoration:none;border-radius:8px;margin:8px 8px 0 0}
        @media(max-width:600px){.box{margin:15px;padding:18px}}
        </style>
    </head>
    <body>
    <main class="box">
        <?php if (!$struk): ?>
            <h1>Transaksi selesai</h1>
            <p class="muted">Struk transaksi ini tidak tersedia pada sesi saat ini.</p>
        <?php else: ?>
            <h1>✓ Checkout Berhasil!</h1>
            <p>Pembayaran simulasi berhasil dan status transaksi adalah <strong>Lunas</strong>.</p>
            <p class="muted">
                Nomor transaksi: #<?= (int)$struk['id_transaksi'] ?><br>
                Tanggal: <?= htmlspecialchars($struk['tanggal'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <table>
                <thead>
                <tr><th>Produk</th><th>Ukuran</th><th>Jumlah</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                <?php foreach ($struk['items'] as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['nama'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($item['ukuran'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int)$item['jumlah'] ?></td>
                    <td><?= formatRupiah($item['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="row">
                <span>Subtotal</span>
                <strong><?= formatRupiah($struk['subtotal']) ?></strong>
            </div>
            <div class="row">
                <span>Diskon</span>
                <strong>- <?= formatRupiah($struk['diskon']) ?></strong>
            </div>
            <div class="row total">
                <span>Total dibayar</span>
                <strong><?= formatRupiah($struk['total_bayar']) ?></strong>
            </div>
        <?php endif; ?>

        <a class="btn" href="index.php">Kembali ke Beranda</a>
        <a class="btn" href="keranjang.php">Lihat Keranjang</a>
    </main>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// VALIDASI REQUEST CHECKOUT
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || ($_POST['aksi'] ?? '') !== 'checkout') {
    header('Location: keranjang.php');
    exit;
}

if (empty($_SESSION['keranjang']) || empty($_POST['pilihan'])
    || !is_array($_POST['pilihan'])) {
    $_SESSION['pesan_keranjang'] = 'Pilih setidaknya satu barang untuk checkout.';
    $_SESSION['tipe_pesan_keranjang'] = 'error';
    header('Location: keranjang.php');
    exit;
}

// Validasi pilihan: setiap nilai harus berupa id_produk|ukuran.
$pilihan = [];

foreach ($_POST['pilihan'] as $nilai) {
    if (!is_string($nilai) || !preg_match('/^([1-9][0-9]*)\|(38|39|40|41)$/', $nilai, $cocok)) {
        $_SESSION['pesan_keranjang'] = 'Pilihan barang tidak valid.';
        $_SESSION['tipe_pesan_keranjang'] = 'error';
        header('Location: keranjang.php');
        exit;
    }

    $idProduk = (int)$cocok[1];
    $ukuran = $cocok[2];
    $key = $idProduk . '|' . $ukuran;

    // Hilangkan pilihan duplikat.
    $pilihan[$key] = [
        'id_produk' => $idProduk,
        'ukuran' => $ukuran
    ];
}

// Pastikan setiap pilihan benar-benar ada di keranjang sesi.
$itemsPilihan = [];

foreach ($pilihan as $key => $pilih) {
    $idProduk = $pilih['id_produk'];
    $ukuran = $pilih['ukuran'];

    $jumlah = (int)(
        $_SESSION['keranjang'][$idProduk][$ukuran] ?? 0
    );

    if ($jumlah < 1) {
        $_SESSION['pesan_keranjang'] = 'Ada barang yang tidak lagi tersedia di keranjang.';
        $_SESSION['tipe_pesan_keranjang'] = 'error';
        header('Location: keranjang.php');
        exit;
    }

    $itemsPilihan[$key] = [
        'id_produk' => $idProduk,
        'ukuran' => $ukuran,
        'jumlah' => $jumlah
    ];
}

$conn->begin_transaction();

try {
    // Kunci stok produk agar tidak berubah saat transaksi sedang diproses.
    $jumlahPerProduk = [];

    foreach ($itemsPilihan as $item) {
        $idProduk = $item['id_produk'];
        $jumlahPerProduk[$idProduk] =
            ($jumlahPerProduk[$idProduk] ?? 0) + $item['jumlah'];
    }

    $produkTerpilih = [];
    $subtotal = 0.0;

    foreach ($jumlahPerProduk as $idProduk => $jumlahTerpilih) {
        $stmt = $conn->prepare(
            "SELECT id_produk, kode_produk, nama_produk, harga, stok
             FROM produk
             WHERE id_produk = ?
             FOR UPDATE"
        );

        $stmt->bind_param('i', $idProduk);
        $stmt->execute();
        $produk = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$produk) {
            throw new Exception('Ada produk yang sudah tidak tersedia.');
        }

        if (!stokMencukupi((int)$produk['stok'], (int)$jumlahTerpilih)) {
            throw new Exception(
                'Stok ' . $produk['nama_produk']
                . ' tidak mencukupi. Silakan periksa kembali keranjang.'
            );
        }

        $produkTerpilih[$idProduk] = $produk;
    }

    // Hitung subtotal menggunakan harga database, bukan harga dari browser.
    $detailUntukStruk = [];

    foreach ($itemsPilihan as $key => $item) {
        $produk = $produkTerpilih[$item['id_produk']];
        $harga = (float)$produk['harga'];
        $jumlah = (int)$item['jumlah'];
        $nilaiSubtotal = hitungSubtotal($harga, $jumlah);

        $subtotal += $nilaiSubtotal;

        $detailUntukStruk[$key] = [
            'id_produk' => (int)$produk['id_produk'],
            'nama' => $produk['nama_produk'],
            'ukuran' => $item['ukuran'],
            'jumlah' => $jumlah,
            'harga' => $harga,
            'subtotal' => $nilaiSubtotal
        ];
    }

    $diskon = hitungDiskon($subtotal);
    $totalBayar = hitungTotal($subtotal, $diskon);

    // Simulasi toko online: langsung dianggap lunas.
    $statusPembayaran = 'Lunas';

    $stmtTransaksi = $conn->prepare(
        "INSERT INTO transaksi
         (total, diskon, total_bayar, status_pembayaran)
         VALUES (?, ?, ?, ?)"
    );

    $stmtTransaksi->bind_param(
        'ddds',
        $subtotal,
        $diskon,
        $totalBayar,
        $statusPembayaran
    );

    if (!$stmtTransaksi->execute()) {
        throw new Exception('Gagal menyimpan transaksi.');
    }

    $idTransaksi = (int)$conn->insert_id;
    $stmtTransaksi->close();

    $stmtDetail = $conn->prepare(
        "INSERT INTO detail_transaksi
         (id_transaksi, id_produk, jumlah, ukuran, harga, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    foreach ($detailUntukStruk as $item) {
        $idProduk = $item['id_produk'];
        $jumlah = $item['jumlah'];
        $ukuran = $item['ukuran'];
        $harga = $item['harga'];
        $nilaiSubtotal = $item['subtotal'];

        $stmtDetail->bind_param(
            'iiisdd',
            $idTransaksi,
            $idProduk,
            $jumlah,
            $ukuran,
            $harga,
            $nilaiSubtotal
        );

        if (!$stmtDetail->execute()) {
            throw new Exception('Gagal menyimpan detail transaksi.');
        }
    }

    $stmtDetail->close();

    // Kurangi stok hanya untuk barang yang benar-benar dibeli.
    $stmtStok = $conn->prepare(
        "UPDATE produk SET stok = stok - ? WHERE id_produk = ? AND stok >= ?"
    );

    foreach ($jumlahPerProduk as $idProduk => $jumlah) {
        $stmtStok->bind_param('iii', $jumlah, $idProduk, $jumlah);

        if (!$stmtStok->execute() || $stmtStok->affected_rows !== 1) {
            throw new Exception('Gagal memperbarui stok produk.');
        }
    }

    $stmtStok->close();

    // Simpan struk sementara sebelum commit.
    $tanggal = date('d-m-Y H:i:s');

    $conn->commit();

    // Hapus hanya ukuran yang dibeli. Pilihan lain tetap di keranjang.
    foreach ($itemsPilihan as $item) {
        $idProduk = $item['id_produk'];
        $ukuran = $item['ukuran'];

        unset($_SESSION['keranjang'][$idProduk][$ukuran]);

        if (empty($_SESSION['keranjang'][$idProduk])) {
            unset($_SESSION['keranjang'][$idProduk]);
        }
    }

    $_SESSION['transaksi_terakhir'] = [
        'id_transaksi' => $idTransaksi,
        'tanggal' => $tanggal,
        'items' => array_values($detailUntukStruk),
        'subtotal' => $subtotal,
        'diskon' => $diskon,
        'total_bayar' => $totalBayar
    ];

    header('Location: proses.php?status=berhasil');
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    $_SESSION['pesan_keranjang'] = $e instanceof Exception
        ? $e->getMessage()
        : 'Checkout gagal. Silakan coba kembali.';

    $_SESSION['tipe_pesan_keranjang'] = 'error';

    header('Location: keranjang.php');
    exit;
}