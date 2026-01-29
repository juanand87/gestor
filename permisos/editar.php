<?php
/**
 * Editar solicitud de permiso (solo en estado Borrador)
 */
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('permisos_editar');

$db = Database::getInstance();
$user = Session::getUser();

// Verificar ID
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    Session::setFlash('No se especificó la solicitud.', 'danger');
    redirect('permisos/');
}

// Obtener solicitud
$solicitud = $db->selectOne(
    "SELECT sp.*, 
            tp.nombre as tipo_nombre, tp.codigo as tipo_codigo,
            f.nombre as func_nombre, f.apellido_paterno as func_apellido, 
            f.apellido_materno as func_apellido_m, f.rut as func_rut, f.cargo as func_cargo
     FROM solicitudes_permiso sp
     INNER JOIN tipos_permiso tp ON sp.tipo_permiso_id = tp.id
     INNER JOIN funcionarios f ON sp.funcionario_id = f.id
     WHERE sp.id = ?",
    [$id]
);

if (!$solicitud) {
    Session::setFlash('Solicitud no encontrada.', 'danger');
    redirect('permisos/');
}

// Verificar que está en borrador
if ($solicitud['estado_id'] != 1) {
    Session::setFlash('Solo se pueden editar solicitudes en estado Borrador.', 'warning');
    redirect('permisos/ver.php?id=' . $id);
}

// Verificar permisos (solo el solicitante o admin puede editar)
if ($user['rol_id'] != ROL_ADMIN && $solicitud['solicitado_por'] != $user['id']) {
    Session::setFlash('No tienes permiso para editar esta solicitud.', 'danger');
    redirect('permisos/');
}

// Obtener tipos de permiso
$tipos = $db->select("SELECT * FROM tipos_permiso WHERE activo = 1 ORDER BY nombre");

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_id = (int)($_POST['tipo_permiso_id'] ?? 0);
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_termino = trim($_POST['fecha_termino'] ?? '');
    $es_medio_dia = isset($_POST['es_medio_dia']) ? 1 : 0;
    $motivo = trim($_POST['motivo'] ?? '');
    $anio_aplicacion = (int)($_POST['anio_aplicacion'] ?? date('Y'));
    
    $errors = [];
    
    // Validaciones básicas
    if (!$tipo_id) {
        $errors[] = 'Debe seleccionar un tipo de permiso.';
    }
    if (!$fecha_inicio) {
        $errors[] = 'Debe ingresar la fecha de inicio.';
    }
    if (!$fecha_termino) {
        $errors[] = 'Debe ingresar la fecha de término.';
    }
    if ($fecha_inicio && $fecha_termino && $fecha_inicio > $fecha_termino) {
        $errors[] = 'La fecha de término debe ser igual o posterior a la fecha de inicio.';
    }
    
    if (empty($errors)) {
        // Obtener info del tipo
        $tipo = $db->selectOne("SELECT * FROM tipos_permiso WHERE id = ?", [$tipo_id]);
        
        // Función para calcular días hábiles (excluye sábados y domingos)
        $inicio = new DateTime($fecha_inicio);
        $termino = new DateTime($fecha_termino);
        $dias = 0;
        
        while ($inicio <= $termino) {
            $diaSemana = (int)$inicio->format('N'); // 1=Lunes, 7=Domingo
            if ($diaSemana < 6) { // Lunes a Viernes
                $dias++;
            }
            $inicio->modify('+1 day');
        }
        
        if ($tipo['codigo'] == 'permiso_administrativo' && $es_medio_dia) {
            $dias = 0.5;
        }
        
        // Verificar disponibilidad
        $funcionario_id = $solicitud['funcionario_id'];
        
        if ($tipo['codigo'] == 'feriado_legal') {
            // Obtener días asignados para el año
            $config = $db->selectOne(
                "SELECT * FROM feriados_legales_config 
                 WHERE funcionario_id = ? AND anio = ?",
                [$funcionario_id, $anio_aplicacion]
            );
            
            if (!$config) {
                $errors[] = 'No tiene días de feriado legal asignados para el año ' . $anio_aplicacion;
            } else {
                $dias_disponibles = $config['dias_asignados'] - $config['dias_utilizados'];
                
                // Sumar los días de la solicitud original que vamos a liberar
                if ($solicitud['tipo_codigo'] == 'feriado_legal' && $solicitud['anio_aplicacion'] == $anio_aplicacion) {
                    $dias_disponibles += $solicitud['dias_solicitados'];
                }
                
                if ($dias > $dias_disponibles) {
                    $errors[] = 'No tiene suficientes días de feriado legal. Disponibles: ' . $dias_disponibles;
                }
            }
        } else {
            // Permiso administrativo
            $config = $db->selectOne(
                "SELECT * FROM permisos_administrativos_config WHERE funcionario_id = ? AND anio = ?",
                [$funcionario_id, date('Y')]
            );
            
            if (!$config) {
                // Crear configuración
                $db->insert('permisos_administrativos_config', [
                    'funcionario_id' => $funcionario_id,
                    'anio' => date('Y'),
                    'dias_disponibles' => 6
                ]);
                $config = ['dias_disponibles' => 6, 'dias_utilizados' => 0];
            }
            
            $dias_disponibles = $config['dias_disponibles'] - $config['dias_utilizados'];
            
            // Sumar los días de la solicitud original
            if ($solicitud['tipo_codigo'] == 'permiso_administrativo') {
                $dias_disponibles += $solicitud['dias_solicitados'];
            }
            
            if ($dias > $dias_disponibles) {
                $errors[] = 'No tiene suficientes días de permiso administrativo. Disponibles: ' . $dias_disponibles;
            }
            
            if ($es_medio_dia && $dias > 1) {
                $errors[] = 'El medio día solo aplica cuando solicita un solo día.';
            }
        }
    }
    
    if (empty($errors)) {
        // Actualizar solicitud
        $db->update('solicitudes_permiso', [
            'tipo_permiso_id' => $tipo_id,
            'fecha_inicio' => $fecha_inicio,
            'fecha_termino' => $fecha_termino,
            'dias_solicitados' => $dias,
            'es_medio_dia' => $es_medio_dia,
            'motivo' => $motivo,
            'anio_aplicacion' => $tipo['codigo'] == 'feriado_legal' ? $anio_aplicacion : date('Y'),
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $id]);
        
        // Registrar en historial
        $db->insert('historial_permisos', [
            'solicitud_id' => $id,
            'estado_id' => 1,
            'comentario' => 'Solicitud modificada',
            'usuario_id' => $user['id'],
            'fecha' => date('Y-m-d H:i:s')
        ]);
        
        Session::setFlash('Solicitud actualizada exitosamente.', 'success');
        redirect('permisos/ver.php?id=' . $id);
    }
}

// Obtener años disponibles para feriados
$anios_feriados = $db->select(
    "SELECT anio, dias_asignados, dias_utilizados 
     FROM feriados_legales_config 
     WHERE funcionario_id = ? AND (dias_asignados - dias_utilizados) > 0
     ORDER BY anio DESC",
    [$solicitud['funcionario_id']]
);

// Obtener permisos administrativos del año actual
$admin_config = $db->selectOne(
    "SELECT * FROM permisos_administrativos_config WHERE funcionario_id = ? AND anio = ?",
    [$solicitud['funcionario_id'], date('Y')]
);

$pageTitle = 'Editar Solicitud de Permiso';
$currentPage = 'permisos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/permisos/">Permisos</a></li>
                <li class="breadcrumb-item"><a href="ver.php?id=<?= $id ?>"><?= e($solicitud['numero_solicitud']) ?></a></li>
                <li class="breadcrumb-item active">Editar</li>
            </ol>
        </nav>
        <h2><i class="bi bi-pencil text-primary me-2"></i>Editar Solicitud de Permiso</h2>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Datos de la Solicitud</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formEditar">
                    <!-- Funcionario (no editable) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Funcionario</label>
                        <input type="text" class="form-control" 
                               value="<?= e($solicitud['func_nombre'] . ' ' . $solicitud['func_apellido'] . ' ' . $solicitud['func_apellido_m'] . ' - ' . $solicitud['func_rut']) ?>" 
                               readonly>
                    </div>
                    
                    <!-- Tipo de permiso -->
                    <div class="mb-3">
                        <label for="tipo_permiso_id" class="form-label fw-bold">Tipo de Permiso *</label>
                        <select name="tipo_permiso_id" id="tipo_permiso_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>" 
                                        data-codigo="<?= $tipo['codigo'] ?>"
                                        <?= $solicitud['tipo_permiso_id'] == $tipo['id'] ? 'selected' : '' ?>>
                                    <?= e($tipo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Año (para feriado legal) -->
                    <div class="mb-3" id="div_anio" style="display: <?= $solicitud['tipo_codigo'] == 'feriado_legal' ? 'block' : 'none' ?>;">
                        <label for="anio_aplicacion" class="form-label fw-bold">Año de los Días a Utilizar *</label>
                        <select name="anio_aplicacion" id="anio_aplicacion" class="form-select">
                            <?php if (empty($anios_feriados)): ?>
                                <option value="">No tiene días asignados</option>
                            <?php else: ?>
                                <?php foreach ($anios_feriados as $anio): ?>
                                    <option value="<?= $anio['anio'] ?>" 
                                            <?= $solicitud['anio_aplicacion'] == $anio['anio'] ? 'selected' : '' ?>>
                                        <?= $anio['anio'] ?> - Disponibles: <?= ($anio['dias_asignados'] - $anio['dias_utilizados']) ?> días
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <!-- Info días disponibles admin -->
                    <div class="mb-3" id="div_info_admin" style="display: <?= $solicitud['tipo_codigo'] == 'permiso_administrativo' ? 'block' : 'none' ?>;">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Días administrativos disponibles (<?= date('Y') ?>):</strong>
                            <?= $admin_config ? (6 - $admin_config['dias_utilizados']) : 6 ?> días
                        </div>
                    </div>
                    
                    <!-- Fechas -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="fecha_inicio" class="form-label fw-bold">Fecha Inicio *</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" 
                                   class="form-control" required
                                   value="<?= e($_POST['fecha_inicio'] ?? $solicitud['fecha_inicio']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="fecha_termino" class="form-label fw-bold">Fecha Término *</label>
                            <input type="date" name="fecha_termino" id="fecha_termino" 
                                   class="form-control" required
                                   value="<?= e($_POST['fecha_termino'] ?? $solicitud['fecha_termino']) ?>">
                        </div>
                    </div>
                    
                    <!-- Medio día (solo admin) -->
                    <div class="mb-3" id="div_medio_dia" style="display: <?= $solicitud['tipo_codigo'] == 'permiso_administrativo' ? 'block' : 'none' ?>;">
                        <div class="form-check">
                            <input type="checkbox" name="es_medio_dia" id="es_medio_dia" 
                                   class="form-check-input" value="1"
                                   <?= $solicitud['es_medio_dia'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="es_medio_dia">
                                Solicitar medio día (0.5)
                            </label>
                        </div>
                    </div>
                    
                    <!-- Info días calculados -->
                    <div class="mb-3">
                        <div class="alert alert-secondary" id="info_dias">
                            <i class="bi bi-calculator me-2"></i>
                            <strong>Días a solicitar:</strong> <span id="dias_calculados"><?= $solicitud['dias_solicitados'] ?></span> día(s) hábiles
                            <br><small class="text-muted">(Sábados y domingos no se cuentan)</small>
                        </div>
                    </div>
                    
                    <!-- Motivo -->
                    <div class="mb-3">
                        <label for="motivo" class="form-label fw-bold">Motivo / Observaciones</label>
                        <textarea name="motivo" id="motivo" class="form-control" rows="3"><?= e($_POST['motivo'] ?? $solicitud['motivo']) ?></textarea>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Guardar Cambios
                        </button>
                        <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información</h6>
            </div>
            <div class="card-body">
                <p><strong>Solicitud:</strong> <?= e($solicitud['numero_solicitud']) ?></p>
                <p><strong>Funcionario:</strong> <?= e($solicitud['func_nombre'] . ' ' . $solicitud['func_apellido']) ?></p>
                <p><strong>Estado:</strong> <span class="badge bg-secondary">Borrador</span></p>
                
                <hr>
                
                <h6>Reglas:</h6>
                <ul class="small text-muted">
                    <li>Los feriados legales se descuentan del año seleccionado</li>
                    <li>Los permisos administrativos se descuentan del año actual</li>
                    <li>Medio día solo aplica para permisos administrativos de 1 día</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoSelect = document.getElementById('tipo_permiso_id');
    const divAnio = document.getElementById('div_anio');
    const divInfoAdmin = document.getElementById('div_info_admin');
    const divMedioDia = document.getElementById('div_medio_dia');
    const fechaInicio = document.getElementById('fecha_inicio');
    const fechaTermino = document.getElementById('fecha_termino');
    const medioDia = document.getElementById('es_medio_dia');
    const diasCalculados = document.getElementById('dias_calculados');
    
    function toggleCampos() {
        const option = tipoSelect.options[tipoSelect.selectedIndex];
        const codigo = option ? option.dataset.codigo : '';
        
        if (codigo === 'feriado_legal') {
            divAnio.style.display = 'block';
            divInfoAdmin.style.display = 'none';
            divMedioDia.style.display = 'none';
            medioDia.checked = false;
        } else if (codigo === 'permiso_administrativo') {
            divAnio.style.display = 'none';
            divInfoAdmin.style.display = 'block';
            divMedioDia.style.display = 'block';
        } else {
            divAnio.style.display = 'none';
            divInfoAdmin.style.display = 'none';
            divMedioDia.style.display = 'none';
        }
        
        calcularDias();
    }
    
    // Función para calcular días hábiles (excluye sábados y domingos)
    function calcularDiasHabiles(fechaInicio, fechaTermino) {
        let dias = 0;
        let actual = new Date(fechaInicio);
        let fin = new Date(fechaTermino);
        
        while (actual <= fin) {
            let diaSemana = actual.getDay(); // 0=Domingo, 6=Sábado
            if (diaSemana !== 0 && diaSemana !== 6) {
                dias++;
            }
            actual.setDate(actual.getDate() + 1);
        }
        
        return dias;
    }
    
    function calcularDias() {
        if (!fechaInicio.value || !fechaTermino.value) {
            diasCalculados.textContent = '0';
            return;
        }
        
        const d1 = new Date(fechaInicio.value + 'T00:00:00');
        const d2 = new Date(fechaTermino.value + 'T00:00:00');
        let dias = calcularDiasHabiles(d1, d2);
        
        if (dias < 0) dias = 0;
        
        const option = tipoSelect.options[tipoSelect.selectedIndex];
        const codigo = option ? option.dataset.codigo : '';
        
        if (codigo === 'permiso_administrativo' && medioDia.checked && dias === 1) {
            dias = 0.5;
        }
        
        diasCalculados.textContent = dias;
    }
    
    tipoSelect.addEventListener('change', toggleCampos);
    fechaInicio.addEventListener('change', calcularDias);
    fechaTermino.addEventListener('change', calcularDias);
    medioDia.addEventListener('change', calcularDias);
    
    // Calcular al cargar
    calcularDias();
});
</script>

<?php
$content = ob_get_clean();
include VIEWS_PATH . 'layout.php';
?>
