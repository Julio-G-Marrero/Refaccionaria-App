<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
global $wpdb;

// Obtener marcas desde compatibilidades
$marcas = $wpdb->get_col("SELECT DISTINCT marca FROM {$wpdb->prefix}compatibilidades ORDER BY marca ASC");
$modelos = [];

$marca = isset($_GET['marca']) ? sanitize_text_field($_GET['marca']) : '';
$submarca = isset($_GET['submarca']) ? sanitize_text_field($_GET['submarca']) : '';
$anio = isset($_GET['rango']) ? sanitize_text_field($_GET['rango']) : '';
$autoparte = isset($_GET['autoparte']) ? sanitize_text_field($_GET['autoparte']) : '';

// Obtener submarcas
if (!empty($marca)) {
    $modelos = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT submarca FROM {$wpdb->prefix}compatibilidades WHERE marca = %s ORDER BY submarca ASC",
        $marca
    ));
}

$productos = [];
if (isset($_GET['buscar']) && $marca && $submarca && $anio) {
    // Preparar condición de rango de año
    $anio_int = intval($anio);
    $sql = "SELECT a.*, c.marca, c.submarca, c.rango FROM {$wpdb->prefix}autopartes a
            INNER JOIN {$wpdb->prefix}compatibilidades c ON a.id = c.autoparte_id
            WHERE c.marca = %s AND c.submarca = %s";

    $params = [$marca, $submarca];

    // Lógica para interpretar el campo rango (ej: 2007-2012 o 2010)
    $sql .= " AND (
                c.rango = %s
                OR (
                    c.rango LIKE '%-%' AND
                    CAST(SUBSTRING_INDEX(c.rango, '-', 1) AS UNSIGNED) <= %d AND
                    CAST(SUBSTRING_INDEX(c.rango, '-', -1) AS UNSIGNED) >= %d
                )
            )";
    $params[] = $anio;
    $params[] = $anio_int;
    $params[] = $anio_int;
    $busqueda = ''; 
    if (!empty($autoparte)) {
        $busqueda = sanitize_text_field($autoparte);
        // Esto aplica prioridad a las coincidencias que empiezan con la palabra
        $sql .= " AND (
            a.descripcion LIKE %s -- empieza con
            OR a.descripcion LIKE %s -- contiene palabra exacta
            OR a.descripcion LIKE %s -- contiene palabra al final
        )";
        $params[] = $busqueda . '%';       // empieza con "puerta"
        $params[] = '% ' . $busqueda . ' %'; // tiene " puerta "
        $params[] = '% ' . $busqueda;       // termina con " puerta"
    }
    $sql .= " ORDER BY 
    CASE 
        WHEN a.descripcion LIKE %s THEN 1
        WHEN a.descripcion LIKE %s THEN 2
        ELSE 3
    END";
    $params[] = $busqueda . '%';
    $params[] = '% ' . $busqueda . ' %';


    $sql .= " LIMIT 100";

    $productos = $wpdb->get_results($wpdb->prepare($sql, ...$params));
}
?>

<div class="main-content">
    <h2>Captura de Productos - Buscar en Catálogo</h2>

    <form method="GET" action="<?= admin_url('admin.php') ?>">
        <input type="hidden" name="page" value="captura-productos">

        <label>Marca:</label>
        <select name="marca" required onchange="this.form.submit()">
            <option value="">Selecciona una marca</option>
            <?php foreach ($marcas as $m): ?>
                <option value="<?= esc_attr($m) ?>" <?= ($marca === $m) ? 'selected' : '' ?>><?= esc_html($m) ?></option>
            <?php endforeach; ?>
        </select>

        <?php if (!empty($modelos)): ?>
            <div class="contenedor-input">
                <label>Submarca:</label>
                <select name="submarca">
                    <option value="">Selecciona una submarca</option>
                    <?php foreach ($modelos as $sm): ?>
                        <option value="<?= esc_attr($sm) ?>" <?= ($submarca === $sm) ? 'selected' : '' ?>><?= esc_html($sm) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="contenedor-input">
                <label for="anio">Año:</label>
                <select name="rango" id="anio" required>
                    <option value="">Selecciona un año</option>
                    <?php for ($i = 2026; $i >= 1990; $i--): ?>
                        <option value="<?= $i ?>" <?= ($anio == $i) ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            <div>
            <div class="contenedor-input">
                <label>Buscar por pieza o descripción:</label>
                <input type="text" name="autoparte" value="<?= esc_attr($autoparte) ?>" placeholder="Ej. faro, fascia...">
            </div>

            <button type="submit" name="buscar" value="1">Buscar Coincidencias</button>
        <?php endif; ?>
    </form>

    <?php if (!empty($productos)): ?>
        <h3>Coincidencias encontradas (<?= count($productos) ?>)</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Imagen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                    <?php
                        $codigo = esc_attr($p->codigo);
                        $imagen_url = "https://www.radec.com.mx/sites/all/files/productos/{$codigo}.jpg";
                    ?>
                    <tr>
                        <td data-label="Código"><?= esc_html($codigo) ?></td>
                        <td data-label="Descripción"><?= esc_html($p->descripcion) ?></td>
                        <td data-label="Imagen">
                            <a href="#" onclick="mostrarImagen('<?= esc_url($imagen_url) ?>'); return false;">
                                <img src="<?= esc_url($imagen_url) ?>" alt="imagen de <?= esc_attr($p->descripcion) ?>" width="60">
                            </a>
                        </td>
                        <td data-label="Acciones">
                            <form method="GET" action="<?= admin_url('admin.php') ?>">
                                <input type="hidden" name="page" value="resumen-pieza">
                                <input type="hidden" name="autoparte_id" value="<?= esc_attr($p->id) ?>">
                                <button type="submit">Seleccionar</button>
                            </form>
                        </td>
                    </tr>

                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="modalImagen" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.8); z-index:1000; justify-content:center; align-items:center;">
            <img id="imagenGrande" src="" style="max-width:90%; max-height:90%; border:5px solid white;">
        </div>

    <?php elseif (isset($_GET['buscar'])): ?>
        <p>No se encontraron resultados.</p>
    <?php endif; ?>
</div>
<script>
    function mostrarImagen(url) {
        const modal = document.getElementById('modalImagen');
        const img = document.getElementById('imagenGrande');
        img.src = url;
        modal.style.display = 'flex';
        modal.onclick = () => modal.style.display = 'none';
    }
</script>
<style>
/* Aquí va el CSS que te pasé */
/* Estilo general */
.main-content {
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1000px;
    margin: auto;
    background-color: #ffffff;
}

/* Títulos */
.main-content h2, .main-content h3 {
    font-size: 1.5rem;
    margin-bottom: 20px;
    color: #333;
}

/* Etiquetas */
.main-content label {
    display: block;
    margin: 15px 0 5px;
    font-weight: bold;
    color: #555;
}

/* Inputs y Selects */
.main-content input[type="text"],
.main-content input[type="number"],
.main-content select {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1rem;
    box-sizing: border-box;
}

/* Botones */
.main-content button {
    margin-top: 15px;
    padding: 10px 20px;
    background-color: #0073aa;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    transition: background-color 0.3s ease;
}
.main-content button:hover {
    background-color: #005f8d;
}

/* Tabla responsive */
.main-content table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    overflow-x: auto;
}
.main-content th, .main-content td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}
.main-content th {
    background-color: #f7f7f7;
}

/* Imagen pequeña */
.main-content img {
    border-radius: 4px;
    cursor: pointer;
}

/* Modal imagen */
#modalImagen {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.85);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
#modalImagen img {
    max-width: 90%;
    max-height: 90%;
    border: 5px solid white;
    box-shadow: 0 0 20px black;
}

/* Responsive: diseño adaptable */
@media screen and (max-width: 768px) {
    .main-content h2, .main-content h3 {
        font-size: 1.2rem;
    }

    .main-content table,
    .main-content thead,
    .main-content tbody,
    .main-content th,
    .main-content td,
    .main-content tr {
        display: block;
    }

    .main-content thead tr {
        display: none;
    }

    .main-content td {
        position: relative;
        padding-left: 50%;
        margin-bottom: 10px;
        border: none;
        border-bottom: 1px solid #ddd;
    }

    .main-content td:before {
        content: attr(data-label);
        position: absolute;
        top: 10px;
        left: 10px;
        width: 45%;
        font-weight: bold;
        white-space: nowrap;
        color: #666;
    }

    .main-content form {
        display: flex;
        flex-direction: column;
    }

    .main-content button {
        width: 100%;
    }
    .contenedor-input {
        max-width: 400px !important;
    }
}
</style>
