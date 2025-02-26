<?php
// check_rol.php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Solo verificar el rol si se especifica un required_role
if (isset($required_role)) {
    if (!in_array($_SESSION['rol'], $required_role)) {
        header("Location: index.php");
        exit;
    }
}
?>