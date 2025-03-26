<?php
if (!defined('ABSPATH')) {
    exit; // Evita acceso directo
}

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';

// Procesar eliminación de catálogo
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['eliminar_catalogo'])) {
    global $wpdb;
    $catalogo_id = intval($_POST['catalogo_id']);

    $wpdb->delete($wpdb->prefix . 'compatibilidades', ['catalogo_id' => $catalogo_id]);
    $wpdb->delete($wpdb->prefix . 'autopartes', ['catalogo_id' => $catalogo_id]);
    $wpdb->delete($wpdb->prefix . 'catalogos_refaccionarias', ['id' => $catalogo_id]);

    echo "<script>Swal.fire('Eliminado', 'El cat\u00e1logo ha sido eliminado correctamente.', 'success');</script>";
}

// Procesar importación de catálogo CSV por bloques
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES['catalogo_csv'])) {
    global $wpdb;
    $archivo = $_FILES['catalogo_csv'];

    if ($archivo['type'] !== 'text/csv') {
        echo "<script>Swal.fire('Error', 'Solo se permiten archivos CSV.', 'error');</script>";
    } else {
        echo "<script>Swal.fire({title: 'Procesando', text: 'Importando cat\u00e1logo, por favor espera...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});</script>";

        $nombre_catalogo = sanitize_text_field($_POST['nombre_catalogo']);
        $ruta_temp = $archivo['tmp_name'];
        $csv_data = array_map('str_getcsv', file($ruta_temp));
        $columnas = array_map('trim', array_shift($csv_data));

        $wpdb->insert($wpdb->prefix . 'catalogos_refaccionarias', ['nombre' => $nombre_catalogo]);
        $catalogo_id = $wpdb->insert_id;
        $errores_importacion = [];

        $batch_size = 500;
        $total = count($csv_data);
        $batches = array_chunk($csv_data, $batch_size);

        foreach ($batches as $batch) {
            foreach ($batch as $fila) {
                $datos = array_combine($columnas, $fila);

                $exito = $wpdb->insert($wpdb->prefix . 'autopartes', [
                    'codigo' => sanitize_text_field($datos['CODIGO']),
                    'descripcion' => sanitize_text_field($datos['DESCRIPCION']),
                    'sector' => sanitize_text_field($datos['FAMILIA'] ?? ''),
                    'catalogo_id' => $catalogo_id
                ]);
                
                if ($exito === false) {
                    $errores_importacion[] = "Error al insertar pieza con código: " . $datos['CODIGO'];
                }
                $autoparte_id = $wpdb->insert_id;
                for ($i = 1; $i <= 9; $i++) {
                    if (!empty($datos["MARCA$i"]) && !empty($datos["SUBMARCA$i"]) && !empty($datos["RANGOS$i"])) {
                        $wpdb->insert($wpdb->prefix . 'compatibilidades', [
                            'autoparte_id' => $autoparte_id,
                            'catalogo_id' => $catalogo_id,
                            'marca' => sanitize_text_field($datos["MARCA$i"]),
                            'submarca' => sanitize_text_field($datos["SUBMARCA$i"]),
                            'rango' => sanitize_text_field($datos["RANGOS$i"])
                        ]);
                    }
                }
            }
            // Para evitar saturación del servidor
            sleep(1);
        }

        if (empty($errores_importacion)) {
            echo "<script>Swal.fire('Importado', 'Catálogo importado correctamente.', 'success');</script>";
        } else {
            $errores = implode('<br>', $errores_importacion);
            echo "<script>Swal.fire({title: 'Errores en la importación', html: \"$errores\", icon: 'error', width: 600});</script>";
        }
    }
}
global $wpdb;

// Obtener todos los catálogos
$catalogos = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}catalogos_refaccionarias");
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="main-content">
    <h2>Administrar Catálogos de Refaccionarias</h2>

    <h3>Subir Nuevo Catálogo</h3>
    <form method="POST" enctype="multipart/form-data">
        <label>Nombre del Catálogo:</label>
        <input type="text" name="nombre_catalogo" required>
        <label>Archivo CSV:</label>
        <input type="file" name="catalogo_csv" accept=".csv" required>
        <button type="submit">Subir Catálogo</button>
    </form>

    <h3>Catálogos Registrados</h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Fecha de Subida</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($catalogos as $catalogo): ?>
                <tr>
                    <td><?= esc_html($catalogo->id); ?></td>
                    <td><?= esc_html($catalogo->nombre); ?></td>
                    <td><?= esc_html($catalogo->fecha_subida); ?></td>
                    <td>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="catalogo_id" value="<?= esc_attr($catalogo->id); ?>">
                            <input type="hidden" name="eliminar_catalogo" value="1">
                            <button type="submit" onclick="return confirm('¿Eliminar este cat\u00e1logo?')">Eliminar</button>
                            <a href="<?php echo admin_url('admin.php?action=exportar_catalogo&catalogo_id=' . $catalogo->id); ?>" class="button">Exportar CSV</a>
                        </form>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <strong>Productos del Catálogo:</strong>
                        <table class="wp-list-table widefat fixed striped" style="max-height:300px; overflow-y:auto; display:block; width:100%;">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Sector</th>
                                    <th>Peso</th>
                                    <th>Precio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $productos = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}autopartes WHERE catalogo_id = %d LIMIT 100", $catalogo->id));
                                foreach ($productos as $producto): ?>
                                    <tr>
                                        <td><?= esc_html($producto->codigo); ?></td>
                                        <td><?= esc_html($producto->descripcion); ?></td>
                                        <td><?= esc_html($producto->sector); ?></td>
                                        <td><?= esc_html($producto->peso); ?> <?= esc_html($producto->unidad_peso); ?></td>
                                        <td>$<?= number_format($producto->precio, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>