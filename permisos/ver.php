<?php
/**
 * Ver detalle de solicitud de permiso
 */
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('permisos_ver');

$db = Database::getInstance();
$user = Session::getUser();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    Session::setFlash('error', 'Solicitud no válida.');
    redirect(APP_URL . '/permisos/');
}

// Obtener solicitud
$solicitud = $db->selectOne(
    "SELECT sp.*, 
            tp.nombre as tipo_nombre, tp.codigo as tipo_codigo,
            f.nombre as func_nombre, f.apellido_paterno as func_apellido, 
            f.apellido_materno as func_apellido_m, f.rut as func_rut, f.cargo as func_cargo,
            ep.nombre as estado_nombre, ep.color as estado_color,
            u.username as solicitado_por_username,
            uf.nombre as solicitante_nombre, uf.apellido_paterno as solicitante_apellido,
            ua.username as autorizado_por_username
     FROM solicitudes_permiso sp
     INNER JOIN tipos_permiso tp ON sp.tipo_permiso_id = tp.id
     INNER JOIN funcionarios f ON sp.funcionario_id = f.id
     INNER JOIN estados_permiso ep ON sp.estado_id = ep.id
     INNER JOIN usuarios u ON sp.solicitado_por = u.id
     LEFT JOIN funcionarios uf ON u.funcionario_id = uf.id
     LEFT JOIN usuarios ua ON sp.autorizado_por = ua.id
     WHERE sp.id = :id",
    ['id' => $id]
);

if (!$solicitud) {
    Session::setFlash('error', 'Solicitud no encontrada.');
    redirect(APP_URL . '/permisos/');
}

// Verificar permisos de visualización
if (!Auth::isAdmin() && !Auth::isSecretarioEjecutivo()) {
    if ($solicitud['solicitado_por'] != $user['id'] && $solicitud['funcionario_id'] != $user['funcionario_id']) {
        Session::setFlash('error', 'No tiene permisos para ver esta solicitud.');
        redirect(APP_URL . '/permisos/');
    }
}

// Obtener historial
$historial = $db->select(
    "SELECT h.*, u.username, f.nombre, f.apellido_paterno,
            ea.nombre as estado_anterior, ea.color as color_anterior,
            en.nombre as estado_nuevo, en.color as color_nuevo
     FROM historial_permisos h
     INNER JOIN usuarios u ON h.usuario_id = u.id
     LEFT JOIN funcionarios f ON u.funcionario_id = f.id
     LEFT JOIN estados_permiso ea ON h.estado_anterior_id = ea.id
     LEFT JOIN estados_permiso en ON h.estado_nuevo_id = en.id
     WHERE h.solicitud_id = :id
     ORDER BY h.created_at DESC",
    ['id' => $id]
);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    // Enviar a autorización
    if ($accion === 'enviar' && $solicitud['estado_id'] == 1) {
        if ($solicitud['solicitado_por'] == $user['id'] || Auth::isAdmin()) {
            $db->update('solicitudes_permiso', 
                ['estado_id' => 2, 'fecha_envio_autorizacion' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $id]
            );
            $db->insert('historial_permisos', [
                'solicitud_id' => $id,
                'usuario_id' => $user['id'],
                'accion' => 'Enviado a autorización',
                'estado_anterior_id' => 1,
                'estado_nuevo_id' => 2
            ]);
            Session::setFlash('success', 'Solicitud enviada a autorización.');
            redirect(APP_URL . '/permisos/ver.php?id=' . $id);
        }
    }
    
    // Autorizar
    if ($accion === 'autorizar' && $solicitud['estado_id'] == 2 && Auth::hasPermission('permisos_autorizar')) {
        $db->beginTransaction();
        try {
            // Actualizar estado
            $db->update('solicitudes_permiso', 
                [
                    'estado_id' => 3, 
                    'fecha_autorizacion' => date('Y-m-d H:i:s'),
                    'autorizado_por' => $user['id']
                ],
                'id = :id',
                ['id' => $id]
            );
            
            // Descontar días
            if ($solicitud['tipo_codigo'] == 'feriado_legal') {
                $db->query(
                    "UPDATE feriados_legales_config 
                     SET dias_utilizados = dias_utilizados + :dias 
                     WHERE funcionario_id = :fid AND anio = :anio",
                    [
                        'dias' => $solicitud['dias_solicitados'],
                        'fid' => $solicitud['funcionario_id'],
                        'anio' => $solicitud['anio_descuento']
                    ]
                );
            } else {
                $db->query(
                    "UPDATE permisos_administrativos_config 
                     SET dias_utilizados = dias_utilizados + :dias 
                     WHERE funcionario_id = :fid AND anio = :anio",
                    [
                        'dias' => $solicitud['dias_solicitados'],
                        'fid' => $solicitud['funcionario_id'],
                        'anio' => $solicitud['anio_descuento']
                    ]
                );
            }
            
            // Registrar historial
            $db->insert('historial_permisos', [
                'solicitud_id' => $id,
                'usuario_id' => $user['id'],
                'accion' => 'Solicitud autorizada',
                'estado_anterior_id' => 2,
                'estado_nuevo_id' => 3
            ]);
            
            $db->commit();
            Session::setFlash('success', 'Solicitud autorizada correctamente. Se han descontado ' . $solicitud['dias_solicitados'] . ' día(s).');
            redirect(APP_URL . '/permisos/ver.php?id=' . $id);
            
        } catch (Exception $e) {
            $db->rollback();
            Session::setFlash('error', 'Error al autorizar: ' . $e->getMessage());
        }
    }
    
    // Rechazar
    if ($accion === 'rechazar' && $solicitud['estado_id'] == 2 && Auth::hasPermission('permisos_autorizar')) {
        $observaciones = trim($_POST['observaciones_rechazo'] ?? '');
        if (empty($observaciones)) {
            Session::setFlash('error', 'Debe indicar el motivo del rechazo.');
        } else {
            $db->update('solicitudes_permiso', 
                [
                    'estado_id' => 4, 
                    'observaciones_rechazo' => $observaciones,
                    'autorizado_por' => $user['id']
                ],
                'id = :id',
                ['id' => $id]
            );
            $db->insert('historial_permisos', [
                'solicitud_id' => $id,
                'usuario_id' => $user['id'],
                'accion' => 'Solicitud rechazada',
                'estado_anterior_id' => 2,
                'estado_nuevo_id' => 4,
                'observaciones' => $observaciones
            ]);
            Session::setFlash('success', 'Solicitud rechazada.');
            redirect(APP_URL . '/permisos/ver.php?id=' . $id);
        }
    }
}

$pageTitle = 'Solicitud ' . $solicitud['numero_solicitud'];
$currentPage = 'permisos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/permisos/">Permisos</a></li>
                <li class="breadcrumb-item active"><?= e($solicitud['numero_solicitud']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <?php if ($solicitud['tipo_codigo'] == 'feriado_legal'): ?>
                        <i class="bi bi-sun text-success me-2"></i>
                    <?php else: ?>
                        <i class="bi bi-clock text-primary me-2"></i>
                    <?php endif; ?>
                    Solicitud <?= e($solicitud['numero_solicitud']) ?>
                </h4>
                <span class="badge fs-6" style="background-color: <?= $solicitud['estado_color'] ?>">
                    <?= e($solicitud['estado_nombre']) ?>
                </span>
            </div>
            <div class="card-body p-0">
                <!-- Tipo de permiso -->
                <div class="border-bottom border-primary border-3">
                    <div class="bg-primary px-3 py-2">
                        <h6 class="text-white mb-0"><i class="bi bi-tag me-2"></i>Tipo de Solicitud</h6>
                    </div>
                    <div class="p-3">
                        <p class="fw-bold mb-0">
                            <?php if ($solicitud['tipo_codigo'] == 'feriado_legal'): ?>
                                <span class="badge bg-success fs-6"><i class="bi bi-sun me-1"></i>Feriado Legal (Vacaciones)</span>
                            <?php else: ?>
                                <span class="badge bg-primary fs-6"><i class="bi bi-clock me-1"></i>Permiso Administrativo</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Funcionario -->
                <div class="border-bottom border-primary border-3">
                    <div class="bg-primary px-3 py-2">
                        <h6 class="text-white mb-0"><i class="bi bi-person me-2"></i>Funcionario</h6>
                    </div>
                    <div class="p-3">
                        <div class="row">
                            <div class="col-md-4 border-end">
                                <small class="text-muted d-block">Nombre Completo</small>
                                <p class="fw-bold mb-0"><?= e($solicitud['func_nombre'] . ' ' . $solicitud['func_apellido'] . ' ' . $solicitud['func_apellido_m']) ?></p>
                            </div>
                            <div class="col-md-4 border-end">
                                <small class="text-muted d-block">RUT</small>
                                <p class="fw-bold mb-0"><?= e($solicitud['func_rut']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted d-block">Cargo</small>
                                <p class="fw-bold mb-0"><?= e($solicitud['func_cargo']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Período -->
                <div class="border-bottom border-primary border-3">
                    <div class="bg-primary px-3 py-2">
                        <h6 class="text-white mb-0"><i class="bi bi-calendar-event me-2"></i>Período Solicitado</h6>
                    </div>
                    <div class="p-3">
                        <div class="row">
                            <div class="col-md-3 border-end">
                                <small class="text-muted d-block">Fecha Inicio</small>
                                <p class="fw-bold mb-0"><?= formatDate($solicitud['fecha_inicio']) ?></p>
                            </div>
                            <div class="col-md-3 border-end">
                                <small class="text-muted d-block">Fecha Término</small>
                                <p class="fw-bold mb-0"><?= formatDate($solicitud['fecha_termino']) ?></p>
                            </div>
                            <div class="col-md-3 border-end">
                                <small class="text-muted d-block">Días Solicitados</small>
                                <p class="fw-bold mb-0">
                                    <?= number_format($solicitud['dias_solicitados'], 1) ?> día(s)
                                    <?php if ($solicitud['es_medio_dia']): ?>
                                        <span class="badge bg-info">½ día - <?= $solicitud['medio_dia_tipo'] == 'manana' ? 'Mañana' : 'Tarde' ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Año de Descuento</small>
                                <p class="fw-bold mb-0"><?= $solicitud['anio_descuento'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Motivo -->
                <?php if ($solicitud['motivo']): ?>
                <div class="border-bottom border-primary border-3">
                    <div class="bg-primary px-3 py-2">
                        <h6 class="text-white mb-0"><i class="bi bi-chat-text me-2"></i>Motivo</h6>
                    </div>
                    <div class="p-3">
                        <p class="mb-0"><?= nl2br(e($solicitud['motivo'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Rechazo -->
                <?php if ($solicitud['estado_id'] == 4 && $solicitud['observaciones_rechazo']): ?>
                <div>
                    <div class="bg-danger px-3 py-2">
                        <h6 class="text-white mb-0"><i class="bi bi-x-circle me-2"></i>Motivo del Rechazo</h6>
                    </div>
                    <div class="p-3">
                        <p class="mb-0"><?= nl2br(e($solicitud['observaciones_rechazo'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Acciones -->
            <div class="card-footer">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div class="d-flex gap-2">
                        <a href="<?= APP_URL ?>/permisos/" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Volver
                        </a>
                        <a href="imprimir.php?id=<?= $solicitud['id'] ?>" class="btn btn-outline-primary" target="_blank">
                            <i class="bi bi-printer me-2"></i>Imprimir
                        </a>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <?php if ($solicitud['estado_id'] == 1 && ($solicitud['solicitado_por'] == $user['id'] || Auth::isAdmin())): ?>
                            <a href="editar.php?id=<?= $solicitud['id'] ?>" class="btn btn-warning">
                                <i class="bi bi-pencil me-2"></i>Editar
                            </a>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="accion" value="enviar" class="btn btn-primary">
                                    <i class="bi bi-send me-2"></i>Enviar a Autorización
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($solicitud['estado_id'] == 2 && Auth::hasPermission('permisos_autorizar')): ?>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="accion" value="autorizar" class="btn btn-success">
                                    <i class="bi bi-check-circle me-2"></i>Autorizar
                                </button>
                            </form>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalRechazo">
                                <i class="bi bi-x-circle me-2"></i>Rechazar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información</h6>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <small class="text-muted">Solicitado por:</small><br>
                    <strong><?= e($solicitud['solicitante_nombre'] . ' ' . $solicitud['solicitante_apellido']) ?></strong>
                </p>
                <p class="mb-2">
                    <small class="text-muted">Fecha de solicitud:</small><br>
                    <strong><?= formatDateTime($solicitud['created_at']) ?></strong>
                </p>
                <?php if ($solicitud['fecha_autorizacion']): ?>
                <p class="mb-0">
                    <small class="text-muted">Fecha de autorización:</small><br>
                    <strong><?= formatDateTime($solicitud['fecha_autorizacion']) ?></strong>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Historial -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historial</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($historial as $h): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong><?= e($h['accion']) ?></strong>
                            <?php if ($h['estado_nuevo']): ?>
                            <span class="badge" style="background-color: <?= $h['color_nuevo'] ?>"><?= e($h['estado_nuevo']) ?></span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">
                            <?= e($h['nombre'] . ' ' . $h['apellido_paterno']) ?> - 
                            <?= formatDateTime($h['created_at']) ?>
                        </small>
                        <?php if ($h['observaciones']): ?>
                        <p class="mb-0 mt-1 small"><?= e($h['observaciones']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de rechazo -->
<div class="modal fade" id="modalRechazo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Rechazar Solicitud</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="observaciones_rechazo" class="form-label">Motivo del rechazo <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="observaciones_rechazo" name="observaciones_rechazo" 
                                  rows="4" required placeholder="Indique el motivo del rechazo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="accion" value="rechazar" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Confirmar Rechazo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . 'layout.php';
?>
