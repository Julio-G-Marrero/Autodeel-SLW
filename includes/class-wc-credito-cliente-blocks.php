<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Gateway_Credito_Cliente_Blocks extends AbstractPaymentMethodType {
    protected $name = 'credito_cliente'; // debe coincidir con el ID del gateway

    public function initialize() {
        $this->settings = get_option('woocommerce_credito_cliente_settings', []);
    }

    public function is_active() {
        return is_user_logged_in() && get_user_meta(get_current_user_id(), 'estado_credito', true) === 'activo';
    }

    public function get_payment_method_script_handles() {
        return []; // no se necesita script extra
    }

    public function get_payment_method_data() {
        return [
            'title'       => $this->settings['title'] ?? 'Pago a Crédito',
            'description' => $this->settings['description'] ?? 'Usa tu crédito disponible para completar tu compra.',
            'supports'    => [],
        ];
    }
}
