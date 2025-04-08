<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual

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

<!-- Tailwind CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<div class="wrap p-6">
    <h2 class="text-2xl font-semibold mb-6">Resumen de la Pieza Seleccionada</h2>

    <!-- Tabla de datos -->
    <div class="overflow-hidden rounded border border-gray-300 bg-white shadow mb-6">
        <table class="w-full text-sm">
            <tbody>
                <tr class="border-b">
                    <th class="px-4 py-2 text-left bg-gray-100 w-1/4 font-medium">Código:</th>
                    <td class="px-4 py-2"><?= esc_html($pieza->codigo) ?></td>
                </tr>
                <tr class="border-b">
                    <th class="px-4 py-2 text-left bg-gray-100">Descripción:</th>
                    <td class="px-4 py-2"><?= esc_html($pieza->descripcion) ?></td>
                </tr>
                <?php if (!empty($pieza->sector)) : ?>
                <tr class="border-b">
                    <th class="px-4 py-2 text-left bg-gray-100">Sector:</th>
                    <td class="px-4 py-2"><?= esc_html($pieza->sector) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th class="px-4 py-2 text-left bg-gray-100">Imagen de Catálogo:</th>
                    <td class="px-4 py-2">
                        <img src="<?= esc_url($imagen_url) ?>" alt="imagen" class="w-32 h-auto rounded shadow">
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Compatibilidades -->
    <h3 class="text-lg font-semibold mb-2">Compatibilidades</h3>
    <?php if ($compatibilidades): ?>
        <ul class="list-disc list-inside mb-6 text-gray-700">
            <?php foreach ($compatibilidades as $c): ?>
                <li><?= esc_html("{$c->marca} {$c->submarca} ({$c->rango})") ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="mb-6 text-gray-500">No hay compatibilidades registradas.</p>
    <?php endif; ?>

    <!-- Formulario -->
    <h3 class="text-lg font-semibold mb-3">Agregar Datos de la Pieza Física</h3>
    <form id="form-solicitud-pieza" enctype="multipart/form-data" class="space-y-4">
        <?php wp_nonce_field('enviar_solicitud_pieza_nonce', 'enviar_solicitud_pieza_nonce_field'); ?>
        <input type="hidden" name="autoparte_id" value="<?= esc_attr($autoparte_id) ?>">

        <div>
            <label class="block text-sm font-medium mb-1">Ubicación Física:</label>
            <select name="ubicacion" required class="w-full p-2 border border-gray-300 rounded">
                <option value="">Selecciona una ubicación</option>
                <?php foreach ($ubicaciones as $u): ?>
                    <option value="<?= esc_attr($u->id) ?>"><?= esc_html($u->nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Observaciones:</label>
            <textarea name="observaciones" rows="4" class="w-full p-2 border border-gray-300 rounded"></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Estado de la Pieza:</label>
            <select name="estado_pieza" required class="w-full p-2 border border-gray-300 rounded">
                <option value="">Selecciona el estado</option>
                <option value="nuevo">Nuevo</option>
                <option value="usado_buen_estado">Usado en buen estado</option>
                <option value="usado_reparacion">Usado para reparación</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Fotos de la Pieza:</label>
            <input type="file" id="input-fotos" accept="image/*" multiple class="w-full">
            <div id="preview-fotos" class="mt-3 flex flex-wrap gap-3"></div>
            <div id="contenedor-archivos"></div>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            Enviar Solicitud para Aprobación
        </button>
    </form>

    <div id="estado-envio" class="mt-6"></div>
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