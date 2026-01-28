<?php
/**
 * Solicitudes de permisos pendientes de autorización
 */
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('permisos_autorizar');

$db = Database::getInstance();
$user = Session::getUser();

// Obtener solicitudes pendientes
$solicitudes = $db->select(
    "SELECT sp.*, 
            tp.nombre as tipo_nombre, tp.codigo as tipo_codigo,
            f.nombre as func_nombre, f.apellido_paterno as func_apellido, 
            f.apellido_materno as func_apellido_m, f.rut as func_rut,
            ep.nombre as estado_nombre, ep.color as estado_color,
            u.username as solicitado_por_username,
            uf.nombre as solicitante_nombre, uf.apellido_paterno as solicitante_apellido
     FROM solicitudes_permiso sp
     INNER JOIN tipos_permiso tp ON sp.tipo_permiso_id = tp.id
     INNER JOIN funcionarios f ON sp.funcionario_id = f.id
     INNER JOIN estados_permiso ep ON sp.estado_id = ep.id
     INNER JOIN usuarios u ON sp.solicitado_por = u.id
     LEFT JOIN funcionarios uf ON u.funcionario_id = uf.id
     WHERE sp.estado_id = 2
     ORDER BY sp.fecha_envio_autorizacion ASC"
);

$pageTitle = 'Permisos Pendientes de Autorización';
$currentPage = 'permisos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/permisos/">Permisos</a></li>
                <li class="breadcrumb-item active">Pendientes</li>
            </ol>
        </nav>
        <h2><i class="bi bi-hourglass-split text-warning me-2"></i>Solicitudes Pendientes de Autorización</h2>
    </div>
</div>

<?php if (empty($solicitudes)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
        <h4 class="mt-3 text-success">No hay solicitudes pendientes</h4>
        <p class="text-muted">Todas las solicitudes han sido procesadas.</p>
        <a href="<?= APP_URL ?>/permisos/" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Volver a Permisos
        </a>
    </div>
</div>
<?php else: ?>

<div class="row">
    <?php foreach ($solicitudes as $s): ?>
    <div class="col-lg-6 mb-4">
        <div class="card h-100 border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <div>
                    <?php if ($s['tipo_codigo'] == 'feriado_legal'): ?>
                        <i class="bi bi-sun me-2"></i>
                    <?php else: ?>
                        <i class="bi bi-clock me-2"></i>
                    <?php endif; ?>
                    <strong><?= e($s['numero_solicitud']) ?></strong>
                </div>
                <span class="badge bg-dark"><?= e($s['tipo_nombre']) ?></span>
            </div>
            <div class="card-body">
                <h5 class="card-title">
                    <?= e($s['func_nombre'] . ' ' . $s['func_apellido'] . ' ' . $s['func_apellido_m']) ?>
                </h5>
                <p class="text-muted mb-2"><?= e($s['func_rut']) ?></p>
                
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted">Fecha Inicio</small>
                        <p class="fw-bold mb-0"><?= formatDate($s['fecha_inicio']) ?></p>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Fecha Término</small>
                        <p class="fw-bold mb-0"><?= formatDate($s['fecha_termino']) ?></p>
                    </div>
                </div>
                
                <p class="mb-2">
                    <strong>Días solicitados:</strong> 
                    <?= number_format($s['dias_solicitados'], 1) ?>
                    <?php if ($s['es_medio_dia']): ?>
                        <span class="badge bg-info">½ día</span>
                    <?php endif; ?>
                </p>
                
                <?php if ($s['motivo']): ?>
                <p class="mb-2">
                    <strong>Motivo:</strong> <?= e(substr($s['motivo'], 0, 100)) ?><?= strlen($s['motivo']) > 100 ? '...' : '' ?>
                </p>
                <?php endif; ?>
                
                <p class="text-muted small mb-0">
                    Solicitado por <?= e($s['solicitante_nombre'] . ' ' . $s['solicitante_apellido']) ?>
                    el <?= formatDateTime($s['fecha_envio_autorizacion']) ?>
                </p>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="ver.php?id=<?= $s['id'] ?>" class="btn btn-outline-primary">
                    <i class="bi bi-eye me-1"></i>Ver Detalle
                </a>
                <div>
                    <form method="POST" action="ver.php?id=<?= $s['id'] ?>" class="d-inline">
                        <button type="submit" name="accion" value="autorizar" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>Autorizar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
include VIEWS_PATH . 'layout.php';
?>
