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
    global $wpdb;

    $marca       = sanitize_text_field($_POST['marca'] ?? '');
    $submarca    = sanitize_text_field($_POST['submarca'] ?? '');
    $anio        = sanitize_text_field($_POST['anio'] ?? '');
    $categoria   = sanitize_text_field($_POST['categoria'] ?? '');
    $cliente_id  = intval($_POST['cliente_id'] ?? 0);
    $pagina      = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina  = max(1, intval($_POST['por_pagina'] ?? 15));
    $offset      = ($pagina - 1) * $por_pagina;

    $roles_cliente = [];
    if ($cliente_id) {
        $user = get_user_by('id', $cliente_id);
        if ($user) {
            $roles_cliente = $user->roles;
        }
    }

    $termino_exacto = "$marca $submarca $anio";

    $tax_query = [
        [
            'taxonomy' => 'pa_compat_autopartes',
            'field'    => 'name',
            'terms'    => [$termino_exacto],
        ]
    ];

    if (!empty($categoria)) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => [$categoria],
        ];
    }

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
        'tax_query' => $tax_query
    ];

    $query = new WP_Query($args);
    $productos = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            if (!$product) continue;

            $precio_base  = (float) $product->get_price();
            $precio_final = $precio_base;

            if (function_exists('get_wholesale_price_for_user') && $cliente_id > 0) {
                $precio_plugin = get_wholesale_price_for_user($product, $cliente_id);
                if ($precio_plugin && is_numeric($precio_plugin)) {
                    $precio_final = floatval($precio_plugin);
                }
            }

            if ($precio_final == $precio_base && in_array('wholesale_talleres_crash', $roles_cliente)) {
                $precio_final = round($precio_base * 0.5, 2);
            }

            $galeria = array_map(function($img_id) {
                return wp_get_attachment_image_url($img_id, 'large');
            }, $product->get_gallery_image_ids());

            $terms = wp_get_object_terms(get_the_ID(), 'pa_compat_autopartes', ['fields' => 'names']);
            $agrupadas = [];
            foreach ($terms as $term) {
                if (preg_match('/^(.+?)\s+(\d{4})$/', $term, $match)) {
                    $clave = trim($match[1]);
                    $anio  = intval($match[2]);
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

            $productos[] = [
                'nombre'            => get_the_title(),
                'sku'               => $product->get_sku(),
                'precio'            => $precio_final,
                'precio_base'       => $precio_base,
                'precio_html'       => wc_price($precio_final),
                'imagen'            => get_the_post_thumbnail_url(get_the_ID(), 'large') ?: wc_placeholder_img_src(),
                'galeria'           => $galeria,
                'compatibilidades'  => $compatibilidades_rango,
                'link'              => get_permalink(),
                'solicitud_id'      => get_post_meta($product->get_id(), 'solicitud_id', true),
            ];
        }
        wp_reset_postdata();
    }

    wp_send_json_success([
        'resultados'     => $productos,
        'total_paginas'  => ceil($query->found_posts / $por_pagina)
    ]);
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

    // Aplicar IVA (16%) a cada precio
    $resultado_con_iva = array_map(function($fila) {
        $fila->precio_proveedor = round($fila->precio_proveedor * 1.16, 2);
        $fila->precio_publico = round($fila->precio_publico * 1.16, 2);
        return $fila;
    }, $resultado);

    wp_send_json_success($resultado_con_iva);
});

//Endpoint para registrar venta
add_action('wp_ajax_ajax_registrar_venta_autopartes', 'ajax_registrar_venta_autopartes');
function ajax_registrar_venta_autopartes() {
    global $wpdb;

    error_log('🧪 [Inicio] Registro de venta iniciado');

    $cliente_id = intval($_POST['cliente_id']);
    $vendedor = wp_get_current_user()->display_name;
    $vendedor_id = get_current_user_id();
    $metodo_pago = sanitize_text_field($_POST['metodo_pago']);
    $productos = json_decode(stripslashes($_POST['productos']), true);
    $canal_venta = sanitize_text_field($_POST['canal'] ?? 'interno');
    $tipo_cliente = sanitize_text_field($_POST['tipo_cliente'] ?? 'externo');
    $credito_usado = floatval($_POST['credito_usado'] ?? 0);
    $oc_obligatoria = sanitize_text_field($_POST['oc_obligatoria'] ?? 'no');
    $estado_pago = $metodo_pago === 'credito' ? 'pendiente' : 'pagado';
    $entrega_inmediata = sanitize_text_field($_POST['entrega_inmediata'] ?? '0');
    $oc_url = sanitize_text_field($_POST['oc_url'] ?? '');

    if (!$cliente_id || empty($productos)) {
        error_log('❌ Faltan datos de cliente o productos');
        wp_send_json_error(['message' => 'Faltan datos del cliente o productos']);
        exit;
    }

    error_log("🧪 Cliente ID: $cliente_id | Productos: " . count($productos));

    $total = array_reduce($productos, function($carry, $p) {
        return $carry + (floatval($p['precio']) * intval($p['cantidad']));
    }, 0);
    error_log("🧮 Total calculado: $total");

    $venta_insertada = $wpdb->insert("{$wpdb->prefix}ventas_autopartes", [
        'cliente_id'     => $cliente_id,
        'vendedor_id'    => $vendedor_id,
        'productos'      => wp_json_encode($productos),
        'total'          => $total,
        'metodo_pago'    => $metodo_pago,
        'canal_venta'    => $canal_venta,
        'tipo_cliente'   => $tipo_cliente,
        'credito_usado'  => $credito_usado,
        'oc_folio'       => $oc_url,
        'estado_pago'    => $estado_pago
    ]);

    if (!$venta_insertada) {
        error_log("❌ Error al insertar venta: " . $wpdb->last_error);
        wp_send_json_error(['message' => 'Error al registrar la venta']);
        exit;
    }

    $venta_id = $wpdb->insert_id;
    error_log("✅ Venta insertada con ID: $venta_id");

    $order = wc_create_order(['customer_id' => $cliente_id]);
    $cliente_userdata = get_userdata($cliente_id);
    if (!$cliente_userdata) {
        error_log('❌ No se pudo obtener los datos del cliente');
        wp_send_json_error(['message' => 'No se pudo obtener los datos del cliente']);
        exit;
    }

    $meta = get_user_meta($cliente_id);
    $order->set_billing_first_name($meta['nombre_completo'][0] ?? '');
    $order->set_billing_phone($meta['telefono'][0] ?? '');
    $order->set_billing_email($cliente_userdata->user_email);
    $order->set_billing_company($meta['razon_social'][0] ?? '');
    $order->set_billing_address_1($meta['fact_calle'][0] ?? '');
    $order->set_billing_address_2($meta['fact_colonia'][0] ?? '');
    $order->set_billing_city($meta['fact_municipio'][0] ?? '');
    $order->set_billing_state($meta['fact_estado'][0] ?? '');
    $order->set_billing_postcode($meta['fact_cp'][0] ?? '');
    $order->set_billing_country($meta['fact_pais'][0] ?? '');

    error_log("🧪 Agregando productos al pedido...");
    foreach ($productos as $p) {
        $product_id = wc_get_product_id_by_sku($p['sku']);
        if ($product_id) {
            $product = wc_get_product($product_id);
            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity(intval($p['cantidad']));
            $item->set_subtotal(floatval($p['precio']));
            $item->set_total(floatval($p['precio']));
            $order->add_item($item);
            $item->save();

            // Descontar stock
            $stock = $product->get_stock_quantity() - intval($p['cantidad']);
            $product->set_stock_quantity($stock);
            $product->save();

            // Guardar ubicación y removerla
            $ubicacion_id = get_post_meta($product_id, '_ubicacion_fisica', true);
            $ubicacion_nombre = $ubicacion_id;

            // Si es numérico, buscar en la tabla de ubicaciones
            if (is_numeric($ubicacion_id)) {
                $ubicacion_nombre = $wpdb->get_var($wpdb->prepare(
                    "SELECT nombre FROM {$wpdb->prefix}ubicaciones_autopartes WHERE id = %d",
                    intval($ubicacion_id)
                )) ?: $ubicacion_id;
            }

            // Inyectar ubicación al array
            foreach ($productos as &$prod) {
                if ($prod['sku'] === $p['sku']) {
                    $prod['ubicacion'] = $ubicacion_nombre;
                    break;
                }
            }

            delete_post_meta($product_id, '_ubicacion_fisica');
        }

        if (!empty($p['solicitud_id'])) {
            $wpdb->update("{$wpdb->prefix}solicitudes_piezas", ['estado' => 'vendido'], ['id' => intval($p['solicitud_id'])]);
        }

        if (!empty($p['negociacion_id'])) {
            $wpdb->update("{$wpdb->prefix}negociaciones_precios", ['estado' => 'vendida'], ['id' => intval($p['negociacion_id'])]);
        }
    }

    error_log("✅ Productos agregados y stock actualizado");

    $metodo_wc = match($metodo_pago) {
        'efectivo' => 'cod',
        'transferencia' => 'bacs',
        'tarjeta' => 'manual',
        'credito' => 'manual',
        default => 'manual',
    };

    $order->set_payment_method($metodo_wc);
    $order->set_payment_method_title(ucfirst($metodo_pago));
    $order->calculate_totals();

    // 🧩 Estado logístico y de WooCommerce
    if ($entrega_inmediata === '1') {
        update_post_meta($order->get_id(), '_estado_armado', 'entregado');
        update_post_meta($order->get_id(), '_estado_logistico', 'entregado');
        $order->set_status('completed');
    } else {
        update_post_meta($order->get_id(), '_estado_armado', 'pendiente_armado');
        update_post_meta($order->get_id(), '_estado_logistico', 'pendiente_armado');
        $order->set_status('processing');
    }

    update_post_meta($order->get_id(), '_canal_venta', $canal_venta);
    update_post_meta($order->get_id(), '_venta_datos_autoparte', [
        'venta_id' => $venta_id,
        'canal_venta' => $canal_venta,
        'tipo_cliente' => $tipo_cliente,
        'metodo_pago' => $metodo_pago,
        'estado_pago' => $estado_pago,
        'oc_url' => $oc_url,
        'estado_logistico' => $entrega_inmediata === '1' ? 'entregado' : 'pendiente_armado',
    ]);

    error_log("✅ Pedido WooCommerce creado con ID: {$order->get_id()}");

    $wpdb->update("{$wpdb->prefix}ventas_autopartes", [
        'woo_order_id' => $order->get_id()
    ], ['id' => $venta_id]);

    if ($estado_pago === 'pendiente') {
        $dias_credito = intval(get_user_meta($cliente_id, 'dias_credito', true)) ?: 15;
        $fecha_creacion = current_time('mysql');
        $fecha_limite_pago = date('Y-m-d H:i:s', strtotime("+$dias_credito days"));

        $wpdb->insert("{$wpdb->prefix}cuentas_cobrar", [
            'venta_id'          => $venta_id,
            'cliente_id'        => $cliente_id,
            'vendedor_id'       => $vendedor_id,
            'monto_total'       => $total,
            'monto_pagado'      => 0,
            'saldo_pendiente'   => $total,
            'fecha_creacion'    => $fecha_creacion,
            'fecha_limite_pago' => $fecha_limite_pago,
            'estado'            => 'pendiente',
            'orden_compra_url'  => $oc_url
        ]);
    }

    error_log("✅ [Finalizado] Venta ID $venta_id registrada correctamente");

    wp_send_json_success([
        'venta_id' => $venta_id,
        'fecha_hora' => current_time('mysql'),
        'vendedor' => $vendedor,
        'productos' => $productos
    ]);
    exit;
}


add_action('wp_ajax_subir_orden_compra', function () {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_upload('archivo', 0);
    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => 'Error al subir archivo.']);
    }

    wp_send_json_success(['url' => wp_get_attachment_url($attachment_id)]);
});

add_action('wp_ajax_ajax_actualizar_estado_armado', 'ajax_actualizar_estado_armado');

function ajax_actualizar_estado_armado() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'No tienes permisos.']);
    }

    $pedido_id = intval($_POST['pedido_id']);
    $nuevo_estado = sanitize_text_field($_POST['nuevo_estado']);

    if (!$pedido_id || !$nuevo_estado) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }

    // Actualizar estado personalizado
    update_post_meta($pedido_id, '_estado_armado', $nuevo_estado);

    // Obtener el objeto del pedido
    $order = wc_get_order($pedido_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Pedido no encontrado.']);
    }

    // Si el nuevo estado es "enviado", generar código de recepción
    if ($nuevo_estado === 'enviado') {
        $codigo_existente = get_post_meta($pedido_id, '_codigo_recepcion', true);
        if (empty($codigo_existente)) {
            $codigo = strtoupper(wp_generate_password(6, false, false)); // ej: "G3F7K9"
            update_post_meta($pedido_id, '_codigo_recepcion', $codigo);
        }
    }

    // ✅ Si es entregado, marcar el pedido como completado en WooCommerce
    if ($nuevo_estado === 'entregado' && $order->get_status() !== 'completed') {
        $order->update_status('completed', 'Pedido entregado y marcado como completado.');
    }

    wp_send_json_success(['message' => 'Estado actualizado correctamente.']);
}

add_action('wp_ajax_ajax_obtener_pedidos_armado', 'ajax_obtener_pedidos_armado');

function ajax_obtener_pedidos_armado() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    // Filtros recibidos
    $estado_filtro = sanitize_text_field($_POST['estado'] ?? '');
    $cliente_filtro = sanitize_text_field($_POST['cliente'] ?? '');
    $pagina = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina = max(1, intval($_POST['por_pagina'] ?? 10));

    $args = [
        'status' => ['pending', 'processing', 'completed'], // ✅ Incluye también los pedidos a crédito
        'orderby' => 'date',
        'order' => 'DESC',
        'limit' => -1, // Cargar todos para filtrar manualmente
    ];

    $query = new WC_Order_Query($args);
    $pedidos = $query->get_orders();

    $resultado = [];

    foreach ($pedidos as $pedido) {
        /** @var WC_Order $pedido */
        $woo_order_id = $pedido->get_id();
        $user_id = $pedido->get_user_id();
        $oc_obligatoria = get_user_meta($user_id, 'oc_obligatoria', true) === '1';
        $orden_compra_url = get_post_meta($woo_order_id, '_orden_compra_url', true);
        $estado_armado = get_post_meta($woo_order_id, '_estado_armado', true);

        if (empty($estado_armado)) {
            $estado_armado = 'pendiente_armado';
        }

        // Filtro por estado_armado
        if (!empty($estado_filtro)) {
            if ($estado_filtro === 'entregado') {
                if ($estado_armado !== 'entregado' && $pedido->get_status() !== 'completed') {
                    continue;
                }
            } elseif ($estado_armado !== $estado_filtro) {
                continue;
            }
        }

        // Filtro por cliente (nombre o email)
        $cliente_nombre = $pedido->get_billing_first_name();
        $cliente_email = $pedido->get_billing_email();
        if (!empty($cliente_filtro) && stripos($cliente_nombre . ' ' . $cliente_email, $cliente_filtro) === false) {
            continue;
        }

        // Si el pedido está "enviado" pero no tiene código, generar uno automáticamente
        if ($estado_armado === 'enviado') {
            $codigo_actual = get_post_meta($woo_order_id, '_codigo_recepcion', true);
            if (!$codigo_actual) {
                $nuevo_codigo = strtoupper(wp_generate_password(6, false, false)); // Ej: "XZ9PQR"
                update_post_meta($woo_order_id, '_codigo_recepcion', $nuevo_codigo);
                $codigo_actual = $nuevo_codigo;
            }
        } else {
            $codigo_actual = get_post_meta($woo_order_id, '_codigo_recepcion', true);
        }

        $resultado[] = [
            'id' => $woo_order_id,
            'cliente' => $cliente_nombre ?: $cliente_email,
            'total' => number_format($pedido->get_total(), 2),
            'estado_woo' => wc_get_order_status_name($pedido->get_status()),
            'estado_armado' => $estado_armado,
            'fecha' => $pedido->get_date_created() ? $pedido->get_date_created()->format('Y-m-d H:i') : '',
            'ver_url' => admin_url("post.php?post={$woo_order_id}&action=edit"),
            'oc_obligatoria' => $oc_obligatoria,
            'oc_url' => $orden_compra_url,
            'codigo_recepcion' => $codigo_actual,
        ];
    }

    // Paginación manual
    $total_pedidos = count($resultado);
    $total_paginas = ceil($total_pedidos / $por_pagina);
    $offset = ($pagina - 1) * $por_pagina;
    $resultado_paginado = array_slice($resultado, $offset, $por_pagina);

    wp_send_json_success([
        'pedidos' => $resultado_paginado,
        'total_paginas' => $total_paginas
    ]);
}

add_action('woocommerce_order_status_completed', function($order_id) {
    update_post_meta($order_id, '_estado_armado', 'entregado');
});

function ajax_obtener_productos_pedido() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    global $wpdb;

    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    if (!$pedido_id) {
        wp_send_json_error(['message' => 'ID de pedido inválido']);
    }

    $pedido = wc_get_order($pedido_id);
    if (!$pedido) {
        wp_send_json_error(['message' => 'Pedido no encontrado']);
    }

    $productos = [];
    foreach ($pedido->get_items() as $item) {
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);
        if (!$product) continue;

        $ubicacion_raw = get_post_meta($product_id, '_ubicacion_fisica', true);
        $ubicacion_nombre = 'Sin ubicación';
        $ubicacion_descripcion = '';
        $ubicacion_imagen = '';
        
        if (!empty($ubicacion_raw)) {
            if (is_numeric($ubicacion_raw)) {
                // Si es número, buscar por ID
                $ubicacion_info = $wpdb->get_row(
                    $wpdb->prepare("SELECT nombre, descripcion, imagen_url FROM {$wpdb->prefix}ubicaciones_autopartes WHERE id = %d", intval($ubicacion_raw))
                );
            } else {
                // Si es texto, buscar por nombre
                $ubicacion_info = $wpdb->get_row(
                    $wpdb->prepare("SELECT nombre, descripcion, imagen_url FROM {$wpdb->prefix}ubicaciones_autopartes WHERE nombre = %s", sanitize_text_field($ubicacion_raw))
                );
            }
        
            if ($ubicacion_info) {
                $ubicacion_nombre = $ubicacion_info->nombre;
                $ubicacion_descripcion = $ubicacion_info->descripcion;
                $ubicacion_imagen = $ubicacion_info->imagen_url;
            } else {
                // No se encontró en tabla, usar el texto crudo como nombre
                $ubicacion_nombre = is_numeric($ubicacion_raw) ? 'Sin ubicación' : $ubicacion_raw;
            }
        }

        $productos[] = [
            'sku' => $product->get_sku(),
            'nombre' => $product->get_name(),
            'imagen_producto' => wp_get_attachment_url($product->get_image_id()),
            'ubicacion_nombre' => $ubicacion_nombre,
            'ubicacion_descripcion' => $ubicacion_descripcion,
            'ubicacion_imagen' => $ubicacion_imagen
        ];
    }

    $datos_pedido = [
        'cliente' => $pedido->get_billing_first_name() ?: $pedido->get_billing_email(),
        'fecha' => $pedido->get_date_created() ? $pedido->get_date_created()->format('Y-m-d H:i') : '',
        'total' => $pedido->get_total()
    ];

    wp_send_json_success([
        'productos' => $productos,
        'datos_pedido' => $datos_pedido
    ]);
}

add_action('wp_ajax_ajax_obtener_productos_pedido', 'ajax_obtener_productos_pedido');

add_action('wp_ajax_ajax_buscar_producto_por_sku_pedido', 'ajax_buscar_producto_por_sku_pedido');

function ajax_buscar_producto_por_sku_pedido() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    global $wpdb;

    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    $sku_escaneado = sanitize_text_field($_POST['sku'] ?? '');

    if (!$pedido_id || empty($sku_escaneado)) {
        wp_send_json_error(['message' => 'Datos incompletos']);
    }

    $pedido = wc_get_order($pedido_id);
    if (!$pedido) {
        wp_send_json_error(['message' => 'Pedido no encontrado']);
    }

    foreach ($pedido->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);

        if (!$product) {
            continue;
        }

        // ⚡ CORREGIDO: Comparar correctamente SKU completo
        $sku_producto = $product->get_sku(); // Aquí ya viene el SKU como 019-0630-22#P1174

        if (strcasecmp(trim($sku_producto), trim($sku_escaneado)) === 0) {
            // Si coincide el SKU, enviamos el producto
            $ubicacion_id = get_post_meta($product_id, '_ubicacion_fisica', true);
            $ubicacion_info = null;
            if ($ubicacion_id) {
                $ubicacion_info = $wpdb->get_row(
                    $wpdb->prepare("SELECT nombre, descripcion, imagen_url FROM {$wpdb->prefix}ubicaciones_autopartes WHERE id = %d", $ubicacion_id)
                );
            }

            wp_send_json_success([
                'sku' => $sku_producto,
                'nombre' => $product->get_name(),
                'imagen_producto' => wp_get_attachment_url($product->get_image_id()),
                'ubicacion_nombre' => $ubicacion_info ? $ubicacion_info->nombre : 'Sin ubicación',
                'ubicacion_descripcion' => $ubicacion_info ? $ubicacion_info->descripcion : '',
                'ubicacion_imagen' => $ubicacion_info ? $ubicacion_info->imagen_url : ''
            ]);
        }
    }

    // Si no encuentra ningún producto
    wp_send_json_error(['message' => 'Producto no encontrado en el pedido']);
}

function ajax_finalizar_armado_pedido() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    if (!$pedido_id) {
        wp_send_json_error(['message' => 'ID de pedido inválido']);
    }

    $pedido = wc_get_order($pedido_id);
    if (!$pedido) {
        wp_send_json_error(['message' => 'Pedido no encontrado']);
    }

    // Actualizar meta personalizado del armado
    update_post_meta($pedido_id, '_estado_armado', 'listo_para_envio');

    wp_send_json_success(['message' => 'El armado del pedido se ha finalizado correctamente.']);
}

add_action('wp_ajax_ajax_finalizar_armado_pedido', 'ajax_finalizar_armado_pedido');


function ajax_cambiar_estado_armado() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'No tienes permisos para actualizar pedidos.']);
    }

    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    $nuevo_estado = sanitize_text_field($_POST['nuevo_estado'] ?? '');

    if (!$pedido_id || !$nuevo_estado) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }

    if (!in_array($nuevo_estado, ['pendiente_armado', 'en_armado', 'listo_envio'])) {
        wp_send_json_error(['message' => 'Estado inválido.']);
    }

    update_post_meta($pedido_id, '_estado_armado', $nuevo_estado);

    wp_send_json_success(['message' => 'Estado actualizado.']);
}

add_action('wp_ajax_ajax_cambiar_estado_armado', 'ajax_cambiar_estado_armado');

add_action('wp_ajax_agregar_nota_interna', function () {
    $order_id = intval($_POST['order_id']);
    $contenido = sanitize_text_field($_POST['contenido']);

    if (!$order_id || !$contenido) {
        wp_send_json_error();
    }

    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error();

    $note_id = $order->add_order_note($contenido, false); // false = nota interna

    wp_send_json_success([
        'nota' => esc_html($contenido),
        'fecha' => current_time('Y-m-d H:i')
    ]);
});

add_action('wp_ajax_agregar_nota_interna_pedido', function () {
    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error(['message' => 'Sin permisos']);
    }

    $order_id = intval($_POST['order_id']);
    $nota = sanitize_text_field($_POST['nota']);

    if (!$order_id || !$nota) {
        wp_send_json_error(['message' => 'Datos incompletos']);
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => 'Pedido no encontrado']);
    }

    $order->add_order_note($nota, false); // false = nota privada
    wp_send_json_success(['message' => 'Nota guardada']);
});

add_action('wp_ajax_ajax_obtener_pedidos', 'ajax_obtener_pedidos');
function ajax_obtener_pedidos() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'No tienes permisos para ver los pedidos.']);
    }

    $estado = isset($_POST['estado']) ? sanitize_text_field($_POST['estado']) : '';
    $cliente_id = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;

    $args = [
        'limit'   => 50,
        'orderby' => 'date',
        'order'   => 'DESC',
        'status'  => $estado ? [$estado] : ['pending', 'processing', 'on-hold', 'completed'],
    ];

    if ($cliente_id) {
        $args['customer_id'] = $cliente_id;
    }

    $query = new WC_Order_Query($args);
    $pedidos = $query->get_orders();
    $resultado = [];

    foreach ($pedidos as $pedido) {
        /** @var WC_Order $pedido */
        $woo_order_id = $pedido->get_id();

        // 🔁 Verifica si hay datos de facturación, si no, usa el usuario del pedido
        $cliente = trim($pedido->get_billing_first_name() . ' ' . $pedido->get_billing_last_name());
        if (empty($cliente)) {
            $user_id = $pedido->get_customer_id();
            $user = $user_id ? get_user_by('id', $user_id) : null;
            $cliente = $user ? $user->display_name : 'Desconocido';
        }

        $estado_pedido = wc_get_order_status_name($pedido->get_status());
        $fecha = $pedido->get_date_created() ? $pedido->get_date_created()->format('Y-m-d H:i') : '';
        $total = $pedido->get_total();
        $canal = get_post_meta($woo_order_id, '_canal_venta', true) ?: 'WooCommerce';
        $tipo_cliente = get_post_meta($woo_order_id, '_tipo_cliente', true) ?: 'externo';
        $metodo_pago = get_post_meta($woo_order_id, '_metodo_pago', true) ?: $pedido->get_payment_method_title();

        $resultado[] = [
            'id'            => $woo_order_id,
            'cliente'       => $cliente,
            'estado'        => $estado_pedido,
            'fecha'         => $fecha,
            'total'         => number_format($total, 2),
            'canal'         => $canal,
            'tipo_cliente'  => ucfirst($tipo_cliente),
            'metodo_pago'   => $metodo_pago,
            'ver_url'       => admin_url("post.php?post={$woo_order_id}&action=edit"),
        ];
    }

    wp_send_json_success($resultado);
}

add_action('wp_ajax_ajax_obtener_resumen_ventas', function () {
    global $wpdb;

    $busqueda    = sanitize_text_field($_POST['busqueda'] ?? '');
    $pagina      = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina  = 15;
    $offset      = ($pagina - 1) * $por_pagina;

    $where  = '1=1';
    $params = [];

    // Filtro por ID si es numérico
    if (is_numeric($busqueda)) {
        $where .= ' AND id = %d';
        $params[] = intval($busqueda);
    } elseif ($busqueda) {
        // Filtro por nombre o correo del cliente
        $user_ids = get_users([
            'search'         => '*' . esc_attr($busqueda) . '*',
            'search_columns' => ['display_name', 'user_email'],
            'fields'         => ['ID']
        ]);

        if (!empty($user_ids)) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
            $where .= " AND cliente_id IN ($placeholders)";
            $params = array_merge($params, array_map('intval', $user_ids));
        } else {
            wp_send_json_success([
                'ventas'        => [],
                'total_paginas' => 0
            ]);
        }
    }

    // Total de resultados
    $total_query = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ventas_autopartes WHERE $where", ...$params);
    $total = $wpdb->get_var($total_query);

    // Obtener ventas
    $query = $wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ventas_autopartes 
        WHERE $where 
        ORDER BY fecha DESC 
        LIMIT %d OFFSET %d
    ", ...array_merge($params, [$por_pagina, $offset]));

    $ventas = $wpdb->get_results($query);
    $resultado = [];

    foreach ($ventas as $v) {
        $nombre_cliente = get_user_meta($v->cliente_id, 'nombre_completo', true);

        if (!$nombre_cliente) {
            $user = get_userdata($v->cliente_id);
            $nombre_cliente = $user ? ($user->display_name ?: $user->user_email) : 'Cliente eliminado';
        }

        $resultado[] = [
            'id'         => $v->id,
            'cliente'    => $nombre_cliente,
            'cliente_id' => intval($v->cliente_id),
            'total'      => number_format($v->total, 2),
            'metodo'     => $v->metodo_pago,
            'fecha'      => date('Y-m-d H:i', strtotime($v->fecha)),
            'estado'     => !empty($v->estado) ? $v->estado : 'completada'
        ];
    }

    wp_send_json_success([
        'ventas'        => $resultado,
        'total_paginas' => ceil($total / $por_pagina)
    ]);
});

add_action('wp_ajax_ajax_obtener_ticket_venta', function () {
    global $wpdb;

    $venta_id = intval($_POST['venta_id'] ?? 0);
    if (!$venta_id) {
        wp_send_json_error(['message' => 'ID de venta no válido']);
    }

    $venta = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ventas_autopartes WHERE id = %d",
        $venta_id
    ));

    if (!$venta) {
        wp_send_json_error(['message' => 'Venta no encontrada']);
    }

    $user = get_userdata($venta->cliente_id);
    $cliente = $user ? $user->display_name : 'Cliente eliminado';
    $productos = json_decode($venta->productos, true);

    wp_send_json_success([
        'cliente'  => $cliente,
        'productos' => $productos,
        'total'    => floatval($venta->total),
        'metodo'   => $venta->metodo_pago,
        'folio'    => $venta->id
    ]);
});

add_action('wp_ajax_ajax_obtener_resumen_cortes', function () {
    global $wpdb;

    $desde = sanitize_text_field($_POST['desde'] ?? '');
    $hasta = sanitize_text_field($_POST['hasta'] ?? '');
    $estado = sanitize_text_field($_POST['estado'] ?? '');
    $pagina = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina = 10;
    $offset = ($pagina - 1) * $por_pagina;

    $where = "1=1";
    $params = [];

    if ($desde) {
        $where .= " AND DATE(fecha_apertura) >= %s";
        $params[] = $desde;
    }
    if ($hasta) {
        $where .= " AND DATE(fecha_apertura) <= %s";
        $params[] = $hasta;
    }
    if ($estado) {
        $where .= " AND estado = %s";
        $params[] = $estado;
    }

    $sql_total = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}aperturas_caja WHERE $where", ...$params);
    $total = $wpdb->get_var($sql_total);

    $sql = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aperturas_caja WHERE $where ORDER BY fecha_apertura DESC LIMIT %d OFFSET %d",
        ...array_merge($params, [$por_pagina, $offset])
    );
    $registros = $wpdb->get_results($sql);

    $formateados = array_map(function($r) {
        $usuario = get_userdata($r->usuario_id);
        $vobo_usuario = $r->vobo_aprobado_por ? get_userdata($r->vobo_aprobado_por) : null;

        return [
            'id' => $r->id,
            'usuario' => $usuario ? $usuario->display_name : 'Usuario eliminado',
            'monto_apertura' => number_format($r->monto_inicial, 2),
            'total_cierre' => number_format($r->total_cierre ?? 0, 2),
            'diferencia' => number_format($r->diferencia ?? 0, 2),
            'estado' => $r->estado,
            'fecha_apertura' => date('Y-m-d H:i', strtotime($r->fecha_apertura)),
            'fecha_cierre' => $r->fecha_cierre ? date('Y-m-d H:i', strtotime($r->fecha_cierre)) : null,
            'vobo_aprobado' => $r->vobo_aprobado,
            'vobo_por' => $vobo_usuario ? $vobo_usuario->display_name : null,
            'vobo_fecha' => $r->vobo_fecha_aprobacion ? date('Y-m-d H:i', strtotime($r->vobo_fecha_aprobacion)) : null
        ];
    }, $registros);

    wp_send_json_success([
        'cortes' => $formateados,
        'total_paginas' => ceil($total / $por_pagina)
    ]);
});

add_action('wp_ajax_ajax_revertir_vobo_corte', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No tienes permisos suficientes.']);
    }

    global $wpdb;
    $corte_id = intval($_POST['corte_id']);

    $actualizado = $wpdb->update(
        "{$wpdb->prefix}aperturas_caja",
        [
            'vobo_aprobado' => 0,
            'vobo_aprobado_por' => null,
            'vobo_fecha_aprobacion' => null
        ],
        ['id' => $corte_id],
        ['%d', '%d', '%s'],
        ['%d']
    );

    if ($actualizado === false) {
        wp_send_json_error(['message' => 'No se pudo revertir el V°B°.']);
    }

    wp_send_json_success();
});

add_action('wp_ajax_ajax_obtener_ticket_corte', function () {
    global $wpdb;

    $corte_id = intval($_POST['corte_id'] ?? 0);
    if (!$corte_id) {
        wp_send_json_error(['message' => 'ID de corte no válido.']);
    }

    $tabla = $wpdb->prefix . 'aperturas_caja';
    $corte = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id = %d", $corte_id));

    if (!$corte) {
        wp_send_json_error(['message' => 'No se encontró el corte de caja.']);
    }

    $usuario = get_userdata($corte->usuario_id);
    $nombre_usuario = $usuario ? $usuario->display_name : 'Usuario eliminado';

    $resumen = [
        'id' => $corte->id,
        'monto_inicial' => floatval($corte->monto_inicial),
        'monto_cierre' => floatval($corte->total_cierre),
        'diferencia' => floatval($corte->diferencia),
        'ventas_efectivo' => 0, // Calculado abajo
        'fecha_apertura' => date('Y-m-d H:i', strtotime($corte->fecha_apertura)),
        'fecha_cierre' => $corte->fecha_cierre ? date('Y-m-d H:i', strtotime($corte->fecha_cierre)) : '-',
    ];

    // Obtener total de ventas en efectivo ligadas a esta caja
    $ventas = $wpdb->get_results($wpdb->prepare(
        "SELECT monto FROM {$wpdb->prefix}movimientos_caja WHERE caja_id = %d AND tipo = 'venta' AND metodo_pago = 'efectivo'",
        $corte_id
    ));

    foreach ($ventas as $v) {
        $resumen['ventas_efectivo'] += floatval($v->monto);
    }

    // Denominaciones
    $denominaciones = [];
    if ($corte->detalle_cierre) {
        $json = json_decode($corte->detalle_cierre, true);
        if (is_array($json)) {
            $denominaciones = $json;
        }
    }

    wp_send_json_success([
        'resumen' => $resumen,
        'denominaciones' => $denominaciones,
        'usuario' => $nombre_usuario
    ]);
});

// Endpoint: Aprobar V°B° del corte
add_action('wp_ajax_ajax_autorizar_vobo_corte', function () {
    global $wpdb;

    $corte_id = intval($_POST['corte_id'] ?? 0);
    $usuario_id = get_current_user_id();

    if (!$corte_id || !$usuario_id) {
        wp_send_json_error(['message' => 'ID de corte o usuario inválido.']);
    }

    $corte = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aperturas_caja WHERE id = %d", $corte_id
    ));

    if (!$corte) {
        wp_send_json_error(['message' => 'No se encontró el corte.']);
    }

    if ($corte->estado !== 'cerrada') {
        wp_send_json_error(['message' => 'Solo puedes autorizar cortes ya cerrados.']);
    }

    // Verifica si ya está aprobado
    if ((int)$corte->vobo_aprobado === 1) {
        wp_send_json_error(['message' => 'Este corte ya fue aprobado.']);
    }

    $resultado = $wpdb->update(
        "{$wpdb->prefix}aperturas_caja",
        [
            'vobo_aprobado'        => 1,
            'vobo_aprobado_por'    => $usuario_id,
            'vobo_fecha_aprobacion'=> current_time('mysql')
        ],
        ['id' => $corte_id],
        ['%d', '%d', '%s'],
        ['%d']
    );

    if ($resultado === false) {
        error_log("❌ Error al autorizar vobo del corte ID $corte_id: " . $wpdb->last_error);
        wp_send_json_error(['message' => 'No se pudo autorizar el corte.']);
    }

    wp_send_json_success(['message' => '✅ Corte autorizado correctamente.']);
});

add_action('wp_ajax_ajax_registrar_pago_cxc', 'ajax_registrar_pago_cxc');

function ajax_registrar_pago_cxc() {
    global $wpdb;

    $cuenta_id = intval($_POST['cuenta_id'] ?? 0);
    $monto = floatval($_POST['monto_pagado'] ?? 0);
    $metodo = sanitize_text_field($_POST['metodo_pago'] ?? 'efectivo');
    $notas = sanitize_textarea_field($_POST['notas'] ?? '');

    if ($cuenta_id <= 0 || $monto <= 0) {
        wp_send_json_error(['message' => 'Datos incompletos o inválidos.']);
    }

    $cuenta = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cuentas_cobrar WHERE id = %d", $cuenta_id
    ));

    if (!$cuenta) {
        wp_send_json_error(['message' => 'Cuenta por cobrar no encontrada.']);
    }

    $saldo_actual = floatval($cuenta->saldo_pendiente);
    if ($monto > $saldo_actual) {
        wp_send_json_error(['message' => 'El monto pagado excede el saldo pendiente actual.']);
    }

    // ✅ Subir comprobante si se envió
    $comprobante_url = '';

    if (!empty($_FILES['comprobante_pago']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $file = $_FILES['comprobante_pago'];

        // Tipos permitidos
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(['message' => 'Formato no permitido. Usa PDF, JPG, PNG o WEBP.']);
        }

        // Comprimir imagen si es JPG o PNG
        if (in_array($file['type'], ['image/jpeg', 'image/png'])) {
            $editor = wp_get_image_editor($file['tmp_name']);
            if (!is_wp_error($editor)) {
                $editor->resize(1200, 1200, false);
                $editor->set_quality(75);
                $editor->save($file['tmp_name']);
            }
        }

        // Subir sin crear attachment ni metadatos
        $upload = wp_handle_upload($file, ['test_form' => false, 'mimes' => [
            'pdf' => 'application/pdf',
            'jpg|jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp'
        ]]);

        if ($upload && !isset($upload['error'])) {
            $comprobante_url = esc_url_raw($upload['url']);
        } else {
            wp_send_json_error(['message' => 'Error al subir el comprobante.']);
        }
    }

    // 🧾 Insertar pago
    $wpdb->insert("{$wpdb->prefix}pagos_cxc", [
        'cuenta_id'       => $cuenta_id,
        'monto_pagado'    => $monto,
        'metodo_pago'     => $metodo,
        'notas'           => $notas,
        'fecha_pago'      => current_time('mysql'),
        'comprobante_url' => $comprobante_url
    ]);

    //  Actualizar cuenta
    $nuevo_pagado = floatval($cuenta->monto_pagado) + $monto;
    $nuevo_saldo  = max(0, $saldo_actual - $monto);
    $estado       = $nuevo_saldo <= 0 ? 'pagado' : 'pendiente';

    $wpdb->update("{$wpdb->prefix}cuentas_cobrar", [
        'monto_pagado'    => $nuevo_pagado,
        'saldo_pendiente' => $nuevo_saldo,
        'estado'          => $estado
    ], ['id' => $cuenta_id]);

    wp_send_json_success(['message' => '✅ Pago registrado correctamente.']);
}


add_action('wp_ajax_ajax_validar_credito_cliente', 'ajax_validar_credito_cliente');

function ajax_validar_credito_cliente() {
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    if (!$cliente_id) {
        wp_send_json_error(['message' => 'ID de cliente no proporcionado']);
    }

    global $wpdb;

    $cliente = get_userdata($cliente_id);
    if (!$cliente) {
        wp_send_json_error(['message' => 'Cliente no encontrado']);
    }

    $estado_credito   = get_user_meta($cliente_id, 'estado_credito', true) ?: 'inactivo';
    $credito_total    = floatval(get_user_meta($cliente_id, 'credito_disponible', true) ?: 0);
    $oc_obligatoria   = get_user_meta($cliente_id, 'oc_obligatoria', true) === '1';

    // ⚡ Obtener deuda total directamente desde SQL
    $deuda_actual = floatval($wpdb->get_var($wpdb->prepare(
        "SELECT SUM(saldo_pendiente) FROM {$wpdb->prefix}cuentas_cobrar 
         WHERE cliente_id = %d AND estado = 'pendiente'",
        $cliente_id
    ))) ?: 0;

    $credito_disponible = $credito_total - $deuda_actual;

    // ✅ Devolver respuesta JSON estructurada
    wp_send_json_success([
        'id'                 => $cliente_id,
        'nombre'            => $cliente->display_name ?: $cliente->user_email,
        'correo'            => $cliente->user_email,
        'estado_credito'    => strtolower($estado_credito),
        'credito_total'     => round($credito_total, 2),
        'deuda_actual'      => round($deuda_actual, 2),
        'credito_disponible'=> round($credito_disponible, 2),
        'oc_obligatoria'    => $oc_obligatoria
    ]);
}
// Endpoint AJAX: Obtener cuentas por cobrar
// Endpoint AJAX: Obtener cuentas por cobrar (WooCommerce + POS)
add_action('wp_ajax_ajax_obtener_cuentas_cobrar', function () {
    global $wpdb;

    $cliente_term = sanitize_text_field($_POST['cliente'] ?? '');
    $estado       = sanitize_text_field($_POST['estado'] ?? '');
    $desde        = sanitize_text_field($_POST['desde'] ?? '');
    $hasta        = sanitize_text_field($_POST['hasta'] ?? '');
    $pagina       = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina   = 10;
    $offset       = ($pagina - 1) * $por_pagina;

    $where_clauses = ["1=1"];
    $params = [];

    if (!empty($estado)) {
        $where_clauses[] = "c.estado = %s";
        $params[] = $estado;
    }

    if (!empty($desde)) {
        $where_clauses[] = "DATE(c.fecha_creacion) >= %s";
        $params[] = $desde;
    }

    if (!empty($hasta)) {
        $where_clauses[] = "DATE(c.fecha_creacion) <= %s";
        $params[] = $hasta;
    }

    $cliente_in_sql = '';
    if (!empty($cliente_term)) {
        $user_query = get_users([
            'search' => '*' . esc_attr($cliente_term) . '*',
            'search_columns' => ['user_email', 'display_name'],
            'fields' => ['ID']
        ]);
        $cliente_ids = array_map('intval', $user_query);

        if (empty($cliente_ids)) {
            wp_send_json_success(['cuentas' => [], 'total_paginas' => 0]);
        }

        // Construir lista segura de IDs para IN (...)
        $ids = implode(',', array_map('intval', $cliente_ids));
        $where_clauses[] = "c.cliente_id IN ($ids)";
    }

    $where = implode(' AND ', $where_clauses);

    // Consulta total
    $sql_total = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cuentas_cobrar c WHERE $where", ...$params);
    $total_resultados = (int) $wpdb->get_var($sql_total);

    // Consulta paginada
    $params_with_limits = array_merge($params, [$por_pagina, $offset]);
    $sql = $wpdb->prepare(
        "SELECT 
            c.id,
            c.cliente_id,
            c.monto_total,
            c.monto_pagado,
            c.saldo_pendiente,
            c.fecha_limite_pago,
            c.estado,
            c.orden_compra_url,
            (SELECT comprobante_url FROM {$wpdb->prefix}pagos_cxc 
             WHERE cuenta_id = c.id AND comprobante_url IS NOT NULL 
             ORDER BY fecha_pago DESC LIMIT 1) AS comprobante_pago_url
        FROM {$wpdb->prefix}cuentas_cobrar c
        WHERE $where
        ORDER BY c.fecha_creacion DESC
        LIMIT %d OFFSET %d",
        ...$params_with_limits
    );

    $cuentas = $wpdb->get_results($sql);

    $formateadas = [];
    foreach ($cuentas as $cuenta) {
        $user = get_userdata($cuenta->cliente_id);
        $nombre_cliente = 'Cliente eliminado';
        if ($user) {
            $nombre_cliente = $user->display_name ?: trim($user->first_name . ' ' . $user->last_name) ?: $user->user_email;
        }

        $formateadas[] = [
            'id' => (int)$cuenta->id,
            'cliente' => esc_html($nombre_cliente),
            'monto_total' => number_format((float)$cuenta->monto_total, 2),
            'monto_pagado' => number_format((float)$cuenta->monto_pagado, 2),
            'saldo_pendiente' => number_format((float)$cuenta->saldo_pendiente, 2),
            'fecha_limite_pago' => date('Y-m-d', strtotime($cuenta->fecha_limite_pago)),
            'estado' => sanitize_text_field($cuenta->estado),
            'orden_compra_url' => !empty($cuenta->orden_compra_url) ? esc_url($cuenta->orden_compra_url) : null,
            'comprobante_pago_url' => !empty($cuenta->comprobante_pago_url) ? esc_url($cuenta->comprobante_pago_url) : null,
        ];
    }

    wp_send_json_success([
        'cuentas' => $formateadas,
        'total_paginas' => ceil($total_resultados / $por_pagina)
    ]);
});

add_action('wp_ajax_ajax_obtener_historial_pagos_cxc', function () {
    global $wpdb;

    $cuenta_id = intval($_POST['cuenta_id'] ?? 0);
    if (!$cuenta_id) {
        wp_send_json_error('ID inválido');
    }

    $pagos = $wpdb->get_results($wpdb->prepare(
        "SELECT monto_pagado, metodo_pago, fecha_pago, notas, comprobante_url 
         FROM {$wpdb->prefix}pagos_cxc 
         WHERE cuenta_id = %d 
         ORDER BY fecha_pago DESC",
        $cuenta_id
    ));

    if (empty($pagos)) {
        wp_send_json_success([]);
    }

    wp_send_json_success(array_map(function ($p) {
        return [
            'monto'           => floatval($p->monto_pagado),
            'metodo'          => ucfirst($p->metodo_pago),
            'fecha'           => date('Y-m-d H:i', strtotime($p->fecha_pago)),
            'notas'           => $p->notas,
            'comprobante_url' => $p->comprobante_url
        ];
    }, $pagos));
});

//capacidades menu
function catalogo_autopartes_registrar_capacidades_personalizadas() {
    $capabilities = [
        'ver_captura_productos',
        'ver_solicitudes',
        'impresion-qr',
        'asignar_precio_autopartes',
        'punto_de_venta',
        'alta_clientes_nuevos',
        'gestion_clientes',
        'gestion_cuentas_cobrar',
        'gestion_de_cajas',
        'ver_resumen_ventas',
        'resumen_cortes',
        'gestion_de_pedidos',
        'armado_de_pedidos',
        'armado_de_pedido',
        'ver_asignar_ubicaciones_qr',
        'gestion_negociaciones',
        'gestion_devoluciones',
        'gestion_reparaciones',
        'gestion_rembolsos'
        // Agrega aquí cualquier otra capacidad personalizada
    ];

    $admin_role = get_role('administrator');
    if ($admin_role) {
        foreach ($capabilities as $cap) {
            $admin_role->add_cap($cap); // ✅ El admin las tendrá por defecto
        }
    }
}
add_action('init', 'catalogo_autopartes_registrar_capacidades_personalizadas');

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
    global $wpdb;

    $ubicacion_nombre = sanitize_text_field($_POST['ubicacion'] ?? '');
    $skus = json_decode(stripslashes($_POST['skus'] ?? '[]'), true);

    if (!is_array($skus) || empty($ubicacion_nombre)) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }

    // Buscar el ID real de la ubicación
    $ubicacion_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}ubicaciones_autopartes WHERE nombre = %s LIMIT 1",
        $ubicacion_nombre
    ));

    if (!$ubicacion_id) {
        wp_send_json_error(['message' => 'Ubicación no encontrada en la base de datos.']);
    }

    $asignados = 0;
    $errores = [];

    foreach ($skus as $sku) {
        $product_id = wc_get_product_id_by_sku($sku);

        if ($product_id) {
            // Guardar el ID como ubicación física
            update_post_meta($product_id, '_ubicacion_fisica', strval($ubicacion_id));

            // Eliminar marca "pendiente de reubicación"
            delete_post_meta($product_id, 'pendiente_reubicacion');

            // Registrar bitácora de reubicación
            update_post_meta($product_id, '_ultima_reubicacion', current_time('mysql'));

            $asignados++;
        } else {
            $errores[] = $sku;
            error_log("❌ SKU no encontrado: $sku");
        }
    }

    if ($asignados === 0) {
        wp_send_json_error(['message' => 'No se pudo asignar ningún producto.']);
    }

    wp_send_json_success([
        'message' => "✅ Se asignaron $asignados productos a la ubicación.",
        'errores' => $errores
    ]);
}

// Endpoint: Obtener reparaciones
add_action('wp_ajax_ajax_obtener_reparaciones', function () {
    global $wpdb;

    $sku = sanitize_text_field($_POST['sku'] ?? '');
    $nombre = sanitize_text_field($_POST['nombre'] ?? '');
    $estado = sanitize_text_field($_POST['estado'] ?? '');

    $where = '1=1';
    $params = [];

    if (!empty($sku)) {
        $where .= ' AND pm.meta_value LIKE %s';
        $params[] = "%$sku%";
    }

    if (!empty($nombre)) {
        $where .= ' AND pr.post_title LIKE %s';
        $params[] = "%$nombre%";
    }

    if (!empty($estado)) {
        $where .= ' AND r.estado = %s';
        $params[] = $estado;
    }

    $query = $wpdb->prepare("
        SELECT r.*, pr.ID as product_id, pr.post_title, pm.meta_value AS sku
        FROM {$wpdb->prefix}reparaciones_autopartes r
        INNER JOIN {$wpdb->prefix}posts pr ON pr.ID = r.producto_id
        LEFT JOIN {$wpdb->prefix}postmeta pm ON pr.ID = pm.post_id AND pm.meta_key = '_sku'
        WHERE $where
        GROUP BY r.id
        ORDER BY r.fecha_inicio DESC
    ", ...$params);

    $reparaciones = $wpdb->get_results($query);
    $data = [];

    foreach ($reparaciones as $r) {
        $data[] = [
            'id' => $r->id,
            'sku' => $r->sku,
            'nombre' => $r->post_title,
            'fecha_inicio' => date('Y-m-d H:i', strtotime($r->fecha_inicio)),
            'estado' => $r->estado
        ];
    }

    wp_send_json_success(['reparaciones' => $data]);
});

// Endpoint: Marcar reparación como completada
add_action('wp_ajax_ajax_marcar_reparacion_completa', function () {
    global $wpdb;

    $id = intval($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error(['message' => 'ID inválido']);

    // Obtener reparación
    $rep = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}reparaciones_autopartes WHERE id = %d", $id));
    if (!$rep) wp_send_json_error(['message' => 'Reparación no encontrada']);

    // Validar que esté pendiente
    if ($rep->estado !== 'pendiente') {
        wp_send_json_error(['message' => 'Esta reparación ya fue completada.']);
    }

    // Subir imagen de reparación si se envió
    $attachment_id = null;
    if (!empty($_FILES['imagen_reparacion']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file_id = media_handle_upload('imagen_reparacion', 0);
        if (!is_wp_error($file_id)) {
            $attachment_id = $file_id;

            // Agregar la imagen a la galería del producto reparado
            $galeria = get_post_meta($rep->producto_id, '_product_image_gallery', true);
            $galeria_ids = $galeria ? explode(',', $galeria) : [];
            $galeria_ids[] = $attachment_id;
            update_post_meta($rep->producto_id, '_product_image_gallery', implode(',', $galeria_ids));
        }
    }

    // ✅ Marcar reparación como completada
    $wpdb->update(
        "{$wpdb->prefix}reparaciones_autopartes",
        [
            'estado'         => 'reparado',
            'fecha_reparado' => current_time('mysql'),
            'reparado_por'   => get_current_user_id(),
        ],
        ['id' => $id]
    );

    // ✅ Restablecer stock del producto
    $stock_actual = (int) get_post_meta($rep->producto_id, '_stock', true);
    update_post_meta($rep->producto_id, '_stock', $stock_actual + 1);
    update_post_meta($rep->producto_id, '_stock_status', 'instock');

    // ✅ Borrar ubicación para obligar reubicación
    delete_post_meta($rep->producto_id, '_ubicacion_fisica');

    // ✅ (Opcional) Marcar como pendiente de reubicación
    update_post_meta($rep->producto_id, 'pendiente_reubicacion', 1);

    wp_send_json_success(['message' => '✅ Reparación marcada como completada.']);
});

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
                'imagen'            => get_the_post_thumbnail_url(get_the_ID(), 'large') ?: wc_placeholder_img_src(),
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

add_action('wp_ajax_ajax_eliminar_cliente', 'ajax_eliminar_cliente');

function ajax_eliminar_cliente() {
    if (!current_user_can('administrator') && !in_array('cobranza', wp_get_current_user()->roles)) {
        wp_send_json_error(['message' => 'No tienes permisos para eliminar clientes.']);
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id || !get_user_by('id', $user_id)) {
        wp_send_json_error(['message' => 'Cliente no encontrado.']);
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    $result = wp_delete_user($user_id);

    if ($result) {
        wp_send_json_success(['message' => 'Cliente eliminado correctamente.']);
    } else {
        wp_send_json_error(['message' => 'No se pudo eliminar el cliente.']);
    }
}

add_action('wp_ajax_ajax_cancelar_venta_pos', 'ajax_cancelar_venta_pos');

function ajax_cancelar_venta_pos() {
    global $wpdb;

    $current_user = wp_get_current_user();
    if (!current_user_can('administrator') && !in_array('cobranza', $current_user->roles)) {
        wp_send_json_error(['message' => 'No tienes permiso para realizar esta acción.']);
    }

    $venta_id = intval($_POST['venta_id'] ?? 0);
    if (!$venta_id) {
        wp_send_json_error(['message' => 'ID de venta no válido.']);
    }

    // 1. Obtener los productos en formato JSON desde wp_ventas_autopartes
    $venta = $wpdb->get_row($wpdb->prepare(
        "SELECT productos FROM {$wpdb->prefix}ventas_autopartes WHERE id = %d",
        $venta_id
    ));

    if (!$venta) {
        wp_send_json_error(['message' => 'Venta no encontrada.']);
    }

    $productos = json_decode($venta->productos);
    if (!is_array($productos)) {
        wp_send_json_error(['message' => 'Error al procesar los productos de la venta.']);
    }

    // 2. Restaurar stock de cada producto (cantidad * 1 por defecto)
    foreach ($productos as $p) {
        if (!isset($p->sku)) continue;

        $product_id = wc_get_product_id_by_sku($p->sku);
        if ($product_id) {
            $product = wc_get_product($product_id);
            $stock_actual = $product->get_stock_quantity();
            $cantidad = isset($p->cantidad) ? intval($p->cantidad) : 1;

            $product->set_stock_quantity($stock_actual + $cantidad);
            $product->save();
        }
    }

    // 3. Eliminar ingreso en corte de caja
    $wpdb->delete("{$wpdb->prefix}movimientos_caja", ['referencia' => 'venta_' . $venta_id]);

    // 4. Marcar venta como cancelada
    $wpdb->update("{$wpdb->prefix}ventas_autopartes", ['estado' => 'cancelada'], ['id' => $venta_id]);

    wp_send_json_success(['message' => 'Venta cancelada y stock restaurado.']);
}

add_action('wp_ajax_ajax_solicitar_devolucion_pos', function () {
    global $wpdb;

    $current_user = wp_get_current_user();
    if (!current_user_can('administrator') && !in_array('cobranza', $current_user->roles)) {
        wp_send_json_error(['message' => 'No tienes permiso para solicitar devoluciones.']);
    }
    $venta_id   = intval($_POST['venta_id'] ?? 0);
    $cliente_id = intval($_POST['cliente_id'] ?? 0);

    if (!$venta_id) {
        error_log('❌ [Devolución POS] Venta ID inválido o no enviado.');
        wp_send_json_error(['message' => 'ID de venta no válido.']);
    }

    error_log("ℹ️ [Devolución POS] Iniciando proceso para venta ID: $venta_id");

    // 1. Obtener la venta
    $venta = $wpdb->get_row($wpdb->prepare(
        "SELECT productos FROM {$wpdb->prefix}ventas_autopartes WHERE id = %d",
        $venta_id
    ));

    if (!$venta) {
        error_log("❌ [Devolución POS] No se encontró la venta con ID: $venta_id");
        wp_send_json_error(['message' => 'Venta no encontrada.']);
    }

    error_log("✅ [Devolución POS] Venta encontrada. Productos crudos: " . $venta->productos);

    // 2. Decodificar productos
    $productos = json_decode($venta->productos);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($productos)) {
        error_log("❌ [Devolución POS] Error al decodificar productos: " . json_last_error_msg());
        wp_send_json_error(['message' => 'Error al procesar los productos de la venta.']);
    }

    error_log("✅ [Devolución POS] Productos decodificados correctamente. Total: " . count($productos));

    $insertados = 0;

    foreach ($productos as $index => $p) {
        $sku = $p->sku ?? '';
        error_log("🔍 [Devolución POS] Procesando producto $index: SKU $sku");

        if (empty($sku)) {
            error_log("⚠️ [Devolución POS] Producto sin SKU. Saltando.");
            continue;
        }

        $producto_id = wc_get_product_id_by_sku($sku);

        if (!$producto_id) {
            error_log("⚠️ [Devolución POS] No se encontró producto en WooCommerce con SKU: $sku");
            continue;
        }

        // Verificar duplicados
        $existe = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}devoluciones_autopartes WHERE venta_id = %d AND producto_id = %d",
            $venta_id, $producto_id
        ));

        if ($existe > 0) {
            error_log("ℹ️ [Devolución POS] Ya existe devolución registrada para producto_id $producto_id en venta $venta_id");
            continue;
        }

        // Insertar devolución
        $insertado = $wpdb->insert("{$wpdb->prefix}devoluciones_autopartes", [
            'venta_id'        => $venta_id,
            'order_id'        => null,
            'producto_id'     => $producto_id,
            'cliente_id'      => $cliente_id ?: null,
            'motivo_cliente'  => 'Solicitado desde POS',
            'estado_revision' => 'pendiente',
            'fecha_solicitud' => current_time('mysql')
        ]);

        if ($insertado) {
            error_log("✅ [Devolución POS] Devolución insertada para producto_id $producto_id");
            $insertados++;
        } else {
            error_log("❌ [Devolución POS] Error al insertar devolución: " . $wpdb->last_error);
        }
    }

    if ($insertados === 0) {
        error_log("❌ [Devolución POS] Ninguna devolución fue registrada.");
        wp_send_json_error(['message' => 'No se pudo registrar ninguna devolución. Verifica si ya existen.']);
    }

    // 3. Marcar la venta como "en_revision"
    $updated = $wpdb->update(
        "{$wpdb->prefix}ventas_autopartes",
        ['estado' => 'en_revision'],
        ['id' => $venta_id],
        ['%s'],
        ['%d']
    );

    error_log("✅ [Devolución POS] Estado actualizado a 'en_revision' para venta ID: $venta_id ($updated filas)");

    wp_send_json_success(['message' => "$insertados devolución(es) registradas correctamente."]);
});

add_action('wp_ajax_ajax_registrar_cliente', 'ajax_registrar_cliente');

function ajax_registrar_cliente() {
    // Validar permisos mínimos
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'No has iniciado sesión.']);
    }

    $current_user = wp_get_current_user();
    $rol = $current_user->roles[0] ?? '';

    // Sanitizar datos comunes
    $nombre   = sanitize_text_field($_POST['nombre'] ?? '');
    $correo   = sanitize_email($_POST['correo'] ?? '');
    $telefono = sanitize_text_field($_POST['telefono'] ?? '');
    $tipo     = sanitize_text_field($_POST['tipo'] ?? 'externo');
    $sucursal = sanitize_text_field($_POST['sucursal'] ?? '');

    // Validación básica
    if (empty($nombre) || empty($correo)) {
        wp_send_json_error(['message' => 'Faltan campos requeridos.']);
    }

    if (email_exists($correo)) {
        wp_send_json_error(['message' => 'Ya existe un usuario con ese correo.']);
    }

    // Crear usuario
    $password = wp_generate_password(10, true);
    $user_id = wp_create_user($correo, $password, $correo);

    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => 'Error al crear el usuario.']);
    }

    $user = new WP_User($user_id);

    // Asignar rol (solo si tiene permiso para definirlo)
    $rol_wholesale = sanitize_text_field($_POST['wholesale_role'] ?? '');
    if (in_array($rol, ['administrator', 'cobranza']) && !empty($rol_wholesale)) {
        $user->set_role($rol_wholesale);
        update_user_meta($user_id, 'wholesale_role', $rol_wholesale);
    } else {
        $user->set_role('customer');
    }

    // Datos comunes permitidos para todos
    update_user_meta($user_id, 'nombre_completo', $nombre);
    update_user_meta($user_id, 'telefono', $telefono);
    update_user_meta($user_id, 'tipo_cliente', $tipo);
    update_user_meta($user_id, 'sucursal', $sucursal);

    // Si tiene permisos avanzados (admin o cobranza)
    if (in_array($rol, ['administrator', 'cobranza'])) {
        $requiere_oc     = isset($_POST['requiere_oc']) ? 1 : 0;
        $credito         = floatval($_POST['credito'] ?? 0);
        $dias_credito    = intval($_POST['dias_credito'] ?? 0);
        $canal           = sanitize_text_field($_POST['canal'] ?? '');
        $estado_credito  = sanitize_text_field($_POST['estado_credito'] ?? 'activo');
        $oc_obligatoria  = isset($_POST['oc_obligatoria']) ? 1 : 0;

        update_user_meta($user_id, 'requiere_oc', $requiere_oc);
        update_user_meta($user_id, 'credito_disponible', $credito);
        update_user_meta($user_id, 'dias_credito', $dias_credito);
        update_user_meta($user_id, 'canal_venta', $canal);
        update_user_meta($user_id, 'estado_credito', $estado_credito);
        update_user_meta($user_id, 'oc_obligatoria', $oc_obligatoria);

        // Datos fiscales
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
    }

    wp_send_json_success(['message' => 'Cliente creado con éxito']);
}

add_action('wp_ajax_ajax_obtener_devoluciones_admin', function () {
    global $wpdb;

    $cliente   = sanitize_text_field($_POST['cliente'] ?? '');
    $estado    = sanitize_text_field($_POST['estado'] ?? '');
    $desde     = sanitize_text_field($_POST['desde'] ?? '');
    $hasta     = sanitize_text_field($_POST['hasta'] ?? '');

    $pagina     = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina = 15;
    $offset     = ($pagina - 1) * $por_pagina;

    $where  = '1=1';
    $params = [];

    // Buscar por cliente (display_name o correo)
    if (!empty($cliente)) {
        $user_ids = get_users([
            'search'         => '*' . esc_attr($cliente) . '*',
            'search_columns' => ['display_name', 'user_email'],
            'fields'         => ['ID']
        ]);

        if (!empty($user_ids)) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
            $where .= " AND cliente_id IN ($placeholders)";
            $params = array_merge($params, array_map('intval', $user_ids));
        } else {
            wp_send_json_success([
                'devoluciones' => [],
                'total_paginas' => 0
            ]);
        }
    }

    if (!empty($estado)) {
        $where .= " AND estado_revision = %s";
        $params[] = $estado;
    }

    if (!empty($desde)) {
        $where .= " AND fecha_solicitud >= %s";
        $params[] = $desde . ' 00:00:00';
    }
    if (!empty($hasta)) {
        $where .= " AND fecha_solicitud <= %s";
        $params[] = $hasta . ' 23:59:59';
    }

    // Total
    $total = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}devoluciones_autopartes WHERE $where", ...$params)
    );

    // Resultados paginados
    $query = $wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}devoluciones_autopartes
        WHERE $where
        ORDER BY fecha_solicitud DESC
        LIMIT %d OFFSET %d
    ", ...array_merge($params, [$por_pagina, $offset]));

    $resultados = $wpdb->get_results($query);
    $devoluciones = [];

    foreach ($resultados as $d) {
        $cliente_id = intval($d->cliente_id);
        $user = get_userdata($cliente_id);

        $cliente_nombre = 'Cliente eliminado o no encontrado';
        if ($user && !is_wp_error($user)) {
            $cliente_nombre = $user->display_name ?: $user->user_login;
        }

        $producto = wc_get_product($d->producto_id);
        $producto_nombre = $producto ? $producto->get_name() : 'Producto eliminado';

        $devoluciones[] = [
            'id'       => $d->id,
            'cliente'  => $cliente_nombre,
            'producto' => $producto_nombre,
            'motivo'   => $d->motivo_cliente,
            'estado'   => $d->estado_revision,
            'fecha'    => date('Y-m-d H:i', strtotime($d->fecha_solicitud)),
        ];
    }

    wp_send_json_success([
        'devoluciones'   => $devoluciones,
        'total_paginas'  => ceil($total / $por_pagina)
    ]);
});

add_action('wp_ajax_ajax_obtener_detalle_reparacion', function () {
    global $wpdb;

    $id = intval($_POST['id'] ?? 0);
    if (!$id) wp_send_json_error(['message' => 'ID no válido']);

    $reparacion = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}reparaciones_autopartes WHERE id = $id");
    if (!$reparacion) wp_send_json_error(['message' => 'Reparación no encontrada.']);

    $producto = wc_get_product($reparacion->producto_id);
    $sku = $producto ? $producto->get_sku() : '—';
    $nombre = $producto ? $producto->get_name() : 'Producto eliminado';

    $devolucion = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}devoluciones_autopartes WHERE id = {$reparacion->devolucion_id}");
    $evidencias = $devolucion ? maybe_unserialize($devolucion->evidencia_urls) : [];
    $notas_devolucion = $devolucion ? $devolucion->notas_revision : '';

    wp_send_json_success([
        'sku' => $sku,
        'nombre' => $nombre,
        'notas_reparacion' => $reparacion->notas,
        'notas_devolucion' => $notas_devolucion,
        'evidencias' => is_array($evidencias) ? $evidencias : []
    ]);
});

// Obtener detalle de la devolución para el modal
add_action('wp_ajax_ajax_obtener_detalle_devolucion', function () {
    global $wpdb;

    $id = intval($_POST['devolucion_id'] ?? 0); // 🔧 Corrección aquí
    if (!$id) wp_send_json_error(['message' => 'ID no válido.']);

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}devoluciones_autopartes WHERE id = %d", $id));
    if (!$row) wp_send_json_error(['message' => 'Devolución no encontrada.']);

    $cliente = get_userdata($row->cliente_id);
    $producto = wc_get_product($row->producto_id);
    $evidencias = maybe_unserialize($row->evidencia_urls);

    wp_send_json_success([
        'id'         => $row->id,
        'cliente'    => $cliente ? $cliente->display_name : 'Cliente eliminado',
        'producto'   => $producto ? $producto->get_name() : 'Producto eliminado',
        'motivo'     => $row->motivo_cliente,
        'estado'     => $row->estado_revision,
        'evidencias' => is_array($evidencias) ? $evidencias : []
    ]);
});

// Función para crear solicitud de reembolso
function crear_solicitud_reembolso_devolucion($devolucion_id) {
    global $wpdb;

    $devolucion = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}devoluciones_autopartes WHERE id = %d", $devolucion_id
    ));

    if (!$devolucion) return;

    // Evitar duplicados
    $yaExiste = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}solicitudes_rembolso WHERE devolucion_id = %d",
        $devolucion_id
    ));
    if ($yaExiste) return;

    $venta_id = $devolucion->venta_id;
    $order_id = $devolucion->order_id;
    $producto_id = $devolucion->producto_id;
    $cliente_id = $devolucion->cliente_id;

    $metodo_pago = '';
    $tipo_cliente = '';
    $monto = 0.00;
    $nombre_cliente = '';

    // === Si es venta WooCommerce (order_id) ===
    if ($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $metodo_pago = $order->get_payment_method();
            $tipo_cliente = 'externo';
            $nombre_cliente = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

            // Obtener el monto real pagado por ese producto (con descuentos)
            foreach ($order->get_items() as $item) {
                if ((int)$item->get_product_id() === (int)$producto_id) {
                    $monto = floatval($item->get_total());
                    break;
                }
            }
        }
    }

    // === Si es venta interna (venta_id) ===
    elseif ($venta_id) {
        $venta = $wpdb->get_row($wpdb->prepare(
            "SELECT metodo_pago, tipo_cliente FROM {$wpdb->prefix}ventas_autopartes WHERE id = %d",
            $venta_id
        ));

        if ($venta) {
            $metodo_pago = $venta->metodo_pago ?: '';
            $tipo_cliente = $venta->tipo_cliente ?: '';
        }

        $user_info = get_userdata($cliente_id);
        if ($user_info) {
            $nombre_cliente = $user_info->display_name;
        }

        // Fallback al precio del producto solo si no se obtuvo desde Woo
        $precio_producto = get_post_meta($producto_id, '_price', true);
        if (is_numeric($precio_producto)) {
            $monto = floatval($precio_producto);
        }
    }

    // Si el monto no se pudo determinar, no continuar
    if ($monto <= 0) return;

    $wpdb->insert("{$wpdb->prefix}solicitudes_rembolso", [
        'devolucion_id'  => $devolucion_id,
        'venta_id'       => $venta_id ?: null,
        'order_id'       => $order_id ?: null,
        'monto'          => $monto,
        'metodo_pago'    => $metodo_pago,
        'tipo_cliente'   => $tipo_cliente,
        'cliente_nombre' => $nombre_cliente,
        'estado'         => 'pendiente',
        'fecha_creacion' => current_time('mysql')
    ]);
}

add_action('wp_ajax_ajax_obtener_rembolsos', function () {
    global $wpdb;

    $tabla = "{$wpdb->prefix}solicitudes_rembolso";
    $tabla_devoluciones = "{$wpdb->prefix}devoluciones_autopartes";
    $tabla_posts = "{$wpdb->prefix}posts";
    $tabla_postmeta = "{$wpdb->prefix}postmeta";
    $tabla_ventas = "{$wpdb->prefix}ventas_autopartes";

    $query = "
        SELECT 
            r.id, r.devolucion_id, r.estado, r.tipo_rembolso, r.monto, r.metodo_rembolso, r.fecha_creacion,
            r.venta_id, r.order_id, r.metodo_pago, r.tipo_cliente,
            d.producto_id, d.motivo_cliente, d.resolucion_final, d.cliente_id,
            p.post_title AS nombre_producto,
            sku.meta_value AS sku
        FROM $tabla r
        INNER JOIN $tabla_devoluciones d ON r.devolucion_id = d.id
        INNER JOIN $tabla_posts p ON d.producto_id = p.ID
        LEFT JOIN $tabla_postmeta sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
        ORDER BY r.fecha_creacion DESC
    ";

    $resultados = $wpdb->get_results($query);
    $rembolsos = [];

    foreach ($resultados as $r) {
        // Obtener nombre del cliente
        $cliente_nombre = '—';
        if ($r->tipo_cliente === 'externo' && $r->order_id) {
            $order = wc_get_order($r->order_id);
            if ($order) {
                $cliente_nombre = $order->get_formatted_billing_full_name();
            }
        } elseif ($r->tipo_cliente === 'interno' && $r->venta_id) {
            $nombre_cliente = $wpdb->get_var($wpdb->prepare("
                SELECT meta_value FROM {$wpdb->prefix}usermeta
                WHERE user_id = %d AND meta_key = 'nickname'
            ", $r->cliente_id));
            $cliente_nombre = $nombre_cliente ?: 'Interno';
        }

        $rembolsos[] = [
            'id'               => $r->id,
            'devolucion_id'    => $r->devolucion_id,
            'producto'         => $r->nombre_producto,
            'sku'              => $r->sku,
            'motivo'           => $r->motivo_cliente,
            'resolucion'       => $r->resolucion_final,
            'estado'           => $r->estado,
            'tipo_rembolso'    => $r->tipo_rembolso,
            'metodo_rembolso'  => $r->metodo_rembolso,
            'monto'            => $r->monto,
            'fecha'            => date('Y-m-d H:i', strtotime($r->fecha_creacion)),
            'venta_id'         => $r->venta_id,
            'order_id'         => $r->order_id,
            'tipo_cliente'     => $r->tipo_cliente,
            'metodo_pago'      => $r->metodo_pago,
            'cliente'          => $cliente_nombre
        ];
    }

    wp_send_json_success(['rembolsos' => $rembolsos]);
});

add_action('wp_ajax_ajax_detalles_rembolso', function () {
    global $wpdb;

    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        wp_send_json_error(['message' => 'ID inválido']);
    }

    $tabla_rembolsos     = "{$wpdb->prefix}solicitudes_rembolso";
    $tabla_devoluciones  = "{$wpdb->prefix}devoluciones_autopartes";
    $tabla_posts         = "{$wpdb->prefix}posts";
    $tabla_postmeta      = "{$wpdb->prefix}postmeta";
    $tabla_ventas        = "{$wpdb->prefix}ventas_autopartes";
    $tabla_cxc           = "{$wpdb->prefix}cuentas_cobrar";

    // Obtener rembolso y datos relacionados
    $rem = $wpdb->get_row($wpdb->prepare(
        "SELECT r.*, d.cliente_id, d.order_id, d.venta_id, d.producto_id, d.resolucion_final,
                p.post_title AS nombre_producto, sku.meta_value AS sku
         FROM $tabla_rembolsos r
         INNER JOIN $tabla_devoluciones d ON r.devolucion_id = d.id
         INNER JOIN $tabla_posts p ON d.producto_id = p.ID
         LEFT JOIN $tabla_postmeta sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
         WHERE r.id = %d",
        $id
    ));

    if (!$rem) {
        wp_send_json_error(['message' => 'Reembolso no encontrado.']);
    }

    // Obtener nombre del cliente
    $cliente = get_userdata($rem->cliente_id);
    $nombre_cliente = $cliente ? $cliente->display_name : '—';

    // Método de pago y cuenta por cobrar
    $tipo_pago = '';
    $monto_pendiente = 0;
    $cuenta_id = null;

    if ($rem->venta_id) {
        $cuenta = $wpdb->get_row($wpdb->prepare(
            "SELECT id, saldo_pendiente FROM $tabla_cxc WHERE venta_id = %d LIMIT 1",
            $rem->venta_id
        ));
    } elseif ($rem->order_id) {
        $cuenta = $wpdb->get_row($wpdb->prepare(
            "SELECT id, saldo_pendiente FROM $tabla_cxc WHERE order_id = %d LIMIT 1",
            $rem->order_id
        ));

        // Obtener método de pago desde WooCommerce
        $order = wc_get_order($rem->order_id);
        if ($order) {
            $tipo_pago = $order->get_payment_method();
        }
    }

    if (!empty($cuenta)) {
        $cuenta_id = $cuenta->id;
        $monto_pendiente = floatval($cuenta->saldo_pendiente);
    }

    // Si no hay método y sí hay venta POS
    if (!$tipo_pago && $rem->venta_id) {
        $tipo_pago = $wpdb->get_var($wpdb->prepare(
            "SELECT metodo_pago FROM $tabla_ventas WHERE id = %d LIMIT 1",
            $rem->venta_id
        ));
    }

    // Obtener comprobante del reembolso
    $comprobante_id = get_post_meta($rem->id, 'comprobante_id', true);
    $comprobante_url = $comprobante_id ? wp_get_attachment_url($comprobante_id) : '';

    wp_send_json_success([
        'id'              => $rem->id,
        'producto'        => $rem->nombre_producto,
        'sku'             => $rem->sku,
        'cliente'         => $nombre_cliente,
        'metodo_pago'     => $tipo_pago,
        'tipo_cliente'    => $rem->tipo_cliente,
        'monto'           => floatval($rem->monto),
        'estado'          => $rem->estado,
        'resolucion'      => $rem->resolucion_final,
        'tipo_rembolso'   => $rem->tipo_rembolso,
        'metodo_rembolso' => $rem->metodo_rembolso,
        'observaciones'   => $rem->observaciones,
        'venta_id'        => $rem->venta_id,
        'order_id'        => $rem->order_id,
        'cuenta_id'       => $cuenta_id,
        'monto_pendiente' => $monto_pendiente,
        'comprobante_url' => $comprobante_url,
    ]);

});

add_action('wp_ajax_ajax_guardar_resolucion_rembolso', function () {
    global $wpdb;

    $id = intval($_POST['id'] ?? 0);
    $observaciones = sanitize_text_field($_POST['observaciones'] ?? '');
    $accion_credito = sanitize_text_field($_POST['accion_credito'] ?? '');
    $usuario_id = get_current_user_id();

    if (!$id || !$usuario_id) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }

    $rembolso = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}solicitudes_rembolso WHERE id = %d", $id
    ));

    if (!$rembolso) {
        wp_send_json_error(['message' => 'Reembolso no encontrado.']);
    }

    if ($rembolso->estado === 'resuelto') {
        wp_send_json_error(['message' => 'Este reembolso ya ha sido procesado.']);
    }

    $monto = floatval($rembolso->monto);
    $es_credito = $rembolso->metodo_pago === 'credito_cliente';
    $cuenta_id = null;

    if ($es_credito) {
        $cuenta_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cuentas_cobrar WHERE order_id = %d OR venta_id = %d LIMIT 1",
            $rembolso->order_id,
            $rembolso->venta_id
        ));

        if (!$cuenta_id) {
            wp_send_json_error(['message' => 'No se encontró la cuenta por cobrar asociada.']);
        }

        // Validar que no se pague de más
        $saldo_pendiente_actual = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT saldo_pendiente FROM {$wpdb->prefix}cuentas_cobrar WHERE id = %d",
            $cuenta_id
        )));

        if ($monto > $saldo_pendiente_actual) {
            wp_send_json_error(['message' => 'El monto del reembolso excede el saldo pendiente de la cuenta por cobrar.']);
        }

        // Insertar el pago
        $wpdb->insert("{$wpdb->prefix}pagos_cxc", [
            'cuenta_id'      => $cuenta_id,
            'monto_pagado'   => $monto,
            'fecha_pago'     => current_time('mysql'),
            'tipo'           => ($accion_credito === 'liquidar') ? 'reembolso_total' : 'reembolso',
            'registrado_por' => $usuario_id,
            'notas'          => 'Pago automático desde módulo de reembolsos'
        ]);

        // Recalcular el estado de la cuenta
        $pagado_actual = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(monto_pagado) FROM {$wpdb->prefix}pagos_cxc WHERE cuenta_id = %d",
            $cuenta_id
        )));

        $monto_total = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT monto_total FROM {$wpdb->prefix}cuentas_cobrar WHERE id = %d",
            $cuenta_id
        )));

        $saldo_pendiente = max(0, $monto_total - $pagado_actual);
        $estado = ($saldo_pendiente <= 0) ? 'pagado' : 'pendiente';

        $wpdb->update("{$wpdb->prefix}cuentas_cobrar", [
            'monto_pagado'    => $pagado_actual,
            'saldo_pendiente' => $saldo_pendiente,
            'estado'          => $estado
        ], ['id' => $cuenta_id]);
    }

    // Métodos externos (efectivo/tarjeta/etc)
    else {
        $comprobante_id = null;

        if (!empty($_FILES['comprobante']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $comprobante_id = media_handle_upload('comprobante', 0);
        }

        if (!is_wp_error($comprobante_id) && $comprobante_id) {
            update_post_meta($id, 'comprobante_id', $comprobante_id);
        }
    }

    // Marcar el reembolso como resuelto
    $wpdb->update("{$wpdb->prefix}solicitudes_rembolso", [
        'estado'         => 'resuelto',
        'observaciones'  => $observaciones,
        'evaluado_por'   => $usuario_id,
        'fecha_resuelto' => current_time('mysql')
    ], ['id' => $id]);

    wp_send_json_success(['message' => '✅ Reembolso resuelto correctamente.']);
});

add_action('wp_ajax_ajax_guardar_resolucion_devolucion', function () {
    global $wpdb;

    $id = intval($_POST['devolucion_id'] ?? 0);
    $resolucion = sanitize_text_field($_POST['resolucion'] ?? '');
    $notas = sanitize_textarea_field($_POST['notas'] ?? '');
    $usuario_id = get_current_user_id();

    if (!$id || !$resolucion || !$usuario_id) {
        wp_send_json_error(['message' => 'Faltan datos obligatorios.']);
    }

    $resoluciones_validas = ['reintegrado', 'reparacion', 'baja_definitiva'];
    if (!in_array($resolucion, $resoluciones_validas)) {
        wp_send_json_error(['message' => 'Resolución no válida.']);
    }

    $devolucion = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}devoluciones_autopartes WHERE id = %d",
        $id
    ));

    if (!$devolucion) {
        wp_send_json_error(['message' => 'Devolución no encontrada.']);
    }

    if ($devolucion->estado_revision === 'resuelto') {
        wp_send_json_error(['message' => 'La devolución ya fue resuelta.']);
    }

    $producto_id = intval($devolucion->producto_id);

    // Procesos por tipo de resolución
    if ($resolucion === 'reintegrado') {
        delete_post_meta($producto_id, '_ubicacion_fisica');

        // ✅ Restablecer stock a 1
        update_post_meta($producto_id, '_stock', 1);
        update_post_meta($producto_id, '_stock_status', 'instock');
    }

    if ($resolucion === 'reparacion') {
        $wpdb->insert("{$wpdb->prefix}reparaciones_autopartes", [
            'producto_id'    => $producto_id,
            'devolucion_id'  => $id,
            'estado'         => 'pendiente',
            'notas'          => $notas,
            'fecha_registro' => current_time('mysql')
        ]);
    }

    if ($resolucion === 'baja_definitiva') {
        wp_delete_post($producto_id, true);
        $wpdb->delete("{$wpdb->prefix}postmeta", ['post_id' => $producto_id]);
    }

    // Actualizar devolución
    $actualizado = $wpdb->update("{$wpdb->prefix}devoluciones_autopartes", [
        'estado_revision'     => 'resuelto',
        'resolucion_final'    => $resolucion,
        'notas_revision'      => $notas,
        'usuario_revision_id' => $usuario_id,
        'fecha_revision'      => current_time('mysql')
    ], ['id' => $id]);

    if ($actualizado === false) {
        error_log("❌ Error al actualizar devolución ID $id: " . $wpdb->last_error);
        wp_send_json_error(['message' => 'No se pudo guardar la resolución.']);
    }

    // ✅ Crear solicitud de reembolso
    crear_solicitud_reembolso_devolucion($id);

    wp_send_json_success(['message' => 'Resolución registrada.']);
});

add_action('wp_ajax_ajax_verificar_devolucion_existente', function () {
    global $wpdb;

    $producto_id = intval($_POST['producto_id'] ?? 0);
    $order_id = intval($_POST['order_id'] ?? 0);

    if (!$producto_id || !$order_id) {
        wp_send_json_error(['message' => 'Faltan datos']);
    }

    // 🧪 Logging para verificar entrada
    error_log("🧪 Verificando devolución - Order ID: $order_id, Producto ID: $producto_id");

    // Obtener ID de la venta POS si aplica
    $venta_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}ventas_autopartes WHERE woo_order_id = %d LIMIT 1",
        $order_id
    ));

    // Buscar devolución existente, ya sea por venta o por orden directa
    $devolucion = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}devoluciones_autopartes 
        WHERE producto_id = %d 
        AND (venta_id = %d OR order_id = %d)
        AND estado_revision IN ('pendiente', 'resuelto')
        LIMIT 1",
        $producto_id,
        $venta_id ?: 0,
        $order_id
    ));

    if ($devolucion) {
        error_log("🟡 Devolución ya existente para producto $producto_id en orden #$order_id");
        wp_send_json_success(['existe' => true]);
    } else {
        error_log("✅ No hay devolución previa para producto $producto_id en orden #$order_id");
        wp_send_json_success(['existe' => false]);
    }
});

add_action('wp_ajax_ajax_productos_pendientes_reubicacion', function () {
    global $wpdb;

    $prefix = $wpdb->prefix;

    // Consulta para devoluciones con resolución = reintegrado
    $sql_devoluciones = "
        SELECT 
            d.id AS origen_id, 'devolucion' AS origen_tipo,
            p.ID AS producto_id, p.post_title AS nombre, p.post_excerpt AS descripcion,
            m.meta_value AS sku, img.meta_value AS imagen
        FROM {$prefix}devoluciones_autopartes d
        INNER JOIN {$prefix}posts p ON d.producto_id = p.ID
        LEFT JOIN {$prefix}postmeta m ON m.post_id = p.ID AND m.meta_key = '_sku'
        LEFT JOIN {$prefix}postmeta img ON img.post_id = p.ID AND img.meta_key = '_thumbnail_id'
        WHERE d.resolucion_final = 'reintegrado'
          AND d.estado_revision = 'resuelto'
          AND (
              NOT EXISTS (
                  SELECT 1 FROM {$prefix}postmeta um
                  WHERE um.post_id = p.ID AND um.meta_key = '_ubicacion_fisica'
              )
              OR (
                  EXISTS (
                      SELECT 1 FROM {$prefix}postmeta um2
                      WHERE um2.post_id = p.ID AND um2.meta_key = '_ubicacion_fisica'
                      AND (TRIM(um2.meta_value) = '' OR um2.meta_value IS NULL)
                  )
              )
          )
    ";

    // Consulta para productos reparados sin ubicación
    $sql_reparados = "
        SELECT 
            r.id AS origen_id, 'reparacion' AS origen_tipo,
            p.ID AS producto_id, p.post_title AS nombre, p.post_excerpt AS descripcion,
            m.meta_value AS sku, img.meta_value AS imagen
        FROM {$prefix}reparaciones_autopartes r
        INNER JOIN {$prefix}posts p ON r.producto_id = p.ID
        LEFT JOIN {$prefix}postmeta m ON m.post_id = p.ID AND m.meta_key = '_sku'
        LEFT JOIN {$prefix}postmeta img ON img.post_id = p.ID AND img.meta_key = '_thumbnail_id'
        WHERE r.estado = 'reparado'
        AND (
            NOT EXISTS (
                SELECT 1 FROM {$prefix}postmeta um
                WHERE um.post_id = p.ID AND um.meta_key = '_ubicacion_fisica'
            )
            OR (
                EXISTS (
                    SELECT 1 FROM {$prefix}postmeta um2
                    WHERE um2.post_id = p.ID AND um2.meta_key = '_ubicacion_fisica'
                    AND (TRIM(um2.meta_value) = '' OR um2.meta_value IS NULL)
                )
            )
        )
    ";

    // Unimos ambos
    $query = "$sql_devoluciones UNION $sql_reparados";

    $resultados = $wpdb->get_results($query);

    $productos = array_map(function ($row) {
        return [
            'producto_id'   => $row->producto_id,
            'origen_id'     => $row->origen_id,
            'origen_tipo'   => $row->origen_tipo,
            'sku'           => $row->sku,
            'nombre'        => $row->nombre,
            'descripcion'   => $row->descripcion,
            'imagen'        => wp_get_attachment_url($row->imagen) ?: '',
        ];
    }, $resultados);

    wp_send_json_success(['productos' => $productos]);
});


add_action('wp_ajax_ajax_buscar_producto_avanzado', 'ajax_buscar_producto_avanzado');
function ajax_buscar_producto_avanzado() {
    $termino = sanitize_text_field($_POST['termino'] ?? '');
    $cliente_id = intval($_POST['cliente_id'] ?? 0);

    if (empty($termino)) {
        wp_send_json_error('Término de búsqueda vacío');
    }

    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 15,
        's'              => $termino,
        'meta_query'     => [
            [
                'key'     => '_stock',
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC'
            ]
        ]
    ];

    $query = new WP_Query($args);
    $resultados = [];

    // Obtener roles del cliente
    $cliente_roles = [];
    if ($cliente_id) {
        $user = get_user_by('ID', $cliente_id);
        if ($user && !empty($user->roles)) {
            $cliente_roles = $user->roles;
        }
    }

    while ($query->have_posts()) {
        $query->the_post();
        $product = wc_get_product(get_the_ID());
        if (!$product || $product->get_stock_quantity() <= 0) continue;

        $precio_base = (float) $product->get_price();
        $precio_final = $precio_base;

        // Obtener precio con plugin Wholesale Suite
        if (function_exists('get_wholesale_price_for_user') && $cliente_id > 0) {
            $precio_plugin = get_wholesale_price_for_user($product, $cliente_id);
            if ($precio_plugin && is_numeric($precio_plugin)) {
                $precio_final = $precio_plugin;
            }
        }

        // Si no se encontró un precio de plugin, aplica descuento manual
        if ($precio_final == $precio_base && in_array('wholesale_talleres_crash', $cliente_roles)) {
            $precio_final = round($precio_base * 0.5, 2); // Aplica 50% manual
        }

        $galeria = array_map(function ($id) {
            return wp_get_attachment_image_url($id, 'large');
        }, $product->get_gallery_image_ids());

        $resultados[] = [
            'id'           => $product->get_id(),
            'sku'          => $product->get_sku(),
            'nombre'       => $product->get_name(),
            'precio'       => $precio_final,
            'precio_base'  => $precio_base,
            'stock'        => $product->get_stock_quantity(),
            'imagen'       => wp_get_attachment_image_url($product->get_image_id(), 'large'),
            'galeria'      => $galeria,
            'link'         => get_permalink($product->get_id()),
            'solicitud_id' => get_post_meta($product->get_id(), 'solicitud_id', true)
        ];
    }

    wp_reset_postdata();

    if (empty($resultados)) {
        wp_send_json_error('No se encontraron productos con stock disponible');
    }

    wp_send_json_success($resultados);
}

add_action('wp_ajax_obtener_roles_cliente', function () {
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    if (!$cliente_id) wp_send_json_error();

    $user = get_user_by('id', $cliente_id);
    if (!$user) wp_send_json_error();

    wp_send_json_success(['roles' => $user->roles]);
});

add_action('wp_ajax_ajax_verificar_caja_abierta', 'ajax_verificar_caja_abierta');
function ajax_verificar_caja_abierta() {
    global $wpdb;
    $usuario_id = get_current_user_id();

    $existe = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}aperturas_caja 
        WHERE usuario_id = %d AND estado = 'abierta'",
        $usuario_id
    ));

    if ($existe > 0) {
        wp_send_json_success(['mensaje' => 'Caja abierta']);
    } else {
        wp_send_json_error(['mensaje' => 'No tienes una caja abierta']);
    }
}

add_action('wp_ajax_ajax_buscar_clientes_pos', 'ajax_buscar_clientes_pos');
function ajax_buscar_clientes_pos() {
    $termino = sanitize_text_field($_POST['termino'] ?? '');

    global $wp_roles;

    // Roles válidos: los que tienen precio de mayoreo + "customer", excluyendo administrador
    $roles_validos = ['customer'];
    foreach ($wp_roles->roles as $slug => $role) {
        if (!empty($role['capabilities']['have_wholesale_price']) && $slug !== 'administrator') {
            $roles_validos[] = $slug;
        }
    }

    // Obtener usuarios
    $usuarios = get_users([
        'number' => -1,
        'role__in' => $roles_validos,
    ]);

    $resultados = [];

    foreach ($usuarios as $user) {
        $nombre_meta = get_user_meta($user->ID, 'nombre_completo', true);
        $nombre = $nombre_meta ?: $user->display_name;
        $correo = $user->user_email;
        $login  = $user->user_login;

        // Si hay término, filtrar
        if ($termino && (
            stripos($nombre, $termino) === false &&
            stripos($correo, $termino) === false &&
            stripos($login, $termino) === false
        )) {
            continue;
        }

        $resultados[] = [
            'id'                 => $user->ID,
            'nombre'            => $nombre,
            'correo'            => $correo,
            'tipo_cliente'      => get_user_meta($user->ID, 'tipo_cliente', true),
            'credito_disponible'=> get_user_meta($user->ID, 'credito_disponible', true),
            'estado_credito'    => get_user_meta($user->ID, 'estado_credito', true),
            'canal_venta'       => get_user_meta($user->ID, 'canal_venta', true),
        ];
    }

    wp_send_json_success($resultados);
}

add_action('wp_ajax_buscar_autopartes_compatibles', 'buscar_autopartes_compatibles');
add_action('wp_ajax_nopriv_buscar_autopartes_compatibles', 'buscar_autopartes_compatibles');

add_action('wp_ajax_ajax_listar_clientes', 'ajax_listar_clientes');
function ajax_listar_clientes() {
    $estado = sanitize_text_field($_POST['estado'] ?? '');
    $busqueda = sanitize_text_field($_POST['busqueda'] ?? '');

    global $wp_roles;
    $wholesale_roles = [];

    foreach ($wp_roles->roles as $slug => $data) {
        if ($slug === 'administrator') continue;
        if (!empty($data['capabilities']['have_wholesale_price']) || $slug === 'customer') {
            $wholesale_roles[] = $slug;
        }
    }

    $users = get_users([
        'role__in' => $wholesale_roles,
        'number' => 200,
    ]);

    $resultados = [];

    foreach ($users as $user) {
        if (in_array('administrator', $user->roles)) continue;

        $nombre = get_user_meta($user->ID, 'nombre_completo', true) ?: $user->display_name;
        $correo = $user->user_email;
        $tipo_cliente = get_user_meta($user->ID, 'tipo_cliente', true);
        $estado_credito = get_user_meta($user->ID, 'estado_credito', true);

        // Filtrar por búsqueda
        if (!empty($busqueda) && !str_contains(strtolower($nombre), $busqueda) && !str_contains(strtolower($correo), $busqueda)) {
            continue;
        }

        // Filtrar por estado
        if ($estado && $estado_credito !== $estado) continue;

        $rol_slug = $user->roles[0] ?? '';
        $rol_nombre = ucfirst(str_replace(['wholesale_', '_'], ['', ' '], $rol_slug));

        $resultados[] = [
            'id' => $user->ID,
            'nombre' => $nombre,
            'correo' => $correo,
            'tipo_cliente' => $tipo_cliente,
            'estado_credito' => $estado_credito,
            'credito_disponible' => get_user_meta($user->ID, 'credito_disponible', true) ?: 0,
            'canal_venta' => get_user_meta($user->ID, 'canal_venta', true) ?: '',
            'rol' => $rol_nombre,
        ];
    }

    wp_send_json_success($resultados);
}

add_action('wp_ajax_ajax_obtener_cliente', function () {
    $id = intval($_POST['user_id'] ?? 0);
    if (!$id) wp_send_json_error();

    $user = get_userdata($id);
    if (!$user) wp_send_json_error();

    $data = [
        'id' => $id,
        'nombre' => get_user_meta($id, 'nombre_completo', true),
        'tipo_cliente' => get_user_meta($id, 'tipo_cliente', true),
        'estado_credito' => get_user_meta($id, 'estado_credito', true),
        'credito_disponible' => get_user_meta($id, 'credito_disponible', true),
        'dias_credito' => get_user_meta($id, 'dias_credito', true),
        'canal_venta' => get_user_meta($id, 'canal_venta', true),
        'oc_obligatoria' => get_user_meta($id, 'oc_obligatoria', true),
        'rol_slug' => $user->roles[0] ?? 'customer', // 👈 agregado para mostrar rol actual
    ];

    wp_send_json_success($data);
});

add_action('wp_ajax_ajax_actualizar_cliente', function () {
    $id = intval($_POST['user_id'] ?? 0);
    if (!$id) wp_send_json_error();
    if (!current_user_can('administrator') && !in_array('cobranza', wp_get_current_user()->roles)) {
        wp_send_json_error(['message' => 'No tienes permiso para guardar cambios']);
    }

    // Actualizar campos personalizados
    update_user_meta($id, 'nombre_completo', sanitize_text_field($_POST['nombre'] ?? ''));
    update_user_meta($id, 'tipo_cliente', sanitize_text_field($_POST['tipo'] ?? 'externo'));
    update_user_meta($id, 'estado_credito', sanitize_text_field($_POST['estado_credito'] ?? 'activo'));
    update_user_meta($id, 'credito_disponible', floatval($_POST['credito'] ?? 0));
    update_user_meta($id, 'dias_credito', intval($_POST['dias'] ?? 0));
    update_user_meta($id, 'canal_venta', sanitize_text_field($_POST['canal'] ?? ''));
    update_user_meta($id, 'oc_obligatoria', intval($_POST['oc'] ?? 0));

    // Cambiar rol si se proporcionó uno válido y no es 'administrator'
    if (!empty($_POST['rol']) && $_POST['rol'] !== 'administrator') {
        $user = new WP_User($id);
        $user->set_role(sanitize_text_field($_POST['rol']));
    }

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
                'imagen' => get_the_post_thumbnail_url(get_the_ID(), 'large') ?: wc_placeholder_img_src(),
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

//Enpoints apertura caja
add_action('wp_ajax_ajax_verificar_estado_caja', function () {
    global $wpdb;
    $user_id = get_current_user_id();

    $caja = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}aperturas_caja
        WHERE usuario_id = %d AND estado = 'abierta'
        ORDER BY fecha_apertura DESC LIMIT 1
    ", $user_id));

    if ($caja) {
        wp_send_json_success([
            'abierta' => true,
            'fecha_apertura' => $caja->fecha_apertura,
            'monto_inicial' => $caja->monto_inicial
        ]);
    } else {
        wp_send_json_success(['abierta' => false]);
    }
});

add_action('wp_ajax_ajax_abrir_caja', function () {
    global $wpdb;
    $user_id = get_current_user_id();
    $notas = sanitize_textarea_field($_POST['notas'] ?? '');
    $denominaciones = $_POST['denominaciones'] ?? [];

    if (!is_array($denominaciones) || empty($denominaciones)) {
        wp_send_json_error(['message' => 'No se proporcionaron denominaciones.']);
    }

    $monto_total = 0;
    foreach ($denominaciones as $valor => $cantidad) {
        $valor = intval($valor);
        $cantidad = intval($cantidad);
        if ($valor > 0 && $cantidad > 0) {
            $monto_total += $valor * $cantidad;
        }
    }

    // Verificar que no haya una caja abierta
    $ya_abierta = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}aperturas_caja WHERE usuario_id = %d AND estado = 'abierta'",
        $user_id
    ));

    if ($ya_abierta > 0) {
        wp_send_json_error(['message' => 'Ya tienes una caja abierta.']);
    }

    $insertado = $wpdb->insert("{$wpdb->prefix}aperturas_caja", [
        'usuario_id'      => $user_id,
        'monto_inicial'   => $monto_total,
        'fecha_apertura'  => current_time('mysql'),
        'estado'          => 'abierta',
        'notas'           => $notas,
        'detalle_apertura'=> maybe_serialize($denominaciones)
    ]);

    if (!$insertado) {
        wp_send_json_error(['message' => 'Error al registrar apertura de caja.']);
    }
    // Obtener nombre del usuario para mostrar en el ticket
    $caja_id = $wpdb->insert_id;

    $usuario = get_userdata($user_id); // ✅ Obtener usuario correctamente

    wp_send_json_success([
        'message' => 'Caja abierta correctamente',
        'resumen' => [
            'id'             => $wpdb->insert_id, // ✅ ID de la caja
            'fecha_apertura' => current_time('mysql'),
            'monto_inicial'  => $monto_total
        ],
        'usuario' => $usuario ? $usuario->display_name : 'Desconocido'
    ]);

});

add_action('wp_ajax_ajax_cerrar_caja', function () {
    global $wpdb;

    $user_id = get_current_user_id();
    $notas = sanitize_textarea_field($_POST['notas'] ?? '');

    // ✅ Asegúrate de decodificar correctamente el JSON enviado desde JS
    $detalle = isset($_POST['detalle_cierre']) ? json_decode(stripslashes($_POST['detalle_cierre']), true) : [];

    if (empty($detalle) || !is_array($detalle)) {
        wp_send_json_error(['message' => 'No se proporcionó el conteo de efectivo.']);
    }

    // Validar si hay una caja abierta
    $caja = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}aperturas_caja
        WHERE usuario_id = %d AND estado = 'abierta'
        ORDER BY fecha_apertura DESC LIMIT 1
    ", $user_id));

    if (!$caja) {
        wp_send_json_error(['message' => 'No hay una caja abierta para cerrar.']);
    }

    // Calcular total contado
    $total_contado = 0;
    foreach ($detalle as $denom => $cantidad) {
        $total_contado += intval($denom) * intval($cantidad);
    }

    // Guardar el cierre
    $wpdb->update("{$wpdb->prefix}aperturas_caja", [
        'estado'          => 'cerrada',
        'fecha_cierre'    => current_time('mysql'),
        'notas'           => $notas,
        'total_cierre' => $total_contado,
        'diferencia'   => $total_contado - floatval($caja->monto_inicial),
    ], ['id' => $caja->id]);

    wp_send_json_success([
        'message' => 'Caja cerrada correctamente.',
        'resumen' => [
            'id'              => $caja->id, // ✅ Agrega el ID aquí
            'monto_inicial'   => floatval($caja->monto_inicial),
            'monto_cierre'    => $total_contado,
            'fecha_apertura'  => $caja->fecha_apertura,
            'fecha_cierre'    => current_time('mysql'),
            'diferencia'      => $total_contado - floatval($caja->monto_inicial),
            'ventas_efectivo' => 0
        ]
    ]);
});

add_action('wp_ajax_ajax_estado_caja', 'ajax_estado_caja');

function ajax_estado_caja() {
    global $wpdb;

    $user_id = get_current_user_id();

    $caja = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}aperturas_caja
        WHERE usuario_id = %d AND estado = 'abierta'
        ORDER BY fecha_apertura DESC LIMIT 1
    ", $user_id));

    if ($caja) {
        wp_send_json_success([
            'estado' => 'abierta',
            'fecha' => date('d/m/Y H:i', strtotime($caja->fecha_apertura)),
            'monto_inicial' => floatval($caja->monto_inicial)
        ]);
    } else {
        wp_send_json_success(['estado' => 'cerrada']);
    }
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
    // Evita aplicar estilos si es administrador
    if (current_user_can('administrator')) return;

    // Aplica estilos solo si el usuario tiene alguno de los roles personalizados
    if (
        current_user_can('rol_capturista') ||
        current_user_can('rol_solicitudes') ||
        current_user_can('punto_de_venta') ||
        current_user_can('cobranza') ||
        current_user_can('almacenista')
    ) {
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

// Para WooCommerce, solo necesitas 2 argumentos
add_filter('woocommerce_login_redirect', function($redirect_to, $user) {
    return redireccion_por_rol_personalizado($redirect_to, '', $user);
}, 10, 2);

// Para login normal de WP, sí se usan los 3
add_filter('login_redirect', 'redireccion_por_rol_personalizado', 10, 3);

function redireccion_por_rol_personalizado($redirect_to, $requested_redirect_to, $user) {
    if (!is_wp_error($user)) {
        $roles = (array) $user->roles;

        if (in_array('rol_capturista', $roles)) {
            return admin_url('admin.php?page=captura-productos');
        }
        if (in_array('rol_solicitudes', $roles)) {
            return admin_url('admin.php?page=solicitudes-autopartes');
        }
        if (in_array('punto_de_venta', $roles)) {
            return admin_url('admin.php?page=ventas-autopartes');
        }
        if (in_array('cobranza', $roles)) {
            return admin_url('admin.php?page=cuentas-por-cobrar');
        }
        if (in_array('almacenista', $roles)) {
            return admin_url('admin.php?page=gestion-pedidos');
        }
    }

    return $redirect_to;
}


//Funcionalida para credito 
// Agregar Gateway Personalizado de Pago a Crédito
add_filter('woocommerce_payment_gateways', function($methods) {
    $methods[] = 'WC_Gateway_Credito_Cliente';
    return $methods;
});

// Registrar clase del Gateway
add_action('plugins_loaded', function() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_Gateway_Credito_Cliente extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'credito_cliente';
            $this->has_fields = false;
            $this->method_title = 'Pago a Crédito';
            $this->method_description = 'Pago usando crédito disponible para clientes aprobados.';
        
            $this->init_form_fields();
            $this->init_settings();
        
            $this->enabled = $this->get_option('enabled'); // <-- Faltaba esta línea ✅
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
        
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title' => 'Activar/Desactivar',
                    'type' => 'checkbox',
                    'label' => 'Habilitar Pago a Crédito',
                    'default' => 'yes'
                ],
                'title' => [
                    'title' => 'Título mostrado al cliente',
                    'type' => 'text',
                    'default' => 'Pago a Crédito'
                ],
                'description' => [
                    'title' => 'Descripción',
                    'type' => 'textarea',
                    'default' => 'Usa tu crédito disponible para completar tu compra.'
                ]
            ];
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $order->payment_complete();
            WC()->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            ];
        }
    }
});

// Mostrar "Crédito" solo a clientes con crédito activo
add_filter('woocommerce_available_payment_gateways', function($gateways) {
    if (is_admin()) return $gateways;

    // Proteger también en bloques y AJAX
    if (!is_user_logged_in()) {
        unset($gateways['credito_cliente']);
        return $gateways;
    }

    $user_id = get_current_user_id();
    $estado_credito = get_user_meta($user_id, 'estado_credito', true);

    if (strtolower($estado_credito) !== 'activo') {
        unset($gateways['credito_cliente']);
    }

    return $gateways;
});

//Forzar que WooCommerce no muestre pasarela si usuario es guest
add_filter('woocommerce_payment_gateways', function($methods) {
    if (!is_user_logged_in()) return $methods; // No registrar gateway si no está logueado
    $methods[] = 'WC_Gateway_Credito_Cliente';
    return $methods;
});

// Validar crédito disponible y estado antes de procesar el checkout
add_action('woocommerce_checkout_process', function() {
    if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'credito_cliente') {
        $user_id = get_current_user_id();
        if (!$user_id) return;

        global $wpdb;

        $estado_credito = get_user_meta($user_id, 'estado_credito', true);
        $credito_total = floatval(get_user_meta($user_id, 'credito_disponible', true) ?: 0);

        $cuentas = $wpdb->get_results($wpdb->prepare(
            "SELECT saldo_pendiente FROM {$wpdb->prefix}cuentas_cobrar WHERE cliente_id = %d AND estado = 'pendiente'",
            $user_id
        ));

        $deuda_actual = 0;
        foreach ($cuentas as $cuenta) {
            $deuda_actual += floatval(str_replace(['$', ','], '', $cuenta->saldo_pendiente));
        }

        $credito_disponible = $credito_total - $deuda_actual;

        $total_carrito = WC()->cart->get_total('edit'); // string como "$1,600.00"
        $total_numerico = floatval(preg_replace('/[^\d.]/', '', $total_carrito));

        if (strtolower($estado_credito) !== 'activo') {
            wc_add_notice('❌ Tu crédito no está activo.', 'error');
        } elseif ($credito_disponible < $total_numerico) {
            wc_add_notice('❌ No tienes crédito suficiente para esta compra.', 'error');
        }
    }
});

//crear cuenta por cobrar
function crear_cuenta_cobrar_manual($order_id) {
    global $wpdb;

    if (!$order_id) {
        error_log('⚠️ crear_cuenta_cobrar_manual: order_id vacío');
        return false;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('⚠️ crear_cuenta_cobrar_manual: No se encontró la orden ' . $order_id);
        return false;
    }

    if ($order->get_payment_method() !== 'credito_cliente') {
        error_log('⚠️ Método de pago no es credito_cliente, es: ' . $order->get_payment_method());
        return false;
    }

    if (get_post_meta($order_id, '_cuenta_cobrar_generada', true)) {
        error_log('⛔ Cuenta por cobrar ya generada para pedido: ' . $order_id);
        return false;
    }

    $cliente_id = $order->get_customer_id();
    $total_orden = floatval($order->get_total());

    if ($total_orden <= 0) {
        error_log("❌ Total de la orden $order_id inválido.");
        return false;
    }

    $oc_url = get_post_meta($order_id, '_orden_compra_cliente', true) ?: null;
    $dias_credito = intval(get_user_meta($cliente_id, 'dias_credito', true)) ?: 15;
    $fecha_creacion = current_time('mysql');
    $fecha_limite_pago = date('Y-m-d H:i:s', strtotime("+$dias_credito days"));

    // Preparar los datos de inserción
    $datos_cxc = [
        'cliente_id'        => $cliente_id,
        'vendedor_id'       => 0, // Puedes cambiar si quieres asignar vendedor específico
        'monto_total'       => $total_orden,
        'monto_pagado'      => 0,
        'saldo_pendiente'   => $total_orden,
        'fecha_creacion'    => $fecha_creacion,
        'fecha_limite_pago' => $fecha_limite_pago,
        'estado'            => 'pendiente',
        'orden_compra_url'  => $oc_url,
        'order_id'          => $order_id // SIEMPRE relacionamos el pedido Woo
    ];

    // Si existe venta_id (por ejemplo, si algún día usas POS para crear pedidos)
    if (metadata_exists('post', $order_id, '_venta_autoparte_id')) {
        $venta_id = get_post_meta($order_id, '_venta_autoparte_id', true);
        if (!empty($venta_id)) {
            $datos_cxc['venta_id'] = intval($venta_id);
        }
    }

    // Insertar la cuenta por cobrar
    $insertado = $wpdb->insert("{$wpdb->prefix}cuentas_cobrar", $datos_cxc);
    // Marcar solicitud como vendida si aplica
    $items = $order->get_items();
    foreach ($items as $item) {
        $product_id = $item->get_product_id();
        $solicitud_id = get_post_meta($product_id, 'solicitud_id', true);
        if ($solicitud_id) {
            $wpdb->update("{$wpdb->prefix}solicitudes_piezas", [
                'estado' => 'vendido'
            ], ['id' => intval($solicitud_id)]);
        }
    }

    if ($insertado !== false) {
        update_post_meta($order_id, '_cuenta_cobrar_generada', 1);
        error_log("✅ Cuenta CxC creada exitosamente para pedido $order_id");
        return true;
    } else {
        error_log("❌ Error al crear Cuenta CxC en MySQL: " . $wpdb->last_error);
        return false;
    }
}

//Crear cuenta por cobrar al momento de registrar la compra 
add_action('woocommerce_payment_complete', function($order_id) {

    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order || $order->get_payment_method() !== 'credito_cliente') return;

    error_log('✅ Método de pago credito_cliente confirmado para pedido: ' . $order_id);

    crear_cuenta_cobrar_manual($order_id);
}, 10, 1);

// Guardar archivo de OC
// add_action('woocommerce_checkout_create_order', 'guardar_oc_en_biblioteca', 10, 2);

// function guardar_oc_en_biblioteca($order, $data) {
//     error_log('📥 Hook `woocommerce_checkout_create_order` disparado para pedido ID: ' . $order->get_id());

//     if (!isset($_FILES['orden_compra_file']) || empty($_FILES['orden_compra_file']['name'])) {
//         error_log('⚠️ No se detectó archivo para OC en checkout');
//         return;
//     }

//     require_once ABSPATH . 'wp-admin/includes/file.php';
//     require_once ABSPATH . 'wp-admin/includes/media.php';
//     require_once ABSPATH . 'wp-admin/includes/image.php';

//     $file = $_FILES['orden_compra_file'];
//     $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];

//     if (!in_array($file['type'], $allowed_mimes)) {
//         error_log('❌ Tipo de archivo no permitido: ' . $file['type']);
//         return;
//     }

//     if (in_array($file['type'], ['image/jpeg', 'image/png'])) {
//         $editor = wp_get_image_editor($file['tmp_name']);
//         if (!is_wp_error($editor)) {
//             $editor->resize(1200, 1200, false);
//             $editor->set_quality(75);
//             $resultado = $editor->save($file['tmp_name']);
//             if (!is_wp_error($resultado)) {
//                 error_log('🧯 Imagen OC comprimida con éxito');
//             } else {
//                 error_log('⚠️ Falló la compresión: ' . $resultado->get_error_message());
//             }
//         }
//     }

//     $attachment_id = media_handle_upload('orden_compra_file', 0);

//     if (is_wp_error($attachment_id)) {
//         error_log('❌ Error al subir archivo OC: ' . $attachment_id->get_error_message());
//         return;
//     }

//     $url = wp_get_attachment_url($attachment_id);
//     update_post_meta($order->get_id(), '_orden_compra_cliente', $url);
//     update_post_meta($order->get_id(), '_orden_compra_attachment_id', $attachment_id);

//     error_log('✅ Orden de compra subida para pedido ID: ' . $order->get_id());
// }

// Mostrar enlace de OC en admin
add_action('woocommerce_admin_order_data_after_billing_address', function($order){
    $oc_url = get_post_meta($order->get_id(), '_orden_compra_cliente', true);
    if ($oc_url) {
        echo '<p><strong>Orden de Compra:</strong> <a href="' . esc_url($oc_url) . '" target="_blank">Ver Orden de Compra</a></p>';
    }
}, 10, 1);
add_action('template_redirect', function() {
    if (function_exists('is_checkout') && is_checkout()) {
        $user_id = get_current_user_id();
        if ($user_id) {
            $estado_credito = get_user_meta($user_id, 'estado_credito', true);
        } else {
            error_log('❗ Debug Checkout: No hay cliente logueado');
        }
    }
});

// Habilitar el método de pago "credito_cliente" en WooCommerce Blocks
add_action('woocommerce_blocks_loaded', function() {
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    if (!class_exists('WC_Gateway_Credito_Cliente_Blocks')) {
        class WC_Gateway_Credito_Cliente_Blocks extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
            protected $name = 'credito_cliente'; // ID exacto
            protected $settings = [];
            protected $supports = [ 'products', 'cart', 'checkout' ];
    
            public function initialize() {
                $this->settings = get_option('woocommerce_credito_cliente_settings', []);
            }
    
            public function get_payment_method_script_handles() {
                return []; // Esto es obligatorio aunque no cargues JS adicional
            }
        }
    }

    add_action('woocommerce_blocks_payment_method_type_registration', function($payment_method_registry) {
        $payment_method_registry->register(new WC_Gateway_Credito_Cliente_Blocks());
    });
});


add_filter('woocommerce_my_account_my_orders_actions', function($actions, $order) {
    $order_id = $order->get_id();
    $estado_armado = get_post_meta($order_id, '_estado_armado', true);
    $metodo_pago = $order->get_payment_method();

    $cliente_id = $order->get_user_id();
    $oc_obligatoria = get_user_meta($cliente_id, 'oc_obligatoria', true) === '1';
    $orden_compra_url = get_post_meta($order_id, '_orden_compra_url', true);

    // ✅ Asegurar código de recepción
    $codigo_recepcion = get_post_meta($order_id, '_codigo_recepcion', true);
    if ($estado_armado === 'enviado' && empty($codigo_recepcion)) {
        $codigo_recepcion = strtoupper(wp_generate_password(6, false, false));
        update_post_meta($order_id, '_codigo_recepcion', $codigo_recepcion);
    }

    // ✅ Mostrar botón para subir OC
    if (
        in_array($estado_armado, ['en_armado', 'listo_para_envio']) &&
        $metodo_pago === 'credito_cliente' &&
        $oc_obligatoria &&
        empty($orden_compra_url)
    ) {
        $actions['subir_oc'] = [
            'url'  => '#',
            'name' => 'Subir OC',
            'custom_data' => [
                'order-id' => $order_id
            ]
        ];
    }

    // ✅ Botón con código
    if ($estado_armado === 'enviado' && !$order->has_status(['completed', 'cancelled', 'failed'])) {
        $actions['recibir'] = [
            'url' => '', // <- evita el warning
            'name' => 'Recibir',
            'custom_html' => sprintf(
                '<a href="#" class="woocommerce-button wp-element-button button recibir"
                    data-order="%d"
                    data-metodo="%s"
                    data-codigo="%s"
                    data-loaded="true">Recibir</a>',
                $order_id,
                esc_attr($metodo_pago),
                esc_attr($codigo_recepcion)
            )
        ];
    }

    // ✅ Botón de devolución
    if (in_array($estado_armado, ['recibido', 'entregado'])) {
        $fecha_completado = strtotime($order->get_date_completed());
        if ($fecha_completado && (time() - $fecha_completado) <= (15 * 86400)) {
            $actions['devolver'] = [
                'url'  => '#',
                'name' => 'Solicitar Devolución',
                'custom_data' => [
                    'order-id' => $order_id
                ]
            ];
        }
    }

    return $actions;
}, 20, 2);


add_action('wp_ajax_ajax_obtener_codigo_recepcion', function () {
    $order_id = intval($_POST['order_id'] ?? 0);
    if (!$order_id) {
        wp_send_json_error(['message' => 'ID no válido']);
    }

    $codigo = get_post_meta($order_id, '_codigo_recepcion', true);
    wp_send_json_success(['codigo' => $codigo]);
});

add_action('wp_ajax_ajax_obtener_codigo_confirmacion', function () {
    $pedido_id = intval($_POST['order_id'] ?? 0);
    if (!$pedido_id) {
        wp_send_json_error(['message' => 'ID inválido']);
    }

    $codigo = get_post_meta($pedido_id, '_codigo_recepcion', true);
    if (!$codigo) {
        wp_send_json_error(['message' => 'Código no generado aún.']);
    }

    wp_send_json_success(['codigo' => $codigo]);
});

add_action('wp_ajax_ajax_validar_envio_pedido', function () {
    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    if (!$pedido_id) {
        wp_send_json_error(['message' => 'ID de pedido inválido.']);
    }

    $order = wc_get_order($pedido_id);
    if (!$order) {
        wp_send_json_error(['message' => 'Pedido no encontrado.']);
    }

    $user_id = $order->get_user_id();
    $metodo_pago = $order->get_payment_method();
    $oc_obligatoria = get_user_meta($user_id, 'oc_obligatoria', true) === '1';
    $oc_url = get_post_meta($pedido_id, '_orden_compra_url', true);

    if ($metodo_pago === 'credito_cliente' && $oc_obligatoria && empty($oc_url)) {
        wp_send_json_error(['message' => 'Este pedido requiere que el cliente suba una Orden de Compra antes de poder marcarlo como enviado.']);
    }

    wp_send_json_success(['message' => 'Validación aprobada.']);
});

add_filter('woocommerce_my_account_my_orders_actions_html', function($html, $action, $order) {
    if (!empty($action['custom_html']) && empty($action['url'])) {
        return $action['custom_html'];
    }
    return $html;
}, 10, 3);

//Neogciaciones Refacciones 
add_action('wp_ajax_ajax_solicitar_negociacion_precio', function () {
    global $wpdb;

    $usuario_id = get_current_user_id();
    $cliente_id = intval($_POST['cliente_id']);
    $sku = sanitize_text_field($_POST['sku']);
    $nombre = sanitize_text_field($_POST['nombre']);
    $precio_actual = floatval($_POST['precio_actual']);
    $precio_solicitado = floatval($_POST['precio_solicitado']);
    $motivo = sanitize_text_field($_POST['motivo']);

    $tabla = $wpdb->prefix . 'negociaciones_precios';

    $insertado = $wpdb->insert($tabla, [
        'user_id' => $usuario_id,
        'cliente_id' => $cliente_id,
        'producto_sku' => $sku,
        'nombre_producto' => $nombre,
        'precio_original' => $precio_actual,
        'precio_solicitado' => $precio_solicitado,
        'motivo' => $motivo,
        'estado' => 'pendiente',
        'fecha_creacion' => current_time('mysql'),
    ]);

    if ($insertado) {
        wp_send_json_success('Solicitud guardada');
    } else {
        wp_send_json_error('No se pudo registrar la solicitud.');
    }
});

add_action('wp_ajax_ajax_obtener_negociaciones_pendientes', function () {
    global $wpdb;
    $tabla = $wpdb->prefix . 'negociaciones_precios';

    $negociaciones = $wpdb->get_results("
        SELECT id, producto_sku, nombre_producto, precio_original, precio_solicitado, motivo
        FROM $tabla
        WHERE estado = 'pendiente'
        ORDER BY fecha_creacion DESC
        LIMIT 50
    ");

    wp_send_json_success($negociaciones);
});

add_action('wp_ajax_ajax_responder_negociacion_precio', function () {
    global $wpdb;

    $id = intval($_POST['id']);
    $accion = sanitize_text_field($_POST['accion']);
    $comentario = sanitize_text_field($_POST['comentario']);

    if (!in_array($accion, ['aprobar', 'rechazar'])) {
        wp_send_json_error('Acción inválida.');
    }

    $estado = $accion === 'aprobar' ? 'aprobado' : 'rechazado';

    $tabla = $wpdb->prefix . 'negociaciones_precios';

    // Verificar que exista la negociación
    $existe = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabla WHERE id = %d", $id));
    if (!$existe) {
        wp_send_json_error('La negociación no existe.');
    }

    $res = $wpdb->update(
        $tabla,
        [
            'estado' => $estado,
            'comentario_aprobacion' => $comentario,
            'fecha_aprobacion' => current_time('mysql'),
            'aprobado_por' => get_current_user_id()
        ],
        ['id' => $id],
        ['%s', '%s', '%s', '%d'],
        ['%d']
    );

    if ($res !== false) {
        wp_send_json_success("Negociación marcada como '$estado'.");
    } else {
        wp_send_json_error('No se realizaron cambios en la negociación.');
    }
});

add_action('wp_ajax_ajax_obtener_mis_negociaciones_aprobadas', function () {
    global $wpdb;
    $user_id = get_current_user_id();
    $tabla = $wpdb->prefix . 'negociaciones_precios';

    $query = $wpdb->prepare("
        SELECT 
            n.id, 
            n.producto_sku, 
            n.nombre_producto, 
            n.precio_original, 
            n.precio_solicitado, 
            n.estado, 
            n.cliente_id,
            meta.meta_value AS cliente_nombre,
            u.user_email AS cliente_correo
        FROM $tabla n
        LEFT JOIN {$wpdb->prefix}users u ON u.ID = n.cliente_id
        LEFT JOIN {$wpdb->prefix}usermeta meta ON meta.user_id = u.ID AND meta.meta_key = 'nombre_completo'
        WHERE n.user_id = %d
        AND n.estado = 'aprobado'
        AND n.vendido = 0
        ORDER BY n.fecha_creacion DESC
        LIMIT 100
    ", $user_id);

    $negociaciones = $wpdb->get_results($query, ARRAY_A);

    wp_send_json_success($negociaciones);
});


add_action('wp_ajax_ajax_admin_listar_negociaciones', function () {
    global $wpdb;

    $estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
    $cliente = isset($_GET['cliente']) ? sanitize_text_field($_GET['cliente']) : '';

    $tabla_negociaciones = $wpdb->prefix . 'negociaciones_precios';
    $tabla_usuarios = $wpdb->prefix . 'users';
    $tabla_autopartes = $wpdb->prefix . 'autopartes';

    // WHERE base
    $where = "1=1";
    $params = [];

    if (in_array($estado, ['pendiente', 'aprobado', 'rechazado'])) {
        $where .= " AND n.estado = %s";
        $params[] = $estado;
    }

    if (!empty($cliente)) {
        $where .= " AND (LOWER(u.display_name) LIKE %s OR LOWER(u.user_email) LIKE %s)";
        $params[] = '%' . strtolower($cliente) . '%';
        $params[] = '%' . strtolower($cliente) . '%';
    }

    // Consulta principal
    $query = "
        SELECT 
            n.*, 
            u.display_name AS cliente_nombre,
            u.user_email AS cliente_correo,
            a.imagen_lista AS imagen_producto
        FROM $tabla_negociaciones n
        LEFT JOIN $tabla_usuarios u ON u.ID = n.cliente_id
        LEFT JOIN $tabla_autopartes a ON REPLACE(n.producto_sku, SUBSTRING_INDEX(n.producto_sku, '#', -1), '') = a.codigo
        WHERE $where
        ORDER BY n.fecha_creacion DESC
        LIMIT 100
    ";

    // Preparar y ejecutar con parámetros
    $prepared = $wpdb->prepare($query, $params);
    $resultados = $wpdb->get_results($prepared, ARRAY_A);

    // Si hay imágenes adicionales desde galería, agrégalas
    foreach ($resultados as &$negociacion) {
        $sku = $negociacion['producto_sku'];
        $post_id = wc_get_product_id_by_sku($sku);
        $galeria = [];

        if ($post_id) {
            $galeria = [];

            if ($post_id) {
                $galeria_ids = get_post_meta($post_id, '_product_image_gallery', true);
                if (!empty($galeria_ids)) {
                    $ids = explode(',', $galeria_ids);
                    foreach ($ids as $id) {
                        $url = wp_get_attachment_url($id);
                        if ($url) $galeria[] = $url;
                    }
                }

                $imagen_destacada = get_the_post_thumbnail_url($post_id, 'full');
                if ($imagen_destacada) {
                    array_unshift($galeria, $imagen_destacada);
                }
            }

            $negociacion['imagenes'] = $galeria;
            $imagen_destacada = get_the_post_thumbnail_url($post_id, 'full');
            if ($imagen_destacada) {
                array_unshift($galeria, $imagen_destacada);
            }
        }

        $negociacion['imagenes'] = $galeria;
    }

    wp_send_json_success($resultados);
});

add_action('wp_ajax_ajax_recibir_pedido_credito_cliente', 'ajax_recibir_pedido_credito_cliente');

function ajax_recibir_pedido_credito_cliente() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error(['message' => 'Pedido inválido o no autorizado']);
    }

    $order = wc_get_order($order_id);
    if (!$order || $order->get_customer_id() !== get_current_user_id()) {
        wp_send_json_error(['message' => 'No autorizado para este pedido']);
    }

    // Si es crédito, puede traer archivo
    $metodo = $order->get_payment_method();
    $oc_url = null;

    if ($metodo === 'credito_cliente' && isset($_FILES['orden_compra']) && !empty($_FILES['orden_compra']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file = $_FILES['orden_compra'];
        $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowed)) {
            wp_send_json_error(['message' => 'Tipo de archivo no permitido.']);
        }

        // Intentar comprimir si es imagen
        if (in_array($file['type'], ['image/jpeg', 'image/png'])) {
            $editor = wp_get_image_editor($file['tmp_name']);
            if (!is_wp_error($editor)) {
                $editor->resize(1200, 1200, false);
                $editor->set_quality(75);
                $editor->save($file['tmp_name']);
            }
        }

        $attachment_id = media_handle_upload('orden_compra', 0);
        if (!is_wp_error($attachment_id)) {
            $oc_url = wp_get_attachment_url($attachment_id);
            update_post_meta($order_id, '_orden_compra_cliente', $oc_url);
        } else {
            wp_send_json_error(['message' => 'Error al guardar la orden de compra.']);
        }
    }

    // Marcar pedido como entregado
    $order->update_status('completed');

    // También podrías actualizar cuenta por cobrar aquí si aplica
    global $wpdb;
    $wpdb->update("{$wpdb->prefix}cuentas_cobrar", [
        'orden_compra_url' => $oc_url
    ], ['order_id' => $order_id]);

    wp_send_json_success(['message' => 'Pedido recibido con éxito.']);
}

add_action('wp_enqueue_scripts', function () {
    if (!is_account_page()) return;

    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
});

// ✅ Endpoint para obtener los productos de un pedido
add_action('wp_ajax_ajax_obtener_productos_orden', 'ajax_obtener_productos_orden');
function ajax_obtener_productos_orden() {
    $order_id = intval($_POST['order_id'] ?? 0);
    $current_user = get_current_user_id();

    if (!$order_id) {
        wp_send_json_error(['message' => 'ID de pedido no válido']);
    }

    $order = wc_get_order($order_id);

    if (!$order || $order->get_user_id() !== $current_user) {
        wp_send_json_error(['message' => 'No autorizado o pedido inválido']);
    }

    $productos = [];

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product) {
            $productos[] = [
                'product_id' => $product->get_id(),
                'nombre' => $product->get_name(),
                'sku' => $product->get_sku()
            ];
        }
    }

    wp_send_json_success($productos);
}

// ✅ Endpoint para registrar la solicitud de devolución
add_action('wp_ajax_ajax_solicitar_devolucion_cliente', function () {
    global $wpdb;

    $cliente_id   = get_current_user_id();
    $producto_id  = intval($_POST['producto_id'] ?? 0);
    $order_id     = intval($_POST['order_id'] ?? 0);
    $motivo       = sanitize_text_field($_POST['motivo'] ?? '');

    if (!$cliente_id || !$producto_id || !$order_id || empty($motivo)) {
        wp_send_json_error(['message' => 'Faltan datos obligatorios.']);
    }

    // Verificar si ya existe una devolución para este producto + orden + cliente
    $existe = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}devoluciones_autopartes
         WHERE producto_id = %d AND cliente_id = %d AND order_id = %d",
        $producto_id, $cliente_id, $order_id
    ));

    if ($existe > 0) {
        wp_send_json_error(['message' => 'Ya existe una solicitud de devolución para este producto.']);
    }

    // Obtener venta asociada si existe
    $venta_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}ventas_autopartes WHERE woo_order_id = %d LIMIT 1",
        $order_id
    ));

    if (!$venta_id) {
        $venta_id = null; // WooCommerce sin venta POS
    }

    // Subir evidencia si se envía
    $evidencias = [];
    if (!empty($_FILES['evidencia']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file = media_handle_upload('evidencia', 0);
        if (!is_wp_error($file)) {
            $evidencias[] = wp_get_attachment_url($file);
        }
    }

    $evidencia_serializada = maybe_serialize($evidencias);

    // ✅ Insertar devolución (agregamos `order_id`)
    $insertado = $wpdb->insert("{$wpdb->prefix}devoluciones_autopartes", [
        'venta_id'        => $venta_id,
        'order_id'        => $order_id,
        'producto_id'     => $producto_id,
        'cliente_id'      => $cliente_id,
        'motivo_cliente'  => $motivo,
        'evidencia_urls'  => $evidencia_serializada,
        'estado_revision' => 'pendiente',
        'fecha_solicitud' => current_time('mysql')
    ]);

    if (!$insertado) {
        error_log('❌ Error al registrar devolución: ' . $wpdb->last_error);
        wp_send_json_error(['message' => 'Error al registrar la devolución.']);
    }

    wp_send_json_success(['message' => 'Solicitud de devolución registrada.']);
});

add_action('wp_enqueue_scripts', function() {
    if (is_account_page()) {
        wp_enqueue_style(
            'tailwindcdn',
            'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
            [],
            null
        );
    }
});

add_action('wp_ajax_obtener_datos_pedido', function () {
    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    if (!$pedido_id) {
        wp_send_json_error(['message' => 'ID inválido']);
    }

    $order = wc_get_order($pedido_id);
    if (!$order) {
        wp_send_json_error(['message' => 'Pedido no encontrado']);
    }

    wp_send_json_success([
        'metodo' => $order->get_payment_method()
    ]);
});

add_action('wp_footer', function () {
    if (!is_account_page()) return;

    ?>
    <script>
        // Define ajaxurl en el frontend
        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
    </script>
    <?php
}, 5);
add_action('wp_footer', function () {
    if (!is_account_page()) return;

    $user_id = get_current_user_id();
    $oc_obligatoria = get_user_meta($user_id, 'oc_obligatoria', true);
    ?>
    <script>
        jQuery(document).ready(function ($) {
            
            $('.woocommerce-button.devolver').each(function () {
                const $btn = $(this);
                const href = $btn.siblings('a.view').attr('href') || '';
                const match = href.match(/view-order\/(\d+)/);
                if (!match) return;

                const orderId = match[1];
                $btn.attr('data-order', orderId);

                // Verificar si ya hay devolución resuelta
                $.post(ajaxurl, {
                    action: 'ajax_verificar_devolucion_completa',
                    order_id: orderId
                }, function (res) {
                    if (res.success && res.data?.ya_devuelto) {
                        // ✅ Reemplazar el botón
                        $btn.replaceWith(`
                            <button class="woocommerce-button ver-devolucion bg-yellow-500 text-white px-4 py-2 rounded"
                                    data-order="${orderId}" data-detalles='${JSON.stringify(res.data.detalles)}'>
                                Ver devolución
                            </button>
                        `);
                    }
                });
            });

            const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            const ocObligatoria = <?php echo json_encode(get_user_meta(get_current_user_id(), 'oc_obligatoria', true) === '1'); ?>;

            // ✅ BOTÓN "RECIBIR"
            $('.woocommerce-button.recibir').each(function () {
                const $btn = $(this);
                const href = $btn.siblings('a.view').attr('href') || '';
                const match = href.match(/view-order\/(\d+)/);
                if (!match) return;

                const orderId = match[1];
                $btn.attr('data-order', orderId);

                $.post(ajaxUrl, {
                    action: 'obtener_metodo_pago_pedido',
                    order_id: orderId
                }, function (resp) {
                    if (resp.success && resp.data.metodo) {
                        $btn.attr('data-metodo', resp.data.metodo);
                        $btn.attr('data-loaded', 'true');
                    }
                });
            });


            $('.woocommerce-button.subir_oc').each(function () {
                const $btn = $(this);
                const href = $btn.siblings('a.view').attr('href') || '';
                const match = href.match(/view-order\/(\d+)/);
                if (!match) return;

                const orderId = match[1];
                $btn.attr('data-order', orderId);

                $btn.on('click', function (e) {
                    e.preventDefault();

                    Swal.fire({
                        title: 'Subir Orden de Compra',
                        html: `
                        <div class="text-left text-sm">
                            <label class="block font-medium mb-1">Archivo (PDF o imagen):</label>
                            <input type="file" id="oc_file_envio" accept="application/pdf,image/*" class="swal2-file" />
                        </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Subir',
                        preConfirm: () => {
                            const file = document.getElementById('oc_file_envio')?.files[0];
                            if (!file) {
                                Swal.showValidationMessage('Debes seleccionar un archivo.');
                                return false;
                            }
                            return file;
                        }
                    }).then(result => {
                        if (!result.isConfirmed || !result.value) return;

                        const formData = new FormData();
                        formData.append('action', 'ajax_subir_oc_pedido');
                        formData.append('pedido_id', orderId);
                        formData.append('archivo', result.value);

                        Swal.fire({ title: 'Subiendo...', didOpen: () => Swal.showLoading() });

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(resp => {
                            if (resp.success) {
                                Swal.fire('✅ OC subida', 'Gracias, en breve te enviaremos el producto.', 'success')
                                    .then(() => location.reload());
                            } else {
                                Swal.fire('Error', resp.data?.message || 'No se pudo guardar.', 'error');
                            }
                        })
                        .catch(() => Swal.fire('Error', 'Error inesperado.', 'error'));
                    });
                });
            });


            $(document).on('click', '.btnSubirOC', function () {
                const pedidoId = $(this).data('id');

                Swal.fire({
                    title: 'Subir Orden de Compra',
                    html: `
                    <div class="text-left text-sm">
                        <label class="block font-medium mb-1">Archivo (PDF o imagen):</label>
                        <input type="file" id="oc_file" accept="application/pdf,image/*" class="swal2-file" />
                    </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Subir',
                    preConfirm: () => {
                    const file = document.getElementById('oc_file')?.files[0];
                    if (!file) {
                        Swal.showValidationMessage('Debes seleccionar un archivo.');
                        return false;
                    }
                    return file;
                    }
                }).then(result => {
                    if (!result.isConfirmed || !result.value) return;

                    const formData = new FormData();
                    formData.append('action', 'ajax_subir_oc_pedido');
                    formData.append('pedido_id', pedidoId);
                    formData.append('archivo', result.value);

                    Swal.fire({ title: 'Subiendo...', didOpen: () => Swal.showLoading() });

                    fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.success) {
                        Swal.fire('✅ OC subida', 'Ya puedes finalizar el armado.', 'success').then(() => location.reload());
                        } else {
                        Swal.fire('Error', resp.data?.message || 'No se pudo guardar.', 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Error inesperado.', 'error'));
                });
                });


            $(document).on('click', '.woocommerce-button.ver-devolucion', function () {
                const detalles = $(this).data('detalles') || {};
                const html = `
                    <div class="text-left text-sm space-y-2">
                        <p><strong>Producto:</strong> ${detalles.producto}</p>
                        <p><strong>Motivo:</strong> ${detalles.motivo}</p>
                        <p><strong>Notas revisión:</strong> ${detalles.notas || '—'}</p>
                        <p><strong>Resolución:</strong> ${detalles.resolucion}</p>
                        ${detalles.evidencias?.length > 0 ? `
                            <div>
                                <p><strong>Evidencias:</strong></p>
                                ${detalles.evidencias.map(url => `<a href="${url}" target="_blank" class="text-blue-600 underline block">${url}</a>`).join('')}
                            </div>` : ''}
                    </div>
                `;

                Swal.fire({
                    title: 'Detalle de Devolución',
                    html,
                    confirmButtonText: 'Cerrar'
                });
            });

            // Botones "devolver"
            $('.woocommerce-button.devolver').each(function () {
                const $btn = $(this);
                const href = $btn.siblings('a.view').attr('href') || '';
                const match = href.match(/view-order\/(\d+)/);
                if (!match) return;

                const orderId = match[1];
                $btn.attr('data-order', orderId);

                // Verificar si ya existe una devolución procesada para ese pedido
                $.post(ajaxurl, {
                    action: 'ajax_verificar_devolucion_completa',
                    order_id: orderId
                }, function (res) {
                    if (res.success && res.data?.ya_devuelto) {
                        // Reemplazar botón por "Ver Devolución"
                        $btn.replaceWith(`
                            <button class="woocommerce-button ver-devolucion bg-yellow-500 text-white px-4 py-2 rounded"
                                    data-order="${orderId}" data-detalles='${JSON.stringify(res.data.detalles)}'>
                                Ver devolución
                            </button>
                        `);
                    }
                });
            });

            $(document).on('click', '.woocommerce-button.recibir', function (e) {
                e.preventDefault();
                const $btn = $(this);
                const orderId = $btn.data('order');
                let codigo = $btn.data('codigo');

                if (!codigo || codigo === '...') {
                    // Fallback: Consultar vía AJAX
                    $.post(ajaxurl, {
                        action: 'ajax_obtener_codigo_recepcion',
                        order_id: orderId
                    }, function (res) {
                        if (res.success && res.data?.codigo) {
                            mostrarPopupCodigo(orderId, res.data.codigo);
                        } else {
                            Swal.fire('Error', 'No se pudo obtener el código de confirmación.', 'error');
                        }
                    });
                } else {
                    // Ya estaba disponible en el botón
                    mostrarPopupCodigo(orderId, codigo);
                }
            });

            function mostrarPopupCodigo(orderId, codigo) {
                Swal.fire({
                    title: 'Código de Confirmación',
                    html: `
                        <p class="text-sm mb-2">Para confirmar la recepción del pedido <strong>#${orderId}</strong>, el personal del almacén debe ingresar el siguiente código:</p>
                        <p class="text-lg font-bold bg-blue-100 text-blue-700 px-3 py-2 rounded inline-block tracking-widest">${codigo}</p>
                        <p class="text-xs text-gray-500 mt-3">Este código será validado internamente en el punto de entrega.</p>
                    `,
                    confirmButtonText: 'Entendido'
                });
            }

            // ✅ BOTÓN "DEVOLVER"
            $(document).on('click', '.woocommerce-button.devolver', function (e) {
                e.preventDefault();
                const $btn = $(this);
                const orderId = $btn.data('order');

                $.post(ajaxurl, {
                    action: 'ajax_obtener_productos_orden',
                    order_id: orderId
                }, function (res) {
                    if (!res.success || !res.data.length) return;

                    const productosFiltrados = [];
                    let pendientes = res.data.length;

                    res.data.forEach(p => {
                        $.post(ajaxurl, {
                            action: 'ajax_verificar_devolucion_existente',
                            order_id: orderId,
                            producto_id: p.product_id
                        }, function (ver) {
                            if (ver.success && !ver.data.ya_existe) {
                                productosFiltrados.push(p);
                            }

                            pendientes--;
                            if (pendientes === 0) {
                                if (productosFiltrados.length === 0) {
                                    Swal.fire('Ya existe una devolución registrada para todos los productos de este pedido.', '', 'info');
                                    return;
                                }

                                // ✅ Mostrar el popup
                                mostrarPopupDevolucion(orderId, productosFiltrados);
                            }
                        });
                    });
                });
            });

            function mostrarPopupDevolucion(orderId, productos) {
                const $selectHTML = productos.map(p =>
                    `<option value="${p.product_id}">${p.nombre}</option>`
                ).join('');

                Swal.fire({
                    title: 'Solicitar Devolución',
                    html: `
                        <div class="text-left text-sm space-y-4" style="font-family: sans-serif;">
                            <div>
                                <label class="block font-medium mb-1 text-gray-700">Producto a devolver</label>
                                <select id="productoDev" class="swal2-select w-full border border-gray-300 rounded m-0 p-0">
                                    ${$selectHTML}
                                </select>
                            </div>
                            <div>
                                <label class="block font-medium mb-1 text-gray-700">Motivo</label>
                                <textarea id="motivoDev" class="swal2-textarea w-full text-sm m-0 p-0" rows="3" placeholder="Describe el motivo..."></textarea>
                            </div>
                            <div>
                                <label class="block font-medium mb-1 text-gray-700">📎 Evidencia</label>
                                <input type="file" id="evidenciaDev" accept="image/*,application/pdf" class="w-full text-sm">
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Enviar Solicitud',
                    cancelButtonText: 'Cancelar',
                    customClass: {
                        confirmButton: 'bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700',
                        cancelButton: 'bg-gray-200 text-gray-800 px-4 py-2 rounded ml-2 hover:bg-gray-300'
                    },
                    buttonsStyling: false,
                    preConfirm: () => {
                        const motivo = $('#motivoDev').val().trim();
                        const archivo = $('#evidenciaDev')[0].files[0];
                        const producto_id = $('#productoDev').val();

                        if (!motivo || !producto_id) {
                            Swal.showValidationMessage('Debes seleccionar un producto y escribir el motivo.');
                            return false;
                        }

                        const formData = new FormData();
                        formData.append('action', 'ajax_solicitar_devolucion_cliente');
                        formData.append('order_id', orderId);
                        formData.append('producto_id', producto_id);
                        formData.append('motivo', motivo);
                        if (archivo) formData.append('evidencia', archivo);

                        return fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.json())
                        .then(resp => {
                            if (!resp.success) throw new Error(resp.data?.message || 'Error en la solicitud');
                            return resp;
                        })
                        .catch(e => {
                            Swal.showValidationMessage(e.message);
                        });
                    }
                }).then(result => {
                    if (result.isConfirmed) {
                        Swal.fire('Solicitud enviada', 'Tu devolución ha sido registrada.', 'success');
                    }
                });
            }

        });
        </script>

    <?php
});

add_action('wp_ajax_ajax_subir_oc_pedido', function () {
    global $wpdb;

    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    $usuario_id = get_current_user_id();

    if (!$pedido_id || empty($_FILES['archivo'])) {
        wp_send_json_error(['message' => 'Datos incompletos.']);
    }

    $order = wc_get_order($pedido_id);
    if (!$order || $order->get_user_id() !== $usuario_id) {
        wp_send_json_error(['message' => 'No autorizado.']);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    $uploaded = media_handle_upload('archivo', 0);

    if (is_wp_error($uploaded)) {
        wp_send_json_error(['message' => 'Error al subir archivo.']);
    }

    $url = wp_get_attachment_url($uploaded);
    update_post_meta($pedido_id, '_orden_compra_url', esc_url_raw($url));

    //  ACTUALIZAR URL EN TABLA CUENTAS POR COBRAR
    $tabla_cxc = $wpdb->prefix . 'cuentas_cobrar';
    $actualizado = $wpdb->update(
        $tabla_cxc,
        ['orden_compra_url' => esc_url_raw($url)],
        ['order_id' => $pedido_id],
        ['%s'],
        ['%d']
    );

    wp_send_json_success([
        'message' => 'Orden de compra guardada.',
        'url' => $url,
        'cxc_actualizada' => $actualizado !== false
    ]);
});

add_action('wp_ajax_ajax_verificar_devolucion_completa', function () {
    global $wpdb;

    $order_id = intval($_POST['order_id'] ?? 0);
    $cliente_id = get_current_user_id();

    if (!$order_id || !$cliente_id) {
        wp_send_json_error(['message' => 'Datos incompletos']);
    }

    // Buscar devolución resuelta por order_id
    $devolucion = $wpdb->get_row($wpdb->prepare(
        "SELECT d.*, p.post_title AS producto_nombre
         FROM {$wpdb->prefix}devoluciones_autopartes d
         INNER JOIN {$wpdb->prefix}posts p ON d.producto_id = p.ID
         WHERE d.order_id = %d AND d.cliente_id = %d AND d.estado_revision = 'resuelto'
         LIMIT 1",
        $order_id, $cliente_id
    ));

    if ($devolucion) {
        $evidencias = maybe_unserialize($devolucion->evidencia_urls) ?: [];

        wp_send_json_success([
            'ya_devuelto' => true,
            'detalles' => [
                'producto'   => $devolucion->producto_nombre,
                'motivo'     => $devolucion->motivo_cliente,
                'resolucion' => $devolucion->resolucion_final,
                'notas'      => $devolucion->notas_revision,
                'evidencias' => $evidencias
            ]
        ]);
    }

    wp_send_json_success(['ya_devuelto' => false]);
});


add_action('wp_ajax_obtener_metodo_pago_pedido', function () {
    if (!current_user_can('read')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    if (!$order_id) {
        wp_send_json_error(['message' => 'ID de pedido inválido']);
    }

    $order = wc_get_order($order_id);
    if (!$order || $order->get_user_id() !== get_current_user_id()) {
        wp_send_json_error(['message' => 'Pedido no encontrado o no permitido']);
    }

    $metodo = $order->get_payment_method();
    wp_send_json_success(['metodo' => $metodo]);
});

add_action('enqueue_block_assets', function() {
    if (is_checkout()) {
        wp_enqueue_script(
            'wc-credito-cliente-blocks-js',
            plugin_dir_url(__FILE__) . 'assets/js/wc-credito-cliente-blocks.js', // Ajusta la ruta si es diferente
            ['wc-blocks-registry', 'wp-element', 'wp-i18n'],
            '1.0.0',
            true
        );
    }
});

// Registrar el método de pago "Pago a Crédito" en WooCommerce Blocks
// Debug: Registro del método de pago en WooCommerce Blocks
add_action('woocommerce_blocks_payment_method_type_registration', function($payment_method_registry) {

    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        error_log('⚠️ No existe AbstractPaymentMethodType. No se puede registrar Blocks Payment.');
        return;
    }

    if (!class_exists('WC_Gateway_Credito_Cliente_Blocks')) {
        class WC_Gateway_Credito_Cliente_Blocks extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
            protected $name = 'credito_cliente';
            protected $settings = [];
            protected $supports = ['products', 'cart', 'checkout'];

            public function initialize() {
                error_log('🧩 initialize() de WC_Gateway_Credito_Cliente_Blocks ejecutado');
                $this->settings = get_option('woocommerce_credito_cliente_settings', []);
            }
        }
    }

    $payment_method_registry->register(new WC_Gateway_Credito_Cliente_Blocks());
});

// Debug: Cuando carga el Checkout
add_action('template_redirect', function() {
    if (is_checkout()) {
        error_log('🛒 Checkout cargado - Cliente ID: ' . get_current_user_id());
    }
});

add_action('enqueue_block_assets', function() {
    if (is_checkout()) {
        wp_enqueue_script(
            'wc-credito-cliente-blocks-js',
            plugin_dir_url(__FILE__) . 'assets/js/wc-credito-cliente-blocks.js',
            ['wc-blocks-registry', 'wp-element', 'wp-i18n'],
            '1.0.0',
            true
        );

        // Enviar oc_obligatoria al script JS
        $user_id = get_current_user_id();
        $oc_obligatoria = get_user_meta($user_id, 'oc_obligatoria', true) ?: '0';
        wp_localize_script('wc-credito-cliente-blocks-js', 'wp_oc_obligatoria', $oc_obligatoria);
    }
});
// Listar ubicaciones
add_action('wp_ajax_obtener_lista_ubicaciones', function () {
    global $wpdb;

    $tabla = $wpdb->prefix . 'ubicaciones_autopartes';
    $ubicaciones = $wpdb->get_results("SELECT id, nombre, descripcion FROM $tabla");

    wp_send_json_success($ubicaciones);
});

// Mostrar historial de productos asignados
add_action('wp_ajax_historial_productos_asignados', function () {
    global $wpdb;

    $ubicacion = sanitize_text_field($_GET['ubicacion'] ?? '');

    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [],
    ];

    // Filtrar productos sin ubicación
    if ($ubicacion === '__sin_ubicacion__') {
        $args['meta_query'][] = [
            'key'     => '_ubicacion_fisica',
            'compare' => 'NOT EXISTS'
        ];
    }
    // Filtrar por una ubicación específica (por nombre o ID)
    elseif (!empty($ubicacion)) {
        // Buscar el ID de la ubicación por su nombre
        $ubicacion_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ubicaciones_autopartes WHERE nombre = %s LIMIT 1",
            $ubicacion
        ));

        $meta_query = [
            'relation' => 'OR',
            [
                'key'     => '_ubicacion_fisica',
                'value'   => $ubicacion,
                'compare' => '='
            ]
        ];

        if ($ubicacion_id) {
            $meta_query[] = [
                'key'     => '_ubicacion_fisica',
                'value'   => strval($ubicacion_id),
                'compare' => '='
            ];
        }

        $args['meta_query'][] = $meta_query;
    }
    // Mostrar todos los productos que tengan alguna ubicación
    else {
        $args['meta_query'][] = [
            'key'     => '_ubicacion_fisica',
            'compare' => 'EXISTS'
        ];
    }

    $query = new WP_Query($args);
    $resultados = [];

    foreach ($query->posts as $post) {
        $product = wc_get_product($post->ID);

        // Mostrar aunque esté sin stock o vendido (si así lo deseas, elimina esta validación)
        if (!$product) {
            continue;
        }

        $ubicacion_meta = get_post_meta($product->get_id(), '_ubicacion_fisica', true);
        $ubicacion_real = $ubicacion_meta;

        // Si se guardó como ID, obtener el nombre correspondiente
        if (is_numeric($ubicacion_meta)) {
            $ubicacion_obj = $wpdb->get_row(
                $wpdb->prepare("SELECT nombre FROM {$wpdb->prefix}ubicaciones_autopartes WHERE id = %d", $ubicacion_meta)
            );
            if ($ubicacion_obj) {
                $ubicacion_real = $ubicacion_obj->nombre;
            }
        }

        $resultados[] = [
            'id'          => $product->get_id(),
            'sku'         => $product->get_sku(),
            'nombre'      => $product->get_name(),
            'descripcion' => $product->get_description(),
            'ubicacion'   => $ubicacion_real ?: '',
            'imagen'      => wp_get_attachment_image_url($product->get_image_id(), 'full'),
            'url'         => get_permalink($product->get_id()),
        ];
    }

    wp_send_json_success($resultados);
});


add_action('admin_enqueue_scripts', 'catalogo_autopartes_enqueue_scripts');
add_action('wp_ajax_ajax_enviar_solicitud_pieza', 'ajax_guardar_solicitud_pieza');
catalogo_autopartes_crear_tablas();
