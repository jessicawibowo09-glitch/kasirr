<?php

session_start();
include 'koneksi.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {

        $error = "Username dan password wajib diisi.";

    } else {

        $stmt = $conn->prepare("
            SELECT AdminID, NamaAdmin, Username, Password, Level
            FROM admin
            WHERE Username = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param("s", $username);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {

                $admin = $result->fetch_assoc();

                if ($admin['Password'] === md5($password)) {

                    if ($admin['Level'] === 'Admin') {

                        $_SESSION['AdminID'] = $admin['AdminID'];
                        $_SESSION['NamaAdmin'] = $admin['NamaAdmin'];
                        $_SESSION['Username'] = $admin['Username'];
                        $_SESSION['Level'] = $admin['Level'];

                        header("Location: admin_dashboard.php");
                        exit;

                    } else {

                        $error = "Akun ini bukan Admin.";

                    }

                } else {

                    $error = "Password salah.";

                }

            } else {

                $error = "Username tidak ditemukan.";

            }

            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<title>Login Admin - Jessie Bites</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #fff1f6, #f5d5c5);
    display: flex;
    justify-content: center;
    align-items: center;
}

.login-box {
    width: 400px;
    max-width: 90%;
    background: white;
    padding: 35px;
    border-radius: 25px;
    box-shadow: 0 15px 40px rgba(100, 50, 30, 0.15);
}

.logo {
    width: 75px;
    height: 75px;
    background: #713d27;
    border-radius: 50%;
    margin: auto;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 38px;
}

h1 {
    text-align: center;
    color: #713d27;
    margin-top: 15px;
}

.subtitle {
    text-align: center;
    color: #98796e;
    font-size: 13px;
    margin: 7px 0 25px;
}

label {
    display: block;
    color: #65483e;
    font-weight: bold;
    font-size: 13px;
    margin-bottom: 7px;
}

input {
    width: 100%;
    padding: 13px;
    border: 1px solid #ead8d1;
    border-radius: 12px;
    margin-bottom: 17px;
    outline: none;
}

input:focus {
    border-color: #b86848;
}

.error {
    background: #fff0f2;
    color: #bd526e;
    padding: 12px;
    border-radius: 10px;
    text-align: center;
    font-size: 13px;
    margin-bottom: 18px;
}

button {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, #713d27, #b86848);
    color: white;
    font-weight: bold;
    font-size: 15px;
    cursor: pointer;
}

button:hover {
    opacity: .9;
}

.back {
    display: block;
    text-align: center;
    margin-top: 18px;
    text-decoration: none;
    color: #9b604a;
    font-size: 13px;
}

</style>

</head>

<body>

<div class="login-box">

    <div class="logo">
        🔐
    </div>

    <h1>Login Admin</h1>

    <p class="subtitle">
        Jessie Bites • Sistem Kasir Brownies
    </p>

    <?php if ($error !== ""): ?>

        <div class="error">
            ⚠️ <?= htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <label>Username</label>

        <input
            type="text"
            name="username"
            placeholder="Masukkan username"
            required
        >

        <label>Password</label>

        <input
            type="password"
            name="password"
            placeholder="Masukkan password"
            required
        >

        <button type="submit">
            🔑 Login Admin
        </button>

    </form>

    <a href="index.php" class="back">
        ← Kembali ke Kasir
    </a>

</div>

</body>

</html>