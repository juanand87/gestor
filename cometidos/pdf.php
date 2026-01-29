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
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 8, 'ASOCIACIÓN DE MUNICIPIOS - COMETIDO FUNCIONARIO', 0, 1, 'C');
        $this->Line(15, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 7);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 4, 'Documento generado el ' . date('d/m/Y H:i') . ' - Sistema de Gestión Documental', 0, 0, 'C');
    }
}

$pdf = new CometidoPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);

// Configuración del documento
$pdf->SetCreator('Gestor Documental');
$pdf->SetAuthor('Asociación de Municipios');
$pdf->SetTitle('Cometido - ' . $cometido['numero_cometido']);
$pdf->SetSubject('Cometido Funcionario');

// Márgenes más compactos
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Configuración de página
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Agregar página
$pdf->AddPage();

// Número y Estado en una línea
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(90, 7, 'Nº ' . $cometido['numero_cometido'], 0, 0, 'L');

$estadoColor = [200, 200, 200];
if ($cometido['estado_id'] == 3) $estadoColor = [212, 237, 218];
elseif ($cometido['estado_id'] == 4) $estadoColor = [248, 215, 218];
elseif ($cometido['estado_id'] == 2) $estadoColor = [255, 243, 205];

$pdf->SetFillColor($estadoColor[0], $estadoColor[1], $estadoColor[2]);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 7, strtoupper($cometido['estado_nombre']), 1, 1, 'C', true);
$pdf->Ln(2);

// Función para crear secciones compactas
function crearSeccion($pdf, $titulo) {
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 5, $titulo, 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
}

function crearFila($pdf, $label, $value, $labelWidth = 45) {
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(250, 250, 250);
    $pdf->Cell($labelWidth, 5, $label, 'LTB', 0, 'L', true);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 5, $value, 'RTB', 1, 'L');
}

// FUNCIONARIO(S)
crearSeccion($pdf, 'FUNCIONARIO(S)');
if (count($funcionariosCometido) == 1) {
    $f = $funcionariosCometido[0];
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 5, $f['nombre'] . ' ' . $f['apellido_paterno'] . ' ' . $f['apellido_materno'] . ' | RUT: ' . $f['rut'] . ' | ' . $f['cargo'], 1, 1, 'L');
} else {
    $pdf->SetFont('helvetica', '', 7);
    foreach ($funcionariosCometido as $f) {
        $pdf->Cell(0, 4, $f['nombre'] . ' ' . $f['apellido_paterno'] . ' ' . $f['apellido_materno'] . ' - ' . $f['rut'] . ' - ' . $f['cargo'], 1, 1, 'L');
    }
}
$pdf->Ln(1);

// OBJETIVO
crearSeccion($pdf, 'OBJETIVO');
$pdf->SetFont('helvetica', '', 8);
$pdf->MultiCell(0, 4, $cometido['objetivo'], 1, 'L');
$pdf->Ln(1);

// LUGAR Y FECHAS en columnas
crearSeccion($pdf, 'LUGAR Y PERÍODO');
$pdf->SetFont('helvetica', '', 8);
$lugarTexto = $cometido['ciudad'] . ', ' . $cometido['comuna'];
if ($cometido['lugar_descripcion']) $lugarTexto .= ' (' . $cometido['lugar_descripcion'] . ')';
$pdf->Cell(90, 5, 'Lugar: ' . $lugarTexto, 1, 0, 'L');
$fechaTexto = date('d/m/Y', strtotime($cometido['fecha_inicio']));
if ($cometido['fecha_termino'] != $cometido['fecha_inicio']) {
    $fechaTexto .= ' al ' . date('d/m/Y', strtotime($cometido['fecha_termino']));
}
$pdf->Cell(0, 5, 'Fecha: ' . $fechaTexto, 1, 1, 'L');

if ($cometido['horario_inicio'] || $cometido['horario_termino']) {
    $horario = '';
    if ($cometido['horario_inicio']) $horario .= 'Desde: ' . $cometido['horario_inicio'];
    if ($cometido['horario_termino']) $horario .= ' Hasta: ' . $cometido['horario_termino'];
    $pdf->Cell(0, 5, $horario, 1, 1, 'L');
}
$pdf->Ln(1);

// TRASLADO Y FINANCIAMIENTO
crearSeccion($pdf, 'TRASLADO Y FINANCIAMIENTO');
$medioTexto = getMedioTrasladoTextoPDF($cometido['medio_traslado']);
if ($cometido['patente_vehiculo']) $medioTexto .= ' (Patente: ' . $cometido['patente_vehiculo'] . ')';
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(90, 5, 'Medio: ' . $medioTexto, 1, 0, 'L');
$pdf->Cell(0, 5, 'Viático: $' . number_format($cometido['viatico'], 0, ',', '.'), 1, 1, 'L');
$pdf->Ln(1);

// CARÁCTER - en una sola línea
crearSeccion($pdf, 'CARÁCTER DEL COMETIDO');
$pdf->SetFont('helvetica', '', 8);
$caracter = [];
$caracter[] = $cometido['dentro_comuna'] ? 'Dentro comuna' : 'Fuera comuna';
$caracter[] = $cometido['dentro_jornada'] ? 'Dentro jornada' : 'Fuera jornada';
$caracter[] = $cometido['con_costo'] ? 'Con costo' : 'Sin costo';
$pdf->Cell(0, 5, implode(' | ', $caracter), 1, 1, 'L');
$pdf->Ln(1);

// MOTIVO DE RECHAZO (si aplica)
if ($cometido['estado_id'] == 4 && $cometido['observaciones_rechazo']) {
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(248, 215, 218);
    $pdf->SetTextColor(114, 28, 36);
    $pdf->Cell(0, 5, 'MOTIVO DEL RECHAZO', 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->MultiCell(0, 4, $cometido['observaciones_rechazo'], 1, 'L');
    $pdf->Ln(1);
}

// FIRMAS (solo si está autorizado)
if ($cometido['estado_id'] == 3) {
    $pdf->Ln(10);
    $y = $pdf->GetY() + 12;
    
    // Firma funcionario (si es uno solo)
    if (count($funcionariosCometido) == 1) {
        $f = $funcionariosCometido[0];
        $pdf->Line(25, $y, 85, $y);
        $pdf->SetXY(25, $y + 1);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(60, 4, $f['nombre'] . ' ' . $f['apellido_paterno'], 0, 0, 'C');
        $pdf->SetXY(25, $y + 5);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(60, 4, 'Funcionario', 0, 0, 'C');
    }
    
    // Firma Secretario Ejecutivo
    $pdf->Line(125, $y, 185, $y);
    $pdf->SetXY(125, $y + 1);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(60, 4, ($cometido['auto_nombre'] ?? '') . ' ' . ($cometido['auto_apellido'] ?? ''), 0, 0, 'C');
    $pdf->SetXY(125, $y + 5);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(60, 4, 'Secretario Ejecutivo', 0, 0, 'C');
}

// Generar nombre del archivo
$nombreArchivo = 'Cometido_' . $cometido['numero_cometido'] . '.pdf';

// Output del PDF
$pdf->Output($nombreArchivo, 'D'); // D = Descargar
