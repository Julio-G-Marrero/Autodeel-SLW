<?php
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
?>

<div class="p-6 bg-white rounded shadow-md max-w-6xl mx-auto mt-6">
    <h2 class="text-2xl font-semibold mb-4">📦 Nueva Venta de Autopartes</h2>

    <!-- Cliente -->
    <div class="mb-4">
        <label for="cliente" class="block text-sm font-medium text-gray-700 mb-1">Seleccionar Cliente</label>
        <input type="text" id="cliente" placeholder="Buscar por nombre, email o ID"
               class="w-full border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-200" />
        <div id="clienteResultado" class="mt-2 text-sm text-gray-600"></div>
    </div>

    <!-- Buscar producto -->
    <div class="mb-4">
        <label for="buscarProducto" class="block text-sm font-medium text-gray-700 mb-1">Buscar Producto</label>
        <input type="text" id="buscarProducto" placeholder="Ingresa SKU o nombre"
               class="w-full border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-200" />
        <div id="resultadoBusqueda" class="mt-2"></div>
    </div>

    <!-- Productos seleccionados -->
    <div class="mb-4">
        <h3 class="text-lg font-semibold mb-2">🛒 Productos Seleccionados</h3>
        <table class="w-full text-sm border border-gray-300 rounded overflow-hidden">
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-2 py-1">SKU</th>
                    <th class="px-2 py-1">Nombre</th>
                    <th class="px-2 py-1">Cantidad</th>
                    <th class="px-2 py-1">Precio</th>
                    <th class="px-2 py-1">Subtotal</th>
                    <th class="px-2 py-1">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaProductos"></tbody>
        </table>
        <p class="mt-2 text-right font-bold">Total: <span id="totalVenta">$0.00</span></p>
    </div>

    <!-- Método de pago -->
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Método de Pago</label>
        <select id="metodoPago"
                class="w-full border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-200">
            <option value="efectivo">Efectivo</option>
            <option value="tarjeta">Tarjeta</option>
            <option value="transferencia">Transferencia</option>
            <option value="credito">Crédito</option>
        </select>
    </div>

    <!-- Botón registrar venta -->
    <button id="btnRegistrarVenta"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
        Registrar Venta
    </button>

    <div id="mensajeVenta" class="mt-4"></div>
</div>

<script>
jQuery(document).ready(function($) {
    let productosSeleccionados = [];

    $('#buscarProducto').on('input', function () {
        const termino = $(this).val();
        if (termino.length < 3) return;

        // Simulación búsqueda (reemplazar con AJAX real)
        $('#resultadoBusqueda').html(`<p class="text-blue-600">Buscando "${termino}"...</p>`);
        // Aquí irá AJAX para buscar productos
    });

    // Agregar función para actualizar tabla y total
    function actualizarTabla() {
        const tbody = $('#tablaProductos').empty();
        let total = 0;

        productosSeleccionados.forEach((prod, index) => {
            const subtotal = prod.precio * prod.cantidad;
            total += subtotal;

            tbody.append(`
                <tr class="border-t">
                    <td class="px-2 py-1">${prod.sku}</td>
                    <td class="px-2 py-1">${prod.nombre}</td>
                    <td class="px-2 py-1">
                        <input type="number" min="1" value="${prod.cantidad}" class="w-16 px-2 py-1 border rounded cantidad-prod" data-index="${index}" />
                    </td>
                    <td class="px-2 py-1">$${prod.precio.toFixed(2)}</td>
                    <td class="px-2 py-1">$${subtotal.toFixed(2)}</td>
                    <td class="px-2 py-1">
                        <button data-index="${index}" class="text-red-600 btn-eliminar">Eliminar</button>
                    </td>
                </tr>
            `);
        });

        $('#totalVenta').text(`$${total.toFixed(2)}`);
    }

    // Eventos para modificar cantidad o eliminar
    $('#tablaProductos').on('input', '.cantidad-prod', function () {
        const index = $(this).data('index');
        productosSeleccionados[index].cantidad = parseInt($(this).val());
        actualizarTabla();
    });

    $('#tablaProductos').on('click', '.btn-eliminar', function () {
        const index = $(this).data('index');
        productosSeleccionados.splice(index, 1);
        actualizarTabla();
    });

    // Registrar venta (simulado)
    $('#btnRegistrarVenta').on('click', function () {
        const metodo = $('#metodoPago').val();
        const cliente = $('#cliente').val();

        if (!cliente || productosSeleccionados.length === 0) {
            $('#mensajeVenta').html(`<p class="text-red-600">⚠️ Cliente y productos son obligatorios.</p>`);
            return;
        }

        $('#mensajeVenta').html(`<p class="text-green-600">✅ Venta registrada correctamente (simulado)</p>`);
    });
});
</script>