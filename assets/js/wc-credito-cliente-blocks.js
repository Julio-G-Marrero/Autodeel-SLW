const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement } = window.wp.element;

console.log('🧠 Archivo wc-credito-cliente-blocks.js cargado');

registerPaymentMethod({
    name: 'credito_cliente',
    label: 'Pago a Crédito',
    content: createElement('div', null, 'Utiliza tu crédito disponible para pagar este pedido.'),
    edit: createElement('div', null, 'Pago a Crédito'),
    canMakePayment: () => true,
    ariaLabel: 'Pago a Crédito',
    supports: {
        features: ['products', 'cart', 'checkout']
    }
});

console.log('✅ Método de pago "credito_cliente" registrado sin campo de OC');
