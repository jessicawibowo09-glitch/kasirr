<?php

session_start();
include 'koneksi.php';

if (!isset($_SESSION['AdminID']) || $_SESSION['Level'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("Data transaksi tidak ditemukan.");
}

$stmt = $conn->prepare("
    SELECT
        p.PenjualanID,
        p.TanggalPenjualan,
        p.TotalHarga,
        p.PelangganID,
        pl.NamaPelanggan,
        t.TransaksiID,
        t.MetodePembayaran,
        t.StatusTransaksi,
        a.NamaAdmin
    FROM penjualan p
    LEFT JOIN pelanggan pl
        ON p.PelangganID = pl.PelangganID
    LEFT JOIN transaksi t
        ON t.TransaksiID = (
            SELECT t2.TransaksiID
            FROM transaksi t2
            WHERE t2.PelangganID = p.PelangganID
              AND ABS(
                  TIMESTAMPDIFF(
                      SECOND,
                      p.TanggalPenjualan,
                      t2.TanggalTransaksi
                  )
              ) <= 5
            ORDER BY
                ABS(
                    TIMESTAMPDIFF(
                        SECOND,
                        p.TanggalPenjualan,
                        t2.TanggalTransaksi
                    )
                ),
                t2.TransaksiID DESC
            LIMIT 1
        )
    LEFT JOIN admin a
        ON t.AdminID = a.AdminID
    WHERE p.PenjualanID = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$transaksi = $result->fetch_assoc();

if (!$transaksi) {
    die("Data transaksi tidak ditemukan.");
}

$stmtDetail = $conn->prepare("
    SELECT
        d.DetailID,
        d.ProdukID,
        d.JumlahProduk,
        d.Subtotal,
        pr.NamaProduk,
        pr.Harga
    FROM detailpenjualan d
    LEFT JOIN produk pr
        ON d.ProdukID = pr.ProdukID
    WHERE d.PenjualanID = ?
    ORDER BY d.DetailID ASC
");

$stmtDetail->bind_param("i", $id);
$stmtDetail->execute();

$detail = $stmtDetail->get_result();

function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function tanggalIndonesia($tanggal)
{
    return date('d/m/Y H:i', strtotime($tanggal));
}

$status = $transaksi['StatusTransaksi'] ?? '-';
$metode = $transaksi['MetodePembayaran'] ?? '-';
$admin = $transaksi['NamaAdmin'] ?? '-';
$namaPelanggan = $transaksi['NamaPelanggan'] ?? 'Umum';

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi - Jessie Bites</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #fff7fa;
            color: #4b2c22;
        }

        .sidebar {
            width: 245px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #6b4030, #4b2c22);
            padding: 25px 16px;
            box-shadow: 4px 0 15px rgba(90, 50, 40, 0.15);
        }

        .brand {
            text-align: center;
            color: white;
            margin-bottom: 35px;
        }

        .brand-icon {
            width: 58px;
            height: 58px;
            margin: auto;
            border-radius: 18px;
            background: #ff77b7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .brand h2 {
            font-size: 21px;
            margin-bottom: 3px;
        }

        .brand p {
            font-size: 12px;
            color: #f8dce9;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .menu a,
        .logout a {
            text-decoration: none;
            color: #f8e9e3;
            padding: 13px 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 13px;
            font-size: 14px;
            transition: 0.2s;
        }

        .menu a:hover,
        .menu a.active {
            background: #ff72b4;
            color: white;
        }

        .logout {
            position: absolute;
            bottom: 25px;
            left: 16px;
            right: 16px;
        }

        .logout a {
            background: rgba(255,255,255,0.08);
        }

        .logout a:hover {
            background: #ff72b4;
        }

        .main {
            margin-left: 245px;
            padding: 35px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .topbar h1 {
            font-size: 27px;
            color: #4b2c22;
        }

        .topbar p {
            color: #9b7770;
            font-size: 13px;
            margin-top: 4px;
        }

        .admin-info {
            background: white;
            padding: 10px 17px;
            border-radius: 14px;
            box-shadow: 0 5px 18px rgba(100, 60, 50, 0.08);
            font-size: 13px;
            color: #6b4030;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 7px 25px rgba(100, 60, 50, 0.08);
            margin-bottom: 22px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .card-header h2 {
            font-size: 19px;
            color: #4b2c22;
        }

        .badge {
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-selesai {
            background: #e5f8eb;
            color: #238443;
        }

        .badge-proses {
            background: #fff3cd;
            color: #946c00;
        }

        .badge-batal {
            background: #ffe1e1;
            color: #c62828;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .info-box {
            background: #fff8fb;
            border: 1px solid #f7e2eb;
            border-radius: 14px;
            padding: 16px;
        }

        .info-box span {
            display: block;
            color: #a0837b;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .info-box strong {
            color: #4b2c22;
            font-size: 14px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #fff0f6;
            color: #6b4030;
            font-size: 12px;
            padding: 14px;
            text-align: left;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #f4e7eb;
            font-size: 13px;
            color: #5d4741;
        }

        .product-name {
            font-weight: 600;
            color: #4b2c22;
        }

        .total-box {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .total {
            width: 300px;
            background: linear-gradient(135deg, #6b4030, #8c5541);
            color: white;
            padding: 20px;
            border-radius: 17px;
            text-align: right;
        }

        .total span {
            font-size: 12px;
            opacity: 0.85;
        }

        .total h2 {
            margin-top: 5px;
            font-size: 25px;
        }

        .button-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-back {
            background: #f2e7e3;
            color: #6b4030;
        }

        .btn-back:hover {
            background: #e8d7d1;
        }

        .btn-print {
            background: #ff72b4;
            color: white;
        }

        .btn-print:hover {
            background: #ed559d;
            transform: translateY(-1px);
        }

        @media (max-width: 900px) {

            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
                padding: 25px;
            }

            .info-grid {
                grid-template-columns: 1fr 1fr;
            }

        }

        @media (max-width: 650px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .logout {
                position: static;
                margin-top: 20px;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .topbar {
                display: block;
            }

            .admin-info {
                margin-top: 12px;
                display: inline-block;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .button-area {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                text-align: center;
            }

        }

    </style>
</head>

<body>

<div class="sidebar">

    <div class="brand">
        <div class="brand-icon">🍫</div>
        <h2>Jessie Bites</h2>
        <p>Admin Panel</p>
    </div>

    <div class="menu">
        <a href="admin_dashboard.php">
            ⌂ <span>Dashboard</span>
        </a>

        <a href="admin_transaksi.php" class="active">
            ▣ <span>Data Transaksi</span>
        </a>

        <a href="log_transaksi.php">
            ◷ <span>Riwayat Edit</span>
        </a>

        <a href="produk.php">
            ▤ <span>Data Produk</span>
        </a>

        <a href="pelanggan.php">
            ♙ <span>Data Pelanggan</span>
        </a>

        <a href="index.php">
            ▣ <span>Ke Halaman Kasir</span>
        </a>
    </div>

    <div class="logout">
        <a href="logout.php">
            ↪ <span>Logout</span>
        </a>
    </div>

</div>

<div class="main">

    <div class="topbar">

        <div>
            <h1>Detail Transaksi</h1>
            <p>Informasi lengkap transaksi Jessie Bites</p>
        </div>

        <div class="admin-info">
            👤 <?= htmlspecialchars($_SESSION['NamaAdmin'] ?? 'Admin') ?>
        </div>

    </div>

    <div class="card">

        <div class="card-header">

            <h2>Informasi Transaksi</h2>

            <?php if ($status === 'Selesai'): ?>

                <span class="badge badge-selesai">
                    Selesai
                </span>

            <?php elseif ($status === 'Dibatalkan'): ?>

                <span class="badge badge-batal">
                    Dibatalkan
                </span>

            <?php elseif ($status === 'Proses' || $status === 'Diproses'): ?>

                <span class="badge badge-proses">
                    <?= htmlspecialchars($status) ?>
                </span>

            <?php else: ?>

                <span class="badge badge-proses">
                    <?= htmlspecialchars($status) ?>
                </span>

            <?php endif; ?>

        </div>

        <div class="info-grid">

            <div class="info-box">
                <span>ID Transaksi</span>
                <strong>#<?= htmlspecialchars($transaksi['PenjualanID']) ?></strong>
            </div>

            <div class="info-box">
                <span>Tanggal Transaksi</span>
                <strong>
                    <?= tanggalIndonesia($transaksi['TanggalPenjualan']) ?>
                </strong>
            </div>

            <div class="info-box">
                <span>Pelanggan</span>
                <strong>
                    <?= htmlspecialchars($namaPelanggan) ?>
                </strong>
            </div>

            <div class="info-box">
                <span>Metode Pembayaran</span>
                <strong>
                    <?= htmlspecialchars($metode) ?>
                </strong>
            </div>

            <div class="info-box">
                <span>Petugas</span>
                <strong>
                    <?= htmlspecialchars($admin) ?>
                </strong>
            </div>

            <div class="info-box">
                <span>Status</span>
                <strong>
                    <?= htmlspecialchars($status) ?>
                </strong>
            </div>

        </div>

    </div>

    <div class="card">

        <div class="card-header">
            <h2>Detail Produk</h2>
        </div>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Produk</th>
                        <th>Harga</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>

                <tbody>

                <?php

                $no = 1;

                while ($row = $detail->fetch_assoc()):

                ?>

                    <tr>

                        <td><?= $no++ ?></td>

                        <td class="product-name">
                            <?= htmlspecialchars($row['NamaProduk'] ?? '-') ?>
                        </td>

                        <td>
                            <?= rupiah($row['Harga'] ?? 0) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['JumlahProduk']) ?>
                        </td>

                        <td>
                            <?= rupiah($row['Subtotal']) ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>

        <div class="total-box">

            <div class="total">

                <span>Total Pembayaran</span>

                <h2>
                    <?= rupiah($transaksi['TotalHarga']) ?>
                </h2>

            </div>

        </div>

        <div class="button-area">

            <a href="admin_transaksi.php" class="btn btn-back">
                ← Kembali
            </a>

            <a
                href="struk.php?id=<?= (int)$transaksi['PenjualanID'] ?>"
                class="btn btn-print"
            >
                🧾 Lihat / Cetak Struk
            </a>

        </div>

    </div>

</div>

</body>
</html>