<?php
// ============================================
// 1. KONEKSI DATABASE
// ============================================
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'toko_kelompok';

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Koneksi MySQL gagal: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$database`")) {
    die("Gagal membuat database: " . $conn->error);
}

if (!$conn->select_db($database)) {
    die("Gagal memilih database: " . $conn->error);
}

// ============================================
// 2. TABEL PRODUK
// ============================================
$sql = "CREATE TABLE IF NOT EXISTS produk (
    id_produk INT AUTO_INCREMENT PRIMARY KEY,
    kode_produk VARCHAR(20) NOT NULL UNIQUE,
    nama_produk VARCHAR(100) NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    harga DECIMAL(12,2) NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    gambar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB";

if (!$conn->query($sql)) {
    die("Gagal membuat tabel produk: " . $conn->error);
}

// ============================================
// 3. TABEL TRANSAKSI
// ============================================
$sql = "CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    tanggal_transaksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    diskon DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_bayar DECIMAL(12,2) NOT NULL DEFAULT 0,
    status_pembayaran VARCHAR(20) NOT NULL DEFAULT 'Lunas'
) ENGINE=InnoDB";

if (!$conn->query($sql)) {
    die("Gagal membuat tabel transaksi: " . $conn->error);
}

// ============================================
// 4. TABEL DETAIL TRANSAKSI
// ============================================
$sql = "CREATE TABLE IF NOT EXISTS detail_transaksi (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_produk INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 1,
    ukuran VARCHAR(2) NOT NULL DEFAULT '39',
    harga DECIMAL(12,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_transaksi)
        REFERENCES transaksi(id_transaksi)
        ON DELETE CASCADE,
    FOREIGN KEY (id_produk)
        REFERENCES produk(id_produk)
        ON DELETE RESTRICT
) ENGINE=InnoDB";

if (!$conn->query($sql)) {
    die("Gagal membuat tabel detail transaksi: " . $conn->error);
}

// ============================================
// 5. MIGRASI KOLOM DATABASE LAMA
// ============================================
function pastikanKolom(
    mysqli $conn,
    string $tabel,
    string $kolom,
    string $definisi
): void {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS jumlah
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?"
    );

    $stmt->bind_param('ss', $tabel, $kolom);
    $stmt->execute();

    $ada = (int)$stmt->get_result()->fetch_assoc()['jumlah'] > 0;
    $stmt->close();

    if (!$ada) {
        if (!$conn->query(
            "ALTER TABLE `$tabel` ADD COLUMN `$kolom` $definisi"
        )) {
            die("Gagal menambahkan kolom $kolom: " . $conn->error);
        }
    }
}

pastikanKolom(
    $conn,
    'transaksi',
    'diskon',
    'DECIMAL(12,2) NOT NULL DEFAULT 0'
);

pastikanKolom(
    $conn,
    'transaksi',
    'total_bayar',
    'DECIMAL(12,2) NOT NULL DEFAULT 0'
);

pastikanKolom(
    $conn,
    'transaksi',
    'status_pembayaran',
    "VARCHAR(20) NOT NULL DEFAULT 'Lunas'"
);

pastikanKolom(
    $conn,
    'detail_transaksi',
    'ukuran',
    "VARCHAR(2) NOT NULL DEFAULT '39'"
);

// ============================================
// 6. DATA PRODUK AWAL
// Hanya dimasukkan jika tabel produk kosong.
// ============================================
$cekProduk = $conn->query("SELECT COUNT(*) AS jumlah FROM produk");

if ($cekProduk && (int)$cekProduk->fetch_assoc()['jumlah'] === 0) {
    $produkAwal = [
        ['SP001', 'Sepatu Sport', 'Sport', 250000, 10, 'sepatu1.jpg'],
        ['SP002', 'Sepatu Casual', 'Casual', 200000, 8, 'sepatu1.jpg'],
        ['SP003', 'Sepatu Running', 'Running', 300000, 12, 'sepatu1.jpg']
    ];

    $stmt = $conn->prepare(
        "INSERT INTO produk
        (kode_produk, nama_produk, kategori, harga, stok, gambar)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    foreach ($produkAwal as $p) {
        $kode = $p[0];
        $nama = $p[1];
        $kategori = $p[2];
        $harga = $p[3];
        $stok = $p[4];
        $gambar = $p[5];

        $stmt->bind_param(
            'ss sdis',
            $kode,
            $nama,
            $kategori,
            $harga,
            $stok,
            $gambar
        );

        if (!$stmt->execute()) {
            die("Gagal memasukkan produk: " . $stmt->error);
        }
    }

    $stmt->close();
}

// ============================================
// 7. ATURAN PROMO DAN STOK
// ============================================
define('BATAS_MINIMAL_DISKON', 500000);
define('PERSENTASE_DISKON', 0.10);
define('BATAS_STOK_RENDAH', 5);

// ============================================
// 8. FUNGSI UMUM
// ============================================
function escapeHTML($data): string
{
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

function formatRupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function redirectPesan($pesan, $tipe = 'success'): void
{
    header(
        'Location: index.php?pesan=' . urlencode($pesan)
        . '&tipe=' . urlencode($tipe)
    );
    exit;
}

function ambilSemuaProduk(mysqli $conn): array
{
    $hasil = $conn->query(
        "SELECT * FROM produk ORDER BY id_produk DESC"
    );

    return $hasil ? $hasil->fetch_all(MYSQLI_ASSOC) : [];
}

function ambilProdukById(mysqli $conn, int $idProduk): ?array
{
    $stmt = $conn->prepare(
        "SELECT * FROM produk WHERE id_produk = ?"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $idProduk);
    $stmt->execute();

    $produk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $produk ?: null;
}

function hitungJumlahProduk(mysqli $conn): int
{
    $hasil = $conn->query(
        "SELECT COUNT(*) AS jumlah FROM produk"
    );

    return $hasil ? (int)$hasil->fetch_assoc()['jumlah'] : 0;
}

function hitungTotalStok(mysqli $conn): int
{
    $hasil = $conn->query(
        "SELECT SUM(stok) AS total_stok FROM produk"
    );

    return $hasil
        ? (int)($hasil->fetch_assoc()['total_stok'] ?? 0)
        : 0;
}

function hitungTotalTransaksi(mysqli $conn): int
{
    $hasil = $conn->query(
        "SELECT COUNT(*) AS jumlah FROM transaksi"
    );

    return $hasil ? (int)$hasil->fetch_assoc()['jumlah'] : 0;
}

function hitungPendapatan(mysqli $conn): float
{
    $hasil = $conn->query(
        "SELECT SUM(total_bayar) AS pendapatan FROM transaksi"
    );

    return $hasil
        ? (float)($hasil->fetch_assoc()['pendapatan'] ?? 0)
        : 0;
}

function hitungSubtotal(float $harga, int $jumlah): float
{
    return $harga * $jumlah;
}

// Menghasilkan NOMINAL diskon, bukan persentase.
function hitungDiskon(float $subtotal): float
{
    if ($subtotal >= BATAS_MINIMAL_DISKON) {
        return $subtotal * PERSENTASE_DISKON;
    }

    return 0;
}

function hitungTotal(float $subtotal, float $diskon): float
{
    return max(0, $subtotal - $diskon);
}

function stokMencukupi(int $stok, int $jumlah): bool
{
    return $jumlah > 0 && $jumlah <= $stok;
}

function statusStok($stok): string
{
    $stok = (int)$stok;

    if ($stok <= 0) {
        return 'habis';
    }

    if ($stok <= BATAS_STOK_RENDAH) {
        return 'menipis';
    }

    return 'tersedia';
}

function labelStatusStok($stok): string
{
    $status = statusStok($stok);

    if ($status === 'habis') {
        return 'Stok Habis';
    }

    if ($status === 'menipis') {
        return 'Stok Menipis';
    }

    return 'Stok Tersedia';
}