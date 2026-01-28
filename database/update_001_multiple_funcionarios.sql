-- =====================================================
-- ACTUALIZACIÓN: Soporte para múltiples funcionarios por cometido
-- =====================================================

USE gestor;

-- Crear tabla intermedia para relación muchos a muchos
CREATE TABLE IF NOT EXISTS cometidos_funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cometido_id INT NOT NULL,
    funcionario_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cometido_id) REFERENCES cometidos(id) ON DELETE CASCADE,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_cometido_funcionario (cometido_id, funcionario_id)
) ENGINE=InnoDB;

-- Migrar datos existentes (si hay cometidos con funcionario_id)
INSERT IGNORE INTO cometidos_funcionarios (cometido_id, funcionario_id)
SELECT id, funcionario_id FROM cometidos WHERE funcionario_id IS NOT NULL;
