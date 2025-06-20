<?php
if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}

global $wpdb;
global $sql_catalogos, $sql_autopartes,$sql_ventas, $sql_compatibilidades, $sql_ubicaciones, $sql_solicitudes, $sql_precios, $sql_cxc,$sql_pagos, $sql_cajas, $sql_movimientos_caja, $sql_apertura_caja,$sql_negociaciones,$sql_devoluciones,$sql_reparaciones,$sql_rembolsos;
$charset_collate = $wpdb->get_charset_collate();

// Tabla de Precios por Catálogo (RADEC, etc.)
$sql_precios = "CREATE TABLE {$wpdb->prefix}precios_catalogos (
    id INT NOT NULL AUTO_INCREMENT,
    sku_base VARCHAR(100) NOT NULL,
    precio_proveedor DECIMAL(10,2) DEFAULT NULL,
    precio_publico DECIMAL(10,2) DEFAULT NULL,
    catalogo VARCHAR(100) NOT NULL,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY sku_catalogo (sku_base, catalogo)
) $charset_collate;";

// Tabla de Catálogos de Refaccionarias
$sql_catalogos = "CREATE TABLE {$wpdb->prefix}catalogos_refaccionarias (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

// Tabla de Autopartes sin llaves foráneas ni índices problemáticos
$sql_autopartes = "CREATE TABLE {$wpdb->prefix}autopartes (
    id INT NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    grupo VARCHAR(100) NOT NULL,
    clase VARCHAR(100) NOT NULL,
    sector VARCHAR(100) NOT NULL,
    peso DECIMAL(10,2) NOT NULL,
    unidad_peso VARCHAR(10) NOT NULL,
    volumen DECIMAL(10,2) NOT NULL,
    unidad_volumen VARCHAR(10) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    imagen_lista TEXT NULL,
    imagen_grande TEXT NULL,
    catalogo_id INT NOT NULL,
    PRIMARY KEY (id)
) $charset_collate;";

// Tabla de Compatibilidades sin llaves foráneas ni índices duplicados
$sql_compatibilidades = "CREATE TABLE {$wpdb->prefix}compatibilidades (
    id INT NOT NULL AUTO_INCREMENT,
    autoparte_id INT NOT NULL,
    catalogo_id INT NOT NULL,
    marca VARCHAR(100) NOT NULL,
    submarca VARCHAR(100) NOT NULL,
    rango VARCHAR(50) NOT NULL,
    PRIMARY KEY (id)
) $charset_collate;";

// Tabla de Ubicaciones
$sql_ubicaciones = "CREATE TABLE {$wpdb->prefix}ubicaciones_autopartes (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT NULL,
    codigo_qr VARCHAR(255) DEFAULT NULL,
    imagen_url TEXT DEFAULT NULL,
    PRIMARY KEY (id)
) $charset_collate;";

$sql_ventas = "CREATE TABLE {$wpdb->prefix}ventas_autopartes (
    id BIGINT NOT NULL AUTO_INCREMENT,
    cliente_id BIGINT NOT NULL,
    vendedor_id BIGINT NOT NULL,
    solicitud_id BIGINT DEFAULT NULL,
    woo_order_id BIGINT DEFAULT NULL,
    productos LONGTEXT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(50) NOT NULL,
    canal_venta VARCHAR(50) DEFAULT 'interno',
    tipo_cliente VARCHAR(50) DEFAULT 'externo',
    credito_usado DECIMAL(10,2) DEFAULT 0,
    oc_folio TEXT DEFAULT NULL,
    estado_pago ENUM('pagado', 'pendiente', 'vencido') DEFAULT 'pendiente',
    estado VARCHAR(20) DEFAULT 'completada',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cliente_id (cliente_id),
    KEY idx_vendedor_id (vendedor_id),
    KEY idx_woo_order_id (woo_order_id)
) $charset_collate;";
// Tabla de Solicitudes
$sql_solicitudes = "CREATE TABLE {$wpdb->prefix}solicitudes_piezas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    autoparte_id BIGINT UNSIGNED NOT NULL,
    ubicacion_id BIGINT UNSIGNED NOT NULL,
    estado_pieza VARCHAR(100) DEFAULT NULL,
    observaciones TEXT DEFAULT NULL,
    imagenes LONGTEXT DEFAULT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    estado VARCHAR(50) DEFAULT 'pendiente',
    fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

$sql_cxc = "CREATE TABLE {$wpdb->prefix}cuentas_cobrar (
    id BIGINT NOT NULL AUTO_INCREMENT,
    venta_id BIGINT DEFAULT NULL,
    order_id BIGINT DEFAULT NULL,
    cliente_id BIGINT NOT NULL,
    vendedor_id BIGINT DEFAULT 0,
    monto_total DECIMAL(10,2) NOT NULL,
    monto_pagado DECIMAL(10,2) DEFAULT 0,
    saldo_pendiente DECIMAL(10,2) NOT NULL,
    fecha_limite_pago DATE NOT NULL,
    estado ENUM('pendiente', 'pagado', 'vencido', 'bloqueado', 'anulada') DEFAULT 'pendiente',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    orden_compra_url TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY venta_order_unique (venta_id, order_id)
) {$charset_collate};";

$sql_pagos = "CREATE TABLE {$wpdb->prefix}pagos_cxc (
    id BIGINT NOT NULL AUTO_INCREMENT,
    cuenta_id BIGINT NOT NULL,
    caja_id BIGINT DEFAULT NULL,
    monto_pagado DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(50) DEFAULT 'efectivo',
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    tipo VARCHAR(50) DEFAULT 'manual',
    registrado_por BIGINT DEFAULT NULL,
    notas TEXT DEFAULT NULL,
    comprobante_url TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_cuenta_id (cuenta_id),
    KEY idx_caja_id (caja_id)
) $charset_collate;";

$sql_cajas = "CREATE TABLE {$wpdb->prefix}cajas (
    id BIGINT NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT NOT NULL,
    monto_apertura DECIMAL(10,2) NOT NULL,
    fecha_apertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    monto_cierre DECIMAL(10,2) DEFAULT NULL,
    fecha_cierre DATETIME DEFAULT NULL,
    observaciones_apertura TEXT DEFAULT NULL,
    observaciones_cierre TEXT DEFAULT NULL,
    estado ENUM('abierta', 'cerrada') DEFAULT 'abierta',
    PRIMARY KEY (id)
) $charset_collate;";

$sql_movimientos_caja = "CREATE TABLE {$wpdb->prefix}movimientos_caja (
    id BIGINT NOT NULL AUTO_INCREMENT,
    caja_id BIGINT NOT NULL,
    tipo ENUM('venta', 'pago', 'otro') DEFAULT 'venta',
    referencia_id BIGINT DEFAULT NULL,
    metodo_pago VARCHAR(50) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    referencia TEXT DEFAULT NULL,
    usuario_id BIGINT DEFAULT NULL, 
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY caja_idx (caja_id)
) $charset_collate;";

$sql_apertura_caja = "CREATE TABLE {$wpdb->prefix}aperturas_caja (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT NOT NULL,
    monto_inicial DECIMAL(10,2) NOT NULL,
    detalle_apertura LONGTEXT DEFAULT NULL,      
    detalle_cierre LONGTEXT DEFAULT NULL,       
    total_cierre DECIMAL(10,2) DEFAULT NULL,    
    diferencia DECIMAL(10,2) DEFAULT NULL,      
    fecha_apertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre DATETIME DEFAULT NULL,
    estado ENUM('abierta', 'cerrada') DEFAULT 'abierta',
    notas TEXT DEFAULT NULL,
    vobo_aprobado TINYINT(1) DEFAULT 0,
    vobo_aprobado_por BIGINT DEFAULT NULL,
    vobo_fecha_aprobacion DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_estado (estado)
) $charset_collate;";

$sql_negociaciones = "CREATE TABLE {$wpdb->prefix}negociaciones_precios (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    cliente_id BIGINT NOT NULL,
    producto_sku VARCHAR(100) NOT NULL,
    nombre_producto TEXT NOT NULL,
    precio_original DECIMAL(10,2) NOT NULL,
    precio_solicitado DECIMAL(10,2) NOT NULL,
    motivo TEXT NOT NULL,
    estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME DEFAULT NULL,
    aprobado_por BIGINT DEFAULT NULL,
    comentario_aprobacion TEXT DEFAULT NULL,
    vendido TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_user_id (user_id),
    INDEX idx_estado (estado),
    INDEX idx_vendido (vendido)
) $charset_collate;";

$sql_devoluciones = "CREATE TABLE {$wpdb->prefix}devoluciones_autopartes (
    id BIGINT NOT NULL AUTO_INCREMENT,
    venta_id BIGINT NULL,
    order_id BIGINT NULL,
    producto_id BIGINT NOT NULL,
    cliente_id BIGINT NOT NULL,
    motivo_cliente TEXT NOT NULL,
    evidencia_urls LONGTEXT DEFAULT NULL,
    estado_revision ENUM('pendiente', 'en_revision', 'resuelto', 'rechazado') DEFAULT 'pendiente',
    resolucion_final ENUM('reintegrado', 'reparacion', 'baja_definitiva') DEFAULT NULL,
    notas_revision TEXT DEFAULT NULL,
    usuario_revision_id BIGINT DEFAULT NULL,
    fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_revision DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_venta (venta_id),
    INDEX idx_order (order_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_estado (estado_revision)
) $charset_collate;";

$sql_reparaciones = "CREATE TABLE {$wpdb->prefix}reparaciones_autopartes (
    id BIGINT NOT NULL AUTO_INCREMENT,
    producto_id BIGINT NOT NULL,
    devolucion_id BIGINT DEFAULT NULL,
    estado ENUM('pendiente', 'reparado', 'descartado') DEFAULT 'pendiente',
    notas TEXT DEFAULT NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_reparado DATETIME DEFAULT NULL,
    reparado_por BIGINT DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_producto (producto_id),
    INDEX idx_estado_reparacion (estado)
) $charset_collate;";

$sql_rembolsos = "CREATE TABLE {$wpdb->prefix}solicitudes_rembolso (
    id BIGINT NOT NULL AUTO_INCREMENT,
    devolucion_id BIGINT NOT NULL,
    venta_id BIGINT DEFAULT NULL,
    order_id BIGINT DEFAULT NULL,
    monto DECIMAL(10,2) DEFAULT 0,
    metodo_pago VARCHAR(50) DEFAULT '',
    tipo_cliente VARCHAR(50) DEFAULT '',
    cliente_nombre VARCHAR(255) DEFAULT NULL, 
    tipo_rembolso VARCHAR(50) DEFAULT NULL,
    metodo_rembolso VARCHAR(50) DEFAULT NULL,
    estado VARCHAR(50) DEFAULT 'pendiente',
    motivo TEXT DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_resuelto DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_devolucion (devolucion_id),
    INDEX idx_estado_rembolso (estado)
) $charset_collate;";

// Función para crear todas las tablas
function catalogo_autopartes_crear_tablas() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    global $sql_catalogos, $sql_autopartes, $sql_compatibilidades, $sql_ubicaciones, $sql_solicitudes, $sql_precios, $sql_ventas, $sql_cxc, $sql_pagos, $sql_cajas,$sql_movimientos_caja, $sql_apertura_caja, $sql_negociaciones,$sql_devoluciones,$sql_reparaciones,$sql_rembolsos;

    dbDelta($sql_catalogos);
    dbDelta($sql_autopartes);
    dbDelta($sql_compatibilidades);
    dbDelta($sql_ubicaciones);
    dbDelta($sql_solicitudes);
    dbDelta($sql_precios);
    dbDelta($sql_ventas);
    dbDelta($sql_cxc);
    dbDelta($sql_pagos);
    dbDelta($sql_cajas);
    dbDelta($sql_movimientos_caja);
    dbDelta($sql_apertura_caja);
    dbDelta($sql_negociaciones);
    dbDelta($sql_devoluciones);
    dbDelta($sql_reparaciones);
    dbDelta($sql_rembolsos);
}

// Función para eliminar las tablas cuando se desinstala el plugin
function catalogo_autopartes_eliminar_tablas() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "solicitudes_piezas");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "ubicaciones_autopartes");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "compatibilidades");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "autopartes");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "catalogos_refaccionarias");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "precios_catalogos");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "ventas_autopartes");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "cuentas_cobrar");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "cajas");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "aperturas_caja");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "negociaciones_precios");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "devoluciones_autopartes");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "reparaciones_autopartes");
    $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "solicitudes_rembolso");
    
}

// Función para buscar coincidencias en los catálogos
function buscar_coincidencias_autoparte($descripcion, $marca, $modelo, $anio) {
    global $wpdb;

    $query = "SELECT a.id, a.codigo, a.descripcion, a.precio, a.imagen_lista, c.nombre as catalogo 
        FROM {$wpdb->prefix}autopartes a
        INNER JOIN {$wpdb->prefix}compatibilidades cmp ON a.id = cmp.autoparte_id
        INNER JOIN {$wpdb->prefix}catalogos_refaccionarias c ON a.catalogo_id = c.id
        WHERE a.descripcion LIKE %s
        AND cmp.marca = %s 
        AND cmp.submarca = %s
        AND cmp.rango LIKE %s";

    $resultados = $wpdb->get_results($wpdb->prepare(
        $query,
        "%" . $descripcion . "%",
        $marca,
        $modelo,
        "%" . $anio . "%"
    ));

    return $resultados;
}
