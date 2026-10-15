<?php

include 'koneksi.php';

$pesan = "";
$error = "";
$struk = null;
$diskonMember = 10;

function rupiah($angka)
{
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

if (isset($_POST['tambah_pelanggan'])) {

    $namaPelanggan = trim($_POST['NamaPelanggan'] ?? '');
    $nomorTelepon = trim($_POST['NomorTelepon'] ?? '');
    $alamat = trim($_POST['Alamat'] ?? '');
    $statusMember = $_POST['StatusMember'] ?? 'Non-Member';

    if ($namaPelanggan === '') {
        $error = "Nama pelanggan wajib diisi.";
    } elseif ($nomorTelepon === '') {
        $error = "Nomor telepon wajib diisi.";
    } elseif (!preg_match('/^[0-9]{10,13}$/', $nomorTelepon)) {
        $error = "Nomor telepon harus 10 sampai 13 digit.";
    } elseif ($alamat === '') {
        $error = "Alamat wajib diisi.";
    } elseif (!in_array($statusMember, ['Member', 'Non-Member'])) {
        $error = "Status member tidak valid.";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO pelanggan
            (NamaPelanggan, NomorTelepon, Alamat, StatusMember)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssss",
            $namaPelanggan,
            $nomorTelepon,
            $alamat,
            $statusMember
        );

        if ($stmt->execute()) {
            $pesan = "Pelanggan berhasil ditambahkan.";
        } else {
            $error = "Pelanggan gagal ditambahkan.";
        }

        $stmt->close();
    }
}

if (isset($_POST['simpan_transaksi'])) {

    $PelangganID = (int)($_POST['PelangganID'] ?? 0);
    $MetodePembayaran = trim($_POST['MetodePembayaran'] ?? '');
    $StatusTransaksi = trim($_POST['StatusTransaksi'] ?? 'Selesai');
    $UangBayar = (float)($_POST['UangBayar'] ?? 0);
    $ProdukID = $_POST['ProdukID'] ?? [];
    $JumlahProduk = $_POST['JumlahProduk'] ?? [];

    if ($PelangganID <= 0) {

        $error = "Silakan pilih pelanggan.";

    } elseif ($MetodePembayaran === '') {

        $error = "Silakan pilih metode pembayaran.";

    } elseif (empty($ProdukID)) {

        $error = "Silakan pilih minimal satu produk.";

    } else {

        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare("
                SELECT NamaPelanggan, NomorTelepon, StatusMember
                FROM pelanggan
                WHERE PelangganID = ?
            ");

            $stmt->bind_param("i", $PelangganID);
            $stmt->execute();

            $pelangganData = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$pelangganData) {
                throw new Exception("Data pelanggan tidak ditemukan.");
            }

            $statusMember = $pelangganData['StatusMember'];

            $totalSebelumDiskon = 0;
            $detail = [];

            for ($i = 0; $i < count($ProdukID); $i++) {

                $idProduk = (int)$ProdukID[$i];
                $jumlah = (int)($JumlahProduk[$i] ?? 0);

                if ($idProduk <= 0 || $jumlah <= 0) {
                    throw new Exception("Data produk tidak valid.");
                }

                $stmt = $conn->prepare("
                    SELECT NamaProduk, Harga, Stok
                    FROM produk
                    WHERE ProdukID = ?
                    FOR UPDATE
                ");

                $stmt->bind_param("i", $idProduk);
                $stmt->execute();

                $produkData = $stmt->get_result()->fetch_assoc();

                $stmt->close();

                if (!$produkData) {
                    throw new Exception("Produk tidak ditemukan.");
                }

                if ((int)$produkData['Stok'] < $jumlah) {
                    throw new Exception(
                        "Stok {$produkData['NamaProduk']} tidak mencukupi."
                    );
                }

                $subtotal = (float)$produkData['Harga'] * $jumlah;

                $totalSebelumDiskon += $subtotal;

                $detail[] = [
                    'ProdukID' => $idProduk,
                    'NamaProduk' => $produkData['NamaProduk'],
                    'Harga' => $produkData['Harga'],
                    'JumlahProduk' => $jumlah,
                    'Subtotal' => $subtotal
                ];
            }

            if ($statusMember === 'Member') {
                $nilaiDiskon = $totalSebelumDiskon * ($diskonMember / 100);
            } else {
                $nilaiDiskon = 0;
            }

            $totalHarga = $totalSebelumDiskon - $nilaiDiskon;

            if ($UangBayar < $totalHarga) {
                throw new Exception("Uang pembayaran kurang.");
            }

            $kembalian = $UangBayar - $totalHarga;

            $AdminID = 1;

            $stmt = $conn->prepare("
                INSERT INTO transaksi
                (
                    TanggalTransaksi,
                    PelangganID,
                    AdminID,
                    MetodePembayaran,
                    StatusTransaksi
                )
                VALUES (NOW(), ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iiss",
                $PelangganID,
                $AdminID,
                $MetodePembayaran,
                $StatusTransaksi
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Gagal menyimpan data transaksi."
                );
            }

            $TransaksiID = $conn->insert_id;

            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO penjualan
                (
                    TanggalPenjualan,
                    TotalHarga,
                    PelangganID,
                    TransaksiID,
                    AdminID
                )
                VALUES (NOW(), ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "diii",
                $totalHarga,
                $PelangganID,
                $TransaksiID,
                $AdminID
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    "Gagal menyimpan data penjualan."
                );
            }

            $PenjualanID = $conn->insert_id;

            $stmt->close();

            foreach ($detail as $item) {

                $stmt = $conn->prepare("
                    INSERT INTO detailpenjualan
                    (
                        PenjualanID,
                        ProdukID,
                        JumlahProduk,
                        Subtotal
                    )
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "iiid",
                    $PenjualanID,
                    $item['ProdukID'],
                    $item['JumlahProduk'],
                    $item['Subtotal']
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Gagal menyimpan detail transaksi."
                    );
                }

                $stmt->close();

                $stmt = $conn->prepare("
                    UPDATE produk
                    SET Stok = Stok - ?
                    WHERE ProdukID = ?
                ");

                $stmt->bind_param(
                    "ii",
                    $item['JumlahProduk'],
                    $item['ProdukID']
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Gagal mengurangi stok."
                    );
                }

                $stmt->close();
            }

            $conn->commit();

            $struk = [
                'PenjualanID' => $PenjualanID,
                'NamaPelanggan' => $pelangganData['NamaPelanggan'],
                'NomorTelepon' => $pelangganData['NomorTelepon'],
                'StatusMember' => $statusMember,
                'MetodePembayaran' => $MetodePembayaran,
                'StatusTransaksi' => $StatusTransaksi,
                'TotalSebelumDiskon' => $totalSebelumDiskon,
                'Diskon' => $nilaiDiskon,
                'PersenDiskon' => $statusMember === 'Member'
                    ? $diskonMember
                    : 0,
                'TotalHarga' => $totalHarga,
                'UangBayar' => $UangBayar,
                'Kembalian' => $kembalian,
                'Detail' => $detail
            ];

        } catch (Exception $e) {

            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$produk = $conn->query("
    SELECT ProdukID, NamaProduk, Harga, Stok
    FROM produk
    ORDER BY ProdukID ASC
");

$pelanggan = $conn->query("
    SELECT
        PelangganID,
        NamaPelanggan,
        StatusMember
    FROM pelanggan
    ORDER BY NamaPelanggan ASC
");

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0"
>

<title>Kasir Jessie Bites</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background:
        radial-gradient(
            circle at top left,
            rgba(255,255,255,.9),
            transparent 35%
        ),
        linear-gradient(
            135deg,
            #fff5f8,
            #fce7ed 55%,
            #f7ddd1
        );
    min-height: 100vh;
    color: #49352f;
}

button,
input,
select,
textarea {
    font-family: inherit;
}

.container {
    width: 94%;
    max-width: 1350px;
    margin: 28px auto 40px;
}

.header {
    position: relative;
    overflow: hidden;
    background: linear-gradient(
        135deg,
        #6d3925,
        #9f6042
    );
    color: white;
    padding: 27px 32px;
    border-radius: 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow:
        0 15px 40px rgba(91, 48, 30, .20);
    margin-bottom: 24px;
}

.header::after {
    content: "";
    position: absolute;
    width: 180px;
    height: 180px;
    right: -55px;
    top: -75px;
    background: rgba(255,255,255,.08);
    border-radius: 50%;
}

.brand {
    display: flex;
    align-items: center;
    gap: 16px;
    position: relative;
    z-index: 2;
}

.logo {
    width: 65px;
    height: 65px;
    background: #fff;
    border-radius: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 34px;
    box-shadow: 0 7px 18px rgba(0,0,0,.12);
}

.brand h1 {
    font-size: 29px;
}

.brand p {
    font-size: 13px;
    margin-top: 5px;
    opacity: .85;
}

.admin-btn {
    position: relative;
    z-index: 2;
    background: #fff;
    color: #743e29;
    text-decoration: none;
    padding: 12px 19px;
    border-radius: 13px;
    font-weight: 700;
    font-size: 13px;
}

.alert {
    padding: 15px 18px;
    border-radius: 15px;
    margin-bottom: 20px;
    font-size: 13px;
    font-weight: 700;
}

.alert-success {
    background: #eaf8ef;
    color: #34774a;
    border: 1px solid #c6e6d0;
}

.alert-error {
    background: #fff0f2;
    color: #b3475d;
    border: 1px solid #efc2ca;
}

.grid {
    display: grid;
    grid-template-columns: 1.65fr 1fr;
    gap: 22px;
    align-items: start;
}

.box {
    background: rgba(255,255,255,.95);
    border: 1px solid rgba(222,190,198,.55);
    border-radius: 24px;
    padding: 25px;
    box-shadow:
        0 12px 35px rgba(91,50,70,.08);
}

.box-title {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 20px;
}

.box-title span {
    width: 39px;
    height: 39px;
    background: #fff0f5;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.box-title h2 {
    color: #633d30;
    font-size: 19px;
}

.produk-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 17px;
}

.kartu-produk {
    border: 1px solid #f0dce3;
    background: #fffafb;
    border-radius: 19px;
    padding: 11px;
    transition: .25s;
    overflow: hidden;
}

.kartu-produk:hover {
    border-color: #d79bb0;
    transform: translateY(-4px);
    box-shadow:
        0 12px 25px rgba(151,76,105,.12);
}

.foto-produk {
    width: 100%;
    height: 170px;
    object-fit: cover;
    border-radius: 14px;
    display: block;
    background: #fff0f4;
}

.nama-produk {
    font-size: 16px;
    font-weight: 800;
    color: #613c30;
    margin-top: 11px;
}

.harga-produk {
    color: #a74e76;
    font-size: 14px;
    font-weight: 800;
    margin-top: 5px;
}

.stok-produk {
    color: #987a82;
    font-size: 12px;
    margin-top: 5px;
}

.pilih-produk {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 11px;
}

.pilih-produk input {
    width: 65px;
    padding: 9px 6px;
    border: 1px solid #ead6de;
    border-radius: 10px;
    text-align: center;
    background: white;
}

.btn-pilih {
    flex: 1;
    border: none;
    background:
        linear-gradient(135deg,#a94f76,#c96a91);
    color: white;
    padding: 10px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 800;
}

.btn-pilih:disabled {
    background: #cfc3c6;
    cursor: not-allowed;
}

.pesanan {
    margin-top: 24px;
    border-top: 2px dashed #efd6df;
    padding-top: 20px;
}

.pesanan-title {
    font-size: 15px;
    font-weight: 800;
    color: #633d30;
    margin-bottom: 12px;
}

.item-pesanan {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    background:
        linear-gradient(135deg,#fff5f8,#fffafb);
    border: 1px solid #f2dce4;
    padding: 12px;
    border-radius: 13px;
    margin-bottom: 8px;
}

.item-info {
    min-width: 0;
}

.item-info strong {
    color: #633d30;
    font-size: 13px;
}

.item-info small {
    display: block;
    color: #997783;
    margin-top: 4px;
}

.item-kanan {
    display: flex;
    align-items: center;
    gap: 5px;
}

.item-kanan span {
    color: #a74e76;
    font-weight: 800;
    font-size: 13px;
    margin-right: 5px;
}

.btn-kurang,
.btn-hapus {
    border: none;
    width: 29px;
    height: 29px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.btn-kurang {
    background: #f5dbe5;
    color: #914465;
}

.btn-hapus {
    background: #ffe0e7;
    color: #bd526e;
}

.empty {
    text-align: center;
    color: #b18b99;
    font-size: 13px;
    padding: 18px;
    background: #fff9fb;
    border-radius: 13px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 800;
    color: #76515d;
    margin-bottom: 7px;
}

select,
input,
textarea {
    width: 100%;
    border: 1px solid #ead6de;
    background: #fffafd;
    padding: 12px 13px;
    border-radius: 11px;
    outline: none;
    font-size: 13px;
}

textarea {
    resize: vertical;
}

.pelanggan-pilihan {
    display: flex;
    gap: 8px;
}

.pelanggan-pilihan select {
    flex: 1;
}

.btn-tambah-pelanggan {
    border: none;
    background: #ff72b4;
    color: white;
    padding: 0 16px;
    border-radius: 11px;
    font-weight: 800;
    cursor: pointer;
}

.member-info {
    display: none;
    margin-top: 9px;
    padding: 10px 12px;
    border-radius: 11px;
    background: #fff2f7;
    border: 1px solid #f2d3e0;
    color: #a74e76;
    font-size: 12px;
    font-weight: 700;
}

.summary {
    background:
        linear-gradient(135deg,#fff5f8,#fff9fb);
    border: 1px solid #f0dce3;
    padding: 19px;
    border-radius: 17px;
    margin-top: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.summary-row span:first-child {
    font-size: 14px;
    font-weight: 800;
    color: #744b5a;
}

.summary-row:last-child {
    margin-bottom: 0;
}

.total {
    font-size: 25px;
    color: #a74e76;
    font-weight: 900;
}

.diskon {
    color: #4d9864;
    font-weight: 800;
}

.kembalian-box {
    margin-top: 13px;
    padding: 14px;
    background: #fff;
    border-radius: 13px;
    border: 1px solid #f1dce4;
}

.kembalian-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    color: #76515d;
    font-size: 13px;
}

.kembalian-row:last-child {
    margin-bottom: 0;
    font-weight: 800;
    color: #a74e76;
    font-size: 16px;
}

.simpan {
    width: 100%;
    border: none;
    background:
        linear-gradient(135deg,#a94f76,#cb6d94);
    color: white;
    padding: 15px;
    border-radius: 13px;
    font-weight: 800;
    font-size: 14px;
    cursor: pointer;
    margin-top: 16px;
}

.reset {
    width: 100%;
    border: 1px solid #e4b8c9;
    background: white;
    color: #a74e76;
    padding: 13px;
    border-radius: 13px;
    font-weight: 800;
    cursor: pointer;
    margin-top: 10px;
}

.info {
    background: #fff8e9;
    border: 1px solid #f2dfb7;
    padding: 14px;
    border-radius: 13px;
    color: #8d6b35;
    font-size: 12px;
    line-height: 1.7;
    margin-top: 15px;
}

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(57,35,29,.55);
    justify-content: center;
    align-items: center;
    z-index: 999;
    padding: 20px;
    backdrop-filter: blur(4px);
}

.modal-box {
    background: white;
    width: 100%;
    max-width: 450px;
    border-radius: 24px;
    padding: 25px;
    box-shadow: 0 25px 60px rgba(0,0,0,.2);
}

.modal-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-title h2 {
    color: #653f31;
    font-size: 20px;
}

.btn-close {
    border: none;
    background: #ffe3ed;
    color: #a74e76;
    width: 33px;
    height: 33px;
    border-radius: 50%;
    cursor: pointer;
    font-weight: bold;
    font-size: 18px;
}

.btn-simpan-pelanggan {
    width: 100%;
    border: none;
    background:
        linear-gradient(135deg,#a94f76,#c96991);
    color: white;
    padding: 13px;
    border-radius: 12px;
    font-weight: 800;
    cursor: pointer;
}

.struk-area {
    max-width: 520px;
    margin: 25px auto;
}

.struk {
    background: white;
    border-radius: 25px;
    padding: 32px;
    box-shadow:
        0 15px 40px rgba(100,50,80,.12);
}

.struk-header {
    text-align: center;
    border-bottom: 2px dashed #e6c6d3;
    padding-bottom: 19px;
    margin-bottom: 19px;
}

.struk-logo {
    font-size: 43px;
}

.struk-header h1 {
    color: #713d27;
    margin-top: 5px;
}

.struk-header p {
    color: #997783;
    font-size: 12px;
    margin-top: 5px;
}

.struk-info {
    background: #fff8fa;
    border-radius: 13px;
    padding: 13px;
    font-size: 13px;
    line-height: 1.9;
    margin-bottom: 15px;
}

.member-badge {
    display: inline-block;
    background: #ffe4ef;
    color: #a74e76;
    padding: 4px 8px;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 800;
}

.struk-item {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    border-bottom: 1px dashed #ead6de;
    padding: 11px 0;
    font-size: 13px;
}

.struk-item small {
    display: block;
    color: #997783;
    margin-top: 3px;
}

.struk-total {
    margin-top: 17px;
    background: #fff4f8;
    padding: 16px;
    border-radius: 14px;
}

.struk-total div {
    display: flex;
    justify-content: space-between;
    margin-bottom: 9px;
    font-size: 13px;
}

.struk-total .diskon-struk {
    color: #4d9864;
    font-weight: 800;
}

.struk-total .total-akhir {
    margin-bottom: 0;
    font-weight: 800;
    color: #a74e76;
    font-size: 17px;
}

.struk-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.struk-actions a,
.struk-actions button {
    flex: 1;
    text-align: center;
    text-decoration: none;
    border: none;
    padding: 13px;
    border-radius: 12px;
    font-weight: 800;
    cursor: pointer;
}

.btn-transaksi-baru {
    background: #a94f76;
    color: white;
}

.btn-print {
    background: white;
    border: 1px solid #d9afc0 !important;
    color: #a74e76;
}

.footer {
    text-align: center;
    margin-top: 25px;
    color: #a47e8d;
    font-size: 12px;
}

@media (max-width: 950px) {

    .grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 650px) {

    .container {
        width: 94%;
        margin-top: 15px;
    }

    .header {
        flex-direction: column;
        gap: 18px;
        text-align: center;
        padding: 23px;
    }

    .brand {
        flex-direction: column;
    }

    .produk-grid {
        grid-template-columns: 1fr;
    }

    .pelanggan-pilihan {
        flex-direction: column;
    }

    .btn-tambah-pelanggan {
        padding: 12px;
    }

    .item-pesanan {
        align-items: flex-start;
        flex-direction: column;
    }

    .item-kanan {
        width: 100%;
        justify-content: flex-end;
    }

    .struk {
        padding: 23px;
    }

    .struk-actions {
        flex-direction: column;
    }

}

@media print {

    body {
        background: white;
    }

    .header,
    .footer,
    .struk-actions {
        display: none !important;
    }

    .container {
        width: 100%;
        margin: 0;
    }

    .struk-area {
        margin: 0;
        max-width: none;
    }

    .struk {
        box-shadow: none;
        border-radius: 0;
    }

}

</style>

</head>

<body>

<div class="container">

<?php if ($struk): ?>

<div class="struk-area">

<div class="struk">

<div class="struk-header">

<div class="struk-logo">🍫</div>

<h1>Jessie Bites</h1>

<p>Brownies kecil, rasa bikin nagih ♡</p>

</div>

<div class="struk-info">

<strong>No. Transaksi:</strong>
#<?= $struk['PenjualanID']; ?>

<br>

<strong>Pelanggan:</strong>
<?= htmlspecialchars($struk['NamaPelanggan']); ?>

<br>

<strong>No. Telepon:</strong>
<?= htmlspecialchars($struk['NomorTelepon']); ?>

<br>

<strong>Status Pelanggan:</strong>

<?php if ($struk['StatusMember'] === 'Member'): ?>

<span class="member-badge">
⭐ MEMBER
</span>

<?php else: ?>

Non-Member

<?php endif; ?>

<br>

<strong>Pembayaran:</strong>
<?= htmlspecialchars($struk['MetodePembayaran']); ?>

<br>

<strong>Status:</strong>
<?= htmlspecialchars($struk['StatusTransaksi']); ?>

</div>

<?php foreach ($struk['Detail'] as $item): ?>

<div class="struk-item">

<div>

<strong>
<?= htmlspecialchars($item['NamaProduk']); ?>
</strong>

<small>
<?= $item['JumlahProduk']; ?>
×
<?= rupiah($item['Harga']); ?>
</small>

</div>

<strong>
<?= rupiah($item['Subtotal']); ?>
</strong>

</div>

<?php endforeach; ?>

<div class="struk-total">

<div>

<span>Subtotal</span>

<strong>
<?= rupiah($struk['TotalSebelumDiskon']); ?>
</strong>

</div>

<div class="diskon-struk">

<span>

Diskon Member

<?php if ($struk['PersenDiskon'] > 0): ?>

(<?= $struk['PersenDiskon']; ?>%)

<?php endif; ?>

</span>

<strong>
- <?= rupiah($struk['Diskon']); ?>
</strong>

</div>

<div class="total-akhir">

<span>Total Bayar</span>

<strong>
<?= rupiah($struk['TotalHarga']); ?>
</strong>

</div>

<div>

<span>Uang Bayar</span>

<strong>
<?= rupiah($struk['UangBayar']); ?>
</strong>

</div>

<div>

<span>Kembalian</span>

<strong>
<?= rupiah($struk['Kembalian']); ?>
</strong>

</div>

</div>

<div class="struk-actions">

<a
href="index.php"
class="btn-transaksi-baru"
>
＋ Transaksi Baru
</a>

<button
onclick="window.print()"
class="btn-print"
>
🖨️ Cetak Struk
</button>

</div>

</div>

</div>

<?php else: ?>

<div class="header">

<div class="brand">

<div class="logo">
🍫
</div>

<div>

<h1>Jessie Bites</h1>

<p>
Sistem Kasir Brownies • Manis, simpel, dan praktis ♡
</p>

</div>

</div>

<a
href="admin_login.php"
class="admin-btn"
>
🔐 Login Admin
</a>

</div>

<?php if ($pesan): ?>

<div class="alert alert-success">
✅ <?= htmlspecialchars($pesan); ?>
</div>

<?php endif; ?>

<?php if ($error): ?>

<div class="alert alert-error">
⚠️ <?= htmlspecialchars($error); ?>
</div>

<?php endif; ?>

<form method="POST" id="formKasir">

<div class="grid">

<div class="box">

<div class="box-title">

<span>🍫</span>

<h2>Pilih Brownies</h2>

</div>

<div class="produk-grid">

<?php

$produk->data_seek(0);

while ($p = $produk->fetch_assoc()):

$nama = strtolower($p['NamaProduk']);

if (strpos($nama, 'red velvet') !== false) {

    $gambar = 'assets/img/brownies red velvet.jpeg';

} elseif (strpos($nama, 'keju') !== false) {

    $gambar = 'assets/img/brownies keju.jpeg';

} elseif (strpos($nama, 'oreo') !== false) {

    $gambar = 'assets/img/brownies oreo.jpeg';

} elseif (strpos($nama, 'double choco') !== false) {

    $gambar = 'assets/img/brownies double choco.jpeg';

} else {

    $gambar = 'assets/img/brownies coklat.jpeg';

}

?>

<div class="kartu-produk">

<img
src="<?= htmlspecialchars($gambar); ?>"
class="foto-produk"
alt="<?= htmlspecialchars($p['NamaProduk']); ?>"
>

<div class="nama-produk">
<?= htmlspecialchars($p['NamaProduk']); ?>
</div>

<div class="harga-produk">
<?= rupiah($p['Harga']); ?>
</div>

<div class="stok-produk">
📦 Stok tersedia: <?= $p['Stok']; ?>
</div>

<div class="pilih-produk">

<input
type="number"
id="jumlah-<?= $p['ProdukID']; ?>"
min="1"
max="<?= $p['Stok']; ?>"
value="1"
<?= $p['Stok'] <= 0 ? 'disabled' : ''; ?>
>

<button
type="button"
class="btn-pilih"
<?= $p['Stok'] <= 0 ? 'disabled' : ''; ?>
onclick="tambahPesanan(
<?= $p['ProdukID']; ?>,
'<?= htmlspecialchars(
$p['NamaProduk'],
ENT_QUOTES
); ?>',
<?= $p['Harga']; ?>,
<?= $p['Stok']; ?>
)"
>

<?= $p['Stok'] <= 0 ? 'Habis' : '+ Pilih'; ?>

</button>

</div>

</div>

<?php endwhile; ?>

</div>

<div class="pesanan">

<div class="pesanan-title">
🛒 Pesanan Kamu
</div>

<div id="pesanan-container">

<div class="empty">
Belum ada brownies yang dipilih ♡
</div>

</div>

</div>

</div>

<div class="box">

<div class="box-title">

<span>🧾</span>

<h2>Detail Pembayaran</h2>

</div>

<div class="form-group">

<label>
👤 Nama Pelanggan
</label>

<div class="pelanggan-pilihan">

<select
name="PelangganID"
id="pelangganSelect"
required
onchange="cekMember()"
>

<option value="">
-- Pilih Pelanggan --
</option>

<?php while ($p = $pelanggan->fetch_assoc()): ?>

<option
value="<?= $p['PelangganID']; ?>"
data-member="<?= $p['StatusMember']; ?>"
>

<?= htmlspecialchars($p['NamaPelanggan']); ?>
-
<?= $p['StatusMember']; ?>

</option>

<?php endwhile; ?>

</select>

<button
type="button"
class="btn-tambah-pelanggan"
onclick="bukaModal()"
>
+ Tambah
</button>

</div>

<div
id="memberInfo"
class="member-info"
>
⭐ Pelanggan Member mendapatkan diskon 10%.
</div>

</div>

<div class="form-group">

<label>
💳 Metode Pembayaran
</label>

<select
name="MetodePembayaran"
id="metodePembayaran"
required
>

<option value="">
-- Pilih Pembayaran --
</option>

<option value="Cash">
💵 Cash
</option>

<option value="Transfer">
🏦 Transfer
</option>

<option value="QRIS">
📱 QRIS
</option>

<option value="Debit">
💳 Debit
</option>

</select>

</div>

<div class="form-group">

<label>
📌 Status Transaksi
</label>

<select
name="StatusTransaksi"
required
>

<option value="Selesai">
✅ Selesai
</option>

<option value="Diproses">
⏳ Diproses
</option>

</select>

</div>

<div class="form-group">

<label>
💵 Uang Bayar
</label>

<input
type="number"
name="UangBayar"
id="uangBayar"
min="0"
placeholder="Masukkan uang pembayaran"
required
oninput="hitungKembalian()"
>

</div>

<div class="summary">

<div class="summary-row">

<span>
Subtotal
</span>

<span id="subtotal">
Rp 0
</span>

</div>

<div class="summary-row">

<span>
⭐ Diskon Member
</span>

<span
class="diskon"
id="diskon"
>
Rp 0
</span>

</div>

<div class="summary-row">

<span>
Total Bayar
</span>

<span
class="total"
id="total"
>
Rp 0
</span>

</div>

<div class="kembalian-box">

<div class="kembalian-row">

<span>
Uang Bayar
</span>

<strong id="tampilanUang">
Rp 0
</strong>

</div>

<div class="kembalian-row">

<span>
Kembalian
</span>

<strong id="kembalian">
Rp 0
</strong>

</div>

</div>

</div>

<div id="input-pesanan"></div>

<button
type="submit"
name="simpan_transaksi"
class="simpan"
onclick="return cekTransaksi()"
>
💗 Simpan & Buat Struk
</button>

<button
type="button"
class="reset"
onclick="resetPesanan()"
>
🔄 Reset Pesanan
</button>

<div class="info">

🍫 <strong>Catatan Kasir</strong><br>

Pelanggan <strong>Member</strong> otomatis mendapatkan
<strong>diskon 10%</strong>.

Pilih brownies, pilih pelanggan, tentukan pembayaran,
masukkan uang bayar, lalu simpan transaksi.

</div>

</div>

</div>

</form>

<div class="footer">
♡ Jessie Bites — Brownies kecil, rasa bikin nagih ♡
</div>

<?php endif; ?>

</div>

<div
class="modal"
id="modalPelanggan"
onclick="tutupJikaKlikLuar(event)"
>

<div class="modal-box">

<div class="modal-title">

<h2>
👤 Tambah Pelanggan
</h2>

<button
type="button"
class="btn-close"
onclick="tutupModal()"
>
×
</button>

</div>

<form method="POST">

<div class="form-group">

<label>
Nama Pelanggan
</label>

<input
type="text"
name="NamaPelanggan"
placeholder="Masukkan nama pelanggan"
required
>

</div>

<div class="form-group">

<label>
Nomor Telepon
</label>

<input
type="text"
name="NomorTelepon"
placeholder="Contoh: 081234567890"
maxlength="13"
inputmode="numeric"
required
>

</div>

<div class="form-group">

<label>
Status Pelanggan
</label>

<select
name="StatusMember"
required
>

<option value="Non-Member">
Non-Member
</option>

<option value="Member">
⭐ Member
</option>

</select>

</div>

<div class="form-group">

<label>
Alamat
</label>

<textarea
name="Alamat"
rows="3"
placeholder="Masukkan alamat pelanggan"
required
></textarea>

</div>

<button
type="submit"
name="tambah_pelanggan"
class="btn-simpan-pelanggan"
>
💗 Simpan Pelanggan
</button>

</form>

</div>

</div>

<script>

let pesanan = {};
const DISKON_MEMBER = 10;

function formatRupiah(angka) {
    return "Rp " +
        Number(angka).toLocaleString("id-ID");
}

function tambahPesanan(id, nama, harga, stok) {

    const inputJumlah =
        document.getElementById(
            "jumlah-" + id
        );

    let jumlah =
        parseInt(inputJumlah.value) || 1;

    if (jumlah < 1) {
        jumlah = 1;
    }

    if (jumlah > stok) {
        jumlah = stok;
        inputJumlah.value = stok;
    }

    if (pesanan[id]) {

        pesanan[id].jumlah += jumlah;

        if (pesanan[id].jumlah > stok) {
            pesanan[id].jumlah = stok;
        }

    } else {

        pesanan[id] = {
            id: id,
            nama: nama,
            harga: harga,
            stok: stok,
            jumlah: jumlah
        };

    }

    tampilkanPesanan();
}

function kurangiPesanan(id) {

    if (!pesanan[id]) {
        return;
    }

    pesanan[id].jumlah--;

    if (pesanan[id].jumlah <= 0) {
        delete pesanan[id];
    }

    tampilkanPesanan();
}

function tambahSatuPesanan(id) {

    if (!pesanan[id]) {
        return;
    }

    if (
        pesanan[id].jumlah <
        pesanan[id].stok
    ) {
        pesanan[id].jumlah++;
    }

    tampilkanPesanan();
}

function hapusPesanan(id) {
    delete pesanan[id];
    tampilkanPesanan();
}

function dapatkanSubtotal() {

    let subtotal = 0;

    Object.values(pesanan).forEach(function(item) {

        subtotal +=
            item.harga * item.jumlah;

    });

    return subtotal;
}

function pelangganMember() {

    const select =
        document.getElementById(
            "pelangganSelect"
        );

    if (!select.value) {
        return false;
    }

    const option =
        select.options[select.selectedIndex];

    return option.dataset.member === "Member";
}

function hitungTotal() {

    const subtotal =
        dapatkanSubtotal();

    let diskon = 0;

    if (pelangganMember()) {

        diskon =
            subtotal *
            (DISKON_MEMBER / 100);

    }

    const total =
        subtotal - diskon;

    document.getElementById(
        "subtotal"
    ).innerText =
        formatRupiah(subtotal);

    document.getElementById(
        "diskon"
    ).innerText =
        diskon > 0
            ? "- " + formatRupiah(diskon)
            : formatRupiah(0);

    document.getElementById(
        "total"
    ).innerText =
        formatRupiah(total);

    hitungKembalian();
}

function cekMember() {

    const info =
        document.getElementById(
            "memberInfo"
        );

    if (pelangganMember()) {
        info.style.display = "block";
    } else {
        info.style.display = "none";
    }

    hitungTotal();
}

function tampilkanPesanan() {

    const container =
        document.getElementById(
            "pesanan-container"
        );

    const inputContainer =
        document.getElementById(
            "input-pesanan"
        );

    container.innerHTML = "";
    inputContainer.innerHTML = "";

    Object.values(pesanan).forEach(
        function(item) {

            const subtotal =
                item.harga *
                item.jumlah;

            const div =
                document.createElement("div");

            div.className =
                "item-pesanan";

            div.innerHTML = `
                <div class="item-info">
                    <strong>
                        ${item.nama}
                    </strong>

                    <small>
                        ${item.jumlah}
                        ×
                        ${formatRupiah(item.harga)}
                    </small>
                </div>

                <div class="item-kanan">

                    <span>
                        ${formatRupiah(subtotal)}
                    </span>

                    <button
                        type="button"
                        class="btn-kurang"
                        onclick="kurangiPesanan(${item.id})"
                    >
                        −
                    </button>

                    <button
                        type="button"
                        class="btn-kurang"
                        onclick="tambahSatuPesanan(${item.id})"
                    >
                        +
                    </button>

                    <button
                        type="button"
                        class="btn-hapus"
                        onclick="hapusPesanan(${item.id})"
                    >
                        ×
                    </button>

                </div>
            `;

            container.appendChild(div);

            const inputProduk =
                document.createElement("input");

            inputProduk.type = "hidden";
            inputProduk.name = "ProdukID[]";
            inputProduk.value = item.id;

            inputContainer.appendChild(
                inputProduk
            );

            const inputJumlah =
                document.createElement("input");

            inputJumlah.type = "hidden";
            inputJumlah.name = "JumlahProduk[]";
            inputJumlah.value = item.jumlah;

            inputContainer.appendChild(
                inputJumlah
            );
        }
    );

    if (
        Object.keys(pesanan).length === 0
    ) {

        container.innerHTML = `
            <div class="empty">
                Belum ada brownies yang dipilih ♡
            </div>
        `;

    }

    hitungTotal();
}

function hitungKembalian() {

    const subtotal =
        dapatkanSubtotal();

    let diskon = 0;

    if (pelangganMember()) {

        diskon =
            subtotal *
            (DISKON_MEMBER / 100);

    }

    const total =
        subtotal - diskon;

    const uang =
        parseInt(
            document.getElementById(
                "uangBayar"
            ).value
        ) || 0;

    let kembalian =
        uang - total;

    if (kembalian < 0) {
        kembalian = 0;
    }

    document.getElementById(
        "tampilanUang"
    ).innerText =
        formatRupiah(uang);

    document.getElementById(
        "kembalian"
    ).innerText =
        formatRupiah(kembalian);
}

function cekTransaksi() {

    if (
        Object.keys(pesanan).length === 0
    ) {

        alert(
            "🍫 Pilih minimal satu brownies dulu ya."
        );

        return false;
    }

    const pelanggan =
        document.getElementById(
            "pelangganSelect"
        ).value;

    if (!pelanggan) {

        alert(
            "👤 Silakan pilih pelanggan."
        );

        return false;
    }

    const metode =
        document.getElementById(
            "metodePembayaran"
        ).value;

    if (!metode) {

        alert(
            "💳 Silakan pilih metode pembayaran."
        );

        return false;
    }

    const subtotal =
        dapatkanSubtotal();

    let diskon = 0;

    if (pelangganMember()) {

        diskon =
            subtotal *
            (DISKON_MEMBER / 100);

    }

    const total =
        subtotal - diskon;

    const uang =
        parseInt(
            document.getElementById(
                "uangBayar"
            ).value
        ) || 0;

    if (uang < total) {

        alert(
            "💵 Uang pembayaran masih kurang.\n\n" +
            "Subtotal: " +
            formatRupiah(subtotal) +
            "\nDiskon: " +
            formatRupiah(diskon) +
            "\nTotal: " +
            formatRupiah(total) +
            "\nUang bayar: " +
            formatRupiah(uang)
        );

        return false;
    }

    let pesanKonfirmasi =
        "🍫 Simpan transaksi Jessie Bites?\n\n" +
        "Subtotal: " +
        formatRupiah(subtotal) +
        "\n";

    if (diskon > 0) {

        pesanKonfirmasi +=
            "Diskon Member: " +
            formatRupiah(diskon) +
            "\n";

    }

    pesanKonfirmasi +=
        "Total: " +
        formatRupiah(total) +
        "\nUang bayar: " +
        formatRupiah(uang) +
        "\nKembalian: " +
        formatRupiah(uang - total);

    return confirm(
        pesanKonfirmasi
    );
}

function resetPesanan() {

    if (
        Object.keys(pesanan).length === 0
    ) {
        return;
    }

    if (
        confirm(
            "🔄 Hapus semua pesanan?"
        )
    ) {

        pesanan = {};

        document.getElementById(
            "uangBayar"
        ).value = "";

        tampilkanPesanan();
    }
}

function bukaModal() {

    document.getElementById(
        "modalPelanggan"
    ).style.display = "flex";
}

function tutupModal() {

    document.getElementById(
        "modalPelanggan"
    ).style.display = "none";
}

function tutupJikaKlikLuar(event) {

    if (
        event.target.id ===
        "modalPelanggan"
    ) {
        tutupModal();
    }
}

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {
            tutupModal();
        }

    }
);

tampilkanPesanan();

</script>

</body>

</html>

