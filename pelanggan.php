<?php

session_start();
include 'koneksi.php';

if (!isset($_SESSION['AdminID']) || $_SESSION['Level'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

$pesan = "";
$error = "";
$dataEdit = null;
$adminID = (int)$_SESSION['AdminID'];

if (isset($_POST['simpan'])) {

    $nama = trim($_POST['NamaPelanggan'] ?? '');
    $nomor = trim($_POST['NomorTelepon'] ?? '');
    $statusMember = $_POST['StatusMember'] ?? 'Non-Member';
    $alamat = trim($_POST['Alamat'] ?? '');

    if ($nama === '' || $nomor === '' || $alamat === '') {

        $error = "Semua data wajib diisi.";

    } elseif (!preg_match('/^[0-9]{10,13}$/', $nomor)) {

        $error = "Nomor telepon harus 10–13 digit.";

    } elseif (!in_array($statusMember, ['Member', 'Non-Member'])) {

        $error = "Status member tidak valid.";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO pelanggan
            (NamaPelanggan, NomorTelepon, StatusMember, Alamat)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssss",
            $nama,
            $nomor,
            $statusMember,
            $alamat
        );

        if ($stmt->execute()) {
            $pesan = "Pelanggan berhasil ditambahkan.";
        } else {
            $error = "Pelanggan gagal ditambahkan.";
        }

        $stmt->close();
    }
}

if (isset($_POST['update'])) {

    $id = (int)($_POST['PelangganID'] ?? 0);
    $nama = trim($_POST['NamaPelanggan'] ?? '');
    $nomor = trim($_POST['NomorTelepon'] ?? '');
    $statusMember = $_POST['StatusMember'] ?? 'Non-Member';
    $alamat = trim($_POST['Alamat'] ?? '');

    if ($id <= 0 || $nama === '' || $nomor === '' || $alamat === '') {

        $error = "Semua data wajib diisi.";

    } elseif (!preg_match('/^[0-9]{10,13}$/', $nomor)) {

        $error = "Nomor telepon harus 10–13 digit.";

    } elseif (!in_array($statusMember, ['Member', 'Non-Member'])) {

        $error = "Status member tidak valid.";

    } else {

        $cekLama = $conn->prepare("
            SELECT
                NamaPelanggan,
                NomorTelepon,
                StatusMember,
                Alamat
            FROM pelanggan
            WHERE PelangganID = ?
        ");

        $cekLama->bind_param("i", $id);
        $cekLama->execute();

        $hasilLama = $cekLama->get_result()->fetch_assoc();

        $cekLama->close();

        if (!$hasilLama) {

            $error = "Data pelanggan tidak ditemukan.";

        } else {

            $stmt = $conn->prepare("
                UPDATE pelanggan
                SET
                    NamaPelanggan = ?,
                    NomorTelepon = ?,
                    StatusMember = ?,
                    Alamat = ?
                WHERE PelangganID = ?
            ");

            $stmt->bind_param(
                "ssssi",
                $nama,
                $nomor,
                $statusMember,
                $alamat,
                $id
            );

            if ($stmt->execute()) {

                $dataLama =
                    "Nama Pelanggan: " . $hasilLama['NamaPelanggan'] . "\n" .
                    "Nomor Telepon: " . $hasilLama['NomorTelepon'] . "\n" .
                    "Status Member: " . $hasilLama['StatusMember'] . "\n" .
                    "Alamat: " . $hasilLama['Alamat'];

                $dataBaru =
                    "Nama Pelanggan: " . $nama . "\n" .
                    "Nomor Telepon: " . $nomor . "\n" .
                    "Status Member: " . $statusMember . "\n" .
                    "Alamat: " . $alamat;

                if ($hasilLama['StatusMember'] !== $statusMember) {
                    $jenisPerubahan = "Perubahan Status Member";
                } else {
                    $jenisPerubahan = "Edit Pelanggan";
                }

                $log = $conn->prepare("
                    INSERT INTO log_pelanggan
                    (
                        PelangganID,
                        AdminID,
                        JenisPerubahan,
                        DataLama,
                        DataBaru
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                $log->bind_param(
                    "iisss",
                    $id,
                    $adminID,
                    $jenisPerubahan,
                    $dataLama,
                    $dataBaru
                );

                $log->execute();
                $log->close();

                $pesan = "Data pelanggan berhasil diperbarui.";

            } else {

                $error = "Data pelanggan gagal diperbarui.";
            }

            $stmt->close();
        }
    }
}

if (isset($_POST['hapus'])) {

    $id = (int)($_POST['PelangganID'] ?? 0);

    if ($id > 0) {

        $cek = $conn->prepare("
            SELECT COUNT(*) AS jumlah
            FROM penjualan
            WHERE PelangganID = ?
        ");

        $cek->bind_param("i", $id);
        $cek->execute();

        $hasil = $cek->get_result()->fetch_assoc();

        $cek->close();

        if ($hasil['jumlah'] > 0) {

            $error = "Pelanggan tidak bisa dihapus karena sudah memiliki transaksi.";

        } else {

            $stmt = $conn->prepare("
                DELETE FROM pelanggan
                WHERE PelangganID = ?
            ");

            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $pesan = "Pelanggan berhasil dihapus.";
            } else {
                $error = "Pelanggan gagal dihapus.";
            }

            $stmt->close();
        }
    }
}

if (isset($_GET['edit'])) {

    $idEdit = (int)$_GET['edit'];

    $stmt = $conn->prepare("
        SELECT
            PelangganID,
            NamaPelanggan,
            NomorTelepon,
            StatusMember,
            Alamat
        FROM pelanggan
        WHERE PelangganID = ?
    ");

    $stmt->bind_param("i", $idEdit);
    $stmt->execute();

    $dataEdit = $stmt->get_result()->fetch_assoc();

    $stmt->close();
}

$keyword = trim($_GET['keyword'] ?? '');

if ($keyword !== '') {

    $cari = "%" . $keyword . "%";

    $stmt = $conn->prepare("
        SELECT
            PelangganID,
            NamaPelanggan,
            NomorTelepon,
            StatusMember,
            Alamat
        FROM pelanggan
        WHERE
            NamaPelanggan LIKE ?
            OR NomorTelepon LIKE ?
            OR StatusMember LIKE ?
            OR Alamat LIKE ?
        ORDER BY PelangganID DESC
    ");

    $stmt->bind_param(
        "ssss",
        $cari,
        $cari,
        $cari,
        $cari
    );

    $stmt->execute();

    $pelanggan = $stmt->get_result();

    $stmt->close();

} else {

    $pelanggan = $conn->query("
        SELECT
            PelangganID,
            NamaPelanggan,
            NomorTelepon,
            StatusMember,
            Alamat
        FROM pelanggan
        ORDER BY PelangganID DESC
    ");
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Data Pelanggan - Jessie Bites</title>

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

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 13px;
    border: 1px solid #ead4dd;
    border-radius: 11px;
    outline: none;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    background: white;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
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
    text-decoration: none;
    display: inline-block;
    text-align: center;
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

.btn-edit {
    background: #fff0c9;
    color: #9b681d;
    width: auto;
    padding: 8px 12px;
    font-size: 11px;
}

.btn-hapus {
    background: #ffe0e5;
    color: #b52f49;
    width: auto;
    padding: 8px 12px;
    font-size: 11px;
}

.btn-edit:hover {
    background: #ffe7a8;
}

.btn-hapus:hover {
    background: #ffd1d9;
}

.search {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search input {
    flex: 1;
    padding: 12px 13px;
    border: 1px solid #ead4dd;
    border-radius: 11px;
    outline: none;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
}

.search input:focus {
    border-color: #ff72b4;
    box-shadow: 0 0 0 3px rgba(255, 114, 180, 0.1);
}

.search .btn {
    width: auto;
    padding: 12px 20px;
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
    color: #56352b;
    padding: 13px 10px;
    font-size: 12px;
    text-align: left;
}

td {
    padding: 13px 10px;
    border-bottom: 1px solid #f3e3e9;
    font-size: 12px;
    vertical-align: middle;
}

tr:hover td {
    background: #fffafd;
}

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.member {
    background: #eaf8ef;
    color: #287044;
}

.non-member {
    background: #f1ecee;
    color: #75666b;
}

.action {
    display: flex;
    gap: 6px;
}

.action form {
    margin: 0;
}

.empty {
    text-align: center;
    color: #a58d94;
    padding: 30px;
}

@media (max-width: 1100px) {

    .layout {
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

    .search {
        flex-direction: column;
    }

    .search .btn {
        width: 100%;
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

        <a href="produk.php">
            ▤ <span>Data Produk</span>
        </a>

        <a href="pelanggan.php" class="active">
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

        <h1>Data Pelanggan</h1>

        <p>
            Kelola pelanggan dan status member Jessie Bites.
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
                <?= $dataEdit ? "Edit Pelanggan" : "Tambah Pelanggan" ?>
            </h2>

            <form method="POST">

                <?php if ($dataEdit): ?>

                    <input
                        type="hidden"
                        name="PelangganID"
                        value="<?= $dataEdit['PelangganID'] ?>"
                    >

                <?php endif; ?>

                <div class="form-group">

                    <label>Nama Pelanggan</label>

                    <input
                        type="text"
                        name="NamaPelanggan"
                        maxlength="100"
                        placeholder="Contoh: Jessica"
                        required
                        value="<?= htmlspecialchars($dataEdit['NamaPelanggan'] ?? '') ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Nomor Telepon</label>

                    <input
                        type="text"
                        name="NomorTelepon"
                        maxlength="13"
                        minlength="10"
                        pattern="[0-9]{10,13}"
                        placeholder="Contoh: 081234567890"
                        required
                        value="<?= htmlspecialchars($dataEdit['NomorTelepon'] ?? '') ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Status Member</label>

                    <select name="StatusMember" required>

                        <option
                            value="Non-Member"
                            <?= ($dataEdit['StatusMember'] ?? 'Non-Member') === 'Non-Member' ? 'selected' : '' ?>
                        >
                            Non-Member
                        </option>

                        <option
                            value="Member"
                            <?= ($dataEdit['StatusMember'] ?? '') === 'Member' ? 'selected' : '' ?>
                        >
                            Member
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label>Alamat</label>

                    <textarea
                        name="Alamat"
                        placeholder="Masukkan alamat pelanggan"
                        required
                    ><?= htmlspecialchars($dataEdit['Alamat'] ?? '') ?></textarea>

                </div>

                <button
                    type="submit"
                    name="<?= $dataEdit ? 'update' : 'simpan' ?>"
                    class="btn"
                >
                    <?= $dataEdit ? "Simpan Perubahan" : "Tambah Pelanggan" ?>
                </button>

                <?php if ($dataEdit): ?>

                    <a
                        href="pelanggan.php"
                        class="btn-cancel"
                    >
                        Batal Edit
                    </a>

                <?php endif; ?>

            </form>

        </div>

        <div class="card">

            <h2>👥 Daftar Pelanggan</h2>

            <form method="GET" class="search">

                <input
                    type="text"
                    name="keyword"
                    placeholder="Cari nama, nomor, status member..."
                    value="<?= htmlspecialchars($keyword) ?>"
                >

                <button
                    type="submit"
                    class="btn"
                >
                    Cari
                </button>

                <?php if ($keyword !== ''): ?>

                    <a
                        href="pelanggan.php"
                        class="btn-cancel"
                        style="margin-top:0; padding:12px 20px;"
                    >
                        Reset
                    </a>

                <?php endif; ?>

            </form>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>No</th>
                            <th>Nama Pelanggan</th>
                            <th>Nomor Telepon</th>
                            <th>Status Member</th>
                            <th>Alamat</th>
                            <th>Aksi</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($pelanggan && $pelanggan->num_rows > 0): ?>

                        <?php $no = 1; ?>

                        <?php while ($row = $pelanggan->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?= $no++ ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['NamaPelanggan']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['NomorTelepon']) ?>
                                </td>

                                <td>

                                    <?php if ($row['StatusMember'] === 'Member'): ?>

                                        <span class="badge member">
                                            ⭐ Member
                                        </span>

                                    <?php else: ?>

                                        <span class="badge non-member">
                                            Non-Member
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars($row['Alamat']) ?>
                                </td>

                                <td>

                                    <div class="action">

                                        <a
                                            href="pelanggan.php?edit=<?= $row['PelangganID'] ?>"
                                            class="btn btn-edit"
                                        >
                                            ✎ Edit
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus pelanggan ini?')"
                                        >

                                            <input
                                                type="hidden"
                                                name="PelangganID"
                                                value="<?= $row['PelangganID'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="hapus"
                                                class="btn btn-hapus"
                                            >
                                                × Hapus
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                class="empty"
                            >
                                Belum ada data pelanggan.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>

