<?php

session_start();
include 'koneksi.php';

if (!isset($_SESSION['AdminID']) || $_SESSION['Level'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

$id = $_GET['id'] ?? '';

if ($id === '' || !is_numeric($id)) {
    header("Location: admin_transaksi.php");
    exit;
}

$id = (int)$id;

$error = "";
$success = "";

function ambilDataTransaksi($conn, $id)
{
    $stmt = $conn->prepare("
        SELECT
            p.PenjualanID,
            p.TanggalPenjualan,
            p.TotalHarga,
            p.PelangganID,
            pl.NamaPelanggan,
            t.TransaksiID,
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
        WHERE p.PenjualanID = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    $stmt->close();

    return $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pelangganID = $_POST['PelangganID'] ?? '';
    $totalHarga = $_POST['TotalHarga'] ?? '';
    $metodePembayaran = $_POST['MetodePembayaran'] ?? '';
    $statusTransaksi = $_POST['StatusTransaksi'] ?? '';

    if (
        $pelangganID === '' ||
        $totalHarga === '' ||
        $metodePembayaran === '' ||
        $statusTransaksi === ''
    ) {

        $error = "Semua data wajib diisi.";

    } elseif (!is_numeric($totalHarga) || $totalHarga < 0) {

        $error = "Harga tidak valid.";

    } elseif (!in_array($metodePembayaran, ['Cash', 'Transfer', 'QRIS'])) {

        $error = "Metode pembayaran tidak valid.";

    } elseif (!in_array($statusTransaksi, ['Proses', 'Selesai', 'Dibatalkan'])) {

        $error = "Status transaksi tidak valid.";

    } else {

        $totalHarga = (float)$totalHarga;
        $pelangganID = (int)$pelangganID;

        $dataLama = ambilDataTransaksi($conn, $id);

        if (!$dataLama) {

            $error = "Transaksi tidak ditemukan.";

        } else {

            $transaksiID = $dataLama['TransaksiID'];

            $namaPelangganBaru = "Tidak diketahui";

            $stmtPelanggan = $conn->prepare("
                SELECT NamaPelanggan
                FROM pelanggan
                WHERE PelangganID = ?
                LIMIT 1
            ");

            if ($stmtPelanggan) {

                $stmtPelanggan->bind_param("i", $pelangganID);
                $stmtPelanggan->execute();

                $hasilPelanggan = $stmtPelanggan->get_result()->fetch_assoc();

                if ($hasilPelanggan) {
                    $namaPelangganBaru = $hasilPelanggan['NamaPelanggan'];
                }

                $stmtPelanggan->close();
            }

            $adaPerubahan = false;

            if ((int)$dataLama['PelangganID'] !== $pelangganID) {
                $adaPerubahan = true;
            }

            if ((float)$dataLama['TotalHarga'] != $totalHarga) {
                $adaPerubahan = true;
            }

            if (($dataLama['MetodePembayaran'] ?? '') !== $metodePembayaran) {
                $adaPerubahan = true;
            }

            if (($dataLama['StatusTransaksi'] ?? '') !== $statusTransaksi) {
                $adaPerubahan = true;
            }

            if (!$adaPerubahan) {

                $success = "Tidak ada perubahan data.";

            } else {

                $namaPelangganLama = $dataLama['NamaPelanggan'] ?? "Tidak diketahui";

                $dataLamaLog =
                    "Nama Pelanggan: " . $namaPelangganLama . "\n" .
                    "Total Harga: Rp " . number_format((float)$dataLama['TotalHarga'], 0, ',', '.') . "\n" .
                    "Metode Pembayaran: " . ($dataLama['MetodePembayaran'] ?? '-') . "\n" .
                    "Status Transaksi: " . ($dataLama['StatusTransaksi'] ?? '-');

                $dataBaruLog =
                    "Nama Pelanggan: " . $namaPelangganBaru . "\n" .
                    "Total Harga: Rp " . number_format($totalHarga, 0, ',', '.') . "\n" .
                    "Metode Pembayaran: " . $metodePembayaran . "\n" .
                    "Status Transaksi: " . $statusTransaksi;

                $conn->begin_transaction();

                try {

                    $stmt = $conn->prepare("
                        UPDATE penjualan
                        SET
                            PelangganID = ?,
                            TotalHarga = ?
                        WHERE PenjualanID = ?
                    ");

                    if (!$stmt) {
                        throw new Exception($conn->error);
                    }

                    $stmt->bind_param(
                        "idi",
                        $pelangganID,
                        $totalHarga,
                        $id
                    );

                    if (!$stmt->execute()) {
                        throw new Exception($stmt->error);
                    }

                    $stmt->close();

                    if (!empty($transaksiID)) {

                        $stmt = $conn->prepare("
                            UPDATE transaksi
                            SET
                                PelangganID = ?,
                                MetodePembayaran = ?,
                                StatusTransaksi = ?
                            WHERE TransaksiID = ?
                        ");

                        if (!$stmt) {
                            throw new Exception($conn->error);
                        }

                        $stmt->bind_param(
                            "issi",
                            $pelangganID,
                            $metodePembayaran,
                            $statusTransaksi,
                            $transaksiID
                        );

                        if (!$stmt->execute()) {
                            throw new Exception($stmt->error);
                        }

                        $stmt->close();

                    }

                    $jenisPerubahan = "Edit Transaksi";
                    $adminID = $_SESSION['AdminID'];

                    $stmtLog = $conn->prepare("
                        INSERT INTO log_transaksi
                        (
                            PenjualanID,
                            AdminID,
                            JenisPerubahan,
                            DataLama,
                            DataBaru
                        )
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    if (!$stmtLog) {
                        throw new Exception($conn->error);
                    }

                    $stmtLog->bind_param(
                        "iisss",
                        $id,
                        $adminID,
                        $jenisPerubahan,
                        $dataLamaLog,
                        $dataBaruLog
                    );

                    if (!$stmtLog->execute()) {
                        throw new Exception($stmtLog->error);
                    }

                    $stmtLog->close();

                    $conn->commit();

                    $success = "Data transaksi berhasil diperbarui dan riwayat edit berhasil dicatat.";

                } catch (Exception $e) {

                    $conn->rollback();

                    $error = "Perubahan gagal disimpan: " . $e->getMessage();
                }
            }
        }
    }
}

$data = ambilDataTransaksi($conn, $id);

if (!$data) {
    header("Location: admin_transaksi.php");
    exit;
}

$pelanggan = $conn->query("
    SELECT PelangganID, NamaPelanggan
    FROM pelanggan
    ORDER BY NamaPelanggan ASC
");

function rupiah($angka)
{
    return "Rp " . number_format((float)$angka, 0, ',', '.');
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

    <title>Edit Transaksi - Jessie Bites</title>

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

        .layout {
            min-height: 100vh;
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

        .topbar {
            background: white;
            border-radius: 20px;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(120, 70, 80, 0.07);
        }

        .topbar h1 {
            color: #56352b;
            font-size: 24px;
        }

        .topbar p {
            margin-top: 5px;
            color: #9b7c82;
            font-size: 13px;
        }

        .admin-name {
            padding: 10px 15px;
            background: #fff0f6;
            color: #8c4b67;
            border-radius: 12px;
            font-size: 13px;
            font-weight: bold;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(120, 70, 80, 0.07);
            max-width: 850px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
        }

        .card-title .icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: #fff0d9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .card-title h2 {
            color: #56352b;
            font-size: 20px;
        }

        .card-title p {
            color: #9b7c82;
            font-size: 12px;
            margin-top: 4px;
        }

        .info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .info-box {
            background: #fff7fa;
            border: 1px solid #f1dfe6;
            border-radius: 14px;
            padding: 15px;
        }

        .info-box small {
            display: block;
            color: #a0858e;
            font-size: 11px;
            margin-bottom: 6px;
        }

        .info-box strong {
            color: #633e2f;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: bold;
            color: #70452f;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #ead6de;
            border-radius: 11px;
            background: #fffafd;
            outline: none;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #49363e;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #ff72b4;
            box-shadow: 0 0 0 3px rgba(255, 114, 180, 0.1);
        }

        .harga-input {
            position: relative;
        }

        .harga-input span {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #8f6b79;
            font-size: 13px;
            font-weight: bold;
        }

        .harga-input input {
            padding-left: 45px;
        }

        .alert {
            padding: 13px 15px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        .alert-error {
            background: #ffe8e8;
            color: #a33a3a;
        }

        .alert-success {
            background: #e6f8ed;
            color: #39815b;
        }

        .warning {
            background: #fff8e7;
            border: 1px solid #f2dfaa;
            color: #876a28;
            padding: 13px 15px;
            border-radius: 12px;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            border: none;
            padding: 13px 18px;
            border-radius: 11px;
            text-decoration: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            font-weight: bold;
        }

        .btn-save {
            background: #ff72b4;
            color: white;
        }

        .btn-save:hover {
            background: #ed5da2;
        }

        .btn-back {
            background: #f2e0e7;
            color: #73485b;
        }

        @media (max-width: 900px) {

            .sidebar {
                width: 210px;
            }

            .content {
                margin-left: 210px;
                padding: 25px;
            }

            .info {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="layout">

    <aside class="sidebar">

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

    </aside>

    <main class="content">

        <div class="topbar">

            <div>

                <h1>✏️ Edit Transaksi</h1>

                <p>
                    Perbaiki data transaksi yang kurang tepat.
                </p>

            </div>

            <div class="admin-name">
                👩🏻‍💼 <?= htmlspecialchars($_SESSION['NamaAdmin'] ?? 'Admin'); ?>
            </div>

        </div>

        <div class="card">

            <div class="card-title">

                <div class="icon">
                    ✏️
                </div>

                <div>

                    <h2>
                        Edit #<?= str_pad($data['PenjualanID'], 4, '0', STR_PAD_LEFT); ?>
                    </h2>

                    <p>
                        Admin:
                        <?= htmlspecialchars($_SESSION['NamaAdmin'] ?? 'Admin'); ?>
                    </p>

                </div>

            </div>

            <?php if ($error !== ''): ?>

                <div class="alert alert-error">
                    ❌ <?= htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>

            <?php if ($success !== ''): ?>

                <div class="alert alert-success">
                    ✅ <?= htmlspecialchars($success); ?>
                </div>

            <?php endif; ?>

            <div class="warning">

                ⚠️ Admin dapat memperbaiki pelanggan, total harga, metode pembayaran,
                dan status transaksi. Setiap perubahan akan tercatat otomatis.

            </div>

            <div class="info">

                <div class="info-box">

                    <small>Nomor Transaksi</small>

                    <strong>
                        #<?= str_pad($data['PenjualanID'], 4, '0', STR_PAD_LEFT); ?>
                    </strong>

                </div>

                <div class="info-box">

                    <small>Tanggal & Jam</small>

                    <strong>
                        <?= tanggalIndonesia($data['TanggalPenjualan']); ?>
                    </strong>

                </div>

                <div class="info-box">

                    <small>Admin Saat Ini</small>

                    <strong>
                        <?= htmlspecialchars($_SESSION['NamaAdmin'] ?? 'Admin'); ?>
                    </strong>

                </div>

            </div>

            <form method="POST">

                <div class="form-group">

                    <label>Pelanggan</label>

                    <select name="PelangganID" required>

                        <option value="">
                            Pilih pelanggan
                        </option>

                        <?php while ($pl = $pelanggan->fetch_assoc()): ?>

                            <option
                                value="<?= $pl['PelangganID']; ?>"
                                <?= $data['PelangganID'] == $pl['PelangganID'] ? 'selected' : ''; ?>
                            >
                                <?= htmlspecialchars($pl['NamaPelanggan']); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label>Total Harga</label>

                    <div class="harga-input">

                        <span>Rp</span>

                        <input
                            type="number"
                            name="TotalHarga"
                            min="0"
                            step="1"
                            value="<?= htmlspecialchars($data['TotalHarga']); ?>"
                            required
                        >

                    </div>

                </div>

                <div class="form-group">

                    <label>Metode Pembayaran</label>

                    <select name="MetodePembayaran" required>

                        <option value="">
                            Pilih metode pembayaran
                        </option>

                        <option
                            value="Cash"
                            <?= $data['MetodePembayaran'] === 'Cash' ? 'selected' : ''; ?>
                        >
                            Cash
                        </option>

                        <option
                            value="Transfer"
                            <?= $data['MetodePembayaran'] === 'Transfer' ? 'selected' : ''; ?>
                        >
                            Transfer
                        </option>

                        <option
                            value="QRIS"
                            <?= $data['MetodePembayaran'] === 'QRIS' ? 'selected' : ''; ?>
                        >
                            QRIS
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label>Status Transaksi</label>

                    <select name="StatusTransaksi" required>

                        <option
                            value="Proses"
                            <?= $data['StatusTransaksi'] === 'Proses' ? 'selected' : ''; ?>
                        >
                            ⏳ Proses
                        </option>

                        <option
                            value="Selesai"
                            <?= $data['StatusTransaksi'] === 'Selesai' ? 'selected' : ''; ?>
                        >
                            ✓ Selesai
                        </option>

                        <option
                            value="Dibatalkan"
                            <?= $data['StatusTransaksi'] === 'Dibatalkan' ? 'selected' : ''; ?>
                        >
                            ✕ Dibatalkan
                        </option>

                    </select>

                </div>

                <div class="button-group">

                    <button type="submit" class="btn btn-save">
                        💾 Simpan Perubahan
                    </button>

                    <a href="admin_transaksi.php" class="btn btn-back">
                        ← Kembali
                    </a>

                </div>

            </form>

        </div>

    </main>

</div>

</body>

</html>