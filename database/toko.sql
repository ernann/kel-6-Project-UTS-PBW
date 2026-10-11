
CREATE DATABASE IF NOT EXISTS toko_kelompok
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE toko_kelompok;

-- Tabel produk sepatu
CREATE TABLE IF NOT EXISTS produk (
    id_produk INT AUTO_INCREMENT PRIMARY KEY,
    kode_produk VARCHAR(20) NOT NULL UNIQUE,
    nama_produk VARCHAR(100) NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    harga DECIMAL(12,2) NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    gambar VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabel transaksi penjualan
CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    diskon DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_bayar DECIMAL(12,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Tabel detail transaksi
CREATE TABLE IF NOT EXISTS detail_transaksi (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_produk INT NOT NULL,
    jumlah INT NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,

    CONSTRAINT fk_detail_transaksi
        FOREIGN KEY (id_transaksi)
        REFERENCES transaksi(id_transaksi)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_detail_produk
        FOREIGN KEY (id_produk)
        REFERENCES produk(id_produk)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Data awal katalog STEPORA
INSERT IGNORE INTO produk
(kode_produk, nama_produk, kategori, harga, stok, gambar)
VALUES
('STP001', 'Everyday Sneakers', 'Casual', 299000, 18, 'sneakers-casual.jpg'),
('STP002', 'RunFlex Runner', 'Running', 399000, 12, 'running-shoes.jpg'),
('STP003', 'Street High', 'High-top', 449000, 7, 'high-top.jpg'),
('STP004', 'Court Classic', 'Court', 329000, 4, 'sneakers-court.jpg'),
('STP005', 'Easy Slip-On', 'Slip-on', 249000, 10, 'slip-on.jpg');
