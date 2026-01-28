<?php
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('cometidos_crear');

$db = Database::getInstance();
$user = Session::getUser();

// Obtener funcionarios activos
$funcionarios = $db->select(
    "SELECT id, rut, nombre, apellido_paterno, apellido_materno, cargo 
     FROM funcionarios WHERE activo = 1 ORDER BY apellido_paterno, nombre"
);

// Obtener autoridades (Secretario Ejecutivo y subrogantes)
$autoridades = $db->select(
    "SELECT id, nombre, apellido_paterno, apellido_materno, cargo 
     FROM funcionarios 
     WHERE activo = 1 AND (es_secretario_ejecutivo = 1 OR es_subrogante = 1)
     ORDER BY es_secretario_ejecutivo DESC, apellido_paterno"
);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación
    $funcionario_id = (int)($_POST['funcionario_id'] ?? 0);
    $autoridad_id = (int)($_POST['autoridad_id'] ?? 0);
    $objetivo = trim($_POST['objetivo'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $comuna = trim($_POST['comuna'] ?? '');
    $lugar_descripcion = trim($_POST['lugar_descripcion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_termino = $_POST['fecha_termino'] ?? '';
    $horario_inicio = $_POST['horario_inicio'] ?? null;
    $horario_termino = $_POST['horario_termino'] ?? null;
    $medio_traslado = $_POST['medio_traslado'] ?? '';
    $patente_vehiculo = trim($_POST['patente_vehiculo'] ?? '');
    $viatico = (float)($_POST['viatico'] ?? 0);
    $dentro_comuna = isset($_POST['dentro_comuna']) ? 1 : 0;
    $dentro_jornada = isset($_POST['dentro_jornada']) ? 1 : 0;
    $con_costo = isset($_POST['con_costo']) ? 1 : 0;
    $accion = $_POST['accion'] ?? 'guardar';
    
    // Validaciones
    if ($funcionario_id <= 0) $errors[] = 'Debe seleccionar un funcionario';
    if (empty($objetivo)) $errors[] = 'El objetivo del cometido es requerido';
    if (empty($ciudad)) $errors[] = 'La ciudad es requerida';
    if (empty($comuna)) $errors[] = 'La comuna es requerida';
    if (empty($fecha_inicio)) $errors[] = 'La fecha de inicio es requerida';
    if (empty($fecha_termino)) $errors[] = 'La fecha de término es requerida';
    if (!empty($fecha_inicio) && !empty($fecha_termino) && $fecha_termino < $fecha_inicio) {
        $errors[] = 'La fecha de término no puede ser anterior a la fecha de inicio';
    }
    if (empty($medio_traslado)) $errors[] = 'Debe seleccionar un medio de traslado';
    if ($medio_traslado === 'vehiculo_particular' && empty($patente_vehiculo)) {
        $errors[] = 'Debe indicar la patente del vehículo particular';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generar número de cometido
            $numero_cometido = generarNumeroCometido();
            
            // Determinar estado inicial
            $estado_id = ($accion === 'enviar') ? 2 : 1; // 1=Borrador, 2=Pendiente
            
            $data = [
                'numero_cometido' => $numero_cometido,
                'funcionario_id' => $funcionario_id,
                'creado_por' => $user['id'],
                'autoridad_id' => $autoridad_id ?: null,
                'objetivo' => $objetivo,
                'ciudad' => $ciudad,
                'comuna' => $comuna,
                'lugar_descripcion' => $lugar_descripcion,
                'fecha_inicio' => $fecha_inicio,
                'fecha_termino' => $fecha_termino,
                'horario_inicio' => $horario_inicio ?: null,
                'horario_termino' => $horario_termino ?: null,
                'medio_traslado' => $medio_traslado,
                'patente_vehiculo' => $patente_vehiculo ?: null,
                'viatico' => $viatico,
                'dentro_comuna' => $dentro_comuna,
                'dentro_jornada' => $dentro_jornada,
                'con_costo' => $con_costo,
                'estado_id' => $estado_id,
                'fecha_envio_autorizacion' => ($accion === 'enviar') ? date('Y-m-d H:i:s') : null
            ];
            
            $cometido_id = $db->insert('cometidos', $data);
            
            // Registrar en historial
            $accion_historial = ($accion === 'enviar') ? 'Cometido creado y enviado a autorización' : 'Cometido creado como borrador';
            registrarHistorial($cometido_id, $accion_historial, null, $estado_id);
            
            $db->commit();
            
            Session::setFlash('success', 'Cometido ' . $numero_cometido . ' creado correctamente.');
            redirect(APP_URL . '/cometidos/ver.php?id=' . $cometido_id);
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Error al guardar el cometido: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Nuevo Cometido';
$currentPage = 'cometidos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/cometidos/">Cometidos</a></li>
                <li class="breadcrumb-item active">Nuevo Cometido</li>
            </ol>
        </nav>
        <h2><i class="bi bi-plus-circle me-2"></i>Nuevo Cometido</h2>
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

<form method="POST" action="" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= Session::generateCsrfToken() ?>">
    
    <!-- 1. Identificación del Funcionario -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person me-2"></i>1. Identificación del Funcionario</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="funcionario_id" class="form-label">Funcionario <span class="text-danger">*</span></label>
                    <select class="form-select select2" id="funcionario_id" name="funcionario_id" required>
                        <option value="">Seleccione un funcionario...</option>
                        <?php foreach ($funcionarios as $f): ?>
                        <option value="<?= $f['id'] ?>" 
                                data-rut="<?= e($f['rut']) ?>" 
                                data-cargo="<?= e($f['cargo']) ?>"
                                <?= (isset($_POST['funcionario_id']) && $_POST['funcionario_id'] == $f['id']) ? 'selected' : '' ?>>
                            <?= e($f['apellido_paterno'] . ' ' . $f['apellido_materno'] . ', ' . $f['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">RUT</label>
                    <input type="text" class="form-control" id="funcionario_rut" readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Cargo</label>
                    <input type="text" class="form-control" id="funcionario_cargo" readonly>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 2. Autoridad que dispone el cometido -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>2. Autoridad que Dispone el Cometido</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="autoridad_id" class="form-label">Secretario(a) Ejecutivo(a) o Subrogante</label>
                    <select class="form-select" id="autoridad_id" name="autoridad_id">
                        <option value="">Seleccione autoridad...</option>
                        <?php foreach ($autoridades as $a): ?>
                        <option value="<?= $a['id'] ?>"
                                <?= (isset($_POST['autoridad_id']) && $_POST['autoridad_id'] == $a['id']) ? 'selected' : '' ?>>
                            <?= e($a['nombre'] . ' ' . $a['apellido_paterno'] . ' - ' . $a['cargo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 3. Objetivo del Cometido -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-bullseye me-2"></i>3. Objetivo del Cometido</h5>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="objetivo" class="form-label">Descripción del objetivo <span class="text-danger">*</span></label>
                <textarea class="form-control" id="objetivo" name="objetivo" rows="4" required
                          placeholder="Describa el objetivo del cometido..."><?= e($_POST['objetivo'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    
    <!-- 4. Lugar del Cometido -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-geo-alt me-2"></i>4. Lugar del Cometido</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="ciudad" class="form-label">Ciudad <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ciudad" name="ciudad" required
                           value="<?= e($_POST['ciudad'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="comuna" class="form-label">Comuna <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="comuna" name="comuna" required
                           value="<?= e($_POST['comuna'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="lugar_descripcion" class="form-label">Descripción del lugar</label>
                    <input type="text" class="form-control" id="lugar_descripcion" name="lugar_descripcion"
                           placeholder="Ej: Municipalidad de..."
                           value="<?= e($_POST['lugar_descripcion'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- 5. Fecha y Duración -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>5. Fecha y Duración</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="fecha_inicio" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required
                           value="<?= e($_POST['fecha_inicio'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="fecha_termino" class="form-label">Fecha de término <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="fecha_termino" name="fecha_termino" required
                           value="<?= e($_POST['fecha_termino'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="horario_inicio" class="form-label">Horario inicio</label>
                    <input type="time" class="form-control" id="horario_inicio" name="horario_inicio"
                           value="<?= e($_POST['horario_inicio'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="horario_termino" class="form-label">Horario término</label>
                    <input type="time" class="form-control" id="horario_termino" name="horario_termino"
                           value="<?= e($_POST['horario_termino'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- 6. Medio de Traslado -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-car-front me-2"></i>6. Medio de Traslado</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Medio de traslado <span class="text-danger">*</span></label>
                    <div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="medio_traslado" id="medio_asociacion" 
                                   value="vehiculo_asociacion" <?= (($_POST['medio_traslado'] ?? '') === 'vehiculo_asociacion') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="medio_asociacion">
                                <i class="bi bi-truck me-1"></i>Vehículo de la Asociación
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="medio_traslado" id="medio_particular" 
                                   value="vehiculo_particular" <?= (($_POST['medio_traslado'] ?? '') === 'vehiculo_particular') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="medio_particular">
                                <i class="bi bi-car-front me-1"></i>Vehículo Particular
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="medio_traslado" id="medio_publico" 
                                   value="transporte_publico" <?= (($_POST['medio_traslado'] ?? '') === 'transporte_publico') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="medio_publico">
                                <i class="bi bi-bus-front me-1"></i>Transporte Público / Bus / Avión
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div id="patente_container" style="display: none;">
                        <label for="patente_vehiculo" class="form-label">Patente del vehículo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="patente_vehiculo" name="patente_vehiculo" 
                               maxlength="10" placeholder="Ej: ABCD-12"
                               value="<?= e($_POST['patente_vehiculo'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 7. Financiamiento -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>7. Financiamiento</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="viatico" class="form-label">Viático (si aplica)</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="viatico" name="viatico" 
                               min="0" step="1" placeholder="0"
                               value="<?= e($_POST['viatico'] ?? '0') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 8. Carácter del Cometido -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>8. Carácter del Cometido</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dentro_comuna" name="dentro_comuna" value="1"
                               <?= (isset($_POST['dentro_comuna']) || !isset($_POST['funcionario_id'])) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dentro_comuna">
                            <strong>Dentro de la comuna</strong><br>
                            <small class="text-muted">(Desmarcar si es fuera de la comuna)</small>
                        </label>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dentro_jornada" name="dentro_jornada" value="1"
                               <?= (isset($_POST['dentro_jornada']) || !isset($_POST['funcionario_id'])) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dentro_jornada">
                            <strong>Dentro de la jornada laboral</strong><br>
                            <small class="text-muted">(Desmarcar si es fuera de jornada)</small>
                        </label>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="con_costo" name="con_costo" value="1"
                               <?= isset($_POST['con_costo']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="con_costo">
                            <strong>Con costo para la institución</strong><br>
                            <small class="text-muted">(Marcar si tiene costos asociados)</small>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botones de acción -->
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <button type="submit" name="accion" value="guardar" class="btn btn-secondary w-100">
                        <i class="bi bi-save me-2"></i>Guardar como Borrador
                    </button>
                </div>
                <div class="col-md-6 mb-2">
                    <button type="submit" name="accion" value="enviar" class="btn btn-primary w-100">
                        <i class="bi bi-send me-2"></i>Guardar y Enviar a Autorización
                    </button>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="<?= APP_URL ?>/cometidos/" class="btn btn-outline-secondary">
                    <i class="bi bi-x me-2"></i>Cancelar
                </a>
            </div>
        </div>
    </div>
</form>

<?php
$content = ob_get_clean();

ob_start();
?>
<script>
$(document).ready(function() {
    // Actualizar datos del funcionario al seleccionar
    $('#funcionario_id').on('change', function() {
        var selected = $(this).find(':selected');
        $('#funcionario_rut').val(selected.data('rut') || '');
        $('#funcionario_cargo').val(selected.data('cargo') || '');
    });
    
    // Trigger inicial si hay valor seleccionado
    $('#funcionario_id').trigger('change');
    
    // Mostrar/ocultar campo de patente
    $('input[name="medio_traslado"]').on('change', function() {
        if ($(this).val() === 'vehiculo_particular') {
            $('#patente_container').slideDown();
            $('#patente_vehiculo').prop('required', true);
        } else {
            $('#patente_container').slideUp();
            $('#patente_vehiculo').prop('required', false);
        }
    });
    
    // Trigger inicial
    $('input[name="medio_traslado"]:checked').trigger('change');
    
    // Validar fecha término >= fecha inicio
    $('#fecha_termino').on('change', function() {
        var inicio = $('#fecha_inicio').val();
        var termino = $(this).val();
        if (inicio && termino && termino < inicio) {
            Swal.fire({
                icon: 'warning',
                title: 'Fecha inválida',
                text: 'La fecha de término no puede ser anterior a la fecha de inicio'
            });
            $(this).val('');
        }
    });
});
</script>
<?php
$scripts = ob_get_clean();

include VIEWS_PATH . 'layout.php';
?>
