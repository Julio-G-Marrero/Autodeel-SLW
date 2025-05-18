<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php';
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-7xl mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6">Gestión de Devoluciones</h2>

    <!-- Filtros -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <input type="text" id="filtroClienteDev" placeholder="Buscar cliente..." class="border px-3 py-2 rounded w-full">
        <select id="filtroEstadoDev" class="border px-3 py-2 rounded w-full">
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendiente</option>
            <option value="en_revision">En revisión</option>
            <option value="resuelto">Resuelto</option>
            <option value="rechazado">Rechazado</option>
        </select>
        <input type="date" id="filtroDesdeDev" class="border px-3 py-2 rounded w-full">
        <input type="date" id="filtroHastaDev" class="border px-3 py-2 rounded w-full">
    </div>

    <button id="btnBuscarDevoluciones" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition mb-4">
        Buscar
    </button>

    <!-- Tabla de devoluciones -->
    <div class="overflow-x-auto bg-white shadow rounded">
        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-2">Folio</th>
                    <th class="px-4 py-2">Cliente</th>
                    <th class="px-4 py-2">Producto</th>
                    <th class="px-4 py-2">Motivo</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2">Fecha</th>
                    <th class="px-4 py-2 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaDevoluciones">
                <tr><td colspan="7" class="text-center py-4">Cargando devoluciones...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Este script se agrega al final del archivo de gestión de devoluciones
jQuery(document).ready(function($) {
    // Ya existe cargarDevoluciones arriba

    // Revisar solicitud
    $(document).on('click', '.ver-devolucion', function () {
        const devolucionId = $(this).data('id');

        if (!devolucionId || isNaN(devolucionId)) {
            Swal.fire('Error', 'ID de devolución no válido.', 'error');
            return;
        }

        Swal.fire({
            title: 'Cargando...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.post(ajaxurl, {
            action: 'ajax_obtener_detalle_devolucion',
            devolucion_id: devolucionId
        }, function (res) {
            Swal.close();
            if (!res.success) {
                Swal.fire('Error', res.data?.message || 'No se pudo obtener la información.', 'error');
                return;
            }

            const d = res.data;
            const evidencias = d.evidencias.map(url => `<a href="${url}" target="_blank" class="text-blue-600 underline block text-sm">Ver archivo</a>`).join('') || '<em>Sin archivos</em>';

            Swal.fire({
                title: `Revisión Devolución #${d.id}`,
                html: `
                    <div class="text-left text-sm leading-relaxed">
                        <p class="mb-2"><strong>Cliente:</strong> ${d.cliente}</p>
                        <p class="mb-2"><strong>Producto:</strong> ${d.producto}</p>
                        <p class="mb-2"><strong>Motivo:</strong> ${d.motivo}</p>

                        <div class="my-2">
                            <p class="font-semibold mb-1">📎 Evidencias:</p>
                            <div class="grid grid-cols-2 gap-2">${evidencias}</div>
                        </div>

                        <div class="mt-4">
                            <label for="resolucion" class="block font-medium mb-1">Resolución:</label>
                            <select id="resolucion" class="swal2-select w-full text-sm p-0 m-0">
                                <option value="">Selecciona una opción</option>
                                <option value="reintegrado">Reintegrar al inventario</option>
                                <option value="reparacion">Enviar a reparación</option>
                                <option value="baja_definitiva">Dar de baja</option>
                            </select>
                        </div>

                        <div class="mt-3">
                            <label for="notas_revision" class="block font-medium mb-1">Notas del técnico:</label>
                            <textarea id="notas_revision" class="swal2-textarea w-full text-sm p-0 m-0" rows="3" placeholder="Detalles de la revisión..."></textarea>
                        </div>
                    </div>
                `,
                width: 650,
                confirmButtonText: 'Guardar resolución',
                showCancelButton: true,
                focusConfirm: false,
                customClass: {
                    confirmButton: 'bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-white',
                    cancelButton: 'bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded text-black ml-2'
                },
                preConfirm: () => {
                    const resolucion = $('#resolucion').val();
                    const notas = $('#notas_revision').val().trim();

                    if (!resolucion || !notas) {
                        Swal.showValidationMessage('Selecciona una resolución y escribe las notas.');
                        return false;
                    }

                    return { resolucion, notas };
                }
            }).then(result => {
                if (!result.isConfirmed) return;

                Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });

                $.post(ajaxurl, {
                    action: 'ajax_guardar_resolucion_devolucion',
                    devolucion_id: devolucionId,
                    resolucion: result.value.resolucion,
                    notas: result.value.notas
                }, function (res2) {
                    if (res2.success) {
                        Swal.fire('✅ Resolución guardada', '', 'success').then(cargarDevoluciones);
                    } else {
                        Swal.fire('Error', res2.data?.message || 'No se pudo guardar.', 'error');
                    }
                });
            });

        });
    });
});
jQuery(document).ready(function($) {
    function cargarDevoluciones() {
        const cliente = $('#filtroClienteDev').val();
        const estado = $('#filtroEstadoDev').val();
        const desde = $('#filtroDesdeDev').val();
        const hasta = $('#filtroHastaDev').val();

        $.post(ajaxurl, {
            action: 'ajax_obtener_devoluciones_admin',
            cliente, estado, desde, hasta
        }, function(res) {
            const $tabla = $('#tablaDevoluciones');
            if (!res.success || res.data.devoluciones.length === 0) {
                $tabla.html('<tr><td colspan="7" class="text-center py-4">No hay devoluciones registradas.</td></tr>');
                return;
            }

            let html = '';
            res.data.devoluciones.forEach(dev => {
                html += `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-semibold">#${dev.id}</td>
                        <td class="px-4 py-2">${dev.cliente || 'Sin nombre'}</td>
                        <td class="px-4 py-2">${dev.producto}</td>
                        <td class="px-4 py-2">${dev.motivo}</td>
                        <td class="px-4 py-2 capitalize">${dev.estado}</td>
                        <td class="px-4 py-2">${dev.fecha}</td>
                        <td class="px-4 py-2 text-center">
                            <button class="bg-blue-600 text-white text-xs px-3 py-1 rounded ver-devolucion" data-id="${dev.id}">
                                Revisar
                            </button>
                        </td>
                    </tr>`;
            });
            $tabla.html(html);
        });

    }

    $('#btnBuscarDevoluciones').on('click', cargarDevoluciones);
    cargarDevoluciones();
});
</script>
