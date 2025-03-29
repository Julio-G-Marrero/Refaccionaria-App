<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
global $wpdb;

$solicitudes_aprobadas = $wpdb->get_results("
    SELECT s.id AS solicitud_id, s.autoparte_id, a.codigo, a.descripcion, p.ID AS producto_id
    FROM {$wpdb->prefix}solicitudes_piezas s
    INNER JOIN {$wpdb->prefix}autopartes a ON s.autoparte_id = a.id
    LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_key = 'solicitud_id' AND pm.meta_value = s.id
    LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'product'
    WHERE s.estado = 'aprobada'
    ORDER BY s.fecha_envio DESC
");
?>

<div class="wrap">
    <h2>Impresión de Códigos QR de Autopartes Aprobadas</h2>

    <input type="text" id="filtroBusqueda" placeholder="Buscar por ID o descripción..." class="w-full p-2 border border-gray-300 rounded-md mb-4" />

    <div class="overflow-auto">
        <table class="wp-list-table widefat fixed striped" id="tablaSolicitudesQR">
            <thead>
                <tr>
                    <th>ID Solicitud</th>
                    <th>Descripción</th>
                    <th>SKU</th>
                    <th>Producto</th>
                    <th>Código QR</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes_aprobadas as $s): 
                    $sku = $s->producto_id ? get_post_meta($s->producto_id, '_sku', true) : '';
                    $urlProducto = $sku ? home_url('/?sku=' . $sku) : '';
                    $qr_url = $urlProducto ? 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($urlProducto) : '';
                    ?>
                    <tr>
                        <td><?= esc_html($s->solicitud_id) ?></td>
                        <td><?= esc_html($s->descripcion) ?></td>
                        <td><?= esc_html($sku) ?></td>
                        <td>
                            <?php if ($s->producto_id): ?>
                                <a href="<?= esc_url(get_permalink($s->producto_id)) ?>" target="_blank" class="text-blue-600 underline">Ver producto</a>
                            <?php else: ?>
                                <span class="text-gray-500 italic">No creado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($qr_url): ?>
                                <img src="<?= esc_url($qr_url) ?>" alt="QR" width="100" />
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($qr_url): ?>
                                <button onclick="imprimirQR('<?= esc_js($qr_url) ?>', '<?= esc_js($s->descripcion) ?>')" class="button button-primary">Imprimir</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function imprimirQR(qrUrl, descripcion) {
    const sku = qrUrl.split('=')[1] || '';
    const ventana = window.open('', '_blank');
    ventana.document.write(`
        <html>
        <head><title>Imprimir QR</title></head>
        <body style="text-align: center; font-family: sans-serif;">
            <h3 style="margin-bottom: 10px;">${descripcion}</h3>
            <p style="margin-bottom: 5px; font-size: 14px;"><strong>SKU:</strong> ${sku}</p>
            <img src="${qrUrl}" alt="QR" style="width: 200px; height: 200px;" />
            <script>
                setTimeout(() => { window.print(); window.close(); }, 500);
            <\/script>
        </body>
        </html>
    `);
    ventana.document.close();
}

document.getElementById('filtroBusqueda').addEventListener('input', function () {
    const filtro = this.value.toLowerCase();
    const filas = document.querySelectorAll('#tablaSolicitudesQR tbody tr');

    filas.forEach(fila => {
        const id = fila.children[0].innerText.toLowerCase();
        const descripcion = fila.children[1].innerText.toLowerCase();

        if (id.includes(filtro) || descripcion.includes(filtro)) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
});
</script>

<style>
    #tablaSolicitudesQR th, #tablaSolicitudesQR td {
        vertical-align: middle;
        text-align: center;
    }
</style>
