<?php
/** @var WC_Order $order */
$cliente = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
$estado = wc_get_order_status_name($order->get_status());
$total = $order->get_total();
$fecha = $order->get_date_created() ? $order->get_date_created()->format('Y-m-d H:i') : '';
$canal = get_post_meta($order->get_id(), '_canal_venta', true) ?: 'WooCommerce';
$metodo = get_post_meta($order->get_id(), '_metodo_pago', true) ?: $order->get_payment_method_title();
$tipo_cliente = get_post_meta($order->get_id(), '_tipo_cliente', true) ?: 'externo';
wp_enqueue_style('tailwindcdn', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');

?>

<div class="max-w-4xl mx-auto px-6 py-8 bg-white rounded shadow">
    <h1 class="text-2xl font-bold mb-4">🧾 Detalle del Pedido #<?php echo $order->get_id(); ?></h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        <div>
            <p><strong>Cliente:</strong> <?php echo esc_html($cliente); ?></p>
            <p><strong>Fecha:</strong> <?php echo esc_html($fecha); ?></p>
            <p><strong>Canal:</strong> <?php echo esc_html(ucfirst($canal)); ?></p>
            <p><strong>Tipo de Cliente:</strong> <?php echo esc_html(ucfirst($tipo_cliente)); ?></p>
        </div>
        <div>
            <p><strong>Estado del pedido:</strong> <?php echo esc_html($estado); ?></p>
            <p><strong>Método de pago:</strong> <?php echo esc_html($metodo); ?></p>
            <p><strong>Total:</strong> $<?php echo number_format($total, 2); ?></p>
        </div>
    </div>

    <hr class="my-6">

    <h2 class="text-lg font-semibold mb-3">📦 Productos</h2>
    <table class="w-full text-sm border border-gray-300 rounded">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-3 py-2 text-left">SKU</th>
                <th class="px-3 py-2 text-left">Nombre</th>
                <th class="px-3 py-2 text-left">Cantidad</th>
                <th class="px-3 py-2 text-left">Precio</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order->get_items() as $item): ?>
                <?php $product = $item->get_product(); ?>
                <tr>
                    <td class="px-3 py-2"><?php echo $product ? esc_html($product->get_sku()) : '—'; ?></td>
                    <td class="px-3 py-2"><?php echo esc_html($item->get_name()); ?></td>
                    <td class="px-3 py-2"><?php echo esc_html($item->get_quantity()); ?></td>
                    <td class="px-3 py-2">$<?php echo number_format($item->get_total(), 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
