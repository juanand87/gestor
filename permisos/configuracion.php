<?php
/**
 * Configuración de Feriados Legales por Funcionario
 */
require_once '../includes/init.php';
Auth::requireLogin();
Auth::requirePermission('permisos_configurar');

$db = Database::getInstance();
$user = Session::getUser();

// Obtener año seleccionado
$anio = (int)($_GET['anio'] ?? date('Y'));
$anios_disponibles = range(date('Y') - 2, date('Y') + 1);

// Obtener funcionarios activos
$funcionarios = $db->select(
    "SELECT f.*, 
            (SELECT dias_asignados FROM feriados_legales_config WHERE funcionario_id = f.id AND anio = ?) as dias_asignados,
            (SELECT dias_utilizados FROM feriados_legales_config WHERE funcionario_id = f.id AND anio = ?) as dias_utilizados
     FROM funcionarios f
     WHERE f.activo = 1
     ORDER BY f.apellido_paterno, f.apellido_materno, f.nombre",
    [$anio, $anio]
);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'guardar_individual') {
        $funcionario_id = (int)($_POST['funcionario_id'] ?? 0);
        $dias = (int)($_POST['dias'] ?? 0);
        
        if ($funcionario_id && $dias >= 0) {
            // Verificar si ya existe
            $existe = $db->selectOne(
                "SELECT * FROM feriados_legales_config WHERE funcionario_id = ? AND anio = ?",
                [$funcionario_id, $anio]
            );
            
            if ($existe) {
                // Verificar que no se reduzca por debajo de lo utilizado
                if ($dias < $existe['dias_utilizados']) {
                    Session::setFlash('No puede asignar menos días de los ya utilizados (' . $existe['dias_utilizados'] . ').', 'danger');
                } else {
                    $db->update('feriados_legales_config', 
                        ['dias_asignados' => $dias], 
                        ['id' => $existe['id']]
                    );
                    Session::setFlash('Días actualizados correctamente.', 'success');
                }
            } else {
                $db->insert('feriados_legales_config', [
                    'funcionario_id' => $funcionario_id,
                    'anio' => $anio,
                    'dias_asignados' => $dias,
                    'dias_utilizados' => 0
                ]);
                Session::setFlash('Días asignados correctamente.', 'success');
            }
        }
        redirect('permisos/configuracion.php?anio=' . $anio);
    }
    
    if ($action === 'guardar_masivo') {
        $dias_defecto = (int)($_POST['dias_defecto'] ?? 15);
        $solo_sin_asignar = isset($_POST['solo_sin_asignar']);
        
        foreach ($funcionarios as $f) {
            if ($solo_sin_asignar && $f['dias_asignados'] !== null) {
                continue;
            }
            
            $existe = $db->selectOne(
                "SELECT * FROM feriados_legales_config WHERE funcionario_id = ? AND anio = ?",
                [$f['id'], $anio]
            );
            
            if ($existe) {
                if ($dias_defecto >= $existe['dias_utilizados']) {
                    $db->update('feriados_legales_config', 
                        ['dias_asignados' => $dias_defecto], 
                        ['id' => $existe['id']]
                    );
                }
            } else {
                $db->insert('feriados_legales_config', [
                    'funcionario_id' => $f['id'],
                    'anio' => $anio,
                    'dias_asignados' => $dias_defecto,
                    'dias_utilizados' => 0
                ]);
            }
        }
        
        Session::setFlash('Días asignados masivamente.', 'success');
        redirect('permisos/configuracion.php?anio=' . $anio);
    }
}

$pageTitle = 'Configuración de Feriados Legales';
$currentPage = 'permisos';

ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/permisos/">Permisos</a></li>
                <li class="breadcrumb-item active">Configuración</li>
            </ol>
        </nav>
        <h2><i class="bi bi-gear text-primary me-2"></i>Configuración de Feriados Legales</h2>
    </div>
</div>

<div class="row mb-4">
    <!-- Selector de año -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-calendar me-2"></i>Seleccionar Año</h6>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div class="input-group">
                        <select name="anio" class="form-select">
                            <?php foreach ($anios_disponibles as $a): ?>
                                <option value="<?= $a ?>" <?= $anio == $a ? 'selected' : '' ?>><?= $a ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Asignación masiva -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-people me-2"></i>Asignación Masiva</h6>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="action" value="guardar_masivo">
                    <div class="col-md-4">
                        <label class="form-label">Días a asignar</label>
                        <input type="number" name="dias_defecto" class="form-control" value="15" min="0" max="50">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input type="checkbox" name="solo_sin_asignar" id="solo_sin_asignar" class="form-check-input" checked>
                            <label class="form-check-label" for="solo_sin_asignar">
                                Solo funcionarios sin asignar
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success w-100" 
                                onclick="return confirm('¿Está seguro de realizar la asignación masiva?')">
                            <i class="bi bi-check-all me-2"></i>Asignar a Todos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de funcionarios -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-sun me-2"></i>Feriados Legales - Año <?= $anio ?>
        </h5>
        <span class="badge bg-primary"><?= count($funcionarios) ?> funcionarios</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="tablaFuncionarios">
                <thead class="table-dark">
                    <tr>
                        <th>Funcionario</th>
                        <th>RUT</th>
                        <th>Cargo</th>
                        <th class="text-center">Días Asignados</th>
                        <th class="text-center">Días Utilizados</th>
                        <th class="text-center">Disponibles</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $f): ?>
                    <tr>
                        <td>
                            <strong><?= e($f['apellido_paterno'] . ' ' . $f['apellido_materno']) ?></strong>,
                            <?= e($f['nombre']) ?>
                        </td>
                        <td><?= e($f['rut']) ?></td>
                        <td><?= e($f['cargo']) ?></td>
                        <td class="text-center">
                            <?php if ($f['dias_asignados'] !== null): ?>
                                <span class="badge bg-primary fs-6"><?= $f['dias_asignados'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($f['dias_utilizados'] !== null && $f['dias_utilizados'] > 0): ?>
                                <span class="badge bg-warning text-dark fs-6"><?= $f['dias_utilizados'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($f['dias_asignados'] !== null): ?>
                                <?php $disponibles = $f['dias_asignados'] - ($f['dias_utilizados'] ?? 0); ?>
                                <span class="badge <?= $disponibles > 0 ? 'bg-success' : 'bg-danger' ?> fs-6">
                                    <?= $disponibles ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    data-bs-toggle="modal" data-bs-target="#modalEditar"
                                    data-id="<?= $f['id'] ?>"
                                    data-nombre="<?= e($f['nombre'] . ' ' . $f['apellido_paterno']) ?>"
                                    data-dias="<?= $f['dias_asignados'] ?? 15 ?>"
                                    data-utilizados="<?= $f['dias_utilizados'] ?? 0 ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="guardar_individual">
                <input type="hidden" name="funcionario_id" id="modal_funcionario_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-sun me-2"></i>Asignar Días de Feriado Legal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Funcionario:</strong> <span id="modal_nombre"></span></p>
                    <p><strong>Año:</strong> <?= $anio ?></p>
                    <p class="text-muted small" id="modal_info_utilizados"></p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Días a Asignar</label>
                        <input type="number" name="dias" id="modal_dias" class="form-control" 
                               min="0" max="50" required>
                        <div class="form-text">Según la legislación chilena, son 15 días hábiles anuales.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DataTable
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#tablaFuncionarios').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            order: [[0, 'asc']],
            pageLength: 25
        });
    }
    
    // Modal editar
    const modalEditar = document.getElementById('modalEditar');
    modalEditar.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        
        document.getElementById('modal_funcionario_id').value = button.dataset.id;
        document.getElementById('modal_nombre').textContent = button.dataset.nombre;
        document.getElementById('modal_dias').value = button.dataset.dias;
        document.getElementById('modal_dias').min = button.dataset.utilizados;
        
        const utilizados = parseInt(button.dataset.utilizados);
        if (utilizados > 0) {
            document.getElementById('modal_info_utilizados').textContent = 
                'Días ya utilizados: ' + utilizados + ' (no puede asignar menos de esto)';
        } else {
            document.getElementById('modal_info_utilizados').textContent = '';
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include VIEWS_PATH . 'layout.php';
?>
