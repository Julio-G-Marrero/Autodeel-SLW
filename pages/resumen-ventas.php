<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-7xl mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6">Resumen Ventas POS</h2>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
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
        <button id="btnBuscarVentas" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            Buscar
        </button>
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
const rolActual = "<?php echo esc_js(wp_get_current_user()->roles[0] ?? ''); ?>";
jQuery(document).ready(function($) {
    let paginaVenta = 1;

    function cargarVentas() {
        const $btn = $('#btnBuscarVentas');
        $btn.prop('disabled', true).text('Buscando...');

        const busqueda = $('#filtroBusqueda').val();
        const desde = $('#filtroDesdeVenta').val();
        const hasta = $('#filtroHastaVenta').val();
        const metodo = $('#filtroMetodoPago').val();

        $.post(ajaxurl, {
            action: 'ajax_obtener_resumen_ventas',
            busqueda, desde, hasta, metodo, pagina: paginaVenta
        }, function(res) {
            const $tabla = $('#tablaVentasAutopartes');
            if (!res.success || res.data.ventas.length === 0) {
                $tabla.html('<tr><td colspan="6" class="text-center py-4">No hay ventas registradas</td></tr>');
            } else {
                let html = '';
                const puedeGestionar = ['administrator', 'cobranza'].includes(rolActual);

                res.data.ventas.forEach(v => {
                    console.log('Venta:', v);

                    let estadoTexto = '';
                    let acciones = '';

                    switch (v.estado) {
                        case 'en_revision':
                            estadoTexto = '<span class="text-yellow-600 font-semibold">En revisión</span>';
                            acciones = `<span class="text-yellow-700 text-xs italic">Proceso de devolución</span>`;
                            break;

                        case 'cancelada':
                            estadoTexto = '<span class="text-red-600 font-semibold">Cancelada</span>';
                            acciones = `<span class="text-red-700 text-xs italic">Venta cancelada</span>`;
                            break;

                        case 'completada':
                        case null:
                        case '':
                        case undefined:
                            estadoTexto = '<span class="text-green-600 font-semibold"></span>';
                            acciones = `<button data-id="${v.id}" class="bg-blue-600 text-white text-xs px-3 py-1 rounded ver-ticket">Ver Ticket</button>`;
                            
                            if (puedeGestionar) {
                                acciones += `
                                    <button data-id="${v.id}" data-cliente-id="${v.cliente_id}" class="solicitar-devolucion bg-yellow-500 text-white text-xs px-3 py-1 rounded ml-1">Devolución</button>
                                    <button data-id="${v.id}" class="bg-red-600 text-white text-xs px-3 py-1 rounded cancelar-venta ml-1">Cancelar</button>
                                `;
                            }
                            break;

                        default:
                            estadoTexto = '<span class="text-gray-600 font-semibold">Activa</span>';
                            acciones = `<button data-id="${v.id}" class="bg-blue-600 text-white text-xs px-3 py-1 rounded ver-ticket">🧾 Ver Ticket</button>`;
                            break;
                    }

                    html += `
                        <tr class="border-b">
                            <td class="px-4 py-2 font-semibold">#${v.id}</td>
                            <td class="px-4 py-2">${v.cliente}</td>
                            <td class="px-4 py-2">$${v.total}</td>
                            <td class="px-4 py-2">${v.metodo}</td>
                            <td class="px-4 py-2">${v.fecha}</td>
                            <td class="px-4 py-2 space-y-1">
                                ${estadoTexto}
                                <div class="mt-1">${acciones}</div>
                            </td>
                        </tr>`;
                });

                $tabla.html(html);
                $('#paginaActualVenta').text(paginaVenta);
            }

            $btn.prop('disabled', false).text('Buscar');
        }).fail(function() {
            Swal.fire('Error', 'Hubo un problema al cargar las ventas.', 'error');
            $btn.prop('disabled', false).text('Buscar');
        });

    }


    $(document).on('click', '.ver-ticket', function() {
        const ventaId = $(this).data('id');
        Swal.fire('', 'Aquí podrías cargar el detalle de la venta #'+ventaId, 'info');
        // Puedes usar un fetch/$.post aquí para obtener y mostrar el ticket en un popup
    });

    // Cancelar venta
    $(document).on('click', '.cancelar-venta', function () {
        const ventaId = $(this).data('id');

        Swal.fire({
            title: '¿Cancelar esta venta?',
            text: 'Esto eliminará el ingreso, restaurará el stock y marcará esta venta como cancelada.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No, conservar'
        }).then(res => {
            if (!res.isConfirmed) return;

            $.post(ajaxurl, {
                action: 'ajax_cancelar_venta_pos',
                venta_id: ventaId
            }, function (r) {
                if (r.success) {
                    Swal.fire('Cancelada', r.data.message || 'La venta fue cancelada correctamente.', 'success');
                    cargarVentas();
                } else {
                    Swal.fire('Error', r.data.message || 'No se pudo cancelar la venta.', 'error');
                }
            });
        });
    });

    // Solicitar devolución
    $(document).on('click', '.solicitar-devolucion', function () {
        const ventaId = $(this).data('id');
        const clienteId = $(this).data('cliente-id');

        Swal.fire({
            title: '¿Solicitar devolución?',
            text: 'Esto enviará los productos al proceso de inspección.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Solicitar',
            cancelButtonText: 'Cancelar'
        }).then(res => {
            if (!res.isConfirmed) return;

            $.post(ajaxurl, {
                action: 'ajax_solicitar_devolucion_pos',
                venta_id: ventaId,
                cliente_id: clienteId,
            }, function (r) {
                if (r.success) {
                    Swal.fire('Registrado', r.data.message || 'Solicitud de devolución creada.', 'success');
                    cargarVentas();
                } else {
                    Swal.fire('Error', r.data.message || 'No se pudo registrar la devolución.', 'error');
                }
            });
        });
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

            const ticketHTML = generarTicketVentaHTML(res.data); // ← Aquí ya debe incluir vendedor y fecha_hora
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
                                .no-print { display: none; }
                            }
                        </style></head><body>${contenido}</body></html>`);
                        ventana.document.close();
                        ventana.print();
                    });
                }
            });
        });

    });

    function generarTicketVentaHTML({ cliente, productos, total, metodo, folio, vendedor, fecha_hora }) {
        const filas = productos.map(p => `
            <tr>
                <td>${p.nombre}<br><small>SKU: ${p.sku}</small></td>
                <td>${p.ubicacion ? `<small>${p.ubicacion}</small>` : '-'}</td>
                <td style="text-align:right;">$${p.precio.toFixed(2)}</td>
            </tr>
        `).join('');

        return `
            <div id="ticketVentaContenido" style="font-family:monospace;">
                <div style="text-align:center;margin-bottom:10px;">
                    <img src="https://dev-autodeel-slw.pantheonsite.io/wp-content/uploads/2025/05/LOGOSINFONDO-3-1.png" alt="Logo" style="max-width:150px;height:auto;margin-bottom:5px;">
                    <h2 style="margin: 0;">Ticket de Venta</h2>
                    <p style="font-size: 13px; color: #555;">Gracias por su compra. Todas nuestras autopartes son inspeccionadas y garantizadas.<br>Para devoluciones conserve este ticket y contáctenos dentro de los primeros 7 días.</p>
                </div>

                <p><strong>Folio Venta:</strong> #${folio}</p>
                <p><strong>Fecha:</strong> ${fecha_hora}</p>
                <p><strong>Vendedor:</strong> ${vendedor}</p>
                <p><strong>Cliente:</strong> ${cliente}</p>
                <p><strong>Método de pago:</strong> ${metodo}</p>
                <hr/>
                <table style="width:100%; font-size: 14px;">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Ubicación</th>
                            <th style="text-align:right;">Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${filas}
                    </tbody>
                </table>
                <hr/>
                <p class="total" style="text-align:right; font-size: 16px; font-weight: bold;">Total: $${total.toFixed(2)}</p>

                <div style="text-align:center;margin-top:1em;">
                    <button id="btnImprimirTicketVenta" class="no-print bg-black text-white px-4 py-2 rounded text-sm">
                        Imprimir
                    </button>
                </div>
            </div>
        `;
    }


    $('#btnBuscarVentas').on('click', function () {
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