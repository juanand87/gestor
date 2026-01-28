<?php
require_once 'includes/init.php';
Auth::requireLogin();

$db = Database::getInstance();
$user = Session::getUser();

// Estadísticas para el dashboard
$stats = [];

// Total de cometidos (según permisos)
if (Auth::isAdmin() || Auth::isSecretarioEjecutivo()) {
    $stats['total_cometidos'] = $db->selectOne("SELECT COUNT(*) as total FROM cometidos")['total'];
    $stats['pendientes'] = $db->selectOne("SELECT COUNT(*) as total FROM cometidos WHERE estado_id = 2")['total'];
} else {
    // Solo cometidos creados por el usuario o donde es el funcionario
    $stats['total_cometidos'] = $db->selectOne(
        "SELECT COUNT(*) as total FROM cometidos WHERE creado_por = :user_id OR funcionario_id = :func_id",
        ['user_id' => $user['id'], 'func_id' => $user['funcionario_id']]
    )['total'];
    $stats['pendientes'] = $db->selectOne(
        "SELECT COUNT(*) as total FROM cometidos WHERE (creado_por = :user_id OR funcionario_id = :func_id) AND estado_id = 2",
        ['user_id' => $user['id'], 'func_id' => $user['funcionario_id']]
    )['total'];
}

$stats['autorizados'] = $db->selectOne("SELECT COUNT(*) as total FROM cometidos WHERE estado_id = 3")['total'];
$stats['rechazados'] = $db->selectOne("SELECT COUNT(*) as total FROM cometidos WHERE estado_id = 4")['total'];

// Estadísticas de permisos
if (Auth::isAdmin() || Auth::isSecretarioEjecutivo()) {
    $stats['total_permisos'] = $db->selectOne("SELECT COUNT(*) as total FROM solicitudes_permiso")['total'] ?? 0;
    $stats['permisos_pendientes'] = $db->selectOne("SELECT COUNT(*) as total FROM solicitudes_permiso WHERE estado_id = 2")['total'] ?? 0;
} else {
    $stats['total_permisos'] = $db->selectOne(
        "SELECT COUNT(*) as total FROM solicitudes_permiso WHERE solicitado_por = :user_id OR funcionario_id = :func_id",
        ['user_id' => $user['id'], 'func_id' => $user['funcionario_id']]
    )['total'] ?? 0;
    $stats['permisos_pendientes'] = $db->selectOne(
        "SELECT COUNT(*) as total FROM solicitudes_permiso WHERE (solicitado_por = :user_id OR funcionario_id = :func_id) AND estado_id = 2",
        ['user_id' => $user['id'], 'func_id' => $user['funcionario_id']]
    )['total'] ?? 0;
}

// Días de feriado disponibles para el usuario actual
$stats['dias_feriado'] = 0;
$stats['dias_admin'] = 0;
if ($user['funcionario_id']) {
    $feriado = $db->selectOne(
        "SELECT dias_asignados - dias_utilizados as disponibles 
         FROM feriados_legales_config 
         WHERE funcionario_id = :func_id AND anio = :anio",
        ['func_id' => $user['funcionario_id'], 'anio' => date('Y')]
    );
    $stats['dias_feriado'] = $feriado ? $feriado['disponibles'] : 0;
    
    $admin = $db->selectOne(
        "SELECT dias_totales - dias_utilizados as disponibles 
         FROM permisos_administrativos_config 
         WHERE funcionario_id = :func_id AND anio = :anio",
        ['func_id' => $user['funcionario_id'], 'anio' => date('Y')]
    );
    $stats['dias_admin'] = $admin ? $admin['disponibles'] : 6;
}

// Últimos cometidos
if (Auth::isAdmin() || Auth::isSecretarioEjecutivo()) {
    $ultimosCometidos = $db->select(
        "SELECT c.*, f.nombre, f.apellido_paterno, e.nombre as estado_nombre, e.color as estado_color
         FROM cometidos c
         INNER JOIN funcionarios f ON c.funcionario_id = f.id
         INNER JOIN estados_documento e ON c.estado_id = e.id
         ORDER BY c.created_at DESC LIMIT 5"
    );
} else {
    $ultimosCometidos = $db->select(
        "SELECT c.*, f.nombre, f.apellido_paterno, e.nombre as estado_nombre, e.color as estado_color
         FROM cometidos c
         INNER JOIN funcionarios f ON c.funcionario_id = f.id
         INNER JOIN estados_documento e ON c.estado_id = e.id
         WHERE c.creado_por = :user_id OR c.funcionario_id = :func_id
         ORDER BY c.created_at DESC LIMIT 5",
        ['user_id' => $user['id'], 'func_id' => $user['funcionario_id']]
    );
}

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>
        <p class="text-muted">Bienvenido(a), <?= e($user['nombre_completo']) ?></p>
    </div>
</div>

<!-- Estadísticas Cometidos -->
<div class="row mb-4">
    <div class="col-12 mb-2">
        <h5><i class="bi bi-file-earmark-text me-2"></i>Cometidos</h5>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Total Cometidos</h6>
                        <h2 class="mb-0"><?= $stats['total_cometidos'] ?></h2>
                    </div>
                    <i class="bi bi-file-earmark-text" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Pendientes</h6>
                        <h2 class="mb-0"><?= $stats['pendientes'] ?></h2>
                    </div>
                    <i class="bi bi-hourglass-split" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Autorizados</h6>
                        <h2 class="mb-0"><?= $stats['autorizados'] ?></h2>
                    </div>
                    <i class="bi bi-check-circle" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Rechazados</h6>
                        <h2 class="mb-0"><?= $stats['rechazados'] ?></h2>
                    </div>
                    <i class="bi bi-x-circle" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas Permisos -->
<?php if (Auth::hasPermission('permisos_ver')): ?>
<div class="row mb-4">
    <div class="col-12 mb-2">
        <h5><i class="bi bi-calendar-check me-2"></i>Permisos</h5>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Total Permisos</h6>
                        <h2 class="mb-0"><?= $stats['total_permisos'] ?></h2>
                    </div>
                    <i class="bi bi-calendar-check" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Perm. Pendientes</h6>
                        <h2 class="mb-0"><?= $stats['permisos_pendientes'] ?></h2>
                    </div>
                    <i class="bi bi-hourglass" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($user['funcionario_id']): ?>
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Días Feriado <?= date('Y') ?></h6>
                        <h2 class="mb-0"><?= $stats['dias_feriado'] ?></h2>
                    </div>
                    <i class="bi bi-sun" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-1">Días Admin <?= date('Y') ?></h6>
                        <h2 class="mb-0"><?= $stats['dias_admin'] ?></h2>
                    </div>
                    <i class="bi bi-clock" style="font-size: 3rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Acciones rápidas y últimos cometidos -->
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Acciones Rápidas</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (Auth::hasPermission('cometidos_crear')): ?>
                    <a href="<?= APP_URL ?>/cometidos/crear.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Nuevo Cometido
                    </a>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasPermission('cometidos_ver')): ?>
                    <a href="<?= APP_URL ?>/cometidos/" class="btn btn-outline-primary">
                        <i class="bi bi-list-ul me-2"></i>Ver Cometidos
                    </a>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasPermission('cometidos_autorizar')): ?>
                    <a href="<?= APP_URL ?>/cometidos/pendientes.php" class="btn btn-warning">
                        <i class="bi bi-clock-history me-2"></i>Cometidos Pendientes
                        <?php if ($stats['pendientes'] > 0): ?>
                        <span class="badge bg-danger"><?= $stats['pendientes'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <?php if (Auth::hasPermission('permisos_crear')): ?>
                    <a href="<?= APP_URL ?>/permisos/crear.php" class="btn btn-success">
                        <i class="bi bi-calendar-plus me-2"></i>Solicitar Permiso
                    </a>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasPermission('permisos_ver')): ?>
                    <a href="<?= APP_URL ?>/permisos/" class="btn btn-outline-success">
                        <i class="bi bi-calendar-check me-2"></i>Ver Permisos
                    </a>
                    <?php endif; ?>
                    
                    <?php if (Auth::hasPermission('permisos_autorizar')): ?>
                    <a href="<?= APP_URL ?>/permisos/pendientes.php" class="btn btn-warning">
                        <i class="bi bi-hourglass me-2"></i>Permisos Pendientes
                        <?php if ($stats['permisos_pendientes'] > 0): ?>
                        <span class="badge bg-danger"><?= $stats['permisos_pendientes'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Últimos Cometidos</h5>
                <a href="<?= APP_URL ?>/cometidos/" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($ultimosCometidos)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">No hay cometidos registrados</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>N° Cometido</th>
                                    <th>Funcionario</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimosCometidos as $cometido): ?>
                                <tr style="cursor: pointer;" onclick="window.location='<?= APP_URL ?>/cometidos/ver.php?id=<?= $cometido['id'] ?>'">
                                    <td><strong><?= e($cometido['numero_cometido']) ?></strong></td>
                                    <td><?= e($cometido['nombre'] . ' ' . $cometido['apellido_paterno']) ?></td>
                                    <td><?= formatDate($cometido['fecha_inicio']) ?></td>
                                    <td>
                                        <span class="badge" style="background-color: <?= $cometido['estado_color'] ?>">
                                            <?= e($cometido['estado_nombre']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . 'layout.php';
?>
