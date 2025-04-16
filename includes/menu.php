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
        'catalogo-autopartes',
        'Punto de Venta',
        'Punto de Venta',
        'manage_options',
        'punto-venta',
        'catalogo_autopartes_punto_venta'
    );

    add_submenu_page(
        'catalogo-autopartes',
        'Gestión de Cajas',
        'Gestión de Cajas',
        'manage_options',
        'gestion-cajas',
        'catalogo_autopartes_gestion_cajas'
    );

    add_submenu_page(
        'catalogo-autopartes',
        'Reportes',
        'Reportes',
        'manage_options',
        'reportes',
        'catalogo_autopartes_reportes'
    );

    add_submenu_page(
        'catalogo-autopartes',
        'Configuración',
        'Configuración',
        'manage_options',
        'configuracion',
        'catalogo_autopartes_configuracion'
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
        'manage_options',
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
        'manage_options', // O un rol personalizado si ya lo tienes
        'asignar-precios',
        function () {
            include plugin_dir_path(__FILE__) . '../pages/asignar-precios.php';
        }
    );
}

add_action('admin_menu', 'catalogo_autopartes_menu');

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

function catalogo_autopartes_gestion_cajas() {
    include_once plugin_dir_path(__FILE__) . '../pages/gestion-cajas.php';
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

// Función para cargar la página de Listas de Precios
function catalogo_autopartes_listas_precios() {
    include_once plugin_dir_path(__FILE__) . '../pages/listas-precios.php';
}

?>