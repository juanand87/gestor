<?php
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('funcionarios_gestionar');

$db = Database::getInstance();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $es_secretario_ejecutivo = isset($_POST['es_secretario_ejecutivo']) ? 1 : 0;
    $es_subrogante = isset($_POST['es_subrogante']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    // Validaciones
    if (empty($rut)) $errors[] = 'El RUT es requerido';
    elseif (!validarRut($rut)) $errors[] = 'El RUT ingresado no es válido';
    
    if (empty($nombre)) $errors[] = 'El nombre es requerido';
    if (empty($apellido_paterno)) $errors[] = 'El apellido paterno es requerido';
    if (empty($cargo)) $errors[] = 'El cargo es requerido';
    if (empty($email)) $errors[] = 'El email es requerido';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido';
    
    // Verificar RUT único
    if (empty($errors)) {
        $existe = $db->selectOne("SELECT id FROM funcionarios WHERE rut = :rut", ['rut' => formatRut($rut)]);
        if ($existe) $errors[] = 'Ya existe un funcionario con este RUT';
    }
    
    // Verificar email único
    if (empty($errors)) {
        $existe = $db->selectOne("SELECT id FROM funcionarios WHERE email = :email", ['email' => $email]);
        if ($existe) $errors[] = 'Ya existe un funcionario con este email';
    }
    
    if (empty($errors)) {
        $data = [
            'rut' => formatRut($rut),
            'nombre' => $nombre,
            'apellido_paterno' => $apellido_paterno,
            'apellido_materno' => $apellido_materno,
            'cargo' => $cargo,
            'email' => $email,
            'telefono' => $telefono,
            'es_secretario_ejecutivo' => $es_secretario_ejecutivo,
            'es_subrogante' => $es_subrogante,
            'activo' => $activo
        ];
        
        $id = $db->insert('funcionarios', $data);
        
        Session::setFlash('success', 'Funcionario creado correctamente.');
        redirect(APP_URL . '/funcionarios/');
    }
}

$pageTitle = 'Nuevo Funcionario';
$currentPage = 'funcionarios';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/funcionarios/">Funcionarios</a></li>
                <li class="breadcrumb-item active">Nuevo Funcionario</li>
            </ol>
        </nav>
        <h2><i class="bi bi-person-plus me-2"></i>Nuevo Funcionario</h2>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <h6><i class="bi bi-exclamation-triangle me-2"></i>Por favor corrija los siguientes errores:</h6>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="rut" class="form-label">RUT <span class="text-danger">*</span></label>
                    <input type="text" class="form-control rut-input" id="rut" name="rut" required
                           placeholder="12.345.678-9" value="<?= e($_POST['rut'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nombre" name="nombre" required
                           value="<?= e($_POST['nombre'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="apellido_paterno" class="form-label">Apellido Paterno <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required
                           value="<?= e($_POST['apellido_paterno'] ?? '') ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="apellido_materno" class="form-label">Apellido Materno</label>
                    <input type="text" class="form-control" id="apellido_materno" name="apellido_materno"
                           value="<?= e($_POST['apellido_materno'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="cargo" class="form-label">Cargo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="cargo" name="cargo" required
                           value="<?= e($_POST['cargo'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control" id="telefono" name="telefono"
                           value="<?= e($_POST['telefono'] ?? '') ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" required
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>
            </div>
            
            <hr>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1"
                               <?= (!isset($_POST['rut']) || isset($_POST['activo'])) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Funcionario Activo</label>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="es_secretario_ejecutivo" 
                               name="es_secretario_ejecutivo" value="1"
                               <?= isset($_POST['es_secretario_ejecutivo']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="es_secretario_ejecutivo">Es Secretario/a Ejecutivo/a</label>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="es_subrogante" 
                               name="es_subrogante" value="1"
                               <?= isset($_POST['es_subrogante']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="es_subrogante">Es Subrogante</label>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-between">
                <a href="<?= APP_URL ?>/funcionarios/" class="btn btn-outline-secondary">
                    <i class="bi bi-x me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Guardar Funcionario
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include VIEWS_PATH . 'layout.php';
?>
