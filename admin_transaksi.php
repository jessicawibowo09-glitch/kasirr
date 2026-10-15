<?php

session_start();
include 'koneksi.php';

if (!isset($_SESSION['AdminID']) || $_SESSION['Level'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

$keyword = trim($_GET['keyword'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT
        p.PenjualanID,
        p.TanggalPenjualan,
        p.TotalHarga,
        pl.NamaPelanggan,
        a.NamaAdmin,
        t.MetodePembayaran,
        t.StatusTransaksi
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
    WHERE 1=1
";

$params = [];
$types = "";

if ($keyword !== '') {
    $sql .= "
        AND (
            p.PenjualanID LIKE ?
            OR pl.NamaPelanggan LIKE ?
            OR a.NamaAdmin LIKE ?
        )
    ";

    $search = "%" . $keyword . "%";

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;

    $types .= "sss";
}

if ($status !== '') {
    $sql .= " AND t.StatusTransaksi = ?";
    $params[] = $status;
    $types .= "s";
}

$sql .= " ORDER BY p.PenjualanID DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Transaksi - Jessie Bites</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    background: #fff8fb;
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
    color: #9b7770;
    font-size: 14px;
    margin-top: 5px;
}

.filter-box {
    background: white;
    padding: 20px;
    border-radius: 18px;
    box-shadow: 0 5px 20px rgba(90, 50, 40, 0.08);
    margin-bottom: 22px;
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-form input,
.filter-form select {
    padding: 12px 15px;
    border: 1px solid #ead8d3;
    border-radius: 10px;
    outline: none;
    font-size: 13px;
    background: #fff;
}

.filter-form input {
    flex: 1;
    min-width: 230px;
}

.filter-form input:focus,
.filter-form select:focus {
    border-color: #ff72b4;
}

.btn-filter {
    border: none;
    padding: 12px 20px;
    background: #ff72b4;
    color: white;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
}

.btn-reset {
    text-decoration: none;
    padding: 12px 20px;
    background: #f3e7e2;
    color: #6b4030;
    border-radius: 10px;
    font-size: 13px;
    display: flex;
    align-items: center;
}

.table-box {
    background: white;
    border-radius: 18px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(90, 50, 40, 0.08);
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 950px;
}

thead {
    background: #fff0f6;
}

th {
    padding: 15px 12px;
    text-align: left;
    font-size: 13px;
    color: #6b4030;
}

td {
    padding: 14px 12px;
    border-bottom: 1px solid #f1e5e1;
    font-size: 13px;
    color: #5f4841;
}

tr:hover td {
    background: #fffafd;
}

.id {
    font-weight: 700;
    color: #ff4da6;
}

.total {
    font-weight: 700;
    color: #6b4030;
}

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    background: #fff0f6;
    color: #ff4da6;
    font-size: 11px;
    font-weight: 600;
}

.action {
    display: flex;
    gap: 6px;
}

.action a {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    border-radius: 9px;
    transition: 0.2s;
}

.btn-detail {
    background: #eee8ff;
}

.btn-detail:hover {
    background: #ddd2ff;
}

.btn-print {
    background: #e8f5ff;
}

.btn-print:hover {
    background: #d2edff;
}

.btn-edit {
    background: #fff1d8;
}

.btn-edit:hover {
    background: #ffe2ad;
}

.btn-delete {
    background: #ffe5e5;
}

.btn-delete:hover {
    background: #ffcccc;
}

.empty {
    text-align: center;
    padding: 45px;
    color: #a58d86;
}

@media(max-width: 800px) {

    .sidebar {
        width: 190px;
    }

    .main {
        margin-left: 190px;
        padding: 20px;
    }

    .menu a {
        font-size: 12px;
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
            ⌂
            <span>Dashboard</span>
        </a>

        <a href="admin_transaksi.php" class="active">
            ▣
            <span>Data Transaksi</span>
        </a>

        <a href="laporan_bulanan.php">
            📊
            <span>Laporan Bulanan</span>
        </a>

        <a href="log_transaksi.php">
            ◷
            <span>Riwayat Edit</span>
        </a>

        <a href="produk.php">
            ▤
            <span>Data Produk</span>
        </a>

        <a href="pelanggan.php">
            ♙
            <span>Data Pelanggan</span>
        </a>

        <a href="index.php">
            ▣
            <span>Ke Halaman Kasir</span>
        </a>

    </div>

    <div class="logout">

        <a href="logout.php">
            ↪
            <span>Logout</span>
        </a>

    </div>

</div>

<div class="main">

    <div class="header">

        <div>

            <h1>Data Transaksi 🍫</h1>

            <p>Kelola transaksi penjualan Jessie Bites</p>

        </div>

    </div>

    <div class="filter-box">

        <form method="GET" class="filter-form">

            <input
                type="text"
                name="keyword"
                placeholder="Cari nomor transaksi, pelanggan, atau admin..."
                value="<?= htmlspecialchars($keyword) ?>"
            >

            <select name="status">

                <option value="">Semua Status</option>

                <option value="Diproses" <?= $status === 'Diproses' ? 'selected' : '' ?>>
                    Diproses
                </option>

                <option value="Proses" <?= $status === 'Proses' ? 'selected' : '' ?>>
                    Proses
                </option>

                <option value="Selesai" <?= $status === 'Selesai' ? 'selected' : '' ?>>
                    Selesai
                </option>

                <option value="Dibatalkan" <?= $status === 'Dibatalkan' ? 'selected' : '' ?>>
                    Dibatalkan
                </option>

            </select>

            <button type="submit" class="btn-filter">
                🔍 Cari
            </button>

            <a href="admin_transaksi.php" class="btn-reset">
                Reset
            </a>

        </form>

    </div>

    <div class="table-box">

        <table>

            <thead>

                <tr>

                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Admin</th>
                    <th>Pembayaran</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>

                </tr>

            </thead>

            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php $no = 1; ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <tr>

                        <td class="id">
                            #<?= $row['PenjualanID'] ?>
                        </td>

                        <td>
                            <?= date('d/m/Y H:i', strtotime($row['TanggalPenjualan'])) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['NamaPelanggan'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['NamaAdmin'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['MetodePembayaran'] ?? '-') ?>
                        </td>

                        <td class="total">
                            Rp <?= number_format($row['TotalHarga'], 0, ',', '.') ?>
                        </td>

                        <td>

                            <span class="status">
                                <?= htmlspecialchars($row['StatusTransaksi'] ?? '-') ?>
                            </span>

                        </td>

                        <td>

                            <div class="action">

                                <a
                                    href="detail_transaksi.php?id=<?= $row['PenjualanID'] ?>"
                                    target="_blank"
                                    class="btn-detail"
                                    title="Lihat Detail"
                                >
                                    👁️
                                </a>

                                <a
                                    href="struk.php?id=<?= $row['PenjualanID'] ?>"
                                    target="_blank"
                                    class="btn-print"
                                    title="Cetak Struk"
                                >
                                    🖨️
                                </a>

                                <a
                                    href="edit_transaksi.php?id=<?= $row['PenjualanID'] ?>"
                                    class="btn-edit"
                                    title="Edit Transaksi"
                                >
                                    ✏️
                                </a>

                                <a
                                    href="hapus_transaksi.php?id=<?= $row['PenjualanID'] ?>"
                                    class="btn-delete"
                                    title="Hapus Transaksi"
                                    onclick="return confirm('Yakin ingin menghapus transaksi ini? Data yang dihapus tidak dapat dikembalikan.')"
                                >
                                    🗑️
                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td colspan="8" class="empty">
                        🍫 Belum ada data transaksi.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>

