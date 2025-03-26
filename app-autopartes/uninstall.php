<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tablas = [
    "{$wpdb->prefix}solicitudes_piezas",
    "{$wpdb->prefix}ubicaciones_autopartes",
    "{$wpdb->prefix}compatibilidades",
    "{$wpdb->prefix}autopartes",
    "{$wpdb->prefix}catalogos_refaccionarias"
];

foreach ($tablas as $tabla) {
    $wpdb->query("DROP TABLE IF EXISTS $tabla");
}

// Eliminar metadatos de productos WooCommerce creados por el plugin
$meta_keys = ['_ubicacion_fisica', '_observaciones'];
foreach ($meta_keys as $meta_key) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $meta_key
        )
    );
}

// Eliminar opciones del plugin si existieran (reemplaza por tus opciones si las usas)
// delete_option('catalogo_autopartes_opciones');
