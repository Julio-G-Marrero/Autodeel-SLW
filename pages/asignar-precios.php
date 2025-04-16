<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
global $wpdb;
$productos = get_posts([
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => 100,
    'meta_query' => [
        [
            'key' => '_price',
            'value' => 3,
            'compare' => '<=',
            'type' => 'NUMERIC'
        ]
    ]
]);
?>
<!-- SwiperJS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
<div class="wrap ml-4">
    <h2>Asignar Precios a Autopartes</h2>

    <div style="overflow-x:auto; margin-top: 20px;">
        <table class="wp-list-table widefat striped" style="min-width: 600px; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f1f1f1; text-align: left;">
                    <th style="padding: 10px;">Imagen</th>
                    <th style="padding: 10px;">Producto</th>
                    <th style="padding: 10px;">SKU</th>
                    <th style="padding: 10px;">Precio Actual</th>
                    <th style="padding: 10px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $prod): 
                    $sku = get_post_meta($prod->ID, '_sku', true);
                    $precio = get_post_meta($prod->ID, '_price', true);
                    $imagen_id = get_post_thumbnail_id($prod->ID);
                    $imagen_url = wp_get_attachment_image_url($imagen_id, 'full');
                    $estado = get_post_meta($prod->ID, 'estado_pieza', true);
                    $observaciones = get_post_meta($prod->ID, 'observaciones', true);
                    $descripcion = get_post_field('post_content', $prod->ID);

                    $galeria_urls = [];
                    if ($imagen_url) $galeria_urls[] = esc_url($imagen_url);

                    $galeria_ids = get_post_meta($prod->ID, '_product_image_gallery', true);
                    if (!empty($galeria_ids)) {
                        $ids = explode(',', $galeria_ids);
                        foreach ($ids as $id) {
                            $url = wp_get_attachment_url($id);
                            if ($url) $galeria_urls[] = esc_url($url);
                        }
                    }
                ?>
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 8px;">
                            <img src="<?= esc_url($imagen_url) ?>" alt="imagen" width="60" style="cursor:pointer; border-radius: 4px;"
                                onclick="verImagenAmpliada('<?= esc_url($imagen_url) ?>')">
                        </td>
                        <td style="padding: 8px;"><?= esc_html($prod->post_title) ?></td>
                        <td style="padding: 8px;"><?= esc_html($sku) ?></td>
                        <td style="padding: 8px;">$<?= esc_html($precio) ?></td>
                        <td style="padding: 8px;">
                            <button class="button button-primary"
                                onclick='abrirModalAsignarPrecio(
                                    <?= $prod->ID ?>,
                                    <?= json_encode($sku) ?>,
                                    <?= json_encode($prod->post_title) ?>,
                                    <?= json_encode($galeria_urls) ?>,
                                    <?= json_encode($descripcion) ?>
                                )'>
                                Asignar Precio
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para ver imagen -->
<div id="modalImagen" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
    <div style="position:relative;">
        <button onclick="cerrarModalImagen()" style="position:absolute;top:10px;right:10px;font-size:24px;color:white;background:none;border:none;cursor:pointer">&times;</button>
        <img id="imagenAmpliada" src="" style="max-width:90vw; max-height:90vh; border:4px solid white;">
    </div>
</div>
<script>
function verImagenAmpliada(src) {
    document.getElementById('imagenAmpliada').src = src;
    document.getElementById('modalImagen').style.display = 'flex';
}

function cerrarModalImagen() {
    document.getElementById('modalImagen').style.display = 'none';
}

document.getElementById('modalImagen').addEventListener('click', function(e) {
    if (e.target.id === 'modalImagen') cerrarModalImagen();
});

function simplificarTitulo(titulo) {
    let limpio = titulo.toUpperCase();

    // 1. Eliminar marcas comerciales y de catálogo
    limpio = limpio.replace(/\b(DEPO|TYC|JP|EAG|VALEO|MAGNETI MARELLI|DORMAN)\b/g, '');

    // 2. Eliminar versiones del modelo
    limpio = limpio.replace(/\b(LX|EX|GL|GLS|DX|XLE|SE|SL|SR|SR5|S|LE|LT|LS|XLT|XL|AUT|STD|MT|AT|TIP|EXL|Z71)\b/g, '');

    // 3. Eliminar especificaciones como C/FOCO, S/FOCO, LED, etc.
    limpio = limpio.replace(/\b(C\/|S\/)?(FOCO|LUZ DE DIA|BASE|LED|HALOGENO|NEBLINERO|DIA)\b/g, '');

    // 4. Eliminar indicaciones de lado
    limpio = limpio.replace(/\b(IZQ|DER|IZQUIERDO|DERECHO|LADO IZQUIERDO|LADO DERECHO)\b/g, '');

    // 5. Reemplazar guiones, slashes, puntos por espacio
    limpio = limpio.replace(/[-/.,]/g, ' ');

    // 6. Eliminar duplicado "FARO FARO"
    limpio = limpio.replace(/\b(FARO)\b\s+\1/g, '$1');

    // 7. Extraer el primer año del rango
    const anioMatch = limpio.match(/\b(\d{2,4})\s*[-–—]\s*(\d{2,4})\b/);
    let anio = '';
    if (anioMatch) {
        const raw = anioMatch[1];
        anio = raw.length === 2 ? `20${raw}` : raw;
    } else {
        const fallback = limpio.match(/\b(\d{2,4})\b/);
        if (fallback) {
            anio = fallback[1].length === 2 ? `20${fallback[1]}` : fallback[1];
        }
    }

    // 8. Quitar todos los años del texto
    limpio = limpio.replace(/\b\d{2,4}\b/g, '');

    // 9. Limpiar múltiples espacios
    limpio = limpio.replace(/\s+/g, ' ').trim();

    // 10. Retornar resultado final
    return `${limpio.toLowerCase()} ${anio}`.trim();
}

function extraerMarcaModelo(texto) {
    // Ejemplo: "HD CR-V LX/EX/EXL 17-20" => "HD CR-V"
    return texto
        .replace(/LX|EX|DX|GL|GLS|AUT|STD|MT|AT|TIP/gi, '')
        .replace(/\/|-/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function abrirModalAsignarPrecio(id, sku, titulo, galeriaArray, descripcion) {
    const tituloSimplificado = simplificarTitulo(titulo);
    const skuBase = sku.split('#')[0];

    const carruselHTML = `
        <div class="popup-col-imagenes">
            <div class="swiper mySwiper">
                <div class="swiper-wrapper">
                    ${galeriaArray.map(url => `
                        <div class="swiper-slide">
                            <div class="zoom-hover-container">
                                <img src="${url}" alt="Imagen del producto" class="zoom-hover-img">
                            </div>
                        </div>
                    `).join('')}
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>
    `;

    const infoHTML = `
        <div class="popup-col-info">
            <p><strong>ID:</strong> ${id}</p>
            <p><strong>SKU:</strong> ${sku}</p>
            <p><strong>Título:</strong> ${titulo}</p>
            <div style="margin-top:10px;">
                <strong>Descripción del producto:</strong>
                <div style="font-style:italic; color:#555; border-left: 3px solid #ccc; padding-left: 8px;">
                    ${descripcion || 'Sin descripción'}
                </div>
            </div>
            <div style="margin:15px 0;">
                <strong>Búsquedas rápidas:</strong><br>
                <a href="https://listado.mercadolibre.com.mx/${encodeURIComponent(tituloSimplificado)}" target="_blank">🟡 Mercado Libre</a> |
                <a href="https://www.google.com/search?tbm=shop&q=${encodeURIComponent(tituloSimplificado)}" target="_blank">🔵 Google Shopping</a> |
                <a href="https://www.facebook.com/marketplace/search/?query=${encodeURIComponent(tituloSimplificado)}" target="_blank">🔵 Facebook Marketplace</a>
            </div>
            <div id="preciosReferencia" style="margin-bottom:10px;"></div>
            <label>Precio Nuevo: <input type="number" id="nuevoPrecio_${id}" class="swal2-input" placeholder="Ej. 850" step="0.01"></label>
        </div>
    `;

    Swal.fire({
        title: 'Asignar Precio',
        width: '90%',
        html: `
            <div class="asignar-precio-popup">
                ${carruselHTML}
                ${infoHTML}
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        didOpen: () => {
            // Activar zoom táctil/desktop en imágenes
            mediumZoom('.swiper-slide img', {
                margin: 24,
                background: 'rgba(0, 0, 0, 0.8)',
                scrollOffset: 40
            });

            new Swiper('.mySwiper', {
                loop: true,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
            });

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'obtener_precio_por_sku',
                    sku: skuBase
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    let html = `
                        <div>
                            <h4 style="margin-bottom: 10px; font-weight: 600; color: #333;">
                                Precios de referencia para SKU sin IVA <span style="color:#0073aa;">${skuBase}</span>:
                            </h4>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                    `;

                    data.data.forEach(p => {
                        html += `
                            <li style="padding: 8px 0; border-bottom: 1px solid #ddd;">
                                <strong>${p.catalogo}:</strong>
                                <span style="margin-left: 10px; color: #555;">
                                    Proveedor: <strong>$${parseFloat(p.precio_proveedor).toFixed(2)}</strong>,
                                    Público: <strong>$${parseFloat(p.precio_publico).toFixed(2)}</strong>
                                </span>
                            </li>
                        `;
                    });

                    html += `</ul></div>`;

                    const contenedorRef = document.getElementById('preciosReferencia');
                    if (contenedorRef) contenedorRef.innerHTML = html;
                }
            });
        },
        preConfirm: () => {
            const precio = parseFloat(document.getElementById(`nuevoPrecio_${id}`).value);
            if (!precio || precio <= 0) {
                Swal.showValidationMessage('Ingresa un precio válido');
                return;
            }
            return { id, precio };
        }
    }).then(result => {
        if (result.isConfirmed) {
            const formData = new URLSearchParams();
            formData.append('action', 'guardar_precio_autoparte');
            formData.append('id', result.value.id);
            formData.append('precio', result.value.precio);

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Guardado', 'El precio fue actualizado correctamente.', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', 'No se pudo guardar el precio.', 'error');
                }
            });
        }
    });
}

function cambiarImagen(idImg, idProd, dir) {
    const galeria = window['galeria_' + idProd];
    let actual = window['indice_' + idProd];

    actual += dir;
    if (actual < 0) actual = galeria.length - 1;
    if (actual >= galeria.length) actual = 0;

    const imgEl = document.getElementById(idImg);
    imgEl.src = galeria[actual];
    imgEl.setAttribute('onclick', `abrirZoomImagen('${galeria[actual]}')`);

    window['indice_' + idProd] = actual;
}

function cerrarZoomImagen() {
    document.getElementById('modalZoomImagen').style.display = 'none';
}

function abrirZoomImagen(src) {
    const modal = document.getElementById('modalZoomImagen');
    const img = document.getElementById('imagenZoom');

    img.src = src;
    modal.style.display = 'flex';

    // Agregar el listener aquí, una vez que el modal existe en el DOM
    modal.addEventListener('click', function handleOutsideClick(e) {
        if (e.target.id === 'modalZoomImagen') {
            cerrarZoomImagen();
            modal.removeEventListener('click', handleOutsideClick); // limpiamos el evento
        }
    });
}
document.addEventListener('mouseover', function (e) {
    if (e.target.classList.contains('zoom-hover-img')) {
        const img = e.target;
        img.addEventListener('mousemove', function (ev) {
            const rect = img.getBoundingClientRect();
            const x = ((ev.clientX - rect.left) / rect.width) * 100;
            const y = ((ev.clientY - rect.top) / rect.height) * 100;
            img.style.transformOrigin = `${x}% ${y}%`;
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/medium-zoom@1.0.6/dist/medium-zoom.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.img-zoom-wrapper {
  position: relative;
  overflow: hidden;
  display: inline-block;
  width: 100%;
  max-height: 300px;
}

.img-zoom-wrapper img {
  transition: transform 0.3s ease;
  max-width: 100%;
  height: auto;
  display: block;
  margin: 0 auto;
}

.img-zoom-wrapper:hover img {
  transform: scale(1.8);
  cursor: zoom-in;
}

.swiper-slide {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 300px;
  overflow: hidden;
}

.swiper-slide img {
  transition: transform 0.3s ease;
  max-height: 100%;
  object-fit: contain;
}

.swiper-slide:hover img {
  transform: scale(1.6); /* Zoom al pasar el mouse */
  cursor: zoom-in;
}

@media (max-width: 768px) {
  .swal2-popup {
    width: 95% !important;
  }

  .swiper-slide img {
    max-height: 200px !important;
  }
}
@media (min-width: 768px) {
  .asignar-precio-popup {
    display: flex;
    flex-direction: row;
    gap: 20px;
  }
  .popup-col-imagenes {
    flex: 1;
    max-width: 50%;
  }
  .popup-col-info {
    flex: 1;
    max-width: 50%;
  }
}
@media (max-width: 767px) {
  .asignar-precio-popup {
    display: block;
  }
  .popup-col-imagenes,
  .popup-col-info {
    max-width: 100%;
    width: 100%;
  }
    .swiper-slide {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 300px;
        overflow: hidden;  
    }
}
.swiper-slide img {
  transition: transform 0.3s ease;
  max-height: 100%;
  object-fit: contain;
}

.swiper-slide:hover img {
  transform: scale(1.6);
  cursor: zoom-in;
}
.asignar-precio-popup {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
}

.popup-col-imagenes {
    flex: 1;
    min-width: 300px;
    max-width: 400px;
}

.popup-col-info {
    flex: 1;
    min-width: 300px;
}

.zoom-container {
    position: relative;
    overflow: hidden;
}

.zoom-img {
    width: 100%;
    transition: transform 0.3s ease;
    object-fit: contain;
}

.zoom-img:hover {
    transform: scale(1.5);
    z-index: 2;
}
.zoom-hover-container {
    position: relative;
    overflow: hidden;
    width: 100%;
    height: 300px;
    border-radius: 8px;
    border: 1px solid #ccc;
}
.medium-zoom-overlay {
    z-index: 1061 !important; /* por encima del swal (1060) */
}

.medium-zoom-image--opened {
    z-index: 1062 !important; /* imagen aún más arriba */
}

.zoom-hover-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: transform 0.2s ease, transform-origin 0.2s ease;
}

.zoom-hover-container:hover .zoom-hover-img {
    transform: scale(2);
}
@media (min-width: 768px) {
    .swiper.mySwiper {
        max-width: 500px !important;
    }
    .swal2-popup.swal2-modal.swal2-show {
        width: 70% !important;
    }
    .asignar-precio-popup {
        display: flex;
        align-items: center;
        justify-content: space-evenly;
    }
}

@media (max-width: 767px) {
  .swiper.mySwiper {
    max-width: 100% !important;
  }
}
img.zoom-hover-img.medium-zoom-image.medium-zoom-image--opened {
    transform-origin: center !important;
}
.popup-col-info {
    text-align: justify;
    font-size: 18px;
}
.popup-col-info p {
    font-size: 18px;
}
input#nuevoPrecio_783 {
    height: auto;
    max-width: 130px;
    margin: 2px 0px 0px 12px;
    border: none;
    border-bottom: 1px solid #aeaaaa;
}
</style>