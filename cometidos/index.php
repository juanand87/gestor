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
                u.username as creado_por_username,
                e.nombre as estado_nombre, e.color as estado_color
         FROM cometidos c
         INNER JOIN usuarios u ON c.creado_por = u.id
         INNER JOIN estados_documento e ON c.estado_id = e.id
         ORDER BY c.created_at DESC"
    );
} else {
    // Para usuarios normales: ver cometidos que crearon o donde están asignados
    $cometidos = $db->select(
        "SELECT DISTINCT c.*, 
                u.username as creado_por_username,
                e.nombre as estado_nombre, e.color as estado_color
         FROM cometidos c
         INNER JOIN usuarios u ON c.creado_por = u.id
         INNER JOIN estados_documento e ON c.estado_id = e.id
         LEFT JOIN cometidos_funcionarios cf ON c.id = cf.cometido_id
         WHERE c.creado_por = :user_id 
            OR cf.funcionario_id = (SELECT funcionario_id FROM usuarios WHERE id = :user_id2)
         ORDER BY c.created_at DESC",
        ['user_id' => $user['id'], 'user_id2' => $user['id']]
    );
}

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
unset($c); // Romper la referencia

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
                            <th>Funcionario(s)</th>
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
                            <td>
                                <?php if (count($c['funcionarios']) == 1): ?>
                                    <?= e($c['funcionarios'][0]['nombre'] . ' ' . $c['funcionarios'][0]['apellido']) ?>
                                    <br><small class="text-muted"><?= e($c['funcionarios'][0]['rut']) ?></small>
                                <?php elseif (count($c['funcionarios']) > 1): ?>
                                    <span class="badge bg-info"><?= count($c['funcionarios']) ?> funcionarios</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" 
                                            data-bs-toggle="popover" 
                                            data-bs-trigger="hover focus"
                                            data-bs-html="true"
                                            data-bs-content="<?php 
                                                $list = '';
                                                foreach ($c['funcionarios'] as $f) {
                                                    $list .= e($f['nombre'] . ' ' . $f['apellido']) . '<br>';
                                                }
                                                echo $list;
                                            ?>">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">Sin asignar</span>
                                <?php endif; ?>
                            </td>
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

// JavaScript para inicializar popovers
ob_start();
?>
<script>
$(document).ready(function() {
    // Inicializar popovers de Bootstrap
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});
</script>
<?php
$scripts = ob_get_clean();

include VIEWS_PATH . 'layout.php';
?>
