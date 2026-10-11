<?php
session_start();
require_once 'functions.php';

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

// Menyesuaikan format keranjang lama jika sebelumnya hanya menyimpan jumlah.
foreach ($_SESSION['keranjang'] as $id => $isi) {
    if (!is_array($isi)) {
        $_SESSION['keranjang'][$id] = ['39' => (int)$isi];
    }
}

function eIndex($nilai): string
{
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}

function jumlahKeranjangIndex(): int
{
    $jumlah = 0;

    foreach ($_SESSION['keranjang'] as $ukuran) {
        if (is_array($ukuran)) {
            $jumlah += array_sum(array_map('intval', $ukuran));
        }
    }

    return $jumlah;
}

// Tambah produk ke keranjang.
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['aksi'] ?? '') === 'tambah') {

    $idProduk = filter_input(INPUT_POST, 'id_produk', FILTER_VALIDATE_INT);
    $ukuran = (string)($_POST['ukuran'] ?? '');
    $jumlah = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_INT);

    if (!$jumlah || $jumlah < 1) {
        $jumlah = 1;
    }

    if (!$idProduk || !in_array($ukuran, ['38', '39', '40', '41'], true)) {
        redirectPesan('Produk atau ukuran tidak valid.', 'error');
    }

    $produk = ambilProdukById($conn, (int)$idProduk);

    if (!$produk) {
        redirectPesan('Produk tidak ditemukan.', 'error');
    }

    $jumlahDiKeranjang = 0;

    foreach ($_SESSION['keranjang'][$idProduk] ?? [] as $qty) {
        $jumlahDiKeranjang += (int)$qty;
    }

    if ($jumlahDiKeranjang + $jumlah > (int)$produk['stok']) {
        redirectPesan('Jumlah melebihi stok produk yang tersedia.', 'error');
    }

    if (!isset($_SESSION['keranjang'][$idProduk])) {
        $_SESSION['keranjang'][$idProduk] = [];
    }

    $_SESSION['keranjang'][$idProduk][$ukuran] =
        (int)($_SESSION['keranjang'][$idProduk][$ukuran] ?? 0) + $jumlah;

    redirectPesan(
        $produk['nama_produk'] . ' ukuran ' . $ukuran . ' berhasil ditambahkan.',
        'success'
    );
}

$pesan = (string)($_GET['pesan'] ?? '');
$tipePesan = (string)($_GET['tipe'] ?? 'success');

$cari = trim((string)($_GET['cari'] ?? ''));
$kategoriDipilih = trim((string)($_GET['kategori'] ?? ''));

$sql = "SELECT * FROM produk WHERE 1=1";
$parameter = [];
$tipeParameter = '';

if ($cari !== '') {
    $sql .= " AND (nama_produk LIKE ? OR kode_produk LIKE ?)";
    $kataCari = '%' . $cari . '%';
    $parameter[] = $kataCari;
    $parameter[] = $kataCari;
    $tipeParameter .= 'ss';
}

if ($kategoriDipilih !== '') {
    $sql .= " AND kategori = ?";
    $parameter[] = $kategoriDipilih;
    $tipeParameter .= 's';
}

$sql .= " ORDER BY id_produk DESC";

$stmt = $conn->prepare($sql);

if ($parameter) {
    $stmt->bind_param($tipeParameter, ...$parameter);
}

$stmt->execute();
$produkList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$hasilKategori = $conn->query(
    "SELECT DISTINCT kategori FROM produk
     WHERE kategori IS NOT NULL AND kategori <> ''
     ORDER BY kategori"
);

$kategoriList = $hasilKategori
    ? $hasilKategori->fetch_all(MYSQLI_ASSOC)
    : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>STEPORA | Toko Sepatu</title>
<style>
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;font-family:Arial,sans-serif;background:#f7f7f7;color:#222}
a{text-decoration:none;color:inherit}
.navbar{position:sticky;top:0;z-index:10;background:#fff;box-shadow:0 2px 10px #0000000d}
.nav-inner{max-width:1150px;margin:auto;padding:17px 22px;display:flex;align-items:center;justify-content:space-between;gap:18px}
.logo{font-size:25px;font-weight:800;letter-spacing:2px;color:#182b24}
.nav-links{display:flex;gap:22px;align-items:center;flex-wrap:wrap}
.nav-links a{font-size:14px;font-weight:600;padding:8px 0;transition:.2s}
.nav-links a:hover{color:#198754}
.cart-link{background:#182b24;color:#fff!important;padding:10px 14px!important;border-radius:8px}
.hero{background:#182b24;color:white}
.hero-inner{max-width:1150px;margin:auto;padding:65px 22px;display:grid;grid-template-columns:1.2fr .8fr;align-items:center;gap:35px}
.hero h1{font-size:clamp(36px,5vw,56px);margin:0 0 18px;line-height:1.08}
.hero p{line-height:1.7;color:#d7e1db;max-width:520px}
.hero img{width:100%;height:300px;object-fit:cover;border-radius:18px;background:#fff}
.btn{display:inline-block;border:0;border-radius:8px;padding:12px 18px;font-weight:700;cursor:pointer}
.btn-light{background:#fff;color:#182b24;margin-top:10px}
.container{max-width:1150px;margin:auto;padding:38px 22px}
.section-title{margin:0 0 8px;font-size:28px}
.subtext{color:#6c757d;line-height:1.6}
.filter{display:flex;gap:10px;flex-wrap:wrap;margin:24px 0}
.filter input,.filter select{padding:12px;border:1px solid #ddd;border-radius:8px;background:white;min-width:180px}
.filter button{background:#182b24;color:white}
.products{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:22px;margin-top:22px}
.product{background:white;border-radius:14px;overflow:hidden;border:1px solid #eee;box-shadow:0 4px 14px #00000008}
.product img{width:100%;height:220px;object-fit:cover;background:#eee}
.product-body{padding:17px}
.product-code{font-size:12px;color:#777}
.product h3{margin:8px 0}
.category{display:inline-block;background:#edf4ef;color:#315b43;border-radius:20px;padding:5px 10px;font-size:12px}
.price{font-size:20px;font-weight:800;margin:12px 0}
.stock{font-size:13px;color:#555;margin-bottom:13px}
.product select,.product input[type=number]{padding:10px;border:1px solid #ddd;border-radius:7px;width:100%;margin-bottom:10px}
.product button{width:100%;background:#182b24;color:#fff}
.product button:disabled{opacity:.45;cursor:not-allowed}
.promo{background:#e7f1e9;border-radius:15px;padding:25px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
.promo strong{font-size:26px;color:#17663c}
.notice{padding:13px 16px;border-radius:9px;margin-bottom:20px}
.notice.success{background:#dff5e5;color:#155d2d}
.notice.error{background:#ffe4e4;color:#9a2020}
.empty{padding:30px;background:#fff;border-radius:12px;color:#666}
footer{text-align:center;padding:25px;background:#182b24;color:#d7e1db;font-size:13px}
.toast{position:fixed;top:85px;right:20px;z-index:30;background:#fff;border-left:5px solid #198754;box-shadow:0 8px 30px #0002;border-radius:10px;padding:16px;width:min(360px,calc(100% - 40px));display:flex;gap:12px;align-items:flex-start}
.toast.error{border-left-color:#dc3545}
.toast-content{flex:1;line-height:1.5}
.toast a{display:inline-block;margin-top:8px;color:#198754;font-weight:bold}
.toast-close{border:0;background:transparent;font-size:20px;cursor:pointer}
@media(max-width:700px){.hero-inner{grid-template-columns:1fr;padding-top:42px}.hero img{height:220px}.nav-inner{align-items:flex-start;flex-direction:column}.nav-links{gap:14px}.container{padding:28px 16px}}
</style>
</head>
<body>

<header class="navbar">
    <div class="nav-inner">
        <a class="logo" href="index.php">STEPORA</a>
        <nav class="nav-links">
            <a href="index.php">Beranda</a>
            <a href="#produk">Koleksi</a>
            <a href="#promo">Promo</a>
            <a class="cart-link" href="keranjang.php">
                Keranjang (<?= jumlahKeranjangIndex() ?>)
            </a>
        </nav>
    </div>
</header>

<?php if ($pesan !== ''): ?>
<div class="toast <?= eIndex($tipePesan === 'error' ? 'error' : '') ?>" id="toast">
    <div class="toast-content">
        <strong><?= $tipePesan === 'error' ? 'Belum berhasil' : 'Berhasil' ?></strong><br>
        <?= eIndex($pesan) ?>
        <br><a href="keranjang.php">Lihat Keranjang</a>
    </div>
    <button class="toast-close" type="button" onclick="tutupToast()">×</button>
</div>
<?php endif; ?>

<section class="hero">
    <div class="hero-inner">
        <div>
            <p style="letter-spacing:3px;font-weight:bold">STEP INTO YOUR STYLE</p>
            <h1>Temukan Sepatu untuk Setiap Langkah.</h1>
            <p>Pilih sepatu favoritmu, tentukan ukuran, dan pesan dengan mudah melalui STEPORA.</p>
            <a class="btn btn-light" href="#produk">Lihat Koleksi</a>
        </div>
        <img src="assets/images/sepatu1.jpg" alt="Koleksi sepatu STEPORA"
             onerror="this.style.display='none'">
    </div>
</section>

<main class="container">
    <section id="promo" class="promo">
        <div>
            <h2 class="section-title">Promo STEPORA</h2>
            <p class="subtext">Belanja minimal Rp500.000 dalam satu checkout pilihan dan dapatkan diskon 10%.</p>
        </div>
        <strong>Diskon 10%</strong>
    </section>

    <section id="produk" style="padding-top:40px">
        <h2 class="section-title">Koleksi Sepatu</h2>
        <p class="subtext">Cari produk dan pilih ukuran sepatu yang sesuai.</p>

        <form class="filter" method="GET" action="index.php">
            <input type="text" name="cari" placeholder="Cari nama atau kode sepatu..."
                   value="<?= eIndex($cari) ?>">
            <select name="kategori">
                <option value="">Semua kategori</option>
                <?php foreach ($kategoriList as $kat): ?>
                    <option value="<?= eIndex($kat['kategori']) ?>"
                        <?= $kategoriDipilih === $kat['kategori'] ? 'selected' : '' ?>>
                        <?= eIndex($kat['kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Cari Produk</button>
            <a class="btn" style="background:#e5e5e5" href="index.php">Reset</a>
        </form>

        <?php if (!$produkList): ?>
            <div class="empty">Produk tidak ditemukan.</div>
        <?php else: ?>
        <div class="products">
            <?php foreach ($produkList as $produk): ?>
            <article class="product">
                <img
                    src="assets/images/<?= eIndex(basename($produk['gambar'] ?: 'sepatu1.jpg')) ?>"
                    alt="<?= eIndex($produk['nama_produk']) ?>"
                    onerror="this.onerror=null;this.src='assets/images/sepatu1.jpg';"
                >
                <div class="product-body">
                    <span class="product-code"><?= eIndex($produk['kode_produk']) ?></span>
                    <h3><?= eIndex($produk['nama_produk']) ?></h3>
                    <span class="category"><?= eIndex($produk['kategori']) ?></span>
                    <div class="price"><?= formatRupiah($produk['harga']) ?></div>
                    <div class="stock"><?= eIndex(labelStatusStok($produk['stok'])) ?>:
                        <?= (int)$produk['stok'] ?> pasang
                    </div>

                    <form method="POST" action="index.php">
                        <input type="hidden" name="aksi" value="tambah">
                        <input type="hidden" name="id_produk" value="<?= (int)$produk['id_produk'] ?>">

                        <label for="ukuran<?= (int)$produk['id_produk'] ?>">Ukuran sepatu</label>
                        <select id="ukuran<?= (int)$produk['id_produk'] ?>" name="ukuran" required>
                            <option value="38">38</option>
                            <option value="39" selected>39</option>
                            <option value="40">40</option>
                            <option value="41">41</option>
                        </select>

                        <label for="jumlah<?= (int)$produk['id_produk'] ?>">Jumlah</label>
                        <input id="jumlah<?= (int)$produk['id_produk'] ?>"
                               type="number" name="jumlah" min="1" value="1" required>

                        <button class="btn" type="submit"
                            <?= (int)$produk['stok'] <= 0 ? 'disabled' : '' ?>>
                            <?= (int)$produk['stok'] <= 0 ? 'Stok Habis' : 'Tambah ke Keranjang' ?>
                        </button>
                    </form>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</main>

<footer>© <?= date('Y') ?> STEPORA — Toko Sepatu</footer>

<script>
function tutupToast() {
    const toast = document.getElementById('toast');
    if (toast) toast.remove();
}
setTimeout(tutupToast, 4000);
</script>
</body>
</html>