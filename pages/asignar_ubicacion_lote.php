<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual

global $wpdb;

?>
<div class="max-w-4xl mx-auto px-4 py-6">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Asignar Productos a Ubicaciones (por QR)</h2>

    <!-- Botón de cámara -->
    <button id="btnEscanearQR" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800 transition mb-4">
        Activar cámara
    </button>

    <!-- Área del escáner -->
    <div id="scanner" class="hidden mb-6 border border-gray-300 rounded p-4 bg-white w-full max-w-sm"></div>

    <!-- Campo de entrada -->
    <input
        type="text"
        id="scanInput"
        placeholder="Escanea una ubicación o producto..."
        class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring focus:border-blue-300 mb-6"
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
    });
});
</script>
<style>
    .swal-custom-margin {
        margin-top: 70px !important; /* o el valor que necesites */
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
