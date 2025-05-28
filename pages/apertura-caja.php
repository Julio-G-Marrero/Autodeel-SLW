<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php';
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-4xl mx-auto p-6 bg-white rounded shadow mt-6">
    <h2 class="text-2xl font-bold mb-6">Apertura / Cierre de Caja</h2>

    <div id="estadoCaja" class="mb-6"></div>

    <!-- Formulario de apertura/cierre -->
    <form id="formAperturaCaja" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php
        $denominaciones = [1000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
        foreach ($denominaciones as $den) {
            echo "<div>
                    <label class='block text-sm font-medium mb-1'>$ {$den}</label>
                    <input type='number' name='denominaciones[$den]' value='0' min='0' class='w-full border rounded px-3 py-2'/>
                </div>";
        }
        ?>
        <div class="col-span-full">
            <label class="block text-sm font-medium mb-1">Notas</label>
            <textarea name="notas" class="w-full border rounded px-3 py-2"></textarea>
        </div>
        <div class="col-span-full text-right">
            <button type="submit" id="btnCaja" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 font-semibold">
                Abrir Caja
            </button>
        </div>
    </form>
</div>

<script>
const usuarioNombre = "<?= wp_get_current_user()->display_name ?>";

jQuery(document).ready(function ($) {

    function verificarEstadoCaja() {
        $.post(ajaxurl, { action: 'ajax_estado_caja' }, function (res) {
            if (res.success && res.data.estado === 'abierta') {
                $('#estadoCaja').html(`<p class="text-green-700 font-semibold">Caja abierta desde: ${res.data.fecha}</p>`);
                $('#formCaja').data('modo', 'cierre'); // para usar luego
                $('#btnCaja')
                    .removeClass('bg-green-600 hover:bg-green-700')
                    .addClass('bg-yellow-500 hover:bg-yellow-600')
                    .text('Cerrar Caja');
            } else {
                $('#estadoCaja').html(`<p class="text-red-700 font-semibold">No hay caja abierta</p>`);
                $('#formCaja').data('modo', 'apertura');
                $('#btnCaja')
                    .removeClass('bg-yellow-500 hover:bg-yellow-600')
                    .addClass('bg-green-600 hover:bg-green-700')
                    .text('Abrir Caja');
            }
        });
    }

    verificarEstadoCaja();

    $('#formAperturaCaja').on('submit', function (e) {
        e.preventDefault();
        const esCierre = $(this).find('button').text().includes('Cerrar');
        const denominaciones = {};
        $('#formAperturaCaja').find('input[name^="denominaciones"]').each(function () {
            const denom = $(this).attr('name').match(/\d+/)[0];
            denominaciones[denom] = parseInt($(this).val()) || 0;
        });
        const notas = $('#formAperturaCaja textarea[name="notas"]').val();

        if (esCierre) {
            const formData = new FormData();
            formData.append('action', 'ajax_cerrar_caja');
            formData.append('detalle_cierre', JSON.stringify(denominaciones));
            formData.append('notas', notas);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    mostrarTicketCierreCaja(data.data.resumen, denominaciones, usuarioNombre);
                    verificarEstadoCaja();
                } else {
                    Swal.fire('Error', data.data?.message || 'No se pudo cerrar la caja.', 'error');
                }
            });
        } else {
            const formData = $(this).serialize() + '&action=ajax_abrir_caja';
            $.post(ajaxurl, formData, function (res) {
                if (res.success) {
                    mostrarTicketAperturaCaja(res.data.resumen, denominaciones, res.data.usuario);
                    verificarEstadoCaja();
                } else {
                    Swal.fire('Error', res.data?.message || 'No se pudo abrir la caja.', 'error');
                }
            });
        }
    });

    function mostrarTicketCierreCaja(resumen, denominaciones, usuario) {
        const denominacionesTexto = Object.entries(denominaciones).map(([denom, cantidad]) => {
            return `<tr><td class="border px-2 py-1 text-right">$${denom}</td><td class="border px-2 py-1 text-center">${cantidad}</td><td class="border px-2 py-1 text-right">$${(denom * cantidad).toFixed(2)}</td></tr>`;
        }).join('');

        const html = `
            <div id="ticketCierreCaja" class="p-6 max-w-md mx-auto bg-white border border-gray-300 rounded shadow text-sm font-mono">
                <h2 class="text-xl font-bold text-center mb-1">Corte de Caja</h2>
                <p class="text-center mb-2"><strong>Folio:</strong> #${resumen.id}</p>
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
                    <thead><tr><th class="border px-2 py-1">Denom</th><th class="border px-2 py-1">Cantidad</th><th class="border px-2 py-1">Subtotal</th></tr></thead>
                    <tbody>${denominacionesTexto}</tbody>
                </table>
                <div class="text-center mt-4 no-print">
                    <button id="btnImprimirTicketCierreCaja" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-1 rounded text-sm">
                        Imprimir Ticket
                    </button>
                </div>
            </div>
        `;

        // Dentro de mostrarTicketCierreCaja()
        Swal.fire({
            title: 'Cierre de Caja Completado',
            html: html,
            width: 600,
            showConfirmButton: false,
            didOpen: () => {
                document.getElementById('btnImprimirTicketCierreCaja')?.addEventListener('click', imprimirTicketCierreCaja);
            }
        });

    }


    function mostrarTicketAperturaCaja(resumen, denominaciones, usuario) {
        const desglose = Object.entries(denominaciones).map(([denom, cantidad]) => {
            if (cantidad > 0) {
                return `<tr><td class="border px-2 py-1 text-right">$${denom}</td><td class="border px-2 py-1 text-center">${cantidad}</td><td class="border px-2 py-1 text-right">$${(denom * cantidad).toFixed(2)}</td></tr>`;
            }
            return '';
        }).join('');

        const html = `
            <div id="ticketAperturaContenido" class="text-sm font-mono">
                <h2 class="text-lg font-bold text-center mb-2">Ticket Apertura Caja</h2>
                <p class="text-center mb-2"><strong>Folio Corte:</strong> #${resumen.id}</p>
                <p><strong>Usuario:</strong> ${usuario}</p>
                <p><strong>Fecha:</strong> ${resumen.fecha_apertura}</p>
                <p><strong>Monto Inicial:</strong> $${resumen.monto_inicial.toFixed(2)}</p>
                <hr class="my-2"/>
                <p class="font-bold mb-1">Desglose:</p>
                <table class="w-full text-xs border">
                    <thead><tr><th class="border px-2 py-1">Denom</th><th class="border px-2 py-1">Cant</th><th class="border px-2 py-1">Subtotal</th></tr></thead>
                    <tbody>${desglose}</tbody>
                </table>
                <div class="text-center mt-4">
                    <button id="btnImprimirTicketApertura" class="bg-black text-white px-4 py-1 rounded text-sm">Imprimir</button>
                </div>
            </div>
        `;

        Swal.fire({
            title: 'Caja abierta correctamente',
            html: html,
            width: 600,
            showConfirmButton: true,
            confirmButtonText: 'Ir al Punto de Venta',
            didOpen: () => {
                document.getElementById('btnImprimirTicketApertura').addEventListener('click', () => {
                    const contenido = document.getElementById('ticketAperturaContenido').innerHTML;
                    const ventana = window.open('', '', 'width=400,height=600');
                    ventana.document.write(`<html><head><title>Ticket Apertura</title><style>
                        body{font-family:monospace;padding:10px;}
                        table{width:100%;border-collapse:collapse;}
                        td{padding:4px;text-align:left;}
                        .total{font-weight:bold;font-size:1.1em;text-align:right;}
                    </style></head><body>${contenido}</body></html>`);
                    ventana.document.close();
                    ventana.print();
                });
            }
        }).then(result => {
            if (result.isConfirmed) {
                window.location.href = 'admin.php?page=ventas-autopartes';
            }
        });
    }
});
    function imprimirTicketCierreCaja() {
        const contenido = document.getElementById('ticketCierreCaja')?.innerHTML || '';
        if (!contenido) {
            Swal.fire('Error', 'No se encontró el contenido del ticket.', 'error');
            return;
        }

        const ventana = window.open('', '', 'width=300,height=600');
        ventana.document.write(`
            <html>
                <head>
                    <title>Corte de Caja</title>
                    <style>
                        @media print {
                            @page { size: 80mm auto; margin: 10px; }
                            body { margin: 0; font-family: monospace; font-size: 12px; }
                            .no-print { display: none !important; }
                        }
                        body {
                            font-family: monospace;
                            font-size: 12px;
                            padding: 10px;
                            color: #000;
                        }
                        h2 {
                            font-size: 16px;
                            text-align: center;
                            margin-bottom: 5px;
                        }
                        p {
                            margin: 2px 0;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 5px;
                            font-size: 11px;
                        }
                        th, td {
                            padding: 3px;
                            border-bottom: 1px dashed #aaa;
                            text-align: right;
                        }
                        th {
                            text-align: center;
                            font-weight: bold;
                            background-color: #f8f8f8;
                        }
                        .center { text-align: center; }
                        .bold { font-weight: bold; }
                        .green { color: green; }
                        .red { color: red; }
                        .logo {
                            max-width: 120px;
                            display: block;
                            margin: 0 auto 10px;
                        }
                        .footer {
                            text-align: center;
                            font-size: 11px;
                            margin-top: 10px;
                            font-style: italic;
                        }
                    </style>
                </head>
                <body>
                    <img src="https://dev-autodeel-slw.pantheonsite.io/wp-content/uploads/2025/05/LOGOSINFONDO-3-1.png" class="logo" alt="Logo" />
                    ${contenido}
                    <div class="footer">Gracias por su trabajo. Conserve este ticket para control interno.</div>
                </body>
            </html>
        `);
        ventana.document.close();
        ventana.focus();
        ventana.print();
    }
</script>
