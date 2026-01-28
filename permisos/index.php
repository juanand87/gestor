<?php
/**
 * Listado de solicitudes de permisos
 */
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('permisos_ver');

$db = Database::getInstance();
$user = Session::getUser();

// Inicializar permisos administrativos del año actual si no existen
$anioActual = date('Y');
$db->query(
    "INSERT IGNORE INTO permisos_administrativos_config (funcionario_id, anio, dias_disponibles, dias_utilizados)
     SELECT id, :anio, 6.0, 0 FROM funcionarios WHERE activo = 1",
    ['anio' => $anioActual]
);

// Obtener solicitudes según permisos
if (Auth::isAdmin() || Auth::isSecretarioEjecutivo()) {
    $solicitudes = $db->select(
        "SELECT sp.*, 
                tp.nombre as tipo_nombre, tp.codigo as tipo_codigo,
                f.nombre as func_nombre, f.apellido_paterno as func_apellido, f.rut as func_rut,
                ep.nombre as estado_nombre, ep.color as estado_color,
                u.username as solicitado_por_username
         FROM solicitudes_permiso sp
         INNER JOIN tipos_permiso tp ON sp.tipo_permiso_id = tp.id
         INNER JOIN funcionarios f ON sp.funcionario_id = f.id
         INNER JOIN estados_permiso ep ON sp.estado_id = ep.id
         INNER JOIN usuarios u ON sp.solicitado_por = u.id
         ORDER BY sp.created_at DESC"
    );
} else {
    $solicitudes = $db->select(
        "SELECT sp.*, 
                tp.nombre as tipo_nombre, tp.codigo as tipo_codigo,
                f.nombre as func_nombre, f.apellido_paterno as func_apellido, f.rut as func_rut,
                ep.nombre as estado_nombre, ep.color as estado_color,
                u.username as solicitado_por_username
         FROM solicitudes_permiso sp
         INNER JOIN tipos_permiso tp ON sp.tipo_permiso_id = tp.id
         INNER JOIN funcionarios f ON sp.funcionario_id = f.id
         INNER JOIN estados_permiso ep ON sp.estado_id = ep.id
         INNER JOIN usuarios u ON sp.solicitado_por = u.id
         WHERE sp.solicitado_por = :user_id OR sp.funcionario_id = :func_id
         ORDER BY sp.created_at DESC",
        ['user_id' => $user['id'], 'func_id' => $user['funcionario_id'] ?? 0]
    );
}

// Obtener resumen de días disponibles del usuario actual
$diasFeriado = null;
$diasAdmin = null;

if ($user['funcionario_id']) {
    $diasFeriado = $db->selectOne(
        "SELECT * FROM feriados_legales_config WHERE funcionario_id = :fid AND anio = :anio",
        ['fid' => $user['funcionario_id'], 'anio' => $anioActual]
    );
    
    $diasAdmin = $db->selectOne(
        "SELECT * FROM permisos_administrativos_config WHERE funcionario_id = :fid AND anio = :anio",
        ['fid' => $user['funcionario_id'], 'anio' => $anioActual]
    );
}

$pageTitle = 'Permisos y Feriados';
$currentPage = 'permisos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2><i class="bi bi-calendar-check me-2"></i>Permisos y Feriados</h2>
        <p class="text-muted">Gestión de feriados legales y permisos administrativos</p>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if (Auth::hasPermission('permisos_crear')): ?>
        <div class="btn-group">
            <a href="crear.php?tipo=feriado_legal" class="btn btn-success">
                <i class="bi bi-sun me-2"></i>Solicitar Feriado Legal
            </a>
            <a href="crear.php?tipo=permiso_administrativo" class="btn btn-primary">
                <i class="bi bi-clock me-2"></i>Solicitar Permiso Administrativo
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Resumen de días disponibles -->
<?php if ($user['funcionario_id']): ?>
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-success mb-1"><i class="bi bi-sun me-2"></i>Feriado Legal <?= $anioActual ?></h6>
                        <?php if ($diasFeriado): ?>
                            <h3 class="mb-0"><?= number_format($diasFeriado['dias_asignados'] - $diasFeriado['dias_utilizados'], 1) ?> días</h3>
                            <small class="text-muted">de <?= number_format($diasFeriado['dias_asignados'], 1) ?> asignados</small>
                        <?php else: ?>
                            <p class="text-muted mb-0">No asignado</p>
                        <?php endif; ?>
                    </div>
                    <div class="display-4 text-success opacity-25">
                        <i class="bi bi-sun"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-primary mb-1"><i class="bi bi-clock me-2"></i>Permisos Administrativos <?= $anioActual ?></h6>
                        <?php if ($diasAdmin): ?>
                            <h3 class="mb-0"><?= number_format($diasAdmin['dias_disponibles'] - $diasAdmin['dias_utilizados'], 1) ?> días</h3>
                            <small class="text-muted">de 6.0 disponibles al año</small>
                        <?php else: ?>
                            <h3 class="mb-0">6.0 días</h3>
                            <small class="text-muted">disponibles</small>
                        <?php endif; ?>
                    </div>
                    <div class="display-4 text-primary opacity-25">
                        <i class="bi bi-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Enlaces de administración -->
<?php if (Auth::hasPermission('permisos_autorizar')): ?>
<div class="row mb-4">
    <div class="col-12">
        <a href="pendientes.php" class="btn btn-warning">
            <i class="bi bi-hourglass-split me-2"></i>Solicitudes Pendientes de Autorización
        </a>
        <?php if (Auth::hasPermission('permisos_config')): ?>
        <a href="configurar.php" class="btn btn-outline-secondary">
            <i class="bi bi-gear me-2"></i>Configurar Días de Feriado Legal
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Mis Solicitudes</h5>
    </div>
    <div class="card-body">
        <?php if (empty($solicitudes)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 text-muted">No hay solicitudes registradas</h5>
                <?php if (Auth::hasPermission('permisos_crear')): ?>
                <div class="mt-3">
                    <a href="crear.php?tipo=feriado_legal" class="btn btn-success">
                        <i class="bi bi-sun me-2"></i>Solicitar Feriado Legal
                    </a>
                    <a href="crear.php?tipo=permiso_administrativo" class="btn btn-primary">
                        <i class="bi bi-clock me-2"></i>Solicitar Permiso Administrativo
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>N° Solicitud</th>
                            <th>Tipo</th>
                            <th>Funcionario</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Término</th>
                            <th>Días</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitudes as $s): ?>
                        <tr>
                            <td><strong><?= e($s['numero_solicitud']) ?></strong></td>
                            <td>
                                <?php if ($s['tipo_codigo'] == 'feriado_legal'): ?>
                                    <span class="badge bg-success"><i class="bi bi-sun me-1"></i>Feriado Legal</span>
                                <?php else: ?>
                                    <span class="badge bg-primary"><i class="bi bi-clock me-1"></i>Permiso Admin.</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($s['func_nombre'] . ' ' . $s['func_apellido']) ?></td>
                            <td><?= formatDate($s['fecha_inicio']) ?></td>
                            <td><?= formatDate($s['fecha_termino']) ?></td>
                            <td>
                                <?= number_format($s['dias_solicitados'], 1) ?>
                                <?php if ($s['es_medio_dia']): ?>
                                    <small class="text-muted">(½ día)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background-color: <?= $s['estado_color'] ?>">
                                    <?= e($s['estado_nombre']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?= $s['id'] ?>" class="btn btn-outline-primary" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($s['estado_id'] == 1 && ($s['solicitado_por'] == $user['id'] || Auth::isAdmin())): ?>
                                    <a href="editar.php?id=<?= $s['id'] ?>" class="btn btn-outline-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . 'layout.php';
?>
