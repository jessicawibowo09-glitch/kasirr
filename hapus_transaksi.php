<?php

session_start();
include 'koneksi.php';

if (!isset($_SESSION['AdminID']) || $_SESSION['Level'] !== 'Admin') {
    header("Location: admin_login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: admin_transaksi.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT
        PenjualanID,
        TransaksiID
    FROM penjualan
    WHERE PenjualanID = ?
    LIMIT 1
");

if (!$stmt) {
    die("Query gagal: " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>
        alert('Data transaksi tidak ditemukan.');
        window.location.href='admin_transaksi.php';
    </script>";
    exit;
}

$data = $result->fetch_assoc();

$transaksiID = $data['TransaksiID'];

$conn->begin_transaction();

try {

    $stmtDetail = $conn->prepare("
        DELETE FROM detailpenjualan
        WHERE PenjualanID = ?
    ");

    if (!$stmtDetail) {
        throw new Exception($conn->error);
    }

    $stmtDetail->bind_param("i", $id);

    if (!$stmtDetail->execute()) {
        throw new Exception($stmtDetail->error);
    }

    $stmtPenjualan = $conn->prepare("
        DELETE FROM penjualan
        WHERE PenjualanID = ?
    ");

    if (!$stmtPenjualan) {
        throw new Exception($conn->error);
    }

    $stmtPenjualan->bind_param("i", $id);

    if (!$stmtPenjualan->execute()) {
        throw new Exception($stmtPenjualan->error);
    }

    if (!empty($transaksiID)) {

        $stmtTransaksi = $conn->prepare("
            DELETE FROM transaksi
            WHERE TransaksiID = ?
        ");

        if (!$stmtTransaksi) {
            throw new Exception($conn->error);
        }

        $stmtTransaksi->bind_param("i", $transaksiID);

        if (!$stmtTransaksi->execute()) {
            throw new Exception($stmtTransaksi->error);
        }
    }

    $conn->commit();

    echo "<script>
        alert('Transaksi berhasil dihapus.');
        window.location.href='admin_transaksi.php';
    </script>";

    exit;

} catch (Exception $e) {

    $conn->rollback();

    echo "<div style='font-family:Arial;padding:30px'>";
    echo "<h2>Transaksi gagal dihapus</h2>";
    echo "<p><b>Pesan MySQL:</b></p>";
    echo "<p style='color:red'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<br>";
    echo "<a href='admin_transaksi.php'>← Kembali ke Data Transaksi</a>";
    echo "</div>";

    exit;
}
?>

