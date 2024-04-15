<?php
/*
 * Plugin Name: Subir imágenes a ImgBB
 * Plugin URI: https://github.com/0x230797/subir-imagenes
 * Description: Este plugin te permite subir imágenes a ImgBB de forma sencilla y rápida.
 * Version: 1.0
 * Author: C A N I B A L
 * Author URI: https://github.com/0x230797
 * Text Domain: subir-imagenes
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// Evitar que el archivo sea accedido directamente
if (!defined('ABSPATH')) {
    exit;
}

// Añadir las páginas de menú y configuración
add_action('admin_menu', 'imgbb_uploader_menu');
add_action('admin_init', 'imgbb_register_settings');

// Menú del plugin
function imgbb_uploader_menu() {
    add_menu_page('Subir imágenes', 'Subir imágenes', 'manage_options', 'imgbb-uploader', 'imgbb_uploader_page', 'dashicons-format-gallery', 3);
    add_submenu_page('imgbb-uploader', 'Configuración', 'Configuración', 'manage_options', 'imgbb-settings', 'imgbb_settings_page');
}

// Registro de configuraciones del plugin
function imgbb_register_settings() {
    register_setting('imgbb_settings_group', 'imgbb_api_key');
}

// Página de configuración
function imgbb_settings_page() {
    ?>
    <div class="wrap">
        <h2>Configuración</h2>
        <form method="post" action="options.php">
            <?php settings_fields('imgbb_settings_group'); ?>
            <?php do_settings_sections('imgbb_settings_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Key</th>
                    <td>
                        <input type="text" name="imgbb_api_key" value="<?php echo esc_attr(get_option('imgbb_api_key')); ?>" />
                        <p>Puedes encontrar tu API KEY <a href="https://api.imgbb.com/" target="_blank" rel="noopener noreferrer">aquí</a> registrándote en ImgBB</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Página de subida de imágenes
function imgbb_uploader_page() {
    ?>
    <div class="wrap">
        <h2>Subir imágenes a ImgBB</h2>
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="imgbb_upload">
            <?php wp_nonce_field('imgbb_upload_nonce', 'imgbb_upload_nonce'); ?>
            <input type="file" name="image[]" multiple onchange="previewImages(event)">
            <br/><br/>
            <div id="image-preview"></div>
            <input type="submit" value="Subir" class="wp-core-ui button-primary"/>
        </form>
    </div>
    <script>
        function previewImages(event) {
            var preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            var files = event.target.files;

            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var reader = new FileReader();

                reader.onload = function (e) {
                    var img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '100px';
                    img.style.maxHeight = '100px';
                    img.style.marginRight = '10px';
                    img.style.marginBottom = '10px';
                    preview.appendChild(img);
                }

                reader.readAsDataURL(file);
            }
        }
    </script>
    <?php
}

// Manejo de la subida de imágenes
function imgbb_handle_upload() {
    if (!isset($_POST['imgbb_upload_nonce']) || !wp_verify_nonce($_POST['imgbb_upload_nonce'], 'imgbb_upload_nonce')) {
        wp_die('Nonce verification failed');
    }

    if (!current_user_can('manage_options')) {
        wp_die('No tienes permiso para subir archivos');
    }

    if (!isset($_FILES['image']) || empty($_FILES['image']['name'])) {
        wp_die('No se ha subido ningún archivo');
    }

    $api_key = get_option('imgbb_api_key');
    if (!$api_key) {
        wp_die('La API Key no se ha configurado.');
    }

    $uploaded_images = $_FILES['image'];

    $uploaded_urls = array();

    foreach ($uploaded_images['tmp_name'] as $key => $tmp_name) {
        $file = $tmp_name;
        $file_name = pathinfo($uploaded_images['name'][$key], PATHINFO_FILENAME); // Obtener el nombre original del archivo sin la extensión

        $api_url = 'https://api.imgbb.com/1/upload?key=' . $api_key;
        $image_data = file_get_contents($file);
        $image_base64 = base64_encode($image_data);
        $response = wp_remote_post($api_url, array(
            'body' => array(
                'image' => $image_base64,
                'name' => $file_name, // Enviar el nombre original del archivo a la API
            ),
        ));

        if (is_wp_error($response)) {
            wp_die('Error al subir imagen: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!isset($data->data->url)) {
            wp_die('API inválida.');
        }

        $image_url = $data->data->url;
        $uploaded_urls[] = array(
            'url' => $image_url,
            'name' => $file_name,
        );
    }

    foreach ($uploaded_urls as $uploaded) {
        echo  esc_url($uploaded['url']) . '<br>';
    }
}

// Enganchar la acción de subida de imágenes
add_action('admin_post_imgbb_upload', 'imgbb_handle_upload');

?>
