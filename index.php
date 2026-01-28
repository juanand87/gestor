<?php
/**
 * Gestor Documental - Asociación de Municipios
 * Punto de entrada principal
 */

require_once 'includes/init.php';

// Redirigir según estado de sesión
if (Session::isLoggedIn()) {
    redirect(APP_URL . '/dashboard.php');
} else {
    redirect(APP_URL . '/login.php');
}
