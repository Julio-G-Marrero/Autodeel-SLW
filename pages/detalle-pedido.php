<?php
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual
/** @var WC_Order $order */
$cliente = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
$estado = wc_get_order_status_name($order->get_status());
$total = $order->get_total();
$fecha = $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i') : '';
$canal = get_post_meta($order->get_id(), '_canal_venta', true) ?: 'WooCommerce';
$metodo = get_post_meta($order->get_id(), '_metodo_pago', true) ?: $order->get_payment_method_title();
$tipo_cliente = get_post_meta($order->get_id(), '_tipo_cliente', true) ?: 'externo';
$estado_armado = get_post_meta($order->get_id(), '_estado_armado', true);
$productos_recolectados = get_post_meta($order->get_id(), '_productos_recolectados', true);
$recolectados = is_array($productos_recolectados) ? $productos_recolectados : [];

$total_items = count($order->get_items());
$total_recolectados = count($recolectados);
$progreso = $total_items > 0 ? round(($total_recolectados / $total_items) * 100) : 0;
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);
?>

<div class="max-w-7xl mx-auto px-4 py-6 grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- 🧾 Info de Pedido -->
    <div class="md:col-span-2 bg-white rounded shadow p-6">
        <h2 class="text-xl font-bold mb-4">Pedido #<?php echo $order->get_id(); ?></h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-4">
            <div>
                <p><strong>Cliente:</strong> <?php echo esc_html($cliente); ?></p>
                <p><strong>Fecha:</strong> <?php echo esc_html($fecha); ?></p>
                <p><strong>Canal:</strong> <?php echo esc_html(ucfirst($canal)); ?></p>
                <p><strong>Tipo de Cliente:</strong> <?php echo esc_html(ucfirst($tipo_cliente)); ?></p>
            </div>
            <div>
                <p><strong>Estado:</strong> <?php echo esc_html($estado); ?></p>
                <p><strong>Método de Pago:</strong> <?php echo esc_html($metodo); ?></p>
                <p><strong>Total:</strong> $<?php echo number_format($total, 2); ?></p>
            </div>
        </div>

        <h3 class="text-lg font-semibold mt-6 mb-2">Productos</h3>
        <table class="w-full border text-sm rounded overflow-hidden">
            <thead class="bg-gray-100">
                <tr>
                    <th class="text-left px-3 py-2">SKU</th>
                    <th class="text-left px-3 py-2">Nombre</th>
                    <th class="text-left px-3 py-2">Cantidad</th>
                    <th class="text-left px-3 py-2">Precio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order->get_items() as $item): ?>
                    <?php $product = $item->get_product(); ?>
                    <tr class="border-t">
                        <td class="px-3 py-2"><?php echo $product ? esc_html($product->get_sku()) : '—'; ?></td>
                        <td class="px-3 py-2"><?php echo esc_html($item->get_name()); ?></td>
                        <td class="px-3 py-2"><?php echo esc_html($item->get_quantity()); ?></td>
                        <td class="px-3 py-2">$<?php echo number_format($item->get_total(), 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="mt-6 text-sm border-t pt-4 text-right space-y-2">
            <p><strong>Subtotal:</strong> $<?php echo number_format($order->get_subtotal(), 2); ?></p>
            <p><strong>Envío:</strong> $<?php echo number_format($order->get_shipping_total(), 2); ?></p>
            <p class="text-lg font-bold text-gray-800"><strong>Total del Pedido:</strong> $<?php echo number_format($order->get_total(), 2); ?></p>
        </div>
    </div>

    <!-- 📝 Notas internas y historial -->
    <div class="bg-gray-50 rounded shadow p-6 text-sm">
        <h3 class="text-lg font-semibold mb-3">Notas Internas</h3>
        <div id="listaNotasInternas" class="mb-4 space-y-2 rounded p-3 bg-white overflow-y-auto max-h-64 shadow-inner">
            <?php
            $notes = wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'internal']);
            if ($notes) {
                foreach ($notes as $note) {
                    echo '<div class="bg-white border rounded p-2 shadow"><p>' . esc_html($note->content) . '</p><small class="text-gray-500">' . esc_html($note->date_created->date('Y-m-d H:i')) . '</small></div>';
                }
            } else {
                echo '<p class="text-gray-500">No hay notas aún.</p>';
            }
            ?>
        </div>
        <textarea id="nuevaNota" class="w-full border rounded px-2 py-1 mb-2 border-t-0 border-r-0 border-l-0 border-b-3" rows="2" placeholder="Escribe una nota..."></textarea>
        <button id="btnAgregarNota" data-order-id="<?php echo $order->get_id(); ?>"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1 rounded text-sm w-full">
            Agregar Nota
        </button>

        <hr class="my-5">

        <div>
            <h3 class="text-lg font-bold mb-4">Información del Armado</h3>
            <p><strong>Estado:</strong> 
            <span class="capitalize">
                <?php
                    $estado_legible = str_replace('_', ' ', $estado_armado ?: 'pendiente_armado');
                    echo ucwords($estado_legible);
                ?>
            </span>
            </p>
            <!-- <p><strong>Productos recolectados:</strong> <?php echo "$total_recolectados de $total_items"; ?></p> -->
<!-- 
            <div class="w-full bg-gray-200 rounded-full h-4 my-3 overflow-hidden">
                <div class="bg-green-500 h-4 rounded-full transition-all duration-500 ease-out" style="width: <?php echo $progreso; ?>%;"></div>
            </div> -->

            <!-- <?php if (!empty($recolectados)) : ?>
                <div class="mt-4">
                    <h4 class="font-semibold mb-2">Productos Recolectados:</h4>
                    <ul class="list-disc pl-5 text-sm text-gray-700 max-h-48 overflow-y-auto border-t border-gray-200 pt-2">
                        <?php foreach ($recolectados as $sku) : ?>
                            <li><?php echo esc_html($sku); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?> -->
        </div>

        <hr class="my-5">

        <div class="grid grid-cols-1 md:grid-row-2 gap-6 mt-8">
            <!-- 🔸 Columna derecha: Notas e Historial -->
            <div>
                <h2 class="text-lg font-semibold mb-3">Historial del cliente</h2>
                <ul class="text-sm space-y-1">
                    <?php
                    $orders = wc_get_orders([
                        'customer_id' => $order->get_customer_id(),
                        'exclude' => [$order->get_id()],
                        'limit' => 5,
                        'orderby' => 'date',
                        'order' => 'DESC'
                    ]);
                    foreach ($orders as $o):
                    ?>
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=detalle-pedido&id=' . $o->get_id()); ?>" class="text-blue-600 underline">
                                Pedido #<?php echo $o->get_id(); ?> - <?php echo $o->get_date_created()->format('Y-m-d'); ?> ($<?php echo number_format($o->get_total(), 2); ?>)
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#btnAgregarNota').on('click', function () {
        const order_id = $(this).data('order-id');
        const nota = $('#nuevaNota').val().trim();
        if (!nota) return;

        $.post(ajaxurl, {
            action: 'agregar_nota_interna',
            order_id: order_id,
            contenido: nota
        }, function (res) {
            if (res.success) {
                $('#listaNotasInternas').prepend(`
                    <div class="bg-white border rounded p-2 shadow mb-2">
                        <p>${res.data.nota}</p>
                        <small class="text-gray-500">${res.data.fecha}</small>
                    </div>
                `);
                $('#nuevaNota').val('');

                // 🎉 Toast arriba a la izquierda
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Nota guardada correctamente',
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'swal-custom-margin-left' // opcional si quieres ajustar más la posición
                    }
                });
            }else {
                Swal.fire('Error', 'No se pudo guardar la nota.', 'error');
            }
        });
    });
});
</script>
