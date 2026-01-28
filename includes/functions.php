<?php
/**
 * Funciones de ayuda generales
 */

/**
 * Escapar HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redireccionar
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Obtener IP del cliente
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * Formatear RUT chileno
 */
function formatRut($rut) {
    $rut = preg_replace('/[^0-9kK]/', '', $rut);
    if (strlen($rut) < 2) return $rut;
    
    $dv = substr($rut, -1);
    $numero = substr($rut, 0, -1);
    $numero = number_format($numero, 0, '', '.');
    
    return $numero . '-' . $dv;
}

/**
 * Validar RUT chileno
 */
function validarRut($rut) {
    $rut = preg_replace('/[^0-9kK]/', '', $rut);
    if (strlen($rut) < 2) return false;
    
    $dv = strtoupper(substr($rut, -1));
    $numero = substr($rut, 0, -1);
    
    $suma = 0;
    $multiplo = 2;
    
    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += $numero[$i] * $multiplo;
        $multiplo = $multiplo < 7 ? $multiplo + 1 : 2;
    }
    
    $resto = $suma % 11;
    $dvCalculado = 11 - $resto;
    
    if ($dvCalculado == 11) $dvCalculado = '0';
    elseif ($dvCalculado == 10) $dvCalculado = 'K';
    else $dvCalculado = (string)$dvCalculado;
    
    return $dv === $dvCalculado;
}

/**
 * Formatear fecha
 */
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '';
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Formatear fecha y hora
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (!$datetime) return '';
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

/**
 * Formatear moneda
 */
function formatMoney($amount) {
    return '$' . number_format($amount, 0, ',', '.');
}

/**
 * Generar número de cometido
 */
function generarNumeroCometido() {
    $db = Database::getInstance();
    
    $anioActual = date('Y');
    $config = $db->selectOne("SELECT * FROM configuracion WHERE clave = 'correlativo_cometido'");
    $anioConfig = $db->selectOne("SELECT * FROM configuracion WHERE clave = 'anio_correlativo'");
    
    $correlativo = (int)$config['valor'];
    $anioCorrelativo = $anioConfig['valor'];
    
    // Resetear correlativo si cambió el año
    if ($anioCorrelativo != $anioActual) {
        $correlativo = 1;
        $db->update('configuracion', ['valor' => $anioActual], "clave = 'anio_correlativo'");
    }
    
    // Incrementar correlativo
    $db->update('configuracion', ['valor' => $correlativo + 1], "clave = 'correlativo_cometido'");
    
    return sprintf('COM-%s-%04d', $anioActual, $correlativo);
}

/**
 * Obtener estados de documento
 */
function getEstadosDocumento() {
    $db = Database::getInstance();
    return $db->select("SELECT * FROM estados_documento ORDER BY orden");
}

/**
 * Obtener nombre de estado
 */
function getEstadoNombre($estadoId) {
    $db = Database::getInstance();
    $estado = $db->selectOne("SELECT nombre FROM estados_documento WHERE id = :id", ['id' => $estadoId]);
    return $estado ? $estado['nombre'] : '';
}

/**
 * Obtener color de estado
 */
function getEstadoColor($estadoId) {
    $db = Database::getInstance();
    $estado = $db->selectOne("SELECT color FROM estados_documento WHERE id = :id", ['id' => $estadoId]);
    return $estado ? $estado['color'] : '#6c757d';
}

/**
 * Registrar en historial
 */
function registrarHistorial($cometidoId, $accion, $estadoAnterior = null, $estadoNuevo = null, $observaciones = null) {
    $db = Database::getInstance();
    
    $data = [
        'cometido_id' => $cometidoId,
        'usuario_id' => Session::getUserId(),
        'accion' => $accion,
        'estado_anterior_id' => $estadoAnterior,
        'estado_nuevo_id' => $estadoNuevo,
        'observaciones' => $observaciones,
        'ip_address' => getClientIP()
    ];
    
    return $db->insert('historial_cometidos', $data);
}

/**
 * Verificar si es una petición AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Respuesta JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Obtener medio de traslado formateado
 */
function getMedioTrasladoTexto($medio) {
    $medios = [
        'vehiculo_asociacion' => 'Vehículo de la Asociación',
        'vehiculo_particular' => 'Vehículo Particular',
        'transporte_publico' => 'Transporte Público / Bus / Avión'
    ];
    return $medios[$medio] ?? $medio;
}
