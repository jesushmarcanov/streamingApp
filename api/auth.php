<?php
/**
 * API de Autenticación
 * Maneja login, registro y logout de usuarios
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

class AuthAPI {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Solicitar restablecimiento de contraseña
     */
    private function requestPasswordReset() {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = isset($input['email']) ? trim($input['email']) : '';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->sendResponse(false, 'Email inválido', null, 400);
        }
        try {
            // Existe usuario con ese email?
            $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE email = :email AND activo = 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                // Respuesta genérica para no filtrar usuarios
                return $this->sendResponse(true, 'Si el email existe, enviaremos un enlace de restablecimiento');
            }
            $user = $stmt->fetch();
            $token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora
            $upd = $this->conn->prepare("UPDATE usuarios SET password_reset_token = :t, password_reset_expires = :e WHERE id = :id");
            $upd->bindParam(':t', $token);
            $upd->bindParam(':e', $expires);
            $upd->bindParam(':id', $user['id']);
            $upd->execute();
            // En un entorno real enviaríamos email. Devolvemos enlace para entorno local
            $reset_link = sprintf('%s/reset.html?token=%s', dirname(dirname($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'])), $token);
            return $this->sendResponse(true, 'Solicitud recibida. Revisa tu email.', [ 'reset_link' => $reset_link ]);
        } catch (Exception $e) {
            return $this->sendResponse(false, 'Error al generar solicitud', null, 500);
        }
    }
    
    /**
     * Restablecer contraseña con token
     */
    private function resetPassword() {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = isset($input['token']) ? trim($input['token']) : '';
        $password = $input['password'] ?? '';
        if (!$token || strlen($password) < 6) {
            return $this->sendResponse(false, 'Token inválido o contraseña muy corta', null, 400);
        }
        try {
            $stmt = $this->conn->prepare("SELECT id, password_reset_expires FROM usuarios WHERE password_reset_token = :t");
            $stmt->bindParam(':t', $token);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                return $this->sendResponse(false, 'Token inválido', null, 400);
            }
            $user = $stmt->fetch();
            if (!$user['password_reset_expires'] || strtotime($user['password_reset_expires']) < time()) {
                return $this->sendResponse(false, 'Token expirado', null, 400);
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $this->conn->prepare("UPDATE usuarios SET password_hash = :p, password_reset_token = NULL, password_reset_expires = NULL WHERE id = :id");
            $upd->bindParam(':p', $hash);
            $upd->bindParam(':id', $user['id']);
            $upd->execute();
            return $this->sendResponse(true, 'Contraseña actualizada exitosamente');
        } catch (Exception $e) {
            return $this->sendResponse(false, 'Error al restablecer contraseña', null, 500);
        }
    }
    
    /**
     * Enviar verificación de email (genera token)
     */
    private function sendVerification() {
        // Puede recibir ?email= o usar email de sesión
        $email = $_GET['email'] ?? ($_SESSION['email'] ?? '');
        $email = trim($email);
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->sendResponse(false, 'Email inválido', null, 400);
        }
        try {
            $stmt = $this->conn->prepare("SELECT id, email_verified FROM usuarios WHERE email = :email AND activo = 1");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                return $this->sendResponse(false, 'Usuario no encontrado', null, 404);
            }
            $user = $stmt->fetch();
            if ((int)$user['email_verified'] === 1) {
                return $this->sendResponse(true, 'Email ya verificado');
            }
            $token = bin2hex(random_bytes(16));
            $upd = $this->conn->prepare("UPDATE usuarios SET email_verification_token = :t WHERE id = :id");
            $upd->bindParam(':t', $token);
            $upd->bindParam(':id', $user['id']);
            $upd->execute();
            $verify_link = sprintf('%s/verify.html?token=%s', dirname(dirname($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'])), $token);
            return $this->sendResponse(true, 'Verificación enviada', [ 'verify_link' => $verify_link ]);
        } catch (Exception $e) {
            return $this->sendResponse(false, 'Error al generar verificación', null, 500);
        }
    }
    
    /**
     * Verificar email con token
     */
    private function verifyEmail() {
        $token = $_GET['token'] ?? '';
        $token = trim($token);
        if (!$token) {
            return $this->sendResponse(false, 'Token requerido', null, 400);
        }
        try {
            $stmt = $this->conn->prepare("SELECT id FROM usuarios WHERE email_verification_token = :t");
            $stmt->bindParam(':t', $token);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                return $this->sendResponse(false, 'Token inválido', null, 400);
            }
            $user = $stmt->fetch();
            $upd = $this->conn->prepare("UPDATE usuarios SET email_verified = 1, email_verification_token = NULL WHERE id = :id");
            $upd->bindParam(':id', $user['id']);
            $upd->execute();
            return $this->sendResponse(true, 'Email verificado con éxito');
        } catch (Exception $e) {
            return $this->sendResponse(false, 'Error al verificar email', null, 500);
        }
    }
    
    /**
     * Actualizar perfil del usuario autenticado
     */
    private function updateProfile() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return $this->sendResponse(false, 'Usuario no autenticado', null, 401);
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
        $apellido = isset($input['apellido']) ? trim($input['apellido']) : '';
        $telefono = isset($input['telefono']) ? trim($input['telefono']) : null;
        if (!$nombre || !$apellido) {
            return $this->sendResponse(false, 'Nombre y apellido son requeridos', null, 400);
        }
        try {
            $upd = $this->conn->prepare("UPDATE usuarios SET nombre = :n, apellido = :a, telefono = :t WHERE id = :id");
            $upd->bindParam(':n', $nombre);
            $upd->bindParam(':a', $apellido);
            $upd->bindParam(':t', $telefono);
            $upd->bindParam(':id', $_SESSION['user_id']);
            $upd->execute();
            // Actualizar sesión
            $_SESSION['nombre'] = $nombre;
            $_SESSION['apellido'] = $apellido;
            return $this->sendResponse(true, 'Perfil actualizado');
        } catch (Exception $e) {
            return $this->sendResponse(false, 'Error al actualizar perfil', null, 500);
        }
    }
    
    /**
     * Procesar las peticiones de la API
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';
        
        try {
            switch ($method) {
                case 'POST':
                    switch ($action) {
                        case 'login':
                            return $this->login();
                        case 'register':
                            return $this->register();
                        case 'logout':
                            return $this->logout();
                        case 'request_password_reset':
                            return $this->requestPasswordReset();
                        case 'reset_password':
                            return $this->resetPassword();
                        case 'update_profile':
                            return $this->updateProfile();
                        default:
                            return $this->sendResponse(false, 'Acción no válida', null, 400);
                    }
                case 'GET':
                    switch ($action) {
                        case 'check':
                            return $this->checkAuth();
                        case 'profile':
                            return $this->getProfile();
                        case 'send_verification':
                            return $this->sendVerification();
                        case 'verify_email':
                            return $this->verifyEmail();
                        default:
                            return $this->sendResponse(false, 'Acción no válida', null, 400);
                    }
                default:
                    return $this->sendResponse(false, 'Método no permitido', null, 405);
            }
        } catch (Exception $e) {
            return $this->sendResponse(false, 'Error interno del servidor: ' . $e->getMessage(), null, 500);
        }
    }
    
    /**
     * Login de usuario
     */
    private function login() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['username']) || !isset($input['password'])) {
            return $this->sendResponse(false, 'Usuario y contraseña son requeridos', null, 400);
        }
        
        $username = trim($input['username']);
        $password = $input['password'];
        
        if (empty($username) || empty($password)) {
            return $this->sendResponse(false, 'Usuario y contraseña no pueden estar vacíos', null, 400);
        }
        
        try {
            // Buscar usuario por username o email
            $query = "SELECT id, username, email, password_hash, nombre, apellido, rol, activo 
                     FROM usuarios 
                     WHERE (username = :username OR email = :username) AND activo = 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return $this->sendResponse(false, 'Credenciales incorrectas', null, 401);
            }
            
            $user = $stmt->fetch();
            
            // Verificar contraseña
            if (!password_verify($password, $user['password_hash'])) {
                return $this->sendResponse(false, 'Credenciales incorrectas', null, 401);
            }
            
            // Actualizar último acceso
            $updateQuery = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':id', $user['id']);
            $updateStmt->execute();
            
            // Crear sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['apellido'] = $user['apellido'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['logged_in'] = true;
            
            // Preparar datos de respuesta (sin contraseña)
            unset($user['password_hash']);
            
            return $this->sendResponse(true, 'Login exitoso', $user);
            
        } catch (PDOException $e) {
            return $this->sendResponse(false, 'Error en la base de datos', null, 500);
        }
    }
    
    /**
     * Registro de nuevo usuario
     */
    private function register() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validar campos requeridos
        $required_fields = ['username', 'email', 'password', 'nombre', 'apellido'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty(trim($input[$field]))) {
                return $this->sendResponse(false, "El campo {$field} es requerido", null, 400);
            }
        }
        
        $username = trim($input['username']);
        $email = trim($input['email']);
        $password = $input['password'];
        $nombre = trim($input['nombre']);
        $apellido = trim($input['apellido']);
        $telefono = isset($input['telefono']) ? trim($input['telefono']) : null;
        
        // Validaciones
        if (strlen($username) < 3) {
            return $this->sendResponse(false, 'El nombre de usuario debe tener al menos 3 caracteres', null, 400);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->sendResponse(false, 'El email no es válido', null, 400);
        }
        
        if (strlen($password) < 6) {
            return $this->sendResponse(false, 'La contraseña debe tener al menos 6 caracteres', null, 400);
        }
        
        try {
            // Verificar si el usuario ya existe
            $checkQuery = "SELECT id FROM usuarios WHERE username = :username OR email = :email";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':username', $username);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return $this->sendResponse(false, 'El usuario o email ya existe', null, 409);
            }
            
            // Encriptar contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar nuevo usuario
            $insertQuery = "INSERT INTO usuarios (username, email, password_hash, nombre, apellido, telefono) 
                           VALUES (:username, :email, :password_hash, :nombre, :apellido, :telefono)";
            
            $insertStmt = $this->conn->prepare($insertQuery);
            $insertStmt->bindParam(':username', $username);
            $insertStmt->bindParam(':email', $email);
            $insertStmt->bindParam(':password_hash', $password_hash);
            $insertStmt->bindParam(':nombre', $nombre);
            $insertStmt->bindParam(':apellido', $apellido);
            $insertStmt->bindParam(':telefono', $telefono);
            
            if ($insertStmt->execute()) {
                $user_id = $this->conn->lastInsertId();
                
                // Obtener datos del usuario recién creado
                $getUserQuery = "SELECT id, username, email, nombre, apellido, rol FROM usuarios WHERE id = :id";
                $getUserStmt = $this->conn->prepare($getUserQuery);
                $getUserStmt->bindParam(':id', $user_id);
                $getUserStmt->execute();
                $user = $getUserStmt->fetch();
                
                return $this->sendResponse(true, 'Usuario registrado exitosamente', $user, 201);
            } else {
                return $this->sendResponse(false, 'Error al registrar usuario', null, 500);
            }
            
        } catch (PDOException $e) {
            return $this->sendResponse(false, 'Error en la base de datos', null, 500);
        }
    }
    
    /**
     * Logout de usuario
     */
    private function logout() {
        // Destruir sesión
        session_unset();
        session_destroy();
        
        return $this->sendResponse(true, 'Logout exitoso');
    }
    
    /**
     * Verificar autenticación
     */
    private function checkAuth() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            $user_data = [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'nombre' => $_SESSION['nombre'],
                'apellido' => $_SESSION['apellido'],
                'rol' => $_SESSION['rol']
            ];
            return $this->sendResponse(true, 'Usuario autenticado', $user_data);
        } else {
            return $this->sendResponse(false, 'Usuario no autenticado', null, 401);
        }
    }
    
    /**
     * Obtener perfil del usuario
     */
    private function getProfile() {
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return $this->sendResponse(false, 'Usuario no autenticado', null, 401);
        }
        
        try {
            $query = "SELECT id, username, email, nombre, apellido, telefono, rol, fecha_registro, ultimo_acceso 
                     FROM usuarios WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                return $this->sendResponse(false, 'Usuario no encontrado', null, 404);
            }
            
            $user = $stmt->fetch();
            return $this->sendResponse(true, 'Perfil obtenido', $user);
            
        } catch (PDOException $e) {
            return $this->sendResponse(false, 'Error en la base de datos', null, 500);
        }
    }
    
    /**
     * Enviar respuesta JSON
     */
    private function sendResponse($success, $message, $data = null, $status_code = 200) {
        http_response_code($status_code);
        
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Procesar la petición
$api = new AuthAPI();
$api->handleRequest();
?>
