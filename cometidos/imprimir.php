<?php
/**
 * Generar documento de cometido para imprimir/PDF
 * Usa el diálogo de impresión del navegador (Ctrl+P -> Guardar como PDF)
 */
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('cometidos_ver');

$db = Database::getInstance();
$user = Session::getUser();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Cometido no válido.');
}

// Obtener cometido
$cometido = $db->selectOne(
    "SELECT c.*, 
            a.nombre as auto_nombre, a.apellido_paterno as auto_apellido, 
            a.apellido_materno as auto_apellido_m, a.cargo as auto_cargo,
            u.username as creado_por_username,
            e.nombre as estado_nombre
     FROM cometidos c
     LEFT JOIN funcionarios a ON c.autoridad_id = a.id
     INNER JOIN usuarios u ON c.creado_por = u.id
     INNER JOIN estados_documento e ON c.estado_id = e.id
     WHERE c.id = :id",
    ['id' => $id]
);

if (!$cometido) {
    die('Cometido no encontrado.');
}

// Solo se puede imprimir si está autorizado
if ($cometido['estado_id'] != 3) {
    die('Solo se pueden imprimir cometidos autorizados.');
}

// Obtener todos los funcionarios del cometido
$funcionariosCometido = $db->select(
    "SELECT f.* FROM funcionarios f
     INNER JOIN cometidos_funcionarios cf ON f.id = cf.funcionario_id
     WHERE cf.cometido_id = :id
     ORDER BY cf.id",
    ['id' => $id]
);

if (empty($funcionariosCometido)) {
    $funcionariosCometido = $db->select(
        "SELECT * FROM funcionarios WHERE id = :id",
        ['id' => $cometido['funcionario_id']]
    );
}

// Función para texto de medio traslado
function getMedioTraslado($tipo) {
    $medios = [
        'vehiculo_asociacion' => 'Vehículo de la Asociación',
        'vehiculo_particular' => 'Vehículo Particular',
        'transporte_publico' => 'Transporte Público / Bus / Avión'
    ];
    return $medios[$tipo] ?? $tipo;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cometido <?= e($cometido['numero_cometido']) ?></title>
    <style>
        @page {
            size: letter;
            margin: 2cm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }
        
        .documento {
            max-width: 21cm;
            margin: 0 auto;
            padding: 1cm;
        }
        
        /* Encabezado */
        .encabezado {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .logo-text {
            font-size: 14pt;
            font-weight: bold;
            color: #1a5276;
            margin-bottom: 5px;
        }
        
        .titulo-documento {
            font-size: 18pt;
            font-weight: bold;
            margin: 15px 0 5px;
            text-transform: uppercase;
        }
        
        .numero-cometido {
            font-size: 14pt;
            color: #666;
        }
        
        /* Secciones */
        .seccion {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .seccion-titulo {
            font-size: 11pt;
            font-weight: bold;
            color: #1a5276;
            background: #f0f0f0;
            padding: 5px 10px;
            margin-bottom: 10px;
            border-left: 4px solid #1a5276;
        }
        
        .seccion-contenido {
            padding: 0 10px;
        }
        
        /* Tabla de funcionarios */
        .tabla-funcionarios {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        
        .tabla-funcionarios th,
        .tabla-funcionarios td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        
        .tabla-funcionarios th {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        /* Grid de datos */
        .datos-grid {
            display: table;
            width: 100%;
        }
        
        .datos-row {
            display: table-row;
        }
        
        .datos-col {
            display: table-cell;
            padding: 5px 10px 5px 0;
            width: 50%;
        }
        
        .dato-label {
            font-size: 9pt;
            color: #666;
            margin-bottom: 2px;
        }
        
        .dato-valor {
            font-weight: bold;
        }
        
        /* Características */
        .caracteristicas {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 5px;
        }
        
        .caracteristica {
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 10pt;
        }
        
        .caracteristica-si {
            background: #d4edda;
            border: 1px solid #28a745;
        }
        
        .caracteristica-no {
            background: #fff3cd;
            border: 1px solid #ffc107;
        }
        
        /* Firmas */
        .firmas {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        
        .firmas-row {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }
        
        .firma-box {
            text-align: center;
            width: 45%;
        }
        
        .firma-linea {
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 40px;
        }
        
        .firma-nombre {
            font-weight: bold;
        }
        
        .firma-cargo {
            font-size: 10pt;
            color: #666;
        }
        
        /* Pie de página */
        .pie-pagina {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
        
        /* Ocultar elementos en impresión */
        .no-print {
            margin-bottom: 20px;
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .btn-imprimir {
            padding: 10px 30px;
            font-size: 14pt;
            background: #1a5276;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .btn-imprimir:hover {
            background: #154360;
        }
        
        .btn-volver {
            padding: 10px 30px;
            font-size: 14pt;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .documento {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-imprimir" onclick="window.print()">
            🖨️ Imprimir / Guardar como PDF
        </button>
        <a href="ver.php?id=<?= $id ?>" class="btn-volver">← Volver</a>
        <p style="margin-top: 10px; font-size: 10pt; color: #666;">
            Para guardar como PDF: Ctrl+P → Destino: "Guardar como PDF" → Guardar
        </p>
    </div>
    
    <div class="documento">
        <!-- Encabezado -->
        <div class="encabezado">
            <div class="logo-text">ASOCIACIÓN DE MUNICIPIOS</div>
            <div class="titulo-documento">Resolución de Cometido</div>
            <div class="numero-cometido">N° <?= e($cometido['numero_cometido']) ?></div>
        </div>
        
        <!-- 1. Funcionarios -->
        <div class="seccion">
            <div class="seccion-titulo">1. IDENTIFICACIÓN DEL/LOS FUNCIONARIO(S)</div>
            <div class="seccion-contenido">
                <table class="tabla-funcionarios">
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>RUT</th>
                            <th>Cargo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funcionariosCometido as $f): ?>
                        <tr>
                            <td><?= e($f['nombre'] . ' ' . $f['apellido_paterno'] . ' ' . $f['apellido_materno']) ?></td>
                            <td><?= e($f['rut']) ?></td>
                            <td><?= e($f['cargo']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 2. Autoridad -->
        <?php if ($cometido['auto_nombre']): ?>
        <div class="seccion">
            <div class="seccion-titulo">2. AUTORIDAD QUE DISPONE EL COMETIDO</div>
            <div class="seccion-contenido">
                <p><strong><?= e($cometido['auto_nombre'] . ' ' . $cometido['auto_apellido'] . ' ' . $cometido['auto_apellido_m']) ?></strong></p>
                <p><?= e($cometido['auto_cargo']) ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 3. Objetivo -->
        <div class="seccion">
            <div class="seccion-titulo">3. OBJETIVO DEL COMETIDO</div>
            <div class="seccion-contenido">
                <p><?= nl2br(e($cometido['objetivo'])) ?></p>
            </div>
        </div>
        
        <!-- 4. Lugar -->
        <div class="seccion">
            <div class="seccion-titulo">4. LUGAR DEL COMETIDO</div>
            <div class="seccion-contenido">
                <div class="datos-grid">
                    <div class="datos-row">
                        <div class="datos-col">
                            <div class="dato-label">Ciudad</div>
                            <div class="dato-valor"><?= e($cometido['ciudad']) ?></div>
                        </div>
                        <div class="datos-col">
                            <div class="dato-label">Comuna</div>
                            <div class="dato-valor"><?= e($cometido['comuna']) ?></div>
                        </div>
                    </div>
                </div>
                <?php if ($cometido['lugar_descripcion']): ?>
                <p style="margin-top: 10px;"><strong>Descripción:</strong> <?= e($cometido['lugar_descripcion']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 5. Fecha y Duración -->
        <div class="seccion">
            <div class="seccion-titulo">5. FECHA Y DURACIÓN</div>
            <div class="seccion-contenido">
                <div class="datos-grid">
                    <div class="datos-row">
                        <div class="datos-col">
                            <div class="dato-label">Fecha de Inicio</div>
                            <div class="dato-valor"><?= formatDate($cometido['fecha_inicio']) ?></div>
                        </div>
                        <div class="datos-col">
                            <div class="dato-label">Fecha de Término</div>
                            <div class="dato-valor"><?= formatDate($cometido['fecha_termino']) ?></div>
                        </div>
                    </div>
                    <?php if ($cometido['horario_inicio'] || $cometido['horario_termino']): ?>
                    <div class="datos-row">
                        <?php if ($cometido['horario_inicio']): ?>
                        <div class="datos-col">
                            <div class="dato-label">Horario de Inicio</div>
                            <div class="dato-valor"><?= e($cometido['horario_inicio']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($cometido['horario_termino']): ?>
                        <div class="datos-col">
                            <div class="dato-label">Horario de Término</div>
                            <div class="dato-valor"><?= e($cometido['horario_termino']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 6. Medio de Traslado -->
        <div class="seccion">
            <div class="seccion-titulo">6. MEDIO DE TRASLADO</div>
            <div class="seccion-contenido">
                <p class="dato-valor">
                    <?= getMedioTraslado($cometido['medio_traslado']) ?>
                    <?php if ($cometido['patente_vehiculo']): ?>
                        (Patente: <?= e($cometido['patente_vehiculo']) ?>)
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- 7. Financiamiento -->
        <div class="seccion">
            <div class="seccion-titulo">7. FINANCIAMIENTO</div>
            <div class="seccion-contenido">
                <p><strong>Viático asignado:</strong> <?= formatMoney($cometido['viatico']) ?></p>
            </div>
        </div>
        
        <!-- 8. Carácter del Cometido -->
        <div class="seccion">
            <div class="seccion-titulo">8. CARÁCTER DEL COMETIDO</div>
            <div class="seccion-contenido">
                <div class="caracteristicas">
                    <span class="caracteristica <?= $cometido['dentro_comuna'] ? 'caracteristica-si' : 'caracteristica-no' ?>">
                        <?= $cometido['dentro_comuna'] ? '✓ Dentro de la comuna' : '○ Fuera de la comuna' ?>
                    </span>
                    <span class="caracteristica <?= $cometido['dentro_jornada'] ? 'caracteristica-si' : 'caracteristica-no' ?>">
                        <?= $cometido['dentro_jornada'] ? '✓ Dentro de jornada laboral' : '○ Fuera de jornada laboral' ?>
                    </span>
                    <span class="caracteristica <?= !$cometido['con_costo'] ? 'caracteristica-si' : 'caracteristica-no' ?>">
                        <?= $cometido['con_costo'] ? '○ Con costo para la institución' : '✓ Sin costo para la institución' ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Firmas -->
        <div class="firmas">
            <div class="firmas-row">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">
                            <?php if ($cometido['auto_nombre']): ?>
                                <?= e($cometido['auto_nombre'] . ' ' . $cometido['auto_apellido']) ?>
                            <?php else: ?>
                                Secretario(a) Ejecutivo(a)
                            <?php endif; ?>
                        </div>
                        <div class="firma-cargo">
                            <?= e($cometido['auto_cargo'] ?? 'Secretario(a) Ejecutivo(a)') ?>
                        </div>
                    </div>
                </div>
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre">Funcionario(a)</div>
                        <div class="firma-cargo">Firma de recepción</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pie de página -->
        <div class="pie-pagina">
            Documento generado el <?= date('d/m/Y H:i') ?> | 
            Fecha de autorización: <?= formatDate($cometido['fecha_autorizacion']) ?>
        </div>
    </div>
    
    <script>
        // Auto-abrir diálogo de impresión
        window.onload = function() {
            // Descomentar la siguiente línea para abrir automáticamente el diálogo de impresión
            // window.print();
        };
    </script>
</body>
</html>
