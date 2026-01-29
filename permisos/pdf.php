<?php
/**
 * Generar PDF de solicitud de permiso usando TCPDF
 */
require_once '../includes/init.php';
require_once '../libs/tcpdf/tcpdf.php';

Auth::requireLogin();
Auth::requirePermission('permisos_ver');

$db = Database::getInstance();
$user = Session::getUser();

// Verificar ID
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('No se especificó la solicitud.');
}

// Obtener solicitud
$solicitud = $db->selectOne(
    "SELECT sp.*, 
            tp.nombre as tipo_nombre, tp.codigo as tipo_codigo,
            ep.nombre as estado_nombre, ep.color as estado_color,
            f.nombre as func_nombre, f.apellido_paterno as func_apellido, 
            f.apellido_materno as func_apellido_m, f.rut as func_rut,
            f.cargo as func_cargo,
            u.username as solicitado_por_username,
            uf.nombre as solicitante_nombre, uf.apellido_paterno as solicitante_apellido,
            ua.username as autorizado_por_username,
            uaf.nombre as autorizador_nombre, uaf.apellido_paterno as autorizador_apellido
     FROM solicitudes_permiso sp
     INNER JOIN tipos_permiso tp ON sp.tipo_permiso_id = tp.id
     INNER JOIN estados_permiso ep ON sp.estado_id = ep.id
     INNER JOIN funcionarios f ON sp.funcionario_id = f.id
     INNER JOIN usuarios u ON sp.solicitado_por = u.id
     LEFT JOIN funcionarios uf ON u.funcionario_id = uf.id
     LEFT JOIN usuarios ua ON sp.autorizado_por = ua.id
     LEFT JOIN funcionarios uaf ON ua.funcionario_id = uaf.id
     WHERE sp.id = ?",
    [$id]
);

if (!$solicitud) {
    die('Solicitud no encontrada.');
}

// Verificar permisos de acceso
if ($user['rol_id'] != ROL_ADMIN && $solicitud['solicitado_por'] != $user['id']) {
    if (!($user['funcionario_id'] && $solicitud['funcionario_id'] == $user['funcionario_id'])) {
        die('No tiene permiso para ver esta solicitud.');
    }
}

// Obtener historial
$historial = $db->select(
    "SELECT h.*, h.created_at as fecha, en.nombre as estado_nombre,
            u.username, f.nombre as usuario_nombre, f.apellido_paterno as usuario_apellido
     FROM historial_permisos h
     INNER JOIN usuarios u ON h.usuario_id = u.id
     LEFT JOIN funcionarios f ON u.funcionario_id = f.id
     LEFT JOIN estados_permiso en ON h.estado_nuevo_id = en.id
     WHERE h.solicitud_id = ?
     ORDER BY h.created_at ASC",
    [$id]
);

// Función para fecha en español
function fechaEspanol($fecha) {
    if (!$fecha) return '-';
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 
              'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $d = new DateTime($fecha);
    return $d->format('d') . ' de ' . $meses[(int)$d->format('n')] . ' de ' . $d->format('Y');
}

// Crear PDF
class PermisoPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 10, 'ASOCIACIÓN DE MUNICIPIOS', 0, 1, 'C');
        $this->SetFont('helvetica', '', 11);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'Solicitud de Permiso', 0, 1, 'C');
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

$pdf = new PermisoPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);

// Configuración del documento
$pdf->SetCreator('Gestor Documental');
$pdf->SetAuthor('Asociación de Municipios');
$pdf->SetTitle('Solicitud de Permiso - ' . $solicitud['numero_solicitud']);
$pdf->SetSubject('Solicitud de Permiso');

// Márgenes
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);

// Configuración de página
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Agregar página
$pdf->AddPage();

// Tipo de permiso
$tipoTexto = ($solicitud['tipo_codigo'] == 'feriado_legal') ? 'FERIADO LEGAL (VACACIONES)' : 'PERMISO ADMINISTRATIVO';
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 10, $tipoTexto, 1, 1, 'C', true);

// Número de solicitud
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 8, 'Nº ' . $solicitud['numero_solicitud'], 0, 1, 'C');
$pdf->Ln(3);

// Estado
$estadoColor = [200, 200, 200]; // Gris por defecto
if ($solicitud['estado_id'] == 3) $estadoColor = [212, 237, 218]; // Verde
elseif ($solicitud['estado_id'] == 4) $estadoColor = [248, 215, 218]; // Rojo
elseif ($solicitud['estado_id'] == 2) $estadoColor = [255, 243, 205]; // Amarillo

$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor($estadoColor[0], $estadoColor[1], $estadoColor[2]);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'ESTADO: ' . strtoupper($solicitud['estado_nombre']), 1, 1, 'C', true);

if ($solicitud['fecha_autorizacion'] && $solicitud['autorizador_nombre']) {
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, 'Procesado el ' . fechaEspanol($solicitud['fecha_autorizacion']) . 
               ' por ' . $solicitud['autorizador_nombre'] . ' ' . $solicitud['autorizador_apellido'], 0, 1, 'C');
}
$pdf->Ln(5);

// Función para crear secciones
function crearSeccion($pdf, $titulo) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, $titulo, 1, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);
}

function crearFila($pdf, $label, $value) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(60, 7, $label, 1, 0, 'L', true);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 7, $value, 1, 1, 'L');
}

// DATOS DEL FUNCIONARIO
crearSeccion($pdf, 'DATOS DEL FUNCIONARIO');
crearFila($pdf, 'Nombre Completo:', $solicitud['func_nombre'] . ' ' . $solicitud['func_apellido'] . ' ' . $solicitud['func_apellido_m']);
crearFila($pdf, 'RUT:', $solicitud['func_rut']);
crearFila($pdf, 'Cargo:', $solicitud['func_cargo']);
$pdf->Ln(3);

// PERÍODO SOLICITADO
crearSeccion($pdf, 'PERÍODO SOLICITADO');
crearFila($pdf, 'Fecha de Inicio:', fechaEspanol($solicitud['fecha_inicio']));
crearFila($pdf, 'Fecha de Término:', fechaEspanol($solicitud['fecha_termino']));
$diasTexto = number_format($solicitud['dias_solicitados'], 1) . ' día(s) hábiles';
if ($solicitud['es_medio_dia']) {
    $diasTexto .= ' (Medio día)';
}
crearFila($pdf, 'Días Solicitados:', $diasTexto);
if ($solicitud['tipo_codigo'] == 'feriado_legal') {
    crearFila($pdf, 'Año de Descuento:', $solicitud['anio_descuento'] ?? date('Y'));
}
$pdf->Ln(3);

// MOTIVO (si existe)
if (!empty($solicitud['motivo'])) {
    crearSeccion($pdf, 'MOTIVO / OBSERVACIONES');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 6, $solicitud['motivo'], 1, 'L');
    $pdf->Ln(3);
}

// INFORMACIÓN DE LA SOLICITUD
crearSeccion($pdf, 'INFORMACIÓN DE LA SOLICITUD');
crearFila($pdf, 'Solicitado por:', $solicitud['solicitante_nombre'] . ' ' . $solicitud['solicitante_apellido']);
crearFila($pdf, 'Fecha de Creación:', fechaEspanol($solicitud['created_at']));
if ($solicitud['fecha_autorizacion']) {
    crearFila($pdf, 'Fecha de Resolución:', fechaEspanol($solicitud['fecha_autorizacion']));
}
$pdf->Ln(3);

// HISTORIAL
if (!empty($historial)) {
    crearSeccion($pdf, 'HISTORIAL DE LA SOLICITUD');
    
    // Encabezados de tabla
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(35, 6, 'Fecha', 1, 0, 'C', true);
    $pdf->Cell(50, 6, 'Acción', 1, 0, 'C', true);
    $pdf->Cell(45, 6, 'Usuario', 1, 0, 'C', true);
    $pdf->Cell(50, 6, 'Observaciones', 1, 1, 'C', true);
    
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    
    foreach ($historial as $h) {
        $pdf->SetFillColor($fill ? 249 : 255, $fill ? 249 : 255, $fill ? 249 : 255);
        $pdf->Cell(35, 6, date('d/m/Y H:i', strtotime($h['fecha'])), 1, 0, 'L', true);
        $pdf->Cell(50, 6, $h['accion'], 1, 0, 'L', true);
        $pdf->Cell(45, 6, ($h['usuario_nombre'] ?? '') . ' ' . ($h['usuario_apellido'] ?? ''), 1, 0, 'L', true);
        $pdf->Cell(50, 6, $h['observaciones'] ?? '-', 1, 1, 'L', true);
        $fill = !$fill;
    }
    $pdf->Ln(5);
}

// FIRMAS (solo si está autorizado)
if ($solicitud['estado_id'] == 3) {
    $pdf->Ln(15);
    
    // Líneas de firma
    $y = $pdf->GetY() + 20;
    
    // Firma funcionario
    $pdf->Line(25, $y, 90, $y);
    $pdf->SetXY(25, $y + 2);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(65, 5, $solicitud['func_nombre'] . ' ' . $solicitud['func_apellido'], 0, 0, 'C');
    $pdf->SetXY(25, $y + 7);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(65, 5, 'Funcionario', 0, 0, 'C');
    
    // Firma Secretario Ejecutivo
    $pdf->Line(120, $y, 185, $y);
    $pdf->SetXY(120, $y + 2);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(65, 5, ($solicitud['autorizador_nombre'] ?? '') . ' ' . ($solicitud['autorizador_apellido'] ?? ''), 0, 0, 'C');
    $pdf->SetXY(120, $y + 7);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(65, 5, 'Secretario Ejecutivo', 0, 0, 'C');
}

// Generar nombre del archivo
$nombreArchivo = 'Permiso_' . $solicitud['numero_solicitud'] . '.pdf';

// Output del PDF
$pdf->Output($nombreArchivo, 'D'); // D = Descargar
