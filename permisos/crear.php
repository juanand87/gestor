<?php
/**
 * Crear solicitud de permiso
 */
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('permisos_crear');

$db = Database::getInstance();
$user = Session::getUser();
$anioActual = date('Y');

// Determinar tipo de permiso
$tipoCodigo = $_GET['tipo'] ?? 'feriado_legal';
$tipoPermiso = $db->selectOne(
    "SELECT * FROM tipos_permiso WHERE codigo = :codigo AND activo = 1",
    ['codigo' => $tipoCodigo]
);

if (!$tipoPermiso) {
    Session::setFlash('error', 'Tipo de permiso no válido.');
    redirect(APP_URL . '/permisos/');
}

// Obtener funcionarios (admin puede seleccionar cualquiera, usuario solo él mismo)
if (Auth::isAdmin()) {
    $funcionarios = $db->select(
        "SELECT id, rut, nombre, apellido_paterno, apellido_materno, cargo 
         FROM funcionarios WHERE activo = 1 ORDER BY apellido_paterno, nombre"
    );
} else {
    $funcionarios = $db->select(
        "SELECT id, rut, nombre, apellido_paterno, apellido_materno, cargo 
         FROM funcionarios WHERE id = :fid",
        ['fid' => $user['funcionario_id']]
    );
}

// Años disponibles para feriado legal (año actual y anteriores con días)
$aniosDisponibles = [];
if ($tipoCodigo == 'feriado_legal') {
    $aniosConDias = $db->select(
        "SELECT DISTINCT anio, dias_asignados - dias_utilizados as dias_restantes 
         FROM feriados_legales_config 
         WHERE funcionario_id = :fid AND (dias_asignados - dias_utilizados) > 0
         ORDER BY anio DESC",
        ['fid' => $user['funcionario_id'] ?? 0]
    );
    foreach ($aniosConDias as $a) {
        $aniosDisponibles[$a['anio']] = $a['dias_restantes'];
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $funcionario_id = (int)($_POST['funcionario_id'] ?? 0);
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_termino = $_POST['fecha_termino'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    $accion = $_POST['accion'] ?? 'guardar';
    
    // Para permisos administrativos
    $es_medio_dia = isset($_POST['es_medio_dia']) ? 1 : 0;
    $medio_dia_tipo = $_POST['medio_dia_tipo'] ?? null;
    
    // Para feriado legal
    $anio_descuento = (int)($_POST['anio_descuento'] ?? $anioActual);
    
    // Validaciones
    if ($funcionario_id <= 0) $errors[] = 'Debe seleccionar un funcionario';
    if (empty($fecha_inicio)) $errors[] = 'La fecha de inicio es requerida';
    if (empty($fecha_termino)) $errors[] = 'La fecha de término es requerida';
    if (!empty($fecha_inicio) && !empty($fecha_termino) && $fecha_termino < $fecha_inicio) {
        $errors[] = 'La fecha de término no puede ser anterior a la fecha de inicio';
    }
    
    // Calcular días solicitados
    $dias_solicitados = 0;
    if (!empty($fecha_inicio) && !empty($fecha_termino)) {
        $inicio = new DateTime($fecha_inicio);
        $termino = new DateTime($fecha_termino);
        $diff = $inicio->diff($termino);
        $dias_solicitados = $diff->days + 1;
        
        // Si es medio día, ajustar
        if ($tipoCodigo == 'permiso_administrativo' && $es_medio_dia) {
            $dias_solicitados = 0.5;
        }
    }
    
    // Validar disponibilidad de días
    if ($tipoCodigo == 'feriado_legal') {
        $config = $db->selectOne(
            "SELECT * FROM feriados_legales_config WHERE funcionario_id = :fid AND anio = :anio",
            ['fid' => $funcionario_id, 'anio' => $anio_descuento]
        );
        if (!$config) {
            $errors[] = "No tiene días de feriado legal asignados para el año $anio_descuento";
        } elseif (($config['dias_asignados'] - $config['dias_utilizados']) < $dias_solicitados) {
            $disponibles = $config['dias_asignados'] - $config['dias_utilizados'];
            $errors[] = "No tiene suficientes días de feriado legal. Disponibles: $disponibles días";
        }
    } else {
        // Permiso administrativo
        $config = $db->selectOne(
            "SELECT * FROM permisos_administrativos_config WHERE funcionario_id = :fid AND anio = :anio",
            ['fid' => $funcionario_id, 'anio' => $anioActual]
        );
        
        // Crear configuración si no existe
        if (!$config) {
            $db->insert('permisos_administrativos_config', [
                'funcionario_id' => $funcionario_id,
                'anio' => $anioActual,
                'dias_disponibles' => 6.0,
                'dias_utilizados' => 0
            ]);
            $config = ['dias_disponibles' => 6.0, 'dias_utilizados' => 0];
        }
        
        if (($config['dias_disponibles'] - $config['dias_utilizados']) < $dias_solicitados) {
            $disponibles = $config['dias_disponibles'] - $config['dias_utilizados'];
            $errors[] = "No tiene suficientes días de permiso administrativo. Disponibles: $disponibles días";
        }
        
        $anio_descuento = $anioActual;
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generar número de solicitud
            $prefijo = ($tipoCodigo == 'feriado_legal') ? 'FL' : 'PA';
            $ultimoNumero = $db->selectOne(
                "SELECT MAX(CAST(SUBSTRING(numero_solicitud, 4) AS UNSIGNED)) as ultimo 
                 FROM solicitudes_permiso 
                 WHERE numero_solicitud LIKE :prefijo",
                ['prefijo' => $prefijo . '-' . $anioActual . '-%']
            );
            $numero = ($ultimoNumero['ultimo'] ?? 0) + 1;
            $numero_solicitud = $prefijo . '-' . $anioActual . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
            
            // Determinar estado inicial
            $estado_id = ($accion === 'enviar') ? 2 : 1;
            
            $data = [
                'numero_solicitud' => $numero_solicitud,
                'tipo_permiso_id' => $tipoPermiso['id'],
                'funcionario_id' => $funcionario_id,
                'solicitado_por' => $user['id'],
                'fecha_inicio' => $fecha_inicio,
                'fecha_termino' => $fecha_termino,
                'es_medio_dia' => $es_medio_dia,
                'medio_dia_tipo' => $es_medio_dia ? $medio_dia_tipo : null,
                'dias_solicitados' => $dias_solicitados,
                'anio_descuento' => $anio_descuento,
                'motivo' => $motivo,
                'estado_id' => $estado_id,
                'fecha_envio_autorizacion' => ($accion === 'enviar') ? date('Y-m-d H:i:s') : null
            ];
            
            $solicitud_id = $db->insert('solicitudes_permiso', $data);
            
            // Registrar en historial
            $accion_historial = ($accion === 'enviar') ? 'Solicitud creada y enviada a autorización' : 'Solicitud creada como borrador';
            $db->insert('historial_permisos', [
                'solicitud_id' => $solicitud_id,
                'usuario_id' => $user['id'],
                'accion' => $accion_historial,
                'estado_anterior_id' => null,
                'estado_nuevo_id' => $estado_id
            ]);
            
            $db->commit();
            
            Session::setFlash('success', 'Solicitud ' . $numero_solicitud . ' creada correctamente.');
            redirect(APP_URL . '/permisos/ver.php?id=' . $solicitud_id);
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Error al guardar la solicitud: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Nueva Solicitud - ' . $tipoPermiso['nombre'];
$currentPage = 'permisos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/permisos/">Permisos</a></li>
                <li class="breadcrumb-item active">Nueva Solicitud</li>
            </ol>
        </nav>
        <h2>
            <?php if ($tipoCodigo == 'feriado_legal'): ?>
                <i class="bi bi-sun text-success me-2"></i>Solicitar Feriado Legal
            <?php else: ?>
                <i class="bi bi-clock text-primary me-2"></i>Solicitar Permiso Administrativo
            <?php endif; ?>
        </h2>
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
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Funcionario -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-person me-2"></i>Funcionario</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="funcionario_id" class="form-label">Funcionario <span class="text-danger">*</span></label>
                        <?php if (Auth::isAdmin() && count($funcionarios) > 1): ?>
                            <select class="form-select select2" id="funcionario_id" name="funcionario_id" required>
                                <option value="">Seleccione funcionario...</option>
                                <?php foreach ($funcionarios as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= (($_POST['funcionario_id'] ?? $user['funcionario_id']) == $f['id']) ? 'selected' : '' ?>>
                                    <?= e($f['apellido_paterno'] . ' ' . $f['apellido_materno'] . ', ' . $f['nombre'] . ' - ' . $f['rut']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="hidden" name="funcionario_id" value="<?= $funcionarios[0]['id'] ?? '' ?>">
                            <p class="form-control-plaintext fw-bold">
                                <?= e(($funcionarios[0]['nombre'] ?? '') . ' ' . ($funcionarios[0]['apellido_paterno'] ?? '') . ' ' . ($funcionarios[0]['apellido_materno'] ?? '')) ?>
                                <br><small class="text-muted"><?= e($funcionarios[0]['rut'] ?? '') ?> - <?= e($funcionarios[0]['cargo'] ?? '') ?></small>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Fechas -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Período Solicitado</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_inicio" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required
                                   value="<?= e($_POST['fecha_inicio'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_termino" class="form-label">Fecha de término <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="fecha_termino" name="fecha_termino" required
                                   value="<?= e($_POST['fecha_termino'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <?php if ($tipoCodigo == 'permiso_administrativo'): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="es_medio_dia" name="es_medio_dia" value="1"
                                       <?= isset($_POST['es_medio_dia']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="es_medio_dia">
                                    <strong>Solicitar solo medio día</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6" id="medio_dia_options" style="display: none;">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="medio_dia_tipo" id="tipo_manana" value="manana"
                                       <?= (($_POST['medio_dia_tipo'] ?? '') == 'manana') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tipo_manana">Mañana</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="medio_dia_tipo" id="tipo_tarde" value="tarde"
                                       <?= (($_POST['medio_dia_tipo'] ?? '') == 'tarde') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tipo_tarde">Tarde</label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($tipoCodigo == 'feriado_legal' && !empty($aniosDisponibles)): ?>
                    <div class="mt-3">
                        <label for="anio_descuento" class="form-label">Descontar días del año:</label>
                        <select class="form-select" id="anio_descuento" name="anio_descuento">
                            <?php foreach ($aniosDisponibles as $anio => $dias): ?>
                            <option value="<?= $anio ?>" <?= $anio == $anioActual ? 'selected' : '' ?>>
                                <?= $anio ?> (<?= number_format($dias, 1) ?> días disponibles)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="anio_descuento" value="<?= $anioActual ?>">
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Días a solicitar:</strong> <span id="dias_calculados">0</span> día(s)
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Motivo -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-chat-text me-2"></i>Motivo (opcional)</h6>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="motivo" name="motivo" rows="3" 
                              placeholder="Indique el motivo de su solicitud..."><?= e($_POST['motivo'] ?? '') ?></textarea>
                </div>
            </div>
            
            <!-- Botones -->
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
                                <i class="bi bi-send me-2"></i>Enviar a Autorización
                            </button>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= APP_URL ?>/permisos/" class="btn btn-outline-secondary">
                            <i class="bi bi-x me-2"></i>Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar con información -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información</h6>
                </div>
                <div class="card-body">
                    <?php if ($tipoCodigo == 'feriado_legal'): ?>
                        <p><strong>Feriado Legal (Vacaciones)</strong></p>
                        <p class="text-muted small">
                            Los días de feriado legal son asignados anualmente a cada funcionario.
                            Puede solicitar días del año en curso o de años anteriores si tiene días pendientes.
                        </p>
                    <?php else: ?>
                        <p><strong>Permiso Administrativo</strong></p>
                        <p class="text-muted small">
                            Tiene derecho a 6 días de permiso administrativo por año.
                            Puede solicitar medio día o día completo.
                            Los permisos se reinician el 1 de enero de cada año.
                        </p>
                    <?php endif; ?>
                </div>
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
    // Calcular días
    function calcularDias() {
        var inicio = $('#fecha_inicio').val();
        var termino = $('#fecha_termino').val();
        var esMedioDia = $('#es_medio_dia').is(':checked');
        
        if (inicio && termino) {
            var fechaInicio = new Date(inicio);
            var fechaTermino = new Date(termino);
            var diff = Math.ceil((fechaTermino - fechaInicio) / (1000 * 60 * 60 * 24)) + 1;
            
            if (esMedioDia) {
                diff = 0.5;
                $('#fecha_termino').val(inicio);
            }
            
            if (diff > 0) {
                $('#dias_calculados').text(diff);
            } else {
                $('#dias_calculados').text('0');
            }
        }
    }
    
    $('#fecha_inicio, #fecha_termino').on('change', calcularDias);
    
    // Medio día toggle
    $('#es_medio_dia').on('change', function() {
        if ($(this).is(':checked')) {
            $('#medio_dia_options').slideDown();
            $('#fecha_termino').val($('#fecha_inicio').val());
            $('#fecha_termino').prop('disabled', true);
            $('#tipo_manana').prop('checked', true);
        } else {
            $('#medio_dia_options').slideUp();
            $('#fecha_termino').prop('disabled', false);
        }
        calcularDias();
    });
    
    // Inicializar Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        language: 'es'
    });
    
    // Trigger inicial
    if ($('#es_medio_dia').is(':checked')) {
        $('#medio_dia_options').show();
        $('#fecha_termino').prop('disabled', true);
    }
    calcularDias();
});
</script>
<?php
$scripts = ob_get_clean();

include VIEWS_PATH . 'layout.php';
?>
