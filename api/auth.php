<?php
/**
 * API de Autenticación - Login, Registro, Logout y Verificación de sesión
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin($db);
        break;
    case 'register':
        handleRegister($db);
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        handleCheck();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Iniciar sesión
 */
function handleLogin($db)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos']);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id, nombre, username, email, password, rol, activo FROM usuarios WHERE (username = ? OR email = ?) AND activo = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']);
            return;
        }

        // Actualizar último acceso
        $updateStmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        // Crear sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_rol'] = $user['rol'];
        $_SESSION['logged_in'] = true;

        echo json_encode([
            'success' => true,
            'message' => 'Sesión iniciada correctamente',
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'username' => $user['username'],
                'email' => $user['email'],
                'rol' => $user['rol']
            ]
        ]);
    }
    catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
    }
}

/**
 * Registrar nuevo usuario
 */
function handleRegister($db)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $nombre = trim($input['nombre'] ?? '');
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = trim($input['password'] ?? '');

    // Validaciones
    if (empty($nombre) || empty($username) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'El email no es válido']);
        return;
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
        return;
    }

    if (strlen($username) < 3) {
        echo json_encode(['success' => false, 'message' => 'El usuario debe tener al menos 3 caracteres']);
        return;
    }

    try {
        // Verificar si el username o email ya existen
        $checkStmt = $db->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'El usuario o email ya está registrado']);
            return;
        }

        // Crear usuario
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $insertStmt = $db->prepare("INSERT INTO usuarios (nombre, username, email, password, rol) VALUES (?, ?, ?, ?, 'usuario')");
        $insertStmt->execute([$nombre, $username, $email, $hashedPassword]);

        echo json_encode([
            'success' => true,
            'message' => 'Usuario registrado correctamente. Ya puedes iniciar sesión.'
        ]);
    }
    catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al registrar: ' . $e->getMessage()]);
    }
}

/**
 * Cerrar sesión
 */
function handleLogout()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Sesión cerrada correctamente']);
}

/**
 * Verificar si hay sesión activa
 */
function handleCheck()
{
    if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'nombre' => $_SESSION['user_name'],
                'username' => $_SESSION['username'],
                'rol' => $_SESSION['user_rol']
            ]
        ]);
    }
    else {
        echo json_encode([
            'success' => true,
            'authenticated' => false
        ]);
    }
}
?>
