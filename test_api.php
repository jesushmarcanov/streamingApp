<?php
/**
 * Archivo de prueba para verificar que las APIs funcionan correctamente
 * Este archivo se puede eliminar después de verificar que todo funciona
 */

// Incluir configuración de base de datos
require_once 'config/database.php';

echo "<h1>Prueba del Sistema de Gestión de Streaming</h1>";

// Probar conexión a la base de datos
echo "<h2>1. Prueba de Conexión a Base de Datos</h2>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "✅ Conexión a la base de datos exitosa<br>";
        
        // Verificar que las tablas existen
        $tables = ['clientes', 'proveedores', 'servicios', 'notificaciones', 'configuracion'];
        foreach ($tables as $table) {
            $query = "SHOW TABLES LIKE '$table'";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo "✅ Tabla '$table' existe<br>";
            } else {
                echo "❌ Tabla '$table' NO existe<br>";
            }
        }
    } else {
        echo "❌ Error de conexión a la base de datos<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Probar API de clientes
echo "<h2>2. Prueba de API de Clientes</h2>";
echo "<h3>Crear cliente de prueba:</h3>";

$cliente_data = array(
    'nombre' => 'Juan',
    'apellido' => 'Pérez',
    'telefono' => '1234567890',
    'email' => 'juan@ejemplo.com',
    'direccion' => 'Calle 123, Ciudad'
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/streamingApp/api/clientes.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cliente_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Código HTTP: $http_code<br>";
echo "Respuesta: " . $response . "<br>";

if ($http_code == 200) {
    $result = json_decode($response, true);
    if ($result['success']) {
        echo "✅ Cliente creado exitosamente<br>";
        $cliente_id = $result['id'];
    } else {
        echo "❌ Error creando cliente: " . $result['message'] . "<br>";
    }
} else {
    echo "❌ Error en la petición HTTP<br>";
}

echo "<h3>Obtener todos los clientes:</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/streamingApp/api/clientes.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Código HTTP: $http_code<br>";
echo "Respuesta: " . $response . "<br>";

if ($http_code == 200) {
    $result = json_decode($response, true);
    if ($result['success']) {
        echo "✅ Clientes obtenidos exitosamente. Total: " . count($result['data']) . "<br>";
    } else {
        echo "❌ Error obteniendo clientes: " . $result['message'] . "<br>";
    }
} else {
    echo "❌ Error en la petición HTTP<br>";
}

echo "<hr>";

// Probar API de proveedores
echo "<h2>3. Prueba de API de Proveedores</h2>";
echo "<h3>Crear proveedor de prueba:</h3>";

$proveedor_data = array(
    'nombre' => 'Netflix Inc.',
    'contacto' => 'Atención al Cliente',
    'telefono' => '800-123-4567',
    'email' => 'support@netflix.com',
    'direccion' => '100 Winchester Circle, Los Gatos, CA 95032'
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/streamingApp/api/proveedores.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($proveedor_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Código HTTP: $http_code<br>";
echo "Respuesta: " . $response . "<br>";

if ($http_code == 200) {
    $result = json_decode($response, true);
    if ($result['success']) {
        echo "✅ Proveedor creado exitosamente<br>";
        $proveedor_id = $result['id'];
    } else {
        echo "❌ Error creando proveedor: " . $result['message'] . "<br>";
    }
} else {
    echo "❌ Error en la petición HTTP<br>";
}

echo "<hr>";

// Probar API de servicios (si tenemos cliente y proveedor)
if (isset($cliente_id) && isset($proveedor_id)) {
    echo "<h2>4. Prueba de API de Servicios</h2>";
    echo "<h3>Crear servicio de prueba:</h3>";

    $servicio_data = array(
        'cliente_id' => $cliente_id,
        'proveedor_id' => $proveedor_id,
        'nombre_servicio' => 'Netflix Premium',
        'tipo_servicio' => 'Netflix',
        'precio_mensual' => 15.99,
        'fecha_inicio' => date('Y-m-d'),
        'fecha_vencimiento' => date('Y-m-d', strtotime('+1 month')),
        'estado' => 'Activo',
        'observaciones' => 'Servicio de prueba'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/streamingApp/api/servicios.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($servicio_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Código HTTP: $http_code<br>";
    echo "Respuesta: " . $response . "<br>";

    if ($http_code == 200) {
        $result = json_decode($response, true);
        if ($result['success']) {
            echo "✅ Servicio creado exitosamente<br>";
        } else {
            echo "❌ Error creando servicio: " . $result['message'] . "<br>";
        }
    } else {
        echo "❌ Error en la petición HTTP<br>";
    }
} else {
    echo "<h2>4. Prueba de API de Servicios</h2>";
    echo "❌ No se puede probar servicios sin cliente y proveedor<br>";
}

echo "<hr>";

echo "<h2>5. Resumen</h2>";
echo "<p>Si todas las pruebas muestran ✅, el sistema está funcionando correctamente.</p>";
echo "<p>Si hay errores ❌, revisa:</p>";
echo "<ul>";
echo "<li>Que la base de datos esté creada e importada</li>";
echo "<li>Que el servidor web esté ejecutándose</li>";
echo "<li>Que las rutas de los archivos sean correctas</li>";
echo "<li>Que no haya errores de PHP en los logs</li>";
echo "</ul>";

echo "<p><strong>Nota:</strong> Este archivo se puede eliminar después de verificar que todo funciona.</p>";
?>
