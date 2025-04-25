<?php
if (!defined('ABSPATH')) exit;

wp_enqueue_script('jquery');
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-6 flex items-center gap-2">
        <i class="fas fa-clipboard-list"></i> Gestión de Pedidos
    </h1>

    <!-- Filtros -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <input type="text" id="filtroCliente" placeholder="Buscar por cliente, SKU o ID"
            class="w-full border border-gray-300 rounded px-3 py-2" />

        <select id="filtroEstado" class="w-full border border-gray-300 rounded px-3 py-2">
            <option value="">Todos los estados</option>
            <option value="pending">Pendiente</option>
            <option value="processing">Procesando</option>
            <option value="completed">Completado</option>
            <option value="cancelled">Cancelado</option>
        </select>

        <input type="date" id="filtroDesde" class="w-full border border-gray-300 rounded px-3 py-2" />
        <input type="date" id="filtroHasta" class="w-full border border-gray-300 rounded px-3 py-2" />
    </div>

    <!-- Tabla de pedidos -->
    <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Método</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Canal</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaPedidos" class="bg-white divide-y divide-gray-200 text-sm">
                <tr><td colspan="8" class="text-center py-4 text-gray-500">Cargando pedidos...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function cargarPedidos() {
        const cliente = $('#filtroCliente').val();
        const estado = $('#filtroEstado').val();
        const desde = $('#filtroDesde').val();
        const hasta = $('#filtroHasta').val();

        $('#tablaPedidos').html('<tr><td colspan="8" class="text-center py-4 text-gray-500">Cargando pedidos...</td></tr>');

        $.post(ajaxurl, {
            action: 'ajax_obtener_pedidos',
            cliente, estado, desde, hasta
        }, function(res) {
            if (!res.success || res.data.length === 0) {
                $('#tablaPedidos').html('<tr><td colspan="8" class="text-center py-4 text-gray-500">No hay pedidos registrados.</td></tr>');
                return;
            }

            let html = '';
            res.data.forEach(p => {
                html += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium text-gray-800">#${p.id}</td>
                        <td class="px-4 py-2 text-gray-700">${p.cliente}</td>
                        <td class="px-4 py-2 text-green-600 font-semibold">$${p.total}</td>
                        <td class="px-4 py-2 capitalize">${p.estado}</td>
                        <td class="px-4 py-2">${p.metodo_pago}</td>
                        <td class="px-4 py-2 text-sm">${p.canal}</td>
                        <td class="px-4 py-2">${p.fecha}</td>
                        <td class="px-4 py-2 text-center">
                            <a href="admin.php?page=detalle-pedido&id=${p.id}" 
                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded">
                                Ver
                            </a>
                        </td>
                    </tr>`;
            });

            $('#tablaPedidos').html(html);
        });
    }

    $('#filtroCliente, #filtroEstado, #filtroDesde, #filtroHasta').on('change input', cargarPedidos);
    cargarPedidos();
});
</script>
