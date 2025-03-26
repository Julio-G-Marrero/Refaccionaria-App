<?php
if (!defined('ABSPATH') || !isset($_GET['catalogo_id'])) {
    exit;
}

global $wpdb;

$catalogo_id = intval($_GET['catalogo_id']);

// Obtener solo las columnas necesarias
$autopartes = $wpdb->get_results(
    $wpdb->prepare("
        SELECT id, codigo, descripcion, sector, catalogo_id
        FROM {$wpdb->prefix}autopartes
        WHERE catalogo_id = %d
    ", $catalogo_id),
    ARRAY_A
);

// Generar archivo CSV
header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename=\"catalogo_{$catalogo_id}.csv\"");

$output = fopen('php://output', 'w');
if (!empty($autopartes)) {
    fputcsv($output, array_keys($autopartes[0])); // Encabezados
    foreach ($autopartes as $fila) {
        fputcsv($output, $fila);
    }
}
fclose($output);
exit;
