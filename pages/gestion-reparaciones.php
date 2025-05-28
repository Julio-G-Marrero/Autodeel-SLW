<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php';
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-7xl mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6">Gestión de Reparaciones de Autopartes</h2>

    <!-- Filtros -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <input type="text" id="filtroSKU" placeholder="Buscar por SKU..." class="border px-3 py-2 rounded w-full">
        <input type="text" id="filtroNombre" placeholder="Buscar por nombre..." class="border px-3 py-2 rounded w-full">
        <select id="filtroEstado" class="border px-3 py-2 rounded w-full">
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendiente</option>
            <option value="reparado">Reparado</option>
        </select>
    </div>

    <button id="btnBuscarReparaciones" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition mb-4">
        Buscar
    </button>

    <!-- Tabla de reparaciones -->
    <div class="overflow-x-auto bg-white shadow rounded">
        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-2">Folio</th>
                    <th class="px-4 py-2">SKU</th>
                    <th class="px-4 py-2">Nombre</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaReparaciones">
                <tr><td colspan="6" class="text-center py-4">Cargando reparaciones...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function cargarReparaciones() {
        const sku = $('#filtroSKU').val();
        const nombre = $('#filtroNombre').val();
        const estado = $('#filtroEstado').val();

        $.post(ajaxurl, {
            action: 'ajax_obtener_reparaciones',
            sku, nombre, estado
        }, function(res) {
            const $tabla = $('#tablaReparaciones');
            if (!res.success || res.data.length === 0) {
                $tabla.html('<tr><td colspan="6" class="text-center py-4">No hay reparaciones registradas.</td></tr>');
                return;
            }

            let html = '';

            (res.data.reparaciones || []).forEach(rep => {
                html += `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-semibold">#${rep.id}</td>
                        <td class="px-4 py-2">${rep.sku}</td>
                        <td class="px-4 py-2">${rep.nombre}</td>
                        <td class="px-4 py-2 capitalize">${rep.estado}</td>
                        <td class="px-4 py-2 text-center">
                            ${rep.estado === 'pendiente' ? `
                                <button class="bg-yellow-600 text-white text-xs px-3 py-1 rounded gestionar-reparacion" data-id="${rep.id}">
                                    Gestionar
                                </button>` : '<em class="text-gray-500">Completado</em>'}
                        </td>
                    </tr>`;
            });

            $tabla.html(html || `<tr><td colspan="5" class="text-center py-4">No hay reparaciones registradas.</td></tr>`);

        });
    }

      $(document).on('click', '.gestionar-reparacion', function () {
        const id = $(this).data('id');
        if (!id) return;

        Swal.fire({
            title: 'Cargando...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.post(ajaxurl, {
            action: 'ajax_obtener_detalle_reparacion',
            id
        }, function (res) {
            Swal.close();

            if (!res.success) {
                Swal.fire('Error', res.data?.message || 'No se pudo obtener la información.', 'error');
                return;
            }

            const d = res.data;
            let evidenciasHTML = '';
            if (Array.isArray(d.evidencias) && d.evidencias.length > 0) {
                evidenciasHTML = d.evidencias.map(url => `
                    <a href="${url}" target="_blank">
                        <img src="${url}" alt="evidencia" class="w-20 h-20 object-cover rounded border">
                    </a>
                `).join('');
            } else {
                evidenciasHTML = '<em>Sin evidencia</em>';
            }

            const contenido = `
                <form id="formReparacionFinal">
                    <div class="text-left text-sm leading-relaxed">
                        <div class="mb-3">
                            <strong>Producto:</strong> ${d.nombre}<br>
                            <strong>SKU:</strong> ${d.sku}
                        </div>

                        <div class="mb-3">
                            <p class="font-semibold">📝 Notas de devolución:</p>
                            <p class="italic text-gray-700">${d.notas_devolucion || '—'}</p>
                        </div>

                        <div class="mb-3">
                            <p class="font-semibold">📎 Evidencias:</p>
                            <div class="flex gap-2 flex-wrap">${evidenciasHTML}</div>
                        </div>

                        <div class="mb-3">
                            <label class="block font-semibold mb-1">📸 Imagen de la reparación:</label>
                            <input type="file" name="imagen_reparacion" accept="image/*" class="swal2-file w-full" required>
                        </div>
                    </div>
                </form>
            `;

            Swal.fire({
                title: 'Confirmar reparación',
                html: contenido,
                width: 700,
                showCancelButton: true,
                confirmButtonText: 'Marcar como reparado',
                cancelButtonText: 'Cancelar',
                didOpen: () => {
                    const fileInput = Swal.getPopup().querySelector('input[type="file"]');
                    fileInput.addEventListener('change', () => {
                        // Previsualización opcional aquí
                    });
                },
                preConfirm: () => {
                    const form = Swal.getPopup().querySelector('#formReparacionFinal');
                    const archivo = form.querySelector('input[type="file"]').files[0];

                    if (!archivo) {
                        Swal.showValidationMessage('Debes subir una imagen de la reparación.');
                        return false;
                    }
                    return { archivo };
                }
            }).then(result => {
                if (!result.isConfirmed) return;

                const formData = new FormData();
                formData.append('action', 'ajax_marcar_reparacion_completa');
                formData.append('id', id);
                formData.append('imagen_reparacion', result.value.archivo);

                Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('✅ Reparación completada', '', 'success').then(cargarReparaciones);
                    } else {
                        Swal.fire('Error', res.data?.message || 'No se pudo completar.', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Ocurrió un error al subir la imagen.', 'error');
                });
            });
        });
    });

    $(document).on('click', '.marcar-reparado', function () {
        const id = $(this).data('id');
        if (!id) return;

        Swal.fire({
            title: '¿Confirmar reparación?',
            text: 'Esto marcará la pieza como reparada.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;

            $.post(ajaxurl, {
                action: 'ajax_marcar_reparacion_completa',
                id
            }, function(res) {
                if (res.success) {
                    Swal.fire('✅ Reparación completada', '', 'success').then(cargarReparaciones);
                } else {
                    Swal.fire('Error', res.data?.message || 'No se pudo completar la operación.', 'error');
                }
            });
        });
    });

    $('#btnBuscarReparaciones').on('click', cargarReparaciones);
    cargarReparaciones();
});
</script>
