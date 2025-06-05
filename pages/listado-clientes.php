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
<template id="templateFormularioCliente">
    <form id="formEditarCliente" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-left">
        <input type="hidden" id="edit_user_id" />

        <div>
            <label class="block font-medium mb-1">Nombre / Razón Social</label>
            <input type="text" id="edit_nombre" class="w-full border rounded px-3 py-2" />
        </div>

        <div>
            <label class="block font-medium mb-1">Correo Electrónico</label>
            <input type="email" id="edit_correo" class="w-full border rounded px-3 py-2" disabled />
        </div>

        <div>
            <label class="block font-medium mb-1">Teléfono</label>
            <input type="text" id="edit_telefono" class="w-full border rounded px-3 py-2" />
        </div>

        <div>
            <label class="block font-medium mb-1">Tipo de Cliente</label>
            <select id="edit_tipo" class="w-full border rounded px-3 py-2">
                <option value="externo">Externo</option>
                <option value="interno">Interno</option>
                <option value="distribuidor">Distribuidor</option>
            </select>
        </div>

        <div class="col-span-2">
            <label class="inline-flex items-center">
                <input type="checkbox" id="edit_checkCredito" class="mr-2" />
                <span class="font-medium">¿Dispone de Crédito?</span>
            </label>
        </div>

        <div id="edit_camposCredito" class="col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 hidden">
            <div>
                <label class="block font-medium mb-1">Estado del Crédito</label>
                <select id="edit_estado_credito" class="w-full border rounded px-3 py-2">
                    <option value="activo">Activo</option>
                    <option value="suspendido">Suspendido</option>
                </select>
            </div>
            <div>
                <label class="block font-medium mb-1">¿Orden de Compra Obligatoria?</label>
                <select id="edit_oc" class="w-full border rounded px-3 py-2">
                    <option value="0">No</option>
                    <option value="1">Sí</option>
                </select>
            </div>
            <div>
                <label class="block font-medium mb-1">Crédito Disponible</label>
                <input type="number" id="edit_credito" class="w-full border rounded px-3 py-2" min="0" />
            </div>
            <div>
                <label class="block font-medium mb-1">Días de Crédito</label>
                <input type="number" id="edit_dias" class="w-full border rounded px-3 py-2" min="0" />
            </div>
        </div>

        <div class="col-span-2">
            <label class="block font-medium mb-1">Canal de Venta</label>
            <select id="edit_canal" class="w-full border rounded px-3 py-2">
                <option value="Diverso">Diverso</option>
                <option value="Facebook Marketplace">Facebook Marketplace</option>
                <option value="Mercado Libre">Mercado Libre</option>
                <option value="Punto de Venta">Punto de Venta</option>
            </select>
        </div>

        <div class="col-span-2">
            <label class="block font-medium mb-1">Perfil de Descuento</label>
            <select id="edit_rol" class="w-full border rounded px-3 py-2">
                <option value="customer">Customer</option>
                <option value="wholesale_customer">Wholesale Customer</option>
                <option value="wholesale_talleres_crash">Talleres Crash</option>
            </select>
        </div>

        <div class="col-span-2">
            <label class="inline-flex items-center">
                <input type="checkbox" id="edit_checkFacturacion" class="mr-2" />
                <span class="font-medium">¿Desea agregar datos de facturación?</span>
            </label>
        </div>
        
        <div class="col-span-2">
            <label class="inline-flex items-center">
                <input type="checkbox" id="edit_cliente_activo" class="mr-2">
                <span class="font-medium">¿Cliente Activo?</span>
            </label>
        </div>

        <div id="edit_camposFacturacion" class="col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 hidden">
            <div>
                <label class="block font-medium mb-1">Razón Social</label>
                <input type="text" id="edit_razon_social" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="block font-medium mb-1">RFC</label>
                <input type="text" id="edit_rfc" class="w-full border rounded px-3 py-2 uppercase" maxlength="13" />
            </div>
            <div>
                <label class="block font-medium mb-1">Uso de CFDI</label>
                <select id="edit_uso_cfdi" class="w-full border rounded px-3 py-2">
                    <option value="">Seleccione</option>
                    <option value="G03">G03 - Gastos en general</option>
                    <option value="P01">P01 - Por definir</option>
                    <option value="D01">D01 - Honorarios médicos</option>
                </select>
            </div>
            <div>
                <label class="block font-medium mb-1">Régimen Fiscal</label>
                <select id="edit_regimen_fiscal" class="w-full border rounded px-3 py-2">
                    <option value="">Seleccione</option>
                    <option value="601">601 - General de Ley Personas Morales</option>
                    <option value="612">612 - Personas Físicas con Actividades Empresariales</option>
                    <option value="622">622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras</option>
                </select>
            </div>

            <div class="md:col-span-2 font-semibold text-gray-700 pt-2">Dirección Fiscal</div>

            <div>
                <label class="block font-medium mb-1">Calle y número</label>
                <input type="text" id="edit_fact_calle" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="block font-medium mb-1">Colonia</label>
                <input type="text" id="edit_fact_colonia" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="block font-medium mb-1">Municipio / Delegación</label>
                <input type="text" id="edit_fact_municipio" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="block font-medium mb-1">Estado</label>
                <input type="text" id="edit_fact_estado" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="block font-medium mb-1">Código Postal</label>
                <input type="text" id="edit_fact_cp" class="w-full border rounded px-3 py-2" maxlength="5" />
            </div>
            <div>
                <label class="block font-medium mb-1">País</label>
                <input type="text" id="edit_fact_pais" class="w-full border rounded px-3 py-2" value="México" />
            </div>
        </div>
    </form>
</template>
<script>
const rolActual = "<?php echo esc_js(wp_get_current_user()->roles[0] ?? ''); ?>";
jQuery(document).ready(function($) {
    $('#tablaClientes').off('click', '.eliminar-cliente').on('click', '.eliminar-cliente', function () {
        const userId = $(this).data('id');
        const nombre = $(this).data('nombre');

        Swal.fire({
            title: `¿Eliminar cliente?`,
            text: `Esta acción eliminará al cliente "${nombre}". Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;

            $.post(ajaxurl, {
                action: 'ajax_eliminar_cliente',
                user_id: userId
            }, function (res) {
                if (res.success) {
                    Swal.fire('Eliminado', res.data.message || 'Cliente eliminado correctamente', 'success');
                    cargarClientes();
                } else {
                    Swal.fire('Error', res.data.message || 'No se pudo eliminar el cliente', 'error');
                }
            });
        });
    });
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

            const puedeEditar = rolActual === 'administrator' || rolActual === 'cobranza';

            const rows = res.data.map(cliente => `
                <tr class="border-t text-sm text-gray-700">
                    <td class="px-2 py-2">${cliente.nombre}</td>
                    <td class="px-2 py-2">${cliente.correo}</td>
                    <td class="px-2 py-2">${cliente.rol}</td>
                    <td class="px-2 py-2">$${cliente.credito_disponible}</td>
                    <td class="px-2 py-2">${cliente.estado_credito}</td>
                    <td class="px-2 py-2">${cliente.canal_venta}</td>
                    <td class="px-2 py-2">
                        ${puedeEditar ? `
                            <button class="text-blue-600 editar-cliente mr-2" data-id="${cliente.id}">Editar</button>
                            <button class="text-red-600 eliminar-cliente" data-id="${cliente.id}" data-nombre="${cliente.nombre}">Eliminar</button>
                        ` : ''}
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
                    html: $('#templateFormularioCliente').html(),
                    width: '60em',
                    showCancelButton: true,
                    confirmButtonText: 'Guardar cambios',
                    didOpen: () => {
                        // Rellenar valores
                        $('#edit_user_id').val(c.id);
                        $('#edit_nombre').val(c.nombre);
                        $('#edit_correo').val(c.correo);
                        $('#edit_telefono').val(c.telefono);
                        $('#edit_tipo').val(c.tipo_cliente);
                        $('#edit_credito').val(c.credito_disponible);
                        $('#edit_dias').val(c.dias_credito);
                        $('#edit_oc').val(c.oc_obligatoria);
                        $('#edit_estado_credito').val(c.estado_credito);
                        $('#edit_rol').val(c.rol_slug);
                        $('#edit_canal').val(c.canal_venta);
                        $('#edit_rfc').val(c.rfc);
                        $('#edit_razon_social').val(c.razon_social);
                        $('#edit_uso_cfdi').val(c.uso_cfdi);
                        $('#edit_regimen_fiscal').val(c.regimen_fiscal);
                        $('#edit_fact_calle').val(c.fact_calle);
                        $('#edit_fact_colonia').val(c.fact_colonia);
                        $('#edit_fact_municipio').val(c.fact_municipio);
                        $('#edit_fact_estado').val(c.fact_estado);
                        $('#edit_fact_cp').val(c.fact_cp);
                        $('#edit_fact_pais').val(c.fact_pais || 'México');
                        $('#edit_cliente_activo').prop('checked', c.cliente_activo === '1' || typeof c.cliente_activo === 'undefined');

                        // Mostrar campos de crédito si aplica
                        if (parseFloat(c.credito_disponible) > 0 || c.estado_credito === 'activo') {
                            $('#edit_checkCredito').prop('checked', true);
                            $('#edit_camposCredito').removeClass('hidden');
                        }

                        // Mostrar campos de facturación si hay datos
                        if (c.rfc || c.razon_social) {
                            $('#edit_checkFacturacion').prop('checked', true);
                            $('#edit_camposFacturacion').removeClass('hidden');
                        }

                        // Listeners para mostrar/ocultar dinámicamente
                        $('#edit_checkCredito').on('change', function () {
                            $('#edit_camposCredito').toggleClass('hidden', !this.checked);
                        });

                        $('#edit_checkFacturacion').on('change', function () {
                            $('#edit_camposFacturacion').toggleClass('hidden', !this.checked);
                        });
                    },
                    preConfirm: () => {
                        return {
                            user_id: $('#edit_user_id').val(),
                            nombre: $('#edit_nombre').val(),
                            correo: $('#edit_correo').val(),
                            telefono: $('#edit_telefono').val(),
                            tipo: $('#edit_tipo').val(),
                            credito: $('#edit_credito').val(),
                            dias: $('#edit_dias').val(),
                            estado_credito: $('#edit_estado_credito').val(),
                            oc: $('#edit_oc').val(),
                            canal: $('#edit_canal').val(),
                            rol: $('#edit_rol').val(),
                            razon_social: $('#edit_razon_social').val(),
                            rfc: $('#edit_rfc').val(),
                            uso_cfdi: $('#edit_uso_cfdi').val(),
                            regimen_fiscal: $('#edit_regimen_fiscal').val(),
                            fact_calle: $('#edit_fact_calle').val(),
                            fact_colonia: $('#edit_fact_colonia').val(),
                            fact_municipio: $('#edit_fact_municipio').val(),
                            fact_estado: $('#edit_fact_estado').val(),
                            fact_cp: $('#edit_fact_cp').val(),
                            fact_pais: $('#edit_fact_pais').val(),
                            activo: $('#edit_cliente_activo').is(':checked') ? '1' : '0',
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