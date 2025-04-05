<?php
if (!defined('ABSPATH')) exit;

/**
 * Registrar roles personalizados al activar el plugin.
 */
function catalogo_autopartes_registrar_roles() {
    add_role('vendedor_autopartes', 'Vendedor Autopartes', [
        'read' => true,
        'ver_punto_venta' => true,
        'ver_solicitudes' => true,
        'ver_qr' => true,
    ]);

    add_role('bodega_autopartes', 'Bodega Autopartes', [
        'read' => true,
        'ver_ubicaciones' => true,
        'ver_qr' => true,
    ]);
}
register_activation_hook(__FILE__, 'catalogo_autopartes_registrar_roles');

/**
 * Eliminar roles personalizados al desinstalar (opcional).
 */
function catalogo_autopartes_eliminar_roles() {
    remove_role('vendedor_autopartes');
    remove_role('bodega_autopartes');
}
// Puedes usar esta función dentro de uninstall.php si lo deseas.

/**
 * Verifica si el usuario tiene una capacidad específica.
 * @param string $capacidad
 * @return bool
 */
function usuario_tiene_capacidad($capacidad) {
    return current_user_can($capacidad);
}

/**
 * Verifica si el usuario tiene un rol específico.
 * @param string $rol
 * @return bool
 */
function usuario_tiene_rol($rol) {
    $user = wp_get_current_user();
    return in_array($rol, (array) $user->roles);
}
