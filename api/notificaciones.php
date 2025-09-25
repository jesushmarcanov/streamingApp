<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

class NotificacionAPI {
    private $conn;
    private $table_name = "notificaciones";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Obtener configuración de WhatsApp
    private function getConfig($clave) {
        $query = "SELECT valor FROM configuracion WHERE clave = :clave";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':clave', $clave);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['valor'] : '';
    }

    // Enviar notificación por WhatsApp
    private function enviarWhatsApp($telefono, $mensaje) {
        $api_url = $this->getConfig('whatsapp_api_url');
        $token = $this->getConfig('whatsapp_token');
        
        if (empty($api_url) || empty($token)) {
            return array(
                'success' => false,
                'message' => 'Configuración de WhatsApp no encontrada'
            );
        }

        $data = array(
            'phone' => $telefono,
            'message' => $mensaje
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            return array(
                'success' => true,
                'message' => 'Notificación enviada exitosamente'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Error al enviar notificación: ' . $response
            );
        }
    }

    // Crear notificación de vencimiento
    public function crearNotificacionVencimiento($servicio_id, $cliente_id, $mensaje) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (servicio_id, cliente_id, tipo_notificacion, mensaje) 
                     VALUES (:servicio_id, :cliente_id, 'Vencimiento', :mensaje)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':servicio_id', $servicio_id);
            $stmt->bindParam(':cliente_id', $cliente_id);
            $stmt->bindParam(':mensaje', $mensaje);
            
            if ($stmt->execute()) {
                $id = $this->conn->lastInsertId();
                return array(
                    'success' => true,
                    'message' => 'Notificación creada exitosamente',
                    'id' => $id
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error al crear notificación'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al crear notificación: ' . $e->getMessage()
            );
        }
    }

    // Enviar notificaciones pendientes
    public function enviarNotificacionesPendientes() {
        try {
            $query = "SELECT n.*, c.telefono, c.nombre as cliente_nombre, s.nombre_servicio
                      FROM " . $this->table_name . " n
                      LEFT JOIN clientes c ON n.cliente_id = c.id
                      LEFT JOIN servicios s ON n.servicio_id = s.id
                      WHERE n.estado = 'Pendiente'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $notificaciones = $stmt->fetchAll();
            
            $enviadas = 0;
            $fallidas = 0;
            
            foreach ($notificaciones as $notificacion) {
                $resultado = $this->enviarWhatsApp($notificacion['telefono'], $notificacion['mensaje']);
                
                $estado = $resultado['success'] ? 'Enviada' : 'Fallida';
                $fecha_envio = $resultado['success'] ? date('Y-m-d H:i:s') : null;
                
                $update_query = "UPDATE " . $this->table_name . " 
                                SET estado = :estado, fecha_envio = :fecha_envio 
                                WHERE id = :id";
                $update_stmt = $this->conn->prepare($update_query);
                $update_stmt->bindParam(':estado', $estado);
                $update_stmt->bindParam(':fecha_envio', $fecha_envio);
                $update_stmt->bindParam(':id', $notificacion['id']);
                $update_stmt->execute();
                
                if ($resultado['success']) {
                    $enviadas++;
                } else {
                    $fallidas++;
                }
            }
            
            return array(
                'success' => true,
                'message' => "Proceso completado. Enviadas: $enviadas, Fallidas: $fallidas",
                'enviadas' => $enviadas,
                'fallidas' => $fallidas
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al enviar notificaciones: ' . $e->getMessage()
            );
        }
    }

    // Generar notificaciones automáticas para servicios próximos a vencer
    public function generarNotificacionesAutomaticas() {
        try {
            $dias_aviso = $this->getConfig('dias_aviso_vencimiento');
            $mensaje_template = $this->getConfig('mensaje_vencimiento');
            
            $query = "SELECT s.*, c.nombre as cliente_nombre, c.telefono, p.nombre as proveedor_nombre
                      FROM servicios s
                      LEFT JOIN clientes c ON s.cliente_id = c.id
                      LEFT JOIN proveedores p ON s.proveedor_id = p.id
                      WHERE s.estado = 'Activo' 
                      AND s.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
                      AND NOT EXISTS (
                          SELECT 1 FROM " . $this->table_name . " n 
                          WHERE n.servicio_id = s.id 
                          AND n.tipo_notificacion = 'Vencimiento' 
                          AND DATE(n.fecha_creacion) = CURDATE()
                      )";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dias', $dias_aviso);
            $stmt->execute();
            $servicios = $stmt->fetchAll();
            
            $creadas = 0;
            
            foreach ($servicios as $servicio) {
                $mensaje = str_replace(
                    array('{nombre}', '{servicio}', '{fecha}'),
                    array($servicio['cliente_nombre'], $servicio['nombre_servicio'], $servicio['fecha_vencimiento']),
                    $mensaje_template
                );
                
                $resultado = $this->crearNotificacionVencimiento(
                    $servicio['id'], 
                    $servicio['cliente_id'], 
                    $mensaje
                );
                
                if ($resultado['success']) {
                    $creadas++;
                }
            }
            
            return array(
                'success' => true,
                'message' => "Se crearon $creadas notificaciones automáticas",
                'creadas' => $creadas
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al generar notificaciones automáticas: ' . $e->getMessage()
            );
        }
    }

    // Obtener historial de notificaciones
    public function getHistorial() {
        try {
            $query = "SELECT n.*, c.nombre as cliente_nombre, s.nombre_servicio
                      FROM " . $this->table_name . " n
                      LEFT JOIN clientes c ON n.cliente_id = c.id
                      LEFT JOIN servicios s ON n.servicio_id = s.id
                      ORDER BY n.fecha_creacion DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $notificaciones = $stmt->fetchAll();
            
            return array(
                'success' => true,
                'data' => $notificaciones
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage()
            );
        }
    }
}

// Procesar la petición
$method = $_SERVER['REQUEST_METHOD'];
$notificacionAPI = new NotificacionAPI();

switch ($method) {
    case 'GET':
        if (isset($_GET['historial'])) {
            $result = $notificacionAPI->getHistorial();
        } else {
            $result = array(
                'success' => false,
                'message' => 'Parámetro no válido'
            );
        }
        break;
        
    case 'POST':
        $action = $_GET['action'] ?? '';
        switch ($action) {
            case 'enviar_pendientes':
                $result = $notificacionAPI->enviarNotificacionesPendientes();
                break;
            case 'generar_automaticas':
                $result = $notificacionAPI->generarNotificacionesAutomaticas();
                break;
            default:
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $notificacionAPI->crearNotificacionVencimiento(
                    $data['servicio_id'], 
                    $data['cliente_id'], 
                    $data['mensaje']
                );
        }
        break;
        
    default:
        $result = array(
            'success' => false,
            'message' => 'Método no permitido'
        );
}

echo json_encode($result);
?>
