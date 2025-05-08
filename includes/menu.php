<?php
if (!defined('ABSPATH')) {
    exit; // Evita acceso directo
}

// Registrar el menú del plugin en el administrador de WordPress
function catalogo_autopartes_menu() {
    add_menu_page(
        'Catálogo de Autopartes', // Título de la página
        'Catálogo Autopartes', // Nombre en el menú
        'manage_options',
        'catalogo-autopartes',
        'catalogo_autopartes_dashboard',
        'dashicons-car',
        25
    );

    // Submenús para cada funcionalidad
    add_submenu_page(
        'catalogo-autopartes',
        'Gestión de Catálogos',
        'Gestión de Catálogos',
        'manage_options',
        'gestion-catalogos',
        'catalogo_autopartes_gestion_catalogos'
    );

    add_submenu_page(
        'catalogo-autopartes',
        'Captura de Productos',
        'Captura de Productos',
        'ver_captura_productos',
        'captura-productos',
        'catalogo_autopartes_captura_productos'
    );

    add_submenu_page(
        null, // No aparece en el menú
        'Resumen de Pieza',
        'Resumen de Pieza',
        'ver_resumen_pieza',
        'resumen-pieza',
        'catalogo_autopartes_resumen_pieza'
    );

    add_submenu_page(
        'catalogo-autopartes',
        'Gestión de Ubicaciones',
        'Ubicaciones Físicas',
        'manage_options',
        'gestion-ubicaciones',
        'catalogo_autopartes_gestion_ubicaciones'
    );

    add_submenu_page(
        'catalogo-autopartes',
        'Solicitudes de Piezas',
        'Solicitudes',
        'ver_solicitudes',
        'solicitudes-autopartes',
        'catalogo_autopartes_solicitudes_autopartes'
    );
    
    add_submenu_page(
        'catalogo-autopartes',
        'Impresión de Códigos QR',
        'Imprimir QR',
        'impresion-qr',
        'impresion-qr',
        'catalogo_autopartes_impresion_qr'
    );
    
    // Nuevo submenu para Listas de Precios
    add_submenu_page(
        'catalogo-autopartes',
        'Listas de Precios',
        'Listas de Precios',
        'manage_options',
        'listas-precios',
        'catalogo_autopartes_listas_precios'
    );

    add_submenu_page(
        'catalogo-autopartes',
        'Asignar por QR',
        'Asignar por QR',
        'ver_asignar_ubicaciones_qr',
        'asignar-ubicaciones-qr',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/asignar_ubicacion_lote.php';
        }
    );
    add_submenu_page(
        'catalogo-autopartes',
        'Asignar Precio',
        'Asignar Precio',
        'asignar_precio_autopartes',
        'asignar-precios',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/asignar-precios.php';
        }
    );
    add_submenu_page(
        'catalogo-autopartes',
        'Ventas de Autopartes',
        'Ventas de Autopartes',
        'punto_de_venta',
        'ventas-autopartes',
        'catalogo_autopartes_ventas_autopartes'
    );
    add_submenu_page(
        'catalogo-autopartes',
        'Alta de Clientes',
        'Alta de Clientes',
        'alta_clientes_nuevos',
        'alta-clientes',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/alta-clientes.php';
        }
    );
    add_submenu_page(
        'catalogo-autopartes',
        'Clientes',
        'Clientes',
        'gestion_clientes',
        'listado-clientes',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/listado-clientes.php';
        }
    );
    add_submenu_page(
        'catalogo-autopartes',
        'Cuentas por Cobrar',
        'Cuentas por Cobrar',
        'gestion_cuentas_cobrar',
        'cuentas-por-cobrar',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/cuentas-por-cobrar.php';
        }
    );
    add_submenu_page(
        'catalogo-autopartes',
        'Gestion de Cajas',
        'Gestion de Cajas',
        'gestion_de_cajas',
        'gestion-cajas',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/apertura-caja.php';
        }
    );
    add_submenu_page(
        'catalogo-autopartes',
        'Resumen de Ventas',  
        'Resumen Ventas',      
        'ver_resumen_ventas',      
        'resumen-ventas', 
        function () {
            include plugin_dir_path(__FILE__) . '../pages/resumen-ventas.php';
        }
    );
    add_submenu_page(
        'catalogo-autopartes',                    
        'Resumen de Cortes',                      
        'Resumen de Cortes',                      
        'resumen_cortes',                         
        'resumen-cortes',                        
        function () {
            include plugin_dir_path(__FILE__) . '../pages/resumen-cortes.php';
        }
    );
    add_submenu_page(
        'catalogo-autopartes',
        'Gestión de Pedidos',
        'Gestión de Pedidos',
        'gestion_de_pedidos',
        'gestion-pedidos',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/gestion-pedidos.php';
        }
    );
    add_submenu_page(
        null, // no aparece en el menú
        'Detalle del Pedido',
        'Detalle del Pedido',
        'manage_woocommerce', // o tu rol personalizado
        'detalle-pedido',
        'catalogo_autopartes_detalle_pedido'
    );
    add_submenu_page(
        'catalogo-autopartes',
        'Armado de Pedidos',
        'Armado de Pedidos',
        'armado_de_pedidos',
        'gestion-armado',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/gestion-armado.php';
        }
    );
    add_submenu_page(
        null, // No aparece en el menú lateral
        'Armado de Pedido',
        'Armado de Pedido',
        'armado_de_pedido',
        'armado-pedido',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/armado-pedido.php';
        }
    );
}

add_action('admin_menu', 'catalogo_autopartes_menu');

function catalogo_autopartes_register_caps() {
    $caps = [
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
        'ver_asignar_ubicaciones_qr'
    ];

    $admin = get_role('administrator');
    foreach ($caps as $cap) {
        $admin->add_cap($cap); // ✅ El admin tendrá acceso a todo
    }
}
register_activation_hook(__FILE__, 'catalogo_autopartes_register_caps');

// Funciones para cargar cada página del plugin
function catalogo_autopartes_dashboard() {
    include_once plugin_dir_path(__FILE__) . '../pages/dashboard.php';
}

function catalogo_autopartes_impresion_qr() {
    include plugin_dir_path(__FILE__) . '../pages/impresion_qr.php';
}

function catalogo_autopartes_gestion_catalogos() {
    include_once plugin_dir_path(__FILE__) . '../pages/gestion-catalogos.php';
}

function catalogo_autopartes_captura_productos() {
    include_once plugin_dir_path(__FILE__) . '../pages/captura-productos.php';
}

function catalogo_autopartes_punto_venta() {
    include_once plugin_dir_path(__FILE__) . '../pages/punto-venta.php';
}

function catalogo_autopartes_reportes() {
    include_once plugin_dir_path(__FILE__) . '../pages/reportes.php';
}

function catalogo_autopartes_configuracion() {
    include_once plugin_dir_path(__FILE__) . '../pages/configuracion.php';
}

function catalogo_autopartes_resumen_pieza() {
    include_once plugin_dir_path(__FILE__) . '../pages/resumen-pieza.php';
}

function catalogo_autopartes_gestion_ubicaciones() {
    include_once plugin_dir_path(__FILE__) . '../pages/gestion-ubicaciones.php';
}

function catalogo_autopartes_solicitudes_autopartes() {
    include_once plugin_dir_path(__FILE__) . '../pages/solicitudes-autopartes.php';
}
function catalogo_autopartes_ventas_autopartes() {
    include_once plugin_dir_path(__FILE__) . '../pages/ventas-autopartes.php';
}

// Función para cargar la página de Listas de Precios
function catalogo_autopartes_listas_precios() {
    include_once plugin_dir_path(__FILE__) . '../pages/listas-precios.php';
}

function catalogo_autopartes_detalle_pedido() {
    $pedido_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$pedido_id) {
        echo '<div class="notice notice-error"><p>❌ Pedido no válido.</p></div>';
        return;
    }

    $order = wc_get_order($pedido_id);
    if (!$order) {
        echo '<div class="notice notice-error"><p>❌ No se encontró el pedido.</p></div>';
        return;
    }

    include plugin_dir_path(__FILE__) . '../pages/detalle-pedido.php';
}
?>