<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-7xl mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6">🧾 Resumen de Ventas</h2>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <input type="text" id="filtroBusqueda" placeholder="Buscar por cliente o folio..." class="border px-3 py-2 rounded w-full">
        <input type="date" id="filtroDesdeVenta" class="border px-3 py-2 rounded w-full">
        <input type="date" id="filtroHastaVenta" class="border px-3 py-2 rounded w-full">
        <select id="filtroMetodoPago" class="border px-3 py-2 rounded w-full">
            <option value="">Todos los métodos</option>
            <option value="efectivo">Efectivo</option>
            <option value="tarjeta">Tarjeta</option>
            <option value="transferencia">Transferencia</option>
            <option value="credito">Crédito</option>
        </select>
    </div>

    <div class="overflow-x-auto bg-white shadow rounded">
        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-2">Folio</th>
                    <th class="px-4 py-2">Cliente</th>
                    <th class="px-4 py-2">Total</th>
                    <th class="px-4 py-2">Pago</th>
                    <th class="px-4 py-2">Fecha</th>
                    <th class="px-4 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaVentasAutopartes">
                <tr><td colspan="6" class="text-center py-4">Cargando ventas...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-center">
        <button id="btnAnteriorVenta" class="px-4 py-2 bg-gray-200 rounded">Anterior</button>
        <span id="paginaActualVenta" class="mx-2 font-semibold">1</span>
        <button id="btnSiguienteVenta" class="px-4 py-2 bg-gray-200 rounded">Siguiente</button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let paginaVenta = 1;

    function cargarVentas() {
        const busqueda = $('#filtroBusqueda').val();
        const desde = $('#filtroDesdeVenta').val();
        const hasta = $('#filtroHastaVenta').val();
        const metodo = $('#filtroMetodoPago').val();

        $.post(ajaxurl, {
            action: 'ajax_obtener_resumen_ventas',
            busqueda, desde, hasta, metodo, pagina: paginaVenta
        }, function(res) {
            if (!res.success || res.data.ventas.length === 0) {
                $('#tablaVentasAutopartes').html('<tr><td colspan="6" class="text-center py-4">No hay ventas registradas</td></tr>');
                return;
            }

            let html = '';
            res.data.ventas.forEach(v => {
                html += `
                    <tr class="border-b">
                        <td class="px-4 py-2 font-semibold">#${v.id}</td>
                        <td class="px-4 py-2">${v.cliente}</td>
                        <td class="px-4 py-2">$${v.total}</td>
                        <td class="px-4 py-2">${v.metodo}</td>
                        <td class="px-4 py-2">${v.fecha}</td>
                        <td class="px-4 py-2">
                            <button data-id="${v.id}" class="bg-blue-600 text-white text-xs px-3 py-1 rounded ver-ticket">
                                🧾 Ver Ticket
                            </button>
                        </td>
                    </tr>`;
            });
            $('#tablaVentasAutopartes').html(html);
            $('#paginaActualVenta').text(paginaVenta);
        });
    }

    $(document).on('click', '.ver-ticket', function() {
        const ventaId = $(this).data('id');
        Swal.fire('🔍', 'Aquí podrías cargar el detalle de la venta #'+ventaId, 'info');
        // Puedes usar un fetch/$.post aquí para obtener y mostrar el ticket en un popup
    });

    $(document).on('click', '.ver-ticket', function () {
        const ventaId = $(this).data('id');

        Swal.fire({
            title: 'Cargando ticket...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.post(ajaxurl, {
            action: 'ajax_obtener_ticket_venta',
            venta_id: ventaId
        }, function (res) {
            if (!res.success) {
                Swal.fire('Error', res.data?.message || 'No se pudo cargar el ticket.', 'error');
                return;
            }

            const ticketHTML = generarTicketVentaHTML(res.data);
            Swal.fire({
                title: '🎟️ Ticket de Venta',
                html: ticketHTML,
                showConfirmButton: false,
                width: 600,
                didOpen: () => {
                    document.getElementById('btnImprimirTicketVenta').addEventListener('click', () => {
                        const contenido = document.getElementById('ticketVentaContenido').innerHTML;
                        const ventana = window.open('', '', 'width=400,height=600');
                        ventana.document.write(`<html><head><title>Ticket</title><style>
                            body{font-family:monospace;padding:10px;}
                            table{width:100%;border-collapse:collapse;}
                            td{padding:4px;text-align:left;}
                            .total{font-weight:bold;font-size:1.1em;text-align:right;}
                            @media print {
                                #btnImprimirTicketVenta { display: none; }
                            }
                        </style></head><body>${contenido}</body></html>`);
                        ventana.document.close();
                        ventana.print();
                    });
                }
            });
        });
    });

    function generarTicketVentaHTML({ cliente, productos, total, metodo, folio }) {
        const fecha = new Date().toLocaleString();
        const filas = productos.map(p => `
            <tr>
                <td>${p.nombre}</td>
                <td style="text-align:right;">$${p.precio.toFixed(2)}</td>
            </tr>
        `).join('');

        return `
            <div id="ticketVentaContenido" style="font-family:monospace;">
                <h2 style="text-align:center;">🧾 Ticket de Venta</h2>
                <p><strong>Folio:</strong> #${folio}</p>
                <p><strong>Fecha:</strong> ${fecha}</p>
                <p><strong>Cliente:</strong> ${cliente}</p>
                <p><strong>Método:</strong> ${metodo}</p>
                <hr/>
                <table style="width:100%;">${filas}</table>
                <hr/>
                <p class="total">Total: $${total.toFixed(2)}</p>
                <div style="text-align:center;margin-top:1em;">
                    <button id="btnImprimirTicketVenta" class="bg-black text-white px-4 py-2 rounded text-sm">
                        🖨️ Imprimir
                    </button>
                </div>
            </div>
        `;
    }


    $('#filtroBusqueda, #filtroDesdeVenta, #filtroHastaVenta, #filtroMetodoPago').on('change input', function() {
        paginaVenta = 1;
        cargarVentas();
    });

    $('#btnAnteriorVenta').on('click', function() {
        if (paginaVenta > 1) {
            paginaVenta--;
            cargarVentas();
        }
    });

    $('#btnSiguienteVenta').on('click', function() {
        paginaVenta++;
        cargarVentas();
    });

    cargarVentas();
});
</script>