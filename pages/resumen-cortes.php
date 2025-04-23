<?php
if (!defined('ABSPATH')) exit;

wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-7xl mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6">📅 Resumen de Cortes de Caja</h2>

    <!-- Filtros -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <input type="date" id="filtroDesdeCorte" class="border px-3 py-2 rounded w-full">
        <input type="date" id="filtroHastaCorte" class="border px-3 py-2 rounded w-full">
        <select id="filtroEstadoCorte" class="border px-3 py-2 rounded w-full">
            <option value="">Todos los estados</option>
            <option value="abierta">Abierta</option>
            <option value="cerrada">Cerrada</option>
        </select>
    </div>

    <div class="overflow-x-auto bg-white shadow rounded">
        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-100 text-gray-700">
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
            </thead>
            <tbody id="tablaCortesCaja">
                <tr><td colspan="7" class="text-center py-4">Cargando cortes...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function cargarCortes() {
        const desde = $('#filtroDesdeCorte').val();
        const hasta = $('#filtroHastaCorte').val();
        const estado = $('#filtroEstadoCorte').val();

        $.post(ajaxurl, {
            action: 'ajax_obtener_resumen_cortes',
            desde, hasta, estado
        }, function(res) {
            if (!res.success || !res.data || !Array.isArray(res.data.cortes) || res.data.cortes.length === 0) {
                $('#tablaCortesCaja').html('<tr><td colspan="7" class="text-center py-4">No se encontraron cortes.</td></tr>');
                return;
            }

            let html = '';
            res.data.cortes.forEach(corte => {
                const estadoColor = corte.estado === 'abierta' ? 'text-blue-600' : 'text-green-600';
                let botonTicket = '-';
                if (corte.estado === 'cerrada') {
                    botonTicket = `<button data-id="${corte.id}" class="text-xs px-3 py-1 bg-blue-600 text-white rounded btn-ver-ticket-corte">🎟️ Ver Ticket</button>`;
                }

                let botonVoBo = '-';
                if (corte.estado === 'cerrada') {
                    if (corte.vobo_aprobado == 1) {
                        botonVoBo = `
                            <div class="text-green-600 font-semibold">
                                Autorizado<br>
                                <small class="text-gray-600">por ${corte.vobo_por || 'N/A'}<br>${corte.vobo_fecha || ''}</small><br>
                                <button data-id="${corte.id}" class="mt-1 text-xs text-red-600 underline btn-revertir-vobo">❌ Revertir</button>
                            </div>`;
                    } else {
                        botonVoBo = `<button data-id="${corte.id}" class="text-xs px-3 py-1 bg-yellow-500 text-white rounded btn-vobo">Dar V°B°</button>`;
                    }
                }

                html += `
                    <tr class="border-b">
                        <td class="px-4 py-2 font-semibold">#${corte.id}</td>
                        <td class="px-4 py-2">${corte.usuario}</td>
                        <td class="px-4 py-2">${corte.fecha_apertura}</td>
                        <td class="px-4 py-2">${corte.fecha_cierre || '-'}</td>
                        <td class="px-4 py-2">$${corte.total_cierre}</td>
                        <td class="px-4 py-2 capitalize ${estadoColor}">${corte.estado}</td>
                        <td class="px-4 py-2">${botonVoBo}</td>
                        <td class="px-4 py-2">${botonTicket}</td>
                    </tr>
                `;
            });

            $('#tablaCortesCaja').html(html);
            $('#paginaActualCorte').text(res.data.pagina_actual || 1); // opcional si usas paginación
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
            mostrarTicketCierreCaja(res.data.resumen, res.data.denominaciones, res.data.usuario);
        });
    });

    function mostrarTicketCierreCaja(resumen, denominaciones, usuario) {
        const denominacionesTexto = Object.entries(denominaciones).map(([denom, cantidad]) => {
            return `<tr>
                <td class="border px-2 py-1 text-right">$${parseFloat(denom).toFixed(2)}</td>
                <td class="border px-2 py-1 text-center">${cantidad}</td>
                <td class="border px-2 py-1 text-right">$${(parseFloat(denom) * cantidad).toFixed(2)}</td>
            </tr>`;
        }).join('');

        const html = `
            <div id="ticketCierreCaja" class="p-6 max-w-md mx-auto bg-white border border-gray-300 rounded shadow text-sm font-mono">
                <h2 class="text-xl font-bold text-center mb-2">🧾 Corte de Caja</h2>
                <p><strong>Folio:</strong> #${resumen.id}</p>
                <p><strong>Usuario:</strong> ${usuario}</p>
                <p><strong>Apertura:</strong> ${resumen.fecha_apertura}</p>
                <p><strong>Cierre:</strong> ${resumen.fecha_cierre}</p>
                <hr class="my-2" />
                <p><strong>Monto Inicial:</strong> $${resumen.monto_inicial.toFixed(2)}</p>
                <p><strong>Ventas en Efectivo:</strong> $${resumen.ventas_efectivo.toFixed(2)}</p>
                <p><strong>Total Declarado:</strong> $${resumen.monto_cierre.toFixed(2)}</p>
                <p><strong>Diferencia:</strong> <span class="${resumen.diferencia < 0 ? 'text-red-600' : 'text-green-600'}">$${resumen.diferencia.toFixed(2)}</span></p>
                <hr class="my-2" />
                <p class="font-bold">🧮 Desglose de Billetes:</p>
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
                    <button onclick="window.print()" class="bg-black text-white px-4 py-1 rounded text-sm">🖨️ Imprimir Ticket</button>
                </div>
            </div>
        `;

        Swal.fire({
            title: '🧾 Cierre de Caja Completado',
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
                        Swal.fire('✅ Revertido', 'Se quitó la autorización correctamente.', 'success');
                        cargarCortes();
                    } else {
                        Swal.fire('Error', res.data?.message || 'No se pudo revertir el V°B°', 'error');
                    }
                });
            }
        });
    });

    $('#filtroDesdeCorte, #filtroHastaCorte, #filtroEstadoCorte').on('change', cargarCortes);

    cargarCortes();
});
</script>
