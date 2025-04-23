<?php
if (!defined('ABSPATH')) exit;

wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-4xl mx-auto p-6 bg-white rounded shadow mt-6">
    <h2 class="text-2xl font-bold mb-6">🧾 Apertura / Cierre de Caja</h2>

    <div id="estadoCaja" class="mb-6"></div>

    <!-- Formulario de apertura -->
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
            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                Abrir Caja
            </button>
        </div>
    </form>

    <!-- Botón de cierre -->
    <button id="btnCerrarCaja" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 hidden">
        Cerrar Caja
    </button>
</div>

<script>
const usuarioNombre = "<?= wp_get_current_user()->display_name ?>";

jQuery(document).ready(function ($) {

    function mostrarTicketCierreCaja(resumen, denominaciones, usuario) {
        const denominacionesTexto = Object.entries(denominaciones).map(([denom, cantidad]) => {
            return `<tr><td class="border px-2 py-1 text-right">$${denom}</td><td class="border px-2 py-1 text-center">${cantidad}</td><td class="border px-2 py-1 text-right">$${(denom * cantidad).toFixed(2)}</td></tr>`;
        }).join('');

        const html = `
            <div id="ticketCierreCaja" class="p-6 max-w-md mx-auto bg-white border border-gray-300 rounded shadow text-sm font-mono">
                <h2 class="text-xl font-bold text-center mb-1">🧾 Corte de Caja</h2>
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


    function verificarEstadoCaja() {
        $.post(ajaxurl, { action: 'ajax_estado_caja' }, function (res) {
            if (res.success && res.data.estado === 'abierta') {
                $('#estadoCaja').html(`<p class="text-green-700 font-semibold">✅ Caja abierta desde: ${res.data.fecha}</p>`);
                $('#btnCerrarCaja').show();
                $('#formAperturaCaja').hide();
            } else {
                $('#estadoCaja').html(`<p class="text-red-700 font-semibold">🔒 No hay caja abierta</p>`);
                $('#btnCerrarCaja').hide();
                $('#formAperturaCaja').show();
            }
        });
    }

    verificarEstadoCaja();

    $('#formAperturaCaja').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&action=ajax_abrir_caja';
        const denominaciones = {};
        $('#formAperturaCaja')
            .find('input[name^="denominaciones"]')
            .each(function () {
                const denom = $(this).attr('name').match(/\d+/)[0];
                denominaciones[denom] = parseInt($(this).val()) || 0;
            });

        $.post(ajaxurl, formData, function (res) {
            if (res.success) {
                mostrarTicketAperturaCaja(res.data.resumen, denominaciones, res.data.usuario);
            } else {
                Swal.fire('Error', res.data?.message || 'No se pudo abrir la caja.', 'error');
            }
        });
    });

    $('#btnCerrarCaja').on('click', function () {
        Swal.fire({
            title: 'Cerrar Caja',
            html: generarFormularioDenominaciones(),
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Cerrar Caja',
            preConfirm: () => {
                const datos = {};
                document.querySelectorAll('.swal2-input-den').forEach(input => {
                    datos[input.name] = parseInt(input.value) || 0;
                });
                return {
                    detalle_cierre: datos,
                    notas: document.getElementById('notasCierre').value
                };
            }
        }).then(result => {
            if (!result.isConfirmed) return;

            const formData = new FormData();
            formData.append('action', 'ajax_cerrar_caja');
            formData.append('detalle_cierre', JSON.stringify(result.value.detalle_cierre));
            formData.append('notas', result.value.notas);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    mostrarTicketCierreCaja(data.data.resumen, result.value.detalle_cierre, usuarioNombre);
                    verificarEstadoCaja();
                }
                else {
                    Swal.fire('Error', data.data?.message || 'No se pudo cerrar la caja.', 'error');
                }
            });
        });
    });

    function mostrarTicketAperturaCaja(resumen, denominaciones, usuario) {
        const desglose = Object.entries(denominaciones).map(([denom, cantidad]) => {
            if (cantidad > 0) {
                return `<tr><td class="border px-2 py-1 text-right">$${denom}</td><td class="border px-2 py-1 text-center">${cantidad}</td><td class="border px-2 py-1 text-right">$${(denom * cantidad).toFixed(2)}</td></tr>`;
            }
            return '';
        }).join('');

        const html = `
            <div id="ticketAperturaContenido" class="text-sm font-mono">
                <h2 class="text-lg font-bold text-center mb-2">🧾 Ticket Apertura Caja</h2>
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
                    <button id="btnImprimirTicketApertura" class="bg-black text-white px-4 py-1 rounded text-sm">🖨️ Imprimir</button>
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
                window.location.href = 'admin.php?page=ventas-autopartes'; // Reemplaza por el slug real del POS
            }
        });
    }

    
    function generarFormularioDenominaciones() {
        const valores = [1000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
        let html = `<p class="mb-2">Ingresa los billetes/monedas al cerrar la caja:</p>`;
        valores.forEach(v => {
            html += `
                <div class="mb-2">
                    <label class="block text-sm">Denominación $${v}</label>
                    <input type="number" name="${v}" class="swal2-input swal2-input-den" min="0" value="0">
                </div>`;
        });
        html += `<textarea id="notasCierre" class="swal2-textarea mt-4" placeholder="Notas de cierre..."></textarea>`;
        return html;
    }
});
</script>
