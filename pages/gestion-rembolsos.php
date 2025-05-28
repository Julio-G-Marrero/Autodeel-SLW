<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php';

wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');

?>
<div class="max-w-7xl mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Gestión de Solicitudes de Reembolso</h2>

    <div class="overflow-x-auto bg-white shadow rounded">
        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-2">Folio</th>
                    <th class="px-4 py-2">Cliente</th>
                    <th class="px-4 py-2">Producto</th>
                    <th class="px-4 py-2">Resolución</th>
                    <th class="px-4 py-2">Monto</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2">Fecha</th>
                    <th class="px-4 py-2 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaRembolsos">
                <tr><td colspan="9" class="text-center py-4">Cargando solicitudes de reembolso...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function cargarRembolsos() {
        $.post(ajaxurl, { action: 'ajax_obtener_rembolsos' }, function(res) {
            const $tabla = $('#tablaRembolsos');

            // Validación correcta según estructura recibida
            const rembolsos = res?.data?.rembolsos;
            if (!Array.isArray(rembolsos) || rembolsos.length === 0) {
                $tabla.html(`
                    <tr>
                        <td colspan="9" class="text-center py-4 text-gray-500">
                            No hay solicitudes de reembolso registradas.
                        </td>
                    </tr>
                `);
                return;
            }

        // Renderizar cada fila
            const html = rembolsos.map(rem => {
                return `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2 font-semibold">#${rem.id}</td>
                        <td class="px-4 py-2">${rem.cliente || '-'}</td>
                        <td class="px-4 py-2">${rem.producto || '-'}</td>
                        <td class="px-4 py-2 capitalize">${rem.resolucion || '-'}</td>
                        <td class="px-4 py-2">$${parseFloat(rem.monto || 0).toFixed(2)}</td>
                        <td class="px-4 py-2 capitalize">${rem.tipo_rembolso || '—'}</td>
                        <td class="px-4 py-2 capitalize">${rem.estado || 'pendiente'}</td>
                        <td class="px-4 py-2">${rem.fecha || '-'}</td>
                        <td class="px-4 py-2 text-center">
                            ${rem.estado === 'resuelto' || rem.estado === 'completado'
                                ? `<button class="bg-gray-700 hover:bg-gray-800 text-white px-3 py-1 text-xs rounded ver-rembolso" data-id="${rem.id}">
                                        Ver Reembolso
                                </button>`
                                : `<button class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 text-xs rounded revisar-rembolso" data-id="${rem.id}">
                                        Gestionar
                                </button>`
                            }
                        </td>
                    </tr>
                `;
            }).join('');

            $tabla.html(html);
        });
    }

    cargarRembolsos();
    $(document).on('click', '.ver-rembolso', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Cargando...',
            didOpen: () => Swal.showLoading()
        });

        $.post(ajaxurl, { action: 'ajax_detalles_rembolso', id }, function (res) {
            if (!res.success) {
                Swal.fire('Error', res.data?.message || 'No se pudo obtener el detalle.', 'error');
                return;
            }

            const r = res.data;

            let html = `
                <div class="text-left text-sm space-y-2">
                    <p><strong>Cliente:</strong> ${r.cliente}</p>
                    <p><strong>Producto:</strong> ${r.producto}</p>
                    <p><strong>SKU:</strong> ${r.sku}</p>
                    <p><strong>Resolución:</strong> ${r.resolucion}</p>
                    <p><strong>Método de pago:</strong> ${r.metodo_pago}</p>
                    <p><strong>Tipo de cliente:</strong> ${r.tipo_cliente}</p>
                    <p><strong>Monto:</strong> $${r.monto.toFixed(2)}</p>
                    <p><strong>Observaciones:</strong> ${r.observaciones || '—'}</p>
            `;

            if (r.estado === 'resuelto' && r.comprobante_url) {
                html += `
                    <label class="block mt-2 mb-1">Comprobante registrado:</label>
                    <div class="bg-gray-100 border p-2 rounded">
                        <a href="${r.comprobante_url}" target="_blank" class="text-blue-600 underline">Ver comprobante</a>
                    </div>
                `;
            }

            html += `</div>`;

            Swal.fire({
                title: `Reembolso #${r.id}`,
                html: html,
                confirmButtonText: 'Cerrar'
            });
        });
    });

    $(document).on('click', '.revisar-rembolso', function () {
        const rembolsoId = $(this).data('id');

        Swal.fire({
            title: 'Cargando...',
            didOpen: () => Swal.showLoading()
        });

        $.post(ajaxurl, {
            action: 'ajax_detalles_rembolso',
            id: rembolsoId
        }, function (res) {
            if (!res.success) {
                Swal.fire('Error', res.data?.message || 'No se pudieron obtener los datos.', 'error');
                return;
            }

            const r = res.data;
            console.log(r)
            let html = `
                <div class="text-left text-sm space-y-2">
                    <p><strong>Cliente:</strong> ${r.cliente}</p>
                    <p><strong>Producto:</strong> ${r.producto}</p>
                    <p><strong>SKU:</strong> ${r.sku}</p>
                    <p><strong>Resolución:</strong> ${r.resolucion}</p>
                    <p><strong>Método de pago:</strong> ${r.metodo_pago}</p>
                    <p><strong>Tipo de cliente:</strong> ${r.tipo_cliente}</p>
            `;

            if (r.metodo_pago === 'credito_cliente') {
                if (!r.cuenta_id) {
                    Swal.fire('⚠️ Cuenta no encontrada', 'No se encontró una cuenta por cobrar asociada al pedido o venta.', 'warning');
                    return;
                }

                html += `
                    <p><strong>Monto pendiente en cuenta:</strong> $${parseFloat(r.monto_pendiente || 0).toFixed(2)}</p>
                    <label class="block mt-2 mb-1">Acción:</label>
                    <div class="flex items-center space-x-2 mt-3">
                        <input type="checkbox" id="liquidar_cuenta" class="h-4 w-4 text-green-600">
                        <label for="liquidar_cuenta" class="text-sm">Liquidar cuenta</label>
                    </div>
                `;
            } else {
                html += `
                    <label class="block mt-2 mb-1">Comprobante de reembolso (PDF o imagen):</label>
                    <input type="file" id="comprobante_reembolso" class="swal2-file" accept="application/pdf,image/*">
                `;
            }

            html += `
                <label class="block mt-3 mb-1">Observaciones:</label>
                <textarea id="observaciones_rembolso" class="swal2-textarea w-full text-sm" placeholder="Detalles u observaciones..."></textarea>
                </div>
            `;

            Swal.fire({
                title: `Gestionar Reembolso #${r.id}`,
                html: html,
                width: 600,
                showCancelButton: true,
                confirmButtonText: 'Guardar resolución',
                cancelButtonText: 'Cancelar',
                focusConfirm: false,
                preConfirm: () => {
                    const observaciones = $('#observaciones_rembolso').val().trim();

                    // Flujo para crédito
                    if (r.metodo_pago === 'credito_cliente') {
                        Swal.close();

                        setTimeout(() => {
                            if (!r.cuenta_id) {
                                Swal.fire('Error', 'No se encontró la cuenta por cobrar asociada.', 'error');
                                return;
                            }

                            const accion = $('#accion_credito').val() || 'liquidar';

                            $.post(ajaxurl, {
                                action: 'ajax_guardar_resolucion_rembolso',
                                id: r.id,
                                observaciones: 'Pago automático desde módulo de reembolsos',
                                accion_credito: accion
                            }, function (resp) {
                                if (resp.success) {
                                    Swal.fire('✅ Reembolso gestionado', 'La cuenta fue actualizada y el reembolso registrado.', 'success')
                                        .then(() => location.reload());
                                } else {
                                    Swal.fire('Error', resp.data?.message || 'No se pudo actualizar el reembolso.', 'error');
                                }
                            });
                        }, 300);

                        return false;
                    }

                    // Flujo para efectivo/tarjeta/transferencia
                    const file = $('#comprobante_reembolso')[0]?.files?.[0];
                    if (!file) {
                        Swal.showValidationMessage('Debes subir un comprobante.');
                        return false;
                    }

                    const data = new FormData();
                    data.append('action', 'ajax_guardar_resolucion_rembolso');
                    data.append('id', r.id);
                    data.append('observaciones', observaciones);
                    data.append('comprobante', file);

                    return fetch(ajaxurl, {
                        method: 'POST',
                        body: data
                    })
                    .then(res => res.json())
                    .then(json => {
                        if (!json.success) throw new Error(json.data?.message || 'No se pudo guardar.');
                        return json;
                    })
                    .catch(err => {
                        Swal.showValidationMessage(err.message);
                    });
                }
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire('✅ Reembolso gestionado', 'La resolución ha sido registrada.', 'success')
                        .then(() => location.reload());
                }
            });
        });
    });
});
</script>
