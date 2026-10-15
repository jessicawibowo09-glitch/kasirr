<?php

session_start();

include 'koneksi.php';

if (!isset($_SESSION['AdminID']) || $_SESSION['Level'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

$query = $conn->query("
    SELECT
        l.LogID AS ID,
        l.PenjualanID AS ReferensiID,
        'Transaksi' AS Tipe,
        l.JenisPerubahan,
        l.DataLama,
        l.DataBaru,
        l.TanggalEdit,
        a.NamaAdmin
    FROM log_transaksi l
    LEFT JOIN admin a
        ON l.AdminID = a.AdminID

    UNION ALL

    SELECT
        lp.LogProdukID AS ID,
        lp.ProdukID AS ReferensiID,
        'Produk' AS Tipe,
        lp.JenisPerubahan,
        lp.DataLama,
        lp.DataBaru,
        lp.TanggalEdit,
        a.NamaAdmin
    FROM log_produk lp
    LEFT JOIN admin a
        ON lp.AdminID = a.AdminID

    UNION ALL

    SELECT
        lg.LogPelangganID AS ID,
        lg.PelangganID AS ReferensiID,
        'Pelanggan' AS Tipe,
        lg.JenisPerubahan,
        lg.DataLama,
        lg.DataBaru,
        lg.TanggalEdit,
        a.NamaAdmin
    FROM log_pelanggan lg
    LEFT JOIN admin a
        ON lg.AdminID = a.AdminID

    ORDER BY TanggalEdit DESC
");

function tampilkanDataLog($teks)
{
    if (empty($teks)) {
        echo '<div class="kosong">
                Tidak ada data
              </div>';
        return;
    }

    $baris = preg_split('/\r\n|\r|\n/', $teks);

    foreach ($baris as $data) {

        $data = trim($data);

        if ($data === '') {
            continue;
        }

        $bagian = explode(': ', $data, 2);

        echo '<div class="data-row">';

        if (count($bagian) === 2) {

            echo '<span class="data-label">'
                . htmlspecialchars($bagian[0])
                . '</span>';

            echo '<strong>'
                . htmlspecialchars($bagian[1])
                . '</strong>';

        } else {

            echo '<strong>'
                . htmlspecialchars($data)
                . '</strong>';
        }

        echo '</div>';
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Riwayat Edit - Jessie Bites</title>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>

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

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    font-size: 27px;
    color: #4b2c22;
}

.header p {
    color: #9a7770;
    font-size: 13px;
    margin-top: 5px;
}

.header-icon {
    width: 55px;
    height: 55px;
    border-radius: 16px;
    background: #ffe2ef;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 27px;
}

.log-card {
    background: white;
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 22px;
    box-shadow: 0 5px 20px rgba(92, 54, 43, 0.08);
    border: 1px solid #f7e3ea;
}

.log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f2e4e8;
}

.log-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.log-number {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: #ff72b4;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.log-title h3 {
    font-size: 16px;
    color: #4b2c22;
}

.log-title p {
    font-size: 12px;
    color: #a08078;
    margin-top: 2px;
}

.tipe-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 10px;
}

.tipe-transaksi {
    background: #fff0c9;
    color: #9b681d;
}

.tipe-produk {
    background: #e8f8ef;
    color: #347653;
}

.tipe-pelanggan {
    background: #ffe2ef;
    color: #c03b78;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.admin-info,
.tanggal-info {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 12px;
    border-radius: 9px;
    font-size: 12px;
}

.admin-info {
    background: #f5ede9;
    color: #6b4030;
}

.tanggal-info {
    background: #f4f1ff;
    color: #68558e;
}

.change-title {
    font-size: 13px;
    font-weight: 600;
    color: #6b4030;
    margin-bottom: 10px;
}

.data-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.data-box {
    border-radius: 15px;
    padding: 18px;
}

.data-box.lama {
    background: #fff4f4;
    border: 1px solid #f4dcdc;
}

.data-box.baru {
    background: #f1fff7;
    border: 1px solid #d9f0e2;
}

.data-box h4 {
    font-size: 13px;
    margin-bottom: 14px;
}

.data-box.lama h4 {
    color: #b85c5c;
}

.data-box.baru h4 {
    color: #3d8b63;
}

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    padding: 10px 0;
    border-bottom: 1px dashed rgba(100, 70, 60, 0.12);
    font-size: 12px;
}

.data-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.data-label {
    color: #987b73;
    min-width: 125px;
}

.data-row strong {
    color: #4b2c22;
    text-align: right;
    word-break: break-word;
}

.lama .data-row strong {
    color: #8e4f4f;
}

.baru .data-row strong {
    color: #347653;
}

.empty {
    background: white;
    padding: 55px;
    text-align: center;
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(92, 54, 43, 0.08);
}

.empty-icon {
    font-size: 50px;
    margin-bottom: 12px;
}

.empty h3 {
    color: #6b4030;
    margin-bottom: 5px;
}

.empty p {
    color: #a08078;
    font-size: 13px;
}

.kosong {
    color: #a08078;
    font-size: 12px;
}

@media (max-width: 850px) {

    .sidebar {
        width: 210px;
    }

    .main {
        margin-left: 210px;
        padding: 25px;
    }

    .data-container {
        grid-template-columns: 1fr;
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

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .header {
        align-items: flex-start;
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

        <h2>
            Jessie Bites
        </h2>

        <p>
            Admin Panel
        </p>

    </div>

    <div class="menu">

        <a href="admin_dashboard.php">
            ⌂
            <span>
                Dashboard
            </span>
        </a>

        <a href="admin_transaksi.php">
            ▣
            <span>
                Data Transaksi
            </span>
        </a>

        <a href="laporan_bulanan.php">
            📊
            <span>
                Laporan Bulanan
            </span>
        </a>

        <a href="log_transaksi.php" class="active">
            ◷
            <span>
                Riwayat Edit
            </span>
        </a>

        <a href="produk.php">
            ▤
            <span>
                Data Produk
            </span>
        </a>

        <a href="pelanggan.php">
            ♙
            <span>
                Data Pelanggan
            </span>
        </a>

        <a href="index.php">
            ▣
            <span>
                Ke Halaman Kasir
            </span>
        </a>

    </div>

    <div class="logout">

        <a href="logout.php">
            ↪
            <span>
                Logout
            </span>
        </a>

    </div>

</div>

<div class="main">

    <div class="header">

        <div>

            <h1>
                Riwayat Edit
            </h1>

            <p>
                Catatan perubahan transaksi, produk, dan pelanggan yang dilakukan oleh admin
            </p>

        </div>

        <div class="header-icon">
            ◷
        </div>

    </div>

    <?php if ($query && $query->num_rows > 0): ?>

        <?php $nomor = 1; ?>

        <?php while ($log = $query->fetch_assoc()): ?>

            <div class="log-card">

                <div class="log-header">

                    <div class="log-title">

                        <div class="log-number">
                            <?= $nomor++ ?>
                        </div>

                        <div>

                            <h3>
                                <?= htmlspecialchars(
                                    $log['JenisPerubahan']
                                    ?: 'Perubahan Data'
                                ) ?>
                            </h3>

                            <p>

                                <?= htmlspecialchars(
                                    $log['Tipe']
                                ) ?>

                                #

                                <?= htmlspecialchars(
                                    $log['ReferensiID']
                                ) ?>

                            </p>

                        </div>

                    </div>

                    <?php if ($log['Tipe'] === 'Produk'): ?>

                        <span class="tipe-badge tipe-produk">
                            📦 Produk
                        </span>

                    <?php elseif ($log['Tipe'] === 'Pelanggan'): ?>

                        <span class="tipe-badge tipe-pelanggan">
                            👥 Pelanggan
                        </span>

                    <?php else: ?>

                        <span class="tipe-badge tipe-transaksi">
                            🧾 Transaksi
                        </span>

                    <?php endif; ?>

                </div>

                <div class="info-row">

                    <div class="admin-info">

                        👤
                        Diubah oleh:

                        <strong>
                            <?= htmlspecialchars(
                                $log['NamaAdmin']
                                ?? 'Admin'
                            ) ?>
                        </strong>

                    </div>

                    <div class="tanggal-info">

                        🕐

                        <?= !empty($log['TanggalEdit'])
                            ? date(
                                'd-m-Y H:i',
                                strtotime(
                                    $log['TanggalEdit']
                                )
                            )
                            : '-'
                        ?>

                    </div>

                </div>

                <div class="change-title">
                    Detail Perubahan
                </div>

                <div class="data-container">

                    <div class="data-box lama">

                        <h4>
                            🔴 Data Lama
                        </h4>

                        <?php
                        tampilkanDataLog(
                            $log['DataLama']
                        );
                        ?>

                    </div>

                    <div class="data-box baru">

                        <h4>
                            🟢 Data Baru
                        </h4>

                        <?php
                        tampilkanDataLog(
                            $log['DataBaru']
                        );
                        ?>

                    </div>

                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                📝
            </div>

            <h3>
                Belum Ada Riwayat Edit
            </h3>

            <p>
                Riwayat perubahan transaksi, produk, dan pelanggan akan muncul di sini.
            </p>

        </div>

    <?php endif; ?>

</div>

</body>

</html>

