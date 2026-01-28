<?php
/**
 * Imprimir solicitud de permiso
 */
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('permisos_ver');

$db = Database::getInstance();
$user = Session::getUser();

// Verificar ID
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('No se especificó la solicitud.');
}

// Obtener solicitud
$solicitud = $db->selectOne(
    "SELECT sp.*, 
            tp.nombre as tipo_nombre, tp.codigo as tipo_codigo,
            ep.nombre as estado_nombre, ep.color as estado_color,
            f.nombre as func_nombre, f.apellido_paterno as func_apellido, 
            f.apellido_materno as func_apellido_m, f.rut as func_rut,
            f.cargo as func_cargo, f.departamento as func_departamento,
            u.username as solicitado_por_username,
            uf.nombre as solicitante_nombre, uf.apellido_paterno as solicitante_apellido,
            ua.username as autorizado_por_username,
            uaf.nombre as autorizador_nombre, uaf.apellido_paterno as autorizador_apellido
     FROM solicitudes_permiso sp
     INNER JOIN tipos_permiso tp ON sp.tipo_permiso_id = tp.id
     INNER JOIN estados_permiso ep ON sp.estado_id = ep.id
     INNER JOIN funcionarios f ON sp.funcionario_id = f.id
     INNER JOIN usuarios u ON sp.solicitado_por = u.id
     LEFT JOIN funcionarios uf ON u.funcionario_id = uf.id
     LEFT JOIN usuarios ua ON sp.autorizado_por = ua.id
     LEFT JOIN funcionarios uaf ON ua.funcionario_id = uaf.id
     WHERE sp.id = ?",
    [$id]
);

if (!$solicitud) {
    die('Solicitud no encontrada.');
}

// Verificar permisos de acceso
if ($user['rol_id'] != ROL_ADMIN && $solicitud['solicitado_por'] != $user['id']) {
    if (!($user['funcionario_id'] && $solicitud['funcionario_id'] == $user['funcionario_id'])) {
        die('No tiene permiso para ver esta solicitud.');
    }
}

// Obtener historial
$historial = $db->select(
    "SELECT h.*, e.nombre as estado_nombre, e.color as estado_color,
            u.username, f.nombre as usuario_nombre, f.apellido_paterno as usuario_apellido
     FROM historial_permisos h
     INNER JOIN estados_permiso e ON h.estado_id = e.id
     INNER JOIN usuarios u ON h.usuario_id = u.id
     LEFT JOIN funcionarios f ON u.funcionario_id = f.id
     WHERE h.solicitud_id = ?
     ORDER BY h.fecha ASC",
    [$id]
);

// Función para fecha en español
function fechaEspanol($fecha) {
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 
              'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $d = new DateTime($fecha);
    return $d->format('d') . ' de ' . $meses[(int)$d->format('n')] . ' de ' . $d->format('Y');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Permiso - <?= e($solicitud['numero_solicitud']) ?></title>
    <style>
        @page {
            size: letter portrait;
            margin: 2cm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* Encabezado */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #000;
        }
        
        .header h1 {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .header h2 {
            font-size: 14pt;
            font-weight: normal;
            margin-bottom: 15px;
        }
        
        .header .tipo-permiso {
            display: inline-block;
            font-size: 14pt;
            font-weight: bold;
            padding: 5px 20px;
            border: 2px solid #000;
            background: #f0f0f0;
        }
        
        /* Secciones */
        .section {
            margin-bottom: 20px;
            border: 1px solid #000;
        }
        
        .section-header {
            background: #003366;
            color: #fff;
            font-weight: bold;
            padding: 8px 15px;
            font-size: 11pt;
        }
        
        .section-body {
            padding: 15px;
        }
        
        /* Datos en grid */
        .data-grid {
            display: table;
            width: 100%;
        }
        
        .data-row {
            display: table-row;
        }
        
        .data-label {
            display: table-cell;
            width: 30%;
            font-weight: bold;
            padding: 5px 10px 5px 0;
            vertical-align: top;
        }
        
        .data-value {
            display: table-cell;
            width: 70%;
            padding: 5px 0;
        }
        
        /* Info compacta en línea */
        .info-inline {
            margin-bottom: 8px;
        }
        
        .info-inline strong {
            display: inline-block;
            min-width: 140px;
        }
        
        /* Estado */
        .estado-box {
            text-align: center;
            padding: 15px;
            margin: 20px 0;
            border: 2px solid #000;
        }
        
        .estado-box .estado-label {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .estado-autorizado { background: #d4edda; border-color: #28a745; }
        .estado-rechazado { background: #f8d7da; border-color: #dc3545; }
        .estado-pendiente { background: #fff3cd; border-color: #ffc107; }
        
        /* Tabla historial */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        
        table th {
            background: #003366;
            color: #fff;
        }
        
        /* Firmas */
        .firmas {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        
        .firma-box {
            text-align: center;
            width: 40%;
        }
        
        .firma-linea {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 10px;
        }
        
        /* Pie de página */
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }
        
        /* Impresión */
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        /* Botón imprimir */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #003366;
            color: #fff;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #002244;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Imprimir
    </button>
    
    <div class="container">
        <!-- Encabezado -->
        <div class="header">
            <h1>ASOCIACIÓN DE MUNICIPIOS</h1>
            <h2>Solicitud de Permiso</h2>
            <div class="tipo-permiso">
                <?= strtoupper(e($solicitud['tipo_nombre'])) ?>
            </div>
        </div>
        
        <!-- Número y Fecha -->
        <div class="section">
            <div class="section-header">INFORMACIÓN DE LA SOLICITUD</div>
            <div class="section-body">
                <div class="data-grid">
                    <div class="data-row">
                        <div class="data-label">Nº Solicitud:</div>
                        <div class="data-value"><strong><?= e($solicitud['numero_solicitud']) ?></strong></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Fecha Solicitud:</div>
                        <div class="data-value"><?= fechaEspanol($solicitud['created_at']) ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Año de Aplicación:</div>
                        <div class="data-value"><?= $solicitud['anio_aplicacion'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Datos del Funcionario -->
        <div class="section">
            <div class="section-header">DATOS DEL FUNCIONARIO</div>
            <div class="section-body">
                <div class="data-grid">
                    <div class="data-row">
                        <div class="data-label">Nombre Completo:</div>
                        <div class="data-value">
                            <?= e($solicitud['func_nombre'] . ' ' . $solicitud['func_apellido'] . ' ' . $solicitud['func_apellido_m']) ?>
                        </div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">RUT:</div>
                        <div class="data-value"><?= e($solicitud['func_rut']) ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Cargo:</div>
                        <div class="data-value"><?= e($solicitud['func_cargo']) ?></div>
                    </div>
                    <?php if ($solicitud['func_departamento']): ?>
                    <div class="data-row">
                        <div class="data-label">Departamento:</div>
                        <div class="data-value"><?= e($solicitud['func_departamento']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Período Solicitado -->
        <div class="section">
            <div class="section-header">PERÍODO SOLICITADO</div>
            <div class="section-body">
                <div class="data-grid">
                    <div class="data-row">
                        <div class="data-label">Fecha de Inicio:</div>
                        <div class="data-value"><?= fechaEspanol($solicitud['fecha_inicio']) ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Fecha de Término:</div>
                        <div class="data-value"><?= fechaEspanol($solicitud['fecha_termino']) ?></div>
                    </div>
                    <div class="data-row">
                        <div class="data-label">Días Solicitados:</div>
                        <div class="data-value">
                            <strong><?= number_format($solicitud['dias_solicitados'], 1) ?></strong>
                            <?php if ($solicitud['es_medio_dia']): ?>(Medio día)<?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Motivo -->
        <?php if ($solicitud['motivo']): ?>
        <div class="section">
            <div class="section-header">MOTIVO / OBSERVACIONES</div>
            <div class="section-body">
                <?= nl2br(e($solicitud['motivo'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Estado -->
        <?php
        $estado_class = '';
        if ($solicitud['estado_id'] == 4) $estado_class = 'estado-autorizado';
        elseif ($solicitud['estado_id'] == 5) $estado_class = 'estado-rechazado';
        elseif ($solicitud['estado_id'] == 2) $estado_class = 'estado-pendiente';
        ?>
        <div class="estado-box <?= $estado_class ?>">
            <div class="estado-label">Estado: <?= e($solicitud['estado_nombre']) ?></div>
            <?php if ($solicitud['fecha_autorizacion']): ?>
            <div style="margin-top: 10px;">
                Procesado el <?= fechaEspanol($solicitud['fecha_autorizacion']) ?>
                <?php if ($solicitud['autorizador_nombre']): ?>
                por <?= e($solicitud['autorizador_nombre'] . ' ' . $solicitud['autorizador_apellido']) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Historial -->
        <?php if (!empty($historial)): ?>
        <div class="section">
            <div class="section-header">HISTORIAL DE LA SOLICITUD</div>
            <div class="section-body" style="padding: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Usuario</th>
                            <th>Comentario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($h['fecha'])) ?></td>
                            <td><?= e($h['estado_nombre']) ?></td>
                            <td><?= e($h['usuario_nombre'] . ' ' . $h['usuario_apellido']) ?></td>
                            <td><?= e($h['comentario']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Firmas -->
        <?php if ($solicitud['estado_id'] == 4): ?>
        <div class="firmas">
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>FUNCIONARIO</strong><br>
                    <?= e($solicitud['func_nombre'] . ' ' . $solicitud['func_apellido']) ?>
                </div>
            </div>
            <div class="firma-box">
                <div class="firma-linea">
                    <strong>SECRETARIO EJECUTIVO</strong><br>
                    <?= e($solicitud['autorizador_nombre'] . ' ' . $solicitud['autorizador_apellido']) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pie de página -->
        <div class="footer">
            Documento generado el <?= date('d/m/Y H:i:s') ?> por el Sistema de Gestión Documental<br>
            Asociación de Municipios - <?= APP_NAME ?>
        </div>
    </div>
</body>
</html>
