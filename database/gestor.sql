-- =====================================================
-- BASE DE DATOS: GESTOR DOCUMENTAL
-- Asociación de Municipios
-- =====================================================

CREATE DATABASE IF NOT EXISTS gestor CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE gestor;

-- =====================================================
-- TABLA: roles
-- =====================================================
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: permisos
-- =====================================================
CREATE TABLE permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    clave VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: roles_permisos (relación muchos a muchos)
-- =====================================================
CREATE TABLE roles_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rol_permiso (rol_id, permiso_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: funcionarios
-- =====================================================
CREATE TABLE funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(12) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    apellido_paterno VARCHAR(100) NOT NULL,
    apellido_materno VARCHAR(100),
    cargo VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    activo TINYINT(1) DEFAULT 1,
    es_secretario_ejecutivo TINYINT(1) DEFAULT 0,
    es_subrogante TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: usuarios
-- =====================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol_id INT NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    ultimo_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: usuarios_permisos (permisos adicionales por usuario)
-- =====================================================
CREATE TABLE usuarios_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    permiso_id INT NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_permiso (usuario_id, permiso_id)
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: estados_documento
-- =====================================================
CREATE TABLE estados_documento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    color VARCHAR(20) DEFAULT '#6c757d',
    descripcion VARCHAR(255),
    orden INT DEFAULT 0
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: cometidos
-- =====================================================
CREATE TABLE cometidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_cometido VARCHAR(20) UNIQUE,
    
    -- Funcionario que realiza el cometido
    funcionario_id INT NOT NULL,
    
    -- Usuario que crea el cometido
    creado_por INT NOT NULL,
    
    -- Autoridad que dispone (Secretario Ejecutivo o subrogante)
    autoridad_id INT,
    
    -- Objetivo del cometido
    objetivo TEXT NOT NULL,
    
    -- Lugar del cometido
    ciudad VARCHAR(100) NOT NULL,
    comuna VARCHAR(100) NOT NULL,
    lugar_descripcion TEXT,
    
    -- Fecha y duración
    fecha_inicio DATE NOT NULL,
    fecha_termino DATE NOT NULL,
    horario_inicio TIME,
    horario_termino TIME,
    
    -- Medio de traslado
    medio_traslado ENUM('vehiculo_asociacion', 'vehiculo_particular', 'transporte_publico') NOT NULL,
    patente_vehiculo VARCHAR(10),
    
    -- Financiamiento
    viatico DECIMAL(10,2) DEFAULT 0,
    
    -- Carácter del cometido (JSON para múltiples opciones)
    dentro_comuna TINYINT(1) DEFAULT 1,
    dentro_jornada TINYINT(1) DEFAULT 1,
    con_costo TINYINT(1) DEFAULT 0,
    
    -- Estado y fechas
    estado_id INT NOT NULL DEFAULT 1,
    fecha_envio_autorizacion DATETIME,
    fecha_autorizacion DATETIME,
    observaciones_rechazo TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (autoridad_id) REFERENCES funcionarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (estado_id) REFERENCES estados_documento(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: historial_cometidos
-- =====================================================
CREATE TABLE historial_cometidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cometido_id INT NOT NULL,
    usuario_id INT NOT NULL,
    accion VARCHAR(100) NOT NULL,
    estado_anterior_id INT,
    estado_nuevo_id INT,
    observaciones TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (cometido_id) REFERENCES cometidos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    FOREIGN KEY (estado_anterior_id) REFERENCES estados_documento(id) ON DELETE SET NULL,
    FOREIGN KEY (estado_nuevo_id) REFERENCES estados_documento(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- TABLA: configuracion
-- =====================================================
CREATE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    descripcion VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- DATOS INICIALES
-- =====================================================

-- Roles
INSERT INTO roles (nombre, descripcion) VALUES
('Administrador', 'Acceso total al sistema'),
('Secretario Ejecutivo', 'Puede autorizar/rechazar documentos'),
('Funcionario', 'Usuario básico, puede crear cometidos');

-- Permisos
INSERT INTO permisos (nombre, clave, descripcion) VALUES
('Ver Dashboard', 'dashboard_ver', 'Acceso al panel principal'),
('Crear Cometidos', 'cometidos_crear', 'Puede crear nuevos cometidos'),
('Editar Cometidos', 'cometidos_editar', 'Puede editar cometidos propios'),
('Ver Cometidos', 'cometidos_ver', 'Puede ver listado de cometidos'),
('Autorizar Cometidos', 'cometidos_autorizar', 'Puede autorizar/rechazar cometidos'),
('Gestionar Funcionarios', 'funcionarios_gestionar', 'CRUD de funcionarios'),
('Gestionar Usuarios', 'usuarios_gestionar', 'CRUD de usuarios'),
('Ver Reportes', 'reportes_ver', 'Acceso a reportes'),
('Configuración Sistema', 'configuracion', 'Acceso a configuración del sistema');

-- Asignar permisos a roles
-- Administrador (todos los permisos)
INSERT INTO roles_permisos (rol_id, permiso_id) 
SELECT 1, id FROM permisos;

-- Secretario Ejecutivo
INSERT INTO roles_permisos (rol_id, permiso_id) 
SELECT 2, id FROM permisos WHERE clave IN ('dashboard_ver', 'cometidos_ver', 'cometidos_autorizar', 'reportes_ver');

-- Funcionario
INSERT INTO roles_permisos (rol_id, permiso_id) 
SELECT 3, id FROM permisos WHERE clave IN ('dashboard_ver', 'cometidos_crear', 'cometidos_editar', 'cometidos_ver');

-- Estados de documento
INSERT INTO estados_documento (nombre, color, descripcion, orden) VALUES
('Borrador', '#6c757d', 'Documento en edición', 1),
('Pendiente Autorización', '#ffc107', 'Enviado para autorización', 2),
('Autorizado', '#28a745', 'Documento autorizado', 3),
('Rechazado', '#dc3545', 'Documento rechazado', 4);

-- Configuración inicial
INSERT INTO configuracion (clave, valor, descripcion) VALUES
('nombre_institucion', 'Asociación de Municipios', 'Nombre de la institución'),
('correlativo_cometido', '1', 'Número correlativo para cometidos'),
('anio_correlativo', '2026', 'Año del correlativo actual');

-- Funcionarios de ejemplo
INSERT INTO funcionarios (rut, nombre, apellido_paterno, apellido_materno, cargo, email, es_secretario_ejecutivo) VALUES
('12345678-9', 'María', 'González', 'López', 'Secretaria Ejecutiva', 'secretaria@asociacion.cl', 1),
('11111111-1', 'Juan', 'Pérez', 'Soto', 'Analista', 'jperez@asociacion.cl', 0),
('22222222-2', 'Ana', 'Martínez', 'Rojas', 'Coordinadora', 'amartinez@asociacion.cl', 0),
('33333333-3', 'Carlos', 'Silva', 'Muñoz', 'Asesor Jurídico', 'csilva@asociacion.cl', 0);

-- Usuario administrador (password: admin123)
INSERT INTO usuarios (funcionario_id, username, password, rol_id) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Usuario secretario ejecutivo (password: secretario123)
INSERT INTO usuarios (funcionario_id, username, password, rol_id) VALUES
(1, 'secretario', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2);

-- Usuario funcionario (password: funcionario123)
INSERT INTO usuarios (funcionario_id, username, password, rol_id) VALUES
(2, 'jperez', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3);

-- Agregar permiso de crear cometidos al funcionario
INSERT INTO usuarios_permisos (usuario_id, permiso_id) 
SELECT 3, id FROM permisos WHERE clave = 'cometidos_crear';
