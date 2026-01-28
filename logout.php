<?php
require_once 'includes/init.php';

$auth = new Auth();
$auth->logout();

Session::setFlash('success', 'Ha cerrado sesión correctamente.');
redirect(APP_URL . '/login.php');
