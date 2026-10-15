```php
<?php

include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$PelangganID = (int)($_POST['PelangganID'] ?? 0);
$MetodePembayaran = $_POST['MetodePembayaran'] ?? '';
$StatusTransaksi = $_POST['StatusTransaksi'] ?? 'Selesai';

$ProdukID = $_POST['ProdukID'] ?? [];
$JumlahProduk = $_POST['JumlahProduk'] ?? [];

if ($PelangganID <= 0) {
    die("Pelanggan belum dipilih.");
}

if (empty($ProdukID) || empty($JumlahProduk)) {
    die("Produk belum dipilih.");
}

if ($MetodePembayaran === '') {
    die("Metode pembayaran belum dipilih.");
}

$cekPelanggan = $conn->prepare("
    SELECT PelangganID
    FROM pelanggan
    WHERE PelangganID = ?
");

$cekPelanggan->bind_param("i", $PelangganID);
$cekPelanggan->execute();

$hasilPelanggan = $cekPelanggan->get_result();

if ($hasilPelanggan->num_rows === 0) {
    die("Data pelanggan tidak ditemukan.");
}

$cekKasir = $conn->query("
    SELECT AdminID
    FROM admin
    WHERE Level = 'Kasir'
    ORDER BY AdminID ASC
    LIMIT 1
");

if (!$cekKasir || $cekKasir->num_rows === 0) {
    die("Akun Kasir belum tersedia di database.");
}

$dataKasir = $cekKasir->fetch_assoc();
$AdminID = (int)$dataKasir['AdminID'];

$conn->begin_transaction();

try {

    $stmtTransaksi = $conn->prepare("
        INSERT INTO transaksi
        (TanggalTransaksi, PelangganID, AdminID, MetodePembayaran, StatusTransaksi)
        VALUES (NOW(), ?, ?, ?, ?)
    ");

    if (!$stmtTransaksi) {
        throw new Exception("Gagal menyiapkan data transaksi.");
    }

    $stmtTransaksi->bind_param(
        "iiss",
        $PelangganID,
        $AdminID,
        $MetodePembayaran,
        $StatusTransaksi
    );

    if (!$stmtTransaksi->execute()) {
        throw new Exception("Gagal menyimpan transaksi.");
    }

    $TransaksiID = $conn->insert_id;

    $stmtPenjualan = $conn->prepare("
        INSERT INTO penjualan
        (TanggalPenjualan, TotalHarga, PelangganID, TransaksiID, AdminID)
        VALUES (NOW(), 0, ?, ?, ?)
    ");

    if (!$stmtPenjualan) {
        throw new Exception("Gagal menyiapkan data penjualan.");
    }

    $stmtPenjualan->bind_param(
        "iii",
        $PelangganID,
        $TransaksiID,
        $AdminID
    );

    if (!$stmtPenjualan->execute()) {
        throw new Exception("Gagal membuat data penjualan.");
    }

    $PenjualanID = $conn->insert_id;

    $stmtProduk = $conn->prepare("
        SELECT ProdukID, NamaProduk, Harga, Stok
        FROM produk
        WHERE ProdukID = ?
        FOR UPDATE
    ");

    if (!$stmtProduk) {
        throw new Exception("Gagal mengambil data produk.");
    }

    $stmtDetail = $conn->prepare("
        INSERT INTO detailpenjualan
        (PenjualanID, ProdukID, JumlahProduk, Subtotal)
        VALUES (?, ?, ?, ?)
    ");

    if (!$stmtDetail) {
        throw new Exception("Gagal menyiapkan detail penjualan.");
    }

    $stmtStok = $conn->prepare("
        UPDATE produk
        SET Stok = Stok - ?
        WHERE ProdukID = ?
    ");

    if (!$stmtStok) {
        throw new Exception("Gagal menyiapkan pengurangan stok.");
    }

    $TotalHarga = 0;
    $adaProduk = false;

    foreach ($ProdukID as $i => $idProduk) {

        $idProduk = (int)$idProduk;
        $jumlah = isset($JumlahProduk[$i])
            ? (int)$JumlahProduk[$i]
            : 0;

        if ($idProduk <= 0 || $jumlah <= 0) {
            continue;
        }

        $stmtProduk->bind_param("i", $idProduk);
        $stmtProduk->execute();

        $hasilProduk = $stmtProduk->get_result();

        if ($hasilProduk->num_rows === 0) {
            throw new Exception("Produk tidak ditemukan.");
        }

        $produk = $hasilProduk->fetch_assoc();

        $stok = (int)$produk['Stok'];
        $harga = (float)$produk['Harga'];

        if ($stok <= 0) {
            throw new Exception(
                "Stok {$produk['NamaProduk']} sudah habis."
            );
        }

        if ($jumlah > $stok) {
            throw new Exception(
                "Stok {$produk['NamaProduk']} tidak mencukupi. Stok tersedia: {$stok}."
            );
        }

        $subtotal = $harga * $jumlah;

        $stmtDetail->bind_param(
            "iiid",
            $PenjualanID,
            $idProduk,
            $jumlah,
            $subtotal
        );

        if (!$stmtDetail->execute()) {
            throw new Exception(
                "Gagal menyimpan detail produk {$produk['NamaProduk']}."
            );
        }

        $stmtStok->bind_param(
            "ii",
            $jumlah,
            $idProduk
        );

        if (!$stmtStok->execute()) {
            throw new Exception(
                "Gagal mengurangi stok {$produk['NamaProduk']}."
            );
        }

        $TotalHarga += $subtotal;
        $adaProduk = true;
    }

    if (!$adaProduk) {
        throw new Exception("Minimal pilih satu produk.");
    }

    if ($TotalHarga <= 0) {
        throw new Exception("Total transaksi tidak valid.");
    }

    $updatePenjualan = $conn->prepare("
        UPDATE penjualan
        SET TotalHarga = ?
        WHERE PenjualanID = ?
    ");

    if (!$updatePenjualan) {
        throw new Exception("Gagal menyiapkan total penjualan.");
    }

    $updatePenjualan->bind_param(
        "di",
        $TotalHarga,
        $PenjualanID
    );

    if (!$updatePenjualan->execute()) {
        throw new Exception("Gagal memperbarui total penjualan.");
    }

    $conn->commit();

    header("Location: struk.php?id=" . $PenjualanID);
    exit;

} catch (Exception $e) {

    $conn->rollback();

    echo "<!DOCTYPE html>";
    echo "<html lang='id'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>Transaksi Gagal</title>";

    echo "<style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #fff0f6, #ffe0ec);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .box {
            background: white;
            width: 100%;
            max-width: 500px;
            padding: 35px;
            border-radius: 25px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(150, 60, 100, 0.15);
        }

        .icon {
            font-size: 55px;
            margin-bottom: 15px;
        }

        h2 {
            color: #8d3b62;
            margin-bottom: 10px;
        }

        p {
            color: #666;
            line-height: 1.6;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 22px;
            background: #d85b91;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: bold;
        }

        a:hover {
            background: #bd4679;
        }

    </style>";

    echo "</head>";

    echo "<body>";

    echo "<div class='box'>";

    echo "<div class='icon'>🍫</div>";

    echo "<h2>Transaksi Belum Berhasil</h2>";

    echo "<p>" .
        htmlspecialchars($e->getMessage()) .
        "</p>";

    echo "<a href='index.php'>← Kembali ke Kasir</a>";

    echo "</div>";

    echo "</body>";
    echo "</html>";
}

?>
```