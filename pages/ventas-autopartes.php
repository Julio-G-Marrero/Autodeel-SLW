<?php
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('html5-qrcode', 'https://unpkg.com/html5-qrcode', [], null, true);
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
wp_enqueue_script('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js', [], null, true);
?>
<header class="bg-blue-800 text-white p-4 flex items-center justify-between">
    <h1 class="text-2xl font-bold flex items-center space-x-2">
        <i class="fas fa-tools text-white"></i>
        <span class="text-white">Autodeel POS</span>
    </h1>
</header>

<main class="flex flex-col md:flex-row flex-1 p-4 gap-4 max-w-7xl mx-auto w-full">
    <!-- Izquierda: Cliente y Búsqueda -->
    <section class="md:w-2/3 bg-white rounded-lg shadow p-6 flex flex-col gap-6">
        <div>
            <h2 class="text-xl font-semibold mb-3 border-b border-gray-300 pb-2">Buscador de cliente</h2>
            <form id="customer-search-form" class="flex flex-col sm:flex-row gap-3 sm:items-center">
                <input
                    type="text"
                    id="cliente"
                    placeholder="Busqueda por nombre o correo"
                    class="flex-grow border border-gray-300 rounded px-3 py-2"
                    autocomplete="off"
                />
                <input type="hidden" id="clienteID" />
                <button type="submit" class="bg-blue-700 text-white px-5 py-2 rounded flex items-center gap-2 hidden">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
            <div id="clienteResultado" class="mt-2 border rounded bg-white shadow max-h-48 overflow-y-auto text-sm"></div>
        </div>

        <div>
            <h2 class="text-xl font-semibold mb-3 border-b border-gray-300 pb-2">Busqueda de prodcutos</h2>
            <div class="flex flex-wrap gap-3 mb-4" id="tabsTipoBusqueda">
                <button type="button" data-tipo="qr" class="tab-busqueda bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2">
                    <i class="fas fa-qrcode"></i> Codigo QR
                </button>
                <button type="button" data-tipo="sku" class="tab-busqueda bg-gray-200 text-gray-700 px-4 py-2 rounded flex items-center gap-2">
                    <i class="fas fa-barcode"></i> SKU / Descripción
                </button>
                <button type="button" data-tipo="compat" class="tab-busqueda bg-gray-200 text-gray-700 px-4 py-2 rounded flex items-center gap-2">
                    <i class="fas fa-cogs"></i> Compatibilidad Autoparte
                </button>
            </div>

            <div id="contenedorBusquedaDinamica" class="mb-4"></div>
            <div id="resultadoBusquedaProducto" class="text-sm text-gray-600 mt-2"></div>
        </div>
    </section>

    <!-- Derecha: Carrito -->
    <section class="md:w-1/3 bg-white rounded-lg shadow p-6 flex flex-col">
        <h2 class="text-xl font-semibold mb-3 border-b border-gray-300 pb-2 flex items-center justify-between">
            Carrito
            <button id="clear-cart-btn" class="text-red-600 hover:text-red-800 text-sm flex items-center gap-1">
                <i class="fas fa-trash-alt"></i> Limpiar
            </button>
        </h2>

        <div id="cart-items" class="overflow-y-auto max-h-[40vh] divide-y divide-gray-200">
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
        </div>

        <div class="mt-6 border-t border-gray-300 pt-4">
            <h3 class="text-lg font-semibold mb-3">Resumen de Venta</h3>
            <div class="flex justify-between mb-2"><span>Subtotal:</span><span id="subtotal" class="font-medium">$0.00</span></div>
            <!-- <div class="flex justify-between mb-2"><span>Tax (15%):</span><span id="tax" class="font-medium">$0.00</span></div> -->
            <!-- <div class="flex justify-between mb-4">
                <span>Discount:</span>
                <input type="number" id="discount" min="0" max="100" step="1" value="0"
                       class="w-20 border border-gray-300 rounded px-2 py-1 text-right" />
                <span class="ml-2 text-gray-600">%</span>
            </div> -->
            <div class="flex justify-between text-xl font-bold border-t border-gray-300 pt-2">
                <span>Total:</span>
                <span id="total">$0.00</span>
            </div>
            
            <button id="btnValidarVenta"
            class="mt-4 w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded text-lg flex items-center justify-center gap-2"
            >
            <i class="fas fa-check-circle"></i> Validar Venta
            </button>
        </div>
    </section>
</main>

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

            let html = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
            res.data.forEach(p => {
                html += `
                    <div class="border rounded p-4 shadow bg-white">
                        <img src="${p.imagen}" class="w-full h-32 object-contain mb-2 popup-imagen cursor-pointer" />
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
                        <span class="block text-center">${prod.cantidad}</span>
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
        $('#subtotal').text(`$${total.toFixed(2)}`);
        $('#tax').text(`$${(total * 0.15).toFixed(2)}`);
        const descuento = parseFloat($('#discount').val()) || 0;
        const totalFinal = total * (1 - descuento / 100);
        $('#total').text(`$${totalFinal.toFixed(2)}`);
        $('#btnRegistrarVenta').prop('disabled', productosSeleccionados.length === 0);
    }

    // Eventos para modificar cantidad o eliminar
    // $('#tablaProductos').on('input', '.cantidad-prod', function () {
    //     const index = $(this).data('index');
    //     productosSeleccionados[index].cantidad = parseInt($(this).val());
    //     actualizarTabla();
    // });


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
        if (termino.length < 2) {
            $('#clienteResultado').html('').hide();
            return;
        }

        $.post(ajaxurl, {
            action: 'ajax_buscar_cliente',
            termino
        }, function (res) {
            if (!res.success || res.data.length === 0) {
                $('#clienteResultado').html('<p class="text-gray-600 px-3 py-2">❌ No se encontraron resultados.</p>').show();
                return;
            }

            let html = '<ul>';
            res.data.forEach(c => {
                html += `
                    <li class="px-3 py-2 hover:bg-gray-100 cursor-pointer cliente-item"
                        data-id="${c.id}" data-nombre="${c.nombre}" data-correo="${c.correo}">
                        ${c.nombre} (${c.correo})
                    </li>`;
            });
            html += '</ul>';

            $('#clienteResultado').html(html).show();
        });
    });

    $(document).on('click', '.cliente-item', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const correo = $(this).data('correo');

        $('#cliente').val(`${nombre} (${correo})`);
        $('#clienteID').val(id);
        $('#clienteResultado').html(`<span class="text-green-600 px-2">Cliente seleccionado: ${nombre}</span>`);
    });

    $(document).on('click', '.tab-busqueda', function () {
        $('.tab-busqueda').removeClass('bg-blue-600 text-white').addClass('bg-gray-200 text-gray-700');
        $(this).removeClass('bg-gray-200 text-gray-700').addClass('bg-blue-600 text-white');

        $('#modoBusquedaProducto').val($(this).data('tipo')).trigger('change');
    });

    $(document).on('click', '.cliente-item', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const correo = $(this).data('correo');

        $('#cliente').val(`${nombre} (${correo})`);
        $('#clienteID').val(id);  // ← guardamos el ID del cliente para usarlo después
        $('#clienteResultado').html(`<span class="text-green-600">Cliente seleccionado: ${nombre}</span>`);
    });

    $('#modoBusquedaProducto').on('change', function () {
        const modo = $(this).val();
        const contenedor = $('#contenedorBusquedaDinamica');
        contenedor.empty();

        if (modo === 'qr') {
            contenedor.html(`
                <div class="mb-2">
                    <button id="btnIniciarQR" class="bg-blue-600 text-white px-4 py-2 rounded">📷 Activar cámara</button>
                </div>
                <div id="qr-reader" class="mt-2" style="width: 100%; max-width: 400px;"></div>
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

    $(document).on('click', '#btnIniciarQR', function () {
        const qrContainer = document.getElementById("qr-reader");
        qrContainer.innerHTML = ''; // limpiar anterior

        const qrScanner = new Html5Qrcode("qr-reader");

        qrScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            (decodedText) => {
                qrScanner.stop().then(() => {
                    buscarProductoDesdeQR(decodedText); // ✅ carga directo al carrito
                });
            },
            (errorMessage) => {
                // opcional
            }
        ).catch(err => {
            $('#qr-reader').html('<p class="text-red-600">❌ Error al activar cámara: ' + err + '</p>');
        });
    });

    function buscarProductoDesdeQR(url) {
        const match = url.match(/sku=([^#]+)/i);
        if (!match) {
            Swal.fire('QR inválido', 'El código escaneado no contiene un SKU válido.', 'error');
            return;
        }

        const sku = match[1].trim();

        // Evitar duplicados en el carrito
        const yaExiste = productosSeleccionados.find(p => p.sku === sku);
        if (yaExiste) {
            Swal.fire('Producto ya agregado', 'Este producto ya está en el carrito.', 'info');
            return;
        }

        // Mostrar búsqueda visual
        $('#resultadoBusquedaProducto').html('<p class="text-blue-600">🔎 Buscando producto: ' + sku + '</p>');

        // Buscar producto por AJAX
        $.post(ajaxurl, {
            action: 'ajax_buscar_producto_avanzado',
            termino: sku
        }, function (res) {
            if (!res.success || !res.data.length) {
                $('#resultadoBusquedaProducto').html('<p class="text-red-600">❌ Producto no encontrado.</p>');
                return;
            }

            const producto = res.data[0]; // primer resultado

            // Agregar al carrito directamente
            productosSeleccionados.push({
                sku: producto.sku,
                nombre: producto.nombre,
                precio: parseFloat(producto.precio),
                cantidad: 1
            });

            actualizarTabla(); // función que ya tienes para redibujar la tabla

            $('#resultadoBusquedaProducto').html(`
                <p class="text-green-600">✅ Producto <strong>${producto.nombre}</strong> agregado desde QR.</p>
            `);
        });
    }

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
                Swal.fire('Sin resultados', 'No se encontraron productos con ese criterio.', 'warning');
                return;
            }

            let html = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
            res.data.forEach(p => {
                html += `
                    <div class="border rounded p-4 shadow bg-white">
                        <img src="${p.imagen}" class="w-full h-32 object-contain mb-2 popup-imagen cursor-pointer" />
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

    $(document).on('click', '#btnValidarVenta', function () {
        const clienteID = $('#clienteID').val();
        const clienteTexto = $('#cliente').val();
        const metodo = $('#metodoPago').val();
        const total = $('#total').text();
        const tieneOCObligatoria = clienteTexto.toLowerCase().includes('[oc]');
        const usaCredito = metodo === 'credito';

        if (!clienteID || productosSeleccionados.length === 0) {
            Swal.fire('⚠️ Información incompleta', 'Debes seleccionar un cliente y al menos un producto.', 'warning');
            return;
        }

        let contenidoHTML = `
            <div class="text-left">
            <p><strong>Cliente:</strong> ${clienteTexto}</p>
            <p><strong>Método de pago:</strong> ${metodo}</p>
            <p><strong>Total:</strong> ${total}</p>
            <hr class="my-2">
            <ul class="list-disc pl-5 text-sm">
                ${productosSeleccionados.map(p => `<li>${p.nombre} - $${p.precio}</li>`).join('')}
            </ul>
            <hr class="my-2">
            ${usaCredito ? `<p class="text-red-600">🧾 Esta venta usará crédito. Se validará límite y días.</p>` : ''}
            ${tieneOCObligatoria ? `<p class="text-orange-600">📄 Se requiere orden de compra para este cliente.</p>` : ''}
            </div>
        `;

        Swal.fire({
            title: 'Resumen de Venta',
            html: contenidoHTML,
            showCancelButton: true,
            confirmButtonText: 'Registrar Venta',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            preConfirm: () => {
            return new Promise((resolve, reject) => {
                // TODO: agregar validaciones más profundas aquí
                $.post(ajaxurl, {
                action: 'ajax_registrar_venta_autopartes',
                cliente_id: clienteID,
                metodo_pago: metodo,
                productos: JSON.stringify(productosSeleccionados)
                }, function (res) {
                if (res.success) {
                    resolve(res.data);
                } else {
                    reject(res.data.message || 'Error al registrar venta');
                }
                });
            });
            }
        }).then(result => {
            if (result.isConfirmed) {
            Swal.fire('✅ Venta registrada', 'La venta fue procesada exitosamente.', 'success');
            productosSeleccionados = [];
            actualizarTabla();
            }
        }).catch(err => {
            Swal.fire('❌ Error', err, 'error');
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

            let html = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
            res.data.forEach(p => {
                html += `
                    <div class="border rounded p-4 shadow bg-white">
                        <img src="${p.imagen}" class="w-full h-32 object-contain mb-2 popup-imagen cursor-pointer" />
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
        Swal.fire({
            icon: 'success',
            title: 'Producto agregado',
            text: `${nombre} fue añadido al carrito.`,
            toast: true,
            position: 'top-end',
            timer: 2000,
            showConfirmButton: false
        });

        $('#resultadoBusquedaProducto').html('');
        $('#inputSKU').val(''); 
    });

    $(document).on('click', '.popup-imagen', function () {
        const src = $(this).attr('src');
        Swal.fire({
            imageUrl: src,
            imageAlt: 'Imagen del producto',
            showConfirmButton: false,
            background: 'transparent', // el fondo del popup
            backdrop: `
                rgba(0, 0, 0, 0.8)
                center left
                no-repeat
            `,
            width: 'auto',
            padding: 0,
            customClass: {
                popup: 'rounded-lg overflow-hidden shadow-lg'
            }
        });
    });

    // Manejar cambio entre tipos de búsqueda (QR, SKU, Compatibilidad)
    $(document).ready(function () {
        $('.tab-busqueda[data-tipo="qr"]').trigger('click');
    });

    $(document).on('click', '.tab-busqueda', function () {
        $('.tab-busqueda')
            .removeClass('bg-blue-600 text-white')
            .addClass('bg-gray-200 text-gray-700');
        $(this)
            .removeClass('bg-gray-200 text-gray-700')
            .addClass('bg-blue-600 text-white');

        const tipo = $(this).data('tipo');
        const contenedor = $('#contenedorBusquedaDinamica');
        contenedor.fadeOut(150, function () {
            contenedor.empty();

            if (tipo === 'qr') {
                contenedor.html(`
                    <div class="mb-2">
                        <button id="btnIniciarQR" class="bg-blue-600 text-white px-4 py-2 rounded">📷 Activar cámara</button>
                    </div>
                    <div id="qr-reader" class="mt-2" style="width: 100%; max-width: 400px;"></div>
                `);
            }

            if (tipo === 'sku') {
                contenedor.html(`
                    <label for="inputSKU" class="block font-medium mb-1">Buscar por SKU o descripción</label>
                    <input type="text" id="inputSKU" placeholder="Ej. 019-3104-02 o calavera explorer"
                        class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" />
                `);
            }

            if (tipo === 'compat') {
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

                // Precargar categorías
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

            contenedor.fadeIn(250);
        });
    });

});

</script>
<style>
    .swal2-popup.swal2-modal.rounded-lg.overflow-hidden.swal2-show {
        width: auto !important;
    }
</style>