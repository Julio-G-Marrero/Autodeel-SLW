<?php
$current_user = wp_get_current_user();
$page = $_GET['page'] ?? '';
?>

<aside style="width: 220px; background: #1e1e1e; color: white; padding: 20px;">
    <h2 style="font-size: 18px;">🔧 Autopartes</h2>
    <nav>
        <ul style="list-style: none; padding: 0;">

            <?php if (current_user_can('manage_options')): ?>
                <li><a href="?page=catalogo-autopartes" class="<?= $page == 'catalogo-autopartes' ? 'active' : '' ?>">📊 Dashboard</a></li>
            <?php endif; ?>

            <?php if (current_user_can('ver_captura_productos')): ?>
                <li><a href="?page=captura-productos" class="<?= $page == 'captura-productos' ? 'active' : '' ?>">📝 Captura de Productos</a></li>
            <?php endif; ?>

            <?php if (current_user_can('ver_qr')): ?>
                <li><a href="?page=impresion-qr" class="<?= $page == 'impresion-qr' ? 'active' : '' ?>">🔖 Imprimir QR</a></li>
            <?php endif; ?>

            <?php if (current_user_can('ver_solicitudes')): ?>
                <li><a href="?page=solicitudes-autopartes" class="<?= $page == 'solicitudes-autopartes' ? 'active' : '' ?>">📥 Solicitudes</a></li>
            <?php endif; ?>

            <?php if (current_user_can('ver_ubicaciones')): ?>
                <li><a href="?page=gestion-ubicaciones" class="<?= $page == 'gestion-ubicaciones' ? 'active' : '' ?>">📍 Ubicaciones</a></li>
            <?php endif; ?>

            <?php if (current_user_can('manage_options')): ?>
                <li><a href="?page=reportes" class="<?= $page == 'reportes' ? 'active' : '' ?>">📈 Reportes</a></li>
                <li><a href="?page=configuracion" class="<?= $page == 'configuracion' ? 'active' : '' ?>">⚙️ Configuración</a></li>
            <?php endif; ?>

        </ul>
    </nav>
</aside>
