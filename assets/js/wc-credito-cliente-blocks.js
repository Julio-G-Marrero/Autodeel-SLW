const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement, Fragment } = window.wp.element;

console.log('🧠 Archivo wc-credito-cliente-blocks.js cargado');

// Recuperar el valor de oc_obligatoria desde una variable global de WordPress
// ✅ Asegúrate que en PHP también envías este dato, te lo explico más abajo
const ocObligatoria = window.wp_oc_obligatoria === '1'; 

registerPaymentMethod({
    name: 'credito_cliente',
    label: 'Pago a Crédito',
    content: createElement(Fragment, null,
        createElement('div', { style: { marginBottom: '20px' } }, 'Utiliza tu crédito disponible para pagar este pedido.'),
        ocObligatoria && createElement('div', { style: { marginTop: '10px' } },
            createElement('label', { htmlFor: 'orden_compra_file', style: { fontWeight: 'bold' } }, 'Sube tu Orden de Compra'),
            createElement('input', {
                type: 'file',
                id: 'orden_compra_file',
                name: 'orden_compra_file',
                style: { display: 'block', marginTop: '5px' },
                onChange: function (e) {
                    const fileSelected = e.target.files.length > 0;
                    const placeOrderBtn = document.querySelector('button[type="submit"]');
                    if (placeOrderBtn) {
                        placeOrderBtn.disabled = !fileSelected;
                    }
                }
            })
        )
    ),
    edit: createElement('div', null, 'Pago a Crédito'),
    canMakePayment: () => true,
    ariaLabel: 'Pago a Crédito',
    supports: {
        features: ['products', 'cart', 'checkout']
    }
});

console.log('✅ Método de pago "credito_cliente" registrado correctamente en Blocks');
