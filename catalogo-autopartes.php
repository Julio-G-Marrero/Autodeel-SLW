<?php
/**
 * Plugin Name: Catálogo de Autopartes
 * Plugin URI: https://tudominio.com
 * Description: Plugin para la gestión de un catálogo de autopartes con integración en WooCommerce.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://tudominio.com
 * License: GPL v2 or later
 */

 if (!defined('ABSPATH')) {
    exit; // Evita acceso directo
}

// Definir constantes del plugin
define('CATALOGO_AUTOPARTES_DIR', plugin_dir_path(__FILE__));
define('CATALOGO_AUTOPARTES_URL', plugin_dir_url(__FILE__));
define('CATALOGO_AUTOPARTES_VERSION', '1.0.0');

// Incluir archivos esenciales
require_once CATALOGO_AUTOPARTES_DIR . '/includes/database.php';    // Gestión de la base de datos
require_once CATALOGO_AUTOPARTES_DIR . '/includes/roles.php';       // Gestión de roles y permisos
require_once CATALOGO_AUTOPARTES_DIR . '/includes/menu.php';        // Menú y páginas de administración
require_once CATALOGO_AUTOPARTES_DIR . '/includes/api.php';         // API interna para AJAX
$product_sync_path = CATALOGO_AUTOPARTES_DIR . 'includes/product-sync.php';
if (file_exists($product_sync_path)) {
    require_once $product_sync_path;
}
// Función que se ejecuta al activar el plugin (crea las tablas necesarias)
register_activation_hook(__FILE__, 'catalogo_autopartes_activar');

register_activation_hook(__FILE__, function() {
    global $wpdb;

    $attribute_name = 'compat_autopartes';

    // Verifica si ya existe
    $exists = $wpdb->get_var($wpdb->prepare("
        SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s
    ", $attribute_name));

    if (!$exists) {
        $wpdb->insert(
            "{$wpdb->prefix}woocommerce_attribute_taxonomies",
            [
                'attribute_name' => $attribute_name,
                'attribute_label' => 'Compat Autopartes',
                'attribute_type' => 'select',
                'attribute_orderby' => 'name',
                'attribute_public' => 1
            ]
        );

        // Forzar refresco de taxonomías
        delete_transient('wc_attribute_taxonomies');
    }
});


function catalogo_autopartes_activar() {
    require_once plugin_dir_path(__FILE__) . 'includes/database.php';
    catalogo_autopartes_crear_tablas();
}

// Función que se ejecuta al desactivar el plugin (sin eliminar datos)
function catalogo_autopartes_desactivar() {
    // Aquí podríamos limpiar cachés o realizar alguna acción antes de desactivar
}
register_deactivation_hook(__FILE__, 'catalogo_autopartes_desactivar');

// Función para eliminar completamente el plugin (elimina tablas y datos)
function catalogo_autopartes_desinstalar() {
    require_once CATALOGO_AUTOPARTES_DIR . 'uninstall.php';
}
register_uninstall_hook(__FILE__, 'catalogo_autopartes_desinstalar');

// Cargar los archivos de scripts y estilos del plugin
function catalogo_autopartes_cargar_recursos($hook) {
    if (strpos($hook, 'catalogo-autopartes') === false) {
        return;
    }
    
    wp_enqueue_style('catalogo-autopartes-css', CATALOGO_AUTOPARTES_URL . 'assets/style.css', array(), CATALOGO_AUTOPARTES_VERSION);
    wp_enqueue_script('catalogo-autopartes-js', CATALOGO_AUTOPARTES_URL . 'assets/script.js', array('jquery'), CATALOGO_AUTOPARTES_VERSION, true);
}
add_action('admin_enqueue_scripts', 'catalogo_autopartes_cargar_recursos');

add_action('admin_init', 'catalogo_autopartes_exportar_csv');
function catalogo_autopartes_exportar_csv() {
    if (!isset($_GET['catalogo_id']) || $_GET['action'] !== 'exportar_catalogo') return;

    if (!current_user_can('manage_options')) return; // Seguridad

    global $wpdb;

    $catalogo_id = intval($_GET['catalogo_id']);
    $autopartes = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}autopartes WHERE catalogo_id = %d", $catalogo_id),
        ARRAY_A
    );

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="catalogo_' . $catalogo_id . '.csv"');

    $output = fopen('php://output', 'w');
    if (!empty($autopartes)) {
        fputcsv($output, array_keys($autopartes[0])); // encabezados
        foreach ($autopartes as $fila) {
            fputcsv($output, $fila);
        }
    }
    fclose($output);
    exit;
}
function catalogo_autopartes_enqueue_scripts() {
    wp_enqueue_script('jquery'); // necesario para WordPress AJAX
    wp_localize_script('jquery', 'ajaxurl', admin_url('admin-ajax.php'));
}
function ajax_guardar_solicitud_pieza() {
    check_ajax_referer('enviar_solicitud_pieza', 'security');

    global $wpdb;

    $autoparte_id   = intval($_POST['autoparte_id']);
    $ubicacion_id   = intval($_POST['ubicacion']);
    $estado_pieza   = sanitize_text_field($_POST['estado_pieza']);
    $observaciones  = sanitize_text_field($_POST['observaciones']);
    $usuario_id     = get_current_user_id();

    $fotos_urls = [];

    if (!empty($_FILES['fotos']['name'][0])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        foreach ($_FILES['fotos']['name'] as $i => $name) {
            $file = [
                'name'     => $_FILES['fotos']['name'][$i],
                'type'     => $_FILES['fotos']['type'][$i],
                'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                'error'    => $_FILES['fotos']['error'][$i],
                'size'     => $_FILES['fotos']['size'][$i],
            ];

            $upload = wp_handle_upload($file, ['test_form' => false]);

            if (!isset($upload['error'])) {
                $fotos_urls[] = esc_url_raw($upload['url']);
            }
        }
    }

    $insertado = $wpdb->insert("{$wpdb->prefix}solicitudes_piezas", [
        'autoparte_id'   => $autoparte_id,
        'ubicacion_id'   => $ubicacion_id,
        'estado_pieza'   => $estado_pieza,
        'observaciones'  => $observaciones,
        'imagenes'       => maybe_serialize($fotos_urls),
        'usuario_id'     => $usuario_id,
        'estado'         => 'pendiente',
        'fecha_envio'    => current_time('mysql')
    ]);

    if ($insertado) {
        $solicitud_id = $wpdb->insert_id;

        wp_send_json_success([
            'message' => 'Solicitud enviada',
            'id'      => $solicitud_id
        ]);
    } else {
        wp_send_json_error(['message' => 'No se pudo guardar la solicitud']);
    }
}

function mi_plugin_cargar_tailwind_cdn() {
    wp_enqueue_style('tailwind-cdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@3.4.1/dist/tailwind.min.css');
}
add_action('admin_enqueue_scripts', 'mi_plugin_cargar_tailwind_cdn');

add_action('wp_ajax_crear_producto_autoparte', 'crear_producto_autoparte');

function crear_producto_autoparte() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }

    // Datos base
    $sku_base = sanitize_text_field($_POST['sku'] ?? '');
    $nombre = sanitize_text_field($_POST['nombre'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $categoria_id = intval($_POST['categoria'] ?? 0);
    $ubicacion = sanitize_text_field($_POST['ubicacion'] ?? '');
    $observaciones = sanitize_textarea_field($_POST['observaciones'] ?? '');
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $imagenes = json_decode(stripslashes($_POST['imagenes'] ?? '[]'), true);
    $compatibilidades = json_decode(stripslashes($_POST['compatibilidades'] ?? '[]'), true);
    $estado_pieza = sanitize_text_field($_POST['estado_pieza'] ?? '');

    if (!is_array($imagenes)) $imagenes = [];
    if (!is_array($compatibilidades)) $compatibilidades = [];
    $estado_formateado = ucwords(str_replace('_', ' ', $estado_pieza));
    $post_content = "Estado de la pieza: {$estado_formateado}\n\n, observaciones: {$observaciones}";
    
    
    // Crear producto
    $post_id = wp_insert_post([
        'post_title'   => $nombre,
        'post_content' => $post_content,
        'post_status'  => 'publish',
        'post_type'    => 'product',
    ]);

    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => 'No se pudo crear el producto.']);
    }

    $sku_extendido = $sku_base . '#P' . $post_id;

    update_post_meta($post_id, '_sku', $sku_extendido);
    update_post_meta($post_id, '_regular_price', $precio);
    update_post_meta($post_id, '_price', $precio);
    update_post_meta($post_id, '_manage_stock', 'yes');
    update_post_meta($post_id, '_stock', 1);
    update_post_meta($post_id, '_stock_status', 'instock');

    if ($categoria_id) {
        wp_set_object_terms($post_id, [$categoria_id], 'product_cat');
    }

    update_post_meta($post_id, '_ubicacion_fisica', $ubicacion);
    update_post_meta($post_id, '_observaciones', $observaciones);

    // Imágenes
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $galeria_ids = [];

    error_log("===== INICIO DE IMÁGENES PRODUCTO $post_id =====");
    error_log("Total imágenes recibidas: " . count($imagenes));

    foreach ($imagenes as $index => $img_data) {
        $img_data = trim($img_data);
        error_log("Imagen [$index] inicio: " . substr($img_data, 0, 40));
        $attachment_id = false;

        // Base64
        if (preg_match('/^data:image\/(jpeg|png|jpg);base64,/', $img_data, $type)) {
            $img_data = substr($img_data, strpos($img_data, ',') + 1);
            $decoded = base64_decode($img_data);

            if ($decoded === false) {
                error_log("Error al decodificar base64 en imagen [$index]");
                continue;
            }

            $ext = $type[1];
            $filename = 'autoparte_' . time() . '_' . $index . '.' . $ext;
            $upload = wp_upload_bits($filename, null, $decoded);

            if ($upload['error']) {
                error_log("Fallo en wp_upload_bits [$filename]: " . $upload['error']);
                continue;
            }

            $file_path = $upload['file'];
            $filetype = wp_check_filetype($filename, null);

            $attachment = [
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name($filename),
                'post_content'   => '',
                'post_status'    => 'inherit'
            ];

            $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
            if (is_wp_error($attachment_id)) {
                error_log("Fallo en wp_insert_attachment: " . $attachment_id->get_error_message());
                continue;
            }

            $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $attach_data);

            error_log("Imagen base64 [$index] guardada, ID: $attachment_id");

        } else {
            // URL
            $tmp = download_url($img_data);
            if (is_wp_error($tmp)) {
                error_log("Error al descargar imagen URL [$index]: " . $tmp->get_error_message());
                continue;
            }

            $file_array = [
                'name'     => basename($img_data),
                'tmp_name' => $tmp,
            ];

            $attachment_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($attachment_id)) {
                error_log("media_handle_sideload falló: " . $attachment_id->get_error_message());
                continue;
            }

            error_log("Imagen URL [$index] subida, ID: $attachment_id");
        }

        if ($attachment_id) {
            if ($index === 0) {
                set_post_thumbnail($post_id, $attachment_id);
                error_log("Asignada como imagen destacada: $attachment_id");
            } else {
                $galeria_ids[] = $attachment_id;
            }
        }
    }

    if (!empty($galeria_ids)) {
        update_post_meta($post_id, '_product_image_gallery', implode(',', $galeria_ids));
        error_log("Galería asignada: " . implode(',', $galeria_ids));
    }

    error_log("===== FIN DE IMÁGENES PRODUCTO $post_id =====");

    // Compatibilidades
    $attribute_slug = 'compat_autopartes';
    $taxonomy = 'pa_' . $attribute_slug;
    $terminos = [];

    foreach ($compatibilidades as $c) {
        $marca = sanitize_text_field($c['marca'] ?? '');
        $modelo = sanitize_text_field($c['submarca'] ?? '');
        $rango = explode('-', $c['rango'] ?? '');
        $anio_inicio = intval(trim($rango[0] ?? 0));
        $anio_fin = intval(trim($rango[1] ?? 0));
        if (!$marca || !$modelo || !$anio_inicio || !$anio_fin) continue;
        for ($anio = $anio_inicio; $anio <= $anio_fin; $anio++) {
            $terminos[] = "$marca $modelo $anio";
        }
    }

    $terminos = array_unique($terminos);
    foreach ($terminos as $term) {
        $term = sanitize_text_field(trim($term));
        if (!term_exists($term, $taxonomy)) {
            wp_insert_term($term, $taxonomy);
        }
    }

    wp_set_object_terms($post_id, $terminos, $taxonomy, false);

    $product_attributes = get_post_meta($post_id, '_product_attributes', true);
    if (!is_array($product_attributes)) $product_attributes = [];

    $product_attributes[$taxonomy] = [
        'name'         => $taxonomy,
        'value'        => '',
        'position'     => 0,
        'is_visible'   => 1,
        'is_variation' => 0,
        'is_taxonomy'  => 1,
    ];

    update_post_meta($post_id, '_product_attributes', $product_attributes);

    // Relación con solicitud
    update_post_meta($post_id, 'solicitud_id', $solicitud_id);

    global $wpdb;
    $wpdb->update("{$wpdb->prefix}solicitudes_piezas", ['estado' => 'aprobada'], ['id' => $solicitud_id]);

    // Guardar
    $product = wc_get_product($post_id);
    $product->save();

    $terminos_asignados = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'names']);

    wp_send_json_success([
        'message' => 'Producto creado correctamente.',
        'sku' => $sku_extendido,
        'compatibilidades' => $terminos_asignados
    ]);
}

// Endpoint AJAX: obtener datos de una ubicación
add_action('wp_ajax_obtener_datos_ubicacion', function () {
    global $wpdb;
    $id = intval($_GET['id'] ?? 0);
    $ubicacion = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ubicaciones_autopartes WHERE id = %d", $id
    ));

    if ($ubicacion) {
        wp_send_json_success(['ubicacion' => $ubicacion]);
    } else {
        wp_send_json_error();
    }
});

// Endpoint AJAX: Consultar precios de listas de precios por sku
add_action('wp_ajax_obtener_precio_por_sku', function () {
    global $wpdb;

    $sku = sanitize_text_field($_POST['sku']);
    $tabla = $wpdb->prefix . 'precios_catalogos';

    $resultado = $wpdb->get_results(
        $wpdb->prepare("SELECT catalogo, precio_proveedor, precio_publico FROM $tabla WHERE sku_base = %s", $sku)
    );

    wp_send_json_success($resultado);
});

// Endpoint AJAX: asignar producto escaneado a la ubicación activa
add_action('wp_ajax_asignar_producto_a_ubicacion', function () {
    $sku = sanitize_text_field($_POST['sku'] ?? '');
    $ubicacion = sanitize_text_field($_POST['ubicacion'] ?? '');
    $product_id = wc_get_product_id_by_sku($sku);

    if (!$product_id) {
        wp_send_json_error(['message' => 'Producto no encontrado por SKU.']);
    }

    update_post_meta($product_id, '_ubicacion_fisica', $ubicacion);

    wp_send_json_success([
        'nombre' => get_the_title($product_id),
        'sku' => $sku,
    ]);
});

add_action('wp_ajax_buscar_producto_por_sku', 'buscar_producto_por_sku');
function buscar_producto_por_sku() {
    global $wpdb;

    $sku = sanitize_text_field($_POST['sku'] ?? '');
    error_log("SKU recibido: " . $sku);

    // Buscar SKU parcialmente (por si contiene "#P123")
    $result = $wpdb->get_row($wpdb->prepare("
        SELECT post_id, meta_value FROM {$wpdb->prefix}postmeta
        WHERE meta_key = '_sku' AND meta_value LIKE %s
        LIMIT 1
    ", '%' . $sku . '%'));

    if (!$result) {
        error_log("No se encontró ningún SKU similar a: " . $sku);
        wp_send_json_error(['message' => 'No encontrado']);
    }

    $product_id = $result->post_id;
    $product = wc_get_product($product_id);

    // Obtener imagen destacada o placeholder
    $imagen_url = get_the_post_thumbnail_url($product_id, 'thumbnail') ?: wc_placeholder_img_src();

    wp_send_json_success([
        'nombre'      => get_the_title($product_id),
        'sku_real'    => $result->meta_value,
        'descripcion' => get_the_excerpt($product_id),
        'imagen'      => $imagen_url
    ]);
}

add_action('wp_ajax_asignar_lote_ubicacion', 'asignar_lote_ubicacion');

function asignar_lote_ubicacion() {
    $ubicacion = sanitize_text_field($_POST['ubicacion'] ?? '');
    $skus = json_decode(stripslashes($_POST['skus'] ?? '[]'), true);

    if (!is_array($skus) || empty($ubicacion)) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }

    $asignados = 0;
    foreach ($skus as $sku) {
        $product_id = wc_get_product_id_by_sku($sku); // ❗️ Usa el SKU tal cual viene
        if ($product_id) {
            update_post_meta($product_id, '_ubicacion_fisica', $ubicacion);
            $asignados++;
        } else {
            error_log("❌ No se encontró producto con SKU: " . $sku);
        }
    }

    wp_send_json_success([
        'message' => "✅ Se asignaron $asignados productos a la ubicación '$ubicacion'."
    ]);
}

add_action('wp_ajax_productos_por_ubicacion', function () {
    $ubicacion = sanitize_text_field($_GET['ubicacion'] ?? '');

    if (!$ubicacion) {
        wp_send_json_error(['message' => 'Ubicación no recibida']);
    }

    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => '_ubicacion_fisica',
                'value' => $ubicacion,
                'compare' => '='
            ]
        ]
    ];

    $query = new WP_Query($args);
    $productos = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $productos[] = [
                'nombre' => get_the_title(),
                'sku' => get_post_meta(get_the_ID(), '_sku', true),
                'imagen' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') ?: wc_placeholder_img_src()
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success(['productos' => $productos]);
});

add_action('wp_ajax_buscar_autopartes_front', 'ajax_buscar_autopartes_front');
add_action('wp_ajax_nopriv_buscar_autopartes_front', 'ajax_buscar_autopartes_front');

function ajax_buscar_autopartes_front() {
    global $wpdb;

    $compat = sanitize_text_field($_POST['compatibilidad'] ?? '');
    $categoria = intval($_POST['categoria'] ?? 0);
    $pagina = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina = max(1, intval($_POST['por_pagina'] ?? 15));
    $offset = ($pagina - 1) * $por_pagina;

    $tax_query = [];

    // 🚀 Búsqueda parcial por término de compatibilidad
    if (!empty($compat)) {
        // Buscar términos que empiecen con la palabra ingresada
        $term_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT t.term_id FROM {$wpdb->terms} t 
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
             WHERE tt.taxonomy = %s AND t.name LIKE %s",
            'pa_compat_autopartes',
            '%' . $wpdb->esc_like($compat) . '%'
        ));

        if (!empty($term_ids)) {
            $tax_query[] = [
                'taxonomy' => 'pa_compat_autopartes',
                'field'    => 'term_id',
                'terms'    => $term_ids,
            ];
        } else {
            // No hay coincidencias
            wp_send_json_success([
                'resultados' => [],
                'total_paginas' => 0
            ]);
            return;
        }
    }

    // 🎯 Filtro adicional por categoría si se seleccionó
    if ($categoria) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => [$categoria]
        ];
    }

    // 📦 Argumentos para buscar productos publicados con stock > 0
    $args = [
        'post_type'      => 'product',
        'posts_per_page' => $por_pagina,
        'offset'         => $offset,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'     => '_stock',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC'
            ]
        ],
    ];

    // Solo agregamos tax_query si hay condiciones
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query($args);
    $resultados = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            global $product;

            $terms = wp_get_object_terms(get_the_ID(), 'pa_compat_autopartes', ['fields' => 'names']);
            $agrupadas = [];

            foreach ($terms as $term) {
                if (preg_match('/^(.+?)\s+(\d{4})$/', $term, $match)) {
                    $clave = trim($match[1]);
                    $anio = intval($match[2]);
                    $agrupadas[$clave][] = $anio;
                }
            }

            $compatibilidades_rango = [];
            foreach ($agrupadas as $clave => $anios) {
                sort($anios);
                $inicio = $fin = $anios[0];
                for ($i = 1; $i < count($anios); $i++) {
                    if ($anios[$i] === $fin + 1) {
                        $fin = $anios[$i];
                    } else {
                        $compatibilidades_rango[] = "$clave $inicio–$fin";
                        $inicio = $fin = $anios[$i];
                    }
                }
                $compatibilidades_rango[] = "$clave $inicio–$fin";
            }

            $resultados[] = [
                'nombre'            => get_the_title(),
                'link'              => get_permalink(),
                'imagen'            => get_the_post_thumbnail_url(get_the_ID(), 'medium') ?: wc_placeholder_img_src(),
                'precio'            => $product->get_price_html(),
                'compatibilidades'  => $compatibilidades_rango
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success([
        'resultados'     => $resultados,
        'total_paginas'  => ceil($query->found_posts / $por_pagina)
    ]);
}

add_action('wp_ajax_obtener_submarcas', 'ajax_obtener_submarcas');
add_action('wp_ajax_nopriv_obtener_submarcas', 'ajax_obtener_submarcas');

function ajax_obtener_submarcas() {
    global $wpdb;

    if (!isset($_GET['marca']) || empty($_GET['marca'])) {
        wp_send_json_error(['message' => 'Marca no recibida']);
    }

    $marca = sanitize_text_field($_GET['marca']);

    $submarcas = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT submarca
        FROM {$wpdb->prefix}compatibilidades
        WHERE marca = %s
        ORDER BY submarca ASC
    ", $marca));

    if (empty($submarcas)) {
        wp_send_json_error(['message' => 'No se encontraron submarcas']);
    }

    wp_send_json_success(['submarcas' => $submarcas]);
}

add_action('wp_ajax_buscar_autopartes_compatibles', 'buscar_autopartes_compatibles');
add_action('wp_ajax_nopriv_buscar_autopartes_compatibles', 'buscar_autopartes_compatibles');

function buscar_autopartes_compatibles() {
    $compat = sanitize_text_field($_POST['compatibilidad'] ?? '');
    $categoria = intval($_POST['categoria'] ?? 0);

    $args = [
        'post_type' => 'product',
        'posts_per_page' => 30,
        'post_status' => 'publish',
        'tax_query' => [
            [
                'taxonomy' => 'pa_compat_autopartes',
                'field' => 'name',
                'terms' => $compat,
            ]
        ]
    ];

    if ($categoria) {
        $args['tax_query'][] = [
            'taxonomy' => 'product_cat',
            'field' => 'term_id',
            'terms' => $categoria
        ];
    }

    $query = new WP_Query($args);

    $resultados = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            global $product;

            $resultados[] = [
                'nombre' => get_the_title(),
                'link'   => get_permalink(),
                'imagen' => get_the_post_thumbnail_url(get_the_ID(), 'medium') ?: wc_placeholder_img_src(),
                'precio' => $product->get_price_html()
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success(['resultados' => $resultados]);
}

// ✅ Crear roles personalizados al activar el plugin
register_activation_hook(__FILE__, 'catalogo_autopartes_crear_roles');

function catalogo_autopartes_crear_roles() {
    // Punto de Venta
    add_role('rol_punto_venta', 'Punto de Venta', [
        'read' => true,
        'ver_punto_venta' => true,
    ]);

    // Capturista
    add_role('rol_capturista', 'Capturista de Productos', [
        'read' => true,
        'ver_captura_productos' => true,
    ]);

    // Gestor de Cajas
    add_role('rol_gestor_cajas', 'Gestor de Cajas', [
        'read' => true,
        'ver_gestion_cajas' => true,
    ]);
}

// Oculta barra superior de WordPress (admin bar)
add_action('after_setup_theme', function () {
    if (!current_user_can('administrator')) {
        show_admin_bar(false);
    }
});

// Oculta la sidebar del admin para roles personalizados (capturista y gestor de solicitudes)
add_action('admin_head', 'ocultar_sidebar_para_roles_personalizados');
function ocultar_sidebar_para_roles_personalizados() {
    if (current_user_can('rol_capturista') || current_user_can('rol_solicitudes')) {
        echo '<style>
            #adminmenu, #adminmenuback, #adminmenuwrap,
            .update-nag, #screen-meta, #wpfooter {
                display: none !important;
            }
            #wpcontent {
                margin-left: 0 !important;
            }
        </style>';
    }
}

// Oculta el admin bar (barra negra superior) en frontend y backend
add_action('admin_head', 'ocultar_wpadminbar_con_css');
add_action('wp_head', 'ocultar_wpadminbar_con_css');
function ocultar_wpadminbar_con_css() {
    if (!current_user_can('administrator')) {
        echo '<style>
            #wpadminbar {
                display: none !important;
            }
            html {
                margin-top: 0 !important;
            }
        </style>';
    }
}

add_filter('login_redirect', 'redirigir_personalizado_al_login', 100, 3);

function redirigir_personalizado_al_login($redirect_to, $request, $user) {
    if (!is_wp_error($user)) {
        $roles = (array) $user->roles;

        if (in_array('rol_capturista', $roles)) {
            return admin_url('admin.php?page=captura-productos');
        }

        if (in_array('rol_solicitudes', $roles)) {
            return admin_url('admin.php?page=solicitudes-autopartes');
        }
    }

    return $redirect_to;
}

add_filter('woocommerce_login_redirect', 'woocommerce_redireccion_personalizada', 10, 2);

function woocommerce_redireccion_personalizada($redirect, $user) {
    $roles = (array) $user->roles;

    if (in_array('rol_capturista', $roles)) {
        return admin_url('admin.php?page=captura-productos');
    }

    if (in_array('rol_solicitudes', $roles)) {
        return admin_url('admin.php?page=solicitudes-autopartes');
    }

    return $redirect;
}


add_action('admin_enqueue_scripts', 'catalogo_autopartes_enqueue_scripts');
add_action('wp_ajax_ajax_enviar_solicitud_pieza', 'ajax_guardar_solicitud_pieza');
catalogo_autopartes_crear_tablas();
