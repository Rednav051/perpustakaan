<?php
require_once 'includes/config.php';

$id_admin = 'rednavganteng';
$nama     = 'Rednav';
$password = 'christian123';

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO admin (id_admin, nama, password) VALUES (?,?,?)");
$stmt->bind_param("sss", $id_admin, $nama, $hash);

if ($stmt->execute()) {
    echo "✅ Admin berhasil ditambahkan! ID: $id_admin | Password: $password";
} else {
    echo "❌ Gagal: " . $conn->error;
}
?>