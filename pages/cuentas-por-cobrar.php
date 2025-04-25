<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);

// Agrega un contenedor para paginación
?>
<div class="max-w-7xl mx-auto p-6">
    <h2 class="text-2xl font-bold mb-6">💳 Cuentas por Cobrar</h2>

    <!-- Filtros -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <input type="text" id="filtroCliente" placeholder="Buscar cliente..." class="border px-3 py-2 rounded w-full">
        <select id="filtroEstado" class="border px-3 py-2 rounded w-full">
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendiente</option>
            <option value="pagado">Pagado</option>
            <option value="vencido">Vencido</option>
            <option value="bloqueado">Bloqueado</option>
        </select>
        <input type="date" id="filtroDesde" class="border px-3 py-2 rounded w-full">
        <input type="date" id="filtroHasta" class="border px-3 py-2 rounded w-full">
    </div>

    <!-- Tabla -->
    <div class="overflow-x-auto bg-white shadow rounded">
        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-2">Cliente</th>
                    <th class="px-4 py-2">Monto Total</th>
                    <th class="px-4 py-2">Pagado</th>
                    <th class="px-4 py-2">Pendiente</th>
                    <th class="px-4 py-2">Vence</th>
                    <th class="px-4 py-2">Estado</th>
                    <th class="px-4 py-2 text-center">Acciones</th>
                    <th class="px-4 py-2 text-center">Historial</th>
                </tr>
            </thead>
            <tbody id="tablaCuentasCXC"></tbody>
        </table>
    </div>

    <!-- Paginación -->
    <div class="mt-4 text-center">
        <button id="btnAnterior" class="px-4 py-2 bg-gray-200 rounded">Anterior</button>
        <span id="paginaActual" class="mx-2 font-semibold">1</span>
        <button id="btnSiguiente" class="px-4 py-2 bg-gray-200 rounded">Siguiente</button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let pagina = 1;

    function cargarCuentas() {
        const cliente = $('#filtroCliente').val();
        const estado = $('#filtroEstado').val();
        const desde = $('#filtroDesde').val();
        const hasta = $('#filtroHasta').val();

        $.post(ajaxurl, {
            action: 'ajax_obtener_cuentas_cobrar',
            cliente, estado, desde, hasta, pagina
        }, function(res) {
            if (!res.success || res.data.cuentas.length === 0) {
                $('#tablaCuentasCXC').html('<tr><td colspan="7" class="text-center py-4">No hay resultados</td></tr>');
                return;
            }


            let html = '';
            res.data.cuentas.forEach(c => {
                let historialBtn = `
                <button class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1 rounded ver-historial"
                    data-id="${c.id}" data-cliente="${c.cliente}">
                    📄 Ver
                </button>`;
                let estadoColor = 'text-gray-600';
                if (c.estado === 'vencido') estadoColor = 'text-red-600 font-semibold';
                if (c.estado === 'bloqueado') estadoColor = 'text-orange-600 font-semibold';
                if (c.estado === 'pagado') estadoColor = 'text-green-600 font-semibold';

                let accion = '';
                if (c.estado === 'pagado') {
                    accion = '<span class="text-gray-500 italic">Sin acción pendiente</span>';
                } else if (c.estado === 'bloqueado') {
                    accion = '<span class="text-orange-600 italic">Crédito bloqueado</span>';
                } else {
                    accion = `<button class="bg-green-600 text-white text-sm px-3 py-1 rounded registrar-pago" 
                        data-id="${c.id}" data-cliente="${c.cliente}" data-pendiente="${c.saldo_pendiente.replace(/,/g, '')}">
                        Registrar Pago
                    </button>`;
                }

                // ✅ Mostrar botón para ver la OC si existe
                if (c.orden_compra_url) {
                    accion += `<br><a href="${c.orden_compra_url}" target="_blank" class="text-blue-600 underline text-sm mt-1 inline-block">📎 Ver OC</a>`;
                }
                html += `
                    <tr class="border-b">
                        <td class="px-4 py-2">${c.cliente}</td>
                        <td class="px-4 py-2">$${c.monto_total}</td>
                        <td class="px-4 py-2">$${c.monto_pagado}</td>
                        <td class="px-4 py-2">$${c.saldo_pendiente}</td>
                        <td class="px-4 py-2">${c.fecha_limite_pago}</td>
                        <td class="px-4 py-2 capitalize ${estadoColor}">${c.estado}</td>
                        <td class="px-4 py-2 text-center">${accion}</td>
                        <td class="px-4 py-2 text-center">${historialBtn}</td>
                    </tr>`;
            });

            $('#tablaCuentasCXC').html(html);
            $('#paginaActual').text(pagina);
        });
    }

    $(document).on('click', '.registrar-pago', function() {
        const cuentaId = $(this).data('id');
        const cliente = $(this).data('cliente');
        const pendiente = parseFloat(
            $(this).data('pendiente').toString().replace(/,/g, '')
        );

        Swal.fire({
            title: 'Registrar Pago',
            html: `
                <div class="text-left space-y-4 text-sm">
                    <div>
                    <p class="mb-1"><strong>Cliente:</strong> <span class="text-gray-800">${cliente}</span></p>
                    </div>

                    <div>
                    <label for="montoPago" class="block font-medium mb-1">Monto a pagar</label>
                    <input type="number" id="montoPago" class="swal2-input w-full" placeholder="Ej. 1000" min="0" max="${pendiente}" step="0.01" />
                    </div>

                    <div class="flex items-center space-x-2">
                    <input type="checkbox" id="pagarTodo" class="h-4 w-4" />
                    <label for="pagarTodo" class="text-sm text-gray-700">Pagar el total: <strong>$${pendiente.toFixed(2)}</strong></label>
                    </div>

                    <div>
                    <label for="metodoPago" class="block font-medium mb-1">Método de pago</label>
                    <select id="metodoPago" class="swal2-select w-full">
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                    </div>

                    <div>
                    <label for="comprobantePago" class="block font-medium mb-1">📎 Comprobante (opcional)</label>
                    <input type="file" id="comprobantePago" accept=".pdf,image/*" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" />
                    </div>

                    <div>
                    <label for="notasPago" class="block font-medium mb-1">Notas adicionales</label>
                    <textarea id="notasPago" rows="3" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" placeholder="Ej. Referencia de pago, observaciones..."></textarea>
                    </div>
                </div>
                `,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            didOpen: () => {
                $('#pagarTodo').on('change', function () {
                    if (this.checked) {
                        $('#montoPago').val(pendiente.toFixed(2)).prop('disabled', true);
                    } else {
                        $('#montoPago').val('').prop('disabled', false);
                    }
                });
            },
            preConfirm: () => {
                const monto = parseFloat(document.getElementById('montoPago').value);
                const file = document.getElementById('comprobantePago').files[0];

                if (!monto || monto <= 0 || monto > pendiente) {
                    Swal.showValidationMessage('Monto inválido o superior al saldo pendiente.');
                    return false;
                }

                return {
                    cuenta_id: cuentaId,
                    monto: monto,
                    metodo: document.getElementById('metodoPago').value,
                    notas: document.getElementById('notasPago').value,
                    archivo: file || null
                };
            }
        }).then(res => {
            if (!res.isConfirmed) return;

            const formData = new FormData();
            formData.append('action', 'ajax_registrar_pago_cxc');
            formData.append('cuenta_id', res.value.cuenta_id);
            formData.append('monto_pagado', res.value.monto);
            formData.append('metodo_pago', res.value.metodo);
            formData.append('notas', res.value.notas);
            if (res.value.archivo) {
                formData.append('comprobante_pago', res.value.archivo);
            }

            Swal.fire({ title: 'Procesando...', didOpen: () => Swal.showLoading() });

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.success) {
                    Swal.fire('✅ Pago registrado', '', 'success');
                    cargarCuentas();
                } else {
                    Swal.fire('❌ Error', resp.data?.message || 'No se pudo registrar el pago', 'error');
                }
            })
            .catch(() => {
                Swal.fire('❌ Error', 'Ocurrió un error inesperado.', 'error');
            });
        });

    });

    $(document).on('click', '.ver-historial', function () {
        const cuentaId = $(this).data('id');
        const cliente = $(this).data('cliente');

        Swal.fire({
            title: `Historial de Pagos`,
            html: 'Cargando historial...',
            didOpen: () => {
                fetch(ajaxurl, {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'ajax_obtener_historial_pagos_cxc',
                        cuenta_id: cuentaId
                    })
                })
                .then(res => res.json())
                .then(resp => {
                    if (!resp.success || !resp.data || resp.data.length === 0) {
                        Swal.update({
                            html: `<p class="text-gray-600 text-sm">No hay pagos registrados para esta cuenta.</p>`
                        });
                        return;
                    }

                    let tabla = `<table class="w-full text-left text-sm border border-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-2 py-1 border">Fecha</th>
                                <th class="px-2 py-1 border">Monto</th>
                                <th class="px-2 py-1 border">Método</th>
                                <th class="px-2 py-1 border">Notas</th>
                                <th class="px-2 py-1 border">Comprobante</th>
                            </tr>
                        </thead><tbody>`;

                    resp.data.forEach(p => {
                        tabla += `<tr>
                            <td class="border px-2 py-1">${p.fecha}</td>
                            <td class="border px-2 py-1">$${parseFloat(p.monto).toFixed(2)}</td>
                            <td class="border px-2 py-1">${p.metodo}</td>
                            <td class="border px-2 py-1">${p.notas || '-'}</td>
                            <td class="border px-2 py-1 text-center">
                                ${p.comprobante_url ? `<a href="${p.comprobante_url}" target="_blank" class="text-blue-600 underline">📎 Ver</a>` : '-'}
                            </td>
                        </tr>`;
                    });

                    tabla += '</tbody></table>';

                    Swal.update({
                        html: `
                            <div class="text-left">
                                <p class="font-medium mb-2">Cliente: ${cliente}</p>
                                ${tabla}
                            </div>
                        `,
                        width: 700
                    });
                })
                .catch(() => {
                    Swal.update({
                        html: `<p class="text-red-600 text-sm">Error al cargar el historial.</p>`
                    });
                });
            },
            showCloseButton: true,
            showCancelButton: false,
            showConfirmButton: false
        });
    });

    $('#filtroCliente, #filtroEstado, #filtroDesde, #filtroHasta').on('change input', function() {
        pagina = 1;
        cargarCuentas();
    });

    $('#btnAnterior').on('click', function() {
        if (pagina > 1) {
            pagina--;
            cargarCuentas();
        }
    });

    $('#btnSiguiente').on('click', function() {
        pagina++;
        cargarCuentas();
    });

    cargarCuentas();
});
</script>
<style>
    input#montoPago {
        margin: 0;
    }
    select#metodoPago {
        margin: 0;
    }
</style>