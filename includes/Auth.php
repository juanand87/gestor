<?php
/**
 * Clase de autenticación de usuarios
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Iniciar sesión
     */
    public function login($username, $password) {
        $sql = "SELECT u.*, f.nombre, f.apellido_paterno, f.apellido_materno, f.cargo, f.rut,
                       f.es_secretario_ejecutivo, r.nombre as rol_nombre
                FROM usuarios u
                INNER JOIN funcionarios f ON u.funcionario_id = f.id
                INNER JOIN roles r ON u.rol_id = r.id
                WHERE u.username = :username AND u.activo = 1 AND f.activo = 1";
        
        $user = $this->db->selectOne($sql, ['username' => $username]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Actualizar último login
            $this->db->update('usuarios', 
                ['ultimo_login' => date('Y-m-d H:i:s')], 
                'id = :id', 
                ['id' => $user['id']]
            );
            
            // Obtener permisos del usuario
            $permisos = $this->getUserPermisos($user['id'], $user['rol_id']);
            
            // Guardar en sesión
            Session::set('user_id', $user['id']);
            Session::set('user', [
                'id' => $user['id'],
                'username' => $user['username'],
                'funcionario_id' => $user['funcionario_id'],
                'nombre_completo' => $user['nombre'] . ' ' . $user['apellido_paterno'] . ' ' . $user['apellido_materno'],
                'cargo' => $user['cargo'],
                'rut' => $user['rut'],
                'rol_id' => $user['rol_id'],
                'rol_nombre' => $user['rol_nombre'],
                'es_secretario_ejecutivo' => $user['es_secretario_ejecutivo'],
                'permisos' => $permisos
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        Session::destroy();
    }
    
    /**
     * Obtener permisos del usuario (rol + permisos adicionales)
     */
    private function getUserPermisos($userId, $rolId) {
        // Permisos del rol
        $sql = "SELECT p.clave 
                FROM permisos p
                INNER JOIN roles_permisos rp ON p.id = rp.permiso_id
                WHERE rp.rol_id = :rol_id";
        $rolPermisos = $this->db->select($sql, ['rol_id' => $rolId]);
        
        // Permisos adicionales del usuario
        $sql = "SELECT p.clave 
                FROM permisos p
                INNER JOIN usuarios_permisos up ON p.id = up.permiso_id
                WHERE up.usuario_id = :usuario_id";
        $userPermisos = $this->db->select($sql, ['usuario_id' => $userId]);
        
        // Combinar permisos
        $permisos = [];
        foreach ($rolPermisos as $p) {
            $permisos[] = $p['clave'];
        }
        foreach ($userPermisos as $p) {
            if (!in_array($p['clave'], $permisos)) {
                $permisos[] = $p['clave'];
            }
        }
        
        return $permisos;
    }
    
    /**
     * Verificar si el usuario tiene un permiso
     */
    public static function hasPermission($permiso) {
        $user = Session::getUser();
        if (!$user) return false;
        
        return in_array($permiso, $user['permisos']);
    }
    
    /**
     * Verificar si es Secretario Ejecutivo
     */
    public static function isSecretarioEjecutivo() {
        $user = Session::getUser();
        if (!$user) return false;
        
        return $user['es_secretario_ejecutivo'] == 1 || $user['rol_id'] == 2;
    }
    
    /**
     * Verificar si es Administrador
     */
    public static function isAdmin() {
        $user = Session::getUser();
        if (!$user) return false;
        
        return $user['rol_id'] == 1;
    }
    
    /**
     * Requerir login
     */
    public static function requireLogin() {
        if (!Session::isLoggedIn()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }
    
    /**
     * Requerir permiso específico
     */
    public static function requirePermission($permiso) {
        self::requireLogin();
        
        if (!self::hasPermission($permiso)) {
            Session::setFlash('error', 'No tiene permisos para acceder a esta sección.');
            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        }
    }
}
