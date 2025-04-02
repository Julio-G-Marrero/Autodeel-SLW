<?php
if ( ! defined('ABSPATH') ) {
    exit;
}

global $wpdb;

// Incluir funciones para manejo de archivos (si no están ya cargadas)
if ( ! function_exists('wp_handle_upload') ) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

$upload_notice = '';

// Procesar la subida de archivos cuando se envíe el formulario
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_price_lists']) ) {
    // Procesar archivo de Precio Público
    if ( ! empty( $_FILES['public_price_file']['name'] ) ) {
        $uploaded_file = $_FILES['public_price_file'];
        $upload_overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $uploaded_file, $upload_overrides );
        if ( $movefile && ! isset( $movefile['error'] ) ) {
            update_option( 'catalogo_public_price_list', $movefile['url'] );
            $upload_notice .= '<div class="notice notice-success"><p>Archivo de Precio Público subido correctamente.</p></div>';
        } else {
            $upload_notice .= '<div class="notice notice-error"><p>Error al subir archivo de Precio Público: ' . esc_html( $movefile['error'] ) . '</p></div>';
        }
    }
    // Procesar archivo de Precio Proveedores
    if ( ! empty( $_FILES['supplier_price_file']['name'] ) ) {
        $uploaded_file = $_FILES['supplier_price_file'];
        $upload_overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $uploaded_file, $upload_overrides );
        if ( $movefile && ! isset( $movefile['error'] ) ) {
            update_option( 'catalogo_supplier_price_list', $movefile['url'] );
            $upload_notice .= '<div class="notice notice-success"><p>Archivo de Precio Proveedores subido correctamente.</p></div>';
        } else {
            $upload_notice .= '<div class="notice notice-error"><p>Error al subir archivo de Precio Proveedores: ' . esc_html( $movefile['error'] ) . '</p></div>';
        }
    }
}

// Recuperar los archivos subidos (si existen)
$public_price_file_url = get_option( 'catalogo_public_price_list', '' );
$supplier_price_file_url = get_option( 'catalogo_supplier_price_list', '' );

// Obtener el SKU para consultar las listas de precios (en este ejemplo se utiliza para realizar una consulta en la base de datos)
$catalog_sku = isset( $_GET['catalog_sku'] ) ? sanitize_text_field( $_GET['catalog_sku'] ) : '';

$public_prices = [];
$supplier_prices = [];

// Si se proporciona un SKU, se realizan las consultas en las tablas correspondientes (ajusta las consultas según tu estructura)
if ( ! empty( $catalog_sku ) ) {
    $public_prices = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM wp_lista_precio_publico WHERE sku = %s", $catalog_sku ) );
    $supplier_prices = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM wp_lista_precio_proveedores WHERE sku = %s", $catalog_sku ) );
}
?>

<div class="wrap mi-lista-precios">
    <h2>Subir y Procesar Listas de Precios para Autopartes</h2>
    
    <?php
    if ( ! empty( $upload_notice ) ) {
        echo $upload_notice;
    }
    ?>
    
    <!-- Formulario para subir archivos de listas de precios -->
    <form method="post" enctype="multipart/form-data" class="price-upload-form">
        <h3>Subir Archivo de Precio Público</h3>
        <div class="form-group">
            <input type="file" name="public_price_file" class="form-control">
            <?php if ( $public_price_file_url ) : ?>
                <p>Archivo actual: <a href="<?php echo esc_url( $public_price_file_url ); ?>" target="_blank">Ver Archivo</a></p>
            <?php endif; ?>
        </div>
        
        <h3>Subir Archivo de Precio Proveedores</h3>
        <div class="form-group">
            <input type="file" name="supplier_price_file" class="form-control">
            <?php if ( $supplier_price_file_url ) : ?>
                <p>Archivo actual: <a href="<?php echo esc_url( $supplier_price_file_url ); ?>" target="_blank">Ver Archivo</a></p>
            <?php endif; ?>
        </div>
        
        <div class="form-group">
            <button type="submit" name="upload_price_lists" class="btn btn-primary">Subir Archivos</button>
        </div>
    </form>
    
    <hr>
    
    <!-- Formulario para consultar las listas de precios según el SKU del catálogo profesional -->
    <h2>Consultar Listas de Precios</h2>
    <form method="GET" action="" class="price-search-form">
        <input type="hidden" name="page" value="listas-precios">
        <div class="form-group">
            <label for="catalog_sku">SKU del Catálogo Profesional:</label>
            <input type="text" name="catalog_sku" id="catalog_sku" value="<?php echo esc_attr( $catalog_sku ); ?>" class="form-control">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Buscar</button>
        </div>
    </form>
    
    <?php if ( ! empty( $catalog_sku ) ) : ?>
        <h3 class="tabla-titulo">Precio Público</h3>
        <?php if ( ! empty( $public_prices ) ) : ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $public_prices as $price ) : ?>
                        <tr>
                            <td><?php echo esc_html( $price->sku ); ?></td>
                            <td><?php echo esc_html( $price->price ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No se encontraron registros para el precio público.</p>
        <?php endif; ?>
        
        <h3 class="tabla-titulo">Precio Proveedores</h3>
        <?php if ( ! empty( $supplier_prices ) ) : ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $supplier_prices as $price ) : ?>
                        <tr>
                            <td><?php echo esc_html( $price->sku ); ?></td>
                            <td><?php echo esc_html( $price->price ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No se encontraron registros para el precio proveedores.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
/* Estilos para la página de Listas de Precios */
.mi-lista-precios {
    background: #fff;
    padding: 20px;
    border: 1px solid #e1e4e8;
    border-radius: 8px;
    max-width: 1100px;
    margin: 20px auto;
}

.mi-lista-precios h2 {
    color: #0073aa;
    margin-bottom: 20px;
}

.price-upload-form, .price-search-form {
    background: #f9f9f9;
    padding: 15px;
    border: 1px solid #e1e4e8;
    border-radius: 8px;
    margin-bottom: 20px;
}

.price-upload-form h3 {
    margin-top: 0;
}

.price-upload-form .form-group,
.price-search-form .form-group {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
}

.price-upload-form label,
.price-search-form label {
    font-weight: bold;
    margin-bottom: 5px;
}

.form-control {
    padding: 8px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    font-size: 1rem;
}

.btn {
    display: inline-block;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    text-align: center;
}

.btn-primary {
    background-color: #0073aa;
    color: #fff;
}

.btn-primary:hover {
    background-color: #005177;
}

.tabla-titulo {
    margin-top: 30px;
    margin-bottom: 10px;
    font-size: 1.3rem;
    color: #333;
}

.mi-lista-precios table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
}

.mi-lista-precios table th,
.mi-lista-precios table td {
    padding: 12px;
    border: 1px solid #e1e4e8;
    text-align: left;
}

.mi-lista-precios table th {
    background: #f7f9fc;
    font-weight: bold;
}

/* Notificaciones */
.notice {
    padding: 10px;
    margin-bottom: 15px;
    border-left: 4px solid;
}

.notice-success {
    background-color: #e6ffed;
    border-color: #46b450;
}

.notice-error {
    background-color: #ffeef0;
    border-color: #d73a49;
}
</style>
