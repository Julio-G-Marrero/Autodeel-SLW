<?php
if (!defined('ABSPATH')) exit;

include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
global $wpdb;

$solicitudes_aprobadas = $wpdb->get_results("
    SELECT s.id AS solicitud_id, s.autoparte_id, a.codigo, a.descripcion, p.ID AS producto_id
    FROM {$wpdb->prefix}solicitudes_piezas s
    INNER JOIN {$wpdb->prefix}autopartes a ON s.autoparte_id = a.id
    LEFT JOIN {$wpdb->postmeta} pm ON pm.meta_key = 'solicitud_id' AND pm.meta_value = s.id
    LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'product'
    WHERE s.estado = 'aprobada'
    ORDER BY s.fecha_envio DESC
");
?>

<div class="wrap">
    <h2 style="font-size: 24px; margin-bottom: 20px;">🧾 Impresión de Códigos QR de Autopartes Aprobadas</h2>

    <!-- Panel de Configuración de Impresión -->
    <div id="panelConfiguracion" style="margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
        <h3>Configuración de Impresión</h3>
        <div style="margin-bottom: 10px;">
            <label>
                <input type="radio" name="modoImpresion" value="todas" checked> Imprimir todas
            </label>
            <label style="margin-left: 20px;">
                <input type="radio" name="modoImpresion" value="seleccionadas"> Imprimir seleccionadas
            </label>
        </div>
        <div>
            <label for="posicionInicial">Posición inicial de impresión:</label>
            <input type="number" id="posicionInicial" min="1" value="1" style="width: 60px; margin-left: 10px;">
        </div>
        <button onclick="generarEtiquetas()" class="button button-primary" style="margin-bottom: 20px; margin-top: 20px;">
            Generar etiquetas para impresión
        </button>

    </div>

    <input type="text" id="filtroBusqueda" placeholder="Buscar por ID o descripción..."
        style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 20px;" />
    <div style="overflow-x: auto;">
        <table id="tablaSolicitudesQR" style="width: 100%; min-width: 900px; border-collapse: collapse; background: white; border: 1px solid #e2e2e2; border-radius: 6px; overflow: hidden;">
            <thead style="background: #f5f5f5;">
                <tr>
                    <th style="padding: 12px;"><input type="checkbox" id="selectAll"></th>
                    <th style="padding: 12px;">ID Solicitud</th>
                    <th style="padding: 12px;">Descripción</th>
                    <th style="padding: 12px;">SKU</th>
                    <th style="padding: 12px;">Producto</th>
                    <th style="padding: 12px;">Código QR</th>
                    <th style="padding: 12px;">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes_aprobadas as $s): 
                    $sku = $s->producto_id ? get_post_meta($s->producto_id, '_sku', true) : '';
                    $urlProducto = $sku ? home_url('/?sku=' . $sku) : '';
                    $qr_url = $urlProducto ? 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($urlProducto) : '';
                ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; text-align: center;">
                            <input type="checkbox" class="filaCheckbox">
                        </td>
                        <td style="padding: 12px; text-align: center;"><?= esc_html($s->solicitud_id) ?></td>
                        <td style="padding: 12px;"><?= esc_html($s->descripcion) ?></td>
                        <td style="padding: 12px;"><?= esc_html($sku) ?></td>
                        <td style="padding: 12px;">
                            <?php if ($s->producto_id): ?>
                                <a href="<?= esc_url(get_permalink($s->producto_id)) ?>" target="_blank" style="color: #0073aa; text-decoration: underline;">Ver producto</a>
                            <?php else: ?>
                                <span style="color: #999; font-style: italic;">No creado</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px;">
                            <?php if ($qr_url): ?>
                                <img src="<?= esc_url($qr_url) ?>" alt="QR" style="width: 100px;" />
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px;">
                            <?php if ($qr_url): ?>
                                <button onclick="imprimirQR('<?= esc_js($qr_url) ?>', '<?= esc_js($s->descripcion) ?>')"
                                    style="background: #0073aa; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                                    Imprimir
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Selección/deselección de todos los checkbox
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.filaCheckbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Filtro de búsqueda en la tabla (actualiza índices debido a la nueva columna)
document.getElementById('filtroBusqueda').addEventListener('input', function () {
    const filtro = this.value.toLowerCase();
    const filas = document.querySelectorAll('#tablaSolicitudesQR tbody tr');

    filas.forEach(fila => {
        const id = fila.children[1]?.textContent.toLowerCase();
        const descripcion = fila.children[2]?.textContent.toLowerCase();

        if (id.includes(filtro) || descripcion.includes(filtro)) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
});

function generarEtiquetas() {
    const filas = document.querySelectorAll('#tablaSolicitudesQR tbody tr');
    const etiquetas = [];

    // Obtener configuración de impresión
    const modoImpresion = document.querySelector('input[name="modoImpresion"]:checked').value;
    const posicionInicial = parseInt(document.getElementById('posicionInicial').value) || 1;

    filas.forEach(fila => {
        if (fila.style.display !== 'none') {
            // Si se seleccionó "imprimir seleccionadas", verificar el checkbox de la fila
            if (modoImpresion === 'seleccionadas') {
                const checkbox = fila.querySelector('.filaCheckbox');
                if (!checkbox || !checkbox.checked) {
                    return; // Saltar fila no seleccionada
                }
            }
            // Debido a la nueva columna, el ID se encuentra en children[1], SKU en children[3] y la imagen QR en children[5]
            const solicitudID = fila.children[1].innerText.trim();
            const sku = fila.children[3].innerText.trim();
            const qrImg = fila.children[5].querySelector('img')?.src;
            if (sku && qrImg) {
                etiquetas.push({ solicitudID, sku, qrImg });
            }
        }
    });

    if (etiquetas.length === 0) {
        alert("No hay etiquetas visibles para imprimir.");
        return;
    }

    // Agregar etiquetas en blanco según la posición inicial (para saltar celdas ya usadas)
    const etiquetasFinal = [];
    for (let i = 1; i < posicionInicial; i++) {
         etiquetasFinal.push(null); // Etiqueta en blanco
    }
    etiquetasFinal.push(...etiquetas);

    const win = window.open('', '_blank');
    win.document.write(`
        <html>
        <head>
            <title>Impresión de Etiquetas Avery 5263</title>
            <style>
                @page {
                    size: 8.5in 11in;
                    margin: 0.5in;
                }
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 0;
                }
                .contenedor {
                    display: grid;
                    grid-template-columns: repeat(2, 3.94in);
                    grid-auto-rows: 2in;
                    gap: 0.125in;
                    margin: 0 auto;
                    padding: 0;
                }
                .etiqueta {
                    width: 3.94in;
                    height: 2in;
                    box-sizing: border-box;
                    padding: 0.1in;
                    display: flex;
                    align-items: center;
                    gap: 0.7in;
                    page-break-inside: avoid;
                }
                .etiqueta img {
                    width: 1.5in;
                    height: 1.5in;
                    object-fit: contain;
                }
                .info {
                    display: flex;
                    flex-direction: column;
                    font-size: 12pt;
                    justify-content: center;
                }
                .info-label {
                    font-weight: bold;
                }
                @media print {
                    .etiqueta {
                        page-break-inside: avoid;
                        break-inside: avoid;
                    }
                }
            </style>
        </head>
        <body>
            <div class="contenedor">
                ${etiquetasFinal.map(et => {
                    if (et === null) {
                        // Espacio en blanco para posición sin imprimir
                        return '<div class="etiqueta"></div>';
                    }
                    return `
                        <div class="etiqueta">
                            <img src="${et.qrImg}" alt="QR">
                            <div class="info">
                                <div class="info-label">SKU:</div>
                                <div>${et.sku}</div>
                                <div class="info-label">Solicitud:</div>
                                <div>${et.solicitudID}</div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
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

function imprimirQR(url, descripcion) {
    const win = window.open('', '_blank');
    win.document.write(`
        <html>
        <head><title>Imprimir QR</title></head>
        <body style="text-align:center; padding: 40px; font-family: sans-serif;">
            <h2>${descripcion}</h2>
            <img src="${url}" style="width:200px; height:200px; margin-top: 20px;" />
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
</script>
