<?php

session_start();
include 'koneksi.php';

if (!isset($_SESSION['AdminID']) || $_SESSION['Level'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

function rupiah($angka)
{
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

function tanggalIndonesia($tanggal)
{
    return date('d-m-Y H:i', strtotime($tanggal));
}

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date('m');
}

if ($tahun < 2020 || $tahun > 2100) {
    $tahun = (int)date('Y');
}

$awalBulan = sprintf('%04d-%02d-01 00:00:00', $tahun, $bulan);
$akhirBulan = date('Y-m-t 23:59:59', strtotime($awalBulan));

$namaBulan = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];


/* =========================
   RINGKASAN
========================= */

$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT p.PenjualanID) AS TotalTransaksi,
        COALESCE(SUM(p.TotalHarga), 0) AS TotalPendapatan
    FROM penjualan p
    INNER JOIN transaksi t
        ON p.TransaksiID = t.TransaksiID
    WHERE t.TanggalTransaksi BETWEEN ? AND ?
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$ringkasan = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalTransaksi = (int)$ringkasan['TotalTransaksi'];
$totalPendapatan = (float)$ringkasan['TotalPendapatan'];


/* =========================
   TOTAL PRODUK TERJUAL
========================= */

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(dp.JumlahProduk), 0) AS TotalProduk
    FROM detailpenjualan dp
    INNER JOIN penjualan p
        ON dp.PenjualanID = p.PenjualanID
    INNER JOIN transaksi t
        ON p.TransaksiID = t.TransaksiID
    WHERE t.TanggalTransaksi BETWEEN ? AND ?
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$totalProduk = (int)$stmt->get_result()->fetch_assoc()['TotalProduk'];
$stmt->close();


/* =========================
   TOTAL PELANGGAN TRANSAKSI
========================= */

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT p.PelangganID) AS TotalPelanggan
    FROM penjualan p
    INNER JOIN transaksi t
        ON p.TransaksiID = t.TransaksiID
    WHERE t.TanggalTransaksi BETWEEN ? AND ?
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$totalPelanggan = (int)$stmt->get_result()->fetch_assoc()['TotalPelanggan'];
$stmt->close();


/* =========================
   TOTAL MEMBER
========================= */

$resultMember = $conn->query("
    SELECT
        SUM(CASE WHEN StatusMember = 'Member' THEN 1 ELSE 0 END) AS Member,
        SUM(CASE WHEN StatusMember = 'Non-Member' THEN 1 ELSE 0 END) AS NonMember
    FROM pelanggan
");

$memberData = $resultMember->fetch_assoc();

$totalMember = (int)$memberData['Member'];
$totalNonMember = (int)$memberData['NonMember'];


/* =========================
   TRANSAKSI DETAIL
========================= */

$stmt = $conn->prepare("
    SELECT
        p.PenjualanID,
        t.TanggalTransaksi,
        pl.NamaPelanggan,
        pl.StatusMember,
        a.NamaAdmin,
        t.MetodePembayaran,
        t.StatusTransaksi,
        p.TotalHarga
    FROM penjualan p
    INNER JOIN transaksi t
        ON p.TransaksiID = t.TransaksiID
    LEFT JOIN pelanggan pl
        ON p.PelangganID = pl.PelangganID
    LEFT JOIN admin a
        ON t.AdminID = a.AdminID
    WHERE t.TanggalTransaksi BETWEEN ? AND ?
    ORDER BY t.TanggalTransaksi DESC
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$transaksiResult = $stmt->get_result();


/* =========================
   PRODUK TERJUAL
========================= */

$stmt = $conn->prepare("
    SELECT
        pr.ProdukID,
        pr.NamaProduk,
        pr.Harga,
        COALESCE(SUM(dp.JumlahProduk), 0) AS JumlahTerjual,
        COALESCE(SUM(dp.Subtotal), 0) AS TotalPenjualan
    FROM produk pr
    LEFT JOIN detailpenjualan dp
        ON pr.ProdukID = dp.ProdukID
    LEFT JOIN penjualan p
        ON dp.PenjualanID = p.PenjualanID
    LEFT JOIN transaksi t
        ON p.TransaksiID = t.TransaksiID
        AND t.TanggalTransaksi BETWEEN ? AND ?
    GROUP BY
        pr.ProdukID,
        pr.NamaProduk,
        pr.Harga
    ORDER BY JumlahTerjual DESC, pr.NamaProduk ASC
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$produkTerjualResult = $stmt->get_result();


/* =========================
   PENJUALAN HARIAN
========================= */

$stmt = $conn->prepare("
    SELECT
        DATE(t.TanggalTransaksi) AS Tanggal,
        COUNT(DISTINCT p.PenjualanID) AS JumlahTransaksi,
        COALESCE(SUM(dp.JumlahProduk), 0) AS JumlahProduk,
        COALESCE(SUM(p.TotalHarga), 0) AS Pendapatan
    FROM transaksi t
    INNER JOIN penjualan p
        ON t.TransaksiID = p.TransaksiID
    LEFT JOIN detailpenjualan dp
        ON p.PenjualanID = dp.PenjualanID
    WHERE t.TanggalTransaksi BETWEEN ? AND ?
    GROUP BY DATE(t.TanggalTransaksi)
    ORDER BY Tanggal DESC
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$harianResult = $stmt->get_result();


/* =========================
   METODE PEMBAYARAN
========================= */

$stmt = $conn->prepare("
    SELECT
        t.MetodePembayaran,
        COUNT(DISTINCT p.PenjualanID) AS JumlahTransaksi,
        COALESCE(SUM(p.TotalHarga), 0) AS Total
    FROM transaksi t
    INNER JOIN penjualan p
        ON t.TransaksiID = p.TransaksiID
    WHERE t.TanggalTransaksi BETWEEN ? AND ?
    GROUP BY t.MetodePembayaran
    ORDER BY JumlahTransaksi DESC
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$pembayaranResult = $stmt->get_result();


/* =========================
   STATUS TRANSAKSI
========================= */

$stmt = $conn->prepare("
    SELECT
        t.StatusTransaksi,
        COUNT(DISTINCT p.PenjualanID) AS JumlahTransaksi,
        COALESCE(SUM(p.TotalHarga), 0) AS Total
    FROM transaksi t
    INNER JOIN penjualan p
        ON t.TransaksiID = p.TransaksiID
    WHERE t.TanggalTransaksi BETWEEN ? AND ?
    GROUP BY t.StatusTransaksi
    ORDER BY JumlahTransaksi DESC
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$statusResult = $stmt->get_result();


/* =========================
   MEMBER VS NON MEMBER
========================= */

$stmt = $conn->prepare("
    SELECT
        COALESCE(pl.StatusMember, 'Non-Member') AS StatusMember,
        COUNT(DISTINCT p.PenjualanID) AS JumlahTransaksi,
        COALESCE(SUM(p.TotalHarga), 0) AS Total
    FROM penjualan p
    INNER JOIN transaksi t
        ON p.TransaksiID = t.TransaksiID
    LEFT JOIN pelanggan pl
        ON p.PelangganID = pl.PelangganID
    WHERE t.TanggalTransaksi BETWEEN ? AND ?
    GROUP BY COALESCE(pl.StatusMember, 'Non-Member')
    ORDER BY JumlahTransaksi DESC
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$memberResult = $stmt->get_result();


/* =========================
   ADMIN / KASIR
========================= */

$stmt = $conn->prepare("
    SELECT
        COALESCE(a.NamaAdmin, 'Tidak diketahui') AS NamaAdmin,
        COUNT(DISTINCT p.PenjualanID) AS JumlahTransaksi,
        COALESCE(SUM(p.TotalHarga), 0) AS Total
    FROM transaksi t
    INNER JOIN penjualan p
        ON t.TransaksiID = p.TransaksiID
    LEFT JOIN admin a
        ON t.AdminID = a.AdminID
    WHERE t.TanggalTransaksi BETWEEN ? AND ?
    GROUP BY t.AdminID, a.NamaAdmin
    ORDER BY JumlahTransaksi DESC
");

$stmt->bind_param("ss", $awalBulan, $akhirBulan);
$stmt->execute();

$adminResult = $stmt->get_result();


/* =========================
   SEMUA PRODUK + STOK
========================= */

$stokResult = $conn->query("
    SELECT
        ProdukID,
        NamaProduk,
        Harga,
        Stok
    FROM produk
    ORDER BY NamaProduk ASC
");

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Laporan Bulanan - Jessie Bites</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Poppins', sans-serif;
    background: #fff7fb;
    color: #4a3038;
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

.content {
    margin-left: 245px;
    padding: 35px;
}

.top-title {
    margin-bottom: 22px;
}

.top-title h1 {
    font-size: 28px;
    color: #56352b;
}

.top-title p {
    color: #9b7c82;
    font-size: 14px;
    margin-top: 5px;
}

.filter {
    background: white;
    padding: 20px;
    border-radius: 18px;
    border: 1px solid #f5e2ea;
    box-shadow: 0 8px 25px rgba(91, 53, 45, 0.06);
    display: flex;
    align-items: end;
    gap: 15px;
    margin-bottom: 25px;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #68473e;
}

.filter select {
    min-width: 160px;
    padding: 11px 13px;
    border: 1px solid #ead4dd;
    border-radius: 10px;
    font-family: 'Poppins', sans-serif;
    outline: none;
}

.filter button {
    padding: 11px 20px;
    border: none;
    border-radius: 10px;
    background: #ff72b4;
    color: white;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    cursor: pointer;
}

.filter button:hover {
    background: #ed5da2;
}

.periode {
    margin-left: auto;
    color: #8d6e76;
    font-size: 13px;
}

.summary {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 17px;
    margin-bottom: 25px;
}

.summary-card {
    background: white;
    border-radius: 18px;
    padding: 20px;
    border: 1px solid #f5e2ea;
    box-shadow: 0 8px 25px rgba(91, 53, 45, 0.06);
}

.summary-icon {
    font-size: 25px;
    margin-bottom: 10px;
}

.summary-label {
    font-size: 12px;
    color: #9b7c82;
    margin-bottom: 5px;
}

.summary-value {
    font-size: 20px;
    font-weight: 700;
    color: #56352b;
}

.section {
    background: white;
    border-radius: 20px;
    padding: 23px;
    border: 1px solid #f5e2ea;
    box-shadow: 0 8px 25px rgba(91, 53, 45, 0.06);
    margin-bottom: 25px;
}

.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.section-title h2 {
    font-size: 18px;
    color: #56352b;
}

.section-title span {
    font-size: 12px;
    color: #a1848b;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th {
    background: #fff0f6;
    color: #69434f;
    padding: 12px;
    text-align: left;
    font-size: 12px;
    white-space: nowrap;
}

td {
    padding: 12px;
    border-bottom: 1px solid #f3e6eb;
    font-size: 12px;
    color: #5e484f;
}

tr:hover td {
    background: #fffafd;
}

.total {
    font-weight: 700;
    color: #ff4d9d;
}

.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 600;
}

.member {
    background: #e8f7ee;
    color: #287044;
}

.non-member {
    background: #f1edf0;
    color: #74636a;
}

.selesai {
    background: #e7f8ed;
    color: #27733f;
}

.diproses {
    background: #fff2d6;
    color: #9a671d;
}

.grid-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.empty {
    text-align: center;
    padding: 25px;
    color: #a58d94;
    font-size: 13px;
}

.stock-low {
    color: #c23a50;
    font-weight: 700;
}

.stock-ok {
    color: #287044;
    font-weight: 600;
}

@media (max-width: 1200px) {

    .summary {
        grid-template-columns: repeat(3, 1fr);
    }

    .grid-two {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {

    .sidebar {
        width: 210px;
    }

    .content {
        margin-left: 210px;
        padding: 25px;
    }

    .summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .filter {
        flex-wrap: wrap;
    }

    .periode {
        width: 100%;
        margin-left: 0;
    }
}

@media (max-width: 650px) {

    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .logout {
        position: static;
        margin-top: 15px;
    }

    .content {
        margin-left: 0;
        padding: 20px;
    }

    .summary {
        grid-template-columns: 1fr;
    }

    .filter {
        align-items: stretch;
        flex-direction: column;
    }

    .filter select,
    .filter button {
        width: 100%;
    }
}

</style>

</head>

<body>


<div class="sidebar">

    <div class="brand">

        <div class="brand-icon">
            🍫
        </div>

        <h2>Jessie Bites</h2>

        <p>Admin Panel</p>

    </div>


    <div class="menu">

        <a href="admin_dashboard.php">
            ⌂ <span>Dashboard</span>
        </a>

        <a href="admin_transaksi.php">
            ▣ <span>Data Transaksi</span>
        </a>

        <a href="laporan_bulanan.php" class="active">
            📊 <span>Laporan Bulanan</span>
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


<div class="content">

    <div class="top-title">

        <h1>📊 Laporan Bulanan</h1>

        <p>
            Laporan penjualan Jessie Bites berdasarkan bulan dan tahun.
        </p>

    </div>


    <form method="GET" class="filter">

        <div class="filter-group">

            <label>Bulan</label>

            <select name="bulan">

                <?php foreach ($namaBulan as $nomor => $nama): ?>

                    <option
                        value="<?= $nomor ?>"
                        <?= $bulan == $nomor ? 'selected' : '' ?>
                    >
                        <?= $nama ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="filter-group">

            <label>Tahun</label>

            <select name="tahun">

                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>

                    <option
                        value="<?= $y ?>"
                        <?= $tahun == $y ? 'selected' : '' ?>
                    >
                        <?= $y ?>
                    </option>

                <?php endfor; ?>

            </select>

        </div>


        <button type="submit">
            🔍 Tampilkan
        </button>


        <div class="periode">
            Periode:
            <strong>
                <?= $namaBulan[$bulan] . ' ' . $tahun ?>
            </strong>
        </div>

    </form>


    <div class="summary">

        <div class="summary-card">

            <div class="summary-icon">
                🧾
            </div>

            <div class="summary-label">
                Total Transaksi
            </div>

            <div class="summary-value">
                <?= number_format($totalTransaksi) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                💰
            </div>

            <div class="summary-label">
                Total Pendapatan
            </div>

            <div class="summary-value">
                <?= rupiah($totalPendapatan) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                🍫
            </div>

            <div class="summary-label">
                Produk Terjual
            </div>

            <div class="summary-value">
                <?= number_format($totalProduk) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                👥
            </div>

            <div class="summary-label">
                Pelanggan Bertransaksi
            </div>

            <div class="summary-value">
                <?= number_format($totalPelanggan) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                💗
            </div>

            <div class="summary-label">
                Total Member
            </div>

            <div class="summary-value">
                <?= number_format($totalMember) ?>
            </div>

        </div>

    </div>


    <div class="section">

        <div class="section-title">

            <h2>🧾 Semua Transaksi</h2>

            <span>
                <?= number_format($totalTransaksi) ?> transaksi
            </span>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>No</th>
                        <th>ID Penjualan</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Member</th>
                        <th>Admin/Kasir</th>
                        <th>Pembayaran</th>
                        <th>Status</th>
                        <th>Total</th>

                    </tr>

                </thead>

                <tbody>

                <?php if ($transaksiResult->num_rows > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($row = $transaksiResult->fetch_assoc()): ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td>
                                #<?= (int)$row['PenjualanID'] ?>
                            </td>

                            <td>
                                <?= tanggalIndonesia($row['TanggalTransaksi']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['NamaPelanggan'] ?? 'Umum') ?>
                            </td>

                            <td>

                                <?php if (($row['StatusMember'] ?? '') === 'Member'): ?>

                                    <span class="badge member">
                                        Member
                                    </span>

                                <?php else: ?>

                                    <span class="badge non-member">
                                        Non-Member
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= htmlspecialchars($row['NamaAdmin'] ?? 'Tidak diketahui') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['MetodePembayaran']) ?>
                            </td>

                            <td>

                                <?php if ($row['StatusTransaksi'] === 'Selesai'): ?>

                                    <span class="badge selesai">
                                        Selesai
                                    </span>

                                <?php else: ?>

                                    <span class="badge diproses">
                                        <?= htmlspecialchars($row['StatusTransaksi']) ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td class="total">
                                <?= rupiah($row['TotalHarga']) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="empty">
                            Tidak ada transaksi pada periode ini.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <div class="grid-two">


        <div class="section">

            <div class="section-title">

                <h2>🍫 Produk Terjual</h2>

                <span>Semua produk</span>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>Produk</th>
                            <th>Harga</th>
                            <th>Terjual</th>
                            <th>Total</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php $no = 1; ?>

                    <?php while ($row = $produkTerjualResult->fetch_assoc()): ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($row['NamaProduk']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= rupiah($row['Harga']) ?>
                            </td>

                            <td>
                                <?= number_format($row['JumlahTerjual']) ?>
                            </td>

                            <td class="total">
                                <?= rupiah($row['TotalPenjualan']) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <div class="section">

            <div class="section-title">

                <h2>📅 Penjualan Harian</h2>

                <span><?= $namaBulan[$bulan] ?></span>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Tanggal</th>
                            <th>Transaksi</th>
                            <th>Produk</th>
                            <th>Pendapatan</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($harianResult->num_rows > 0): ?>

                        <?php while ($row = $harianResult->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= date('d-m-Y', strtotime($row['Tanggal'])) ?>
                                </td>

                                <td>
                                    <?= number_format($row['JumlahTransaksi']) ?>
                                </td>

                                <td>
                                    <?= number_format($row['JumlahProduk']) ?>
                                </td>

                                <td class="total">
                                    <?= rupiah($row['Pendapatan']) ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4" class="empty">
                                Belum ada penjualan.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <div class="grid-two">


        <div class="section">

            <div class="section-title">

                <h2>💳 Metode Pembayaran</h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Metode</th>
                            <th>Transaksi</th>
                            <th>Total</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($pembayaranResult->num_rows > 0): ?>

                        <?php while ($row = $pembayaranResult->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($row['MetodePembayaran']) ?>
                                </td>

                                <td>
                                    <?= number_format($row['JumlahTransaksi']) ?>
                                </td>

                                <td class="total">
                                    <?= rupiah($row['Total']) ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="empty">
                                Belum ada data.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <div class="section">

            <div class="section-title">

                <h2>📌 Status Transaksi</h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Status</th>
                            <th>Transaksi</th>
                            <th>Total</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($statusResult->num_rows > 0): ?>

                        <?php while ($row = $statusResult->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($row['StatusTransaksi']) ?>
                                </td>

                                <td>
                                    <?= number_format($row['JumlahTransaksi']) ?>
                                </td>

                                <td class="total">
                                    <?= rupiah($row['Total']) ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="empty">
                                Belum ada data.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <div class="grid-two">


        <div class="section">

            <div class="section-title">

                <h2>👥 Member & Non-Member</h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Status</th>
                            <th>Transaksi</th>
                            <th>Total</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($memberResult->num_rows > 0): ?>

                        <?php while ($row = $memberResult->fetch_assoc()): ?>

                            <tr>

                                <td>

                                    <?php if ($row['StatusMember'] === 'Member'): ?>

                                        <span class="badge member">
                                            Member
                                        </span>

                                    <?php else: ?>

                                        <span class="badge non-member">
                                            Non-Member
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= number_format($row['JumlahTransaksi']) ?>
                                </td>

                                <td class="total">
                                    <?= rupiah($row['Total']) ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="empty">
                                Belum ada transaksi pelanggan.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <div class="section">

            <div class="section-title">

                <h2>👤 Admin / Kasir</h2>

            </div>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Nama</th>
                            <th>Transaksi</th>
                            <th>Total</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($adminResult->num_rows > 0): ?>

                        <?php while ($row = $adminResult->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($row['NamaAdmin']) ?>
                                </td>

                                <td>
                                    <?= number_format($row['JumlahTransaksi']) ?>
                                </td>

                                <td class="total">
                                    <?= rupiah($row['Total']) ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" class="empty">
                                Belum ada transaksi.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <div class="section">

        <div class="section-title">

            <h2>📦 Stok Produk Saat Ini</h2>

            <span>
                Data stok terbaru
            </span>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>No</th>
                        <th>ID Produk</th>
                        <th>Nama Produk</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Keterangan</th>

                    </tr>

                </thead>

                <tbody>

                <?php if ($stokResult && $stokResult->num_rows > 0): ?>

                    <?php $no = 1; ?>

                    <?php while ($row = $stokResult->fetch_assoc()): ?>

                        <tr>

                            <td><?= $no++ ?></td>

                            <td>
                                #<?= (int)$row['ProdukID'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($row['NamaProduk']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= rupiah($row['Harga']) ?>
                            </td>

                            <td>

                                <?php if ((int)$row['Stok'] <= 5): ?>

                                    <span class="stock-low">
                                        <?= (int)$row['Stok'] ?>
                                    </span>

                                <?php else: ?>

                                    <span class="stock-ok">
                                        <?= (int)$row['Stok'] ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ((int)$row['Stok'] <= 5): ?>

                                    <span class="badge diproses">
                                        Stok Menipis
                                    </span>

                                <?php else: ?>

                                    <span class="badge selesai">
                                        Stok Aman
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            Belum ada produk.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


</div>

</body>
</html>
