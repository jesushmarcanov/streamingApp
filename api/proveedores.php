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

class ProveedorAPI {
    private $conn;
    private $table_name = "proveedores";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Obtener todos los proveedores con búsqueda y paginación
    public function getAll($params = []) {
        try {
            $page = max(1, isset($params['page']) ? (int)$params['page'] : 1);
            $perPage = max(1, min(1000, isset($params['per_page']) ? (int)$params['per_page'] : 10));
            $search = isset($params['search']) ? trim($params['search']) : '';

            $where = " WHERE activo = 1";
            $bindings = [];
            if ($search !== '') {
                $where .= " AND (nombre LIKE :q OR contacto LIKE :q OR telefono LIKE :q OR email LIKE :q)";
                $bindings[':q'] = "%" . $search . "%";
            }

            // Conteo total
            $countQuery = "SELECT COUNT(*) AS total FROM " . $this->table_name . $where;
            $countStmt = $this->conn->prepare($countQuery);
            foreach ($bindings as $k => $v) {
                $countStmt->bindValue($k, $v);
            }
            $countStmt->execute();
            $total = (int)$countStmt->fetch()['total'];

            $offset = ($page - 1) * $perPage;

            // Obtener página
            $query = "SELECT * FROM " . $this->table_name . $where . " ORDER BY fecha_registro DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($query);
            foreach ($bindings as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $proveedores = $stmt->fetchAll();
            
            return array(
                'success' => true,
                'data' => $proveedores,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al obtener proveedores: ' . $e->getMessage()
            );
        }
    }

    // Obtener un proveedor por ID
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND activo = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $proveedor = $stmt->fetch();
            
            if ($proveedor) {
                return array(
                    'success' => true,
                    'data' => $proveedor
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Proveedor no encontrado'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al obtener proveedor: ' . $e->getMessage()
            );
        }
    }

    // Crear nuevo proveedor
    public function create($data) {
        try {
            // Validar campos requeridos
            if (empty($data['nombre'])) {
                return array(
                    'success' => false,
                    'message' => 'El campo nombre es requerido'
                );
            }
            
            $query = "INSERT INTO " . $this->table_name . " 
                     (nombre, contacto, telefono, email, direccion) 
                     VALUES (:nombre, :contacto, :telefono, :email, :direccion)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':nombre', $data['nombre']);
            
            // Manejar campos opcionales
            $contacto = !empty($data['contacto']) ? $data['contacto'] : null;
            $telefono = !empty($data['telefono']) ? $data['telefono'] : null;
            $email = !empty($data['email']) ? $data['email'] : null;
            $direccion = !empty($data['direccion']) ? $data['direccion'] : null;
            
            $stmt->bindParam(':contacto', $contacto);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':direccion', $direccion);
            
            if ($stmt->execute()) {
                $id = $this->conn->lastInsertId();
                return array(
                    'success' => true,
                    'message' => 'Proveedor creado exitosamente',
                    'id' => $id
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al crear proveedor'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al crear proveedor: ' . $e->getMessage()
            );
        }
    }

    // Actualizar proveedor
    public function update($id, $data) {
        try {
            // Validar campos requeridos
            if (empty($data['nombre'])) {
                return array(
                    'success' => false,
                    'message' => 'El campo nombre es requerido'
                );
            }
            
            $query = "UPDATE " . $this->table_name . " 
                     SET nombre = :nombre, contacto = :contacto, telefono = :telefono, 
                         email = :email, direccion = :direccion 
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nombre', $data['nombre']);
            
            // Manejar campos opcionales
            $contacto = !empty($data['contacto']) ? $data['contacto'] : null;
            $telefono = !empty($data['telefono']) ? $data['telefono'] : null;
            $email = !empty($data['email']) ? $data['email'] : null;
            $direccion = !empty($data['direccion']) ? $data['direccion'] : null;
            
            $stmt->bindParam(':contacto', $contacto);
            $stmt->bindParam(':telefono', $telefono);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':direccion', $direccion);
            
            if ($stmt->execute()) {
                return array(
                    'success' => true,
                    'message' => 'Proveedor actualizado exitosamente'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al actualizar proveedor'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al actualizar proveedor: ' . $e->getMessage()
            );
        }
    }

    // Eliminar proveedor (soft delete)
    public function delete($id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET activo = 0 WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return array(
                    'success' => true,
                    'message' => 'Proveedor eliminado exitosamente'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al eliminar proveedor'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al eliminar proveedor: ' . $e->getMessage()
            );
        }
    }
}

// Procesar la petición
$method = $_SERVER['REQUEST_METHOD'];
$proveedorAPI = new ProveedorAPI();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $result = $proveedorAPI->getById($_GET['id']);
        } else {
            $params = [
                'page' => $_GET['page'] ?? 1,
                'per_page' => $_GET['per_page'] ?? 10,
                'search' => $_GET['search'] ?? ''
            ];
            $result = $proveedorAPI->getAll($params);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $proveedorAPI->create($data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'] ?? $data['id'];
        $result = $proveedorAPI->update($id, $data);
        break;
        
    case 'DELETE':
        $id = $_GET['id'];
        $result = $proveedorAPI->delete($id);
        break;
        
    default:
        $result = array(
            'success' => false,
            'message' => 'Método no permitido'
        );
}

echo json_encode($result);
?>
