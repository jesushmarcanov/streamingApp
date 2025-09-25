<?php
/**
 * Configuración de WhatsApp
 * Este archivo contiene las configuraciones para el envío de notificaciones por WhatsApp
 */

class WhatsAppConfig {
    private $conn;
    
    public function __construct() {
        require_once 'database.php';
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Obtener configuración de WhatsApp
     */
    public function getConfig() {
        try {
            $query = "SELECT clave, valor FROM configuracion WHERE clave IN ('whatsapp_api_url', 'whatsapp_token', 'dias_aviso_vencimiento', 'mensaje_vencimiento')";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $config = array();
            while ($row = $stmt->fetch()) {
                $config[$row['clave']] = $row['valor'];
            }
            
            return $config;
        } catch (Exception $e) {
            return array(
                'whatsapp_api_url' => '',
                'whatsapp_token' => '',
                'dias_aviso_vencimiento' => '7',
                'mensaje_vencimiento' => 'Hola {nombre}, tu servicio {servicio} vence el {fecha}. Por favor renueva tu suscripción.'
            );
        }
    }
    
    /**
     * Actualizar configuración de WhatsApp
     */
    public function updateConfig($config) {
        try {
            $this->conn->beginTransaction();
            
            foreach ($config as $clave => $valor) {
                $query = "UPDATE configuracion SET valor = :valor WHERE clave = :clave";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':valor', $valor);
                $stmt->bindParam(':clave', $clave);
                $stmt->execute();
            }
            
            $this->conn->commit();
            return array('success' => true, 'message' => 'Configuración actualizada exitosamente');
        } catch (Exception $e) {
            $this->conn->rollBack();
            return array('success' => false, 'message' => 'Error actualizando configuración: ' . $e->getMessage());
        }
    }
    
    /**
     * Probar conexión con WhatsApp API
     */
    public function testConnection() {
        $config = $this->getConfig();
        
        if (empty($config['whatsapp_api_url']) || empty($config['whatsapp_token'])) {
            return array(
                'success' => false,
                'message' => 'Configuración de WhatsApp incompleta'
            );
        }
        
        // Aquí puedes agregar una llamada de prueba a tu API de WhatsApp
        // Por ejemplo, enviar un mensaje de prueba
        
        return array(
            'success' => true,
            'message' => 'Configuración válida (prueba de conexión no implementada)'
        );
    }
}

// Si se accede directamente a este archivo, mostrar la configuración
if (basename($_SERVER['PHP_SELF']) == 'whatsapp.php') {
    header('Content-Type: application/json');
    
    $whatsapp = new WhatsAppConfig();
    $config = $whatsapp->getConfig();
    
    echo json_encode(array(
        'success' => true,
        'data' => $config
    ));
}
?>
