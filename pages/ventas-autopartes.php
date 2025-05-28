<?php
if (!defined('ABSPATH')) {
    exit;
}
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
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
            <div class="flex justify-between justify-items-center mb-3 pb-2">
                <h2 class="text-xl font-semibold">Buscador de cliente</h2>
                <button type="button" id="abrirSelectorClientes" class="bg-blue-800 hover:bg-blue-900 text-white px-4 py-2 rounded text-sm">
                    Listado Clientes
                </button>
            </div>

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
            <h2 class="text-xl font-semibold mb-3 border-b border-gray-300 pb-2">Busqueda de Productos</h2>
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
                        <!-- <th class="px-2 py-1">Cantidad</th> -->
                        <th class="px-2 py-1">Precio</th>
                        <!-- <th class="px-2 py-1">Subtotal</th> -->
                        <th class="px-2 py-1"></th>
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
                <i class="fas fa-check-circle"></i> Proceder Venta
            </button>
            <button id="btnCargarNegociacion" class="mt-4 w-full bg-yellow-600 hover:bg-yellow-700 text-white py-3 rounded text-lg flex items-center justify-center gap-2">
                <i class="fas fa-handshake"></i> Cargar negociaciones
            </button>
        </div>
    </section>
</main>

<script>
let productosSeleccionados = [];

jQuery(document).ready(function($) {
    $(document).on('click', '#btnCargarNegociacion', function () {
        $.post(ajaxurl, {
            action: 'ajax_obtener_mis_negociaciones_aprobadas'
        }, function (res) {
            if (!res.success || !res.data.length) {
                Swal.fire('Sin resultados', 'No tienes negociaciones disponibles.', 'info');
                return;
            }

            const cards = res.data.map(n => {
                return `
                    <div class="border rounded p-3 bg-white shadow-sm flex flex-col gap-1 text-sm" data-json='${JSON.stringify(n)}'>
                        <div class="font-semibold text-gray-800">${n.nombre_producto}</div>
                        <div class="text-gray-500">SKU: <span class="text-xs">${n.producto_sku}</span></div>
                        <div class="text-gray-500">Cliente: <span class="text-xs">${n.cliente_nombre}</span></div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-green-600">✅ Aprobado</span>
                            <span class="ml-auto text-gray-600">$${parseFloat(n.precio_solicitado).toFixed(2)}</span>
                        </div>
                        <button class="btn-cargar-negociacion bg-green-600 hover:bg-green-700 text-white text-xs py-1 px-2 rounded mt-2 self-start">
                            Cargar esta negociación
                        </button>
                    </div>
                `;
            }).join('');

            Swal.fire({
                title: 'Negociaciones aprobadas',
                html: `<div class="max-h-96 overflow-y-auto flex flex-col gap-3">${cards}</div>`,
                showCancelButton: true,
                showConfirmButton: false,
                cancelButtonText: 'Cerrar'
            });
        });
    });

    $(document).on('click', '.btn-cargar-negociacion', function () {
        const container = $(this).closest('[data-json]');
        const n = JSON.parse(container.attr('data-json'));

        $('#clienteID').val(n.cliente_id);
        $('#cliente').val(`${n.cliente_nombre} (${n.cliente_correo})`);
        $('#clienteResultado').html(`<span class="text-green-600">Cliente cargado: ${n.cliente_nombre}</span>`);

        const yaExiste = productosSeleccionados.find(p => p.sku === n.producto_sku);
        if (yaExiste) {
            Swal.fire('Ya en carrito', 'Este producto ya fue agregado.', 'info');
            return;
        }

        productosSeleccionados.push({
            sku: n.producto_sku,
            nombre: n.nombre_producto,
            precio: parseFloat(n.precio_solicitado),
            cantidad: 1,
            negociacion_id: n.id
        });

        actualizarTabla();

        Swal.close();
        Swal.fire('Cargado', 'Producto y cliente agregados correctamente.', 'success');
    });
    $(document).on('input', '#inputQR', function () {
        const url = $(this).val().trim();
        const match = url.match(/sku=([^#]+)/i);

        if (!match) {
            $('#resultadoBusquedaProducto').html('<p class="text-red-600">❌ URL no válida o sin SKU.</p>');
            return;
        }

        const sku = match[1];
        const clienteId = $('#clienteID').val(); // 🟩 Obtener cliente si está seleccionado

        $('#resultadoBusquedaProducto').html('<p class="text-blue-600">Buscando producto por QR...</p>');
        $.post(ajaxurl, {
            action: 'ajax_buscar_producto_avanzado',
            termino: sku,
            cliente_id: clienteId // 🟩 Enviar cliente_id al backend
        }, function (res) {
            if (!res.success || !res.data.length) {
                $('#resultadoBusquedaProducto').html('<p class="text-red-600">❌ Producto no encontrado.</p>');
                return;
            }

            const formatoMXN = new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            });

            let html = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
            res.data.forEach(p => {
                const precioNumerico = parseFloat(p.precio || 0);
                const precioBase = parseFloat(p.precio_base || 0);

                const precioFormateado = formatoMXN.format(precioNumerico);
                const precioBaseFormateado = formatoMXN.format(precioBase);

                const mostrarPrecio = (precioBase > precioNumerico)
                    ? `<p class="text-sm text-gray-500 line-through">${precioBaseFormateado}</p>
                    <p class="text-sm text-green-600 font-bold">Precio Especial: ${precioFormateado}</p>`
                    : `<p class="text-sm text-green-600 font-bold">${precioFormateado}</p>`;

                html += `
                    <div class="border rounded p-4 shadow bg-white">
                        <img 
                            src="${p.imagen}" 
                            class="w-full h-32 object-contain mb-2 popup-imagen cursor-pointer" 
                            data-galeria='${JSON.stringify(p.galeria || [])}' 
                        />
                        <h4 class="text-sm font-bold">${p.nombre}</h4>
                        <p class="text-sm text-gray-600">${p.sku}</p>
                        ${mostrarPrecio}
                        <button 
                            data-sku="${p.sku}" 
                            data-nombre="${p.nombre}" 
                            data-precio="${precioNumerico}" 
                            data-solicitud-id="${p.solicitud_id}" 
                            data-ubicacion="${p.ubicacion || ''}"
                            class="mt-2 bg-blue-600 text-white px-3 py-1 rounded agregar-producto">
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
                    <td class="px-2 py-1 hidden">
                        <span class="block text-center">${prod.cantidad}</span>
                    </td>
                    <td class="px-2 py-1">$${prod.precio.toFixed(2)}</td>
                    <td class="px-2 py-1 hidden">$${subtotal.toFixed(2)}</td>
                    <td class="px-2 py-1">
                        <button data-index="${index}" class="text-red-600 btn-eliminar">
                            <svg class="w-6 h-6 text-red-600 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M8.586 2.586A2 2 0 0 1 10 2h4a2 2 0 0 1 2 2v2h3a1 1 0 1 1 0 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a1 1 0 0 1 0-2h3V4a2 2 0 0 1 .586-1.414ZM10 6h4V4h-4v2Zm1 4a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Zm4 0a1 1 0 1 0-2 0v8a1 1 0 1 0 2 0v-8Z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <button data-index="${index}" class="text-yellow-600 btn-negociar-precio" title="Solicitar negociación">
                            <i class="fas fa-handshake"></i>
                        </button>
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

        $('#mensajeVenta').html(`<p class="text-green-600">Venta registrada correctamente (simulado)</p>`);
    });

    $('#cliente').on('input', function () {
        const termino = $(this).val().trim();
        if (termino.length < 2) {
            $('#clienteResultado').html('').hide();
            return;
        }

        $.post(ajaxurl, {
            action: 'ajax_buscar_clientes_pos',
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
        $('#clienteID').val(id);  
        $('#clienteResultado').html(`<span class="text-green-600">Cliente seleccionado: ${nombre}</span>`);

        // 🔥 Validar crédito al seleccionar cliente
        $.post(ajaxurl, {
            action: 'ajax_validar_credito_cliente',
            cliente_id: id
        }, function (res) {
            if (res.success) {
                const credito = parseFloat(res.data.credito_disponible);
                const estado = res.data.estado_credito;
                const ocObligatoria = res.data.oc_obligatoria;

                // Mostrar alerta si el crédito está suspendido
                if (estado !== 'activo') {
                    $('#clienteResultado').append(`
                        <div class="mt-2 text-red-600 font-semibold">
                            Crédito suspendido para este cliente. No puede pagar con crédito.
                        </div>
                    `);
                }

                // Puedes guardar estos valores si quieres usarlos más adelante
                $('#clienteResultado').data('estado_credito', estado);
                $('#clienteResultado').data('credito_disponible', credito);
                $('#clienteResultado').data('oc_obligatoria', ocObligatoria);
            }
        });
    });

    $(document).on('click', '#abrirSelectorClientes', function () {
        Swal.fire({
            title: 'Buscar Cliente',
            html: `
                <input type="text" id="filtroClientePopup" class="swal2-input w-full m-0" placeholder="Filtrar por nombre o correo">
                <div id="tablaClientesPopup" class="text-left text-sm max-h-64 overflow-y-auto mt-2"></div>
            `,
            width: 700,
            showCancelButton: true,
            showConfirmButton: false,
            didOpen: () => {
                cargarClientesPOS('');

                $('#filtroClientePopup').on('input', function () {
                    const termino = $(this).val().trim();
                    cargarClientesPOS(termino);
                });
            }
        });
    });

    function cargarClientesPOS(termino = '') {
        $.post(ajaxurl, {
            action: 'ajax_buscar_clientes_pos',
            termino: termino
        }, function (res) {
            if (!res.success || res.data.length === 0) {
                $('#tablaClientesPopup').html('<p class="text-gray-600 p-2">No se encontraron resultados.</p>');
                return;
            }

            let html = `
                <table class="w-full text-sm table-auto border border-gray-300">
                    <thead><tr class="bg-gray-100">
                        <th class="px-2 py-1">Nombre</th>
                        <th class="px-2 py-1">Correo</th>
                        <th class="px-2 py-1">Seleccionar</th>
                    </tr></thead>
                    <tbody>
            `;

            res.data.forEach(c => {
                html += `
                    <tr class="border-t">
                        <td class="px-2 py-1">${c.nombre}</td>
                        <td class="px-2 py-1">${c.correo}</td>
                        <td class="px-2 py-1 text-center">
                            <button class="seleccionarClientePOS bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs"
                                data-id="${c.id}" data-nombre="${c.nombre}" data-correo="${c.correo}">
                                Seleccionar
                            </button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            $('#tablaClientesPopup').html(html);
        });
    }

    $(document).on('click', '.seleccionarClientePOS', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const correo = $(this).data('correo');

        $('#cliente').val(`${nombre} (${correo})`);
        $('#clienteID').val(id);
        $('#clienteResultado').html(`<span class="text-green-600">Cliente seleccionado: ${nombre}</span>`);
        Swal.close();

        // Obtener roles del cliente
        $.post(ajaxurl, {
            action: 'obtener_roles_cliente',
            cliente_id: id
        }, function (res) {
            if (res.success) {
                $('#clienteResultado').data('roles_cliente', res.data.roles);
            }
        });
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

    function mostrarResumenVenta(cliente, productos, total, metodoPago, clienteInfo) {
        let productosHTML = productos.map(p =>
            `<li>${p.nombre} (SKU: ${p.sku}) - $${p.precio}</li>`
        ).join('');
        const solicitudes = productosSeleccionados
        .filter(p => p.solicitud_id)
        .map(p => p.solicitud_id);

        formData.append('solicitudes_ids', JSON.stringify(solicitudes));
        const requiereOC = clienteInfo.oc_obligatoria === '1';
        const tieneCredito = clienteInfo.estado_credito === 'activo';

        const metodosDisponibles = ['efectivo', 'tarjeta', 'transferencia'];
        if (tieneCredito) {
            metodosDisponibles.push('credito');
        }

        const metodoSelectHTML = `
            <label class="block mt-2 mb-1 text-sm font-medium">Método de Pago</label>
            <select id="selectMetodoPago" class="w-full border px-3 py-2 rounded">
                ${metodosDisponibles.map(m => `<option value="${m}">${m.charAt(0).toUpperCase() + m.slice(1)}</option>`).join('')}
            </select>
        `;

        Swal.fire({
            title: '📋 Confirmar Venta',
            html: `
                <div class="text-left text-sm">
                    <p><strong>Cliente:</strong> ${cliente}</p>
                    ${metodoSelectHTML}
                    ${tieneCredito ? `
                        <div class="bg-blue-50 border border-blue-300 text-blue-800 p-2 rounded text-sm">
                            <p><strong>Crédito total:</strong> $${parseFloat(clienteInfo.credito_total).toFixed(2)}</p>
                            <p><strong>Deuda actual:</strong> $${parseFloat(clienteInfo.deuda_actual).toFixed(2)}</p>
                            <p><strong>Crédito disponible:</strong> <span class="${clienteInfo.credito_disponible <= 0 ? 'text-red-600' : 'text-green-600'} font-bold">
                                $${parseFloat(clienteInfo.credito_disponible).toFixed(2)}
                            </span></p>
                        </div>
                    ` : ''}
                    ${requiereOC ? `<p class="text-red-600 font-semibold">Este cliente requiere orden de compra obligatoria.</p>` : ''}
                    <hr class="my-2">
                    <ul class="list-disc list-inside mb-2">${productosHTML}</ul>
                    <p class="font-bold text-lg">Total: $${total.toFixed(2)}</p>
                    ${requiereOC ? `
                        <label class="block mt-4 mb-2">📎 Subir Orden de Compra (PDF/Imagen):</label>
                        <input type="file" id="ocArchivo" accept=".pdf,image/*" class="swal2-file w-full text-sm" />
                    ` : ''}
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Confirmar Venta',
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            preConfirm: () => {
                const metodoPagoSeleccionado = document.getElementById('selectMetodoPago').value;
                if (!metodoPagoSeleccionado) {
                    Swal.showValidationMessage('Selecciona un método de pago válido.');
                    return false;
                }

                const result = {
                    metodo: metodoPagoSeleccionado
                };

                if (requiereOC) {
                    const archivo = document.getElementById('ocArchivo').files[0];
                    if (!archivo) {
                        Swal.showValidationMessage('Debes subir la orden de compra obligatoria.');
                        return false;
                    }
                    result.archivoOC = archivo;
                }

                return result;
            }
        }).then(result => {
            if (!result.isConfirmed) return;

            const formData = new FormData();
            formData.append('action', 'ajax_registrar_venta_autopartes');
            formData.append('cliente_id', clienteInfo.id);
            formData.append('productos', JSON.stringify(productos));
            formData.append('total', total);
            formData.append('metodo_pago', result.value.metodo);
            formData.append('oc_obligatoria', requiereOC ? '1' : '0');
            formData.append('tipo_cliente', clienteInfo.tipo_cliente);
            formData.append('credito_disponible', clienteInfo.credito_disponible);
            formData.append('solicitud_id', clienteInfo.solicitud_id || 0);

            if (result.value.archivoOC) {
                formData.append('orden_compra', result.value.archivoOC);
            }

            Swal.fire({
                title: 'Procesando venta...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire('Venta registrada', res.data.mensaje || 'La venta fue guardada con éxito.', 'success');
                    productosSeleccionados = [];
                    actualizarTabla();
                    $('#cliente').val('');
                    $('#clienteID').val('');
                    $('#resultadoBusquedaProducto').html('');
                } else {
                    Swal.fire('❌ Error', res.data || 'No se pudo completar la venta.', 'error');
                }
            })
            .catch(() => Swal.fire('❌ Error', 'Hubo un problema al registrar la venta.', 'error'));
        });
    }

    async function subirOrdenCompra(archivo) {
        const formData = new FormData();
        formData.append('action', 'subir_orden_compra');
        formData.append('archivo', archivo);

        const res = await fetch(ajaxurl, { method: 'POST', body: formData });
        const json = await res.json();
        if (!json.success) throw new Error(json.data?.message || 'Error al subir OC');
        return json.data.url;
    }

    function registrarVenta(data) {
        $.post(ajaxurl, {
            action: 'ajax_registrar_venta_autopartes',
            cliente_id: data.cliente_id,
            metodo_pago: data.metodo_pago,
            productos: JSON.stringify(data.productos)
        }, function (res) {
            if (res.success) {
                Swal.fire('Venta registrada', 'La venta fue registrada correctamente.', 'success');
                productosSeleccionados = [];
                actualizarTabla();
            } else {
                Swal.fire('Error', 'No se pudo registrar la venta.', 'error');
            }
        });
    }

    function validarCondicionesCredito(clienteId, metodo) {
        if (metodo !== 'credito') return;

        $('#extraValidacion').html('<p class="text-blue-600">Verificando condiciones de crédito...</p>');

        $.post(ajaxurl, {
            action: 'ajax_validar_credito_cliente',
            cliente_id: clienteId
        }, function (res) {
            if (!res.success) {
                $('#extraValidacion').html('<p class="text-red-600">No se pudo validar el crédito del cliente.</p>');
                return;
            }

            const c = res.data;
            let html = '';

            if (c.estado_credito !== 'activo') {
                html += `<p class="text-red-600">⚠️ Crédito suspendido.</p>`;
            }

            if (c.oc_obligatoria === '1') {
                html += `<p class="text-yellow-600">⚠️ Es obligatorio subir orden de compra.</p>`;
            }

            if (parseFloat(c.credito_disponible) < calcularTotalVenta()) {
                html += `<p class="text-red-600">⚠️ Crédito insuficiente. Disponible: $${c.credito_disponible}</p>`;
            }

            $('#extraValidacion').html(html || '<p class="text-green-600"> Cliente válido para crédito.</p>');
        });
    }

    $('#customer-search-form').on('submit', function(e) {
        e.preventDefault(); 
    });

    $('#cliente').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
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
                <p class="text-green-600"> Producto <strong>${producto.nombre}</strong> agregado desde QR.</p>
            `);
        });
    }

    $(document).on('input', '#inputSKU', function () {
        const termino = $(this).val().trim();
        const clienteID = $('#clienteID').val() || 0;

        if (termino.length < 2) {
            $('#resultadoBusquedaProducto').html('');
            return;
        }

        $('#resultadoBusquedaProducto').html(`<p class="text-blue-600">🔎 Buscando "${termino}"...</p>`);

        $.post(ajaxurl, {
            action: 'ajax_buscar_producto_avanzado',
            termino: termino,
            cliente_id: clienteID
        }, function (res) {
            if (!res.success || !res.data.length) {
                $('#resultadoBusquedaProducto').html('<p class="text-red-600">❌ No se encontraron productos con ese criterio.</p>');
                return;
            }

            const formatoMXN = new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            });

            let html = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
            res.data.forEach(p => {
                const precioNumerico = parseFloat(p.precio || 0);
                const precioBase = parseFloat(p.precio_base || 0);

                const precioFormateado = formatoMXN.format(precioNumerico);
                const precioBaseFormateado = formatoMXN.format(precioBase);

                const mostrarPrecio = (precioBase > precioNumerico)
                    ? `<p class="text-sm text-gray-500 line-through">${precioBaseFormateado}</p>
                    <p class="text-sm text-green-600 font-bold">Precio Especial: ${precioFormateado}</p>`
                    : `<p class="text-sm text-green-600 font-bold">${precioFormateado}</p>`;

                html += `
                    <div class="border rounded p-4 shadow bg-white">
                        <img 
                            src="${p.imagen}" 
                            class="w-full h-32 object-contain mb-2 popup-imagen cursor-pointer" 
                            data-galeria='${JSON.stringify(p.galeria || [])}' 
                        />
                        <h4 class="text-sm font-bold">${p.nombre}</h4>
                        <p class="text-sm text-gray-600">${p.sku}</p>
                        ${p.ubicacion ? `<p class="text-sm text-blue-700">📍 ${p.ubicacion}</p>` : ''}
                        ${mostrarPrecio}
                        <button 
                            data-sku="${p.sku}" 
                            data-nombre="${p.nombre}" 
                            data-precio="${precioNumerico}" 
                            data-solicitud-id="${p.solicitud_id || ''}" 
                            data-ubicacion="${p.ubicacion || ''}"
                            class="mt-2 bg-blue-600 text-white px-3 py-1 rounded agregar-producto">
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
        const total = parseFloat($('#total').text().replace('$', ''));

        if (!clienteID || productosSeleccionados.length === 0) {
            Swal.fire('Información incompleta', 'Debes seleccionar un cliente y al menos un producto.', 'warning');
            return;
        }

        // ✅ Validar si el usuario tiene caja abierta antes de continuar
        $.post(ajaxurl, { action: 'ajax_verificar_caja_abierta' }, function (resCaja) {
            if (!resCaja.success) {
                Swal.fire('Caja cerrada', 'No puedes registrar una venta sin tener una caja abierta.', 'error');
                return;
            }

            // ✅ Caja abierta → continuar con validación de cliente y venta
            $.post(ajaxurl, {
                action: 'ajax_validar_credito_cliente',
                cliente_id: clienteID
            }, function (res) {
                if (!res.success) {
                    Swal.fire('❌ Error', 'No se pudo obtener la información del cliente.', 'error');
                    return;
                }

                const clienteInfo = res.data;
                const requiereOC = clienteInfo.oc_obligatoria === true || clienteInfo.oc_obligatoria === '1';
                const tieneCredito = clienteInfo.estado_credito === 'activo';
                const creditoDisponible = parseFloat(clienteInfo.credito_disponible || 0);

                const metodosPago = ['efectivo', 'tarjeta', 'transferencia'];
                if (tieneCredito) metodosPago.push('credito');

                const metodoSelectHTML = `
                    <label class="block mt-3">Método de pago:</label>
                    <select id="metodoPagoSelect" class="swal2-input">
                        ${metodosPago.map(m => `<option value="${m}">${m.toUpperCase()}</option>`).join('')}
                    </select>
                `;

                Swal.fire({
                    title: 'Resumen de Venta',
                    html: `
                        <div class="text-left text-sm space-y-4">
                            <div class="bg-gray-100 p-3 rounded">
                                <p class="font-medium text-gray-700"><strong>Cliente:</strong> <span class="text-gray-900">${clienteTexto}</span></p>
                                <p class="font-medium text-gray-700"><strong>Total:</strong> <span class="text-green-600 font-bold">$${total.toFixed(2)}</span></p>
                            </div>

                            <div>
                                <label for="metodoPagoSelect" class="block text-sm font-semibold mb-1">Método de pago:</label>
                                ${metodoSelectHTML}
                            </div>

                            <div>
                            <label class="inline-flex items-center mt-2 text-sm">
                                <input type="checkbox" id="entregaInmediata" class="form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out">
                                <span class="ml-2">¿Entrega inmediata?</span>
                            </label>
                            </div>

                            ${tieneCredito ? `
                                <div class="bg-blue-50 border border-blue-300 text-blue-700 p-2 rounded">
                                    <strong>Crédito disponible:</strong> $${creditoDisponible.toFixed(2)}
                                </div>
                            ` : ''}

                            ${requiereOC ? `
                                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-3 rounded">
                                    ⚠️ Este cliente <strong>requiere orden de compra</strong> si paga a crédito.
                                </div>
                            ` : ''}

                            <div id="ocUploadField" class="hidden">
                                <label class="block text-sm font-medium mb-1 mt-2">📎 Subir orden de compra:</label>
                                <input type="file" id="archivoOC" accept=".pdf,image/*" class="w-full border border-gray-300 px-3 py-2 rounded text-sm" />
                            </div>

                            <hr class="my-3">
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Registrar Venta',
                    cancelButtonText: 'Cancelar',
                    didOpen: () => {
                        $('#metodoPagoSelect').on('change', function () {
                            const metodo = $(this).val();
                            if (metodo === 'credito' && requiereOC) {
                                $('#ocUploadField').slideDown();
                            } else {
                                $('#ocUploadField').slideUp();
                            }
                        }).trigger('change');
                    },
                    preConfirm: () => {
                        return (async () => {
                            const metodo = $('#metodoPagoSelect').val();
                            const archivoOC = $('#archivoOC')[0]?.files?.[0];
                            const entregaInmediata = $('#entregaInmediata').is(':checked');

                            // Validación de crédito
                            if (metodo === 'credito') {
                                if (requiereOC && !archivoOC) {
                                    Swal.showValidationMessage('Debes subir la orden de compra obligatoria.');
                                    return false;
                                }

                                if (total > creditoDisponible) {
                                    Swal.showValidationMessage(`El total de la venta excede el crédito disponible. Disponible: $${creditoDisponible.toFixed(2)}`);
                                    return false;
                                }
                            }

                            let oc_url = '';
                            if (archivoOC) {
                                try {
                                    oc_url = await subirOrdenCompra(archivoOC);
                                } catch (err) {
                                    Swal.showValidationMessage(err.message || 'Error al subir la orden de compra.');
                                    return false;
                                }
                            }

                            const formData = new FormData();
                            formData.append('fecha_hora', new Date().toLocaleString('es-MX'));
                            formData.append('caja_id', resCaja.data?.caja_id || '');
                            formData.append('caja_folio', resCaja.data?.folio || '');
                            formData.append('action', 'ajax_registrar_venta_autopartes');
                            formData.append('entrega_inmediata', entregaInmediata ? '1' : '0');
                            formData.append('cliente_id', clienteID);
                            formData.append('metodo_pago', metodo);
                            formData.append('productos', JSON.stringify(productosSeleccionados));
                            formData.append('oc_obligatoria', requiereOC && metodo === 'credito' ? '1' : '0');
                            formData.append('oc_url', oc_url); // ✅ OC como URL

                            const solicitudes = productosSeleccionados
                                .filter(p => p.solicitud_id)
                                .map(p => p.solicitud_id);
                            formData.append('solicitudes_ids', JSON.stringify(solicitudes));

                            return fetch(ajaxurl, {
                                method: 'POST',
                                body: formData
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (!data.success) {
                                    Swal.showValidationMessage(data.data?.message || 'No se pudo registrar la venta.');
                                    return false;
                                }
                                return data;
                            })
                            .catch(() => {
                                Swal.showValidationMessage('Error al registrar venta.');
                                return false;
                            });
                        })();
                    }
                }).then(result => {
                    if (result.isConfirmed && result.value?.success) {
                        const metodo = $('#metodoPagoSelect').val();
                        const cliente = $('#cliente').val();
                        const total = parseFloat($('#total').text().replace('$', ''));
                        const ticketHTML = generarTicketVentaHTML({
                            cliente,
                            productos: result.value.data.productos,
                            total,
                            metodo,
                            folio: result.value.data.venta_id,
                            vendedor: result.value.data.vendedor,
                            fecha_hora: result.value.data.fecha_hora
                        });
                        Swal.fire({
                            title: '',
                            html: ticketHTML, // ← ya contiene el logo y mensaje profesional
                            showConfirmButton: false,
                            width: 600,
                            didOpen: () => {
                                document.getElementById('btnImprimirTicketVenta').addEventListener('click', () => {
                                    const contenido = document.getElementById('ticketVentaContenido').innerHTML;
                                    const ventana = window.open('', '', 'width=400,height=600');
                                    ventana.document.write(`
                                        <html>
                                            <head>
                                                <title>Ticket de Venta</title>
                                                <style>
                                                    body {
                                                        font-family: monospace;
                                                        padding: 10px;
                                                        font-size: 13px;
                                                        color: #333;
                                                    }
                                                    img {
                                                        max-width: 150px;
                                                        display: block;
                                                        margin: 0 auto 5px;
                                                    }
                                                    h2 {
                                                        text-align: center;
                                                        margin-bottom: 5px;
                                                    }
                                                    p {
                                                        margin: 2px 0;
                                                    }
                                                    table {
                                                        width: 100%;
                                                        border-collapse: collapse;
                                                        margin-top: 10px;
                                                    }
                                                    th, td {
                                                        padding: 4px;
                                                        border-bottom: 1px dashed #ccc;
                                                        text-align: left;
                                                    }
                                                    th {
                                                        font-weight: bold;
                                                        background-color: #f8f8f8;
                                                    }
                                                    .total {
                                                        font-weight: bold;
                                                        font-size: 1.1em;
                                                        text-align: right;
                                                        margin-top: 10px;
                                                    }
                                                    .agradecimiento {
                                                        text-align: center;
                                                        font-style: italic;
                                                        margin-top: 15px;
                                                        font-size: 12px;
                                                        color: #666;
                                                    }
                                                </style>
                                            </head>
                                            <body>
                                                ${contenido}
                                                <p class="agradecimiento">Gracias por su compra. Conserve este ticket para futuras referencias.</p>
                                            </body>
                                        </html>
                                    `);
                                    ventana.document.close();
                                    ventana.print();
                                });
                            }
                        });

                        // Limpiar venta actual
                        productosSeleccionados = [];
                        actualizarTabla();
                        $('#cliente').val('');
                        $('#clienteID').val('');
                        $('#resultadoBusquedaProducto').html('');
                        $('#contenedorBusquedaDinamica').html('');
                        $('#modoBusquedaProducto').val('');
                    } else if (result.isConfirmed && !result.value?.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error en la venta',
                            text: result.data?.message || 'No se pudo registrar la venta. Intenta de nuevo.',
                            footer: '<a href="#">Contacta soporte si el problema persiste</a>'
                        });
                    }
                });
            });
        });
    });

    $(document).on('click', '#btnBuscarCompatibilidad', function () {
        const marca = $('#marcaFiltro').val().trim();
        const submarca = $('#submarcaFiltro').val().trim();
        const anio = $('#anioFiltro').val().trim();
        const categoria = $('#categoriaFiltro').val().trim();

        const compatibilidad = `${marca} ${submarca} ${anio}`.toUpperCase();

        if (!marca && !submarca && !anio) {
            $('#resultadoBusquedaProducto').html('<p class="text-red-600">Ingresa al menos un filtro para buscar.</p>');
            return;
        }

        $('#resultadoBusquedaProducto').html('<p class="text-blue-600">🔎 Buscando productos compatibles...</p>');
        const clienteId = $('#clienteID').val(); // asegúrate que esté seteado

        $.post(ajaxurl, {
            action: 'ajax_buscar_productos_compatibles',
            marca,
            submarca,
            anio,
            categoria,
            cliente_id: clienteId 
        }, function (res) {
            const resultados = res.data?.resultados || [];

            if (!res.success || resultados.length === 0) {
                $('#resultadoBusquedaProducto').html('<p class="text-red-600">❌ No se encontraron productos.</p>');
                return;
            }

            const formatoMXN = new Intl.NumberFormat('es-MX', {
                style: 'currency',
                currency: 'MXN'
            });

            let html = '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
            resultados.forEach(p => {
                const precioNumerico = parseFloat(p.precio || 0);
                const precioBase = parseFloat(p.precio_base || 0);

                const precioFormateado = formatoMXN.format(precioNumerico);
                const precioBaseFormateado = formatoMXN.format(precioBase);

                const mostrarPrecio = (precioBase > precioNumerico)
                    ? `<p class="text-sm text-gray-500 line-through">${precioBaseFormateado}</p>
                    <p class="text-sm text-green-600 font-bold">Precio Especial: ${precioFormateado}</p>`
                    : `<p class="text-sm text-green-600 font-bold">${precioFormateado}</p>`;

                html += `
                    <div class="border rounded p-4 shadow bg-white">
                        <img 
                            src="${p.imagen}" 
                            class="w-full h-32 object-contain mb-2 popup-imagen cursor-pointer" 
                            data-galeria='${JSON.stringify(p.galeria || [])}' 
                        />
                        <h4 class="text-sm font-bold">${p.nombre}</h4>
                        <p class="text-sm text-gray-600">${p.sku || ''}</p>
                        ${p.ubicacion ? `<p class="text-sm text-blue-700">📍 ${p.ubicacion}</p>` : ''}
                        ${mostrarPrecio}
                        <button 
                            data-sku="${p.sku}" 
                            data-nombre="${p.nombre}" 
                            data-precio="${precioNumerico}" 
                            data-solicitud-id="${p.solicitud_id || ''}" 
                            data-ubicacion="${p.ubicacion || ''}"
                            class="mt-2 bg-blue-600 text-white px-3 py-1 rounded agregar-producto">
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
        const solicitud_id = $(this).data('solicitud_id') || null;
        const ubicacion = $(this).data('ubicacion') || ''; // 🟩 extraer ubicación desde el botón

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
            cantidad: 1,
            solicitud_id,
            ubicacion // ✅ se guarda en el producto
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
        const principal = $(this).attr('src');
        const galeria = JSON.parse($(this).attr('data-galeria') || '[]');
        const imagenes = [principal, ...galeria];

        let swiperSlides = imagenes.map(src => `
            <div class="swiper-slide">
                <img src="${src}" class="w-full h-auto object-contain mx-auto max-h-[400px] border-none" />
            </div>
        `).join('');

        Swal.fire({
            title: 'Imágenes del producto',
            html: `
                <div class="swiper-container">
                    <div class="swiper-wrapper">
                        ${swiperSlides}
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
            `,
            width: 800,
            showConfirmButton: false,
            willOpen: () => {
                new Swiper('.swiper-container', {
                    loop: true,
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev'
                    },
                    pagination: {
                        el: '.swiper-pagination'
                    }
                });
            }
        });
    });

    // Manejar cambio entre tipos de búsqueda (QR, SKU, Compatibilidad)
    $(document).ready(function () {
        $('.tab-busqueda[data-tipo="qr"]').trigger('click');
    });

    $(document).on('click', '.btn-negociar-precio', function () {
        const index = $(this).data('index');
        const producto = productosSeleccionados[index];

        Swal.fire({
            title: '💬 Solicitar negociación',
            html: `
                <p>Producto: <strong>${producto.nombre}</strong></p>
                <p>Precio actual: $${producto.precio.toFixed(2)}</p>
                <label class="block text-left mt-2 mb-1 text-sm font-medium">Nuevo precio sugerido:</label>
                <input type="number" id="precioNegociado" class="swal2-input" placeholder="Ej. 3200" min="1">
                <label class="block text-left mt-2 mb-1 text-sm font-medium">Motivo de la solicitud:</label>
                <textarea id="motivoNegociacion" class="swal2-textarea" placeholder="Descuento por volumen, daño, etc."></textarea>
            `,
            showCancelButton: true,
            confirmButtonText: 'Enviar solicitud',
            preConfirm: () => {
                const precioInput = document.getElementById('precioNegociado').value.trim().replace(',', '.');
                const nuevoPrecio = parseFloat(precioInput);
                const motivo = document.getElementById('motivoNegociacion').value.trim();

                if (isNaN(nuevoPrecio) || nuevoPrecio <= 0) {
                    Swal.showValidationMessage('Ingresa un precio válido.');
                    return false;
                }

                if (motivo.length < 5) {
                    Swal.showValidationMessage('Ingresa un motivo válido.');
                    return false;
                }

                return { nuevoPrecio, motivo };
            }
        }).then(result => {
            if (!result.isConfirmed) return;

            const clienteID = $('#clienteID').val();
            if (!clienteID) {
                Swal.fire('Cliente no seleccionado', 'Primero debes seleccionar un cliente.', 'warning');
                return;
            }

            $.post(ajaxurl, {
                action: 'ajax_solicitar_negociacion_precio',
                sku: producto.sku,
                nombre: producto.nombre,
                precio_actual: producto.precio,
                precio_solicitado: result.value.nuevoPrecio,
                motivo: result.value.motivo,
                cliente_id: clienteID
            }, function (res) {
                if (res.success) {
                    Swal.fire('✅ Solicitud enviada', 'Tu solicitud de negociación fue enviada correctamente.', 'success');
                } else {
                    Swal.fire('❌ Error', res.data || 'No se pudo enviar la solicitud.', 'error');
                }
            });
        });
    });

    $(document).on('click', '#clear-cart-btn', function () {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esto eliminará todos los productos del carrito.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                productosSeleccionados = [];
                actualizarTabla();
                Swal.fire('Limpiado', 'El carrito ha sido vaciado.', 'success');
            }
        });
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
    svg.svg-inline--fa.fa-handshake.fa-w-20 {
        width: 24px;
        height: 24px;
    }
    @media print {
        button#btnImprimirTicketVenta {
            display: none !important;
        }
    }
    .no-print {
        display: inline-block;
    }
    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>