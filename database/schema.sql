-- Base de datos para gestión de servicios de streaming
CREATE DATABASE IF NOT EXISTS streaming_services;
USE streaming_services;

-- Tabla de usuarios para autenticación
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    rol ENUM('admin', 'usuario') DEFAULT 'usuario',
    activo BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(64) NULL,
    password_reset_token VARCHAR(64) NULL,
    password_reset_expires DATETIME NULL,
    ultimo_acceso TIMESTAMP NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    direccion TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE
);

-- Tabla de servicios de streaming
CREATE TABLE servicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    proveedor_id INT NOT NULL,
    nombre_servicio VARCHAR(100) NOT NULL,
    tipo_servicio ENUM('Netflix', 'Disney+', 'Amazon Prime', 'HBO Max', 'Spotify', 'Apple Music', 'YouTube Premium', 'Otro') NOT NULL,
    precio_mensual DECIMAL(10,2) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    estado ENUM('Activo', 'Suspendido', 'Cancelado') DEFAULT 'Activo',
    observaciones TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE CASCADE
);

-- Tabla de notificaciones
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    servicio_id INT NOT NULL,
    cliente_id INT NOT NULL,
    tipo_notificacion ENUM('Vencimiento', 'Recordatorio', 'Pago') NOT NULL,
    mensaje TEXT NOT NULL,
    fecha_envio TIMESTAMP NULL,
    estado ENUM('Pendiente', 'Enviada', 'Fallida') DEFAULT 'Pendiente',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Tabla de configuración
CREATE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) UNIQUE NOT NULL,
    valor TEXT,
    descripcion TEXT,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar configuración inicial
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('whatsapp_api_url', '', 'URL de la API de WhatsApp'),
('whatsapp_token', '', 'Token de acceso para WhatsApp'),
('dias_aviso_vencimiento', '7', 'Días de anticipación para avisar vencimientos'),
('mensaje_vencimiento', 'Hola {nombre}, tu servicio {servicio} vence el {fecha}. Por favor renueva tu suscripción.', 'Mensaje template para vencimientos');

-- Insertar usuario administrador por defecto (password: admin123)
INSERT INTO usuarios (username, email, password_hash, nombre, apellido, rol, email_verified) VALUES
('admin', 'admin@streamingapp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'Sistema', 'admin', 1);

-- Insertar algunos proveedores de ejemplo
INSERT INTO proveedores (nombre, contacto, telefono, email) VALUES
('Netflix Inc.', 'Atención al Cliente', '+1-800-123-4567', 'support@netflix.com'),
('Disney+', 'Soporte Técnico', '+1-800-987-6543', 'help@disneyplus.com'),
('Amazon Prime Video', 'Customer Service', '+1-888-280-4331', 'prime-video-support@amazon.com'),
('HBO Max', 'Soporte', '+1-855-442-6629', 'support@hbomax.com'),
('Spotify', 'Atención al Usuario', '+1-877-778-3687', 'support@spotify.com');

-- Tabla de usuarios del sistema
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'usuario') DEFAULT 'usuario',
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL
);

-- Usuario administrador por defecto (contraseña: admin123)
INSERT INTO usuarios (nombre, username, email, password, rol) VALUES
('Administrador', 'admin', 'admin@streamingapp.com', '$2y$10$zwYzxbK3tbZcPpaVhD8q6eH7W5uCq.pI/LhVbKkA6QSpFtIyO/Y2W', 'admin');
