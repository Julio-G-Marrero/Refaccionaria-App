<?php
if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();

// Tabla de Catálogos de Refaccionarias
$sql_catalogos = "CREATE TABLE {$wpdb->prefix}catalogos_refaccionarias (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

// Tabla de Autopartes sin llaves foráneas ni índices problemáticos
$sql_autopartes = "CREATE TABLE {$wpdb->prefix}autopartes (
    id INT NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    grupo VARCHAR(100) NOT NULL,
    clase VARCHAR(100) NOT NULL,
    sector VARCHAR(100) NOT NULL,
    peso DECIMAL(10,2) NOT NULL,
    unidad_peso VARCHAR(10) NOT NULL,
    volumen DECIMAL(10,2) NOT NULL,
    unidad_volumen VARCHAR(10) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    imagen_lista TEXT NULL,
    imagen_grande TEXT NULL,
    catalogo_id INT NOT NULL,
    PRIMARY KEY (id)
) $charset_collate;";

// Tabla de Compatibilidades sin llaves foráneas ni índices duplicados
$sql_compatibilidades = "CREATE TABLE {$wpdb->prefix}compatibilidades (
    id INT NOT NULL AUTO_INCREMENT,
    autoparte_id INT NOT NULL,
    catalogo_id INT NOT NULL,
    marca VARCHAR(100) NOT NULL,
    submarca VARCHAR(100) NOT NULL,
    rango VARCHAR(50) NOT NULL,
    PRIMARY KEY (id)
) $charset_collate;";

// Tabla de Ubicaciones
$sql_ubicaciones = "CREATE TABLE {$wpdb->prefix}ubicaciones_autopartes (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT NULL,
    codigo_qr VARCHAR(255) DEFAULT NULL,
    imagen_url TEXT DEFAULT NULL,
    PRIMARY KEY (id)
) $charset_collate;";

// Tabla de Solicitudes
$sql_solicitudes = "CREATE TABLE {$wpdb->prefix}solicitudes_piezas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    autoparte_id BIGINT UNSIGNED NOT NULL,
    ubicacion_id BIGINT UNSIGNED NOT NULL,
    estado_pieza VARCHAR(100) DEFAULT NULL,
    observaciones TEXT DEFAULT NULL,
    imagenes LONGTEXT DEFAULT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    estado VARCHAR(50) DEFAULT 'pendiente',
    fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

// Función para crear todas las tablas
function catalogo_autopartes_crear_tablas() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    global $sql_catalogos, $sql_autopartes, $sql_compatibilidades, $sql_ubicaciones, $sql_solicitudes;

    dbDelta($sql_catalogos);
    dbDelta($sql_autopartes);
    dbDelta($sql_compatibilidades);
    dbDelta($sql_ubicaciones);
    dbDelta($sql_solicitudes);
}

// Función para eliminar las tablas cuando se desinstala el plugin
function catalogo_autopartes_eliminar_tablas() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "solicitudes_piezas");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "ubicaciones_autopartes");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "compatibilidades");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "autopartes");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "catalogos_refaccionarias");
}

// Función para buscar coincidencias en los catálogos
function buscar_coincidencias_autoparte($descripcion, $marca, $modelo, $anio) {
    global $wpdb;

    $query = "SELECT a.id, a.codigo, a.descripcion, a.precio, a.imagen_lista, c.nombre as catalogo 
              FROM {$wpdb->prefix}autopartes a
              INNER JOIN {$wpdb->prefix}compatibilidades cmp ON a.id = cmp.autoparte_id
              INNER JOIN {$wpdb->prefix}catalogos_refaccionarias c ON a.catalogo_id = c.id
              WHERE a.descripcion LIKE %s
              AND cmp.marca = %s 
              AND cmp.submarca = %s
              AND cmp.rango LIKE %s";

    $resultados = $wpdb->get_results($wpdb->prepare(
        $query,
        "%" . $descripcion . "%",
        $marca,
        $modelo,
        "%" . $anio . "%"
    ));

    return $resultados;
}
