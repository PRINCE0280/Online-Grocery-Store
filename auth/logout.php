<?php
@include 'config.php';

session_start();
session_unset();
session_destroy();

header('location:../index.php'); // Redirect to homepage instead of login page
exit;
?>
