<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-7xl mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6">Resumen de Cortes de Caja</h2>

    <!-- Filtros -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <input type="date" id="filtroDesdeCorte" class="border px-3 py-2 rounded w-full">
        <input type="date" id="filtroHastaCorte" class="border px-3 py-2 rounded w-full">
        <select id="filtroEstadoCorte" class="border px-3 py-2 rounded w-full">
            <option value="">Todos los estados</option>
            <option value="abierta">Abierta</option>
            <option value="cerrada">Cerrada</option>
        </select>
        <button id="btnBuscarCortes" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            🔍 Buscar
        </button>
    </div>


    <div class="overflow-x-auto bg-white shadow rounded">
        <table class="min-w-full text-sm text-left">
            <!-- <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-2">Folio</th>
                    <th class="px-4 py-2">Usuario</th>
                    <th class="px-4 py-2">Apertura</th>
                    <th class="px-4 py-2">Cierre</th>
                    <th class="px-4 py-2">Total Cierre</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2">V°B°</th>
                    <th class="px-4 py-2">Acciones</th>
                </tr>
            </thead> -->
            <tbody id="tablaCortesCaja">
                <tr><td colspan="7" class="text-center py-4">Cargando cortes...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function cargarCortes() {
        const $btn = $('#btnBuscarCortes');
        $btn.prop('disabled', true).text('Buscando...');

        const desde = $('#filtroDesdeCorte').val();
        const hasta = $('#filtroHastaCorte').val();
        const estado = $('#filtroEstadoCorte').val();

        $.post(ajaxurl, {
            action: 'ajax_obtener_resumen_cortes',
            desde, hasta, estado
        }, function(res) {
            const $tabla = $('#tablaCortesCaja');

            if (!res.success || !res.data || !Array.isArray(res.data.cortes) || res.data.cortes.length === 0) {
                $tabla.html('<tr><td colspan="10" class="text-center py-4">No se encontraron cortes.</td></tr>');
            } else {
                let html = '';
                res.data.cortes.forEach(corte => {
                    const estadoColor = corte.estado === 'abierta' ? 'text-yellow-600' : 'text-green-600';
                    const diferenciaColor = corte.diferencia < 0 ? 'text-red-600' : 'text-green-600';
                    const voboLabel = corte.vobo_aprobado === 1
                        ? `<span class="text-green-600 font-semibold">✔ Autorizado</span>
                        <button data-id="${corte.id}" class="btn-revertir-vobo text-xs text-red-600 hover:underline ml-2">Revertir</button>`
                        : `<button data-id="${corte.id}" class="btn-vobo text-blue-600 hover:underline text-xs">Autorizar</button>`;

                    html += `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2 font-mono">#${corte.id}</td>
                            <td class="px-4 py-2">${corte.usuario}</td>
                            <td class="px-4 py-2">${corte.fecha_apertura}</td>
                            <td class="px-4 py-2">${corte.fecha_cierre || '-'}</td>
                            <td class="px-4 py-2 text-gray-800">$${parseFloat(corte.total_teorico).toFixed(2)}</td>
                            <td class="px-4 py-2 text-blue-700 font-semibold">$${parseFloat(corte.total_cierre).toFixed(2)}</td>
                            <td class="px-4 py-2 ${diferenciaColor} font-semibold">$${parseFloat(corte.diferencia).toFixed(2)}</td>
                            <td class="px-4 py-2 ${estadoColor} capitalize">${corte.estado}</td>
                            <td class="px-4 py-2">${voboLabel}</td>
                            <td class="px-4 py-2">
                                <button data-id="${corte.id}" class="btn-ver-ticket-corte text-blue-600 hover:underline text-xs">
                                    Ver Ticket
                                </button>
                            </td>
                        </tr>
                    `;
                });
                $tabla.html(html);
            }

            $btn.prop('disabled', false).text('Buscar');
        }).fail(() => {
            Swal.fire('Error', 'No se pudo cargar la información.', 'error');
            $btn.prop('disabled', false).text('Buscar');
        });
    }



    $(document).on('click', '.btn-ver-ticket-corte', function () {
        const corteId = $(this).data('id');

        Swal.fire({
            title: 'Cargando ticket...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.post(ajaxurl, {
            action: 'ajax_obtener_ticket_corte',
            corte_id: corteId
        }, function (res) {
            if (!res.success) {
                Swal.fire('Error', res.data?.message || 'No se pudo cargar el ticket.', 'error');
                return;
            }

            // Cerrar la alerta de carga
            Swal.close();

            // Mostrar el ticket correctamente
            mostrarTicketCierreCaja(
                res.data.resumen,
                res.data.denominaciones,
                res.data.usuario,
                res.data.movimientos_detalle
            );
        });
    });

    function mostrarTicketCierreCaja(resumen, denominaciones, usuario, detalle = {}) {
        const denominacionesTexto = Object.entries(denominaciones).map(([denom, cantidad]) => {
            return `<tr>
                <td class="border px-2 py-1 text-right">$${parseFloat(denom).toFixed(2)}</td>
                <td class="border px-2 py-1 text-center">${cantidad}</td>
                <td class="border px-2 py-1 text-right">$${(parseFloat(denom) * cantidad).toFixed(2)}</td>
            </tr>`;
        }).join('');

        function renderDetalleMetodo(titulo, lista) {
            if (!lista || lista.length === 0) return '';
            
            const filas = lista.map(item => `
                <tr>
                    <td class="border px-2 py-1 text-right">$${parseFloat(item.monto).toFixed(2)}</td>
                    <td class="border px-2 py-1 text-left">${item.referencia || '—'}</td>
                </tr>
            `).join('');

            return `
                <div class="mt-4">
                    <p class="font-bold">${titulo}:</p>
                    <table class="w-full text-xs border mt-1 mb-2">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border px-2 py-1 text-right">Monto</th>
                                <th class="border px-2 py-1 text-left">Referencia</th>
                            </tr>
                        </thead>
                        <tbody>${filas}</tbody>
                    </table>
                </div>
            `;
        }

        const html = `
            <div id="ticketCierreCaja" class="p-6 max-w-md mx-auto bg-white border border-gray-300 rounded shadow text-sm font-mono">
                <h2 class="text-xl font-bold text-center mb-2">Corte de Caja</h2>
                <p><strong>Folio:</strong> #${resumen.id}</p>
                <p><strong>Usuario:</strong> ${usuario}</p>
                <p><strong>Apertura:</strong> ${resumen.fecha_apertura}</p>
                <p><strong>Cierre:</strong> ${resumen.fecha_cierre}</p>
                <hr class="my-2" />
                <p><strong>Monto Inicial:</strong> $${resumen.monto_inicial.toFixed(2)}</p>
                <p><strong>Ventas en Efectivo:</strong> $${resumen.ventas_efectivo.toFixed(2)}</p>
                <p><strong>Ventas con Tarjeta:</strong> $${resumen.ventas_tarjeta.toFixed(2)}</p>
                <p><strong>Ventas por Transferencia:</strong> $${resumen.ventas_transferencia.toFixed(2)}</p>
                <p><strong>Abonos a CxC:</strong> $${resumen.abonos_cxc.toFixed(2)}</p>
                <p><strong>Total Declarado:</strong> $${resumen.monto_cierre.toFixed(2)}</p>
                <p><strong>Diferencia:</strong> 
                    <span class="${resumen.diferencia < 0 ? 'text-red-600' : 'text-green-600'}">
                        $${resumen.diferencia.toFixed(2)}
                    </span>
                </p>
                ${renderDetalleMetodo('Detalle Efectivo', detalle.efectivo)}
                ${renderDetalleMetodo('Detalle Tarjeta', detalle.tarjeta)}
                ${renderDetalleMetodo('Detalle Transferencia', detalle.transferencia)}
                ${renderDetalleMetodo('Detalle Abonos CxC', detalle.cxc)}
                <hr class="my-2" />
                <p class="font-bold">Desglose de Billetes:</p>
                <table class="w-full mt-2 border text-xs">
                    <thead>
                        <tr>
                            <th class="border px-2 py-1">Denom</th>
                            <th class="border px-2 py-1">Cantidad</th>
                            <th class="border px-2 py-1">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>${denominacionesTexto || `<tr><td colspan="3" class="text-center py-2">Sin desglose</td></tr>`}</tbody>
                </table>
                <div class="text-center mt-4 no-print">
                    <button onclick="window.print()" class="bg-black text-white px-4 py-1 rounded text-sm">Imprimir Ticket</button>
                </div>
            </div>
        `;

        Swal.fire({
            title: 'Cierre de Caja Completado',
            html: html,
            width: 600,
            showConfirmButton: false
        });
    }

    $(document).on('click', '.btn-vobo', function() {
        const corteId = $(this).data('id');

        Swal.fire({
            title: '¿Autorizar cierre?',
            text: 'Esta acción confirmará que el efectivo del sobre fue verificado.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, autorizar V°B°'
        }).then(res => {
            if (!res.isConfirmed) return;

            $.post(ajaxurl, {
                action: 'ajax_autorizar_vobo_corte',
                corte_id: corteId
            }, function(r) {
                if (r.success) {
                    Swal.fire('✅ Autorizado', 'El corte fue marcado como verificado.', 'success');
                    cargarCortes();
                } else {
                    Swal.fire('Error', r.data?.message || 'No se pudo autorizar.', 'error');
                }
            });
        });
    });

    $(document).on('click', '.btn-revertir-vobo', function () {
        const corteId = $(this).data('id');

        Swal.fire({
            title: '¿Revertir V°B°?',
            text: 'Esto eliminará la autorización del corte.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, revertir',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                $.post(ajaxurl, {
                    action: 'ajax_revertir_vobo_corte',
                    corte_id: corteId
                }, function (res) {
                    if (res.success) {
                        Swal.fire('Revertido', 'Se quitó la autorización correctamente.', 'success');
                        cargarCortes();
                    } else {
                        Swal.fire('Error', res.data?.message || 'No se pudo revertir el V°B°', 'error');
                    }
                });
            }
        });
    });

    $('#btnBuscarCortes').on('click', function () {
        cargarCortes();
    });

    cargarCortes();
});
</script>
