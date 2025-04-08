<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual

global $wpdb;

$tabla = $wpdb->prefix . 'ubicaciones_autopartes';

// Procesar nueva ubicación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_ubicacion'])) {
    $nombre = sanitize_text_field($_POST['nombre']);
    $descripcion = sanitize_text_field($_POST['descripcion']);

    $foto_url = null;

    if (!empty($_FILES['foto_ubicacion']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $file = $_FILES['foto_ubicacion'];
        $overrides = ['test_form' => false];

        $uploaded = wp_handle_upload($file, $overrides);

        if (!isset($uploaded['error'])) {
            $foto_url = $uploaded['url'];
        }
    }

    $wpdb->insert($wpdb->prefix . 'ubicaciones_autopartes', [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'codigo_qr' => '',
        'imagen_url' => $foto_url
    ]);

    $ubicacion_id = $wpdb->insert_id;
    $codigo_qr = 'ubicacion#' . $ubicacion_id;

    $wpdb->update($wpdb->prefix . 'ubicaciones_autopartes', [
        'codigo_qr' => $codigo_qr
    ], ['id' => $ubicacion_id]);

    echo "<div class='updated'><p>Ubicación agregada correctamente.</p></div>";
}

// Procesar eliminación
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $wpdb->delete($tabla, ['id' => intval($_GET['eliminar'])]);
    echo "<div class='updated'><p>Ubicación eliminada correctamente.</p></div>";
}

$ubicaciones = $wpdb->get_results("SELECT * FROM $tabla ORDER BY id DESC");
?>

<div class="ubicaciones-wrap">
    <h2>Gestión de Ubicaciones Físicas</h2>

    <div class="form-card">
        <h3>➕ Agregar Nueva Ubicación</h3>
        <form method="POST" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="nueva_ubicacion" value="1">

            <label>Nombre:
                <input type="text" name="nombre" required>
            </label>

            <label>Descripción (opcional):
                <textarea name="descripcion" rows="3"></textarea>
            </label>

            <label>Imagen (opcional):
                <input type="file" name="foto_ubicacion" accept="image/*" capture="environment">
            </label>

            <button type="submit" class="btn-primario">Agregar Ubicación</button>
        </form>
    </div>

    <hr>

    <h3>Ubicaciones Registradas</h3>
    <div class="table-container">
        <table class="tabla-ubicaciones">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Imagen</th>
                    <th>QR</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ubicaciones as $u): ?>
                    <tr>
                        <td><?= esc_html($u->id) ?></td>
                        <td><?= esc_html($u->nombre) ?></td>
                        <td><?= esc_html($u->descripcion) ?></td>
                        <td>
                            <?php if (!empty($u->imagen_url)) : ?>
                                <img src="<?= esc_url($u->imagen_url) ?>" 
                                    class="img-mini" 
                                    style="cursor: zoom-in; border-radius: 6px;" 
                                    width="80"
                                    onclick="verImagenGrande('<?= esc_url($u->imagen_url) ?>', 'Imagen de la ubicación')">
                            <?php else: ?>
                                <em>Sin imagen</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($u->codigo_qr)) : ?>
                                <?php $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($u->codigo_qr); ?>
                                <div class="qr-actions">
                                    <img src="<?= esc_url($qr_url) ?>" class="img-qr" onclick="verQR('<?= esc_url($qr_url) ?>')">
                                    <button type="button" class="btn-secundario" onclick="imprimirQR('<?= esc_url($qr_url) ?>', '<?= esc_js($u->nombre) ?>')">Imprimir</button>
                                    </div>
                            <?php else: ?>
                                <em>Sin QR</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class=" btn-secundario btn-link" href="<?= admin_url('admin.php?page=gestion-ubicaciones&eliminar=' . $u->id) ?>" onclick="return confirm('¿Eliminar esta ubicación?')">Eliminar</a>
                            <button class="btn-secundario ver-productos-btn" data-ubicacion="<?= esc_attr($u->nombre) ?>">Ver productos</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SweetAlert2 + QR -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function verQR(qrURL) {
    Swal.fire({
        title: 'Código QR',
        html: `<img src="${qrURL}" alt="QR" style="width:250px;">`,
        showCloseButton: true,
        showConfirmButton: false
    });
}
function verImagenGrande(url, titulo = 'Vista previa') {
    Swal.fire({
        title: titulo,
        html: `<img src="${url}" alt="Imagen" style="max-width:100%; border-radius:8px;">`,
        showCloseButton: true,
        showConfirmButton: false,
        customClass: {
            popup: 'popup-img-preview'
        }
    });
}
function imprimirQR(qrURL, nombreUbicacion) {
    const win = window.open('', '_blank');
        win.document.write(`
        <html>
            <head>
                <title>Imprimir QR</title>
                <style>
                    body {
                        text-align: center;
                        padding: 20px;
                        font-family: sans-serif;
                    }
                    h2 {
                        margin-bottom: 20px;
                    }
                </style>
            </head>
            <body>
                <h2>Ubicación: ${nombreUbicacion}</h2>
                <img src="${qrURL}" style="width:300px;"><br>
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() {
                            window.close();
                        };
                    };
                <\/script>
            </body>
        </html>
    `);
    win.document.close();
}

function verProductosUbicacion(nombreUbicacion) {
    fetch("<?= admin_url('admin-ajax.php') ?>?action=productos_por_ubicacion&ubicacion=" + encodeURIComponent(nombreUbicacion))
    .then(res => res.json())
    .then(response => {
        if (response.success && response.data && Array.isArray(response.data.productos)) {
            const productos = response.data.productos;

            if (productos.length > 0) {
                let html = '<div class="popup-productos">';
                productos.forEach(p => {
                    html += `
                        <div class="producto-item">
                            <img src="${p.imagen}" alt="imagen" class="producto-img">
                            <div class="producto-info">
                                <strong>${p.nombre}</strong>
                                <span class="sku">(SKU: ${p.sku})</span>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';

                Swal.fire({
                    title: '📦 Productos en esta ubicación',
                    html: html,
                    width: 700,
                    customClass: {
                        popup: 'swal-productos-popup'
                    },
                    confirmButtonText: 'Cerrar'
                });
            } else {
                Swal.fire('Sin resultados', 'No se encontraron productos en esta ubicación.', 'info');
            }
        } else {
            Swal.fire('Error', 'No se pudieron cargar los productos.', 'error');
        }
    })
    .catch(err => {
        console.error('Error al obtener productos:', err);
        Swal.fire('Error', 'Fallo al obtener los productos.', 'error');
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ver-productos-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const nombreUbicacion = this.getAttribute('data-ubicacion');
            verProductosUbicacion(nombreUbicacion);
        });
    });
});

</script>
<style>
.ubicaciones-wrap {
    padding: 20px;
    max-width: 1000px;
    margin: auto;
    font-family: sans-serif;
}

.form-card {
    background: #f7f7f7;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.form-grid label {
    display: block;
    margin-bottom: 15px;
}

.form-grid input[type="text"],
.form-grid textarea,
.form-grid input[type="file"] {
    width: 100%;
    padding: 8px;
    margin-top: 4px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.btn-primario {
    background-color: #2271b1;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 10px;
}

.btn-secundario {
    background-color: #e2e8f0;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 6px;
}

.btn-link {
    display: inline-block;
    margin-bottom: 6px;
    color: #b32d2e;
    text-decoration: underline;
}

.img-mini {
    width: 70px;
    height: auto;
    border-radius: 6px;
}

.img-qr {
    width: 60px;
    cursor: pointer;
    margin-bottom: 4px;
}

.qr-actions {
    text-align: center;
}

.table-container {
    overflow-x: auto;
}

.tabla-ubicaciones {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.tabla-ubicaciones th,
.tabla-ubicaciones td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

@media (max-width: 768px) {
    .tabla-ubicaciones th, .tabla-ubicaciones td {
        padding: 8px;
        font-size: 13px;
    }

    .img-mini, .img-qr {
        width: 50px;
    }
}
.popup-productos {
    max-height: 400px;
    overflow-y: auto;
    padding: 5px 0;
}

.producto-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.producto-img {
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
    font-size: 15px;
    color: #333;
}

.producto-info .sku {
    font-size: 13px;
    color: #666;
}
.popup-img-preview {
    max-width: 90vw;
    max-height: 90vh;
}
</style>