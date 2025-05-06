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
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'tax_query'      => $tax_query
    ]);

    $productos = [];

    while ($query->have_posts()) {
        $query->the_post();
        $product = wc_get_product(get_the_ID());

        // ✅ Filtrar productos con stock > 0
        if ($product && $product->get_stock_quantity() > 0) {
            $productos[] = [
                'id'     => $product->get_id(),
                'nombre' => $product->get_name(),
                'sku'    => $product->get_sku(),
                'precio' => $product->get_price(),
                'stock'  => $product->get_stock_quantity(),
                'imagen' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                'link'   => get_permalink($product->get_id())
            ];
        }
    }

    wp_reset_postdata();

    if (empty($productos)) {
        wp_send_json_error('No se encontraron productos con stock disponible.');
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

    // 🛡️ Sanitizar y validar inputs
    $cliente_id     = intval($_POST['cliente_id']);
    $vendedor_id    = get_current_user_id();
    $metodo_pago    = sanitize_text_field($_POST['metodo_pago']);
    $productos      = json_decode(stripslashes($_POST['productos']), true);
    $canal_venta    = sanitize_text_field($_POST['canal'] ?? 'interno');
    $tipo_cliente   = sanitize_text_field($_POST['tipo_cliente'] ?? 'externo');
    $credito_usado  = floatval($_POST['credito_usado'] ?? 0);
    $oc_obligatoria = sanitize_text_field($_POST['oc_obligatoria'] ?? 'no');
    $estado_pago    = $metodo_pago === 'credito' ? 'pendiente' : 'pagado';

    if (!$cliente_id || empty($productos)) {
        wp_send_json_error(['message' => 'Faltan datos del cliente o productos']);
    }

    // 🎯 Subir Orden de Compra si aplica
    $oc_url = null;
    if (($oc_obligatoria === 'si' || $oc_obligatoria === '1') && !empty($_FILES['orden_compra']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('orden_compra', 0);
        if (!is_wp_error($attachment_id)) {
            $oc_url = wp_get_attachment_url($attachment_id);
            error_log("📎 Orden de compra subida exitosamente: $oc_url");
        } else {
            wp_send_json_error(['message' => 'Error al subir la orden de compra.']);
        }
    }

    // 🎯 Calcular total
    $total = array_reduce($productos, function($carry, $p) {
        return $carry + (floatval($p['precio']) * intval($p['cantidad']));
    }, 0);

    // 🧩 Insertar Venta en `ventas_autopartes`
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
    ], ['%d','%d','%s','%f','%s','%s','%f','%s','%s']);

    if ($venta_insertada === false) {
        error_log("❌ Error al insertar venta: " . $wpdb->last_error);
        wp_send_json_error(['message' => 'Error al registrar la venta']);
    }

    $venta_id = $wpdb->insert_id;

    // 🧩 Crear Pedido en WooCommerce
    $order = wc_create_order(['customer_id' => $cliente_id]);
    $cliente_userdata = get_userdata($cliente_id);

    if (!$cliente_userdata) {
        wp_send_json_error(['message' => 'No se pudo obtener los datos del cliente.']);
    }

    $order->set_billing_first_name(get_user_meta($cliente_id, 'nombre_completo', true));
    $order->set_billing_phone(get_user_meta($cliente_id, 'telefono', true));
    $order->set_billing_email($cliente_userdata->user_email);

    $order->set_billing_company(get_user_meta($cliente_id, 'razon_social', true));
    $order->set_billing_address_1(get_user_meta($cliente_id, 'fact_calle', true));
    $order->set_billing_address_2(get_user_meta($cliente_id, 'fact_colonia', true));
    $order->set_billing_city(get_user_meta($cliente_id, 'fact_municipio', true));
    $order->set_billing_state(get_user_meta($cliente_id, 'fact_estado', true));
    $order->set_billing_postcode(get_user_meta($cliente_id, 'fact_cp', true));
    $order->set_billing_country(get_user_meta($cliente_id, 'fact_pais', true));

    foreach ($productos as $p) {
        $product_id = wc_get_product_id_by_sku($p['sku']);
        if ($product_id) {
            $order->add_product(wc_get_product($product_id), intval($p['cantidad']));
        }
    }

    $metodo_wc = match($metodo_pago) {
        'efectivo'       => 'cod',
        'transferencia'  => 'bacs',
        'tarjeta'        => 'manual',
        'credito'        => 'manual',
        default          => 'manual',
    };

    $order->set_payment_method($metodo_wc);
    $order->set_payment_method_title(ucfirst($metodo_pago));
    $order->update_status('processing');

    update_post_meta($order->get_id(), '_venta_autoparte_id', $venta_id);
    update_post_meta($order->get_id(), '_canal_venta', $canal_venta);
    update_post_meta($order->get_id(), '_tipo_cliente', $tipo_cliente);
    update_post_meta($order->get_id(), '_metodo_pago', $metodo_pago);
    update_post_meta($order->get_id(), '_estado_pago', $estado_pago);
    update_post_meta($order->get_id(), '_oc_url', $oc_url);
    update_post_meta($order->get_id(), '_estado_logistico', 'pendiente_armado');
    update_post_meta($order->get_id(), '_estado_armado', 'pendiente_armado');

    $order->calculate_totals();
    $order->save();

    // 🎯 Relacionar venta y pedido
    $wpdb->update("{$wpdb->prefix}ventas_autopartes", [
        'woo_order_id' => $order->get_id()
    ], ['id' => $venta_id]);

    // 🎯 Insertar Cuenta por Cobrar si fue crédito
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

    // 🎯 Descontar stock y actualizar estado de solicitudes
    foreach ($productos as $p) {
        $producto_id = wc_get_product_id_by_sku($p['sku']);
        if ($producto_id) {
            $stock_actual = (int) get_post_meta($producto_id, '_stock', true);
            update_post_meta($producto_id, '_stock', max(0, $stock_actual - intval($p['cantidad'])));
        }

        if (!empty($p['solicitud_id'])) {
            $wpdb->update("{$wpdb->prefix}solicitudes_piezas", [
                'estado' => 'vendido'
            ], ['id' => intval($p['solicitud_id'])]);
        }
    }

    // 🚀 Respuesta de éxito
    wp_send_json_success([
        'venta_id' => $venta_id,
        'woo_order_id' => $order->get_id()
    ]);
}

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

    update_post_meta($pedido_id, '_estado_armado', $nuevo_estado);

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
        'status' => ['processing', 'completed'], // Incluye también pedidos completados
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

        $resultado[] = [
            'id' => $woo_order_id,
            'cliente' => $cliente_nombre ?: $cliente_email,
            'total' => number_format($pedido->get_total(), 2),
            'estado_woo' => wc_get_order_status_name($pedido->get_status()),
            'estado_armado' => $estado_armado,
            'fecha' => $pedido->get_date_created() ? $pedido->get_date_created()->format('Y-m-d H:i') : '',
            'ver_url' => admin_url("post.php?post={$woo_order_id}&action=edit"),
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

    $busqueda = sanitize_text_field($_POST['busqueda'] ?? '');
    $pagina = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina = 15;
    $offset = ($pagina - 1) * $por_pagina;

    $where = '1=1';
    $params = [];

    // Si es numérico, buscar por ID de venta
    if (is_numeric($busqueda)) {
        $where .= ' AND id = %d';
        $params[] = intval($busqueda);
    } elseif ($busqueda) {
        // Buscar cliente por nombre o correo
        $user_ids = get_users([
            'search' => '*' . esc_attr($busqueda) . '*',
            'search_columns' => ['display_name', 'user_email'],
            'fields' => ['ID']
        ]);

        if (!empty($user_ids)) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
            $where .= " AND cliente_id IN ($placeholders)";
            $params = array_merge($params, array_map('intval', $user_ids));
        } else {
            wp_send_json_success([
                'ventas' => [],
                'total_paginas' => 0
            ]);
        }
    }

    $total_query = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ventas_autopartes WHERE $where", ...$params);
    $total = $wpdb->get_var($total_query);

    $query = $wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ventas_autopartes 
        WHERE $where 
        ORDER BY fecha DESC 
        LIMIT %d OFFSET %d
    ", ...array_merge($params, [$por_pagina, $offset]));

    $ventas = $wpdb->get_results($query);
    $resultado = [];

    foreach ($ventas as $v) {
        $user = get_userdata($v->cliente_id);
        $resultado[] = [
            'id'     => $v->id,
            'cliente'=> $user ? $user->display_name : 'Cliente eliminado',
            'total'  => number_format($v->total, 2),
            'metodo' => $v->metodo_pago,
            'fecha'  => date('Y-m-d H:i', strtotime($v->fecha))
        ];
    }

    wp_send_json_success([
        'ventas' => $resultado,
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

    $nuevo_pagado = floatval($cuenta->monto_pagado) + $monto;
    $nuevo_saldo = max(0, floatval($cuenta->saldo_pendiente) - $monto);
    $nuevo_estado = $nuevo_saldo <= 0 ? 'pagado' : 'pendiente';

    // ✅ Manejo del archivo de comprobante
    $comprobante_url = '';
    if (!empty($_FILES['comprobante_pago']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $file_type = $_FILES['comprobante_pago']['type'];

        if (!in_array($file_type, $allowed_types)) {
            wp_send_json_error(['message' => 'Formato de comprobante no permitido. Usa JPG, PNG o PDF.']);
        }

        $upload = wp_handle_upload($_FILES['comprobante_pago'], ['test_form' => false]);
        if (isset($upload['url'])) {
            $comprobante_url = esc_url_raw($upload['url']);
        } else {
            wp_send_json_error(['message' => 'Error al subir el comprobante.']);
        }
    }
    if ($monto > floatval($cuenta->saldo_pendiente)) {
        wp_send_json_error(['message' => 'El monto pagado excede el saldo pendiente.']);
    }
    // ✅ Insertar pago
    $wpdb->insert("{$wpdb->prefix}pagos_cxc", [
        'cuenta_id'       => $cuenta_id,
        'monto_pagado'    => $monto,
        'metodo_pago'     => $metodo,
        'notas'           => $notas,
        'fecha_pago'      => current_time('mysql'),
        'comprobante_url' => $comprobante_url
    ]);

    // ✅ Actualizar la cuenta
    $wpdb->update("{$wpdb->prefix}cuentas_cobrar", [
        'monto_pagado'     => $nuevo_pagado,
        'saldo_pendiente'  => $nuevo_saldo,
        'estado'           => $nuevo_estado
    ], ['id' => $cuenta_id]);

    wp_send_json_success(['message' => 'Pago registrado correctamente.']);
}

add_action('wp_ajax_ajax_validar_credito_cliente', 'ajax_validar_credito_cliente');
function ajax_validar_credito_cliente() {
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    if (!$cliente_id) {
        wp_send_json_error('ID de cliente no proporcionado');
    }

    global $wpdb;

    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT user_email FROM {$wpdb->prefix}users WHERE ID = %d", $cliente_id
    ));

    if (!$cliente) {
        wp_send_json_error('Cliente no encontrado');
    }

    $estado_credito = get_user_meta($cliente_id, 'estado_credito', true) ?: 'inactivo';
    $credito_total = floatval(get_user_meta($cliente_id, 'credito_disponible', true) ?: 0);
    $oc_obligatoria = get_user_meta($cliente_id, 'oc_obligatoria', true) == '1';

    // ✅ Usar el campo correcto: saldo_pendiente
    $cuentas = $wpdb->get_results($wpdb->prepare(
        "SELECT saldo_pendiente FROM {$wpdb->prefix}cuentas_cobrar WHERE cliente_id = %d AND estado = 'pendiente'",
        $cliente_id
    ));

    $deuda_actual = 0;
    foreach ($cuentas as $c) {
        $monto = floatval(str_replace([',', '$'], '', $c->saldo_pendiente));
        $deuda_actual += $monto;
    }

    $credito_disponible = $credito_total - $deuda_actual;

    wp_send_json_success([
        'id' => $cliente_id,
        'nombre' => $cliente->user_email,
        'correo' => $cliente->user_email,
        'estado_credito' => $estado_credito,
        'credito_total' => $credito_total,
        'deuda_actual' => $deuda_actual,
        'credito_disponible' => $credito_disponible,
        'oc_obligatoria' => $oc_obligatoria
    ]);
}
// Endpoint AJAX: Obtener cuentas por cobrar
// Endpoint AJAX: Obtener cuentas por cobrar (WooCommerce + POS)
add_action('wp_ajax_ajax_obtener_cuentas_cobrar', function () {
    global $wpdb;

    // 🛡️ Sanitizar inputs recibidos
    $cliente_term = sanitize_text_field($_POST['cliente'] ?? '');
    $estado       = sanitize_text_field($_POST['estado'] ?? '');
    $desde        = sanitize_text_field($_POST['desde'] ?? '');
    $hasta        = sanitize_text_field($_POST['hasta'] ?? '');
    $pagina       = max(1, intval($_POST['pagina'] ?? 1));
    $por_pagina   = 10;
    $offset       = ($pagina - 1) * $por_pagina;

    $where = "1=1";
    $params = [];

    // 🔥 Aplicar filtros dinámicos
    if (!empty($estado)) {
        $where .= " AND estado = %s";
        $params[] = $estado;
    }
    if (!empty($desde)) {
        $where .= " AND DATE(fecha_creacion) >= %s";
        $params[] = $desde;
    }
    if (!empty($hasta)) {
        $where .= " AND DATE(fecha_creacion) <= %s";
        $params[] = $hasta;
    }

    // 🔥 Filtro por cliente
    if (!empty($cliente_term)) {
        $user_query = get_users([
            'search' => '*' . esc_attr($cliente_term) . '*',
            'search_columns' => ['user_email', 'display_name'],
            'fields' => ['ID']
        ]);
        $cliente_ids = array_map('intval', $user_query);

        if (empty($cliente_ids)) {
            wp_send_json_success([
                'cuentas' => [],
                'total_paginas' => 0
            ]);
        }

        $placeholders = implode(',', array_fill(0, count($cliente_ids), '%d'));
        $where .= " AND cliente_id IN ($placeholders)";
        $params = array_merge($params, $cliente_ids);
    }

    // 🔍 Consulta: obtener cuentas por cobrar
    $sql_total = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cuentas_cobrar WHERE $where", ...$params);
    $total_resultados = (int) $wpdb->get_var($sql_total);

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
        ...array_merge($params, [$por_pagina, $offset])
    );
    $cuentas = $wpdb->get_results($sql);

    // 📦 Formatear resultados
    $formateadas = [];
    foreach ($cuentas as $cuenta) {
        $user = get_userdata($cuenta->cliente_id);

        // ⚙️ Lógica para obtener un nombre válido
        $nombre_cliente = 'Cliente eliminado';
        if ($user) {
            $nombre_cliente = $user->display_name;
            if (empty($nombre_cliente)) {
                $nombre_cliente = trim($user->first_name . ' ' . $user->last_name);
                if (empty($nombre_cliente)) {
                    $nombre_cliente = $user->user_email;
                }
            }
        }

        $formateadas[] = [
            'id'                    => (int) $cuenta->id,
            'cliente'               => esc_html($nombre_cliente),
            'monto_total'           => number_format((float) $cuenta->monto_total, 2),
            'monto_pagado'          => number_format((float) $cuenta->monto_pagado, 2),
            'saldo_pendiente'       => number_format((float) $cuenta->saldo_pendiente, 2),
            'fecha_limite_pago'     => date('Y-m-d', strtotime($cuenta->fecha_limite_pago)),
            'estado'                => sanitize_text_field($cuenta->estado),
            'orden_compra_url'      => !empty($cuenta->orden_compra_url) ? esc_url($cuenta->orden_compra_url) : null,
            'comprobante_pago_url'  => !empty($cuenta->comprobante_pago_url) ? esc_url($cuenta->comprobante_pago_url) : null,
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

    // 1. Buscar productos por SKU (meta_value LIKE)
    $product_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s",
        '%' . $wpdb->esc_like($termino) . '%'
    ));

    if (!empty($product_ids)) {
        $sku_query = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'post__in'       => $product_ids,
            'posts_per_page' => 10,
        ]);

        while ($sku_query->have_posts()) {
            $sku_query->the_post();
            $product = wc_get_product(get_the_ID());

            // ✅ Filtrar productos con stock > 0
            if ($product && $product->get_stock_quantity() > 0) {
                $resultados[] = [
                    'id'            => $product->get_id(),
                    'sku'           => $product->get_sku(),
                    'nombre'        => $product->get_name(),
                    'precio'        => $product->get_price(),
                    'stock'         => $product->get_stock_quantity(),
                    'imagen'        => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                    'link'          => get_permalink($product->get_id()),
                    'solicitud_id'  => get_post_meta($product->get_id(), 'solicitud_id', true)
                ];
            }
        }
        wp_reset_postdata();
    }

    // 2. Si no hay resultados por SKU, buscar por nombre
    if (empty($resultados)) {
        $name_query = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            's'              => $termino,
        ]);

        while ($name_query->have_posts()) {
            $name_query->the_post();
            $product = wc_get_product(get_the_ID());

            // ✅ Filtrar productos con stock > 0
            if ($product && $product->get_stock_quantity() > 0) {
                $resultados[] = [
                    'id'            => $product->get_id(),
                    'sku'           => $product->get_sku(),
                    'nombre'        => $product->get_name(),
                    'precio'        => $product->get_price(),
                    'stock'         => $product->get_stock_quantity(),
                    'imagen'        => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
                    'link'          => get_permalink($product->get_id()),
                    'solicitud_id'  => get_post_meta($product->get_id(), 'solicitud_id', true)
                ];
            }
        }
        wp_reset_postdata();
    }

    if (empty($resultados)) {
        wp_send_json_error('No se encontraron productos con stock disponible');
    }

    wp_send_json_success($resultados);
}

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

    if ($monto_total <= 0) {
        wp_send_json_error(['message' => 'Monto inicial inválido']);
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
    $usuario = wp_get_current_user();

    wp_send_json_success([
        'message' => 'Caja abierta correctamente',
        'resumen' => [
            'fecha_apertura' => current_time('mysql'),
            'monto_inicial'  => $monto_total
        ],
        'usuario' => $usuario->display_name
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
            'monto_inicial'   => floatval($caja->monto_inicial),
            'monto_cierre'    => $total_contado,
            'fecha_apertura'  => $caja->fecha_apertura,
            'fecha_cierre'    => current_time('mysql'),
            'diferencia'      => $total_contado - floatval($caja->monto_inicial),
            'ventas_efectivo' => 0 // <- puedes consultar ventas reales si lo deseas
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

add_filter('woocommerce_login_redirect', 'redireccion_por_rol_personalizado', 10, 2);
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
    if (!is_admin() && is_checkout()) {
        $user_id = get_current_user_id();
        if ($user_id) {
            $estado_credito = get_user_meta($user_id, 'estado_credito', true);
            error_log('📦 Estado crédito en filter: ' . $estado_credito);

            if (strtolower($estado_credito) !== 'activo') {
                unset($gateways['credito_cliente']);
                error_log('❌ Crédito no activo, se eliminó el gateway');
            } else {
                error_log('✅ Crédito activo, se mantiene gateway');
            }
        }
    }
    return $gateways;
});

// Validar crédito disponible y estado antes de procesar el checkout
add_action('woocommerce_checkout_process', function() {
    if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'credito_cliente') {
        $user_id = get_current_user_id();
        if (!$user_id) return;

        global $wpdb;

        $estado_credito = get_user_meta($user_id, 'estado_credito', true);
        $credito_total = floatval(get_user_meta($user_id, 'credito_disponible', true) ?: 0);

        // ✅ Sumar todas las cuentas pendientes
        $cuentas = $wpdb->get_results($wpdb->prepare(
            "SELECT saldo_pendiente FROM {$wpdb->prefix}cuentas_cobrar WHERE cliente_id = %d AND estado = 'pendiente'",
            $user_id
        ));

        $deuda_actual = 0;
        foreach ($cuentas as $cuenta) {
            $deuda_actual += floatval(str_replace(['$', ','], '', $cuenta->saldo_pendiente));
        }

        $credito_disponible = $credito_total - $deuda_actual;
        $total_carrito = WC()->cart->get_total('edit'); // Asegura usar monto flotante

        if (strtolower($estado_credito) !== 'activo') {
            wc_add_notice('❌ Tu crédito no está activo.', 'error');
        } elseif ($credito_disponible < floatval(preg_replace('/[^\d.]/', '', $total_carrito))) {
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
    $estado_armado = get_post_meta($order->get_id(), '_estado_armado', true);
    $metodo_pago = $order->get_payment_method();

    // Validar que el pedido no esté completado o cancelado
    if ($order->has_status(['completed', 'cancelled', 'failed'])) {
        return $actions;
    }

    // Mostrar botón solo si el estado armado es "enviado"
    if ($estado_armado === 'enviado') {
        $actions['recibir'] = [
            'url'  => '#',
            'name' => '📥 Recibir',
            'custom_data' => [
                'order-id' => $order->get_id(),
                'metodo' => $metodo_pago
            ]
        ];
    }

    return $actions;
}, 20, 2);

// Reemplazar HTML del botón con el campo personalizado
add_filter('woocommerce_my_account_my_orders_actions_html', function($html, $action, $order) {
    return isset($action['custom_html']) ? $action['custom_html'] : $html;
}, 10, 3);


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

    $user_id = get_current_user_id();
    $oc_obligatoria = get_user_meta($user_id, 'oc_obligatoria', true);
    ?>
    <script>
    jQuery(document).ready(function ($) {
        // Agrega los atributos data-order y luego espera que estén listos
        $('.woocommerce-button.recibir').each(function () {
            const $btn = $(this);
            const href = $btn.siblings('a.view').attr('href') || '';
            const match = href.match(/view-order\/(\d+)/);
            if (!match) return;

            const orderId = match[1];
            $btn.attr('data-order', orderId);

            // Recuperar el método de pago
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'obtener_metodo_pago_pedido',
                order_id: orderId
            }, function (resp) {
                if (resp.success && resp.data.metodo) {
                    $btn.attr('data-metodo', resp.data.metodo);
                    $btn.attr('data-loaded', 'true');
                }
            });
        });

        $(document).on('click', '.woocommerce-button.recibir', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const orderId = $btn.data('order');

            const waitForData = () => {
                const metodo = $btn.data('metodo');
                const loaded = $btn.data('loaded') === true || $btn.data('loaded') === 'true';
                const ocObligatoria = <?php echo json_encode($oc_obligatoria === '1'); ?>;

                if (!loaded) {
                    setTimeout(waitForData, 100); // espera 100ms y vuelve a intentar
                    return;
                }

                let html = `<p class="mb-2">¿Deseas confirmar la recepción del pedido <strong>#${orderId}</strong>?</p>`;

                if (metodo === 'credito_cliente' && ocObligatoria) {
                    html += `
                    <div class="text-left">
                        <label class="block font-medium mb-1">Sube tu Orden de Compra (PDF o imagen):</label>
                        <input type="file" id="oc_file" class="swal2-file" accept="application/pdf,image/*" required />
                    </div>`;
                }

                Swal.fire({
                    title: 'Recibir Pedido',
                    html: html,
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar',
                    focusConfirm: false,
                    preConfirm: () => {
                        if (metodo === 'credito_cliente' && ocObligatoria) {
                            const fileInput = document.getElementById('oc_file');
                            const file = fileInput?.files?.[0];
                            if (!file) {
                                Swal.showValidationMessage('Debes subir una orden de compra.');
                                return false;
                            }
                            return { archivo: file };
                        }
                        return true;
                    }
                }).then(result => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('action', 'ajax_recibir_pedido_credito_cliente');
                    formData.append('order_id', orderId);
                    if (result.value?.archivo) {
                        formData.append('orden_compra', result.value.archivo);
                    }

                    Swal.fire({ title: 'Procesando...', didOpen: () => Swal.showLoading() });

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(resp => {
                        if (resp.success) {
                            Swal.fire('✅ Pedido recibido', resp.data.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('❌ Error', resp.data?.message || 'No se pudo procesar.', 'error');
                        }
                    })
                    .catch(() => {
                        Swal.fire('❌ Error', 'Error inesperado de red.', 'error');
                    });
                });
            };

            waitForData();
        });
    });
    </script>
    <?php
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

    // Sin ubicación
    if ($ubicacion === '__sin_ubicacion__') {
        $args['meta_query'][] = [
            'key'     => '_ubicacion_fisica',
            'compare' => 'NOT EXISTS'
        ];
    }
    // Ubicación específica
    elseif ($ubicacion) {
        $ubicacion_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ubicaciones_autopartes WHERE nombre = %s LIMIT 1",
            $ubicacion
        ));

        $args['meta_query'][] = [
            'relation' => 'OR',
            [
                'key'     => '_ubicacion_fisica',
                'value'   => $ubicacion,
                'compare' => '='
            ],
            [
                'key'     => '_ubicacion_fisica',
                'value'   => strval($ubicacion_id),
                'compare' => '='
            ]
        ];
    }
    // Todos los que tienen ubicación asignada
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

        // ✅ Validar stock positivo
        if (!$product || !$product->is_in_stock() || $product->get_stock_quantity() <= 0) {
            continue;
        }

        $ubicacion_meta = get_post_meta($product->get_id(), '_ubicacion_fisica', true);
        $ubicacion_real = $ubicacion_meta;

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
