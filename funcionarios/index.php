<?php
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('funcionarios_gestionar');

$db = Database::getInstance();

// Procesar eliminación
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    
    // Verificar que no tenga cometidos asociados
    $cometidos = $db->selectOne("SELECT COUNT(*) as total FROM cometidos WHERE funcionario_id = :id", ['id' => $id]);
    
    if ($cometidos['total'] > 0) {
        Session::setFlash('error', 'No se puede eliminar el funcionario porque tiene cometidos asociados.');
    } else {
        // Verificar que no tenga usuario asociado
        $usuarios = $db->selectOne("SELECT COUNT(*) as total FROM usuarios WHERE funcionario_id = :id", ['id' => $id]);
        
        if ($usuarios['total'] > 0) {
            Session::setFlash('error', 'No se puede eliminar el funcionario porque tiene un usuario asociado.');
        } else {
            $db->delete('funcionarios', 'id = :id', ['id' => $id]);
            Session::setFlash('success', 'Funcionario eliminado correctamente.');
        }
    }
    redirect(APP_URL . '/funcionarios/');
}

// Obtener funcionarios
$funcionarios = $db->select(
    "SELECT f.*, 
            (SELECT COUNT(*) FROM usuarios u WHERE u.funcionario_id = f.id) as tiene_usuario,
            (SELECT COUNT(*) FROM cometidos c WHERE c.funcionario_id = f.id) as total_cometidos
     FROM funcionarios f 
     ORDER BY f.apellido_paterno, f.nombre"
);

$pageTitle = 'Funcionarios';
$currentPage = 'funcionarios';

ob_start();
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2><i class="bi bi-people me-2"></i>Funcionarios</h2>
        <p class="text-muted">Gestión de funcionarios de la Asociación</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="crear.php" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>Nuevo Funcionario
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($funcionarios)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                <h5 class="mt-3 text-muted">No hay funcionarios registrados</h5>
                <a href="crear.php" class="btn btn-primary mt-3">
                    <i class="bi bi-plus-circle me-2"></i>Agregar primer funcionario
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>RUT</th>
                            <th>Nombre Completo</th>
                            <th>Cargo</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>Cometidos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funcionarios as $f): ?>
                        <tr>
                            <td><?= e($f['rut']) ?></td>
                            <td>
                                <?= e($f['nombre'] . ' ' . $f['apellido_paterno'] . ' ' . $f['apellido_materno']) ?>
                                <?php if ($f['es_secretario_ejecutivo']): ?>
                                    <span class="badge bg-primary ms-1">Secretario/a Ejecutivo/a</span>
                                <?php endif; ?>
                                <?php if ($f['es_subrogante']): ?>
                                    <span class="badge bg-info ms-1">Subrogante</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($f['cargo']) ?></td>
                            <td><a href="mailto:<?= e($f['email']) ?>"><?= e($f['email']) ?></a></td>
                            <td>
                                <?php if ($f['activo']): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= $f['total_cometidos'] ?></span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="editar.php?id=<?= $f['id'] ?>" class="btn btn-outline-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($f['total_cometidos'] == 0 && $f['tiene_usuario'] == 0): ?>
                                    <a href="?eliminar=<?= $f['id'] ?>" class="btn btn-outline-danger btn-delete" 
                                       title="Eliminar" data-confirm="¿Está seguro de eliminar a <?= e($f['nombre']) ?>?">
                                        <i class="bi bi-trash"></i>
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
