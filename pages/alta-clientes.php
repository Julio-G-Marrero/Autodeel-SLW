<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
// ✅ Obtener perfiles de Wholesale (excluyendo administrador)
global $wp_roles;

$perfiles_wholesale = [];

foreach ($wp_roles->roles as $slug => $details) {
    if (
        $slug !== 'administrator' &&                                // 🔴 Excluir administrador
        !empty($details['capabilities']['have_wholesale_price'])    // ✅ Solo los que tengan precio de mayoreo
    ) {
        $perfiles_wholesale[$slug] = $details['name'];
    }
}

// Ordenar por nombre mostrado (opcional)
asort($perfiles_wholesale);
?>

<div class="max-w-4xl mx-auto p-6 bg-white rounded shadow mt-6">
    <h2 class="text-2xl font-bold mb-4">Alta de Cliente</h2>

    <form id="formAltaCliente" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Nombre / Razón Social</label>
            <input type="text" name="nombre" class="w-full border rounded px-3 py-2" required />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Correo Electrónico</label>
            <input type="email" name="correo" class="w-full border rounded px-3 py-2" required />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Teléfono</label>
            <input type="text" name="telefono" class="w-full border rounded px-3 py-2" />
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Tipo de Cliente</label>
            <select name="tipo" id="tipoCliente" class="w-full border rounded px-3 py-2">
                <option value="externo">Externo</option>
                <option value="interno">Interno</option>
                <option value="distribuidor">Distribuidor</option>
            </select>
        </div>
        <div id="campoSucursal" class="hidden">
            <label class="block text-sm font-medium mb-1">Sucursal Asociada</label>
            <input type="text" name="sucursal" class="w-full border rounded px-3 py-2" />
        </div>

        <div class="md:col-span-2">
            <label class="inline-flex items-center">
                <input type="checkbox" id="checkCredito" class="mr-2">
                <span class="text-sm font-medium">¿Dispone de Crédito?</span>
            </label>
        </div>

        <div id="camposCredito" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 hidden">
            <div>
                <label class="block text-sm font-medium mb-1">Estado del Crédito</label>
                <select name="estado_credito" class="w-full border rounded px-3 py-2">
                    <option value="activo">Activo</option>
                    <option value="suspendido">Suspendido</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">¿Orden de Compra Obligatoria?</label>
                <input type="checkbox" name="oc_obligatoria" class="mr-2" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Crédito Disponible</label>
                <input type="number" name="credito" class="w-full border rounded px-3 py-2" value="0" min="0" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Días de Crédito</label>
                <input type="number" name="dias_credito" class="w-full border rounded px-3 py-2" value="0" min="0" />
            </div>
        </div>

        <div class="md:col-span-2">
            <div>
                <label class="block text-sm font-medium mb-1">Canal de Venta Predeterminado</label>
                <select name="canal" class="w-full border rounded px-3 py-2">
                    <option value="Diverso">Diverso</option>
                    <option value="Facebook Marketplace">Facebook Marketplace</option>
                    <option value="Mercado Libre">Mercado Libre</option>
                    <option value="Punto de Venta">Punto de Venta</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Perfil de Descuento</label>
                    <select name="wholesale_role" class="w-full border rounded px-3 py-2">
                        <option value="">Sin perfil</option>
                        <?php foreach ($perfiles_wholesale as $slug => $label): ?>
                            <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="inline-flex items-center">
                <input type="checkbox" id="checkFacturacion" class="mr-2">
                <span class="text-sm font-medium">¿Desea agregar datos de facturación?</span>
            </label>
        </div>

        <div id="camposFacturacion" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 hidden">

            <div>
                <label class="block text-sm font-medium mb-1">Razón Social</label>
                <input type="text" name="razon_social" class="w-full border rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">RFC</label>
                <input type="text" name="rfc" class="w-full border rounded px-3 py-2 text-uppercase" maxlength="13" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Uso de CFDI</label>
                <select name="uso_cfdi" class="w-full border rounded px-3 py-2">
                    <option value="">Seleccione</option>
                    <option value="G03">G03 - Gastos en general</option>
                    <option value="P01">P01 - Por definir</option>
                    <option value="D01">D01 - Honorarios médicos</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Régimen Fiscal</label>
                <select name="regimen_fiscal" class="w-full border rounded px-3 py-2">
                    <option value="">Seleccione</option>
                    <option value="601">601 - General de Ley Personas Morales</option>
                    <option value="612">612 - Personas Físicas con Actividades Empresariales</option>
                    <option value="622">622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras</option>
                </select>
            </div>

            <div class="md:col-span-2 font-semibold text-gray-700 pt-2">
                Dirección Fiscal
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Calle y número</label>
                <input type="text" name="fact_calle" class="w-full border rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Colonia</label>
                <input type="text" name="fact_colonia" class="w-full border rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Municipio / Delegación</label>
                <input type="text" name="fact_municipio" class="w-full border rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Estado</label>
                <input type="text" name="fact_estado" class="w-full border rounded px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Código Postal</label>
                <input type="text" name="fact_cp" class="w-full border rounded px-3 py-2" maxlength="5" />
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">País</label>
                <input type="text" name="fact_pais" class="w-full border rounded px-3 py-2" value="México" />
            </div>
        </div>
        
        <div class="md:col-span-2 text-right mt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Registrar Cliente
            </button>
        </div>
    </form>

    <div id="respuestaAlta" class="mt-4 text-sm"></div>
</div>

<script>
document.getElementById('tipoCliente').addEventListener('change', function () {
    const campoSucursal = document.getElementById('campoSucursal');
    campoSucursal.classList.toggle('hidden', this.value !== 'interno');
});

document.getElementById('formAltaCliente').addEventListener('submit', function (e) {
    e.preventDefault();
    const datos = new FormData(this);
    const btn = document.querySelector('#formAltaCliente button[type="submit"]');
    btn.disabled = true;

    fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
        method: 'POST',
        body: new URLSearchParams([...datos.entries()]).toString() + '&action=ajax_registrar_cliente',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    })
    .then(res => res.json())  // ← 🔥 importante: convertir a JSON
    .then(data => {
        const btn = document.querySelector('#formAltaCliente button[type="submit"]');
        btn.disabled = false;

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Cliente registrado correctamente',
                text: data.data.message || 'El cliente fue creado exitosamente.',
                confirmButtonText: 'Aceptar'
            });

            document.getElementById('formAltaCliente').reset();
            document.getElementById('camposCredito').classList.add('hidden');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error al registrar',
                text: data?.data?.message || 'Hubo un problema inesperado.'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error de red',
            text: 'No se pudo conectar con el servidor.'
        });
        console.error('Error al procesar el registro:', error);
    });
});
document.getElementById('checkCredito').addEventListener('change', function () {
    const campos = document.getElementById('camposCredito');
    campos.classList.toggle('hidden', !this.checked);
});
document.getElementById('checkFacturacion').addEventListener('change', function () {
    document.getElementById('camposFacturacion').classList.toggle('hidden', !this.checked);
});

</script>
<style>
.swal2-html-container {
    text-align: left !important;
}

/* Hacer que los inputs ocupen todo el ancho */
.swal2-input,
.swal2-select {
    width: 100% !important;
    margin: 0.5rem 0;
    text-align: left !important;
}

/* Opcional: reducir tamaño si deseas mostrar más datos */
.swal2-popup {
    width: 40em !important;
    max-width: 90%;
}
</style>
