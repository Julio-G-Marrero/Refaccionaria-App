<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
global $wpdb;

$solicitud_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$solicitud_id) {
    echo '<div class="notice notice-error"><p>Solicitud no encontrada.</p></div>';
    return;
}

$solicitud = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}solicitudes_piezas WHERE id = %d", $solicitud_id));
if (!$solicitud) {
    echo '<div class="notice notice-error"><p>Solicitud no encontrada.</p></div>';
    return;
}

$pieza = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}autopartes WHERE id = %d", $solicitud->autoparte_id));
$compatibilidades = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}compatibilidades WHERE autoparte_id = %d", $pieza->id));
$imagenes = maybe_unserialize($solicitud->imagenes);

$imagen_catalogo = "https://www.radec.com.mx/sites/all/files/productos/{$pieza->codigo}.jpg";
?>

<div class="wrap">
    <h2>Revisión de Solicitud</h2>
    <form method="POST" action="<?= admin_url('admin-post.php') ?>">
        <input type="hidden" name="action" value="aprobar_solicitud_pieza">
        <input type="hidden" name="solicitud_id" value="<?= esc_attr($solicitud_id) ?>">

        <table class="widefat fixed">
            <tr><th>Código:</th><td><input type="text" name="codigo" value="<?= esc_attr($pieza->codigo) ?>" readonly></td></tr>
            <tr><th>Descripción:</th><td><textarea name="descripcion" rows="2" cols="60"><?= esc_textarea($pieza->descripcion) ?></textarea></td></tr>
            <tr><th>Sector:</th><td><input type="text" name="sector" value="<?= esc_attr($pieza->sector) ?>"></td></tr>
            <tr><th>Ubicación:</th><td><?= esc_html(get_post_meta($solicitud->ubicacion_id, 'nombre', true)) ?></td></tr>
            <tr><th>Observaciones:</th><td><textarea name="observaciones" rows="3" cols="60" readonly><?= esc_textarea($solicitud->observaciones) ?></textarea></td></tr>
        </table>

        <h3>Comparativa de Imágenes</h3>
        <div style="display:flex;gap:40px;">
            <div>
                <p><strong>Imagen del Catálogo:</strong></p>
                <img src="<?= esc_url($imagen_catalogo) ?>" alt="catalogo" style="max-width:300px;">
            </div>
            <div>
                <p><strong>Imágenes Subidas:</strong></p>
                <?php foreach ($imagenes as $img): ?>
                    <img src="<?= esc_url($img) ?>" style="max-width:300px;margin-bottom:10px;"><br>
                <?php endforeach; ?>
            </div>
        </div>

        <h3>Compatibilidades</h3>
        <ul>
            <?php foreach ($compatibilidades as $c): ?>
                <li><?= esc_html("{$c->marca} {$c->submarca} ({$c->rango})") ?></li>
            <?php endforeach; ?>
        </ul>

        <br>
        <button type="submit" class="button button-primary">Aprobar y Crear Producto</button>
    </form>
</div>
