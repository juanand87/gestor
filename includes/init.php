<?php
/**
 * Archivo de inicialización del sistema
 * Se incluye en todas las páginas
 */

// Definir constante para evitar acceso directo
define('GESTOR', true);

// Cargar configuración
require_once __DIR__ . '/../config/config.php';

// Cargar clases principales
require_once INCLUDES_PATH . 'Database.php';
require_once INCLUDES_PATH . 'Session.php';
require_once INCLUDES_PATH . 'Auth.php';
require_once INCLUDES_PATH . 'functions.php';

// Iniciar sesión
Session::init();
