
<?php
require_once 'functions.php';

echo "<h2>TES DATABASE TOKO</h2>";

echo "<p>Database: toko_kelompok</p>";

// Cek jumlah produk
$hasil = $conn->query("SELECT COUNT(*) AS jumlah FROM produk");

if (!$hasil) {
    die("Gagal mengecek produk: " . $conn->error);
}

$data = $hasil->fetch_assoc();

echo "<p>Jumlah produk saat ini: " . $data['jumlah'] . "</p>";

// Tambahkan data jika tabel kosong
if ((int) $data['jumlah'] === 0) {
    $sql = "INSERT INTO produk
        (kode_produk, nama_produk, kategori, harga, stok, gambar)
        VALUES
        ('SP001', 'Sepatu Sport', 'Sport', 250000, 10, 'sepatu1.jpg'),
        ('SP002', 'Sepatu Casual', 'Casual', 200000, 8, 'sepatu1.jpg'),
        ('SP003', 'Sepatu Running', 'Running', 300000, 12, 'sepatu1.jpg')";

    if ($conn->query($sql)) {
        echo "<p>Berhasil menambahkan 3 produk.</p>";
    } else {
        die("<p>Gagal menambahkan produk: " . $conn->error . "</p>");
    }
} else {
    echo "<p>Data produk sudah tersedia, tidak ditambahkan ulang.</p>";
}

// Tampilkan data produk
$hasilProduk = $conn->query("SELECT * FROM produk");

if (!$hasilProduk) {
    die("Gagal mengambil data: " . $conn->error);
}

echo "<h3>Daftar Produk</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Kode</th><th>Nama</th><th>Harga</th><th>Stok</th><th>Gambar</th></tr>";

while ($produk = $hasilProduk->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($produk['id_produk']) . "</td>";
    echo "<td>" . htmlspecialchars($produk['kode_produk']) . "</td>";
    echo "<td>" . htmlspecialchars($produk['nama_produk']) . "</td>";
    echo "<td>" . htmlspecialchars($produk['harga']) . "</td>";
    echo "<td>" . htmlspecialchars($produk['stok']) . "</td>";
    echo "<td>" . htmlspecialchars($produk['gambar']) . "</td>";
    echo "</tr>";
}

echo "</table>";
?>
