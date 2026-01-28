-- Corregir datos de funcionarios con tildes
-- Ejecutar este script en phpMyAdmin o consola MySQL

-- Primero, asegurarse de que la tabla tiene la codificación correcta
ALTER TABLE funcionarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Actualizar los datos con los caracteres correctos
UPDATE funcionarios SET nombre = 'María', apellido_paterno = 'González' WHERE id = 1;
UPDATE funcionarios SET apellido_paterno = 'Pérez' WHERE id = 2;
UPDATE funcionarios SET apellido_paterno = 'Martínez' WHERE id = 3;
UPDATE funcionarios SET cargo = 'Asesor Jurídico' WHERE id = 4;

-- Verificar otras tablas que puedan tener el mismo problema
ALTER TABLE usuarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE cometidos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE historial_cometidos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE permisos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE estados_documento CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
