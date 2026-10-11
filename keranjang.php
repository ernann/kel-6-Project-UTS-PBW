<?php
session_start();
require_once 'functions.php';

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

foreach ($_SESSION['keranjang'] as $id => $isi) {
    if (!is_array($isi)) {
        $_SESSION['keranjang'][$id] = ['39' => (int)$isi];
    }
}

function eCart($nilai): string
{
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}

function kembaliCart(string $pesan, string $tipe = 'success'): void
{
    $_SESSION['pesan_keranjang'] = $pesan;
    $_SESSION['tipe_pesan_keranjang'] = $tipe;
    header('Location: keranjang.php');
    exit;
}

// Ubah jumlah barang.
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['aksi'] ?? '') === 'ubah') {

    $idProduk = filter_input(INPUT_POST, 'id_produk', FILTER_VALIDATE_INT);
    $ukuran = (string)($_POST['ukuran'] ?? '');
    $jumlah = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_INT);

    if (!$idProduk || !in_array($ukuran, ['38', '39', '40', '41'], true)
        || !$jumlah || $jumlah < 1) {
        kembaliCart('Data perubahan jumlah tidak valid.', 'error');
    }

    $produk = ambilProdukById($conn, (int)$idProduk);

    if (!$produk) {
        kembaliCart('Produk tidak ditemukan.', 'error');
    }

    $jumlahUkuranLain = 0;

    foreach ($_SESSION['keranjang'][$idProduk] ?? [] as $ukuranLama => $qtyLama) {
        if ((string)$ukuranLama !== $ukuran) {
            $jumlahUkuranLain += (int)$qtyLama;
        }
    }

    if ($jumlah + $jumlahUkuranLain > (int)$produk['stok']) {
        kembaliCart('Jumlah melebihi stok yang tersedia.', 'error');
    }

    $_SESSION['keranjang'][$idProduk][$ukuran] = (int)$jumlah;
    kembaliCart('Jumlah barang berhasil diperbarui.');
}

// Hapus satu kombinasi produk dan ukuran.
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['aksi'] ?? '') === 'hapus') {

    $idProduk = filter_input(INPUT_POST, 'id_produk', FILTER_VALIDATE_INT);
    $ukuran = (string)($_POST['ukuran'] ?? '');

    if ($idProduk && in_array($ukuran, ['38', '39', '40', '41'], true)) {
        unset($_SESSION['keranjang'][$idProduk][$ukuran]);

        if (empty($_SESSION['keranjang'][$idProduk])) {
            unset($_SESSION['keranjang'][$idProduk]);
        }
    }

    kembaliCart('Barang berhasil dihapus dari keranjang.');
}

$pesan = $_SESSION['pesan_keranjang'] ?? '';
$tipePesan = $_SESSION['tipe_pesan_keranjang'] ?? 'success';
unset($_SESSION['pesan_keranjang'], $_SESSION['tipe_pesan_keranjang']);

$items = [];
$subtotalSemua = 0;

foreach ($_SESSION['keranjang'] as $idProduk => $ukuranList) {
    $produk = ambilProdukById($conn, (int)$idProduk);

    if (!$produk) {
        unset($_SESSION['keranjang'][$idProduk]);
        continue;
    }

    foreach ($ukuranList as $ukuran => $jumlah) {
        $jumlah = (int)$jumlah;

        if ($jumlah < 1) {
            continue;
        }

        $subtotal = (float)$produk['harga'] * $jumlah;
        $key = $idProduk . '|' . $ukuran;

        $items[] = [
            'key' => $key,
            'id_produk' => (int)$idProduk,
            'nama' => $produk['nama_produk'],
            'kode' => $produk['kode_produk'],
            'gambar' => $produk['gambar'],
            'ukuran' => (string)$ukuran,
            'jumlah' => $jumlah,
            'harga' => (float)$produk['harga'],
            'stok' => (int)$produk['stok'],
            'subtotal' => $subtotal
        ];

        $subtotalSemua += $subtotal;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Keranjang | STEPORA</title>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,sans-serif;background:#f7f7f7;color:#222}
a{text-decoration:none;color:inherit}
header{background:#fff;box-shadow:0 2px 10px #0000000d}
.nav{max-width:1100px;margin:auto;padding:18px 22px;display:flex;justify-content:space-between;align-items:center;gap:15px}
.logo{font-size:24px;font-weight:800;letter-spacing:2px;color:#182b24}
.nav a:hover{color:#198754}
.container{max-width:1100px;margin:30px auto;padding:0 20px}
h1{margin-bottom:8px}
.muted{color:#6c757d}
.notice{padding:13px 16px;border-radius:8px;margin:18px 0}
.notice.success{background:#dff5e5;color:#155d2d}
.notice.error{background:#ffe4e4;color:#9a2020}
.layout{display:grid;grid-template-columns:1fr 300px;gap:22px;align-items:start}
.panel{background:#fff;border:1px solid #eee;border-radius:12px;padding:18px;overflow:hidden}
.table-wrap{overflow-x:auto}
table{border-collapse:collapse;width:100%;min-width:650px}
th,td{text-align:left;padding:13px 10px;border-bottom:1px solid #eee;vertical-align:middle}
th{font-size:13px;color:#666}
.item{display:flex;align-items:center;gap:12px}
.item img{width:70px;height:70px;object-fit:cover;border-radius:8px;background:#eee}
.item strong{display:block;margin-bottom:5px}
.small{font-size:12px;color:#777}
.price{font-weight:bold;white-space:nowrap}
input[type=number]{width:70px;padding:8px;border:1px solid #ddd;border-radius:6px}
.btn{border:0;border-radius:7px;padding:9px 12px;font-weight:bold;cursor:pointer;display:inline-block}
.btn-dark{background:#182b24;color:white}
.btn-grey{background:#eee;color:#222}
.btn-red{background:#ffe5e5;color:#a32222}
.btn-green{background:#198754;color:#fff;width:100%;padding:13px}
.actions{display:flex;gap:6px;flex-wrap:wrap}
.summary h2{margin-top:0}
.summary-row{display:flex;justify-content:space-between;gap:12px;margin:15px 0}
.summary-row.total{font-size:19px;font-weight:bold;border-top:1px solid #ddd;padding-top:15px}
.promo{padding:12px;background:#edf7ef;border-radius:8px;color:#205c35;font-size:13px;line-height:1.5}
.empty{text-align:center;padding:45px 15px}
footer{text-align:center;color:#777;font-size:13px;padding:25px}
@media(max-width:800px){.layout{grid-template-columns:1fr}.nav{align-items:flex-start;flex-direction:column}.container{padding:0 12px}}
</style>
</head>
<body>
<header>
    <div class="nav">
        <a class="logo" href="index.php">STEPORA</a>
        <a href="index.php">← Lanjut Belanja</a>
    </div>
</header>

<main class="container">
    <h1>Keranjang Belanja</h1>
    <p class="muted">Centang hanya barang yang ingin kamu beli sekarang.</p>

    <?php if ($pesan !== ''): ?>
        <div class="notice <?= $tipePesan === 'error' ? 'error' : 'success' ?>">
            <?= eCart($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if (!$items): ?>
        <div class="panel empty">
            <h2>Keranjang masih kosong</h2>
            <p class="muted">Pilih sepatu yang kamu suka dari katalog STEPORA.</p>
            <a class="btn btn-dark" href="index.php">Lihat Koleksi</a>
        </div>
    <?php else: ?>
    <div class="layout">
        <section class="panel">
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="pilihSemua" aria-label="Pilih semua"></th>
                        <th>Produk</th>
                        <th>Ukuran</th>
                        <th>Harga</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                        <th>Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="pilihan"
                                   name="pilihan[]"
                                   form="formCheckout"
                                   value="<?= eCart($item['key']) ?>"
                                   data-subtotal="<?= $item['subtotal'] ?>">
                        </td>
                        <td>
                            <div class="item">
                                <img src="assets/images/<?= eCart(basename($item['gambar'] ?: 'sepatu1.jpg')) ?>"
                                     alt="<?= eCart($item['nama']) ?>"
                                     onerror="this.onerror=null;this.src='assets/images/sepatu1.jpg';">
                                <div>
                                    <strong><?= eCart($item['nama']) ?></strong>
                                    <span class="small"><?= eCart($item['kode']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= eCart($item['ukuran']) ?></td>
                        <td class="price"><?= formatRupiah($item['harga']) ?></td>
                        <td>
                            <form method="POST" action="keranjang.php">
                                <input type="hidden" name="aksi" value="ubah">
                                <input type="hidden" name="id_produk" value="<?= $item['id_produk'] ?>">
                                <input type="hidden" name="ukuran" value="<?= eCart($item['ukuran']) ?>">
                                <input type="number" name="jumlah" min="1"
                                       max="<?= $item['stok'] ?>"
                                       value="<?= $item['jumlah'] ?>" required>
                                <button class="btn btn-grey" type="submit">Ubah</button>
                            </form>
                        </td>
                        <td class="price"><?= formatRupiah($item['subtotal']) ?></td>
                        <td>
                            <form method="POST" action="keranjang.php"
                                  onsubmit="return confirm('Hapus barang ini dari keranjang?')">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_produk" value="<?= $item['id_produk'] ?>">
                                <input type="hidden" name="ukuran" value="<?= eCart($item['ukuran']) ?>">
                                <button class="btn btn-red" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted">Subtotal dan diskon di sebelah kanan mengikuti barang yang dicentang.</p>
        </section>

        <aside class="panel summary">
            <h2>Ringkasan Pilihan</h2>
            <div class="summary-row">
                <span>Barang dipilih</span>
                <strong id="jumlahDipilih">0</strong>
            </div>
            <div class="summary-row">
                <span>Subtotal</span>
                <strong id="subtotalDipilih">Rp 0</strong>
            </div>
            <div class="summary-row">
                <span>Diskon 10%</span>
                <strong id="diskonDipilih">Rp 0</strong>
            </div>
            <div class="summary-row total">
                <span>Total bayar</span>
                <strong id="totalDipilih">Rp 0</strong>
            </div>

            <div class="promo">
                Diskon 10% berlaku jika subtotal barang pilihan mencapai Rp500.000.
            </div>

            <form id="formCheckout" method="POST" action="proses.php" style="margin-top:16px">
                <input type="hidden" name="aksi" value="checkout">
                <button class="btn btn-green" id="tombolBeli" type="submit" disabled>
                    Beli Barang Pilihan
                </button>
            </form>
            <p class="small">Pembayaran pada proyek ini merupakan simulasi dan langsung dianggap lunas.</p>
        </aside>
    </div>
    <?php endif; ?>
</main>

<footer>STEPORA — Keranjang Belanja</footer>

<script>
const pilihan = Array.from(document.querySelectorAll('.pilihan'));
const pilihSemua = document.getElementById('pilihSemua');
const tombolBeli = document.getElementById('tombolBeli');

function rupiah(angka) {
    return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
}

function perbaruiRingkasan() {
    const dicentang = pilihan.filter(item => item.checked);
    const subtotal = dicentang.reduce(
        (total, item) => total + Number(item.dataset.subtotal || 0), 0
    );
    const diskon = subtotal >= 500000 ? subtotal * 0.10 : 0;

    document.getElementById('jumlahDipilih').textContent = dicentang.length;
    document.getElementById('subtotalDipilih').textContent = rupiah(subtotal);
    document.getElementById('diskonDipilih').textContent = rupiah(diskon);
    document.getElementById('totalDipilih').textContent = rupiah(subtotal - diskon);

    if (tombolBeli) tombolBeli.disabled = dicentang.length === 0;
    if (pilihSemua) {
        pilihSemua.checked = pilihan.length > 0 && dicentang.length === pilihan.length;
        pilihSemua.indeterminate = dicentang.length > 0 && dicentang.length < pilihan.length;
    }
}

pilihan.forEach(item => item.addEventListener('change', perbaruiRingkasan));

if (pilihSemua) {
    pilihSemua.addEventListener('change', function() {
        pilihan.forEach(item => item.checked = pilihSemua.checked);
        perbaruiRingkasan();
    });
}

const formCheckout = document.getElementById('formCheckout');
if (formCheckout) {
    formCheckout.addEventListener('submit', function(event) {
        if (!pilihan.some(item => item.checked)) {
            event.preventDefault();
            alert('Pilih setidaknya satu barang untuk checkout.');
        }
    });
}

perbaruiRingkasan();
</script>
</body>
</html>