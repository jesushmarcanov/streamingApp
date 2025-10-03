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

class ServicioAPI {
    private $conn;
    private $table_name = "servicios";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Obtener todos los servicios con información de cliente y proveedor (con búsqueda y paginación)
    public function getAll($params = []) {
        try {
            $page = max(1, isset($params['page']) ? (int)$params['page'] : 1);
            $perPage = max(1, min(1000, isset($params['per_page']) ? (int)$params['per_page'] : 10));
            $search = isset($params['search']) ? trim($params['search']) : '';

            $where = '';
            $bindings = [];
            if ($search !== '') {
                $where = " WHERE (c.nombre LIKE :q OR c.apellido LIKE :q OR p.nombre LIKE :q OR s.nombre_servicio LIKE :q OR s.tipo_servicio LIKE :q OR s.estado LIKE :q)";
                $bindings[':q'] = "%" . $search . "%";
            }

            // Conteo total
            $countQuery = "SELECT COUNT(*) AS total
                           FROM " . $this->table_name . " s
                           LEFT JOIN clientes c ON s.cliente_id = c.id
                           LEFT JOIN proveedores p ON s.proveedor_id = p.id" . $where;
            $countStmt = $this->conn->prepare($countQuery);
            foreach ($bindings as $k => $v) {
                $countStmt->bindValue($k, $v);
            }
            $countStmt->execute();
            $total = (int)$countStmt->fetch()['total'];

            $offset = ($page - 1) * $perPage;

            // Datos paginados
            $query = "SELECT s.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido, 
                             c.telefono as cliente_telefono, p.nombre as proveedor_nombre
                      FROM " . $this->table_name . " s
                      LEFT JOIN clientes c ON s.cliente_id = c.id
                      LEFT JOIN proveedores p ON s.proveedor_id = p.id" . $where . "
                      ORDER BY s.fecha_registro DESC
                      LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($query);
            foreach ($bindings as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            $servicios = $stmt->fetchAll();
            
            return array(
                'success' => true,
                'data' => $servicios,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al obtener servicios: ' . $e->getMessage()
            );
        }
    }

    // Obtener servicios próximos a vencer
    public function getProximosVencer($dias = 7) {
        try {
            $query = "SELECT s.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido, 
                             c.telefono as cliente_telefono, p.nombre as proveedor_nombre
                      FROM " . $this->table_name . " s
                      LEFT JOIN clientes c ON s.cliente_id = c.id
                      LEFT JOIN proveedores p ON s.proveedor_id = p.id
                      WHERE s.estado = 'Activo' 
                      AND s.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
                      ORDER BY s.fecha_vencimiento ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dias', $dias);
            $stmt->execute();
            $servicios = $stmt->fetchAll();
            
            return array(
                'success' => true,
                'data' => $servicios
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al obtener servicios próximos a vencer: ' . $e->getMessage()
            );
        }
    }

    // Obtener un servicio por ID
    public function getById($id) {
        try {
            $query = "SELECT s.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido, 
                             c.telefono as cliente_telefono, p.nombre as proveedor_nombre
                      FROM " . $this->table_name . " s
                      LEFT JOIN clientes c ON s.cliente_id = c.id
                      LEFT JOIN proveedores p ON s.proveedor_id = p.id
                      WHERE s.id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $servicio = $stmt->fetch();
            
            if ($servicio) {
                return array(
                    'success' => true,
                    'data' => $servicio
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Servicio no encontrado'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al obtener servicio: ' . $e->getMessage()
            );
        }
    }

    // Crear nuevo servicio
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (cliente_id, proveedor_id, nombre_servicio, tipo_servicio, precio_mensual, 
                      fecha_inicio, fecha_vencimiento, estado, observaciones) 
                     VALUES (:cliente_id, :proveedor_id, :nombre_servicio, :tipo_servicio, :precio_mensual, 
                             :fecha_inicio, :fecha_vencimiento, :estado, :observaciones)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':cliente_id', $data['cliente_id']);
            $stmt->bindParam(':proveedor_id', $data['proveedor_id']);
            $stmt->bindParam(':nombre_servicio', $data['nombre_servicio']);
            $stmt->bindParam(':tipo_servicio', $data['tipo_servicio']);
            $stmt->bindParam(':precio_mensual', $data['precio_mensual']);
            $stmt->bindParam(':fecha_inicio', $data['fecha_inicio']);
            $stmt->bindParam(':fecha_vencimiento', $data['fecha_vencimiento']);
            $stmt->bindParam(':estado', $data['estado']);
            $stmt->bindParam(':observaciones', $data['observaciones']);
            
            if ($stmt->execute()) {
                $id = $this->conn->lastInsertId();
                return array(
                    'success' => true,
                    'message' => 'Servicio creado exitosamente',
                    'id' => $id
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al crear servicio'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al crear servicio: ' . $e->getMessage()
            );
        }
    }

    // Actualizar servicio
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET cliente_id = :cliente_id, proveedor_id = :proveedor_id, 
                         nombre_servicio = :nombre_servicio, tipo_servicio = :tipo_servicio, 
                         precio_mensual = :precio_mensual, fecha_inicio = :fecha_inicio, 
                         fecha_vencimiento = :fecha_vencimiento, estado = :estado, 
                         observaciones = :observaciones 
                     WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':cliente_id', $data['cliente_id']);
            $stmt->bindParam(':proveedor_id', $data['proveedor_id']);
            $stmt->bindParam(':nombre_servicio', $data['nombre_servicio']);
            $stmt->bindParam(':tipo_servicio', $data['tipo_servicio']);
            $stmt->bindParam(':precio_mensual', $data['precio_mensual']);
            $stmt->bindParam(':fecha_inicio', $data['fecha_inicio']);
            $stmt->bindParam(':fecha_vencimiento', $data['fecha_vencimiento']);
            $stmt->bindParam(':estado', $data['estado']);
            $stmt->bindParam(':observaciones', $data['observaciones']);
            
            if ($stmt->execute()) {
                return array(
                    'success' => true,
                    'message' => 'Servicio actualizado exitosamente'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al actualizar servicio'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al actualizar servicio: ' . $e->getMessage()
            );
        }
    }

    // Eliminar servicio
    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return array(
                    'success' => true,
                    'message' => 'Servicio eliminado exitosamente'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al eliminar servicio'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al eliminar servicio: ' . $e->getMessage()
            );
        }
    }
}

// Procesar la petición
$method = $_SERVER['REQUEST_METHOD'];
$servicioAPI = new ServicioAPI();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $result = $servicioAPI->getById($_GET['id']);
        } elseif (isset($_GET['proximos_vencer'])) {
            $dias = $_GET['dias'] ?? 7;
            $result = $servicioAPI->getProximosVencer($dias);
        } else {
            $params = [
                'page' => $_GET['page'] ?? 1,
                'per_page' => $_GET['per_page'] ?? 10,
                'search' => $_GET['search'] ?? ''
            ];
            $result = $servicioAPI->getAll($params);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $result = $servicioAPI->create($data);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $_GET['id'] ?? $data['id'];
        $result = $servicioAPI->update($id, $data);
        break;
        
    case 'DELETE':
        $id = $_GET['id'];
        $result = $servicioAPI->delete($id);
        break;
        
    default:
        $result = array(
            'success' => false,
            'message' => 'Método no permitido'
        );
}

echo json_encode($result);
?>
