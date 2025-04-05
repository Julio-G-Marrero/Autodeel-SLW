<?php
if (!defined('ABSPATH')) exit;
include_once plugin_dir_path(__FILE__) . '../templates/layout.php';
include_once plugin_dir_path(__FILE__) . '/../templates/sidebar.php'; // sidebar visual


// Solo para administradores
if (!current_user_can('manage_options')) {
    wp_die(__('No tienes permisos para acceder a esta página.'));
}

global $wpdb;
$tabla_precios = $wpdb->prefix . 'precios_catalogos';
$catalogos_disponibles = $wpdb->get_col("SELECT DISTINCT catalogo FROM $tabla_precios ORDER BY catalogo ASC");

// Eliminar todos los precios de un catálogo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_catalogo'])) {
    $catalogo_a_eliminar = sanitize_text_field($_POST['eliminar_catalogo']);
    $eliminados = $wpdb->delete($tabla_precios, ['catalogo' => $catalogo_a_eliminar]);

    echo '<div class="notice notice-warning"><p>Se eliminaron <strong>' . intval($eliminados) . '</strong> precios del catálogo <strong>' . esc_html($catalogo_a_eliminar) . '</strong>.</p></div>';
}

// Subida y procesamiento del archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $catalogo = sanitize_text_field($_POST['catalogo']);
    $archivo = $_FILES['csv_file'];

    // Validar si ya hay registros para este catálogo
    $ya_existen = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tabla_precios WHERE catalogo = %s",
        $catalogo
    ));

    if ($ya_existen > 0) {
        echo '<div class="notice notice-error"><p>⚠️ Ya existen precios para el catálogo <strong>' . esc_html($catalogo) . '</strong>. Elimínalos antes de volver a subir.</p></div>';
    } else {
        if ($archivo['error'] === 0 && pathinfo($archivo['name'], PATHINFO_EXTENSION) === 'csv') {
            $handle = fopen($archivo['tmp_name'], 'r');
            $cabecera = fgetcsv($handle); // Leer encabezado

            $insertados = 0;
            $ignorados = 0;

            while (($datos = fgetcsv($handle, 1000, ',')) !== false) {
                // Validación y asignación por columnas:
                // [0] SKU
                // [1] PRECIO PUBLICO SIN IVA
                // [2] PRECIO PUBLICO CON IVA INCLUIDO
                // [3] PRECIO PROVEEDOR SIN IVA
                // [4] PRECIO PROVEEDOR CON IVA INCLUIDO

                if (count($datos) < 5) {
                    $ignorados++;
                    continue;
                }

                $sku_completo = trim($datos[0]);
                $sku_base = explode('#', $sku_completo)[0];

                $precio_publico = floatval(str_replace(['$', ','], '', $datos[2] ?? 0));
                $precio_proveedor = floatval(str_replace(['$', ','], '', $datos[4] ?? 0));

                if (!$sku_base || $precio_publico <= 0) {
                    $ignorados++;
                    continue;
                }

                $existe_sku = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $tabla_precios WHERE sku_base = %s AND catalogo = %s",
                    $sku_base, $catalogo
                ));

                if ($existe_sku > 0) {
                    $ignorados++;
                    continue; // no sobrescribimos
                }

                $resultado = $wpdb->insert(
                    $tabla_precios,
                    [
                        'sku_base' => $sku_base,
                        'precio_proveedor' => $precio_proveedor,
                        'precio_publico' => $precio_publico,
                        'catalogo' => $catalogo,
                        'fecha_subida' => current_time('mysql')
                    ]
                );

                if ($resultado !== false) {
                    $insertados++;
                } else {
                    $ignorados++;
                }
            }

            fclose($handle);

            echo '<div class="notice notice-success"><p>';
            echo "Carga completada: <strong>$insertados insertados</strong>, <strong>$ignorados ignorados</strong>.";
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error al subir el archivo. Asegúrate de que sea un CSV válido.</p></div>';
        }
    }
}
?>

<div class="wrap">
    <h1>Subir Lista de Precios</h1>
    <form method="post" enctype="multipart/form-data">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="catalogo">Nombre del Catálogo</label></th>
                <td><input type="text" name="catalogo" id="catalogo" value="RADEC" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="csv_file">Archivo CSV</label></th>
                <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
            </tr>
        </table>
        <p><input type="submit" value="Subir y Procesar" class="button button-primary"></p>
    </form>
    <hr>
    <h2>Eliminar precios por catálogo</h2>
    <?php if (!empty($catalogos_disponibles)): ?>
    <form method="post" style="margin-bottom: 2rem;">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="eliminar_catalogo_input">Selecciona un catálogo</label></th>
                <td>
                    <select name="eliminar_catalogo" id="eliminar_catalogo_input" required>
                        <option value="">-- Selecciona un catálogo --</option>
                        <?php foreach ($catalogos_disponibles as $catalogo): ?>
                            <option value="<?php echo esc_attr($catalogo); ?>"><?php echo esc_html($catalogo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-danger" onclick="return confirm('¿Estás seguro de eliminar todos los precios del catálogo seleccionado?')">
            🗑 Eliminar todos los precios del catálogo
        </button>
    </form>
    <?php else: ?>
        <p><em>No hay catálogos disponibles para eliminar.</em></p>
    <?php endif; ?>
</div>

<?php
// Consulta visual de precios cargados
$sku_filtro = isset($_GET['sku']) ? sanitize_text_field($_GET['sku']) : '';
$filtro_sql = $sku_filtro ? $wpdb->prepare("WHERE sku_base LIKE %s", '%' . $sku_filtro . '%') : '';

$resultados = $wpdb->get_results("
    SELECT sku_base, precio_proveedor, precio_publico, catalogo, fecha_subida 
    FROM $tabla_precios
    $filtro_sql
    ORDER BY fecha_subida DESC
    LIMIT 200
");

?>

<hr>
<h2>Buscar precios por SKU</h2>
<form method="get" style="margin-bottom: 1rem;">
    <input type="hidden" name="page" value="listas-precios" />
    <label for="sku">Buscar SKU:</label>
    <input type="text" name="sku" id="sku" value="<?php echo esc_attr($sku_filtro); ?>" placeholder="Ej. 017-16303-25" />
    <input type="submit" class="button" value="Buscar">
</form>

<table class="widefat striped" style="margin-top: 1rem;">
    <thead>
        <tr>
            <th>SKU Base</th>
            <th>Precio Proveedor</th>
            <th>Precio Público</th>
            <th>Catálogo</th>
            <th>Fecha de Subida</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($resultados) > 0): ?>
            <?php foreach ($resultados as $fila): ?>
                <tr>
                    <td><?php echo esc_html($fila->sku_base); ?></td>
                    <td>$<?php echo number_format($fila->precio_proveedor, 2); ?></td>
                    <td>$<?php echo number_format($fila->precio_publico, 2); ?></td>
                    <td><?php echo esc_html($fila->catalogo); ?></td>
                    <td><?php echo esc_html($fila->fecha_subida); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5">No se encontraron registros.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
