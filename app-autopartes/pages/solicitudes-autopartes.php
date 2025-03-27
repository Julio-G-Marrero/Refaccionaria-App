<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
global $wpdb;
$ubicaciones = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ubicaciones_autopartes ORDER BY nombre ASC");
$solicitudes = $wpdb->get_results("SELECT s.*, a.codigo, a.descripcion, u.nombre AS ubicacion_nombre FROM {$wpdb->prefix}solicitudes_piezas s INNER JOIN {$wpdb->prefix}autopartes a ON s.autoparte_id = a.id LEFT JOIN {$wpdb->prefix}ubicaciones_autopartes u ON s.ubicacion_id = u.id WHERE s.estado = 'pendiente' ORDER BY s.fecha_envio DESC");
?>

<div class="wrap">
    <h2>Solicitudes de Autopartes Pendientes</h2>

    <div class="table-responsive">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Descripción</th>
                <th>Ubicación</th>
                <th>Fecha de Envío</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($solicitudes as $s): ?>
                <?php 
                    $imagenes = maybe_unserialize($s->imagenes); 
                    $imagenes_json = json_encode($imagenes);
                ?>
                <tr>
                    <td data-label="ID"><?= esc_html($s->id) ?></td>
                    <td data-label="Código"><?= esc_html($s->codigo) ?></td>
                    <td data-label="Descripción"><?= esc_html($s->descripcion) ?></td>
                    <td data-label="Ubicación"><?= esc_html($s->ubicacion_nombre) ?></td>
                    <td data-label="Fecha"><?= esc_html($s->fecha_envio) ?></td>
                    <td data-label="Acciones">
                        <button class="button ver-detalles" 
                            data-id="<?= esc_attr($s->id) ?>" 
                            data-codigo="<?= esc_attr($s->codigo) ?>"
                            data-descripcion="<?= esc_attr($s->descripcion) ?>"
                            data-ubicacion="<?= esc_attr($s->ubicacion_nombre) ?>"
                            data-observaciones="<?= esc_attr($s->observaciones) ?>"
                            data-estado="<?= esc_attr($s->estado_pieza) ?>"
                            data-compatibilidades='<?= esc_attr(json_encode($wpdb->get_results($wpdb->prepare("SELECT marca, submarca, rango FROM {$wpdb->prefix}compatibilidades WHERE autoparte_id = %d", $s->autoparte_id))) ) ?>'
                            data-imagenes='<?= esc_attr($imagenes_json) ?>'>
                            Ver Detalles
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div id="imagen-modal" class="fixed inset-0 bg-black bg-opacity-80 z-50 hidden items-center justify-center p-4">
        <div class="max-w-screen-md max-h-screen overflow-auto relative">
            <button onclick="cerrarImagenModal()" class="absolute top-2 right-2 text-stone-700 text-2xl font-bold">&times;</button>
            <img id="imagen-modal-src" src="" class="max-w-full max-h-[80vh] rounded shadow-lg" />
        </div>
    </div>
</div>

<?php
    $categorias = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false
    ]);
?>
<script>
    window.categoriasWoo = <?= json_encode(get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false
    ])) ?>;
</script>

<script>
    var ajaxurl = "<?= admin_url('admin-ajax.php') ?>";
    var urlSitio ="https://dev-refacciones-app.pantheonsite.io/"
</script>
<script>
    window.ubicacionesDisponibles = <?= json_encode($ubicaciones) ?>;
</script>


<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ver-detalles').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const codigo = this.dataset.codigo;
            const descripcion = this.dataset.descripcion;
            const ubicacion = this.dataset.ubicacion;
            const observaciones = this.dataset.observaciones;
            const estado = this.dataset.estado || 'No especificado';
            const imagenes = JSON.parse(this.dataset.imagenes || '[]');
            const compatibilidades = JSON.parse(this.dataset.compatibilidades || '[]');

            const imagenCatalogo = `https://www.radec.com.mx/sites/all/files/productos/${codigo}.jpg`;

            let compatHtml = '';
            if (compatibilidades.length > 0) {
                compatHtml = `<ul class="list-disc ml-5 text-sm text-left">`;
                compatibilidades.forEach(c => {
                    compatHtml += `<li>${c.marca} ${c.submarca} (${c.rango})</li>`;
                });
                compatHtml += `</ul>`;
            } else {
                compatHtml = `<p class="text-sm text-gray-500">No hay compatibilidades registradas.</p>`;
            }

            let imagenSubida = '';
            if (imagenes.length > 0) {
                imagenSubida += `<div class="grid grid-cols-2 md:grid-cols-3 gap-4 justify-center">`;
                imagenes.forEach((url) => {
                    imagenSubida += `
                        <img src="${url}" 
                            class="w-full max-w-[120px] h-[100px] object-cover border rounded shadow cursor-pointer hover:scale-105 transition-transform duration-200 mx-auto" 
                            onclick="mostrarImagenGrande('${url}')"
                        />`;
                });
                imagenSubida += `</div>`;
            } else {
                imagenSubida = `<p class="text-gray-400 italic">Sin imágenes subidas</p>`;
            }

            const contenido = `
                <div class="text-left space-y-4 text-sm">
                    <div><strong>Código:</strong> ${codigo}</div>
                    <div><strong>Descripción:</strong> ${descripcion}</div>
                    <div><strong>Estado de la Pieza:</strong> <span class="text-blue-700 font-medium">${estado.replaceAll('_', ' ')}</span></div>
                    <div><strong>Ubicación Física:</strong> ${ubicacion}</div>
                    <div><strong>Observaciones:</strong> ${observaciones || '<span class="text-gray-400 italic">Ninguna</span>'}</div>
                    <div><strong>Compatibilidades:</strong>${compatHtml}</div>
                    <div class="grid grid-cols-2 gap-4 mt-4 text-center">
                        <div>
                            <p class="font-semibold mb-2">Imagen del Catálogo</p>
                            <img src="${imagenCatalogo}" width="120" class="mx-auto border p-2 cursor-pointer rounded shadow" onclick="mostrarImagenGrande('${imagenCatalogo}')">
                        </div>
                        <div>
                            <p class="font-semibold mb-2">Imagen Subida</p>
                            ${imagenSubida}
                        </div>
                    </div>
                </div>
            `;

            Swal.fire({
                title: 'Detalles de la Solicitud',
                html: contenido,
                width: '700px',
                showCloseButton: true,
                confirmButtonText: 'Aprobar Solicitud',
                showCancelButton: true,
                cancelButtonText: 'Cerrar'
            }).then((result) => {
                if (result.isConfirmed) {
                    mostrarFormularioCreacionProducto(id, codigo, descripcion, ubicacion, observaciones, compatibilidades);
                }
            });
        });
    });
});

function mostrarImagenGrande(url) {
  const modal = document.getElementById('imagen-modal');
  const img = document.getElementById('imagen-modal-src');
  img.src = url;
  modal.classList.add('show');
}

function cerrarImagenModal() {
  document.getElementById('imagen-modal').classList.remove('show');
}


function mostrarFormularioCreacionProducto(solicitudId, codigo, descripcion, ubicacionActual, observaciones, compatibilidades) {
    const imagenes = JSON.parse(
        document.querySelector(`button[data-id="${solicitudId}"]`).dataset.imagenes || '[]'
    );

    let sugerida = '';
    const desc = descripcion.toUpperCase();
    const mapaSugerencias = ['PUERTA', 'CALAVERA', 'COFRE', 'ESPEJO', 'FARO', 'DEFENSA'];

    for (const sugerencia of mapaSugerencias) {
        if (desc.includes(sugerencia)) {
            const encontrada = window.categoriasWoo.find(c => c.name.toUpperCase().includes(sugerencia));
            if (encontrada) {
                sugerida = encontrada.term_id;
                break;
            }
        }
    }

    let galeriaHTML = '';
    if (imagenes.length > 0) {
        galeriaHTML = `
            <label class="block text-sm font-medium mb-1">Selecciona imágenes para el producto:</label>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">`;

        imagenes.forEach((url, i) => {
            galeriaHTML += `
                <label class="block text-center cursor-pointer">
                    <input type="checkbox" name="galeria[]" value="${url}" checked class="mb-1">
                    <img src="${url}" class="w-full h-[100px] object-cover border rounded shadow mx-auto">
                </label>`;
        });

        galeriaHTML += `</div>`;
    }

    const opcionesUbicaciones = window.ubicacionesDisponibles
        .map(u => `<option value="${u.id}" ${u.nombre === ubicacionActual ? 'selected' : ''}>${u.nombre}</option>`)
        .join('');

    const opcionesCategorias = window.categoriasWoo
        .map(cat => `<option value="${cat.term_id}">${cat.name}</option>`)
        .join('');

    Swal.fire({
    title: 'Crear Producto en WooCommerce',
    html: `
        <form id="formCrearProducto" class="text-left">
            <input type="hidden" name="solicitud_id" value="${solicitudId}">

            <label class="block text-sm font-medium">Código (SKU)</label>
            <input type="text" id="sku" name="sku" value="${codigo}" class="w-full border rounded p-2 mb-2">

            <label class="block text-sm font-medium">Nombre del Producto</label>
            <input type="text" id="nombre" name="nombre" value="${descripcion}" class="w-full border rounded p-2 mb-2">

            <label class="block text-sm font-medium">Precio</label>
            <input type="number" id="precio" name="precio" class="w-full border rounded p-2 mb-2">

            <label class="block text-sm font-medium">Categoría</label>
            <select id="categoria" name="categoria" class="w-full border rounded p-2 mb-2">
                <option value="">Seleccione una</option>
                ${opcionesCategorias}
            </select>

            <label class="block text-sm font-medium">Ubicación Física</label>
            <select id="ubicacion" name="ubicacion" class="w-full border rounded p-2 mb-2">
                <option value="">Seleccione una ubicación</option>
                ${opcionesUbicaciones}
            </select>

            <label class="block text-sm font-medium">Observaciones</label>
            <textarea id="observaciones" name="observaciones" class="w-full border rounded p-2 mb-4">${observaciones}</textarea>

            ${galeriaHTML}

            <!-- Mostrar compatibilidades (visible para depuración) -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Compatibilidades detectadas</label>
                <textarea readonly class="w-full border rounded p-2 text-xs bg-gray-100 text-gray-800" rows="4">
				${compatibilidades.map(c => `${c.marca} ${c.submarca} (${c.rango})`).join('\n')}
                </textarea>
            </div>

            <!-- Compatibilidades como input hidden -->
            <input type="hidden" name="compatibilidades_debug" id="compatibilidades_debug" value='${JSON.stringify(compatibilidades)}'>
        </form>
    `,
        didOpen: () => {
            if (sugerida) {
                document.getElementById("categoria").value = sugerida;
            }
        },
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Crear Producto',
        preConfirm: () => {
            const seleccionadas = [...document.querySelectorAll('input[name="galeria[]"]:checked')].map(i => i.value);
            return {
                solicitud_id: solicitudId,
                sku: document.getElementById('sku').value,
                nombre: document.getElementById('nombre').value,
                precio: document.getElementById('precio').value,
                categoria: document.getElementById('categoria').value,
                ubicacion: document.getElementById('ubicacion').value,
                observaciones: document.getElementById('observaciones').value,
                imagenes: seleccionadas,
                compatibilidades: compatibilidades
            };
        }
    }).then(result => {
        if (result.isConfirmed) {
            const datos = result.value;

            const formData = new URLSearchParams();
            formData.append('action', 'crear_producto_autoparte');
            formData.append('solicitud_id', datos.solicitud_id);
            formData.append('sku', datos.sku);
            formData.append('nombre', datos.nombre);
            formData.append('precio', datos.precio);
            formData.append('categoria', datos.categoria);
            formData.append('ubicacion', datos.ubicacion);
            formData.append('observaciones', datos.observaciones);
            formData.append('imagenes', JSON.stringify(datos.imagenes));
            formData.append('compatibilidades', JSON.stringify(datos.compatibilidades));

            Swal.fire({
                title: 'Creando producto...',
                html: 'Por favor espera mientras se crea el producto',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent('https://dev-refacciones-app.pantheonsite.io/?sku=' + datos.sku)}`;
                    const compat = data.compatibilidades || [];

                    Swal.fire({
                        title: 'Producto creado',
                        html: `
                            <p><strong>SKU:</strong> ${datos.sku}</p>
                            <p>El producto fue creado exitosamente.</p>
                            <p class="mt-4">Código QR:</p>
                            <img src="${qrUrl}" alt="QR del producto" class="mx-auto mt-2" />

                            <div class="text-left mt-4">
                                <p class="font-semibold">Compatibilidades asignadas:</p>
                                <ul class="list-disc ml-5 text-sm">
                                    ${compat.map(c => `<li>${c}</li>`).join('')}
                                </ul>
                            </div>
                        `,
                        width: '700px',
                        confirmButtonText: 'Aceptar'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.data?.message || 'Error al crear el producto.', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
                console.error('Error:', error);
            });
        }
    });
}


</script>


<style>
    
#imagen-modal.show {
  display: flex;
  z-index: 2000 !important;
}
#imagen-modal {
    display: none;
}
#imagen-modal.show {
    display: flex;
}
/* Contenedor general */
.wrap {
    max-width: 1100px;
    padding: 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Título */
.wrap h2 {
    font-size: 1.8rem;
    color: #333;
    margin-bottom: 25px;
}

/* Tabla moderna */
table.wp-list-table {
    width: 80%;
    border-collapse: collapse;
    background-color: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

table.wp-list-table th,
table.wp-list-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    text-align: left;
    vertical-align: middle;
}

table.wp-list-table th {
    background-color: #f8f8f8;
    font-weight: 600;
    color: #444;
    text-transform: uppercase;
    font-size: 0.85rem;
}

table.wp-list-table tr:hover {
    background-color: #f9f9f9;
}

/* Botón */
.ver-detalles.button {
    background-color: #0073aa;
    border: none;
    padding: 8px 14px;
    font-size: 0.9rem;
    color: white;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s ease;
}
.ver-detalles.button:hover {
    background-color: #005f8d;
}

/* Responsive: móvil */
@media screen and (max-width: 768px) {
    table.wp-list-table,
    table.wp-list-table thead,
    table.wp-list-table tbody,
    table.wp-list-table th,
    table.wp-list-table td,
    table.wp-list-table tr {
        display: block;
    }

    table.wp-list-table thead {
        display: none;
    }

    table.wp-list-table tr {
        margin-bottom: 15px;
        border-bottom: 2px solid #ccc;
        background: #fff;
        border-radius: 6px;
        padding: 10px;
    }

    table.wp-list-table td {
        position: relative;
        padding-left: 50%;
        border: none;
        border-bottom: 1px solid #eee;
    }

    table.wp-list-table td::before {
        position: absolute;
        top: 12px;
        left: 15px;
        width: 45%;
        white-space: nowrap;
        font-weight: bold;
        color: #666;
        content: attr(data-label);
    }
}
</style>
