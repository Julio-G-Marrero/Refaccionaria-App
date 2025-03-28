<?php
/**
 * Plugin Name: Catálogo de Autopartes
 * Plugin URI: https://tudominio.com
 * Description: Plugin para la gestión de un catálogo de autopartes con integración en WooCommerce.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://tudominio.com
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit; // Evita acceso directo al archivo
}

// Definir constantes del plugin
define('CATALOGO_AUTOPARTES_DIR', plugin_dir_path(__FILE__));
define('CATALOGO_AUTOPARTES_URL', plugin_dir_url(__FILE__));
define('CATALOGO_AUTOPARTES_VERSION', '1.0.0');

// Incluir archivos esenciales
require_once CATALOGO_AUTOPARTES_DIR . '/includes/database.php';    // Gestión de la base de datos
require_once CATALOGO_AUTOPARTES_DIR . '/includes/roles.php';       // Gestión de roles y permisos
require_once CATALOGO_AUTOPARTES_DIR . '/includes/menu.php';        // Menú y páginas de administración
require_once CATALOGO_AUTOPARTES_DIR . '/includes/api.php';         // API interna para AJAX
$product_sync_path = CATALOGO_AUTOPARTES_DIR . 'includes/product-sync.php';
if (file_exists($product_sync_path)) {
    require_once $product_sync_path;
}
// Función que se ejecuta al activar el plugin (crea las tablas necesarias)
register_activation_hook(__FILE__, 'catalogo_autopartes_activar');

function catalogo_autopartes_activar() {
    require_once plugin_dir_path(__FILE__) . 'includes/database.php';
    catalogo_autopartes_crear_tablas();
}

// Función que se ejecuta al desactivar el plugin (sin eliminar datos)
function catalogo_autopartes_desactivar() {
    // Aquí podríamos limpiar cachés o realizar alguna acción antes de desactivar
}
register_deactivation_hook(__FILE__, 'catalogo_autopartes_desactivar');

// Función para eliminar completamente el plugin (elimina tablas y datos)
function catalogo_autopartes_desinstalar() {
    require_once CATALOGO_AUTOPARTES_DIR . 'uninstall.php';
}
register_uninstall_hook(__FILE__, 'catalogo_autopartes_desinstalar');

// Cargar los archivos de scripts y estilos del plugin
function catalogo_autopartes_cargar_recursos($hook) {
    if (strpos($hook, 'catalogo-autopartes') === false) {
        return;
    }
    
    wp_enqueue_style('catalogo-autopartes-css', CATALOGO_AUTOPARTES_URL . 'assets/style.css', array(), CATALOGO_AUTOPARTES_VERSION);
    wp_enqueue_script('catalogo-autopartes-js', CATALOGO_AUTOPARTES_URL . 'assets/script.js', array('jquery'), CATALOGO_AUTOPARTES_VERSION, true);
}
add_action('admin_enqueue_scripts', 'catalogo_autopartes_cargar_recursos');

add_action('init', 'registrar_taxonomia_compatibilidades');
function registrar_taxonomia_compatibilidades() {
    $taxonomy = 'pa_compatibilidades';
    if (!taxonomy_exists($taxonomy)) {
        register_taxonomy($taxonomy, 'product', [
            'hierarchical' => false,
            'label' => 'Compatibilidades',
            'query_var' => true,
            'rewrite' => ['slug' => 'compatibilidades'],
            'show_admin_column' => true,
            'show_in_rest' => true,
        ]);
    }
}

add_action('admin_init', 'catalogo_autopartes_exportar_csv');
function catalogo_autopartes_exportar_csv() {
    if (!isset($_GET['catalogo_id']) || $_GET['action'] !== 'exportar_catalogo') return;

    if (!current_user_can('manage_options')) return; // Seguridad

    global $wpdb;

    $catalogo_id = intval($_GET['catalogo_id']);
    $autopartes = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}autopartes WHERE catalogo_id = %d", $catalogo_id),
        ARRAY_A
    );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="catalogo_' . $catalogo_id . '.csv"');

    $output = fopen('php://output', 'w');
    if (!empty($autopartes)) {
        fputcsv($output, array_keys($autopartes[0])); // encabezados
        foreach ($autopartes as $fila) {
            fputcsv($output, $fila);
        }
    }
    fclose($output);
    exit;
}
function catalogo_autopartes_enqueue_scripts() {
    wp_enqueue_script('jquery'); // necesario para WordPress AJAX
    wp_localize_script('jquery', 'ajaxurl', admin_url('admin-ajax.php'));
}
function ajax_guardar_solicitud_pieza() {
    check_ajax_referer('enviar_solicitud_pieza', 'security');

    global $wpdb;

    $autoparte_id   = intval($_POST['autoparte_id']);
    $ubicacion_id   = intval($_POST['ubicacion']);
    $estado_pieza   = sanitize_text_field($_POST['estado_pieza']);
    $observaciones  = sanitize_text_field($_POST['observaciones']);
    $usuario_id     = get_current_user_id();

    $fotos_urls = [];

    if (!empty($_FILES['fotos']['name'][0])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        foreach ($_FILES['fotos']['name'] as $i => $name) {
            $file = [
                'name'     => $_FILES['fotos']['name'][$i],
                'type'     => $_FILES['fotos']['type'][$i],
                'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                'error'    => $_FILES['fotos']['error'][$i],
                'size'     => $_FILES['fotos']['size'][$i],
            ];

            $upload = wp_handle_upload($file, ['test_form' => false]);

            if (!isset($upload['error'])) {
                $fotos_urls[] = esc_url_raw($upload['url']);
            }
        }
    }

    $insertado = $wpdb->insert("{$wpdb->prefix}solicitudes_piezas", [
        'autoparte_id'   => $autoparte_id,
        'ubicacion_id'   => $ubicacion_id,
        'estado_pieza'   => $estado_pieza,
        'observaciones'  => $observaciones,
        'imagenes'       => maybe_serialize($fotos_urls),
        'usuario_id'     => $usuario_id,
        'estado'         => 'pendiente',
        'fecha_envio'    => current_time('mysql')
    ]);

    if ($insertado) {
        $solicitud_id = $wpdb->insert_id;

        wp_send_json_success([
            'message' => 'Solicitud enviada',
            'id'      => $solicitud_id
        ]);
    } else {
        wp_send_json_error(['message' => 'No se pudo guardar la solicitud']);
    }
}

function mi_plugin_cargar_tailwind_cdn() {
    wp_enqueue_style('tailwind-cdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@3.4.1/dist/tailwind.min.css');
}
add_action('admin_enqueue_scripts', 'mi_plugin_cargar_tailwind_cdn');

add_action('wp_ajax_crear_producto_autoparte', 'crear_producto_autoparte');

function crear_producto_autoparte() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }

    // Sanitizar datos recibidos
    $sku = sanitize_text_field($_POST['sku'] ?? '');
    $nombre = sanitize_text_field($_POST['nombre'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $categoria_id = intval($_POST['categoria'] ?? 0);
    $ubicacion = sanitize_text_field($_POST['ubicacion'] ?? '');
    $observaciones = sanitize_textarea_field($_POST['observaciones'] ?? '');
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $imagenes = json_decode(stripslashes($_POST['imagenes'] ?? '[]'), true);
    $compatibilidades = json_decode(stripslashes($_POST['compatibilidades'] ?? '[]'), true);

    if (!is_array($imagenes)) $imagenes = [];
    if (!is_array($compatibilidades)) $compatibilidades = [];

    // Crear producto en WooCommerce
    $post_id = wp_insert_post([
        'post_title'   => $nombre,
        'post_content' => 'Producto creado desde solicitud de autoparte.',
        'post_status'  => 'publish',
        'post_type'    => 'product',
    ]);

    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => 'No se pudo crear el producto.']);
    }

    // Precio y SKU
    update_post_meta($post_id, '_sku', $sku);
    update_post_meta($post_id, '_regular_price', $precio);
    update_post_meta($post_id, '_price', $precio);

    // Inventario
    update_post_meta($post_id, '_manage_stock', 'yes');
    update_post_meta($post_id, '_stock', 1);
    update_post_meta($post_id, '_stock_status', 'instock');

    // Categoría
    if ($categoria_id) {
        wp_set_object_terms($post_id, [$categoria_id], 'product_cat');
    }

    // Ubicación y observaciones
    update_post_meta($post_id, '_ubicacion_fisica', $ubicacion);
    update_post_meta($post_id, '_observaciones', $observaciones);

    // Imágenes
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $galeria_ids = [];
    foreach ($imagenes as $index => $img_url) {
        $tmp = download_url($img_url);
        if (is_wp_error($tmp)) continue;

        $file_array = [
            'name'     => basename($img_url),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($attachment_id)) continue;

        if ($index === 0) {
            set_post_thumbnail($post_id, $attachment_id);
        } else {
            $galeria_ids[] = $attachment_id;
        }
    }

    if (!empty($galeria_ids)) {
        update_post_meta($post_id, '_product_image_gallery', implode(',', $galeria_ids));
    }

    // Compatibilidades
    if (!empty($compatibilidades)) {
        $attribute_slug = 'compat_autopartes'; // NUEVO SLUG
		$taxonomy = 'pa_' . $attribute_slug;

        // Registrar la taxonomía si no existe
        if (!taxonomy_exists($taxonomy)) {
		    register_taxonomy($taxonomy, 'product', [
		        'hierarchical' => false,
		        'label' => 'Compatibilidades',
		        'query_var' => true,
		        'rewrite' => ['slug' => sanitize_title($attribute_slug)],
		    ]);
		}
        $terminos = [];

        foreach ($compatibilidades as $c) {
            $marca = sanitize_text_field($c->marca ?? '');
            $modelo = sanitize_text_field($c->submarca ?? '');
            $rango = explode('-', $c->rango ?? '');
            $anio_inicio = intval(trim($rango[0] ?? 0));
            $anio_fin = intval(trim($rango[1] ?? 0));

            if (!$marca || !$modelo || !$anio_inicio || !$anio_fin) continue;

            // 1. Término visual del rango
            $rango_legible = "$marca $modelo ($anio_inicio–$anio_fin)";
            $terminos[] = $rango_legible;

            // 2. Términos por año para búsqueda precisa
            for ($anio = $anio_inicio; $anio <= $anio_fin; $anio++) {
                $terminos[] = "$marca $modelo $anio";
            }
        }

        // Quitar duplicados
        $terminos = array_unique($terminos);

        // Crear términos si no existen
        foreach ($terminos as $term) {
            $term = sanitize_text_field(trim($term));
            if (!term_exists($term, $taxonomy)) {
                wp_insert_term($term, $taxonomy);
            }
        }
		
		// Asignar términos al producto (taxonomía)
		wp_set_object_terms($post_id, $terminos, $taxonomy, false);

        // Declarar el atributo como visible
		$product_attributes['pa_compat_autopartes'] = [
		    'name' => 'pa_compat_autopartes',
		    'value' => '',
		    'position' => 0,
		    'is_visible' => 1,
		    'is_variation' => 0,
		    'is_taxonomy' => 1,
		];
		update_post_meta($post_id, '_product_attributes', $product_attributes);
		// Refrescar producto como objeto WC_Product y guardar cambios
		$product = wc_get_product($post_id);
		$product->save(); // Asegura que todos los cambios se guarden en Woo
    }

    // Marcar solicitud como aprobada
    global $wpdb;
    $wpdb->update("{$wpdb->prefix}solicitudes_piezas", [
        'estado' => 'aprobada'
    ], ['id' => $solicitud_id]);
	
	$terminos_asignados = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'names']);

	wp_send_json_success([
    	'message' => 'Producto creado correctamente.',
    	'sku' => $sku,
    	'compatibilidades' => $terminos_asignados
	]);

}

add_action('admin_enqueue_scripts', 'catalogo_autopartes_enqueue_scripts');
add_action('wp_ajax_ajax_enviar_solicitud_pieza', 'ajax_guardar_solicitud_pieza');
catalogo_autopartes_crear_tablas();
