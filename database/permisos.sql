-- =====================================================
-- Módulo de Permisos y Feriados Legales
-- =====================================================

-- Tabla para configurar días de feriado legal por funcionario por año
CREATE TABLE IF NOT EXISTS feriados_legales_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    anio INT NOT NULL,
    dias_asignados DECIMAL(4,1) NOT NULL DEFAULT 15,
    dias_utilizados DECIMAL(4,1) NOT NULL DEFAULT 0,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_por INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_funcionario_anio (funcionario_id, anio),
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    FOREIGN KEY (asignado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para permisos administrativos por funcionario por año (6 días, se reinicia cada año)
CREATE TABLE IF NOT EXISTS permisos_administrativos_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    anio INT NOT NULL,
    dias_disponibles DECIMAL(3,1) NOT NULL DEFAULT 6.0,
    dias_utilizados DECIMAL(3,1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_funcionario_anio (funcionario_id, anio),
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de solicitud de permiso
CREATE TABLE IF NOT EXISTS tipos_permiso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar tipos de permiso
INSERT INTO tipos_permiso (codigo, nombre, descripcion) VALUES
('feriado_legal', 'Feriado Legal (Vacaciones)', 'Días de vacaciones anuales asignados al funcionario'),
('permiso_administrativo', 'Permiso Administrativo', 'Permisos administrativos (6 días anuales, medio día o día completo)');

-- Estados para solicitudes de permiso
CREATE TABLE IF NOT EXISTS estados_permiso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#6c757d',
    descripcion TEXT,
    orden INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar estados
INSERT INTO estados_permiso (id, nombre, color, orden) VALUES
(1, 'Borrador', '#6c757d', 1),
(2, 'Pendiente', '#ffc107', 2),
(3, 'Autorizado', '#28a745', 3),
(4, 'Rechazado', '#dc3545', 4),
(5, 'Anulado', '#343a40', 5);

-- Tabla principal de solicitudes de permiso
CREATE TABLE IF NOT EXISTS solicitudes_permiso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_solicitud VARCHAR(20) NOT NULL UNIQUE,
    tipo_permiso_id INT NOT NULL,
    funcionario_id INT NOT NULL,
    solicitado_por INT NOT NULL,
    
    -- Fechas del permiso
    fecha_inicio DATE NOT NULL,
    fecha_termino DATE NOT NULL,
    
    -- Para permisos administrativos: medio día o día completo
    es_medio_dia TINYINT(1) DEFAULT 0,
    medio_dia_tipo ENUM('manana', 'tarde') NULL,
    
    -- Cantidad de días solicitados
    dias_solicitados DECIMAL(4,1) NOT NULL,
    
    -- Año del cual se descuentan los días (para feriado legal puede ser año anterior)
    anio_descuento INT NOT NULL,
    
    -- Motivo/observaciones
    motivo TEXT,
    
    -- Estado y workflow
    estado_id INT NOT NULL DEFAULT 1,
    fecha_envio_autorizacion DATETIME NULL,
    fecha_autorizacion DATETIME NULL,
    autorizado_por INT NULL,
    observaciones_rechazo TEXT NULL,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tipo_permiso_id) REFERENCES tipos_permiso(id),
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id),
    FOREIGN KEY (solicitado_por) REFERENCES usuarios(id),
    FOREIGN KEY (estado_id) REFERENCES estados_permiso(id),
    FOREIGN KEY (autorizado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de solicitudes de permiso
CREATE TABLE IF NOT EXISTS historial_permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    usuario_id INT NOT NULL,
    accion VARCHAR(255) NOT NULL,
    estado_anterior_id INT NULL,
    estado_nuevo_id INT NULL,
    observaciones TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_permiso(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (estado_anterior_id) REFERENCES estados_permiso(id),
    FOREIGN KEY (estado_nuevo_id) REFERENCES estados_permiso(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar permisos para el módulo
INSERT INTO permisos (clave, nombre, descripcion) VALUES
('permisos_ver', 'Ver Permisos', 'Permite ver listado de permisos'),
('permisos_crear', 'Crear Permisos', 'Permite crear solicitudes de permiso'),
('permisos_editar', 'Editar Permisos', 'Permite editar solicitudes en borrador'),
('permisos_autorizar', 'Autorizar Permisos', 'Permite autorizar o rechazar solicitudes'),
('permisos_config', 'Configurar Feriados', 'Permite asignar días de feriado legal a funcionarios');

-- Asignar permisos al rol Administrador (id=1)
INSERT INTO roles_permisos (rol_id, permiso_id) 
SELECT 1, id FROM permisos WHERE clave LIKE 'permisos_%';

-- Asignar permisos básicos al rol Usuario (id=3) - solo ver y crear propios
INSERT INTO roles_permisos (rol_id, permiso_id) 
SELECT 3, id FROM permisos WHERE clave IN ('permisos_ver', 'permisos_crear', 'permisos_editar');

-- Asignar permiso de autorizar al Secretario Ejecutivo (id=2)
INSERT INTO roles_permisos (rol_id, permiso_id) 
SELECT 2, id FROM permisos WHERE clave LIKE 'permisos_%';

-- Evento programado para reiniciar permisos administrativos cada año
-- (Ejecutar manualmente o configurar en el servidor)
-- Este es un ejemplo, en producción se puede usar un CRON job

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS reiniciar_permisos_administrativos_anual()
BEGIN
    DECLARE nuevo_anio INT;
    SET nuevo_anio = YEAR(CURDATE());
    
    -- Insertar configuración de permisos administrativos para todos los funcionarios activos
    -- que no tengan configuración para el nuevo año
    INSERT INTO permisos_administrativos_config (funcionario_id, anio, dias_disponibles, dias_utilizados)
    SELECT f.id, nuevo_anio, 6.0, 0
    FROM funcionarios f
    WHERE f.activo = 1
    AND NOT EXISTS (
        SELECT 1 FROM permisos_administrativos_config pac 
        WHERE pac.funcionario_id = f.id AND pac.anio = nuevo_anio
    );
END //
DELIMITER ;

-- Ejecutar el procedimiento para el año actual
CALL reiniciar_permisos_administrativos_anual();
