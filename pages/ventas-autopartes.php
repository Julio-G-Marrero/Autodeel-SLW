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
        <input type="hidden" id="clienteID" />
        <div id="clienteResultado" class="mt-2 text-sm text-gray-600"></div>
    </div>

    <div class="mb-4">
        <label class="block text-sm font-medium mb-1">Tipo de Búsqueda de Producto</label>
        <select id="modoBusquedaProducto" class="w-full border-gray-300 rounded px-3 py-2">
            <option value="">Selecciona un método</option>
            <option value="qr">Escanear QR</option>
            <option value="sku">Buscar por SKU / Nombre</option>
            <option value="filtro">Filtrar por Compatibilidad</option>
        </select>
    </div>
    <div id="contenedorBusquedaDinamica" class="mb-4"></div>
    <div id="resultadoBusquedaProducto" class="mt-2 text-sm text-gray-600"></div>


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
let productosSeleccionados = [];

jQuery(document).ready(function($) {
    $(document).on('input', '#inputQR', function () {
        const url = $(this).val().trim();
        const match = url.match(/sku=([^#]+)/i);

        if (!match) {
            $('#resultadoBusquedaProducto').html('<p class="text-red-600">❌ URL no válida o sin SKU.</p>');
            return;
        }

        const sku = match[1];

        $('#resultadoBusquedaProducto').html('<p class="text-blue-600">🔎 Buscando producto por QR...</p>');

        $.post(ajaxurl, {
            action: 'ajax_buscar_producto_avanzado',
            termino: sku
        }, function (res) {
            if (!res.success || res.data.length === 0) {
                $('#resultadoBusquedaProducto').html('<p class="text-red-600">❌ No se encontraron productos.</p>');
                return;
            }

            let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
            res.data.forEach(p => {
                html += `
                    <div class="border rounded p-4 shadow bg-white">
                        <img src="${p.imagen}" class="w-full h-32 object-contain mb-2" />
                        <h4 class="text-sm font-bold">${p.nombre}</h4>
                        <p class="text-sm text-gray-600">${p.sku}</p>
                        <p class="text-sm text-green-600 font-bold">$${p.precio}</p>
                        <button data-sku="${p.sku}" data-nombre="${p.nombre}" data-precio="${p.precio}" class="mt-2 bg-blue-600 text-white px-3 py-1 rounded agregar-producto">
                            Agregar
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            $('#resultadoBusquedaProducto').html(html);
        });
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
        const clienteID = $('#clienteID').val();

        if (!clienteID || productosSeleccionados.length === 0) {
            $('#mensajeVenta').html(`<p class="text-red-600">⚠️ Debes seleccionar un cliente y al menos un producto.</p>`);
            return;
        }

        // Aquí iría el fetch/post real con el clienteID y los productos

        $('#mensajeVenta').html(`<p class="text-green-600">✅ Venta registrada correctamente (simulado)</p>`);
    });

    $('#cliente').on('input', function () {
        const termino = $(this).val().trim();
        if (termino.length < 2) return;

        $('#clienteResultado').html('<p class="text-blue-600">🔍 Buscando clientes...</p>');

        $.post(ajaxurl, {
            action: 'ajax_buscar_cliente',
            termino
        }, function (res) {
            if (!res.success || res.data.length === 0) {
                $('#clienteResultado').html('<p class="text-red-600">❌ No se encontraron resultados.</p>');
                return;
            }

            let html = '<ul class="border rounded bg-white shadow text-sm">';
            res.data.forEach(c => {
                html += `<li class="px-3 py-2 hover:bg-gray-100 cursor-pointer cliente-item" data-id="${c.id}" data-nombre="${c.nombre}" data-correo="${c.correo}">
                            ${c.nombre} (${c.correo})
                        </li>`;
            });
            html += '</ul>';

            $('#clienteResultado').html(html);
        });
    });

    $(document).on('click', '.cliente-item', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const correo = $(this).data('correo');

        $('#cliente').val(`${nombre} (${correo})`);
        $('#clienteID').val(id);  // ← guardamos el ID del cliente para usarlo después
        $('#clienteResultado').html(`<span class="text-green-600">✅ Cliente seleccionado: ${nombre}</span>`);
    });

    $('#modoBusquedaProducto').on('change', function () {
        const modo = $(this).val();
        const contenedor = $('#contenedorBusquedaDinamica');
        contenedor.empty();

        if (modo === 'qr') {
            contenedor.html(`
                <input type="text" id="inputQR" placeholder="Escanea o pega la URL del QR"
                    class="w-full border rounded px-3 py-2" />
                <button class="mt-2 bg-blue-600 text-white px-4 py-2 rounded" id="btnLeerQR">Activar Cámara</button>
            `);
        } else if (modo === 'sku') {
            contenedor.html(`
                <input type="text" id="inputSKU" placeholder="Ingresa SKU o descripción"
                    class="w-full border rounded px-3 py-2" />
            `);
        } else if (modo === 'filtro') {
            contenedor.html(`
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <select id="marcaFiltro" class="border rounded px-3 py-2 w-full"></select>
                    <select id="submarcaFiltro" class="border rounded px-3 py-2 w-full"></select>
                    <select id="anioFiltro" class="border rounded px-3 py-2 w-full">
                        <option value="">Año</option>
                        ${Array.from({ length: 2026 - 1990 + 1 }, (_, i) => {
                            const year = 2026 - i;
                            return `<option value="${year}">${year}</option>`;
                        }).join('')}
                    </select>
                    <select id="categoriaFiltro" class="border rounded px-3 py-2 w-full">
                        <option value="">Todas las categorías</option>
                    </select>
                </div>
                <button id="btnBuscarCompatibilidad" class="mt-3 bg-blue-600 text-white px-4 py-2 rounded">Buscar por compatibilidad</button>
            `);

            // Precargar marcas
            $.post(ajaxurl, { action: 'obtener_marcas' }, function (res) {
                if (res.success) {
                    const opciones = res.data.map(m => `<option value="${m}">${m}</option>`);
                    $('#marcaFiltro').html('<option value="">Marca</option>' + opciones.join(''));
                }
            });

            // Obtener categorías de productos
            $.post(ajaxurl, { action: 'obtener_categorias_productos' }, function (res) {
                if (res.success && res.data.length > 0) {
                    const opciones = res.data.map(cat => `<option value="${cat.slug}">${cat.nombre}</option>`);
                    $('#categoriaFiltro').append(opciones.join(''));
                }
            });

            // Precargar submarcas al cambiar marca
            $(document).on('change', '#marcaFiltro', function () {
                const marca = $(this).val();
                $('#submarcaFiltro').html('<option value="">Cargando...</option>');
                $.get(ajaxurl + '?action=obtener_submarcas&marca=' + encodeURIComponent(marca), function (res) {
                    if (res.success) {
                        const opciones = res.data.submarcas.map(sm => `<option value="${sm}">${sm}</option>`);
                        $('#submarcaFiltro').html('<option value="">Submarca</option>' + opciones.join(''));
                    } else {
                        $('#submarcaFiltro').html('<option value="">No se encontraron submarcas</option>');
                    }
                });
            });
        }
    });

    $(document).on('input', '#inputSKU', function () {
        const termino = $(this).val().trim();

        if (termino.length < 2) {
            $('#resultadoBusquedaProducto').html('');
            return;
        }

        $('#resultadoBusquedaProducto').html(`<p class="text-blue-600">🔎 Buscando "${termino}"...</p>`);

        $.post(ajaxurl, {
            action: 'ajax_buscar_producto_avanzado',
            termino
        }, function (res) {
            if (!res.success || !res.data.length) {
                $('#resultadoBusquedaProducto').html('<p class="text-red-600">❌ No se encontraron productos.</p>');
                return;
            }

            let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
            res.data.forEach(p => {
                html += `
                    <div class="border rounded p-4 shadow bg-white">
                        <img src="${p.imagen}" class="w-full h-32 object-contain mb-2" />
                        <h4 class="text-sm font-bold">${p.nombre}</h4>
                        <p class="text-sm text-gray-600">${p.sku}</p>
                        <p class="text-sm text-green-600 font-bold">$${p.precio}</p>
                        <button data-sku="${p.sku}" data-nombre="${p.nombre}" data-precio="${p.precio}" class="mt-2 bg-blue-600 text-white px-3 py-1 rounded agregar-producto">
                            Agregar
                        </button>
                    </div>
                `;
            });
            html += '</div>';

            $('#resultadoBusquedaProducto').html(html);
        });
    });

    $(document).on('click', '#btnBuscarCompatibilidad', function () {
        const marca = $('#marcaFiltro').val().trim();
        const submarca = $('#submarcaFiltro').val().trim();
        const anio = $('#anioFiltro').val().trim();
        const categoria = $('#categoriaFiltro').val().trim();

        const compatibilidad = `${marca} ${submarca} ${anio}`.toUpperCase();

        if (!marca || !submarca || !anio) {
            $('#resultadoBusquedaProducto').html('<p class="text-red-600">Todos los campos son obligatorios.</p>');
            return;
        }

        $('#resultadoBusquedaProducto').html('<p class="text-blue-600">🔎 Buscando productos compatibles...</p>');

        $.post(ajaxurl, {
            action: 'ajax_buscar_productos_compatibles',
            compatibilidad,
            categoria
        }, function (res) {
            if (!res.success || res.data.length === 0) {
                $('#resultadoBusquedaProducto').html('<p class="text-red-600">❌ No se encontraron productos.</p>');
                return;
            }

            let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
            res.data.forEach(p => {
                html += `
                    <div class="border rounded p-4 shadow bg-white">
                        <img src="${p.imagen}" class="w-full h-32 object-contain mb-2" />
                        <h4 class="text-sm font-bold">${p.nombre}</h4>
                        <p class="text-sm text-gray-600">${p.sku}</p>
                        <p class="text-sm text-green-600 font-bold">$${p.precio}</p>
                        <button data-sku="${p.sku}" data-nombre="${p.nombre}" data-precio="${p.precio}" class="mt-2 bg-blue-600 text-white px-3 py-1 rounded agregar-producto">
                            Agregar
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            $('#resultadoBusquedaProducto').html(html);
        });
    });

    $(document).on('click', '.agregar-producto', function () {
        const sku = $(this).data('sku');
        const nombre = $(this).data('nombre');
        const precio = parseFloat($(this).data('precio'));

        // Verifica si ya está agregado
        const yaExiste = productosSeleccionados.find(p => p.sku === sku);
        if (yaExiste) {
            Swal.fire('Ya agregado', 'Este producto ya está en la venta.', 'info');
            return;
        }

        productosSeleccionados.push({
            sku,
            nombre,
            precio,
            cantidad: 1
        });

        actualizarTabla();
    });

});

</script>