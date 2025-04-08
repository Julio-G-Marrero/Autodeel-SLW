<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual

global $wpdb;
$ubicaciones = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ubicaciones_autopartes ORDER BY nombre ASC");
$solicitudes = $wpdb->get_results("SELECT s.*, a.codigo, a.descripcion, u.nombre AS ubicacion_nombre FROM {$wpdb->prefix}solicitudes_piezas s INNER JOIN {$wpdb->prefix}autopartes a ON s.autoparte_id = a.id LEFT JOIN {$wpdb->prefix}ubicaciones_autopartes u ON s.ubicacion_id = u.id WHERE s.estado = 'pendiente' ORDER BY s.fecha_envio DESC");
?>

<div class="wrap">
    <h2 style="font-size: 24px; margin-bottom: 20px;">Solicitudes de Autopartes Pendientes</h2>

    <div class="responsive-table-wrapper">
        <table class="custom-responsive-table">
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
                        <td data-label="Descripción" class="td-descripcion-per"><?= esc_html($s->descripcion) ?></td>
                        <td data-label="Ubicación"><?= esc_html($s->ubicacion_nombre) ?></td>
                        <td data-label="Fecha"><?= esc_html($s->fecha_envio) ?></td>
                        <td data-label="Acciones">
                            <button class="btn-detalles ver-detalles"
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
            <button onclick="cerrarImagenModal()" class="absolute top-2 right-2 text-white text-2xl font-bold hover:text-red-300">&times;</button>
            <img id="imagen-modal-src" src="" class="max-w-full max-h-[80vh] rounded shadow-lg border-4 border-white" />
        </div>
    </div>
</div>

<style>
/* Wrapper para scroll horizontal solo en pantallas pequeñas */
.responsive-table-wrapper {
    overflow-x: auto;
    width: 100%;
}

/* Estilo base */
.custom-responsive-table {
    width: 100%;
    min-width: 800px;
    border-collapse: collapse;
    border: 1px solid #ddd;
    background-color: #fff;
}

.custom-responsive-table th,
.custom-responsive-table td {
    text-align: left;
    padding: 12px;
    border-bottom: 1px solid #eee;
    white-space: nowrap;
}

.custom-responsive-table th {
    background-color: #f7f7f7;
    font-weight: bold;
}

/* Botón de acción */
.btn-detalles {
    background: #0073aa;
    color: #fff;
    border: none;
    padding: 8px 16px;
    font-weight: bold;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-detalles:hover {
    background: #005177;
}

/* Responsive en móvil: usar data-label */
@media screen and (max-width: 768px) {
    .custom-responsive-table {
        min-width: 100%;
        border: none;
    }

    .custom-responsive-table thead {
        display: none;
    }

    .custom-responsive-table tr {
        display: block;
        margin-bottom: 15px;
        border-bottom: 2px solid #ddd;
    }

    .custom-responsive-table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px;
        font-size: 14px;
        border: none;
        border-bottom: 1px solid #eee;
    }

    .custom-responsive-table td::before {
        content: attr(data-label);
        font-weight: bold;
        margin-right: 10px;
        flex-shrink: 0;
    }
}
@media screen and (max-width: 768px) {
    .custom-responsive-table {
        width: 100%;
        border-collapse: collapse;
        display: block;
        overflow-x: auto;
    }

    .custom-responsive-table thead {
        display: none;
    }

    .custom-responsive-table tbody,
    .custom-responsive-table tr,
    .custom-responsive-table td {
        display: block;
        width: 100%;
    }

    .custom-responsive-table tr {
        margin-bottom: 16px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: #fff;
        padding: 12px;
    }

    .custom-responsive-table td {
        padding: 8px 10px;
        text-align: left;
        border: none;
        border-bottom: 1px solid #eee;
        word-wrap: break-word;
        word-break: break-word;
        white-space: normal;
    }

    .custom-responsive-table td::before {
        content: attr(data-label);
        display: block;
        font-weight: bold;
        margin-bottom: 4px;
        color: #444;
    }
}
@media screen and (max-width: 1024px) {
    .custom-responsive-table {
        display: block;
        overflow-x: auto;
        width: 100%;
        -webkit-overflow-scrolling: touch; /* iOS smooth scrolling */
    }

    .custom-responsive-table table {
        min-width: 900px;
        width: 100%;
    }

    .custom-responsive-table th,
    .custom-responsive-table td {
        white-space: nowrap;
    }
}

</style>

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
                    mostrarFormularioCreacionProducto(id, codigo, descripcion, ubicacion, observaciones, compatibilidades, estado);
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


// Función para comprimir una imagen desde una URL usando un canvas
function compressImage(url, quality = 0.7, maxWidth = 1024, maxHeight = 1024) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    // Permitir carga crossOrigin para evitar problemas con CORS (si el servidor lo permite)
    img.crossOrigin = 'Anonymous';
    img.onload = function () {
      let width = img.width;
      let height = img.height;
      // Si la imagen excede los límites, se redimensiona proporcionalmente
      if (width > maxWidth || height > maxHeight) {
        const ratio = Math.min(maxWidth / width, maxHeight / height);
        width = width * ratio;
        height = height * ratio;
      }
      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, width, height);
      // Genera un blob en formato JPEG con la calidad indicada
      canvas.toBlob((blob) => {
        if (blob) {
          const reader = new FileReader();
          reader.onloadend = () => {
            resolve(reader.result); // Devuelve un Data URL comprimido
          };
          reader.onerror = reject;
          reader.readAsDataURL(blob);
        } else {
          reject(new Error('Error al generar el blob'));
        }
      }, 'image/jpeg', quality);
    };
    img.onerror = reject;
    img.src = url;
  });
}

function mostrarFormularioCreacionProducto(solicitudId, codigo, descripcion, ubicacionActual, observaciones, compatibilidades,estado) {
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
    title: 'Crear Producto',
    html: `
    <form id="formCrearProducto" class="form-crear-producto">
        <input type="hidden" name="solicitud_id" value="${solicitudId}">

        <div class="form-group">
            <label for="sku">Código (SKU)</label>
            <input type="text" id="sku" name="sku" value="${codigo}">
        </div>

        <div class="form-group">
            <label for="nombre">Nombre del Producto</label>
            <input type="text" id="nombre" name="nombre" value="${descripcion}">
        </div>

        <div id="precio-referencia" class="precio-referencia"></div>

        <div class="form-group">
            <label for="precio">Precio</label>
            <input type="number" id="precio" name="precio">
        </div>

        <div class="form-group">
            <label for="categoria">Categoría</label>
            <select id="categoria" name="categoria">
                <option value="">Seleccione una</option>
                ${opcionesCategorias}
            </select>
        </div>

        <div class="form-group">
            <label for="ubicacion">Ubicación Física</label>
            <select id="ubicacion" name="ubicacion">
                <option value="">Seleccione una ubicación</option>
                ${opcionesUbicaciones}
            </select>
        </div>

        <div class="form-group">
            <label for="estado_pieza">Estado de la Pieza</label>
            <select id="estado_pieza" name="estado_pieza">
                <option value="">Selecciona el estado</option>
                <option value="nuevo">Nuevo</option>
                <option value="usado_buen_estado">Usado en buen estado</option>
                <option value="usado_reparacion">Usado para reparación</option>
            </select>
        </div>

        <div class="form-group">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones">${observaciones}</textarea>
        </div>

        ${galeriaHTML}

        <input type="hidden" name="compatibilidades_debug" id="compatibilidades_debug" value='${JSON.stringify(compatibilidades)}'>
    </form>
    `,

        didOpen: () => {
            if (sugerida) {
                document.getElementById("categoria").value = sugerida;
            }
            const selectEstado = document.getElementById("estado_pieza");
            if (estado && selectEstado) {
                selectEstado.value = estado;
            }
            // ✅ Coloca aquí la llamada AJAX para mostrar precios sugeridos
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'obtener_precio_por_sku',
                    sku: codigo
                })
            })
            .then(res => res.json())
            .then(data => {
                const div = document.getElementById("precio-referencia");
                if (data.success && data.data.length > 0) {
                    let contenido = `<strong>Precios encontrados SIN IVA para SKU ${codigo}:</strong><ul class="mt-1 list-disc list-inside">`;
                    data.data.forEach(p => {
                        contenido += `<li><strong>${p.catalogo}:</strong> Proveedor: $${parseFloat(p.precio_proveedor).toFixed(2)}, Público: $${parseFloat(p.precio_publico).toFixed(2)}</li>`;
                    });
                    contenido += `</ul>`;
                    div.innerHTML = contenido;
                    div.style.display = 'block';
                }
            });
        },
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Crear Producto',
        preConfirm: () => {
            const seleccionadas = [...document.querySelectorAll('input[name="galeria[]"]:checked')].map(i => i.value);
            return new Promise(async (resolve, reject) => {
                try {
                    const imagenesComprimidas = [];
                    // Se comprimen todas las imágenes seleccionadas antes de enviarlas
                    for (let url of seleccionadas) {
                        const dataUrlComprimido = await compressImage(url);
                        imagenesComprimidas.push(dataUrlComprimido);
                    }
                    resolve({
                        solicitud_id: solicitudId,
                        sku: document.getElementById('sku').value,
                        nombre: document.getElementById('nombre').value,
                        precio: document.getElementById('precio').value,
                        categoria: document.getElementById('categoria').value,
                        ubicacion: document.getElementById('ubicacion').value,
                        observaciones: document.getElementById('observaciones').value,
                        estado_pieza: document.getElementById('estado_pieza')?.value || '',
                        imagenes: imagenesComprimidas,
                        compatibilidades: compatibilidades
                    });
                } catch (e) {
                    reject(e);
                }
            });
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
            formData.append('estado_pieza', datos.estado_pieza);

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
                    Swal.fire({
                        title: 'Producto creado',
                        html: `
                            <p><strong>SKU:</strong> ${datos.sku}</p>
                            <p>El producto fue creado exitosamente.</p>
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
.form-crear-producto {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    gap: 15px;
    font-size: 14px;
    text-align: justify;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.15);
}

.form-group textarea {
    resize: vertical;
    min-height: 70px;
}

.precio-referencia {
    background-color: #f1f5f9;
    padding: 10px 15px;
    border-radius: 6px;
    font-size: 13px;
    color: #333;
    border-left: 4px solid #0073aa;
    display: none;
}

button.absolute.top-2.right-2.text-white.text-2xl.font-bold.hover\:text-red-300 {
    color: red;
}
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
