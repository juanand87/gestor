<?php
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('cometidos_autorizar');

$db = Database::getInstance();

// Obtener cometidos pendientes de autorización
$cometidos = $db->select(
    "SELECT c.*, 
            u.username as creado_por_username,
            uf.nombre as creador_nombre, uf.apellido_paterno as creador_apellido,
            e.nombre as estado_nombre, e.color as estado_color
     FROM cometidos c
     INNER JOIN usuarios u ON c.creado_por = u.id
     LEFT JOIN funcionarios uf ON u.funcionario_id = uf.id
     INNER JOIN estados_documento e ON c.estado_id = e.id
     WHERE c.estado_id = 2
     ORDER BY c.fecha_envio_autorizacion ASC"
);

// Obtener funcionarios para cada cometido
foreach ($cometidos as &$c) {
    $funcionarios = $db->select(
        "SELECT f.id, f.nombre, f.apellido_paterno as apellido, f.rut 
         FROM funcionarios f 
         INNER JOIN cometidos_funcionarios cf ON f.id = cf.funcionario_id 
         WHERE cf.cometido_id = :cometido_id",
        ['cometido_id' => $c['id']]
    );
    $c['funcionarios'] = $funcionarios ?: [];
}
unset($c);

$pageTitle = 'Pendientes de Autorización';
$currentPage = 'cometidos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2><i class="bi bi-hourglass-split me-2"></i>Pendientes de Autorización</h2>
        <p class="text-muted">Cometidos esperando su autorización</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="<?= APP_URL ?>/cometidos/" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Ver todos los cometidos
        </a>
    </div>
</div>

<?php if (empty($cometidos)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
        <h4 class="mt-3 text-muted">No hay cometidos pendientes</h4>
        <p class="text-muted">Todos los cometidos han sido procesados</p>
    </div>
</div>
<?php else: ?>
<div class="row">
    <?php foreach ($cometidos as $c): ?>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i><?= e($c['numero_cometido']) ?>
                </h5>
                <span class="badge" style="background-color: <?= $c['estado_color'] ?>">
                    <?= e($c['estado_nombre']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted">Funcionario(s)</small>
                        <?php if (count($c['funcionarios']) == 1): ?>
                            <p class="mb-0 fw-bold"><?= e($c['funcionarios'][0]['nombre'] . ' ' . $c['funcionarios'][0]['apellido']) ?></p>
                            <small class="text-muted"><?= e($c['funcionarios'][0]['rut']) ?></small>
                        <?php elseif (count($c['funcionarios']) > 1): ?>
                            <p class="mb-0 fw-bold">
                                <span class="badge bg-info"><?= count($c['funcionarios']) ?> funcionarios</span>
                            </p>
                            <small class="text-muted">
                                <?php 
                                $nombres = array_map(function($f) { return $f['nombre'] . ' ' . $f['apellido']; }, $c['funcionarios']);
                                echo e(implode(', ', $nombres));
                                ?>
                            </small>
                        <?php else: ?>
                            <p class="mb-0 text-muted">Sin asignar</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Destino</small>
                        <p class="mb-0 fw-bold"><?= e($c['ciudad']) ?></p>
                        <small class="text-muted"><?= e($c['comuna']) ?></small>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted">Fecha inicio</small>
                        <p class="mb-0"><?= formatDate($c['fecha_inicio']) ?></p>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Fecha término</small>
                        <p class="mb-0"><?= formatDate($c['fecha_termino']) ?></p>
                    </div>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted">Objetivo</small>
                    <p class="mb-0"><?= e(substr($c['objetivo'], 0, 150)) ?><?= strlen($c['objetivo']) > 150 ? '...' : '' ?></p>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="bi bi-person me-1"></i>
                        Creado por: <?= e($c['creador_nombre'] . ' ' . $c['creador_apellido']) ?>
                    </small>
                    <small class="text-muted">
                        <i class="bi bi-clock me-1"></i>
                        <?= formatDateTime($c['fecha_envio_autorizacion']) ?>
                    </small>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between">
                    <a href="ver.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary">
                        <i class="bi bi-eye me-1"></i>Ver detalle
                    </a>
                    <div class="btn-group">
                        <form method="POST" action="ver.php?id=<?= $c['id'] ?>" class="d-inline">
                            <button type="submit" name="accion" value="autorizar" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Autorizar
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger" 
                                onclick="rechazarCometido(<?= $c['id'] ?>, '<?= e($c['numero_cometido']) ?>')">
                            <i class="bi bi-x-circle me-1"></i>Rechazar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal de rechazo -->
<div class="modal fade" id="modalRechazoRapido" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formRechazo" method="POST" action="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Rechazar Cometido <span id="numCometido"></span></h5>
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

ob_start();
?>
<script>
function rechazarCometido(id, numero) {
    $('#formRechazo').attr('action', 'ver.php?id=' + id);
    $('#numCometido').text(numero);
    $('#observaciones_rechazo').val('');
    $('#modalRechazoRapido').modal('show');
}
</script>
<?php
$scripts = ob_get_clean();

include VIEWS_PATH . 'layout.php';
?>
