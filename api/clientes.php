<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

class ClienteAPI {
    private $conn;
    private $table_name = "clientes";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Obtener todos los clientes
    public function getAll() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " ORDER BY fecha_registro DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $clientes = $stmt->fetchAll();
            
            return array(
                'success' => true,
                'data' => $clientes
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
            $result = $clienteAPI->getAll();
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
