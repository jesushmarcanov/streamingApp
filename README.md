# Sistema de Gestión de Servicios de Streaming

Un sistema completo para gestionar servicios de streaming de clientes, proveedores y notificaciones automáticas por WhatsApp.

## Características

- ✅ **CRUD de Clientes**: Gestión completa de información de clientes
- ✅ **CRUD de Proveedores**: Administración de proveedores de servicios
- ✅ **CRUD de Servicios**: Control de servicios contratados por clientes
- ✅ **Notificaciones WhatsApp**: Envío automático de recordatorios de vencimiento
- ✅ **Dashboard**: Resumen estadístico del negocio
- ✅ **Interfaz Responsiva**: Diseño moderno y adaptable a dispositivos móviles

## Tecnologías Utilizadas

### Backend
- **PHP 7.4+** con PDO para base de datos
- **MySQL 5.7+** como base de datos
- **API REST** para comunicación con el frontend

### Frontend
- **HTML5** semántico
- **CSS3** con diseño responsivo y animaciones
- **JavaScript ES6+** vanilla (sin frameworks)
- **Font Awesome** para iconos

## Instalación

### Requisitos Previos
- XAMPP, WAMP, LAMP o servidor web con PHP
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Navegador web moderno

### Pasos de Instalación

1. **Clonar o descargar el proyecto**
   ```bash
   # Si usas Git
   git clone [url-del-repositorio]
   
   # O descarga y extrae el ZIP en tu carpeta de servidor web
   # Para XAMPP: C:\xampp\htdocs\streamingApp
   ```

2. **Configurar la base de datos**
   - Abre phpMyAdmin (http://localhost/phpmyadmin)
   - Importa el archivo `database/schema.sql` para crear la base de datos y tablas
   - O ejecuta manualmente el SQL desde el archivo

3. **Configurar la conexión a la base de datos**
   - Edita el archivo `config/database.php`
   - Ajusta los parámetros de conexión según tu configuración:
   ```php
   private $host = 'localhost';
   private $db_name = 'streaming_services';
   private $username = 'root';
   private $password = ''; // Tu contraseña de MySQL
   ```

4. **Configurar WhatsApp (Opcional)**
   - Edita la tabla `configuracion` en la base de datos
   - Actualiza los valores para tu API de WhatsApp:
   ```sql
   UPDATE configuracion SET valor = 'https://tu-api-whatsapp.com/send' WHERE clave = 'whatsapp_api_url';
   UPDATE configuracion SET valor = 'tu_token_aqui' WHERE clave = 'whatsapp_token';
   ```

5. **Acceder a la aplicación**
   - Abre tu navegador
   - Ve a `http://localhost/streamingApp`
   - ¡Listo! Ya puedes usar el sistema

## Uso del Sistema

### Dashboard
- Vista general con estadísticas del negocio
- Servicios próximos a vencer
- Botón para generar notificaciones automáticas

### Gestión de Clientes
- Agregar, editar y eliminar clientes
- Información completa: nombre, teléfono, email, dirección
- Soft delete (eliminación lógica)

### Gestión de Proveedores
- Administrar proveedores de servicios de streaming
- Información de contacto y dirección
- Soft delete (eliminación lógica)

### Gestión de Servicios
- Crear servicios asociados a clientes y proveedores
- Tipos de servicios predefinidos (Netflix, Disney+, etc.)
- Control de fechas de vencimiento y precios
- Estados: Activo, Suspendido, Cancelado

### Notificaciones
- Generación automática de notificaciones para servicios próximos a vencer
- Envío masivo de notificaciones pendientes
- Historial de notificaciones enviadas

## Configuración de WhatsApp

Para habilitar las notificaciones por WhatsApp, necesitas:

1. **API de WhatsApp**: Usar servicios como:
   - WhatsApp Business API
   - Twilio WhatsApp API
   - Meta for Developers (WhatsApp Business Platform)

2. **Configurar en la base de datos**:
   ```sql
   UPDATE configuracion 
   SET valor = 'https://api.whatsapp.com/send' 
   WHERE clave = 'whatsapp_api_url';
   
   UPDATE configuracion 
   SET valor = 'tu_token_de_acceso' 
   WHERE clave = 'whatsapp_token';
   ```

3. **Personalizar mensajes**:
   ```sql
   UPDATE configuracion 
   SET valor = 'Hola {nombre}, tu servicio {servicio} vence el {fecha}. ¡Renueva ahora!' 
   WHERE clave = 'mensaje_vencimiento';
   ```

## Estructura del Proyecto

```
streamingApp/
├── api/                    # APIs REST
│   ├── clientes.php       # CRUD de clientes
│   ├── proveedores.php    # CRUD de proveedores
│   ├── servicios.php      # CRUD de servicios
│   └── notificaciones.php # Sistema de notificaciones
├── assets/
│   ├── css/
│   │   └── style.css      # Estilos principales
│   └── js/
│       └── app.js         # JavaScript principal
├── config/
│   └── database.php       # Configuración de BD
├── database/
│   └── schema.sql         # Esquema de base de datos
├── index.html             # Página principal
└── README.md             # Este archivo
```

## Características Técnicas

### Seguridad
- Validación de datos en frontend y backend
- Uso de PDO para prevenir inyección SQL
- Headers CORS configurados
- Soft delete para preservar datos

### Rendimiento
- Consultas optimizadas con JOINs
- Carga asíncrona de datos
- Interfaz responsiva
- Animaciones CSS suaves

### Mantenibilidad
- Código modular y bien documentado
- Separación clara de responsabilidades
- APIs RESTful consistentes
- Estructura de archivos organizada

## Solución de Problemas

### Error de conexión a la base de datos
- Verifica que MySQL esté ejecutándose
- Confirma los datos de conexión en `config/database.php`
- Asegúrate de que la base de datos existe

### Notificaciones no se envían
- Verifica la configuración de WhatsApp en la tabla `configuracion`
- Confirma que la API de WhatsApp esté funcionando
- Revisa los logs del servidor para errores

### Problemas de permisos
- Asegúrate de que el servidor web tenga permisos de lectura/escritura
- Verifica la configuración de PHP para archivos

## Contribuciones

Para contribuir al proyecto:
1. Fork el repositorio
2. Crea una rama para tu feature
3. Realiza tus cambios
4. Envía un Pull Request

## Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo LICENSE para más detalles.

## Soporte

Para soporte técnico o preguntas:
- Crea un issue en el repositorio
- Contacta al desarrollador

---

**Desarrollado con ❤️ para la gestión eficiente de servicios de streaming**
