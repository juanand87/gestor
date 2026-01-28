<?php
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('cometidos_ver');

$db = Database::getInstance();
$user = Session::getUser();

// Obtener cometidos según permisos
if (Auth::isAdmin() || Auth::isSecretarioEjecutivo()) {
    $cometidos = $db->select(
        "SELECT c.*, 
                f.nombre as func_nombre, f.apellido_paterno as func_apellido, f.rut as func_rut,
                u.username as creado_por_username,
                e.nombre as estado_nombre, e.color as estado_color
         FROM cometidos c
         INNER JOIN funcionarios f ON c.funcionario_id = f.id
         INNER JOIN usuarios u ON c.creado_por = u.id
         INNER JOIN estados_documento e ON c.estado_id = e.id
         ORDER BY c.created_at DESC"
    );
} else {
    $cometidos = $db->select(
        "SELECT c.*, 
                f.nombre as func_nombre, f.apellido_paterno as func_apellido, f.rut as func_rut,
                u.username as creado_por_username,
                e.nombre as estado_nombre, e.color as estado_color
         FROM cometidos c
         INNER JOIN funcionarios f ON c.funcionario_id = f.id
         INNER JOIN usuarios u ON c.creado_por = u.id
         INNER JOIN estados_documento e ON c.estado_id = e.id
         WHERE c.creado_por = :user_id OR c.funcionario_id = :func_id
         ORDER BY c.created_at DESC",
        ['user_id' => $user['id'], 'func_id' => $user['funcionario_id']]
    );
}

$pageTitle = 'Cometidos';
$currentPage = 'cometidos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2><i class="bi bi-file-earmark-text me-2"></i>Cometidos</h2>
        <p class="text-muted">Listado de cometidos registrados</p>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if (Auth::hasPermission('cometidos_crear')): ?>
        <a href="crear.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Nuevo Cometido
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($cometidos)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 text-muted">No hay cometidos registrados</h5>
                <?php if (Auth::hasPermission('cometidos_crear')): ?>
                <a href="crear.php" class="btn btn-primary mt-3">
                    <i class="bi bi-plus-circle me-2"></i>Crear primer cometido
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>N° Cometido</th>
                            <th>Funcionario</th>
                            <th>RUT</th>
                            <th>Destino</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Término</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cometidos as $c): ?>
                        <tr>
                            <td><strong><?= e($c['numero_cometido']) ?></strong></td>
                            <td><?= e($c['func_nombre'] . ' ' . $c['func_apellido']) ?></td>
                            <td><?= e($c['func_rut']) ?></td>
                            <td><?= e($c['ciudad'] . ', ' . $c['comuna']) ?></td>
                            <td><?= formatDate($c['fecha_inicio']) ?></td>
                            <td><?= formatDate($c['fecha_termino']) ?></td>
                            <td>
                                <span class="badge" style="background-color: <?= $c['estado_color'] ?>">
                                    <?= e($c['estado_nombre']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="ver.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="Ver">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($c['estado_id'] == 1 && ($c['creado_por'] == $user['id'] || Auth::isAdmin())): ?>
                                    <a href="editar.php?id=<?= $c['id'] ?>" class="btn btn-outline-warning" title="Editar">
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
