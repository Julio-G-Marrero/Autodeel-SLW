<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
global $wpdb;

?>
<div class="wrap">
    <h2>Asignar Productos a Ubicaciones (por QR)</h2>
    <button id="btnEscanearQR" class="button button-secondary mb-2">Activar cámara</button>
    <div id="scanner" class="mb-4" style="width: 300px; display:none;"></div>

    <input type="text" id="scanInput" placeholder="Escanea una ubicación o producto..." class="w-full p-3 border rounded mb-4" autofocus />

    <div id="resultadoBusqueda" class="mt-4"></div>

    <div id="ubicacionActiva" class="hidden p-4 border rounded bg-green-50 mb-4">
        <h3 class="text-lg font-semibold">Ubicación activa:</h3>
        <p><strong>ID:</strong> <span id="ubicacionID"></span></p>
        <p><strong>Nombre:</strong> <span id="ubicacionNombre"></span></p>
        <p><strong>Descripción:</strong> <span id="ubicacionDescripcion"></span></p>
        <div id="ubicacionImagen"></div>
    </div>

    <div id="productosAsignados" class="hidden">
        <h3 class="text-lg font-semibold">Productos escaneados para asignar:</h3>
        <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>Imagen</th>
                <th>SKU</th>
                <th>Nombre</th>
                <th>Descripción</th>
            </tr>
        </thead>

            <tbody id="tablaProductos"></tbody>
        </table>
        <button id="btnFinalizar" class="button button-primary mt-4">Finalizar asignación</button>
    </div>
</div>

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
