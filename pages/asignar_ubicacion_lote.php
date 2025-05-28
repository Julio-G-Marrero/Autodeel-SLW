<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual

global $wpdb;

?>
<div class="max-w-4xl px-4 pt-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Asignar Productos a Ubicaciones (por QR)</h2>

    <div>
        <p class="mb-3 italic">Activa la camara para escanear el qr de la ubicación y del producto.**<p>
        <!-- Botón de cámara -->
        <button id="btnEscanearQR" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800 transition mb-4">
            Activar cámara
        </button>
    </div>

    <!-- Área del escáner -->
    <div id="scanner" class="hidden mb-6 border border-gray-300 rounded p-4 bg-white w-full max-w-sm"></div>

    <div id="pendientesReubicacion" class="mt-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Productos pendientes de reintegración</h3>
        <div class="overflow-x-auto bg-white shadow rounded mb-6">
            <table class="min-w-full border text-sm">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-4 py-2">Imagen</th>
                        <th class="px-4 py-2">SKU</th>
                        <th class="px-4 py-2">Producto</th>
                        <th class="px-4 py-2">Acción</th>
                    </tr>
                </thead>
                <tbody id="tablaReubicacion">
                    <tr><td colspan="4" class="text-center py-4 text-gray-500">Cargando productos...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Campo de entrada -->
    <input
        type="text"
        id="scanInput"
        placeholder="Escanea una ubicación o producto..."
        class="w-full hidden px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:border-blue-300 mb-6"
        autofocus
    />

    <!-- Resultado de búsqueda -->
    <div id="resultadoBusqueda" class="mb-6 text-sm text-gray-600"></div>

    <!-- Ubicación activa -->
    <div id="ubicacionActiva" class="hidden bg-green-100 border border-green-400 rounded-lg p-4 mb-6 shadow-sm">
        <h3 class="text-lg font-semibold text-green-800 mb-2">Ubicación activa</h3>
        <div class="text-green-900 space-y-1">
            <p><strong>ID:</strong> <span id="ubicacionID"></span></p>
            <p><strong>Nombre:</strong> <span id="ubicacionNombre"></span></p>
            <p><strong>Descripción:</strong> <span id="ubicacionDescripcion"></span></p>
            <div id="ubicacionImagen" class="mt-2"></div>
        </div>
    </div>

    <!-- Productos asignados -->
    <div id="productosAsignados" class="hidden">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">🧾 Productos escaneados para asignar</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full border text-sm bg-white rounded shadow-sm">
                <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                    <tr>
                        <th class="text-left px-4 py-2 border-b">Imagen</th>
                        <th class="text-left px-4 py-2 border-b">SKU</th>
                        <th class="text-left px-4 py-2 border-b">Nombre</th>
                        <th class="text-left px-4 py-2 border-b">Descripción</th>
                    </tr>
                </thead>
                <tbody id="tablaProductos" class="text-gray-800"></tbody>
            </table>
        </div>
        <button id="btnFinalizar" class="mt-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded shadow">
            Finalizar asignación
        </button>
    </div>
</div>

<div id="contenedorHistorialAsignados" class="wrap border-t ml-8 mr-8">
    <h3 class="text-lg font-semibold mb-4 text-gray-800">Productos asignados por ubicación</h3>

    <!-- Filtro por ubicación -->
    <div class="mb-4">
        <label for="filtroUbicacion" class="block text-sm font-medium text-gray-700 mb-1">Selecciona una ubicación:</label>
        <select id="filtroUbicacion" class="w-full border-gray-300 rounded-md shadow-sm focus:ring focus:border-blue-300">
            <option value="">— Ver todas —</option>
        </select>
    </div>
    <div id="resumenUbicacion" class="text-sm text-gray-600 mb-4">
        Total de productos encontrados: <strong><span id="conteoProductosUbicacion">0</span></strong>
    </div>
    <div class="mb-4">
        <label for="inputBusquedaUbicacion" class="block text-sm font-medium text-gray-700 mb-1">
            Buscar producto por nombre o SKU:
        </label>
        <input
            type="text"
            id="inputBusquedaUbicacion"
            placeholder="Ej. 019-1301-00 o calavera jetta"
            class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 focus:ring focus:border-blue-300"
        />
    </div>

    <!-- Tabla de historial -->
    <div class="overflow-x-auto">
        <table class="min-w-full border text-sm bg-white rounded shadow-sm">
            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                <tr>
                    <th class="text-left px-4 py-2 border-b">Imagen</th>
                    <th class="text-left px-4 py-2 border-b">SKU</th>
                    <th class="text-left px-4 py-2 border-b">Titulo</th>
                    <!-- <th class="text-left px-4 py-2 border-b">Descripción</th> -->
                    <th class="text-left px-4 py-2 border-b">Ubicación</th>
                </tr>
            </thead>
            <tbody id="tablaHistorialAsignados" class="text-gray-800"></tbody>
        </table>
    </div>
</div>

<script src="https://cdn.tailwindcss.com"></script>
<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://unpkg.com/html5-qrcode@2.3.7/html5-qrcode.min.js"></script>
<script>
let ubicacionActiva = null;
let productosEscaneados = [];

function renderTabla() {
    const tbody = document.getElementById('tablaProductos');
    tbody.innerHTML = '';
    productosEscaneados.forEach(prod => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><img src="${prod.imagen}" alt="" width="60" style="border-radius:4px;"></td>
            <td>${prod.sku}</td>
            <td>${prod.nombre}</td>
            <td>${prod.descripcion}</td>
        `;
        tbody.appendChild(tr);
    });
}

document.getElementById('scanInput').addEventListener('change', function () {
    let valor = this.value.trim();
    this.value = '';

    // ✅ Extraer solo el sku si viene en formato de URL como ?sku=...
    try {
        if (valor.includes('?sku=')) {
            const url = new URL(valor);
            const sku = url.searchParams.get('sku');
            const hash = url.hash?.replace('#', '');
            valor = hash ? `${sku}#${hash}` : sku;
        }
    } catch (e) {
        console.warn("No es una URL válida:", valor);
    }

    // 📌 Procesar si es una ubicación
    if (valor.startsWith('ubicacion#')) {
        const idUbicacion = valor.replace('ubicacion#', '');
        fetch("<?= admin_url('admin-ajax.php') ?>?action=obtener_datos_ubicacion&id=" + idUbicacion)
            .then(res => res.json())
            .then(data => {
                console.log('Respuesta del servidor:', data);

                if (data.success && data.data?.ubicacion) {
                    ubicacionActiva = data.data.ubicacion;

                    document.getElementById('ubicacionID').textContent = ubicacionActiva.id;
                    document.getElementById('ubicacionNombre').textContent = ubicacionActiva.nombre;
                    document.getElementById('ubicacionDescripcion').textContent = ubicacionActiva.descripcion || '—';
                    document.getElementById('ubicacionImagen').innerHTML = ubicacionActiva.imagen_url
                        ? `<img src="${ubicacionActiva.imagen_url}" width="120">`
                        : '<em>Sin imagen</em>';

                    document.getElementById('ubicacionActiva').classList.remove('hidden');
                    document.getElementById('productosAsignados').classList.remove('hidden');
                    document.getElementById('contenedorHistorialAsignados')?.classList.add('hidden');
                    document.getElementById('pendientesReubicacion')?.classList.add('hidden');
                    productosEscaneados = [];
                    renderTabla();
                } else {
                    alert('Ubicación no encontrada');
                }
            });
    }

    // 📌 Si ya hay ubicación activa, buscar el producto
    else if (ubicacionActiva) {
        fetch("<?= admin_url('admin-ajax.php') ?>", {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'buscar_producto_por_sku',
                sku: valor
            })
        })
        .then(res => {
            if (!res.ok) throw new Error('Error en la respuesta del servidor');
            return res.json();
        })
        .then(data => {
            console.log('Respuesta buscar_producto_por_sku:', data);
            if (data.success) {
                const producto = data.data;

                // Verificar si el SKU ya fue escaneado
                const yaRegistrado = productosEscaneados.some(p => p.sku === valor);
                if (yaRegistrado) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Producto ya escaneado',
                        text: 'Este producto ya fue registrado previamente.',
                        toast: true,
                        position: 'top-end',
                        timer: 3000,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'swal-custom-margin'
                        }
                    });
                    return;
                }

                productosEscaneados.push({
                    sku: valor,
                    nombre: producto.nombre,
                    imagen: producto.imagen || '',
                    descripcion: producto.descripcion || ''
                });

                renderTabla();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'No encontrado',
                    text: data.message || 'Producto no encontrado.'
                });
            }
        })
        .catch(err => {
            console.error('Error en fetch buscar_producto_por_sku:', err);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'Hubo un error al buscar el producto.'
            });
        });
    }
    // ❗ No hay ubicación escaneada todavía
    else {
        alert('Primero escanea una ubicación.');
    }
});


document.getElementById('btnEscanearQR').addEventListener('click', function () {
    const scannerDiv = document.getElementById('scanner');
    scannerDiv.style.display = 'block';

    const html5QrCode = new Html5Qrcode("scanner");
    const config = { fps: 10, qrbox: 250 };

    html5QrCode.start(
        { facingMode: "environment" },
        config,
        (decodedText) => {
            html5QrCode.stop().then(() => {
                document.getElementById('scanner').style.display = 'none';
                document.getElementById('scanInput').value = decodedText;
                document.getElementById('scanInput').dispatchEvent(new Event('change'));
            });
        },
        (errorMsg) => {}
    ).catch(err => {
        alert("No se pudo iniciar la cámara: " + err);
    });
});

document.getElementById('btnFinalizar').addEventListener('click', function () {
    if (!ubicacionActiva || productosEscaneados.length === 0) return;

    const skus = productosEscaneados.map(p => p.sku);

    fetch("<?= admin_url('admin-ajax.php') ?>", {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'asignar_lote_ubicacion',
            ubicacion: ubicacionActiva.nombre,
            skus: JSON.stringify(skus)
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Asignación completada!',
                text: 'Todos los productos fueron asignados correctamente a la ubicación.',
                confirmButtonText: 'Ver resumen'
            }).then(() => {
                // Mostrar resumen visual de productos asignados
                let html = '<div style="max-height:400px;overflow-y:auto;text-align:left">';
                productosEscaneados.forEach(prod => {
                    html += `
                        <div class="producto-item" style="display:flex;align-items:center;margin-bottom:10px;">
                            <img src="${prod.imagen}" style="width:50px;height:50px;object-fit:cover;border-radius:4px;margin-right:10px;">
                            <div>
                                <strong>${prod.nombre}</strong><br>
                                <small><em>SKU: ${prod.sku}</em></small>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';

                Swal.fire({
                    title: 'Resumen de productos asignados',
                    html: html,
                    width: 600,
                    confirmButtonText: 'Aceptar'
                });

                productosEscaneados = [];
                renderTabla();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Hubo un problema al asignar los productos.'
            });
        }
        document.getElementById('contenedorHistorialAsignados')?.classList.remove('hidden');
    });
});
//  Cargar ubicaciones en el select
function cargarUbicacionesEnFiltro() {
    fetch("<?= admin_url('admin-ajax.php') ?>?action=obtener_lista_ubicaciones")
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('filtroUbicacion');

                // Agrega opción especial para productos sin ubicación
                const sinUbicacion = document.createElement('option');
                sinUbicacion.value = '__sin_ubicacion__';
                sinUbicacion.textContent = '— Sin ubicación —';
                select.appendChild(sinUbicacion);

                // Agrega todas las ubicaciones disponibles
                data.data.forEach(ub => {
                    const option = document.createElement('option');
                    option.value = ub.nombre;
                    option.textContent = `${ub.nombre} (${ub.descripcion})`;
                    select.appendChild(option);
                });
            }
        });
}

function cargarPendientesReubicacion() {
    fetch(ajaxurl + '?action=ajax_productos_pendientes_reubicacion')
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('tablaReubicacion');
            if (!data.success || data.data.productos.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-gray-500 py-4">No hay productos pendientes de reintegración.</td></tr>`;
                return;
            }

            let html = '';
            data.data.productos.forEach(prod => {
                html += `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <img src="${prod.imagen || ''}" width="60" class="rounded shadow-sm">
                        </td>
                        <td class="px-4 py-2">${prod.sku}</td>
                        <td class="px-4 py-2">${prod.nombre}</td>
                        <td class="px-4 py-2">
                            <button class="px-3 py-1 text-xs bg-green-600 text-white rounded asignar-ubicacion-btn"
                                    data-sku="${prod.sku}">
                                Asignar ubicación
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        });
}

// Ejecutar al cargar
document.addEventListener('DOMContentLoaded', cargarPendientesReubicacion);
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('asignar-ubicacion-btn')) {
        e.preventDefault();

        const sku = e.target.getAttribute('data-sku');
        if (!sku) return;

        skuAReintegrar = sku;

        Swal.fire({
            title: 'Escanea el QR del producto',
            text: 'Escanea primero el código del producto para reintegrarlo.',
            icon: 'info',
            confirmButtonText: 'Iniciar escaneo'
        }).then(() => {
            iniciarEscaneoQR(validarProductoReintegrado);
        });
    }
});
function iniciarEscaneoQR(callback) {
    const scannerDiv = document.getElementById('scanner');
    scannerDiv.innerHTML = ''; // limpiar
    scannerDiv.style.display = 'block';

    const html5QrCode = new Html5Qrcode("scanner");
    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        (decodedText) => {
            html5QrCode.stop().then(() => {
                scannerDiv.style.display = 'none';
                callback(decodedText);
            });
        },
        (errorMsg) => {}
    ).catch(err => {
        alert("No se pudo iniciar la cámara: " + err);
    });
}
function validarProductoReintegrado(textoQR) {
    let skuEscaneado = textoQR;

    // Extraer el sku si viene en formato de URL
    try {
        if (textoQR.includes('?sku=')) {
            const url = new URL(textoQR);
            const base = url.searchParams.get('sku');
            const hash = url.hash?.replace('#', '');
            skuEscaneado = hash ? `${base}#${hash}` : base;
        }
    } catch (e) {}

    if (skuEscaneado !== skuAReintegrar) {
        Swal.fire({
            icon: 'error',
            title: 'Producto incorrecto',
            text: `El SKU escaneado no coincide: ${skuEscaneado}`
        });
        return;
    }

    // Ahora pedir el QR de la ubicación
    Swal.fire({
        title: 'Producto validado',
        text: 'Ahora escanea el QR de la ubicación donde será reintegrado.',
        icon: 'success',
        confirmButtonText: 'Escanear ubicación'
    }).then(() => {
        iniciarEscaneoQR((qrUbicacion) => asignarUbicacionFinal(skuEscaneado, qrUbicacion));
    });
}
function asignarUbicacionFinal(sku, qrUbicacion) {
    if (!qrUbicacion.startsWith('ubicacion#')) {
        Swal.fire({
            icon: 'error',
            title: 'Ubicación no válida',
            text: 'El QR escaneado no corresponde a una ubicación.'
        });
        return;
    }

    const ubicacion = qrUbicacion.replace('ubicacion#', '');

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'asignar_lote_ubicacion',
            ubicacion,
            skus: JSON.stringify([sku])
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire('✅ Producto reintegrado', `Se asignó el SKU ${sku} a la ubicación correctamente.`, 'success');
            cargarPendientesReubicacion(); // actualizar listado
        } else {
            Swal.fire('Error', data.message || 'No se pudo asignar ubicación.', 'error');
        }
    });
}


// 📦 Mostrar historial asignados
function cargarHistorialAsignados(ubicacion = '') {
    fetch("<?= admin_url('admin-ajax.php') ?>?action=historial_productos_asignados&ubicacion=" + encodeURIComponent(ubicacion))
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('tablaHistorialAsignados');
            tbody.innerHTML = '';

            const total = data.data.length;
            document.getElementById('conteoProductosUbicacion').textContent = total;

            if (data.success && total > 0) {
                data.data.forEach(prod => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>
                            <img 
                                src="${prod.imagen || ''}" 
                                width="50" 
                                style="border-radius:4px;cursor:pointer;"
                                onclick="mostrarImagenProducto('${prod.imagen}', '${prod.nombre}')"
                            >
                        </td>
                        <td>${prod.sku}</td>
                        <td>${prod.nombre}</td>
                        <td class="hidden">${prod.descripcion}</td>
                        <td>${prod.ubicacion || '<em>—</em>'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-gray-500 py-4">No hay productos asignados.</td></tr>`;
            }
        });
}

// 🎯 Inicializar historial y eventos
document.addEventListener('DOMContentLoaded', () => {
    cargarUbicacionesEnFiltro();
    cargarHistorialAsignados();

    document.getElementById('filtroUbicacion').addEventListener('change', function () {
        cargarHistorialAsignados(this.value);
    });
    document.getElementById('inputBusquedaUbicacion').addEventListener('input', function () {
        const filtro = this.value.trim().toLowerCase();
        const filas = document.querySelectorAll('#tablaHistorialAsignados tr');

        filas.forEach(fila => {
            const celdaSKU = fila.querySelector('td:nth-child(2)');
            const celdaNombre = fila.querySelector('td:nth-child(3)');

            const textoSKU = celdaSKU?.textContent.trim().toLowerCase() || '';
            const textoNombre = celdaNombre?.textContent.trim().toLowerCase() || '';

            const coincide = textoSKU.includes(filtro) || textoNombre.includes(filtro);
            fila.style.display = coincide ? '' : 'none';
        });
    });
});
function mostrarImagenProducto(imagenUrl, titulo) {
    if (!imagenUrl) return;

    Swal.fire({
        title: titulo,
        imageUrl: imagenUrl,
        imageAlt: titulo,
        imageWidth: 'auto',
        imageHeight: 400,
        showCloseButton: true,
        confirmButtonText: 'Cerrar',
        customClass: {
            popup: 'swal2-popup-producto'
        }
    });
}
</script>
<style>
    .swal-custom-margin {
        margin-top: 70px !important; /* o el valor que necesites */
    }
    .swal2-popup-producto {
        max-width: 700px !important;
    }
    .qr-panel {
        background: #f9f9f9;
        border: 1px solid #ddd;
        padding: 20px;
        border-radius: 8px;
        max-width: 900px;
        margin: 20px auto;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .qr-panel h2 {
        margin-top: 0;
        font-size: 24px;
    }
    .producto-item {
        display: flex;
        align-items: center;
        border-bottom: 1px solid #eee;
        padding: 10px 0;
    }
    .producto-item img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 6px;
        margin-right: 12px;
    }
    .producto-info {
        display: flex;
        flex-direction: column;
    }
    .producto-info strong {
        font-size: 16px;
    }
    .popup-productos {
        background: white;
        padding: 20px;
        border-radius: 8px;
        max-height: 500px;
        overflow-y: auto;
    }
</style>
