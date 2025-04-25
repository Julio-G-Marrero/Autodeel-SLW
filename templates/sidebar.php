<?php
$current_user = wp_get_current_user();
$page = $_GET['page'] ?? '';
?>
<?php if (!current_user_can('administrator')): ?>

<!-- ✅ Importar Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- 🔧 Script para abrir/cerrar el menú hamburguesa -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const toggle = document.getElementById("menu-toggle");
        const sidebar = document.getElementById("sidebar");

        toggle.addEventListener("click", function () {
            sidebar.classList.toggle("-translate-x-full");
        });
    });
</script>

<!-- 🔲 Botón hamburguesa (solo visible en móviles) -->
<div class="lg:hidden fixed top-4 left-4 z-[9999]">
    <button id="menu-toggle" class="bg-gray-800 text-white p-2 rounded shadow">
        <!-- Icono hamburguesa -->
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
             viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
</div>

<!-- 🧭 Sidebar -->
<aside id="sidebar" class="fixed top-0 left-0 h-screen w-64 bg-[#2d2f36] text-white shadow-lg z-50 
    transform transition-transform duration-300 ease-in-out
    -translate-x-full lg:translate-x-0 lg:static flex flex-col">

    <!-- Contenido scrollable -->
    <div class="flex-1 overflow-y-auto">
        <!-- Logo -->
        <div class="p-6 border-b border-gray-300 flex justify-center mt-10 lg:mt-8">
            <img src="https://dev-autodeel-slw.pantheonsite.io/wp-content/uploads/2025/04/LOGOSINFONDO-2.png" alt="Autodeel Logo" class="h-12 w-auto">
        </div>

        <!-- Navegación -->
        <nav class="mt-4 px-4">
            <ul class="flex flex-col gap-1">
                <?php if (current_user_can('ver_captura_productos')): ?>
                    <li>
                        <a href="?page=captura-productos" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'captura-productos' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Captura de Productos
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (current_user_can('ver_solicitudes')): ?>
                    <li>
                        <a href="?page=solicitudes-autopartes" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'solicitudes-autopartes' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Solicitudes
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (current_user_can('impresion-qr')): ?>
                    <li>
                        <a href="?page=impresion-qr" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'impresion-qr' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Imprimir Solicitudes
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (current_user_can('ver_asignar_ubicaciones_qr')): ?>
                    <li>
                        <a href="?page=asignar-ubicaciones-qr" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'asignar-ubicaciones-qr' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Asignar por QR
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (current_user_can('asignar_precio_autopartes')): ?>
                    <li>
                        <a href="?page=asignar-precios" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'asignar-precios' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Asignar Precios
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (current_user_can('punto_de_venta')): ?>
                    <li>
                        <a href="?page=ventas-autopartes" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'ventas-autopartes' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Punto de Venta
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (current_user_can('gestion_de_cajas')): ?>
                    <li>
                        <a href="?page=gestion-cajas" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'gestion-cajas' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Apertura de Cajas
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (current_user_can('ver_resumen_ventas')): ?>
                    <li>
                        <a href="?page=resumen-ventas" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'resumen-ventas' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Ventas
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (current_user_can('gestion_clientes')): ?>
                    <li>
                        <a href="?page=listado-clientes" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'listado-clientes' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Clientes
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (current_user_can('alta_clientes_nuevos')): ?>
                    <li>
                        <a href="?page=alta-clientes" class="block px-4 py-2 rounded font-medium text-sm transition 
                            <?= $page == 'alta-clientes' ? 'bg-gray-500 bg-opacity-30 font-bold text-black flex items-center justify-between py-1.5 px-4 rounded cursor-pointer' : 'text-gray-600 hover:bg-gray-700 hover:text-white border-2 border-solid ' ?>">
                            Alta clientes
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <!-- Cerrar sesión (siempre visible al final) -->
    <div class="p-4 shrink-0">
        <a href="<?= wp_logout_url() ?>" class="block w-full text-center px-4 py-2 rounded bg-red-600 hover:bg-red-700 transition font-medium">
            Cerrar sesión
        </a>
    </div>
</aside>

<!-- Ajuste del contenido principal solo en escritorio -->
<style>
    @media (min-width: 1024px) {
        #wpcontent {
            padding: 0;
            margin-left: 16rem !important;
        }
        html.wp-toolbar {
            padding: 0;
        }
    }
    @media (max-width: 450px) {
        aside#sidebar {
            height: 90%;
        }
    }
    aside#sidebar {
        position: fixed;
    }
    .main-content {
        margin-top: 60px;
    }
    aside#sidebar {
        background: #EFF1F3;
    }
</style>
<?php endif; ?>