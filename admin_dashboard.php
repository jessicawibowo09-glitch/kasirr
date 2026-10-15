<?php

session_start();
include 'koneksi.php';

if (!isset($_SESSION['AdminID']) || $_SESSION['Level'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

$namaAdmin = $_SESSION['NamaAdmin'];

$totalProduk = 0;
$totalPelanggan = 0;
$totalTransaksi = 0;
$totalPendapatan = 0;

$queryProduk = $conn->query("SELECT COUNT(*) AS total FROM produk");

if ($queryProduk) {
    $dataProduk = $queryProduk->fetch_assoc();
    $totalProduk = $dataProduk['total'];
}

$queryPelanggan = $conn->query("SELECT COUNT(*) AS total FROM pelanggan");

if ($queryPelanggan) {
    $dataPelanggan = $queryPelanggan->fetch_assoc();
    $totalPelanggan = $dataPelanggan['total'];
}

$queryTransaksi = $conn->query("SELECT COUNT(*) AS total FROM transaksi");

if ($queryTransaksi) {
    $dataTransaksi = $queryTransaksi->fetch_assoc();
    $totalTransaksi = $dataTransaksi['total'];
}

$queryPendapatan = $conn->query("SELECT SUM(TotalHarga) AS total FROM penjualan");

if ($queryPendapatan) {
    $dataPendapatan = $queryPendapatan->fetch_assoc();
    $totalPendapatan = $dataPendapatan['total'] ?? 0;
}

$queryTerbaru = $conn->query("
    SELECT 
        t.TransaksiID,
        t.TanggalTransaksi,
        p.NamaPelanggan,
        a.NamaAdmin,
        t.MetodePembayaran,
        t.StatusTransaksi
    FROM transaksi t
    LEFT JOIN pelanggan p ON t.PelangganID = p.PelangganID
    LEFT JOIN admin a ON t.AdminID = a.AdminID
    ORDER BY t.TransaksiID DESC
    LIMIT 5
");

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard Admin - Jessie Bites</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background: #fff7fb;
    color: #4b3027;
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

.top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.welcome h1 {
    color: #6b4030;
    font-size: 28px;
}

.welcome p {
    color: #92766b;
    margin-top: 7px;
    font-size: 14px;
}

.admin-box {
    background: white;
    padding: 12px 18px;
    border-radius: 15px;
    box-shadow: 0 5px 18px rgba(80,40,20,.08);
    font-size: 14px;
}

.admin-box span {
    color: #b86848;
    font-weight: bold;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 30px;
}

.card {
    background: white;
    border-radius: 20px;
    padding: 22px;
    box-shadow: 0 7px 20px rgba(80,40,20,.08);
    position: relative;
    overflow: hidden;
}

.card-icon {
    width: 45px;
    height: 45px;
    border-radius: 13px;
    background: #fff0f6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
    margin-bottom: 15px;
}

.card h3 {
    font-size: 27px;
    color: #6b4030;
}

.card p {
    color: #92766b;
    font-size: 13px;
    margin-top: 5px;
}

.content {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 7px 20px rgba(80,40,20,.08);
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.content-header h2 {
    color: #6b4030;
    font-size: 20px;
}

.content-header a {
    text-decoration: none;
    background: #6b4030;
    color: white;
    padding: 9px 14px;
    border-radius: 10px;
    font-size: 12px;
    transition: 0.2s;
}

.content-header a:hover {
    background: #ff72b4;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th {
    text-align: left;
    background: #fff5f9;
    color: #6b4030;
    padding: 13px;
    font-size: 12px;
}

td {
    padding: 13px;
    border-bottom: 1px solid #f2e5df;
    font-size: 13px;
}

tr:hover td {
    background: #fffafd;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    background: #eaf8ee;
    color: #39804c;
    font-size: 11px;
    font-weight: bold;
}

.empty {
    text-align: center;
    padding: 30px;
    color: #999;
}

@media(max-width: 1100px) {

    .cards {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media(max-width: 700px) {

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

    .top {
        display: block;
    }

    .admin-box {
        margin-top: 15px;
    }

    .cards {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<aside class="sidebar">

    <div class="brand">
        <div class="brand-icon">🍫</div>
        <h2>Jessie Bites</h2>
        <p>Admin Panel</p>
    </div>

    <div class="menu">

        <a href="admin_dashboard.php" class="active">
            ⌂ <span>Dashboard</span>
        </a>

        <a href="admin_transaksi.php">
            ▣ <span>Data Transaksi</span>
        </a>

        <a href="laporan_bulanan.php">
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

</aside>

<div class="main">

    <div class="top">

        <div class="welcome">

            <h1>Halo, <?= htmlspecialchars($namaAdmin); ?>! 👋</h1>

            <p>Selamat datang di dashboard Admin Jessie Bites.</p>

        </div>

        <div class="admin-box">

            👤 Admin:
            <span><?= htmlspecialchars($namaAdmin); ?></span>

        </div>

    </div>

    <div class="cards">

        <div class="card">

            <div class="card-icon">
                🍫
            </div>

            <h3><?= number_format($totalProduk); ?></h3>

            <p>Total Produk</p>

        </div>

        <div class="card">

            <div class="card-icon">
                👥
            </div>

            <h3><?= number_format($totalPelanggan); ?></h3>

            <p>Total Pelanggan</p>

        </div>

        <div class="card">

            <div class="card-icon">
                🧾
            </div>

            <h3><?= number_format($totalTransaksi); ?></h3>

            <p>Total Transaksi</p>

        </div>

        <div class="card">

            <div class="card-icon">
                💰
            </div>

            <h3>
                Rp <?= number_format($totalPendapatan, 0, ',', '.'); ?>
            </h3>

            <p>Total Pendapatan</p>

        </div>

    </div>

    <div class="content">

        <div class="content-header">

            <h2>🧾 Transaksi Terbaru</h2>

            <a href="admin_transaksi.php">
                Lihat Semua
            </a>

        </div>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Admin/Kasir</th>
                        <th>Pembayaran</th>
                        <th>Status</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($queryTerbaru && $queryTerbaru->num_rows > 0): ?>

                    <?php while ($row = $queryTerbaru->fetch_assoc()): ?>

                        <tr>

                            <td>
                                #<?= htmlspecialchars($row['TransaksiID']); ?>
                            </td>

                            <td>
                                <?= date('d/m/Y H:i', strtotime($row['TanggalTransaksi'])); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['NamaPelanggan'] ?? '-'); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['NamaAdmin'] ?? '-'); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['MetodePembayaran'] ?? '-'); ?>
                            </td>

                            <td>

                                <span class="status">
                                    <?= htmlspecialchars($row['StatusTransaksi'] ?? 'Selesai'); ?>
                                </span>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="6" class="empty">
                            Belum ada transaksi.
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

