<?php
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('cometidos_editar');

$db = Database::getInstance();
$user = Session::getUser();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    Session::setFlash('error', 'Cometido no válido.');
    redirect(APP_URL . '/cometidos/');
}

// Obtener cometido
$cometido = $db->selectOne("SELECT * FROM cometidos WHERE id = :id", ['id' => $id]);

if (!$cometido) {
    Session::setFlash('error', 'Cometido no encontrado.');
    redirect(APP_URL . '/cometidos/');
}

// Solo se puede editar si está en borrador
if ($cometido['estado_id'] != 1) {
    Session::setFlash('error', 'Solo se pueden editar cometidos en estado borrador.');
    redirect(APP_URL . '/cometidos/ver.php?id=' . $id);
}

// Verificar permisos
if ($cometido['creado_por'] != $user['id'] && !Auth::isAdmin()) {
    Session::setFlash('error', 'No tiene permisos para editar este cometido.');
    redirect(APP_URL . '/cometidos/');
}

// Obtener funcionarios activos
$funcionarios = $db->select(
    "SELECT id, rut, nombre, apellido_paterno, apellido_materno, cargo 
     FROM funcionarios WHERE activo = 1 ORDER BY apellido_paterno, nombre"
);

// Obtener autoridades
$autoridades = $db->select(
    "SELECT id, nombre, apellido_paterno, apellido_materno, cargo 
     FROM funcionarios 
     WHERE activo = 1 AND (es_secretario_ejecutivo = 1 OR es_subrogante = 1)
     ORDER BY es_secretario_ejecutivo DESC, apellido_paterno"
);

// Obtener funcionarios actuales del cometido
$funcionariosActuales = $db->select(
    "SELECT funcionario_id FROM cometidos_funcionarios WHERE cometido_id = :id",
    ['id' => $id]
);
$funcionariosActualesIds = array_column($funcionariosActuales, 'funcionario_id');

// Si no hay en la tabla de relación, usar el funcionario principal
if (empty($funcionariosActualesIds) && $cometido['funcionario_id']) {
    $funcionariosActualesIds = [$cometido['funcionario_id']];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación
    $funcionarios_ids = $_POST['funcionarios_ids'] ?? [];
    $funcionarios_ids = array_filter(array_map('intval', $funcionarios_ids));
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
    if (empty($funcionarios_ids)) $errors[] = 'Debe seleccionar al menos un funcionario';
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
            
            // Determinar estado
            $estado_id = ($accion === 'enviar') ? 2 : 1;
            
            $data = [
                'funcionario_id' => $funcionarios_ids[0], // Primer funcionario como principal
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
                'estado_id' => $estado_id
            ];
            
            if ($accion === 'enviar') {
                $data['fecha_envio_autorizacion'] = date('Y-m-d H:i:s');
            }
            
            $db->update('cometidos', $data, 'id = :id', ['id' => $id]);
            
            // Actualizar funcionarios en la tabla de relación
            $db->delete('cometidos_funcionarios', 'cometido_id = :id', ['id' => $id]);
            foreach ($funcionarios_ids as $func_id) {
                $db->insert('cometidos_funcionarios', [
                    'cometido_id' => $id,
                    'funcionario_id' => $func_id
                ]);
            }
            
            // Registrar en historial
            $accion_historial = ($accion === 'enviar') ? 'Cometido editado y enviado a autorización' : 'Cometido editado';
            registrarHistorial($id, $accion_historial, 1, $estado_id);
            
            $db->commit();
            
            Session::setFlash('success', 'Cometido actualizado correctamente.');
            redirect(APP_URL . '/cometidos/ver.php?id=' . $id);
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Error al guardar el cometido: ' . $e->getMessage();
        }
    }
} else {
    // Cargar datos del cometido
    $_POST = $cometido;
    $_POST['funcionarios_ids'] = $funcionariosActualesIds;
}

$pageTitle = 'Editar Cometido ' . $cometido['numero_cometido'];
$currentPage = 'cometidos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/cometidos/">Cometidos</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/cometidos/ver.php?id=<?= $id ?>"><?= e($cometido['numero_cometido']) ?></a></li>
                <li class="breadcrumb-item active">Editar</li>
            </ol>
        </nav>
        <h2><i class="bi bi-pencil me-2"></i>Editar Cometido <?= e($cometido['numero_cometido']) ?></h2>
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
            <h5 class="mb-0"><i class="bi bi-people me-2"></i>1. Identificación del/los Funcionario(s)</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="funcionario_selector" class="form-label">Agregar funcionario <span class="text-danger">*</span></label>
                    <select class="form-select select2-single" id="funcionario_selector">
                        <option value="">Buscar y seleccionar funcionario...</option>
                        <?php foreach ($funcionarios as $f): ?>
                        <option value="<?= $f['id'] ?>" 
                                data-rut="<?= e($f['rut']) ?>" 
                                data-cargo="<?= e($f['cargo']) ?>">
                            <?= e($f['apellido_paterno'] . ' ' . $f['apellido_materno'] . ', ' . $f['nombre'] . ' - ' . $f['rut']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Seleccione funcionarios uno a uno. Puede agregar múltiples funcionarios.</div>
                </div>
                <div class="col-md-4 mb-3 d-flex align-items-end">
                    <button type="button" id="btn_agregar_funcionario" class="btn btn-outline-primary w-100">
                        <i class="bi bi-plus-circle me-2"></i>Agregar
                    </button>
                </div>
            </div>
            
            <!-- Inputs ocultos para los funcionarios -->
            <div id="funcionarios_hidden_inputs"></div>
            
            <!-- Tabla de funcionarios seleccionados -->
            <div id="funcionarios_seleccionados" class="mt-3" style="display: none;">
                <h6 class="text-muted"><i class="bi bi-people me-2"></i>Funcionarios seleccionados:</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="tabla_funcionarios">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>RUT</th>
                                <th>Cargo</th>
                                <th width="50" class="text-center">Quitar</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            
            <div id="funcionarios_vacio" class="alert alert-warning mt-3">
                <i class="bi bi-exclamation-triangle me-2"></i>Debe agregar al menos un funcionario.
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
                                <?= ($_POST['autoridad_id'] == $a['id']) ? 'selected' : '' ?>>
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
                <textarea class="form-control" id="objetivo" name="objetivo" rows="4" required><?= e($_POST['objetivo'] ?? '') ?></textarea>
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
                                   value="vehiculo_asociacion" <?= ($_POST['medio_traslado'] ?? '') === 'vehiculo_asociacion' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="medio_asociacion">Vehículo de la Asociación</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="medio_traslado" id="medio_particular" 
                                   value="vehiculo_particular" <?= ($_POST['medio_traslado'] ?? '') === 'vehiculo_particular' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="medio_particular">Vehículo Particular</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="medio_traslado" id="medio_publico" 
                                   value="transporte_publico" <?= ($_POST['medio_traslado'] ?? '') === 'transporte_publico' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="medio_publico">Transporte Público / Bus / Avión</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div id="patente_container" style="<?= ($_POST['medio_traslado'] ?? '') !== 'vehiculo_particular' ? 'display:none' : '' ?>">
                        <label for="patente_vehiculo" class="form-label">Patente del vehículo</label>
                        <input type="text" class="form-control" id="patente_vehiculo" name="patente_vehiculo" 
                               maxlength="10" value="<?= e($_POST['patente_vehiculo'] ?? '') ?>">
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
                               min="0" step="1" value="<?= e($_POST['viatico'] ?? '0') ?>">
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
                               <?= ($_POST['dentro_comuna'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dentro_comuna">Dentro de la comuna</label>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dentro_jornada" name="dentro_jornada" value="1"
                               <?= ($_POST['dentro_jornada'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="dentro_jornada">Dentro de la jornada laboral</label>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="con_costo" name="con_costo" value="1"
                               <?= ($_POST['con_costo'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="con_costo">Con costo para la institución</label>
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
                        <i class="bi bi-save me-2"></i>Guardar Cambios
                    </button>
                </div>
                <div class="col-md-6 mb-2">
                    <button type="submit" name="accion" value="enviar" class="btn btn-primary w-100">
                        <i class="bi bi-send me-2"></i>Guardar y Enviar a Autorización
                    </button>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="<?= APP_URL ?>/cometidos/ver.php?id=<?= $id ?>" class="btn btn-outline-secondary">
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
var funcionariosData = {};
<?php foreach ($funcionarios as $f): ?>
funcionariosData[<?= $f['id'] ?>] = {
    nombre: <?= json_encode($f['nombre'] . ' ' . $f['apellido_paterno'] . ' ' . $f['apellido_materno'], JSON_UNESCAPED_UNICODE) ?>,
    rut: <?= json_encode($f['rut'], JSON_UNESCAPED_UNICODE) ?>,
    cargo: <?= json_encode($f['cargo'], JSON_UNESCAPED_UNICODE) ?>
};
<?php endforeach; ?>

var funcionariosSeleccionados = [];

$(document).ready(function() {
    // Inicializar Select2 para selector individual
    $('.select2-single').select2({
        theme: 'bootstrap-5',
        language: 'es',
        placeholder: 'Buscar y seleccionar funcionario...',
        allowClear: true
    });
    
    // Cargar funcionarios actuales del cometido
    <?php foreach ($funcionariosActualesIds as $fid): ?>
    funcionariosSeleccionados.push(<?= (int)$fid ?>);
    <?php endforeach; ?>
    actualizarTablaFuncionarios();
    
    // Botón agregar funcionario
    $('#btn_agregar_funcionario').on('click', function() {
        var id = $('#funcionario_selector').val();
        if (!id) {
            Swal.fire({
                icon: 'warning',
                title: 'Seleccione un funcionario',
                text: 'Debe seleccionar un funcionario del listado antes de agregar.'
            });
            return;
        }
        
        id = parseInt(id);
        if (funcionariosSeleccionados.indexOf(id) !== -1) {
            Swal.fire({
                icon: 'info',
                title: 'Funcionario ya agregado',
                text: 'Este funcionario ya está en la lista.'
            });
            return;
        }
        
        funcionariosSeleccionados.push(id);
        actualizarTablaFuncionarios();
        
        // Limpiar selector
        $('#funcionario_selector').val('').trigger('change');
    });
    
    // Función para actualizar la tabla
    function actualizarTablaFuncionarios() {
        var tbody = $('#tabla_funcionarios tbody');
        var hiddenInputs = $('#funcionarios_hidden_inputs');
        tbody.empty();
        hiddenInputs.empty();
        
        if (funcionariosSeleccionados.length > 0) {
            $('#funcionarios_seleccionados').show();
            $('#funcionarios_vacio').hide();
            
            funcionariosSeleccionados.forEach(function(id) {
                var f = funcionariosData[id];
                if (f) {
                    tbody.append(
                        '<tr data-id="' + id + '">' +
                        '<td>' + f.nombre + '</td>' +
                        '<td>' + f.rut + '</td>' +
                        '<td>' + f.cargo + '</td>' +
                        '<td class="text-center">' +
                        '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar" data-id="' + id + '" title="Quitar funcionario">' +
                        '<i class="bi bi-trash"></i>' +
                        '</button>' +
                        '</td>' +
                        '</tr>'
                    );
                    hiddenInputs.append('<input type="hidden" name="funcionarios_ids[]" value="' + id + '">');
                }
            });
        } else {
            $('#funcionarios_seleccionados').hide();
            $('#funcionarios_vacio').show();
        }
    }
    
    // Eliminar funcionario de la tabla
    $(document).on('click', '.btn-quitar', function() {
        var id = parseInt($(this).data('id'));
        var index = funcionariosSeleccionados.indexOf(id);
        if (index > -1) {
            funcionariosSeleccionados.splice(index, 1);
        }
        actualizarTablaFuncionarios();
    });
    
    // Mostrar/ocultar campo de patente
    $('input[name="medio_traslado"]').on('change', function() {
        if ($(this).val() === 'vehiculo_particular') {
            $('#patente_container').slideDown();
        } else {
            $('#patente_container').slideUp();
        }
    });
});
</script>
<?php
$scripts = ob_get_clean();

include VIEWS_PATH . 'layout.php';
?>
