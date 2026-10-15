<?php

session_start();
include 'koneksi.php';

if (!isset($_SESSION['AdminID']) || $_SESSION['Level'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

$pesan = "";
$error = "";
$adminID = (int)$_SESSION['AdminID'];

function simpanLogProduk($conn, $produkID, $adminID, $jenis, $dataLama, $dataBaru)
{
    $stmt = $conn->prepare("
        INSERT INTO log_produk
        (ProdukID, AdminID, JenisPerubahan, DataLama, DataBaru)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iisss",
        $produkID,
        $adminID,
        $jenis,
        $dataLama,
        $dataBaru
    );

    $hasil = $stmt->execute();
    $stmt->close();

    return $hasil;
}

if (isset($_GET['hapus'])) {

    $id = (int)$_GET['hapus'];

    if ($id > 0) {

        $stmt = $conn->prepare("
            SELECT ProdukID, NamaProduk, Harga, Stok
            FROM produk
            WHERE ProdukID = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $produkLama = $result->fetch_assoc();

        $stmt->close();

        if ($produkLama) {

            $dataLama =
                "Nama Produk: " . $produkLama['NamaProduk'] . "\n" .
                "Harga: Rp " . number_format($produkLama['Harga'], 0, ',', '.') . "\n" .
                "Stok: " . $produkLama['Stok'];

            $stmt = $conn->prepare("
                DELETE FROM produk
                WHERE ProdukID = ?
            ");

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {

                simpanLogProduk(
                    $conn,
                    $id,
                    $adminID,
                    "Hapus Produk",
                    $dataLama,
                    "Produk dihapus"
                );

                $pesan = "Produk berhasil dihapus.";

            } else {

                $error = "Produk tidak dapat dihapus karena masih digunakan dalam transaksi.";
            }

            $stmt->close();

        } else {

            $error = "Produk tidak ditemukan.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';

    $namaProduk = trim($_POST['NamaProduk'] ?? '');
    $harga = (float)($_POST['Harga'] ?? 0);
    $stok = (int)($_POST['Stok'] ?? 0);

    if ($namaProduk === '') {

        $error = "Nama produk wajib diisi.";

    } elseif ($harga < 0) {

        $error = "Harga tidak boleh kurang dari 0.";

    } elseif ($stok < 0) {

        $error = "Stok tidak boleh kurang dari 0.";

    } else {

        if ($aksi === 'tambah') {

            $stmt = $conn->prepare("
                INSERT INTO produk
                (NamaProduk, Harga, Stok)
                VALUES (?, ?, ?)
            ");

            $stmt->bind_param(
                "sdi",
                $namaProduk,
                $harga,
                $stok
            );

            if ($stmt->execute()) {

                $produkID = $conn->insert_id;

                $dataBaru =
                    "Nama Produk: " . $namaProduk . "\n" .
                    "Harga: Rp " . number_format($harga, 0, ',', '.') . "\n" .
                    "Stok: " . $stok;

                simpanLogProduk(
                    $conn,
                    $produkID,
                    $adminID,
                    "Tambah Produk",
                    "Tidak ada",
                    $dataBaru
                );

                $pesan = "Produk berhasil ditambahkan.";

            } else {

                $error = "Produk gagal ditambahkan.";
            }

            $stmt->close();

        } elseif ($aksi === 'edit') {

            $id = (int)($_POST['ProdukID'] ?? 0);

            if ($id <= 0) {

                $error = "ID produk tidak valid.";

            } else {

                $stmt = $conn->prepare("
                    SELECT ProdukID, NamaProduk, Harga, Stok
                    FROM produk
                    WHERE ProdukID = ?
                    LIMIT 1
                ");

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $resultLama = $stmt->get_result();
                $produkLama = $resultLama->fetch_assoc();

                $stmt->close();

                if (!$produkLama) {

                    $error = "Produk tidak ditemukan.";

                } else {

                    $dataLama =
                        "Nama Produk: " . $produkLama['NamaProduk'] . "\n" .
                        "Harga: Rp " . number_format($produkLama['Harga'], 0, ',', '.') . "\n" .
                        "Stok: " . $produkLama['Stok'];

                    $stmt = $conn->prepare("
                        UPDATE produk
                        SET NamaProduk = ?, Harga = ?, Stok = ?
                        WHERE ProdukID = ?
                    ");

                    $stmt->bind_param(
                        "sdii",
                        $namaProduk,
                        $harga,
                        $stok,
                        $id
                    );

                    if ($stmt->execute()) {

                        $dataBaru =
                            "Nama Produk: " . $namaProduk . "\n" .
                            "Harga: Rp " . number_format($harga, 0, ',', '.') . "\n" .
                            "Stok: " . $stok;

                        simpanLogProduk(
                            $conn,
                            $id,
                            $adminID,
                            "Edit Produk",
                            $dataLama,
                            $dataBaru
                        );

                        $pesan = "Produk berhasil diperbarui.";

                    } else {

                        $error = "Produk gagal diperbarui.";
                    }

                    $stmt->close();
                }
            }
        }
    }
}

$editData = null;

if (isset($_GET['edit'])) {

    $idEdit = (int)$_GET['edit'];

    if ($idEdit > 0) {

        $stmt = $conn->prepare("
            SELECT ProdukID, NamaProduk, Harga, Stok
            FROM produk
            WHERE ProdukID = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $idEdit);
        $stmt->execute();

        $resultEdit = $stmt->get_result();
        $editData = $resultEdit->fetch_assoc();

        $stmt->close();
    }
}

$result = $conn->query("
    SELECT ProdukID, NamaProduk, Harga, Stok
    FROM produk
    ORDER BY ProdukID DESC
");

function rupiah($angka)
{
    return "Rp " . number_format($angka, 0, ',', '.');
}

function fotoProduk($nama)
{
    $nama = strtolower($nama);

    if (strpos($nama, 'red velvet') !== false) {
        return 'assets/img/brownies red velvet.jpeg';
    }

    if (strpos($nama, 'keju') !== false) {
        return 'assets/img/brownies keju.jpeg';
    }

    if (strpos($nama, 'oreo') !== false) {
        return 'assets/img/brownies oreo.jpeg';
    }

    if (strpos($nama, 'double choco') !== false) {
        return 'assets/img/brownies double choco.jpeg';
    }

    if (strpos($nama, 'almond') !== false) {
        return 'assets/img/brownies almond.jpeg';
    }

    return 'assets/img/brownies coklat.jpeg';
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Produk - Jessie Bites</title>

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
    margin-bottom: 25px;
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

.layout {
    display: grid;
    grid-template-columns: 330px 1fr;
    gap: 25px;
    align-items: start;
}

.card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 8px 25px rgba(91, 53, 45, 0.08);
    border: 1px solid #f5e2ea;
}

.card h2 {
    color: #56352b;
    font-size: 19px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 17px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 7px;
    color: #68473e;
}

.form-group input {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #ead4dd;
    border-radius: 11px;
    outline: none;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
}

.form-group input:focus {
    border-color: #ff72b4;
    box-shadow: 0 0 0 3px rgba(255, 114, 180, 0.1);
}

.btn {
    width: 100%;
    border: none;
    padding: 13px;
    border-radius: 11px;
    background: #ff72b4;
    color: white;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    cursor: pointer;
}

.btn:hover {
    background: #ed5da2;
}

.btn-cancel {
    display: block;
    text-align: center;
    text-decoration: none;
    margin-top: 10px;
    padding: 11px;
    border-radius: 11px;
    background: #f4e8ed;
    color: #694b53;
    font-size: 13px;
}

.message {
    padding: 13px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13px;
}

.success {
    background: #eaf8ef;
    color: #287044;
}

.error {
    background: #fff0f0;
    color: #b63b3b;
}

.produk-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(220px, 1fr));
    gap: 18px;
}

.produk-card {
    border: 1px solid #f2dfe7;
    border-radius: 17px;
    overflow: hidden;
    background: #fff;
    transition: 0.2s;
}

.produk-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(91, 53, 45, 0.1);
}

.foto-box {
    width: 100%;
    height: 180px;
    background: #fff0f6;
    overflow: hidden;
}

.foto-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.produk-info {
    padding: 15px;
}

.produk-nama {
    font-size: 15px;
    font-weight: 700;
    color: #56352b;
    margin-bottom: 5px;
}

.produk-harga {
    color: #ff4d9d;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 9px;
}

.produk-stok {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 8px;
    background: #fff0d9;
    color: #9b641e;
    font-size: 11px;
    font-weight: 600;
}

.aksi {
    display: flex;
    gap: 7px;
    margin-top: 14px;
}

.aksi a {
    flex: 1;
    text-align: center;
    text-decoration: none;
    padding: 8px 9px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
}

.edit {
    background: #fff0c9;
    color: #9b681d;
}

.hapus {
    background: #ffe0e5;
    color: #b52f49;
}

.empty {
    text-align: center;
    color: #a58d94;
    padding: 30px;
    grid-column: 1 / -1;
}

@media (max-width: 1100px) {

    .produk-list {
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

    .layout {
        grid-template-columns: 1fr;
    }

    .produk-list {
        grid-template-columns: repeat(2, minmax(200px, 1fr));
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

    .produk-list {
        grid-template-columns: 1fr;
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

        <a href="admin_transaksi.php">
            ▣ <span>Data Transaksi</span>
        </a>

        <a href="laporan_bulanan.php">
            📊 <span>Laporan Bulanan</span>
        </a>

        <a href="log_transaksi.php">
            ◷ <span>Riwayat Edit</span>
        </a>

        <a href="produk.php" class="active">
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

        <h1>Data Produk</h1>

        <p>
            Kelola produk, harga, dan stok Jessie Bites.
        </p>

    </div>

    <?php if ($pesan !== ""): ?>

        <div class="message success">
            ✅ <?= htmlspecialchars($pesan) ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="message error">
            ❌ <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <div class="layout">

        <div class="card">

            <h2>
                <?= $editData ? "Edit Produk" : "Tambah Produk" ?>
            </h2>

            <form method="POST">

                <input
                    type="hidden"
                    name="aksi"
                    value="<?= $editData ? 'edit' : 'tambah' ?>"
                >

                <?php if ($editData): ?>

                    <input
                        type="hidden"
                        name="ProdukID"
                        value="<?= $editData['ProdukID'] ?>"
                    >

                <?php endif; ?>

                <div class="form-group">

                    <label>Nama Produk</label>

                    <input
                        type="text"
                        name="NamaProduk"
                        placeholder="Contoh: Brownies Coklat"
                        value="<?= htmlspecialchars($editData['NamaProduk'] ?? '') ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Harga</label>

                    <input
                        type="number"
                        name="Harga"
                        min="0"
                        placeholder="Contoh: 15000"
                        value="<?= $editData['Harga'] ?? '' ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Stok</label>

                    <input
                        type="number"
                        name="Stok"
                        min="0"
                        placeholder="Contoh: 20"
                        value="<?= $editData['Stok'] ?? '' ?>"
                        required
                    >

                </div>

                <button type="submit" class="btn">
                    <?= $editData ? "Simpan Perubahan" : "Tambah Produk" ?>
                </button>

                <?php if ($editData): ?>

                    <a href="produk.php" class="btn-cancel">
                        Batal Edit
                    </a>

                <?php endif; ?>

            </form>

        </div>

        <div class="card">

            <h2>🍫 Daftar Produk</h2>

            <div class="produk-list">

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <?php
                        $foto = fotoProduk($row['NamaProduk']);
                        ?>

                        <div class="produk-card">

                            <div class="foto-box">

                                <img
                                    src="<?= htmlspecialchars($foto) ?>"
                                    alt="<?= htmlspecialchars($row['NamaProduk']) ?>"
                                    onerror="this.src='assets/img/brownies coklat.jpeg'"
                                >

                            </div>

                            <div class="produk-info">

                                <div class="produk-nama">
                                    <?= htmlspecialchars($row['NamaProduk']) ?>
                                </div>

                                <div class="produk-harga">
                                    <?= rupiah($row['Harga']) ?>
                                </div>

                                <span class="produk-stok">
                                    Stok: <?= (int)$row['Stok'] ?>
                                </span>

                                <div class="aksi">

                                    <a
                                        href="produk.php?edit=<?= $row['ProdukID'] ?>"
                                        class="edit"
                                    >
                                        ✎ Edit
                                    </a>

                                    <a
                                        href="produk.php?hapus=<?= $row['ProdukID'] ?>"
                                        class="hapus"
                                        onclick="return confirm('Yakin ingin menghapus produk ini?')"
                                    >
                                        × Hapus
                                    </a>

                                </div>

                            </div>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="empty">
                        Belum ada produk.
                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

</body>

</html>

