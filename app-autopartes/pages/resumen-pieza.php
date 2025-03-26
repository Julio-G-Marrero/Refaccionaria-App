<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
global $wpdb;

$autoparte_id = isset($_GET['autoparte_id']) ? intval($_GET['autoparte_id']) : 0;
if (!$autoparte_id) {
    echo '<div class="notice notice-error"><p>Autoparte no encontrada.</p></div>';
    return;
}

$pieza = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}autopartes WHERE id = %d", $autoparte_id));
$compatibilidades = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}compatibilidades WHERE autoparte_id = %d", $autoparte_id));
$ubicaciones = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ubicaciones_autopartes ORDER BY nombre ASC");

if (!$pieza) {
    echo '<div class="notice notice-error"><p>Pieza no encontrada.</p></div>';
    return;
}

$imagen_url = "https://www.radec.com.mx/sites/all/files/productos/{$pieza->codigo}.jpg";
?>

<div class="wrap">
    <h2>Resumen de la Pieza Seleccionada</h2>

    <table class="widefat fixed">
        <tr><th>Código:</th><td><?= esc_html($pieza->codigo) ?></td></tr>
        <tr><th>Descripción:</th><td><?= esc_html($pieza->descripcion) ?></td></tr>
        <?php if (!empty($pieza->sector)) : ?>
        <tr><th>Sector:</th><td><?= esc_html($pieza->sector) ?></td></tr>
        <?php endif; ?>
        <tr><th>Imagen de Catálogo:</th>
            <td><img src="<?= esc_url($imagen_url) ?>" alt="imagen" width="120"></td>
        </tr>
    </table>

    <h3>Compatibilidades</h3>
    <?php if ($compatibilidades): ?>
        <ul>
            <?php foreach ($compatibilidades as $c): ?>
                <li><?= esc_html("{$c->marca} {$c->submarca} ({$c->rango})") ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No hay compatibilidades registradas.</p>
    <?php endif; ?>

    <hr>
    <h3>Agregar Datos de la Pieza Física</h3>
    <form id="form-solicitud-pieza" enctype="multipart/form-data">
        <?php wp_nonce_field('enviar_solicitud_pieza_nonce', 'enviar_solicitud_pieza_nonce_field'); ?>
        <input type="hidden" name="autoparte_id" value="<?= esc_attr($autoparte_id) ?>">

        <label>Ubicación Física:</label><br>
        <select name="ubicacion" required style="width: 300px;">
            <option value="">Selecciona una ubicación</option>
            <?php foreach ($ubicaciones as $u): ?>
                <option value="<?= esc_attr($u->id) ?>"><?= esc_html($u->nombre) ?></option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Observaciones:</label><br>
        <textarea name="observaciones" rows="4" cols="50"></textarea><br><br>

        <label>Estado de la Pieza:</label><br>
        <select name="estado_pieza" required style="width: 300px;">
            <option value="">Selecciona el estado</option>
            <option value="nuevo">Nuevo</option>
            <option value="usado_buen_estado">Usado en buen estado</option>
            <option value="usado_reparacion">Usado para reparación</option>
        </select><br><br>

        <label>Fotos de la Pieza:</label><br>
        <input type="file" id="input-fotos" accept="image/*" multiple><br><br>
        <div id="preview-fotos" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px;"></div>
        <!-- Archivos reales -->
        <div id="contenedor-archivos"></div>

        <button type="submit" class="button button-primary" id="btn-enviar-solicitud">
        Enviar Solicitud para Aprobación
        </button>
    </form>

    <div id="estado-envio" style="margin-top: 20px;"></div>

</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('form-solicitud-pieza').addEventListener('submit', async function(e) {
    e.preventDefault();

    const form = e.target;
    const estado = document.getElementById('estado-envio');
    const submitBtn = document.getElementById('btn-enviar-solicitud');
    const formData = new FormData(form);

    formData.append('action', 'ajax_enviar_solicitud_pieza');
    formData.append('security', '<?= wp_create_nonce("enviar_solicitud_pieza") ?>');

    // Mostrar popup de carga y bloquear interfaz
    Swal.fire({
        title: 'Enviando...',
        text: 'Por favor espera mientras se procesa la solicitud',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
     // Desactivar botón para evitar múltiples envíos
     submitBtn.disabled = true;
    try {
        archivosSeleccionados.forEach((item, index) => {
            formData.append(`fotos[]`, item.file);
        });

        const response = await fetch(ajaxurl, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            const solicitudID = result.data.id;

            Swal.fire({
                icon: 'success',
                title: '✅ Solicitud registrada',
                html: `<p>Tu número de solicitud es:</p><h2 style="margin-top:10px; color:#0073aa;">#${solicitudID}</h2><p>Por favor escribe este número en la pieza física.</p>`,
                confirmButtonText: 'Entendido'
            }).then(() => {
                // Redirección al finalizar
                window.location.href = "/wp-admin/admin.php?page=captura-productos";
            });

            form.reset();
            estado.innerHTML = '';
        }
        else {
            Swal.close();
            estado.innerHTML = `<span style="color:red;"><strong>❌ Error: ${result.data.message}</strong></span>`;
            submitBtn.disabled = false; // volver a activar en caso de error
        }
    } catch (error) {
        console.error('Error al enviar:', error);
        Swal.close();
        estado.innerHTML = '<span style="color:red;"><strong>❌ Error de red al enviar la solicitud.</strong></span>';
        submitBtn.disabled = false; // volver a activar en caso de error
    }
});
// Vista previa de imágenes seleccionadas
let archivosSeleccionados = [];

const inputFotos = document.getElementById('input-fotos');
const preview = document.getElementById('preview-fotos');
const contenedorArchivos = document.getElementById('contenedor-archivos');

inputFotos.addEventListener('change', () => {
    const nuevosArchivos = Array.from(inputFotos.files);

    nuevosArchivos.forEach(file => {
        if (!file.type.startsWith('image/')) return;

        const id = Math.random().toString(36).substring(2, 15); // ID único
        archivosSeleccionados.push({ id, file });

        const reader = new FileReader();
        reader.onload = e => {
            const wrapper = document.createElement('div');
            wrapper.style.position = 'relative';
            wrapper.style.display = 'inline-block';

            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.width = '100px';
            img.style.border = '1px solid #ccc';
            img.style.borderRadius = '6px';
            img.style.objectFit = 'cover';

            const btn = document.createElement('button');
            btn.innerHTML = '×';
            btn.type = 'button';
            btn.title = 'Eliminar';
            btn.style.position = 'absolute';
            btn.style.top = '2px';
            btn.style.right = '2px';
            btn.style.background = '#d33';
            btn.style.color = 'white';
            btn.style.border = 'none';
            btn.style.borderRadius = '50%';
            btn.style.width = '20px';
            btn.style.height = '20px';
            btn.style.cursor = 'pointer';
            btn.onclick = () => {
                archivosSeleccionados = archivosSeleccionados.filter(a => a.id !== id);
                wrapper.remove();
            };

            wrapper.appendChild(img);
            wrapper.appendChild(btn);
            preview.appendChild(wrapper);
        };
        reader.readAsDataURL(file);
    });

    inputFotos.value = ''; // limpiar para permitir volver a seleccionar el mismo archivo
});
</script>
<style>
    /* Estilo contenedor general */
.wrap {
    max-width: 900px;
    margin: auto;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #fff;
}

/* Títulos */
.wrap h2, .wrap h3 {
    margin-bottom: 20px;
    color: #333;
}

/* Tabla */
.wrap table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    background-color: #f9f9f9;
    border: 1px solid #ddd;
}
.wrap th, .wrap td {
    text-align: left;
    padding: 12px 15px;
    border-bottom: 1px solid #ddd;
}
.wrap th {
    background-color: #f1f1f1;
    color: #444;
    width: 180px;
}

/* Imagen */
.wrap img {
    border-radius: 6px;
    max-width: 100%;
    height: auto;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Lista de compatibilidades */
.wrap ul {
    list-style-type: disc;
    margin-left: 25px;
    margin-bottom: 30px;
}
.wrap li {
    margin-bottom: 5px;
}

/* Formulario */
#form-solicitud-pieza label {
    display: block;
    margin-top: 15px;
    font-weight: 600;
    color: #444;
}
#form-solicitud-pieza select,
#form-solicitud-pieza textarea,
#form-solicitud-pieza input[type="file"] {
    width: 100%;
    max-width: 500px;
    padding: 10px;
    font-size: 1rem;
    border: 1px solid #ccc;
    border-radius: 6px;
    margin-top: 5px;
    box-sizing: border-box;
}

/* Botón */
#form-solicitud-pieza button {
    margin-top: 20px;
    padding: 10px 25px;
    font-size: 1rem;
    background-color: #0073aa;
    border: none;
    border-radius: 6px;
    color: white;
    cursor: pointer;
    transition: background-color 0.3s ease;
}
#form-solicitud-pieza button:hover {
    background-color: #005f8d;
}

/* Estado del envío */
#estado-envio {
    font-weight: bold;
    color: #0073aa;
}

/* Responsive */
@media screen and (max-width: 768px) {
    .wrap {
        padding: 15px;
    }

    .wrap th {
        width: auto;
    }

    #form-solicitud-pieza select,
    #form-solicitud-pieza textarea,
    #form-solicitud-pieza input[type="file"] {
        width: 100%;
    }
}
</style>