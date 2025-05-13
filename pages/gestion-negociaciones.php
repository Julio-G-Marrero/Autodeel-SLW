<?php
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
?>
<div class="wrap ml-4">
    <h1 class="wp-heading-inline">Gestión de Negociaciones</h1>
    <p class="description">Revisa y responde las solicitudes de negociación de precios enviadas desde el punto de venta.</p>

    <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
        <input type="text" id="busquedaCliente" placeholder="Buscar por cliente..." 
            style="padding: 6px 12px; border: 1px solid #ccc; border-radius: 6px; flex-grow: 1; max-width: 300px;" />
        <button onclick="filtrarPorCliente()" class="button">Buscar</button>
    </div>

    <div id="filtrosEstado" style="margin-top: 20px; display: flex; gap: 10px;">
        <button data-estado="" class="filtro-tab active-tab">Todos</button>
        <button data-estado="pendiente" class="filtro-tab">Pendientes</button>
        <button data-estado="aprobado" class="filtro-tab">Aprobadas</button>
        <button data-estado="rechazado" class="filtro-tab">Rechazadas</button>
    </div>

    <div id="negociacionesLista" class="mt-4" style="margin-top: 20px;"></div>
</div>
<style>
.filtro-tab {
    padding: 6px 12px;
    background: #f1f1f1;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
}
.filtro-tab:hover {
    background: #e2e8f0;
}
.active-tab {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}
button#nextImg:hover {
    cursor: pointer;
}
button#prevImg:hover {
    cursor: pointer;

}
</style>
<script>
function filtrarPorCliente() {
    const cliente = document.getElementById('busquedaCliente').value.trim();
    const activeTab = document.querySelector('.filtro-tab.active-tab');
    const estado = activeTab ? activeTab.dataset.estado : '';
    cargarNegociaciones(estado, cliente);
}
document.getElementById('busquedaCliente').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') filtrarPorCliente();
});

function cargarNegociaciones(estado = '', clienteFiltro = '') {
    const url = new URL(ajaxurl);
    url.searchParams.set('action', 'ajax_admin_listar_negociaciones');
    if (estado) url.searchParams.set('estado', estado);
    if (clienteFiltro) url.searchParams.set('cliente', clienteFiltro.toLowerCase());

    fetch(url)
        .then(r => r.json())
        .then(res => {
            const contenedor = document.getElementById('negociacionesLista');
            if (!res.success || res.data.length === 0) {
                contenedor.innerHTML = '<p>No hay negociaciones registradas.</p>';
                return;
            }

            const cards = res.data.map(n => {
                let estadoTexto = 'Pendiente', color = 'orange';
                if (n.estado === 'aprobado') estadoTexto = 'Aprobado', color = 'green';
                if (n.estado === 'rechazado') estadoTexto = 'Rechazado', color = 'red';

                const imagenes = Array.isArray(n.imagenes) ? n.imagenes : [];
                const galeria = imagenes.length > 0
                    ? `<img src="${imagenes[0]}" class="miniatura-negociacion" data-index="0" data-imagenes='${JSON.stringify(imagenes)}'
                        style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #ccc; margin-right: 10px; cursor: pointer;" />`
                    : '<div style="width:80px;height:80px;background:#eee;display:flex;align-items:center;justify-content:center;border-radius:6px;">Sin imagen</div>';

                return `
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 20px; border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 6px;">
                        <div style="display: flex; align-items: center;">${galeria}</div>
                        <div>
                            <strong style="font-size: 16px;">${n.nombre_producto}</strong><br>
                            SKU: <code>${n.producto_sku}</code><br>
                            Cliente: ${n.cliente_nombre || n.cliente_correo}<br>
                            Precio original: $${parseFloat(n.precio_original).toFixed(2)}<br>
                            Precio solicitado: <strong style="color:green;">$${parseFloat(n.precio_solicitado).toFixed(2)}</strong><br>
                            Estado: <span style="color:${color}; font-weight:bold;">${estadoTexto}</span><br>
                            Motivo: <em>${n.motivo || 'Sin especificar'}</em><br>
                            ${n.estado === 'pendiente' ? `
                                <div style="margin-top:10px;">
                                    <button onclick="responderNegociacion(${n.id}, 'aprobar')" class="button button-primary">Aprobar</button>
                                    <button onclick="responderNegociacion(${n.id}, 'rechazar')" class="button">Rechazar</button>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');

            contenedor.innerHTML = cards;
        });
}

function responderNegociacion(id, accion) {
    Swal.fire({
        title: `${accion === 'aprobar' ? 'Aprobar' : 'Rechazar'} negociación`,
        input: 'textarea',
        inputLabel: 'Comentario (opcional)',
        inputPlaceholder: `Escribe un comentario para ${accion === 'aprobar' ? 'aprobar' : 'rechazar'} esta solicitud`,
        showCancelButton: true,
        confirmButtonText: accion === 'aprobar' ? 'Aprobar' : 'Rechazar',
        confirmButtonColor: accion === 'aprobar' ? '#16a34a' : '#dc2626',
        cancelButtonText: 'Cancelar',
        inputAttributes: {
            rows: 4,
            style: 'resize: none;'
        },
        preConfirm: (comentario) => {
            const formData = new URLSearchParams({
                action: 'ajax_responder_negociacion_precio',
                id: id,
                accion: accion,
                comentario: comentario || ''
            });

            return fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (!res.success) throw new Error(res.data || 'Error al procesar.');
                return res.data;
            })
            .catch(error => {
                Swal.showValidationMessage(`${error.message}`);
            });
        }
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Solicitud procesada',
                text: result.value,
                timer: 2000,
                showConfirmButton: false
            });

            const activeTab = document.querySelector('.filtro-tab.active-tab');
            cargarNegociaciones(activeTab ? activeTab.dataset.estado : '');
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    cargarNegociaciones();

    document.querySelectorAll('.filtro-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.filtro-tab').forEach(t => t.classList.remove('active-tab'));
            this.classList.add('active-tab');
            cargarNegociaciones(this.dataset.estado);
        });
    });
});

// 🖼️ Popup galería de imágenes con navegación
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('miniatura-negociacion')) {
        const imagenes = JSON.parse(e.target.dataset.imagenes);
        let current = parseInt(e.target.dataset.index);

        const galeriaHTML = imagenes.map((img, i) =>
            `<img src="${img}" style="max-width:100%; max-height:70vh; display:${i === current ? 'block' : 'none'};" class="imagen-slide" data-index="${i}" />`
        ).join('');

        Swal.fire({
            title: 'Galería del producto',
            html: `
                <div style="position:relative;text-align:center;min-height:300px;">
                    ${imagenes.map((img, i) =>
                        `<img src="${img}" style="max-height:70vh; display:${i === current ? 'block' : 'none'}; margin: 0 auto;" class="imagen-slide" data-index="${i}" />`
                    ).join('')}
                    <button id="prevImg" style="position:absolute;left:0;top:50%;transform:translateY(-50%);background:#fff;border:none;padding:8px;font-size:18px;"><svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/>
                        </svg>
                        </button>
                                            <button id="nextImg" style="position:absolute;right:0;top:50%;transform:translateY(-50%);background:#fff;border:none;padding:8px;font-size:18px;"><svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7"/>
                        </svg>
                        </button>
                </div>
            `,
            width: '40%',
            showConfirmButton: false,
            padding: '1.5rem',
            background: '#fefefe',
            customClass: {
                popup: 'rounded-lg'
            },
            didOpen: () => {
                const imgs = Swal.getPopup().querySelectorAll('.imagen-slide');
                const show = i => imgs.forEach((img, idx) => img.style.display = idx === i ? 'block' : 'none');

                document.getElementById('prevImg').onclick = () => {
                    current = (current - 1 + imgs.length) % imgs.length;
                    show(current);
                };
                document.getElementById('nextImg').onclick = () => {
                    current = (current + 1) % imgs.length;
                    show(current);
                };
            }
        });
    }
});
</script>
