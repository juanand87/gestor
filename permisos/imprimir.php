<?php
/**
 * Imprimir solicitud de permiso (genera HTML optimizado para PDF)
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

// Obtener solicitud (sin columna departamento que no existe)
$solicitud = $db->selectOne(
    "SELECT sp.*, 
            tp.nombre as tipo_nombre, tp.codigo as tipo_codigo,
            ep.nombre as estado_nombre, ep.color as estado_color,
            f.nombre as func_nombre, f.apellido_paterno as func_apellido, 
            f.apellido_materno as func_apellido_m, f.rut as func_rut,
            f.cargo as func_cargo,
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

// Obtener historial (usando created_at en lugar de fecha)
$historial = $db->select(
    "SELECT h.*, h.created_at as fecha, en.nombre as estado_nombre, en.color as estado_color,
            u.username, f.nombre as usuario_nombre, f.apellido_paterno as usuario_apellido
     FROM historial_permisos h
     INNER JOIN usuarios u ON h.usuario_id = u.id
     LEFT JOIN funcionarios f ON u.funcionario_id = f.id
     LEFT JOIN estados_permiso en ON h.estado_nuevo_id = en.id
     WHERE h.solicitud_id = ?
     ORDER BY h.created_at ASC",
    [$id]
);

// Función para fecha en español
function fechaEspanol($fecha) {
    if (!$fecha) return '-';
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
            margin: 1.5cm;
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
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        /* Encabezado */
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #003366;
        }
        
        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
            color: #003366;
        }
        
        .header h2 {
            font-size: 13pt;
            font-weight: normal;
            margin-bottom: 15px;
        }
        
        .header .tipo-permiso {
            display: inline-block;
            font-size: 12pt;
            font-weight: bold;
            padding: 8px 25px;
            border: 2px solid #003366;
            background: #e8f0fe;
            color: #003366;
        }
        
        .numero-solicitud {
            font-size: 10pt;
            color: #666;
            margin-top: 10px;
        }
        
        /* Secciones */
        .section {
            margin-bottom: 15px;
            border: 1px solid #333;
        }
        
        .section-header {
            background: #003366;
            color: #fff;
            font-weight: bold;
            padding: 6px 12px;
            font-size: 10pt;
        }
        
        .section-body {
            padding: 12px;
        }
        
        /* Tabla de datos */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table td {
            padding: 5px 8px;
            vertical-align: top;
        }
        
        .data-table .label {
            width: 35%;
            font-weight: bold;
            background: #f5f5f5;
            border: 1px solid #ddd;
        }
        
        .data-table .value {
            width: 65%;
            border: 1px solid #ddd;
        }
        
        /* Estado */
        .estado-box {
            text-align: center;
            padding: 12px;
            margin: 15px 0;
            border: 2px solid #000;
            font-weight: bold;
        }
        
        .estado-autorizado { background: #d4edda; border-color: #28a745; color: #155724; }
        .estado-rechazado { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .estado-pendiente { background: #fff3cd; border-color: #ffc107; color: #856404; }
        .estado-borrador { background: #e2e3e5; border-color: #6c757d; color: #383d41; }
        
        /* Tabla historial */
        .historial-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        
        .historial-table th, .historial-table td {
            border: 1px solid #333;
            padding: 5px 8px;
            text-align: left;
        }
        
        .historial-table th {
            background: #003366;
            color: #fff;
        }
        
        .historial-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        /* Firmas */
        .firmas {
            margin-top: 60px;
            page-break-inside: avoid;
        }
        
        .firmas-row {
            display: flex;
            justify-content: space-between;
        }
        
        .firma-box {
            text-align: center;
            width: 45%;
        }
        
        .firma-linea {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 8px;
            font-size: 10pt;
        }
        
        .firma-nombre {
            font-weight: bold;
        }
        
        .firma-cargo {
            font-size: 9pt;
            color: #666;
        }
        
        /* Pie de página */
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8pt;
            color: #888;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        /* Botones de acción */
        .action-buttons {
            position: fixed;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-print {
            background: #003366;
            color: #fff;
        }
        
        .btn-print:hover {
            background: #002244;
        }
        
        .btn-back {
            background: #6c757d;
            color: #fff;
        }
        
        .btn-back:hover {
            background: #545b62;
        }
        
        /* Impresión */
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="action-buttons no-print">
        <button class="btn btn-back" onclick="window.history.back()">
            ← Volver
        </button>
        <button class="btn btn-print" onclick="window.print()">
            🖨️ Imprimir / Guardar PDF
        </button>
    </div>
    
    <div class="container">
        <!-- Encabezado -->
        <div class="header">
            <h1>ASOCIACIÓN DE MUNICIPIOS</h1>
            <h2>Solicitud de Permiso</h2>
            <div class="tipo-permiso">
                <?php if ($solicitud['tipo_codigo'] == 'feriado_legal'): ?>
                    ☀️ FERIADO LEGAL (VACACIONES)
                <?php else: ?>
                    🕐 PERMISO ADMINISTRATIVO
                <?php endif; ?>
            </div>
            <div class="numero-solicitud">
                <strong>Nº <?= e($solicitud['numero_solicitud']) ?></strong>
            </div>
        </div>
        
        <!-- Estado actual -->
        <?php
        $estado_class = 'estado-borrador';
        if ($solicitud['estado_id'] == 3) $estado_class = 'estado-autorizado';
        elseif ($solicitud['estado_id'] == 4) $estado_class = 'estado-rechazado';
        elseif ($solicitud['estado_id'] == 2) $estado_class = 'estado-pendiente';
        ?>
        <div class="estado-box <?= $estado_class ?>">
            ESTADO: <?= strtoupper(e($solicitud['estado_nombre'])) ?>
            <?php if ($solicitud['fecha_autorizacion'] && $solicitud['autorizador_nombre']): ?>
                <br><span style="font-weight: normal; font-size: 10pt;">
                    Procesado el <?= fechaEspanol($solicitud['fecha_autorizacion']) ?>
                    por <?= e($solicitud['autorizador_nombre'] . ' ' . $solicitud['autorizador_apellido']) ?>
                </span>
            <?php endif; ?>
        </div>
        
        <!-- Datos del Funcionario -->
        <div class="section">
            <div class="section-header">DATOS DEL FUNCIONARIO</div>
            <div class="section-body">
                <table class="data-table">
                    <tr>
                        <td class="label">Nombre Completo:</td>
                        <td class="value"><?= e($solicitud['func_nombre'] . ' ' . $solicitud['func_apellido'] . ' ' . $solicitud['func_apellido_m']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">RUT:</td>
                        <td class="value"><?= e($solicitud['func_rut']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Cargo:</td>
                        <td class="value"><?= e($solicitud['func_cargo']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Período Solicitado -->
        <div class="section">
            <div class="section-header">PERÍODO SOLICITADO</div>
            <div class="section-body">
                <table class="data-table">
                    <tr>
                        <td class="label">Fecha de Inicio:</td>
                        <td class="value"><?= fechaEspanol($solicitud['fecha_inicio']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Fecha de Término:</td>
                        <td class="value"><?= fechaEspanol($solicitud['fecha_termino']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Días Hábiles Solicitados:</td>
                        <td class="value">
                            <strong><?= number_format($solicitud['dias_solicitados'], 1) ?></strong> día(s)
                            <?php if ($solicitud['es_medio_dia']): ?>
                                <em>(Medio día)</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($solicitud['tipo_codigo'] == 'feriado_legal'): ?>
                    <tr>
                        <td class="label">Año de Descuento:</td>
                        <td class="value"><?= $solicitud['anio_descuento'] ?? date('Y') ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
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
        
        <!-- Información de la Solicitud -->
        <div class="section">
            <div class="section-header">INFORMACIÓN DE LA SOLICITUD</div>
            <div class="section-body">
                <table class="data-table">
                    <tr>
                        <td class="label">Solicitado por:</td>
                        <td class="value"><?= e($solicitud['solicitante_nombre'] . ' ' . $solicitud['solicitante_apellido']) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Fecha de Creación:</td>
                        <td class="value"><?= fechaEspanol($solicitud['created_at']) ?></td>
                    </tr>
                    <?php if ($solicitud['fecha_autorizacion']): ?>
                    <tr>
                        <td class="label">Fecha de Resolución:</td>
                        <td class="value"><?= fechaEspanol($solicitud['fecha_autorizacion']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- Historial -->
        <?php if (!empty($historial)): ?>
        <div class="section">
            <div class="section-header">HISTORIAL DE LA SOLICITUD</div>
            <div class="section-body" style="padding: 0;">
                <table class="historial-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Fecha</th>
                            <th style="width: 25%;">Acción</th>
                            <th style="width: 25%;">Usuario</th>
                            <th style="width: 30%;">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($h['fecha'])) ?></td>
                            <td><?= e($h['accion']) ?></td>
                            <td><?= e(($h['usuario_nombre'] ?? '') . ' ' . ($h['usuario_apellido'] ?? '')) ?></td>
                            <td><?= e($h['observaciones'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Firmas (solo si está autorizado) -->
        <?php if ($solicitud['estado_id'] == 3): ?>
        <div class="firmas">
            <div class="firmas-row">
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre"><?= e($solicitud['func_nombre'] . ' ' . $solicitud['func_apellido']) ?></div>
                        <div class="firma-cargo">Funcionario</div>
                    </div>
                </div>
                <div class="firma-box">
                    <div class="firma-linea">
                        <div class="firma-nombre"><?= e(($solicitud['autorizador_nombre'] ?? '') . ' ' . ($solicitud['autorizador_apellido'] ?? '')) ?></div>
                        <div class="firma-cargo">Secretario Ejecutivo</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pie de página -->
        <div class="footer">
            Documento generado el <?= date('d/m/Y H:i:s') ?><br>
            Sistema de Gestión Documental - Asociación de Municipios<br>
            <em>Para guardar como PDF: Imprimir → Destino: "Guardar como PDF"</em>
        </div>
    </div>
</body>
</html>
