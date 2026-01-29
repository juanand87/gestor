<?php
/**
 * Generar PDF de cometido usando TCPDF
 */
require_once '../includes/init.php';
require_once '../libs/tcpdf/tcpdf.php';

Auth::requireLogin();
Auth::requirePermission('cometidos_ver');

$db = Database::getInstance();
$user = Session::getUser();

// Verificar ID
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('No se especificó el cometido.');
}

// Obtener cometido
$cometido = $db->selectOne(
    "SELECT c.*, 
            f.nombre as func_nombre, f.apellido_paterno as func_apellido_paterno, 
            f.apellido_materno as func_apellido_materno, f.rut as func_rut, f.cargo as func_cargo,
            a.nombre as auto_nombre, a.apellido_paterno as auto_apellido, a.cargo as auto_cargo,
            u.username as creado_por_username,
            uf.nombre as creador_nombre, uf.apellido_paterno as creador_apellido,
            e.nombre as estado_nombre, e.color as estado_color,
            ua.username as autorizado_por_username,
            uaf.nombre as autorizador_nombre, uaf.apellido_paterno as autorizador_apellido
     FROM cometidos c
     INNER JOIN funcionarios f ON c.funcionario_id = f.id
     LEFT JOIN funcionarios a ON c.autoridad_id = a.id
     INNER JOIN usuarios u ON c.creado_por = u.id
     LEFT JOIN funcionarios uf ON u.funcionario_id = uf.id
     INNER JOIN estados_documento e ON c.estado_id = e.id
     LEFT JOIN usuarios ua ON c.autoridad_id = ua.funcionario_id
     LEFT JOIN funcionarios uaf ON ua.funcionario_id = uaf.id
     WHERE c.id = ?",
    [$id]
);

if (!$cometido) {
    die('Cometido no encontrado.');
}

// Obtener todos los funcionarios del cometido
$funcionariosCometido = $db->select(
    "SELECT f.* FROM funcionarios f
     INNER JOIN cometidos_funcionarios cf ON f.id = cf.funcionario_id
     WHERE cf.cometido_id = ?
     ORDER BY cf.id",
    [$id]
);

// Si no hay en la tabla de relación, usar el funcionario principal
if (empty($funcionariosCometido)) {
    $funcionariosCometido = $db->select(
        "SELECT * FROM funcionarios WHERE id = ?",
        [$cometido['funcionario_id']]
    );
}

// Verificar permisos de acceso
if (!Auth::isAdmin() && !Auth::isSecretarioEjecutivo()) {
    $esFuncionario = false;
    foreach ($funcionariosCometido as $fc) {
        if ($fc['id'] == $user['funcionario_id']) {
            $esFuncionario = true;
            break;
        }
    }
    if ($cometido['creado_por'] != $user['id'] && !$esFuncionario) {
        die('No tiene permiso para ver este cometido.');
    }
}

// Función para fecha en español
function fechaEspanol($fecha) {
    if (!$fecha) return '-';
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 
              'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $d = new DateTime($fecha);
    return $d->format('d') . ' de ' . $meses[(int)$d->format('n')] . ' de ' . $d->format('Y');
}

// Función para texto del medio de traslado
function getMedioTrasladoTextoPDF($medio) {
    $medios = [
        'vehiculo_institucional' => 'Vehículo Institucional',
        'vehiculo_particular' => 'Vehículo Particular',
        'transporte_publico' => 'Transporte Público',
        'avion' => 'Avión',
        'otro' => 'Otro'
    ];
    return $medios[$medio] ?? $medio;
}

// Crear PDF
class CometidoPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 10, 'ASOCIACIÓN DE MUNICIPIOS', 0, 1, 'C');
        $this->SetFont('helvetica', '', 11);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'Cometido Funcionario', 0, 1, 'C');
        $this->Ln(5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    
    public function Footer() {
        $this->SetY(-20);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, 'Documento generado el ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->Cell(0, 5, 'Sistema de Gestión Documental - Asociación de Municipios', 0, 1, 'C');
        $this->Cell(0, 5, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new CometidoPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);

// Configuración del documento
$pdf->SetCreator('Gestor Documental');
$pdf->SetAuthor('Asociación de Municipios');
$pdf->SetTitle('Cometido - ' . $cometido['numero_cometido']);
$pdf->SetSubject('Cometido Funcionario');

// Márgenes
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);

// Configuración de página
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Agregar página
$pdf->AddPage();

// Título
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, 'COMETIDO FUNCIONARIO', 1, 1, 'C', true);

// Número de cometido
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 8, 'Nº ' . $cometido['numero_cometido'], 0, 1, 'C');
$pdf->Ln(3);

// Estado
$estadoColor = [200, 200, 200]; // Gris por defecto
if ($cometido['estado_id'] == 3) $estadoColor = [212, 237, 218]; // Verde - Autorizado
elseif ($cometido['estado_id'] == 4) $estadoColor = [248, 215, 218]; // Rojo - Rechazado
elseif ($cometido['estado_id'] == 2) $estadoColor = [255, 243, 205]; // Amarillo - Pendiente

$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor($estadoColor[0], $estadoColor[1], $estadoColor[2]);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'ESTADO: ' . strtoupper($cometido['estado_nombre']), 1, 1, 'C', true);

if ($cometido['fecha_autorizacion'] && $cometido['auto_nombre']) {
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'Autorizado el ' . fechaEspanol($cometido['fecha_autorizacion']) . 
               ' por ' . $cometido['auto_nombre'] . ' ' . $cometido['auto_apellido'], 0, 1, 'C');
}
$pdf->Ln(5);

// Función para crear secciones
function crearSeccion($pdf, $numero, $titulo) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, $numero . '. ' . $titulo, 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
}

function crearFila($pdf, $label, $value, $labelWidth = 60) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell($labelWidth, 7, $label, 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 7, $value, 1, 1, 'L');
}

// 1. IDENTIFICACIÓN DEL FUNCIONARIO
crearSeccion($pdf, '1', 'IDENTIFICACIÓN DEL/LOS FUNCIONARIO(S)');

if (count($funcionariosCometido) == 1) {
    $f = $funcionariosCometido[0];
    crearFila($pdf, 'Nombre Completo:', $f['nombre'] . ' ' . $f['apellido_paterno'] . ' ' . $f['apellido_materno']);
    crearFila($pdf, 'RUT:', $f['rut']);
    crearFila($pdf, 'Cargo:', $f['cargo']);
} else {
    // Tabla para múltiples funcionarios
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(80, 6, 'Nombre Completo', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'RUT', 1, 0, 'C', true);
    $pdf->Cell(60, 6, 'Cargo', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 8);
    foreach ($funcionariosCometido as $f) {
        $pdf->Cell(80, 6, $f['nombre'] . ' ' . $f['apellido_paterno'] . ' ' . $f['apellido_materno'], 1, 0, 'L');
        $pdf->Cell(40, 6, $f['rut'], 1, 0, 'L');
        $pdf->Cell(60, 6, $f['cargo'], 1, 1, 'L');
    }
}
$pdf->Ln(3);

// 2. AUTORIDAD QUE DISPONE (si existe)
if ($cometido['auto_nombre']) {
    crearSeccion($pdf, '2', 'AUTORIDAD QUE DISPONE EL COMETIDO');
    crearFila($pdf, 'Autoridad:', $cometido['auto_nombre'] . ' ' . $cometido['auto_apellido'] . ' - ' . $cometido['auto_cargo']);
    $pdf->Ln(3);
}

// 3. OBJETIVO
crearSeccion($pdf, '3', 'OBJETIVO DEL COMETIDO');
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell(0, 6, $cometido['objetivo'], 1, 'L');
$pdf->Ln(3);

// 4. LUGAR
crearSeccion($pdf, '4', 'LUGAR DEL COMETIDO');
crearFila($pdf, 'Ciudad:', $cometido['ciudad']);
crearFila($pdf, 'Comuna:', $cometido['comuna']);
if ($cometido['lugar_descripcion']) {
    crearFila($pdf, 'Descripción:', $cometido['lugar_descripcion']);
}
$pdf->Ln(3);

// 5. FECHA Y DURACIÓN
crearSeccion($pdf, '5', 'FECHA Y DURACIÓN');
crearFila($pdf, 'Fecha de Inicio:', fechaEspanol($cometido['fecha_inicio']));
crearFila($pdf, 'Fecha de Término:', fechaEspanol($cometido['fecha_termino']));
if ($cometido['horario_inicio']) {
    crearFila($pdf, 'Horario Inicio:', $cometido['horario_inicio']);
}
if ($cometido['horario_termino']) {
    crearFila($pdf, 'Horario Término:', $cometido['horario_termino']);
}
$pdf->Ln(3);

// 6. MEDIO DE TRASLADO
crearSeccion($pdf, '6', 'MEDIO DE TRASLADO');
$medioTexto = getMedioTrasladoTextoPDF($cometido['medio_traslado']);
if ($cometido['patente_vehiculo']) {
    $medioTexto .= ' (Patente: ' . $cometido['patente_vehiculo'] . ')';
}
crearFila($pdf, 'Medio:', $medioTexto);
$pdf->Ln(3);

// 7. FINANCIAMIENTO
crearSeccion($pdf, '7', 'FINANCIAMIENTO');
crearFila($pdf, 'Viático:', '$' . number_format($cometido['viatico'], 0, ',', '.'));
$pdf->Ln(3);

// 8. CARÁCTER DEL COMETIDO
crearSeccion($pdf, '8', 'CARÁCTER DEL COMETIDO');
$pdf->SetFont('helvetica', '', 9);
$caracteristicas = [];
$caracteristicas[] = $cometido['dentro_comuna'] ? '✓ Dentro de la comuna' : '✗ Fuera de la comuna';
$caracteristicas[] = $cometido['dentro_jornada'] ? '✓ Dentro de jornada laboral' : '✗ Fuera de jornada laboral';
$caracteristicas[] = $cometido['con_costo'] ? '✓ Con costo para la institución' : '✗ Sin costo para la institución';
$pdf->MultiCell(0, 6, implode("\n", $caracteristicas), 1, 'L');
$pdf->Ln(3);

// MOTIVO DE RECHAZO (si aplica)
if ($cometido['estado_id'] == 4 && $cometido['observaciones_rechazo']) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(248, 215, 218);
    $pdf->SetTextColor(114, 28, 36);
    $pdf->Cell(0, 8, 'MOTIVO DEL RECHAZO', 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 6, $cometido['observaciones_rechazo'], 1, 'L');
    $pdf->Ln(3);
}

// FIRMAS (solo si está autorizado)
if ($cometido['estado_id'] == 3) {
    $pdf->Ln(15);
    
    // Líneas de firma
    $y = $pdf->GetY() + 20;
    
    // Firma funcionario (si es uno solo)
    if (count($funcionariosCometido) == 1) {
        $f = $funcionariosCometido[0];
        $pdf->Line(25, $y, 90, $y);
        $pdf->SetXY(25, $y + 2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(65, 5, $f['nombre'] . ' ' . $f['apellido_paterno'], 0, 0, 'C');
        $pdf->SetXY(25, $y + 7);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(65, 5, 'Funcionario', 0, 0, 'C');
    }
    
    // Firma Secretario Ejecutivo
    $pdf->Line(120, $y, 185, $y);
    $pdf->SetXY(120, $y + 2);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(65, 5, ($cometido['auto_nombre'] ?? '') . ' ' . ($cometido['auto_apellido'] ?? ''), 0, 0, 'C');
    $pdf->SetXY(120, $y + 7);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(65, 5, 'Secretario Ejecutivo', 0, 0, 'C');
}

// Generar nombre del archivo
$nombreArchivo = 'Cometido_' . $cometido['numero_cometido'] . '.pdf';

// Output del PDF
$pdf->Output($nombreArchivo, 'D'); // D = Descargar
