<?php
/**
 * Clase de manejo de sesiones y autenticación
 */

class Session {
    
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    public static function destroy() {
        session_unset();
        session_destroy();
    }
    
    public static function isLoggedIn() {
        return self::has('user_id') && self::get('user_id') > 0;
    }
    
    public static function getUserId() {
        return self::get('user_id', 0);
    }
    
    public static function getUser() {
        return self::get('user', null);
    }
    
    public static function setFlash($type, $message) {
        self::set('flash', [
            'type' => $type,
            'message' => $message
        ]);
    }
    
    public static function getFlash() {
        $flash = self::get('flash');
        self::remove('flash');
        return $flash;
    }
    
    public static function hasFlash() {
        return self::has('flash');
    }
    
    // Generar token CSRF
    public static function generateCsrfToken() {
        if (!self::has('csrf_token')) {
            self::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return self::get('csrf_token');
    }
    
    // Verificar token CSRF
    public static function verifyCsrfToken($token) {
        return self::has('csrf_token') && hash_equals(self::get('csrf_token'), $token);
    }
}
