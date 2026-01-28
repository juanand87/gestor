<?php
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('cometidos_ver');

$db = Database::getInstance();
$user = Session::getUser();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    Session::setFlash('error', 'Cometido no válido.');
    redirect(APP_URL . '/cometidos/');
}

// Obtener cometido
$cometido = $db->selectOne(
    "SELECT c.*, 
            f.nombre as func_nombre, f.apellido_paterno as func_apellido_paterno, 
            f.apellido_materno as func_apellido_materno, f.rut as func_rut, f.cargo as func_cargo,
            a.nombre as auto_nombre, a.apellido_paterno as auto_apellido, a.cargo as auto_cargo,
            u.username as creado_por_username,
            uf.nombre as creador_nombre, uf.apellido_paterno as creador_apellido,
            e.nombre as estado_nombre, e.color as estado_color
     FROM cometidos c
     INNER JOIN funcionarios f ON c.funcionario_id = f.id
     LEFT JOIN funcionarios a ON c.autoridad_id = a.id
     INNER JOIN usuarios u ON c.creado_por = u.id
     INNER JOIN funcionarios uf ON u.funcionario_id = uf.id
     INNER JOIN estados_documento e ON c.estado_id = e.id
     WHERE c.id = :id",
    ['id' => $id]
);

if (!$cometido) {
    Session::setFlash('error', 'Cometido no encontrado.');
    redirect(APP_URL . '/cometidos/');
}

// Obtener todos los funcionarios del cometido
$funcionariosCometido = $db->select(
    "SELECT f.* FROM funcionarios f
     INNER JOIN cometidos_funcionarios cf ON f.id = cf.funcionario_id
     WHERE cf.cometido_id = :id
     ORDER BY cf.id",
    ['id' => $id]
);

// Si no hay en la tabla de relación, usar el funcionario principal
if (empty($funcionariosCometido)) {
    $funcionariosCometido = $db->select(
        "SELECT * FROM funcionarios WHERE id = :id",
        ['id' => $cometido['funcionario_id']]
    );
}

// Verificar permisos de visualización
if (!Auth::isAdmin() && !Auth::isSecretarioEjecutivo()) {
    $esFuncionario = false;
    foreach ($funcionariosCometido as $fc) {
        if ($fc['id'] == $user['funcionario_id']) {
            $esFuncionario = true;
            break;
        }
    }
    if ($cometido['creado_por'] != $user['id'] && !$esFuncionario) {
        Session::setFlash('error', 'No tiene permisos para ver este cometido.');
        redirect(APP_URL . '/cometidos/');
    }
}

// Obtener historial
$historial = $db->select(
    "SELECT h.*, u.username, f.nombre, f.apellido_paterno,
            ea.nombre as estado_anterior, ea.color as color_anterior,
            en.nombre as estado_nuevo, en.color as color_nuevo
     FROM historial_cometidos h
     INNER JOIN usuarios u ON h.usuario_id = u.id
     INNER JOIN funcionarios f ON u.funcionario_id = f.id
     LEFT JOIN estados_documento ea ON h.estado_anterior_id = ea.id
     LEFT JOIN estados_documento en ON h.estado_nuevo_id = en.id
     WHERE h.cometido_id = :id
     ORDER BY h.created_at DESC",
    ['id' => $id]
);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    // Enviar a autorización
    if ($accion === 'enviar' && $cometido['estado_id'] == 1) {
        if ($cometido['creado_por'] == $user['id'] || Auth::isAdmin()) {
            $db->update('cometidos', 
                ['estado_id' => 2, 'fecha_envio_autorizacion' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $id]
            );
            registrarHistorial($id, 'Enviado a autorización', 1, 2);
            Session::setFlash('success', 'Cometido enviado a autorización.');
            redirect(APP_URL . '/cometidos/ver.php?id=' . $id);
        }
    }
    
    // Autorizar
    if ($accion === 'autorizar' && $cometido['estado_id'] == 2 && Auth::hasPermission('cometidos_autorizar')) {
        $db->update('cometidos',
            ['estado_id' => 3, 'fecha_autorizacion' => date('Y-m-d H:i:s'), 'autoridad_id' => $user['funcionario_id']],
            'id = :id',
            ['id' => $id]
        );
        registrarHistorial($id, 'Cometido autorizado', 2, 3);
        Session::setFlash('success', 'Cometido autorizado correctamente.');
        redirect(APP_URL . '/cometidos/ver.php?id=' . $id);
    }
    
    // Rechazar
    if ($accion === 'rechazar' && $cometido['estado_id'] == 2 && Auth::hasPermission('cometidos_autorizar')) {
        $observaciones = trim($_POST['observaciones_rechazo'] ?? '');
        $db->update('cometidos',
            ['estado_id' => 4, 'observaciones_rechazo' => $observaciones, 'autoridad_id' => $user['funcionario_id']],
            'id = :id',
            ['id' => $id]
        );
        registrarHistorial($id, 'Cometido rechazado', 2, 4, $observaciones);
        Session::setFlash('warning', 'Cometido rechazado.');
        redirect(APP_URL . '/cometidos/ver.php?id=' . $id);
    }
}

$pageTitle = 'Cometido ' . $cometido['numero_cometido'];
$currentPage = 'cometidos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/cometidos/">Cometidos</a></li>
                <li class="breadcrumb-item active"><?= e($cometido['numero_cometido']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Cabecera del cometido -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Cometido <?= e($cometido['numero_cometido']) ?>
                </h4>
                <span class="badge fs-6" style="background-color: <?= $cometido['estado_color'] ?>">
                    <?= e($cometido['estado_nombre']) ?>
                </span>
            </div>
            <div class="card-body">
                <!-- 1. Identificación del Funcionario -->
                <div class="mb-4">
                    <h6 class="text-primary"><i class="bi bi-people me-2"></i>1. Identificación del/los Funcionario(s)</h6>
                    <?php if (count($funcionariosCometido) == 1): ?>
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">Nombre Completo</small>
                            <p class="fw-bold"><?= e($funcionariosCometido[0]['nombre'] . ' ' . $funcionariosCometido[0]['apellido_paterno'] . ' ' . $funcionariosCometido[0]['apellido_materno']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">RUT</small>
                            <p class="fw-bold"><?= e($funcionariosCometido[0]['rut']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Cargo</small>
                            <p class="fw-bold"><?= e($funcionariosCometido[0]['cargo']) ?></p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre Completo</th>
                                    <th>RUT</th>
                                    <th>Cargo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($funcionariosCometido as $fc): ?>
                                <tr>
                                    <td><?= e($fc['nombre'] . ' ' . $fc['apellido_paterno'] . ' ' . $fc['apellido_materno']) ?></td>
                                    <td><?= e($fc['rut']) ?></td>
                                    <td><?= e($fc['cargo']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- 2. Autoridad que dispone -->
                <?php if ($cometido['auto_nombre']): ?>
                <div class="mb-4">
                    <h6 class="text-primary"><i class="bi bi-person-badge me-2"></i>2. Autoridad que Dispone el Cometido</h6>
                    <p class="fw-bold"><?= e($cometido['auto_nombre'] . ' ' . $cometido['auto_apellido'] . ' - ' . $cometido['auto_cargo']) ?></p>
                </div>
                <?php endif; ?>
                
                <!-- 3. Objetivo -->
                <div class="mb-4">
                    <h6 class="text-primary"><i class="bi bi-bullseye me-2"></i>3. Objetivo del Cometido</h6>
                    <p><?= nl2br(e($cometido['objetivo'])) ?></p>
                </div>
                
                <!-- 4. Lugar -->
                <div class="mb-4">
                    <h6 class="text-primary"><i class="bi bi-geo-alt me-2"></i>4. Lugar del Cometido</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">Ciudad</small>
                            <p class="fw-bold"><?= e($cometido['ciudad']) ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Comuna</small>
                            <p class="fw-bold"><?= e($cometido['comuna']) ?></p>
                        </div>
                        <?php if ($cometido['lugar_descripcion']): ?>
                        <div class="col-md-4">
                            <small class="text-muted">Descripción</small>
                            <p class="fw-bold"><?= e($cometido['lugar_descripcion']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 5. Fecha y Duración -->
                <div class="mb-4">
                    <h6 class="text-primary"><i class="bi bi-calendar-event me-2"></i>5. Fecha y Duración</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted">Fecha Inicio</small>
                            <p class="fw-bold"><?= formatDate($cometido['fecha_inicio']) ?></p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Fecha Término</small>
                            <p class="fw-bold"><?= formatDate($cometido['fecha_termino']) ?></p>
                        </div>
                        <?php if ($cometido['horario_inicio']): ?>
                        <div class="col-md-3">
                            <small class="text-muted">Horario Inicio</small>
                            <p class="fw-bold"><?= e($cometido['horario_inicio']) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($cometido['horario_termino']): ?>
                        <div class="col-md-3">
                            <small class="text-muted">Horario Término</small>
                            <p class="fw-bold"><?= e($cometido['horario_termino']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 6. Medio de Traslado -->
                <div class="mb-4">
                    <h6 class="text-primary"><i class="bi bi-car-front me-2"></i>6. Medio de Traslado</h6>
                    <p class="fw-bold">
                        <?= getMedioTrasladoTexto($cometido['medio_traslado']) ?>
                        <?php if ($cometido['patente_vehiculo']): ?>
                            <span class="badge bg-secondary ms-2">Patente: <?= e($cometido['patente_vehiculo']) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <!-- 7. Financiamiento -->
                <div class="mb-4">
                    <h6 class="text-primary"><i class="bi bi-cash-stack me-2"></i>7. Financiamiento</h6>
                    <p class="fw-bold">Viático: <?= formatMoney($cometido['viatico']) ?></p>
                </div>
                
                <!-- 8. Carácter del Cometido -->
                <div class="mb-4">
                    <h6 class="text-primary"><i class="bi bi-list-check me-2"></i>8. Carácter del Cometido</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge <?= $cometido['dentro_comuna'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <?= $cometido['dentro_comuna'] ? 'Dentro de la comuna' : 'Fuera de la comuna' ?>
                        </span>
                        <span class="badge <?= $cometido['dentro_jornada'] ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <?= $cometido['dentro_jornada'] ? 'Dentro de jornada laboral' : 'Fuera de jornada laboral' ?>
                        </span>
                        <span class="badge <?= $cometido['con_costo'] ? 'bg-danger' : 'bg-success' ?>">
                            <?= $cometido['con_costo'] ? 'Con costo para la institución' : 'Sin costo para la institución' ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($cometido['estado_id'] == 4 && $cometido['observaciones_rechazo']): ?>
                <div class="alert alert-danger">
                    <h6><i class="bi bi-x-circle me-2"></i>Motivo del Rechazo</h6>
                    <p class="mb-0"><?= nl2br(e($cometido['observaciones_rechazo'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Acciones -->
            <div class="card-footer">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <a href="<?= APP_URL ?>/cometidos/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Volver
                    </a>
                    
                    <div class="d-flex gap-2">
                        <?php if ($cometido['estado_id'] == 1 && ($cometido['creado_por'] == $user['id'] || Auth::isAdmin())): ?>
                            <a href="editar.php?id=<?= $cometido['id'] ?>" class="btn btn-warning">
                                <i class="bi bi-pencil me-2"></i>Editar
                            </a>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="accion" value="enviar" class="btn btn-primary">
                                    <i class="bi bi-send me-2"></i>Enviar a Autorización
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($cometido['estado_id'] == 2 && Auth::hasPermission('cometidos_autorizar')): ?>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="accion" value="autorizar" class="btn btn-success">
                                    <i class="bi bi-check-circle me-2"></i>Autorizar
                                </button>
                            </form>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalRechazo">
                                <i class="bi bi-x-circle me-2"></i>Rechazar
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($cometido['estado_id'] == 3): ?>
                            <a href="imprimir.php?id=<?= $cometido['id'] ?>" class="btn btn-outline-primary" target="_blank">
                                <i class="bi bi-printer me-2"></i>Imprimir
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar con información adicional -->
    <div class="col-lg-4">
        <!-- Info del documento -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información del Documento</h6>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <small class="text-muted">Creado por:</small><br>
                    <?= e($cometido['creador_nombre'] . ' ' . $cometido['creador_apellido']) ?>
                </p>
                <p class="mb-2">
                    <small class="text-muted">Fecha de creación:</small><br>
                    <?= formatDateTime($cometido['created_at']) ?>
                </p>
                <?php if ($cometido['fecha_envio_autorizacion']): ?>
                <p class="mb-2">
                    <small class="text-muted">Enviado a autorización:</small><br>
                    <?= formatDateTime($cometido['fecha_envio_autorizacion']) ?>
                </p>
                <?php endif; ?>
                <?php if ($cometido['fecha_autorizacion']): ?>
                <p class="mb-0">
                    <small class="text-muted">Fecha de autorización:</small><br>
                    <?= formatDateTime($cometido['fecha_autorizacion']) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Historial -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historial de Movimientos</h6>
            </div>
            <div class="card-body">
                <?php if (empty($historial)): ?>
                    <p class="text-muted text-center">Sin movimientos registrados</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($historial as $h): ?>
                        <div class="timeline-item">
                            <div class="time"><?= formatDateTime($h['created_at']) ?></div>
                            <div class="fw-bold"><?= e($h['accion']) ?></div>
                            <small class="text-muted">
                                Por: <?= e($h['nombre'] . ' ' . $h['apellido_paterno']) ?>
                            </small>
                            <?php if ($h['observaciones']): ?>
                            <div class="mt-1">
                                <small class="text-danger"><?= e($h['observaciones']) ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Rechazo -->
<div class="modal fade" id="modalRechazo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Rechazar Cometido</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="observaciones_rechazo" class="form-label">Motivo del rechazo</label>
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
