<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);

?>

<div class="max-w-6xl mx-auto p-6 bg-white rounded shadow mt-6">
    <h2 class="text-2xl font-bold mb-4">Clientes Registrados</h2>

    <!-- Filtros -->
    <div class="mb-4 flex flex-col md:flex-row gap-4">
        <input type="text" id="filtroBusqueda" class="border rounded px-3 py-2 w-full md:w-1/2" placeholder="Buscar por nombre o correo...">
        <select id="filtroEstado" class="border rounded px-3 py-2 w-full md:w-1/3">
            <option value="">Todos los Estados de Crédito</option>
            <option value="activo">Activo</option>
            <option value="suspendido">Suspendido</option>
        </select>
        <button id="btnBuscarClientes" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Buscar
        </button>
    </div>
    <!-- Tabla -->
    <div class="overflow-x-auto">
        <table class="w-full table-auto border border-gray-200 rounded">
            <thead class="bg-gray-100 text-sm">
                <tr>
                    <th class="px-2 py-2">Nombre</th>
                    <th class="px-2 py-2">Correo</th>
                    <th class="px-2 py-2">Rol</th>
                    <th class="px-2 py-2">Crédito</th>
                    <th class="px-2 py-2">Estado</th>
                    <th class="px-2 py-2">Canal</th>
                    <th class="px-2 py-2">Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaClientes"></tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function cargarClientes() {
        const busqueda = $('#filtroBusqueda').val().toLowerCase().trim();
        const estado = $('#filtroEstado').val();


        $('#tablaClientes').html('<tr><td colspan="7" class="text-center py-4 text-gray-500">Cargando...</td></tr>');
        $.post(ajaxurl, {
            action: 'ajax_listar_clientes',
            estado,
            busqueda
        }, function(res) {
            if (!res.success || res.data.length === 0) {
                $('#tablaClientes').html('<tr><td colspan="7" class="text-center py-4 text-red-500">No se encontraron clientes</td></tr>');
                return;
            }

            const rows = res.data.map(cliente => `
                <tr class="border-t text-sm text-gray-700">
                    <td class="px-2 py-2">${cliente.nombre}</td>
                    <td class="px-2 py-2">${cliente.correo}</td>
                    <td class="px-2 py-2">${cliente.rol}</td>
                    <td class="px-2 py-2">$${cliente.credito_disponible}</td>
                    <td class="px-2 py-2">${cliente.estado_credito}</td>
                    <td class="px-2 py-2">${cliente.canal_venta}</td>
                    <td class="px-2 py-2">
                        <button class="text-blue-600 editar-cliente" data-id="${cliente.id}">Editar</button>
                    </td>
                </tr>
            `).join('');
            $('#tablaClientes').html(rows);
            // Evento para abrir el modal de edición
            $('#tablaClientes').off('click', '.editar-cliente').on('click', '.editar-cliente', function () {
                const id = $(this).data('id');

                $.post(ajaxurl, {
                    action: 'ajax_obtener_cliente',
                    user_id: id
                }, function (res) {
                    if (!res.success) return alert('Error al obtener cliente');

                    const c = res.data;

                    Swal.fire({
                        title: 'Editar Cliente',
                        html: `
                        <input type="hidden" id="user_id" value="${c.id}">
                        <div style="display: flex; flex-direction: column; gap: 12px; text-align: left; font-size: 14px;">

                            <div>
                            <label>Nombre / Razón Social</label><br>
                            <input id="edit_nombre" class="swal2-input" style="width: 100%;" value="${c.nombre}">
                            </div>

                            <div style="display: flex; gap: 10px;">
                            <div style="flex: 1;">
                                <label>Tipo de Cliente</label><br>
                                <select id="edit_tipo" class="swal2-input" style="width: 100%;">
                                <option value="externo" ${c.tipo_cliente === 'externo' ? 'selected' : ''}>Externo</option>
                                <option value="interno" ${c.tipo_cliente === 'interno' ? 'selected' : ''}>Interno</option>
                                <option value="distribuidor" ${c.tipo_cliente === 'distribuidor' ? 'selected' : ''}>Distribuidor</option>
                                </select>
                            </div>
                            <div style="flex: 1;">
                                <label>Estado del Crédito</label><br>
                                <select id="edit_estado_credito" class="swal2-input" style="width: 100%;">
                                <option value="activo" ${c.estado_credito === 'activo' ? 'selected' : ''}>Activo</option>
                                <option value="suspendido" ${c.estado_credito === 'suspendido' ? 'selected' : ''}>Suspendido</option>
                                </select>
                            </div>
                            </div>

                            <div style="display: flex; gap: 10px;">
                            <div style="flex: 1;">
                                <label>Crédito Disponible</label><br>
                                <input id="edit_credito" type="number" min="0" class="swal2-input" style="width: 100%;" value="${c.credito_disponible}">
                            </div>
                            <div style="flex: 1;">
                                <label>Días de Crédito</label><br>
                                <input id="edit_dias" type="number" min="0" class="swal2-input" style="width: 100%;" value="${c.dias_credito}">
                            </div>
                            </div>
                            <div>
                                <label>Rol / Perfil de Descuento</label><br>
                                <select id="edit_rol" class="swal2-input" style="width: 100%;">
                                    <option value="customer" ${c.rol_slug === 'customer' ? 'selected' : ''}>Customer</option>
                                    <option value="wholesale_customer" ${c.rol_slug === 'wholesale_customer' ? 'selected' : ''}>Wholesale Customer</option>
                                    <option value="wholesale_talleres_crash" ${c.rol_slug === 'wholesale_talleres_crash' ? 'selected' : ''}>Talleres Crash</option>
                                </select>
                            </div>
                            <div>
                            <label>¿Orden de Compra Obligatoria?</label><br>
                            <select id="edit_oc" class="swal2-input" style="width: 100%;">
                                <option value="0" ${c.oc_obligatoria == 0 ? 'selected' : ''}>No</option>
                                <option value="1" ${c.oc_obligatoria == 1 ? 'selected' : ''}>Sí</option>
                            </select>
                            </div>

                        </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Guardar cambios',
                        preConfirm: () => {
                            return {
                                user_id: $('#user_id').val(),
                                nombre: $('#edit_nombre').val(),
                                tipo: $('#edit_tipo').val(),
                                estado_credito: $('#edit_estado_credito').val(),
                                credito: $('#edit_credito').val(),
                                dias: $('#edit_dias').val(),
                                canal: $('#edit_canal').val(),
                                oc: $('#edit_oc').val(),
                                rol: $('#edit_rol').val()
                            };
                        }
                    }).then(result => {
                        if (!result.isConfirmed) return;

                        $.post(ajaxurl, {
                            action: 'ajax_actualizar_cliente',
                            ...result.value
                        }, function (r) {
                            if (r.success) {
                                Swal.fire('¡Actualizado!', 'Cliente editado con éxito', 'success');
                                cargarClientes();
                            } else {
                                Swal.fire('Error', 'No se pudo guardar los cambios', 'error');
                            }
                        });
                    });
                });
            });
        });
    }

    $('#btnBuscarClientes').on('click', cargarClientes);
    cargarClientes();
    });
</script>
<style>
    input#edit_nombre {
        margin: 0;
    }
    input#edit_dias {
        margin: 0;
    }
    input#edit_credito {
        margin: 0;
    }
    input#edit_canal {
        margin: 0;
    }
</style>