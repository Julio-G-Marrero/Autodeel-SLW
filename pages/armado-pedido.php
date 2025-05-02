<?php
  if (!defined('ABSPATH')) exit;
  include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
  include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
  // Verificar que haya un pedido_id en URL
  $pedido_id = isset($_GET['pedido_id']) ? intval($_GET['pedido_id']) : 0;
  if (!$pedido_id) {
      echo '<div class="text-red-600 font-bold p-6">Error: No se proporcionó un ID de pedido válido.</div>';
      return;
  }
  
  // Enqueue Tailwind y SweetAlert
  wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
  wp_enqueue_script('jquery');
  wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
  ?>
<div class="max-w-7xl mx-auto p-6">
  <h1 class="text-3xl font-bold mb-6 flex items-center gap-2">
    <i class="fas fa-dolly"></i> Armado de Pedido #<?php echo esc_html($pedido_id); ?>
  </h1>
  <!-- Datos del Pedido -->
  <div id="datosPedido" class="mb-6 p-4 bg-white rounded shadow">
    <p class="text-gray-700">Cargando información del pedido...</p>
  </div>
  <div class="text-center mb-6">
    <button id="btnIniciarEscaneo" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
    Iniciar Escaneo de QR
    </button>
  </div>
  <div id="progresoArmado" class="my-8">
    <div class="text-center mb-2 text-sm text-gray-700" id="contadorProductos">
      0 de 0 productos recolectados
    </div>
    <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
      <div id="barraProgreso" class="bg-green-500 h-4 rounded-full transition-all duration-500 ease-out" style="width: 0%;"></div>
    </div>
  </div>
  <div id="qr-reader" class="w-full hidden"></div>
  <!-- oculto hasta que inicie -->
  <!-- Productos Pendientes y Recolectados -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <h3 class="text-lg font-bold mb-2">Productos Pendientes</h3>
        <div id="productosPendientes" class="bg-white rounded shadow p-4 h-[400px] overflow-y-auto">
          <p class="text-gray-500">Cargando productos pendientes...</p>
        </div>
    </div>
    <div>
        <h3 class="text-lg font-bold mb-2">Productos Recolectados</h3>
        <div id="productosRecolectados" class="bg-white rounded shadow p-4 h-[400px] overflow-y-auto">
          <p class="text-gray-500">Aún no se han recolectado productos.</p>
        </div>
    </div>
  </div>
  <!-- Botón Finalizar Armado -->
  <div class="mt-8 text-center">
    <button id="btnFinalizarArmado" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded text-lg hidden">
    Finalizar Armado
    </button>
  </div>
</div>
<audio id="beep-sound" src="https://actions.google.com/sounds/v1/alarms/beep_short.ogg" preload="auto"></audio>
<script>
  var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
</script>
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
  jQuery(document).ready(function($) {
    const pedidoId = <?php echo intval($pedido_id); ?>;
    let productos = []; // Productos cargados
    let recolectados = []; // Productos recolectados
  
    // Cargar productos del pedido
    function cargarProductosPedido() {
      $.post(ajaxurl, { action: 'ajax_obtener_productos_pedido', pedido_id: pedidoId }, function(res) {
        if (!res.success) {
          Swal.fire('Error', 'No se pudo cargar el pedido.', 'error');
          return;
        }
  
        productos = res.data.productos;
        renderProductos();
        renderDatosPedido(res.data.datos_pedido);
      });
    }
    $('#btnIniciarEscaneo').on('click', function() {
        $('#qr-reader').removeClass('hidden'); // Mostrar el lector
        startScanner();
    });

    function renderProductos() {
        if (productos.length === 0) {
        $('#productosPendientes').html('<p class="text-gray-500">No hay productos pendientes.</p>');
        return;
        }
          // Agrupar productos por ubicación
        const ubicaciones = {};
        productos.forEach(p => {
        if (!ubicaciones[p.ubicacion_nombre]) {
            ubicaciones[p.ubicacion_nombre] = [];
        }
        ubicaciones[p.ubicacion_nombre].push(p);
        });

        let html = '';

        for (const [ubicacion, productosUbicacion] of Object.entries(ubicaciones)) {
        html += `
            <div class="mb-6">
            <h4 class="text-lg font-bold mb-2 text-blue-600">${ubicacion}</h4>
            <div class="grid grid-cols-1 gap-4">
        `;

        productosUbicacion.forEach(p => {
            html += `
            <div class="flex items-center gap-4 p-2 border-b">
                <img 
                src="${p.imagen_producto || 'https://via.placeholder.com/50'}" 
                alt="Imagen" 
                class="w-12 h-12 object-cover rounded cursor-pointer img-ver-producto"
                data-imagen="${p.imagen_producto || 'https://via.placeholder.com/200'}">
                <div class="flex-1">
                <div class="font-semibold">${p.sku}</div>
                <div class="text-sm text-gray-600">${p.nombre}</div>
                </div>
                <button 
                  class="btnVerUbicacion text-blue-500 text-xs underline" 
                  data-imagen="${p.ubicacion_imagen}" 
                  data-descripcion="${p.ubicacion_descripcion}" 
                  data-nombre="${p.ubicacion_nombre}">
                  Ver Ubicación
                </button>
            </div>
            `;
        });
        html += '</div></div>';
        }

        $('#productosPendientes').html(html);
        actualizarProgreso();
    }
    function renderRecolectados() {
      if (recolectados.length === 0) {
        $('#productosRecolectados').html('<p class="text-gray-500">Aún no se han recolectado productos.</p>');
        return;
      }
      let html = '';
      recolectados.forEach(p => {
        html += `
        <div class="p-2 border-b flex items-center gap-4">
          <img 
          src="${p.imagen_producto || 'https://via.placeholder.com/50'}" 
          alt="Imagen producto" 
          class="w-12 h-12 object-cover rounded cursor-pointer img-ver-producto"
          data-imagen="${p.imagen_producto || 'https://via.placeholder.com/200'}">
          <div class="flex-1">
            <div class="font-semibold text-sm">${p.sku}</div>
            <div class="text-gray-600 text-xs">${p.nombre}</div>
          </div>
        </div>
      `;
      });
      $('#productosRecolectados').html(html);
      actualizarProgreso(); 
    }
    function renderDatosPedido(datos) {
      $('#datosPedido').html(`
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-700">
          <div><strong>Cliente:</strong> ${datos.cliente}</div>
          <div><strong>Fecha:</strong> ${datos.fecha}</div>
          <div><strong>Total:</strong> $${parseFloat(datos.total).toFixed(2)}</div>
        </div>
      `);
    }
    $(document).on('click', '.btnVerUbicacion', function() {
      const imagen = $(this).data('imagen') || '';
      const descripcion = $(this).data('descripcion') || '';
      const nombreUbicacion = $(this).data('nombre') || 'Ubicación sin nombre';
      Swal.fire({
        title: nombreUbicacion,
        html: `
          ${imagen ? `<img src="${imagen}" alt="Ubicación" class="mx-auto mb-4 rounded mb-2">` : ''}
          <p class="text-gray-700">${descripcion || 'Sin descripción disponible.'}</p>
        `,
        width: 400,
        confirmButtonText: 'Cerrar'
      });
    });
    $(document).on('click', '.img-ver-producto', function() {
      const imagen = $(this).data('imagen') || 'https://via.placeholder.com/400';
      
      Swal.fire({
        title: 'Vista de Producto',
        imageUrl: imagen,
        imageAlt: 'Imagen del producto',
        imageWidth: 400,
        imageHeight: 'auto',
        showConfirmButton: false,
        background: '#fff',
        backdrop: `
          rgba(0,0,0,0.8)
          center center
          no-repeat
        `,
        padding: '2em'
      });
    });

    const qrScanner = new Html5Qrcode("qr-reader");

    function startScanner() {
      Html5Qrcode.getCameras().then(cameras => {
        if (cameras && cameras.length) {
          let cameraId = cameras[0].id;
          for (let cam of cameras) {
            if (cam.label.toLowerCase().includes('back') || cam.label.toLowerCase().includes('trasera')) {
              cameraId = cam.id;
              break;
            }
          }

          qrScanner.start(
            cameraId,
            {
              fps: 30,
              qrbox: { width: 300, height: 300 }
            },
            qrCodeMessage => {
              console.log('QR leído:', qrCodeMessage);
              qrScanner.stop()  // 🛑 Detener antes de procesar
                .then(() => {
                  procesarEscaneo(qrCodeMessage);
                })
                .catch(err => {
                  console.error('Error deteniendo QR scanner:', err);
                });
            },
            errorMessage => {
              console.warn(`QR Error: ${errorMessage}`);
            }
          );
        }
      }).catch(err => {
        console.error('Error obteniendo cámaras:', err);
      });
    }

    function procesarEscaneo(qrData) {
      const sku = extraerSkuDeQR(qrData);

      // 🔥 Validar si ya fue recolectado
      const yaRecolectado = recolectados.some(p => p.sku === sku);
      if (yaRecolectado) {
        Swal.fire('Producto ya recolectado', 'Este producto ya fue escaneado anteriormente.', 'warning');
        return;
      }

      // Si no fue recolectado, ahora sí enviar al servidor
      $.post(ajaxurl, {
          action: 'ajax_buscar_producto_por_sku_pedido',
          pedido_id: pedidoId,
          sku: sku
      }, function(res) {
          if (!res.success) {
              Swal.fire('Producto no encontrado', 'Este producto no pertenece al pedido o ya fue recolectado.', 'error');
              return;
          }

          const beep = document.getElementById('beep-sound');
          beep.play();

          if (navigator.vibrate) {
              navigator.vibrate(200);
          }

          productos = productos.filter(p => p.sku !== res.data.sku);
          recolectados.push(res.data);

          renderProductos();
          renderRecolectados();

          setTimeout(() => {
              $('#productosRecolectados .p-2').first().addClass('bg-green-100');
              setTimeout(() => {
                  $('#productosRecolectados .p-2').first().removeClass('bg-green-100');
              }, 500);
          }, 100);

          if (productos.length === 0) {
              $('#btnFinalizarArmado').removeClass('hidden');
          }

          // 🔥 🔥 🔥 YA NO REACTIVAMOS scanner aquí
          // (Ahora el usuario tiene que dar click en "Iniciar Escaneo de QR" manualmente)
      });
    }

    function extraerSkuDeQR(qrData) {
      try {
          const parser = document.createElement('a');
          parser.href = qrData;

          // Obtener el valor de ?sku=
          const searchParams = new URLSearchParams(parser.search);
          let sku = searchParams.get('sku') || '';

          // Agregar manualmente el fragmento si existe
          if (parser.hash) {
              sku += parser.hash; // el hash incluye el #, ejemplo "#P1174"
          }

          return sku;
      } catch (error) {
          console.warn('Error extrayendo SKU del QR:', error);
          return qrData; // fallback: usar lo que venga
      }
    }
    function actualizarProgreso() {
      const total = productos.length + recolectados.length;
      const recolectadosCount = recolectados.length;
      const porcentaje = total === 0 ? 0 : Math.round((recolectadosCount / total) * 100);
      $('#contadorProductos').text(`${recolectadosCount} de ${total} productos recolectados`);
      $('#barraProgreso').css('width', `${porcentaje}%`);
    }
  function recolectarProducto(qrData) {
      const sku = extraerSkuDeQR(qrData);

      $.post(ajaxurl, {
          action: 'ajax_buscar_producto_por_sku_pedido',
          pedido_id: pedidoId,
          sku: sku
      }, function(res) {
          console.log(res)
          if (!res.success) {
          Swal.fire('Producto no encontrado', 'Este producto no pertenece al pedido o ya fue recolectado.', 'error');
          return;
    }
    // Sonido de beep
      const beep = document.getElementById('beep-sound');
      beep.play();
        // Vibración
        if (navigator.vibrate) {
        navigator.vibrate(200);
        }

        // Agregar a recolectados manualmente
        recolectados.push(res.data);

        renderRecolectados();

        setTimeout(() => {
        $('#productosRecolectados .p-2').first().addClass('bg-green-100');
        setTimeout(() => {
            $('#productosRecolectados .p-2').first().removeClass('bg-green-100');
        }, 500);
        }, 100);

        if (productos.length === 0) {
        $('#btnFinalizarArmado').removeClass('hidden');
        }
    });
    }
    // Finalizar armado
    $('#btnFinalizarArmado').on('click', function() {
      Swal.fire({
        title: '¿Finalizar Armado?',
        text: '¿Estás seguro de que deseas marcar el pedido como "Listo para Envío"?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          $.post(ajaxurl, {
            action: 'ajax_finalizar_armado_pedido',
            pedido_id: pedidoId
          }, function(res) {
            if (res.success) {
              // 🎯 Mostrar resumen bonito primero
              Swal.fire({
                title: 'Armado Finalizado',
                html: `
                  <p class="text-gray-700 text-lg mb-2">Has recolectado todos los productos del pedido.</p>
                  <p class="text-green-600 font-bold text-2xl">${recolectados.length} productos recolectados</p>
                `,
                icon: 'success',
                confirmButtonText: 'Regresar a Gestión de Pedidos',
                background: '#fff',
                padding: '2em',
                backdrop: `
                  rgba(0,0,0,0.8)
                  center center
                  no-repeat
                `
              }).then(() => {
                // 🎯 Ahora sí, redirigir
                window.location.href = '?page=gestion-armado';
              });
            } else {
              Swal.fire('❌ Error', res.data.message || 'No se pudo finalizar el armado.', 'error');
            }
          });
        }
      });
    });
    cargarProductosPedido();
  });
  
</script>
<style>
  img.swal2-image {
    border: none;
  }
</style>