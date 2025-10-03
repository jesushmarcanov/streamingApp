<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../config/session.php';

// Verificar autenticación de usuario
SessionManager::init();
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autenticado'
    ]);
    exit;
}

class ClienteAPI {
    private $conn;
    private $table_name = "clientes";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Obtener todos los clientes con búsqueda y paginación
    public function getAll($params = []) {
        try {
            // Parámetros de paginación y búsqueda
            $page = max(1, isset($params['page']) ? (int)$params['page'] : 1);
            $perPage = max(1, min(1000, isset($params['per_page']) ? (int)$params['per_page'] : 10));
            $search = isset($params['search']) ? trim($params['search']) : '';
            $activo = isset($params['activo']) && $params['activo'] !== '' ? (int)$params['activo'] : null;

            $conditions = [];
            $bindings = [];
            if ($search !== '') {
                $conditions[] = "(nombre LIKE :q OR apellido LIKE :q OR telefono LIKE :q OR email LIKE :q)";
                $bindings[':q'] = "%" . $search . "%";
            }
            if ($activo !== null) {
                $conditions[] = "activo = :activo";
                $bindings[':activo'] = $activo;
            }
            $where = count($conditions) ? (' WHERE ' . implode(' AND ', $conditions)) : '';

            // Contar total
            $countQuery = "SELECT COUNT(*) AS total FROM " . $this->table_name . $where;
            $countStmt = $this->conn->prepare($countQuery);
            foreach ($bindings as $k => $v) {
                $countStmt->bindValue($k, $v);
            }
            $countStmt->execute();
            $total = (int)$countStmt->fetch()['total'];

            // Calcular límite/offset
            $offset = ($page - 1) * $perPage;

            // Obtener datos paginados
            $query = "SELECT * FROM " . $this->table_name . $where . " ORDER BY fecha_registro DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($query);
            foreach ($bindings as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $clientes = $stmt->fetchAll();
            
            return array(
                'success' => true,
                'data' => $clientes,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al obtener clientes: ' . $e->getMessage()
            );
        }
    }

    // Obtener un cliente por ID
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $cliente = $stmt->fetch();
            
            if ($cliente) {
                return array(
                    'success' => true,
                    'data' => $cliente
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al obtener cliente: ' . $e->getMessage()
            );
        }
    }

    // Crear nuevo cliente
    public function create($data) {
        try {
            // Validar campos requeridos
            if (empty($data['nombre']) || empty($data['apellido']) || empty($data['telefono'])) {
                return array(
                    'success' => false,
                    'message' => 'Los campos nombre, apellido y teléfono son requeridos'
                );
            }
            
            $query = "INSERT INTO " . $this->table_name . " 
                     (nombre, apellido, telefono, email, direccion, activo) 
                     VALUES (:nombre, :apellido, :telefono, :email, :direccion, :activo)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':apellido', $data['apellido']);
            $stmt->bindParam(':telefono', $data['telefono']);
            
            // Manejar campos opcionales
            $email = !empty($data['email']) ? $data['email'] : null;
            $direccion = !empty($data['direccion']) ? $data['direccion'] : null;
            $activo = isset($data['activo']) ? (int)$data['activo'] : 1; // Default to active if not specified
            
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':direccion', $direccion);
            $stmt->bindParam(':activo', $activo);
            
            if ($stmt->execute()) {
                $id = $this->conn->lastInsertId();
                return array(
                    'success' => true,
                    'message' => 'Cliente creado exitosamente',
                    'id' => $id
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al crear cliente'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al crear cliente: ' . $e->getMessage()
            );
        }
    }

    // Actualizar cliente
    public function update($id, $data) {
        try {
            // Validar campos requeridos
            if (empty($data['nombre']) || empty($data['apellido']) || empty($data['telefono'])) {
                return array(
                    'success' => false,
                    'message' => 'Los campos nombre, apellido y teléfono son requeridos'
                );
            }
            
            $query = "UPDATE " . $this->table_name . " 
                     SET nombre = :nombre, apellido = :apellido, telefono = :telefono, 
                         email = :email, direccion = :direccion, activo = :activo 
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindParam(':apellido', $data['apellido']);
            $stmt->bindParam(':telefono', $data['telefono']);
            
            // Manejar campos opcionales
            $email = !empty($data['email']) ? $data['email'] : null;
            $direccion = !empty($data['direccion']) ? $data['direccion'] : null;
            $activo = isset($data['activo']) ? (int)$data['activo'] : 1; // Default to active if not specified
            
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':direccion', $direccion);
            $stmt->bindParam(':activo', $activo);
            
            if ($stmt->execute()) {
                return array(
                    'success' => true,
                    'message' => 'Cliente actualizado exitosamente'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al actualizar cliente'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al actualizar cliente: ' . $e->getMessage()
            );
        }
    }

    // Eliminar cliente (soft delete)
    public function delete($id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return array(
                    'success' => true,
                    'message' => 'Cliente eliminado exitosamente'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al eliminar cliente'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al eliminar cliente: ' . $e->getMessage()
            );
        }
    }
}

// Procesar la petición
$method = $_SERVER['REQUEST_METHOD'];
$clienteAPI = new ClienteAPI();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $result = $clienteAPI->getById($_GET['id']);
        } else {
            // Soporte de paginación y búsqueda
            $params = [
                'page' => $_GET['page'] ?? 1,
                'per_page' => $_GET['per_page'] ?? 10,
                'search' => $_GET['search'] ?? ''
            ];
            $result = $clienteAPI->getAll($params);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $clienteAPI->create($data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'] ?? $data['id'];
        $result = $clienteAPI->update($id, $data);
        break;
        
    case 'DELETE':
        $id = $_GET['id'];
        $result = $clienteAPI->delete($id);
        break;
        
    default:
        $result = array(
            'success' => false,
            'message' => 'Método no permitido'
        );
}

echo json_encode($result);
?>
