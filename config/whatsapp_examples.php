<?php
/**
 * Ejemplos de configuración para diferentes APIs de WhatsApp
 * 
 * Este archivo contiene ejemplos de cómo configurar el sistema
 * con diferentes proveedores de API de WhatsApp
 */

// ========================================
// TWILIO WHATSAPP API
// ========================================
/*
Configuración para Twilio:
1. Crear cuenta en https://www.twilio.com/
2. Obtener Account SID y Auth Token
3. Configurar WhatsApp Sandbox o usar WhatsApp Business API

SQL para configurar:
UPDATE configuracion SET valor = 'https://api.twilio.com/2010-04-01/Accounts/{AccountSID}/Messages.json' WHERE clave = 'whatsapp_api_url';
UPDATE configuracion SET valor = 'Basic ' . base64_encode('{AccountSID}:{AuthToken}') WHERE clave = 'whatsapp_token';

Ejemplo de envío con Twilio:
$data = array(
    'From' => 'whatsapp:+14155238886', // Número de Twilio
    'To' => 'whatsapp:' . $telefono,
    'Body' => $mensaje
);
*/

// ========================================
// META WHATSAPP BUSINESS API
// ========================================
/*
Configuración para Meta (Facebook):
1. Crear app en https://developers.facebook.com/
2. Configurar WhatsApp Business API
3. Obtener Access Token

SQL para configurar:
UPDATE configuracion SET valor = 'https://graph.facebook.com/v17.0/{phone_number_id}/messages' WHERE clave = 'whatsapp_api_url';
UPDATE configuracion SET valor = '{access_token}' WHERE clave = 'whatsapp_token';

Ejemplo de envío con Meta:
$data = array(
    'messaging_product' => 'whatsapp',
    'to' => $telefono,
    'type' => 'text',
    'text' => array('body' => $mensaje)
);
*/

// ========================================
// WHATSAPP BUSINESS API (OTROS PROVEEDORES)
// ========================================
/*
Configuración para otros proveedores como:
- 360Dialog
- MessageBird
- Infobip
- etc.

SQL para configurar:
UPDATE configuracion SET valor = 'https://api.proveedor.com/whatsapp/send' WHERE clave = 'whatsapp_api_url';
UPDATE configuracion SET valor = '{api_key}' WHERE clave = 'whatsapp_token';
*/

// ========================================
// FUNCIÓN DE EJEMPLO PARA ENVÍO
// ========================================
function enviarWhatsAppEjemplo($telefono, $mensaje, $proveedor = 'twilio') {
    $config = array(
        'whatsapp_api_url' => '',
        'whatsapp_token' => ''
    );
    
    switch ($proveedor) {
        case 'twilio':
            $config['whatsapp_api_url'] = 'https://api.twilio.com/2010-04-01/Accounts/{AccountSID}/Messages.json';
            $config['whatsapp_token'] = 'Basic ' . base64_encode('{AccountSID}:{AuthToken}');
            
            $data = array(
                'From' => 'whatsapp:+14155238886',
                'To' => 'whatsapp:' . $telefono,
                'Body' => $mensaje
            );
            break;
            
        case 'meta':
            $config['whatsapp_api_url'] = 'https://graph.facebook.com/v17.0/{phone_number_id}/messages';
            $config['whatsapp_token'] = '{access_token}';
            
            $data = array(
                'messaging_product' => 'whatsapp',
                'to' => $telefono,
                'type' => 'text',
                'text' => array('body' => $mensaje)
            );
            break;
            
        default:
            return array('success' => false, 'message' => 'Proveedor no soportado');
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['whatsapp_api_url']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: ' . $config['whatsapp_token']
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 || $http_code == 201) {
        return array('success' => true, 'message' => 'Mensaje enviado exitosamente');
    } else {
        return array('success' => false, 'message' => 'Error al enviar mensaje: ' . $response);
    }
}

// ========================================
// CONFIGURACIÓN DE MENSAJES
// ========================================
/*
Ejemplos de mensajes personalizados:

Mensaje simple:
UPDATE configuracion SET valor = 'Hola {nombre}, tu servicio {servicio} vence el {fecha}. ¡Renueva ahora!' WHERE clave = 'mensaje_vencimiento';

Mensaje con emojis:
UPDATE configuracion SET valor = '🔔 Hola {nombre}! 📺 Tu servicio {servicio} vence el {fecha}. ⏰ ¡No olvides renovarlo!' WHERE clave = 'mensaje_vencimiento';

Mensaje con información adicional:
UPDATE configuracion SET valor = 'Hola {nombre}, tu suscripción a {servicio} vence el {fecha}. Para continuar disfrutando del servicio, por favor renueva tu suscripción. ¡Gracias por confiar en nosotros!' WHERE clave = 'mensaje_vencimiento';

Configurar días de anticipación:
UPDATE configuracion SET valor = '3' WHERE clave = 'dias_aviso_vencimiento'; -- 3 días antes
UPDATE configuracion SET valor = '7' WHERE clave = 'dias_aviso_vencimiento'; -- 1 semana antes
UPDATE configuracion SET valor = '1' WHERE clave = 'dias_aviso_vencimiento'; -- 1 día antes
*/

// ========================================
// SCRIPT DE CONFIGURACIÓN AUTOMÁTICA
// ========================================
/*
Para configurar automáticamente el sistema, puedes ejecutar este SQL:

-- Configuración básica
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('whatsapp_api_url', 'https://api.ejemplo.com/whatsapp/send', 'URL de la API de WhatsApp'),
('whatsapp_token', 'tu_token_aqui', 'Token de acceso para WhatsApp'),
('dias_aviso_vencimiento', '7', 'Días de anticipación para avisar vencimientos'),
('mensaje_vencimiento', 'Hola {nombre}, tu servicio {servicio} vence el {fecha}. Por favor renueva tu suscripción.', 'Mensaje template para vencimientos')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- Verificar configuración
SELECT * FROM configuracion WHERE clave LIKE 'whatsapp%' OR clave LIKE 'dias_%' OR clave LIKE 'mensaje_%';
*/
?>
