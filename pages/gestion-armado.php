<?php
if (!defined('ABSPATH')) exit;

// Enqueue Tailwind y SweetAlert
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-7xl mx-auto p-6">
  <h1 class="text-3xl font-bold mb-6 flex items-center gap-2">
    <i class="fas fa-box"></i> Gestión de Armado de Pedidos
  </h1>

  <!-- Tabs de estados -->
  <div id="tabsEstados" class="flex flex-wrap gap-2 mb-6">
    <button data-estado="" class="tab-estado px-4 py-2 rounded-full bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300 active">Todos</button>
    <button data-estado="pendiente_armado" class="tab-estado px-4 py-2 rounded-full bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Pendiente</button>
    <!-- <button data-estado="en_armado" class="tab-estado px-4 py-2 rounded-full bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">En Armado</button> -->
    <button data-estado="listo_para_envio" class="tab-estado px-4 py-2 rounded-full bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Listo para Envío</button>
    <button data-estado="enviado" class="tab-estado px-4 py-2 rounded-full bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Enviado</button>
    <button data-estado="entregado" class="tab-estado px-4 py-2 rounded-full bg-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-300">Entregado</button>
  </div>

  <!-- Filtro de cliente -->
  <div class="flex items-center gap-2 mb-6">
    <label for="filtroCliente" class="text-sm font-semibold text-gray-600">Buscar Cliente:</label>
    <input type="text" id="filtroCliente" placeholder="Nombre del Cliente" class="border border-gray-300 rounded px-3 py-2 text-sm w-full md:w-80">
  </div>

  <!-- Tabla de pedidos -->
  <div class="overflow-x-auto bg-white rounded shadow">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
          <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
          <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
        </tr>
      </thead>
      <tbody id="tablaArmado" class="bg-white divide-y divide-gray-200 text-sm">
        <tr><td colspan="6" class="text-center py-4 text-gray-500">Cargando pedidos...</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <div id="paginacionArmado" class="flex justify-center items-center gap-2 mt-6"></div>
</div>
<script>
  var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
</script>
<script>
  jQuery(document).ready(function($) {
  let filtroEstado = ''; 
  let paginaActual = 1;
  const pedidosPorPagina = 10;

  function cargarPedidosArmado() {
    const estado = filtroEstado; // 🔥
    const cliente = $('#filtroCliente').val();

    $('#tablaArmado').html('<tr><td colspan="6" class="text-center py-4 text-gray-500">Cargando...</td></tr>');

    $.post(ajaxurl, {
        action: 'ajax_obtener_pedidos_armado',
        estado: estado,
        cliente: cliente,
        pagina: paginaActual,
        por_pagina: pedidosPorPagina
    }, function(res) {
        if (!res.success || res.data.pedidos.length === 0) {
        $('#tablaArmado').html('<tr><td colspan="6" class="text-center py-4 text-gray-500">No hay pedidos encontrados.</td></tr>');
        $('#paginacionArmado').empty();
        return;
        }

        let html = '';
        res.data.pedidos.forEach(p => {
        const accionBtn = generarBotonAccion(p.id, p.estado_armado);

        html += `
            <tr class="hover:bg-gray-50">
            <td class="px-4 py-2 font-semibold">#${p.id}</td>
            <td class="px-4 py-2">${p.cliente || 'Sin nombre'}</td>
            <td class="px-4 py-2 text-green-600 font-bold">$${parseFloat(p.total.replace(',', '')).toFixed(2)}</td>
            <td class="px-4 py-2 capitalize">${badgeEstado(p.estado_armado)}</td>
            <td class="px-4 py-2">${p.fecha}</td>
            <td class="px-4 py-2 text-center">${accionBtn}</td>
            </tr>`;
        });

        $('#tablaArmado').html(html);
        generarPaginacion(res.data.total_paginas);
    });
    }



  function badgeEstado(estado) {
    let clases = 'px-2 py-1 rounded-full text-xs font-semibold ';
    switch (estado) {
      case 'pendiente_armado': clases += 'bg-yellow-200 text-yellow-800'; break;
      case 'en_armado': clases += 'bg-blue-200 text-blue-800'; break;
      case 'listo_para_envio': clases += 'bg-green-200 text-green-800'; break;
      case 'enviado': clases += 'bg-indigo-200 text-indigo-800'; break;
      case 'entregado': clases += 'bg-gray-300 text-gray-800'; break;
      default: clases += 'bg-gray-200 text-gray-600';
    }
    return `<span class="${clases}">${formatearEstado(estado)}</span>`;
  }

  function formatearEstado(estado) {
    switch (estado) {
      case 'pendiente_armado': return 'Pendiente';
      case 'en_armado': return 'En Armado';
      case 'listo_para_envio': return 'Listo para Envío';
      case 'enviado': return 'Enviado';
      case 'entregado': return 'Entregado';
      default: return estado;
    }
  }

  function generarBotonAccion(pedidoId, estadoArmado) {
    let label = '', nuevoEstado = '', redirect = false;

    switch (estadoArmado) {
      case 'pendiente_armado':
        label = 'Iniciar Armado';
        redirect = true;
        break;
      case 'en_armado':
        label = 'Finalizar Armado';
        nuevoEstado = 'listo_para_envio';
        break;
      case 'listo_para_envio':
        label = 'Marcar como Enviado';
        nuevoEstado = 'enviado';
        break;
      case 'enviado':
        label = 'Marcar como Entregado';
        nuevoEstado = 'entregado';
        break;
      default:
        return '<span class="text-gray-400">Sin acción</span>';
    }

    if (redirect) {
      return `<a href="?page=armado-pedido&pedido_id=${pedidoId}" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs">Iniciar Armado</a>`;
    } else {
      return `<button class="btnActualizarEstado bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs" data-id="${pedidoId}" data-estado="${nuevoEstado}">${label}</button>`;
    }
  }

  function generarPaginacion(totalPaginas) {
        if (totalPaginas <= 1) {
            $('#paginacionArmado').empty();
            return;
        }

        let html = '';

        for (let i = 1; i <= totalPaginas; i++) {
            html += `
            <button
                class="px-3 py-1 rounded-md border text-sm font-semibold ${i === paginaActual ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-100'}"
                data-pagina="${i}"
            >
                ${i}
            </button>
            `;
        }

        $('#paginacionArmado').html(html);
    }
  $(document).on('click', '.tab-estado', function() {
    $('.tab-estado').removeClass('active');
    $(this).addClass('active');

    const estado = $(this).data('estado') || '';
    filtroEstado = estado;
    paginaActual = 1; // Reinicia a página 1
    cargarPedidosArmado();
  });

  $(document).on('click', '.btnActualizarEstado', function() {
    const pedidoId = $(this).data('id');
    const nuevoEstado = $(this).data('estado');

    Swal.fire({
      title: '¿Confirmar cambio?',
      text: `¿Deseas cambiar a "${formatearEstado(nuevoEstado)}"?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, actualizar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        actualizarEstadoArmado(pedidoId, nuevoEstado);
      }
    });
  });

  function actualizarEstadoArmado(pedidoId, nuevoEstado) {
    $.post(ajaxurl, {
      action: 'ajax_actualizar_estado_armado',
      pedido_id: pedidoId,
      nuevo_estado: nuevoEstado
    }, function(res) {
      if (res.success) {
        Swal.fire('✅ Actualizado', res.data.message, 'success');
        cargarPedidosArmado();
      } else {
        Swal.fire('❌ Error', res.data.message || 'No se pudo actualizar.', 'error');
      }
    });
  }

  $(document).on('click', '#paginacionArmado button', function() {
    paginaActual = parseInt($(this).data('pagina'));
    cargarPedidosArmado();
  });

  $('#filtroEstado, #filtroCliente').on('change keyup', function() {
    paginaActual = 1;
    cargarPedidosArmado();
  });

  cargarPedidosArmado();
});
</script>
<style>
  .tab-estado.active {
    background-color: #3b82f6; /* azul de Tailwind */
    color: white;
  }
</style>
