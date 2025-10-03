<?php
/**
 * Gestión de sesiones y autenticación
 */

class SessionManager {
    
    /**
     * Inicializar sesión
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    public static function isLoggedIn() {
        self::init();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Verificar si el usuario es administrador
     */
    public static function isAdmin() {
        self::init();
        return self::isLoggedIn() && isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
    }
    
    /**
     * Obtener ID del usuario actual
     */
    public static function getUserId() {
        self::init();
        return self::isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Obtener datos del usuario actual
     */
    public static function getUserData() {
        self::init();
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'nombre' => $_SESSION['nombre'],
            'apellido' => $_SESSION['apellido'],
            'rol' => $_SESSION['rol']
        ];
    }
    
    /**
     * Requerir autenticación - redirige al login si no está autenticado
     */
    public static function requireAuth() {
        if (!self::isLoggedIn()) {
            header('Location: login.html');
            exit;
        }
    }
    
    /**
     * Requerir permisos de administrador
     */
    public static function requireAdmin() {
        self::requireAuth();
        if (!self::isAdmin()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Acceso denegado. Se requieren permisos de administrador.'
            ]);
            exit;
        }
    }
    
    /**
     * Destruir sesión
     */
    public static function destroy() {
        self::init();
        session_unset();
        session_destroy();
    }
    
    /**
     * Establecer datos de usuario en la sesión
     */
    public static function setUserData($user_data) {
        self::init();
        $_SESSION['user_id'] = $user_data['id'];
        $_SESSION['username'] = $user_data['username'];
        $_SESSION['email'] = $user_data['email'];
        $_SESSION['nombre'] = $user_data['nombre'];
        $_SESSION['apellido'] = $user_data['apellido'];
        $_SESSION['rol'] = $user_data['rol'];
        $_SESSION['logged_in'] = true;
    }
}
?>
