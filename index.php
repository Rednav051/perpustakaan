<?php
require_once 'includes/config.php';
if (isLoggedIn()) {
    if (isAdmin()) redirect('/perpustakaan/admin/dashboard.php');
    else redirect('/perpustakaan/user/dashboard.php');
} else {
    redirect('/perpustakaan/login.php');
}
?>
