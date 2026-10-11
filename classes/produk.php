```php
<?php

class Produk
{
    public string $nama;
    public float $harga;
    public int $stok;

    public function __construct(
        string $nama,
        float|int|string $harga,
        int|string $stok
    ) {
        $this->nama = $nama;
        $this->harga = (float) $harga;
        $this->stok = (int) $stok;
    }

    // Menghitung subtotal pembelian
    public function hitungSubtotal(int $jumlah): float
    {
        if ($jumlah <= 0) {
            return 0;
        }

        return $this->harga * $jumlah;
    }

    // Memeriksa apakah stok mencukupi
    public function cekStok(int $jumlah): bool
    {
        return $jumlah > 0 && $this->stok >= $jumlah;
    }

    // Mengambil status stok
    public function getStatusStok(): string
    {
        return statusStok($this->stok);
    }

    // Mengambil label status stok
    public function getStatusStokLabel(): string
    {
        return labelStatusStok($this->stok);
    }
}
```