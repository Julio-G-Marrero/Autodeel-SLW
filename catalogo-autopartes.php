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

add_action('wp_ajax_ajax_buscar_productos_compatibles', 'ajax_buscar_productos_compatibles');
function ajax_buscar_productos_compatibles() {
    $compat = sanitize_text_field($_POST['compatibilidad'] ?? '');
    $categoria = sanitize_text_field($_POST['categoria'] ?? '');

    if (empty($compat)) {
        wp_send_json_error('Compatibilidad requerida.');
    }

    $tax_query = [
        [
            'taxonomy' => 'pa_compat_autopartes',
            'field'    => 'name',
            'terms'    => [$compat],
        ]
    ];

    if (!empty($categoria)) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => [$categoria],
        ];
    }

    $query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 20,
        'tax_query' => $tax_query
    ]);

    $productos = [];

    while ($query->have_posts()) {
        $query->the_post();
        $product = wc_get_product(get_the_ID());

        $productos[] = [
            'id' => $product->get_id(),
            'nombre' => $product->get_name(),
            'sku' => $product->get_sku(),
            'precio' => $product->get_price(),
            'stock' => $product->get_stock_quantity(),
            'imagen' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
            'link' => get_permalink($product->get_id())
        ];
    }

    wp_reset_postdata();

    if (empty($productos)) {
        wp_send_json_error('No se encontraron productos.');
    }

    wp_send_json_success($productos);
}

add_action('wp_ajax_obtener_marcas', 'obtener_marcas');
function obtener_marcas() {
    global $wpdb;

    $tabla = $wpdb->prefix . 'compatibilidades';
    $marcas = $wpdb->get_col("SELECT DISTINCT marca FROM $tabla ORDER BY marca ASC");

    if (empty($marcas)) {
        wp_send_json_error('No se encontraron marcas.');
    }

    wp_send_json_success($marcas);
}

add_action('wp_ajax_obtener_categorias_productos', 'obtener_categorias_productos');
function obtener_categorias_productos() {
    $categorias = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ]);

    if (is_wp_error($categorias) || empty($categorias)) {
        wp_send_json_error('No se encontraron categorías');
    }

    $data = array_map(function ($cat) {
        return [
            'id'    => $cat->term_id,
            'slug'  => $cat->slug,
            'nombre'=> $cat->name
        ];
    }, $categorias);

    wp_send_json_success($data);
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
//Endpoint para registrar venta
function ajax_registrar_venta_autopartes() {
    $cliente_id = intval($_POST['cliente_id']);
    $metodo_pago = sanitize_text_field($_POST['metodo_pago']);
    $productos = json_decode(stripslashes($_POST['productos']), true);

    if (!$cliente_id || empty($productos)) {
        wp_send_json_error(['message' => 'Datos incompletos']);
    }

    // TODO: Validar crédito, stock, OC si aplica...

    // Simulación de registro
    $venta_id = wp_insert_post([
        'post_type' => 'venta_autoparte',
        'post_status' => 'publish',
        'post_title' => 'Venta a cliente ' . $cliente_id,
        'meta_input' => [
            'cliente_id' => $cliente_id,
            'metodo_pago' => $metodo_pago,
            'productos' => $productos
        ]
    ]);

    if ($venta_id) {
        wp_send_json_success(['venta_id' => $venta_id]);
    } else {
        wp_send_json_error(['message' => 'Error al guardar venta']);
    }
}
add_action('wp_ajax_ajax_registrar_venta_autopartes', 'ajax_registrar_venta_autopartes');

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
function limpiar_titulo_para_mercadolibre($titulo) {
    // Quitar abreviaciones y palabras poco útiles
    $titulo = strtoupper($titulo);
    $remplazos = ['IZQ', 'DER', 'S/FOCO', 'C/FOCO', 'FONDO', 'CROMADO', 'NEGRO', '-', '/', 'DEPO', 'TY', 'JP'];
    foreach ($remplazos as $palabra) {
        $titulo = str_replace($palabra, '', $titulo);
    }

    // Solo palabras clave significativas
    $titulo = preg_replace('/\s+/', ' ', $titulo); // Quitar espacios múltiples
    $titulo = trim($titulo);

    return $titulo;
}

// En tu archivo principal del plugin
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css');
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], null, true);
});



function obtener_precio_mercado_libre() {
    $titulo = sanitize_text_field($_GET['titulo'] ?? '');
    if (!$titulo) {
        wp_send_json_error(['message' => 'Título inválido']);
    }

    // Limpiar título para búsqueda
    $titulo_busqueda = limpiar_titulo_para_mercadolibre($titulo);
    $url = "https://api.mercadolibre.com/sites/MLM/search?q=" . urlencode($titulo_busqueda);

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error al conectar con Mercado Libre']);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Si no hay resultados, construir fallback
    if (empty($data['results'])) {
        $fallback_url = 'https://listado.mercadolibre.com.mx/' . rawurlencode(strtolower(str_replace(' ', '-', $titulo)));
        wp_send_json_error([
            'message' => 'No se encontraron resultados',
            'fallback' => $fallback_url
        ]);
    }

    // Extraer resultados
    $resultados = array_slice($data['results'], 0, 5);
    $productos = array_map(function ($item) {
        return [
            'titulo' => $item['title'],
            'precio' => $item['price'],
            'link'   => $item['permalink'],
            'imagen' => $item['thumbnail']
        ];
    }, $resultados);

    wp_send_json_success($productos);
}
add_action('wp_ajax_obtener_precio_mercado_libre', 'obtener_precio_mercado_libre');

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

add_action('wp_ajax_guardar_precio_autoparte', 'guardar_precio_autoparte');

function guardar_precio_autoparte() {
    // Seguridad básica
    if (!current_user_can('edit_products')) {
        wp_send_json_error(['message' => 'No tienes permisos para editar productos.']);
    }

    $product_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $nuevo_precio = isset($_POST['precio']) ? floatval($_POST['precio']) : 0;

    if ($product_id <= 0 || $nuevo_precio <= 0) {
        wp_send_json_error(['message' => 'Datos inválidos.']);
    }

    // Obtener el producto
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error(['message' => 'Producto no encontrado.']);
    }

    // Actualizar el precio
    $product->set_regular_price($nuevo_precio);
    $product->set_price($nuevo_precio);
    $product->save();

    wp_send_json_success(['message' => 'Precio actualizado correctamente.']);
}

add_action('wp_ajax_ajax_registrar_cliente', 'ajax_registrar_cliente');

function ajax_registrar_cliente() {
    // Validar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }

    // Sanitizar datos
    $nombre = sanitize_text_field($_POST['nombre'] ?? '');
    $correo = sanitize_email($_POST['correo'] ?? '');
    $telefono = sanitize_text_field($_POST['telefono'] ?? '');
    $tipo = sanitize_text_field($_POST['tipo'] ?? 'externo');
    $sucursal = sanitize_text_field($_POST['sucursal'] ?? '');
    $requiere_oc = isset($_POST['requiere_oc']) ? 1 : 0;
    $credito = floatval($_POST['credito'] ?? 0);
    $dias_credito = intval($_POST['dias_credito'] ?? 0);
    $canal = sanitize_text_field($_POST['canal'] ?? '');
    $estado_credito = sanitize_text_field($_POST['estado_credito'] ?? 'activo');
    $oc_obligatoria = isset($_POST['oc_obligatoria']) ? 1 : 0;
    
    // Validación básica
    if (empty($nombre) || empty($correo)) {
        wp_send_json_error(['message' => 'Faltan campos requeridos']);
    }

    // Verificar si ya existe usuario
    if (email_exists($correo)) {
        wp_send_json_error(['message' => 'Ya existe un usuario con ese correo']);
    }

    // Crear usuario
    $password = wp_generate_password(10, true);
    $user_id = wp_create_user($correo, $password, $correo);

    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => 'Error al crear el usuario']);
    }


    // Asignar rol de cliente
    $user = new WP_User($user_id);
    $user->set_role('customer');

    //Enviar correo para restablecer contraseña
    // wp_new_user_notification($user_id, null, 'user');

    // Guardar metadatos personalizados
    update_user_meta($user_id, 'nombre_completo', $nombre);
    update_user_meta($user_id, 'telefono', $telefono);
    update_user_meta($user_id, 'tipo_cliente', $tipo);
    update_user_meta($user_id, 'sucursal', $sucursal);
    update_user_meta($user_id, 'requiere_oc', $requiere_oc);
    update_user_meta($user_id, 'credito_disponible', $credito);
    update_user_meta($user_id, 'dias_credito', $dias_credito);
    update_user_meta($user_id, 'canal_venta', $canal);
    update_user_meta($user_id, 'estado_credito', $estado_credito);
    update_user_meta($user_id, 'oc_obligatoria', $oc_obligatoria);
    update_user_meta($user_id, 'razon_social', sanitize_text_field($_POST['razon_social'] ?? ''));
    update_user_meta($user_id, 'rfc', strtoupper(sanitize_text_field($_POST['rfc'] ?? '')));
    update_user_meta($user_id, 'uso_cfdi', sanitize_text_field($_POST['uso_cfdi'] ?? ''));
    update_user_meta($user_id, 'regimen_fiscal', sanitize_text_field($_POST['regimen_fiscal'] ?? ''));
    update_user_meta($user_id, 'fact_calle', sanitize_text_field($_POST['fact_calle'] ?? ''));
    update_user_meta($user_id, 'fact_colonia', sanitize_text_field($_POST['fact_colonia'] ?? ''));
    update_user_meta($user_id, 'fact_municipio', sanitize_text_field($_POST['fact_municipio'] ?? ''));
    update_user_meta($user_id, 'fact_estado', sanitize_text_field($_POST['fact_estado'] ?? ''));
    update_user_meta($user_id, 'fact_cp', sanitize_text_field($_POST['fact_cp'] ?? ''));
    update_user_meta($user_id, 'fact_pais', sanitize_text_field($_POST['fact_pais'] ?? ''));


    // También puedes enviarle un correo con su acceso si lo deseas

    wp_send_json_success(['message' => 'Cliente creado con éxito']);
}

add_action('wp_ajax_ajax_buscar_cliente', 'ajax_buscar_cliente');

add_action('wp_ajax_ajax_buscar_producto_avanzado', 'ajax_buscar_producto_avanzado');
function ajax_buscar_producto_avanzado() {
    $termino = sanitize_text_field($_POST['termino'] ?? '');
    if (empty($termino)) {
        wp_send_json_error('Término de búsqueda vacío');
    }

    global $wpdb;
    $resultados = [];

    // 1. Buscar por SKU exacto o parcial
    $product_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s",
        '%' . $wpdb->esc_like($termino) . '%'
    ));

    if (!empty($product_ids)) {
        $sku_query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'post__in' => $product_ids,
            'posts_per_page' => 10,
        ]);

        while ($sku_query->have_posts()) {
            $sku_query->the_post();
            $product = wc_get_product(get_the_ID());

            $resultados[] = [
                'id' => $product->get_id(),
                'sku' => $product->get_sku(),
                'nombre' => $product->get_name(),
                'precio' => $product->get_price(),
                'stock' => $product->get_stock_quantity(),
                'imagen' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                'link' => get_permalink($product->get_id())
            ];
        }
        wp_reset_postdata();
    }

    // 2. Si no encontró por SKU, intenta por nombre
    if (empty($resultados)) {
        $name_query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            's' => $termino,
        ]);

        while ($name_query->have_posts()) {
            $name_query->the_post();
            $product = wc_get_product(get_the_ID());

            $resultados[] = [
                'id' => $product->get_id(),
                'sku' => $product->get_sku(),
                'nombre' => $product->get_name(),
                'precio' => $product->get_price(),
                'stock' => $product->get_stock_quantity(),
                'imagen' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                'link' => get_permalink($product->get_id())
            ];
        }
        wp_reset_postdata();
    }

    if (empty($resultados)) {
        wp_send_json_error('No se encontraron productos');
    }

    wp_send_json_success($resultados);
}

function ajax_buscar_cliente() {
    // Verifica permisos si es necesario (puedes quitar esto si no estás autenticando)
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    $termino = sanitize_text_field($_POST['termino'] ?? '');

    if (strlen($termino) < 2) {
        wp_send_json_error(['message' => 'Ingresa al menos 2 caracteres']);
    }

    $args = [
        'role' => 'customer',
        'number' => 20,
        'search' => "*{$termino}*",
        'search_columns' => ['user_login', 'user_email', 'display_name']
    ];

    $clientes = get_users($args);
    $resultados = [];

    foreach ($clientes as $cliente) {
        $resultados[] = [
            'id' => $cliente->ID,
            'nombre' => get_user_meta($cliente->ID, 'nombre_completo', true) ?: $cliente->display_name,
            'correo' => $cliente->user_email,
            'tipo_cliente' => get_user_meta($cliente->ID, 'tipo_cliente', true),
            'credito_disponible' => get_user_meta($cliente->ID, 'credito_disponible', true),
            'estado_credito' => get_user_meta($cliente->ID, 'estado_credito', true),
            'canal_venta' => get_user_meta($cliente->ID, 'canal_venta', true)
        ];
    }

    wp_send_json_success($resultados);
}

add_action('wp_ajax_buscar_autopartes_compatibles', 'buscar_autopartes_compatibles');
add_action('wp_ajax_nopriv_buscar_autopartes_compatibles', 'buscar_autopartes_compatibles');

add_action('wp_ajax_ajax_listar_clientes', 'ajax_listar_clientes');

function ajax_listar_clientes() {
    $tipo = sanitize_text_field($_POST['tipo'] ?? '');
    $estado = sanitize_text_field($_POST['estado'] ?? '');

    $args = [
        'role' => 'customer',
        'number' => 200,
    ];

    $users = get_users($args);
    $resultados = [];

    foreach ($users as $user) {
        $tipo_cliente = get_user_meta($user->ID, 'tipo_cliente', true);
        $estado_credito = get_user_meta($user->ID, 'estado_credito', true);

        if ($tipo && $tipo_cliente !== $tipo) continue;
        if ($estado && $estado_credito !== $estado) continue;

        $resultados[] = [
            'id' => $user->ID,
            'nombre' => get_user_meta($user->ID, 'nombre_completo', true) ?: $user->display_name,
            'correo' => $user->user_email,
            'tipo_cliente' => $tipo_cliente,
            'estado_credito' => $estado_credito,
            'credito_disponible' => get_user_meta($user->ID, 'credito_disponible', true) ?: 0,
            'canal_venta' => get_user_meta($user->ID, 'canal_venta', true) ?: ''
        ];
    }

    wp_send_json_success($resultados);
}

add_action('wp_ajax_ajax_obtener_cliente', function () {
    $id = intval($_POST['user_id'] ?? 0);
    if (!$id) wp_send_json_error();

    $data = [
        'id' => $id,
        'nombre' => get_user_meta($id, 'nombre_completo', true),
        'tipo_cliente' => get_user_meta($id, 'tipo_cliente', true),
        'estado_credito' => get_user_meta($id, 'estado_credito', true),
        'credito_disponible' => get_user_meta($id, 'credito_disponible', true),
        'dias_credito' => get_user_meta($id, 'dias_credito', true),
        'canal_venta' => get_user_meta($id, 'canal_venta', true),
        'oc_obligatoria' => get_user_meta($id, 'oc_obligatoria', true)
    ];

    wp_send_json_success($data);
});

add_action('wp_ajax_ajax_actualizar_cliente', function () {
    $id = intval($_POST['user_id'] ?? 0);
    if (!$id) wp_send_json_error();

    update_user_meta($id, 'nombre_completo', sanitize_text_field($_POST['nombre'] ?? ''));
    update_user_meta($id, 'tipo_cliente', sanitize_text_field($_POST['tipo'] ?? 'externo'));
    update_user_meta($id, 'estado_credito', sanitize_text_field($_POST['estado_credito'] ?? 'activo'));
    update_user_meta($id, 'credito_disponible', floatval($_POST['credito'] ?? 0));
    update_user_meta($id, 'dias_credito', intval($_POST['dias'] ?? 0));
    update_user_meta($id, 'canal_venta', sanitize_text_field($_POST['canal'] ?? ''));
    update_user_meta($id, 'oc_obligatoria', intval($_POST['oc'] ?? 0));

    wp_send_json_success();
});

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

add_action('wp_ajax_marcar_solicitud_borrador', function () {
    global $wpdb;

    $id = intval($_POST['id'] ?? 0);
    $nota = sanitize_text_field($_POST['nota'] ?? '');

    if (!$id) {
        wp_send_json_error(['message' => 'ID inválido']);
    }

    $updated = $wpdb->update(
        "{$wpdb->prefix}solicitudes_piezas",
        [
            'estado' => 'borrador',
            'observaciones' => $nota, // ✅ Aquí guardas la nota como observación
        ],
        ['id' => $id]
    );

    if ($updated !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error(['message' => 'No se pudo actualizar la solicitud.']);
    }
});

add_action('wp_ajax_eliminar_solicitud_pieza', function () {
    global $wpdb;
    $id = intval($_POST['id'] ?? 0);

    if (!$id) {
        wp_send_json_error(['message' => 'ID inválido']);
    }

    $deleted = $wpdb->delete("{$wpdb->prefix}solicitudes_piezas", ['id' => $id]);

    if ($deleted !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error(['message' => 'No se pudo eliminar la solicitud.']);
    }
});

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
