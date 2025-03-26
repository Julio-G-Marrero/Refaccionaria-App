<?php
if (!defined('ABSPATH')) {
    exit; // Evita acceso directo
}

/**
 * Función para cargar la previsualización de 100 productos mediante AJAX
 */
function cargar_preview_catalogo() {
    global $wpdb;
    
    // Verificar si se ha enviado un catálogo ID válido
    if (!isset($_GET['catalogo_id']) || !is_numeric($_GET['catalogo_id'])) {
        wp_die();
    }

    $catalogo_id = intval($_GET['catalogo_id']);

    // Obtener los primeros 100 productos del catálogo
    $productos = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}autopartes WHERE catalogo_id = %d LIMIT 100",
        $catalogo_id
    ));

    if (!empty($productos)) {
        foreach ($productos as $producto) {
            // Generar imagen de vista previa (placeholder si no hay imagen)
            $imagen_url = !empty($producto->imagen_lista) ? esc_url($producto->imagen_lista) : "https://via.placeholder.com/50";

            echo "<tr>
                    <td>" . esc_html($producto->codigo) . "</td>
                    <td>" . esc_html($producto->descripcion) . "</td>
                    <td>" . esc_html($producto->sector) . "</td>
                    <td>" . esc_html($producto->peso) . " " . esc_html($producto->unidad_peso) . "</td>
                    <td>$" . number_format($producto->precio, 2) . "</td>
                    <td><img src='{$imagen_url}' width='50'></td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='6'>No hay productos en este catálogo.</td></tr>";
    }

    wp_die(); // Finaliza la ejecución de AJAX correctamente
}

function catalogo_autopartes_guardar_solicitud() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes.');
    }
    if (
        !isset($_POST['enviar_solicitud_pieza_nonce_field']) ||
        !wp_verify_nonce($_POST['enviar_solicitud_pieza_nonce_field'], 'enviar_solicitud_pieza_nonce')
    ) {
        wp_die('Error de seguridad: nonce no válido.');
    }
    

    global $wpdb;

    $autoparte_id = intval($_POST['autoparte_id']);
    $ubicacion_id = intval($_POST['ubicacion']);
    $observaciones = sanitize_text_field($_POST['observaciones']);
    $usuario_id = get_current_user_id();

    // Guardar imágenes
    $fotos_urls = [];
    if (!empty($_FILES['fotos']['name'][0])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        foreach ($_FILES['fotos']['name'] as $index => $name) {
            $file = [
                'name'     => $_FILES['fotos']['name'][$index],
                'type'     => $_FILES['fotos']['type'][$index],
                'tmp_name' => $_FILES['fotos']['tmp_name'][$index],
                'error'    => $_FILES['fotos']['error'][$index],
                'size'     => $_FILES['fotos']['size'][$index],
            ];
            $upload = wp_handle_upload($file, ['test_form' => false]);
            if (!isset($upload['error'])) {
                $fotos_urls[] = esc_url_raw($upload['url']);
            }
        }
    }

    // Insertar la solicitud en una nueva tabla
    $wpdb->insert("{$wpdb->prefix}solicitudes_piezas", [
        'autoparte_id' => $autoparte_id,
        'ubicacion_id' => $ubicacion_id,
        'observaciones' => $observaciones,
        'imagenes' => maybe_serialize($fotos_urls),
        'usuario_id' => $usuario_id,
        'estado' => 'pendiente',
        'fecha_envio' => current_time('mysql')
    ]);

    // Redirigir con mensaje
    wp_redirect(admin_url('admin.php?page=captura-productos&solicitud=enviada'));
    exit;
}

function aprobar_solicitud_pieza() {
    global $wpdb;
    $id = intval($_POST['solicitud_id']);
    $wpdb->update("{$wpdb->prefix}solicitudes_piezas", ['estado' => 'aprobada'], ['id' => $id]);
    wp_redirect(admin_url('admin.php?page=solicitudes-autopartes'));
    exit;
}
add_action('admin_post_aprobar_solicitud_pieza', 'aprobar_solicitud_pieza');

function rechazar_solicitud_pieza() {
    global $wpdb;
    $id = intval($_POST['solicitud_id']);
    $wpdb->update("{$wpdb->prefix}solicitudes_piezas", ['estado' => 'rechazada'], ['id' => $id]);
    wp_redirect(admin_url('admin.php?page=solicitudes-autopartes'));
    exit;
}
add_action('admin_post_rechazar_solicitud_pieza', 'rechazar_solicitud_pieza');



function catalogo_autopartes_enqueue_scripts() {
    wp_enqueue_script('jquery'); // solo para asegurar
    wp_localize_script('jquery', 'ajaxurl', admin_url('admin-ajax.php'));
}
add_action('admin_enqueue_scripts', 'catalogo_autopartes_enqueue_scripts');


// Registrar la acción AJAX para usuarios autenticados y no autenticados
add_action('wp_ajax_cargar_preview', 'cargar_preview_catalogo');
add_action('wp_ajax_nopriv_cargar_preview', 'cargar_preview_catalogo');

add_action('admin_post_enviar_solicitud_pieza', 'catalogo_autopartes_guardar_solicitud');
add_action('admin_post_nopriv_enviar_solicitud_pieza', 'catalogo_autopartes_guardar_solicitud');
