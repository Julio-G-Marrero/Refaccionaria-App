<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
global $wpdb;

$tabla = $wpdb->prefix . 'ubicaciones_autopartes';

// Procesar nueva ubicación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_ubicacion'])) {
    $nombre = sanitize_text_field($_POST['nombre']);
    $descripcion = sanitize_text_field($_POST['descripcion']);

    $foto_url = null;

    if (!empty($_FILES['foto_ubicacion']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $file = $_FILES['foto_ubicacion'];
        $overrides = ['test_form' => false];

        $uploaded = wp_handle_upload($file, $overrides);

        if (!isset($uploaded['error'])) {
            $foto_url = $uploaded['url'];
        }
    }

    // El QR se generará con el nombre como identificador único
    $codigo_qr = site_url('?qr=ubicacion_' . urlencode($nombre));

    $wpdb->insert($wpdb->prefix . 'ubicaciones_autopartes', [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'codigo_qr' => $codigo_qr,
        'imagen_url' => $foto_url
    ]);

    echo "<div class='updated'><p>Ubicación agregada correctamente.</p></div>";
}


// Procesar eliminación
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $wpdb->delete($tabla, ['id' => intval($_GET['eliminar'])]);
    echo "<div class='updated'><p>Ubicación eliminada correctamente.</p></div>";
}

$ubicaciones = $wpdb->get_results("SELECT * FROM $tabla ORDER BY id DESC");
?>

<div class="wrap">
    <h2>Gestión de Ubicaciones Físicas</h2>

    <h3>Agregar Nueva Ubicación</h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="nueva_ubicacion" value="1">
        <label>Nombre:</label><br>
        <input type="text" name="nombre" required style="width: 300px;"><br><br>

        <label>Descripción (opcional):</label><br>
        <textarea name="descripcion" rows="3" style="width: 300px;"></textarea><br><br>

        <label>Imagen (opcional):</label><br>
        <input type="file" name="foto_ubicacion" accept="image/*" capture="environment">

        <button type="submit" class="button button-primary">Agregar Ubicación</button>
    </form>

    <hr>

    <h3>Ubicaciones Registradas</h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Imagen</th>
                <th>QR</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ubicaciones as $u): ?>
                <tr>
                    <td><?= esc_html($u->id) ?></td>
                    <td><?= esc_html($u->nombre) ?></td>
                    <td><?= esc_html($u->descripcion) ?></td>
                    <td>
                        <?php if (!empty($u->codigo_qr)) : ?>
                            <?php $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($u->codigo_qr); ?>
                            <div>
                                <img src="<?= esc_url($qr_url) ?>" alt="QR" width="60" style="cursor:pointer;" onclick="verQR('<?= esc_url($qr_url) ?>')">
                                <br>
                                <button type="button" class="button" onclick="imprimirQR('<?= esc_url($qr_url) ?>')">Imprimir</button>
                            </div>
                        <?php else: ?>
                            <em>Sin QR</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($u->imagen_url)) : ?>
                            <img src="<?= esc_url($u->imagen_url) ?>" width="80">
                        <?php else: ?>
                            <em>Sin imagen</em>
                        <?php endif; ?>
                    </td>

                    <td>
                        <a href="<?= admin_url('admin.php?page=gestion-ubicaciones&eliminar=' . $u->id) ?>" onclick="return confirm('¿Eliminar esta ubicación?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<!-- SweetAlert + Función para ver QR en grande -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function verQR(qrURL) {
    Swal.fire({
        title: 'Código QR',
        html: `<img src="${qrURL}" alt="QR" style="width:250px;">`,
        showCloseButton: true,
        showConfirmButton: false
    });
}

function imprimirQR(qrURL) {
    const win = window.open('', '_blank');
    win.document.write(`
        <html>
            <head><title>Imprimir QR</title></head>
            <body style="text-align:center;padding:20px;">
                <img src="${qrURL}" style="width:300px;"><br>
                <script>
                    window.onload = () => { window.print(); window.onafterprint = () => window.close(); };
                </script>
            </body>
        </html>
    `);
    win.document.close();
}
</script>
