<?php

include 'koneksi.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("Data transaksi tidak ditemukan.");
}

$query = $conn->prepare("
    SELECT
        p.PenjualanID,
        p.TanggalPenjualan,
        p.TotalHarga,
        pl.NamaPelanggan,
        pl.NomorTelepon,
        t.TransaksiID,
        t.MetodePembayaran,
        t.StatusTransaksi,
        a.NamaAdmin
    FROM penjualan p
    LEFT JOIN pelanggan pl ON p.PelangganID = pl.PelangganID
    LEFT JOIN transaksi t ON p.TransaksiID = t.TransaksiID
    LEFT JOIN admin a ON p.AdminID = a.AdminID
    WHERE p.PenjualanID = ?
");

$query->bind_param("i", $id);
$query->execute();

$hasil = $query->get_result();

if ($hasil->num_rows === 0) {
    die("Transaksi tidak ditemukan.");
}

$data = $hasil->fetch_assoc();

$stmtDetail = $conn->prepare("
    SELECT
        dp.JumlahProduk,
        dp.Subtotal,
        pr.NamaProduk,
        pr.Harga
    FROM detailpenjualan dp
    INNER JOIN produk pr ON dp.ProdukID = pr.ProdukID
    WHERE dp.PenjualanID = ?
    ORDER BY dp.DetailID ASC
");

$stmtDetail->bind_param("i", $id);
$stmtDetail->execute();

$detail = $stmtDetail->get_result();

function rupiah($angka)
{
    return "Rp " . number_format($angka, 0, ',', '.');
}

function tanggalIndonesia($tanggal)
{
    return date("d/m/Y H:i", strtotime($tanggal));
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #<?= $data['PenjualanID']; ?> - Jessie Bites</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: linear-gradient(135deg, #fff0f6, #ffe1ed);
            font-family: Arial, Helvetica, sans-serif;
            color: #4d3540;
        }

        .wrapper {
            width: 100%;
            max-width: 480px;
            margin: auto;
        }

        .receipt {
            background: white;
            border-radius: 25px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(120, 50, 80, 0.15);
            position: relative;
        }

        .top {
            text-align: center;
            border-bottom: 2px dashed #e8a7bf;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .logo {
            width: 75px;
            height: 75px;
            margin: auto;
            border-radius: 50%;
            background: linear-gradient(135deg, #7b3f25, #b96a43);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            box-shadow: 0 8px 20px rgba(120, 60, 30, 0.2);
        }

        .brand {
            margin-top: 12px;
            font-size: 28px;
            font-weight: bold;
            color: #7b3f25;
        }

        .tagline {
            margin-top: 5px;
            font-size: 13px;
            color: #a67c89;
        }

        .info {
            background: #fff6f9;
            padding: 15px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 5px 0;
            font-size: 13px;
        }

        .info-row span:first-child {
            color: #9b7a86;
        }

        .info-row span:last-child {
            font-weight: bold;
            text-align: right;
        }

        .items {
            margin-bottom: 15px;
        }

        .item {
            padding: 14px 0;
            border-bottom: 1px dashed #ead4dc;
        }

        .item:last-child {
            border-bottom: none;
        }

        .item-name {
            font-weight: bold;
            color: #633e2f;
            margin-bottom: 7px;
        }

        .item-detail {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #917580;
        }

        .total-box {
            border-top: 2px dashed #e8a7bf;
            margin-top: 10px;
            padding-top: 18px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 16px;
            font-weight: bold;
            color: #6d4352;
        }

        .total-price {
            font-size: 25px;
            font-weight: bold;
            color: #b24d78;
        }

        .payment {
            margin-top: 15px;
            text-align: center;
            background: #fdf0f5;
            padding: 12px;
            border-radius: 12px;
            font-size: 13px;
        }

        .thankyou {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px dashed #e8a7bf;
        }

        .thankyou h3 {
            margin: 0 0 7px;
            color: #7b3f25;
        }

        .thankyou p {
            margin: 0;
            color: #a67c89;
            font-size: 13px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .buttons a,
        .buttons button {
            flex: 1;
            border: none;
            padding: 13px;
            border-radius: 13px;
            text-decoration: none;
            text-align: center;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-print {
            background: #b24d78;
            color: white;
        }

        .btn-print:hover {
            background: #963d63;
        }

        .btn-cashier {
            background: #f3d7e2;
            color: #74475a;
        }

        .btn-cashier:hover {
            background: #e9c2d1;
        }

        .admin-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #9b4f72;
            text-decoration: none;
            font-size: 13px;
        }

        @media print {

            body {
                background: white;
                padding: 0;
            }

            .receipt {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }

            .buttons,
            .admin-link {
                display: none;
            }
        }
    </style>
</head>

<body>

<div class="wrapper">

    <div class="receipt">

        <div class="top">

            <div class="logo">
                🍫
            </div>

            <div class="brand">
                Jessie Bites
            </div>

            <div class="tagline">
                Brownies kecil, rasa bikin nagih ♡
            </div>

        </div>

        <div class="info">

            <div class="info-row">
                <span>No. Transaksi</span>
                <span>#<?= str_pad($data['PenjualanID'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>

            <div class="info-row">
                <span>Tanggal</span>
                <span><?= tanggalIndonesia($data['TanggalPenjualan']); ?></span>
            </div>

            <div class="info-row">
                <span>Pelanggan</span>
                <span><?= htmlspecialchars($data['NamaPelanggan'] ?? '-'); ?></span>
            </div>

            <div class="info-row">
                <span>Petugas</span>
                <span><?= htmlspecialchars($data['NamaAdmin'] ?? '-'); ?></span>
            </div>

        </div>

        <div class="items">

            <?php while ($item = $detail->fetch_assoc()): ?>

                <div class="item">

                    <div class="item-name">
                        <?= htmlspecialchars($item['NamaProduk']); ?>
                    </div>

                    <div class="item-detail">

                        <span>
                            <?= $item['JumlahProduk']; ?> ×
                            <?= rupiah($item['Harga']); ?>
                        </span>

                        <strong>
                            <?= rupiah($item['Subtotal']); ?>
                        </strong>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

        <div class="total-box">

            <div class="total-row">

                <div class="total-label">
                    TOTAL
                </div>

                <div class="total-price">
                    <?= rupiah($data['TotalHarga']); ?>
                </div>

            </div>

        </div>

        <div class="payment">

            💳 Pembayaran:
            <strong><?= htmlspecialchars($data['MetodePembayaran']); ?></strong>

            <br>

            📌 Status:
            <strong><?= htmlspecialchars($data['StatusTransaksi']); ?></strong>

        </div>

        <div class="thankyou">

            <h3>Terima Kasih! 🍫💕</h3>

            <p>
                Terima kasih sudah membeli<br>
                brownies dari Jessie Bites.
            </p>

        </div>

    </div>

    <div class="buttons">

        <button class="btn-print" onclick="window.print()">
            🖨️ Cetak Struk
        </button>

        <a href="index.php" class="btn-cashier">
            🛒 Kasir Lagi
        </a>

    </div>

    <a href="admin_login.php" class="admin-link">
        🔐 Login Admin
    </a>

</div>

</body>
</html>