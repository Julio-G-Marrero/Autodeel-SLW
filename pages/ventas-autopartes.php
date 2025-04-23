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
                        <button data-sku="${p.sku}" data-nombre="${p.nombre}" data-precio="${p.precio}" data-solicitud-id="${p.solicitud_id}" class="mt-2 bg-blue-600 text-white px-3 py-1 rounded agregar-producto">
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
            confirmButtonText: '✅ Confirmar Venta',
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
                    Swal.fire('✅ Venta registrada', res.data.mensaje || 'La venta fue guardada con éxito.', 'success');
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

            $('#extraValidacion').html(html || '<p class="text-green-600">✅ Cliente válido para crédito.</p>');
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

    $(document).on('click', '#btnConfirmarVenta', function () {
        const clienteID = $('#clienteID').val();
        const metodoPago = $('#resumenMetodo').text().trim();
        const tipoCliente = $('#resumenTipoCliente').data('tipo');
        const canalVenta = $('#resumenCanal').text().trim();
        const creditoUsado = parseFloat($('#resumenCredito').data('valor') || 0);
        const solicitudID = $('#resumenSolicitud').data('id') || 0;

        const productos = productosSeleccionados.map(p => ({
            sku: p.sku,
            nombre: p.nombre,
            precio: p.precio,
            cantidad: p.cantidad
        }));
        const solicitudes_ids = productosSeleccionados
            .filter(p => p.solicitud_id)
            .map(p => parseInt(p.solicitud_id));

        const ocObligatoria = $('#resumenOC').data('obligatoria');
        const fileInput = document.getElementById('archivoOrdenCompra');
        const archivoOC = fileInput && fileInput.files.length > 0 ? fileInput.files[0] : null;



        const formData = new FormData();
        formData.append('solicitudes_ids', JSON.stringify(solicitudes_ids));
        formData.append('action', 'ajax_registrar_venta_autopartes');
        formData.append('cliente_id', clienteID);
        formData.append('metodo_pago', metodoPago);
        formData.append('tipo_cliente', tipoCliente);
        formData.append('canal', canalVenta);
        formData.append('credito_usado', creditoUsado);
        formData.append('productos', JSON.stringify(productos));
        formData.append('solicitud_id', solicitudID);

        if (ocObligatoria === 'si' && archivoOC) {
            formData.append('orden_compra', archivoOC);
            formData.append('oc_obligatoria', 'si');
        } else {
            formData.append('oc_obligatoria', 'no');
        }

        Swal.fire({
            title: 'Procesando...',
            text: 'Registrando la venta y descontando inventario...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Venta registrada',
                    text: 'La venta ha sido registrada correctamente.',
                    timer: 2500,
                    showConfirmButton: false
                });

                // Limpieza visual
                productosSeleccionados = [];
                actualizarTabla();
                $('#cliente').val('');
                $('#clienteID').val('');
                $('#resultadoBusquedaProducto').html('');
                $('#contenedorBusquedaDinamica').html('');
                $('#modoBusquedaProducto').val('');
            } else {
                Swal.fire('Error', res.data?.message || 'No se pudo registrar la venta.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Error inesperado al registrar la venta.', 'error');
        });
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
                        <button 
                            data-sku="${p.sku}" 
                            data-nombre="${p.nombre}" 
                            data-precio="${p.precio}" 
                            data-solicitud_id="${p.solicitud_id || ''}" 
                            class="agregar-producto">
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
            Swal.fire('⚠️ Información incompleta', 'Debes seleccionar un cliente y al menos un producto.', 'warning');
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
                        const metodo = $('#metodoPagoSelect').val();
                        const archivoOC = $('#archivoOC')[0]?.files?.[0];

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

                        const formData = new FormData();
                        formData.append('action', 'ajax_registrar_venta_autopartes');
                        formData.append('cliente_id', clienteID);
                        formData.append('metodo_pago', metodo);
                        formData.append('productos', JSON.stringify(productosSeleccionados));
                        formData.append('oc_obligatoria', requiereOC && metodo === 'credito' ? '1' : '0');

                        if (archivoOC) {
                            formData.append('orden_compra', archivoOC);
                            console.log(archivoOC)
                        }else {
                            console.log(archivoOC)
                        }

                        const solicitudes = productosSeleccionados
                            .filter(p => p.solicitud_id)
                            .map(p => p.solicitud_id);
                        formData.append('solicitudes_ids', JSON.stringify(solicitudes));

                        return fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .catch(() => {
                            Swal.showValidationMessage('Error al registrar venta.');
                        });
                    }
                }).then(result => {
                    if (result.isConfirmed && result.value?.success) {
                        const metodo = $('#metodoPagoSelect').val();
                        const cliente = $('#cliente').val();
                        const total = parseFloat($('#total').text().replace('$', ''));

                        const ticketHTML = generarTicketVentaHTML({
                            cliente,
                            productos: productosSeleccionados,
                            total,
                            metodo,
                            folio: result.value.data.venta_id
                        });

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
                                    </style></head><body>${contenido}</body></html>`);
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
        const solicitud_id = $(this).data('solicitud_id') || null;

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
            solicitud_id
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
    @media print {
        button#btnImprimirTicketVenta {
            display: none !important;
        }
    }
</style>