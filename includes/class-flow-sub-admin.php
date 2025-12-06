<?php
/**
 * Admin Class for Flow Sub
 *
 * @package FlowSub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Flow_Sub_Admin
 */
class Flow_Sub_Admin
{

    /**
     * Initialize the class.
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add the admin menu page.
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Flow Sub Settings', 'flow-sub'),
            __('Flow Sub', 'flow-sub'),
            'manage_options',
            'flow-sub',
            array($this, 'settings_page_html'),
            'dashicons-money-alt',
            56
        );
    }

    /**
     * Register plugin settings.
     */
    /**
     * Register plugin settings.
     */
    public function register_settings()
    {
        register_setting('flow_sub_options', 'flow_sub_api_key', array($this, 'encrypt_key'));
        register_setting('flow_sub_options', 'flow_sub_secret_key', array($this, 'encrypt_key'));
        register_setting('flow_sub_subscriptions_options', 'flow_sub_subscriptions_content');
        register_setting('flow_sub_subscriptions_options', 'flow_sub_selected_plans');
        register_setting('flow_sub_theme_options', 'flow_sub_background_image');
        register_setting('flow_sub_feed_options', 'flow_sub_feed_slug', array($this, 'sanitize_slug'));
        register_setting('flow_sub_feed_options', 'flow_sub_feed_page_title');
        register_setting('flow_sub_feed_options', 'flow_sub_feed_logo');
    }

    /**
     * Encrypt key before saving.
     *
     * @param string $value The value to encrypt.
     * @return string Encrypted value.
     */
    public function encrypt_key($value)
    {
        if (empty($value)) {
            return $value;
        }

        // Don't double encrypt if it looks like it's already encrypted (simple heuristic or just rely on decryption failing gracefully)
        // Actually, WP passes the new value.

        $key = defined('AUTH_KEY') ? AUTH_KEY : 'flow-sub-secret-salt';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Sanitize slug before saving.
     *
     * @param string $value The slug value to sanitize.
     * @return string Sanitized slug.
     */
    public function sanitize_slug($value)
    {
        if (empty($value)) {
            return 'flow-feed'; // Default value
        }

        // Use WordPress sanitize_title to ensure URL-safe slug
        $sanitized = sanitize_title($value);

        // If sanitization results in empty string, return default
        if (empty($sanitized)) {
            return 'flow-feed';
        }

        return $sanitized;
    }

    /**
     * Enqueue admin scripts for media uploader.
     */
    public function enqueue_admin_scripts($hook)
    {
        if ('toplevel_page_flow-sub' !== $hook) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('flow-sub-admin', plugin_dir_url(__FILE__) . '../assets/js/admin.js', array('jquery'), '1.0.0', true);
    }

    /**
     * Render the settings page HTML.
     */
    public function settings_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'flow_tokens';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=flow-sub&tab=flow_tokens"
                    class="nav-tab <?php echo 'flow_tokens' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Flow Tokens', 'flow-sub'); ?></a>
                <a href="?page=flow-sub&tab=api_tester"
                    class="nav-tab <?php echo 'api_tester' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('API Tester', 'flow-sub'); ?></a>
                <a href="?page=flow-sub&tab=subscriptions"
                    class="nav-tab <?php echo 'subscriptions' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Suscripciones', 'flow-sub'); ?></a>
                <a href="?page=flow-sub&tab=feed_settings"
                    class="nav-tab <?php echo 'feed_settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Feed Settings', 'flow-sub'); ?></a>
                <a href="?page=flow-sub&tab=theme"
                    class="nav-tab <?php echo 'theme' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Theme', 'flow-sub'); ?></a>
            </nav>

            <?php if ('flow_tokens' === $active_tab): ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('flow_sub_options');
                    do_settings_sections('flow_sub_options');
                    ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e('API Key', 'flow-sub'); ?></th>
                            <td><input type="text" name="flow_sub_api_key"
                                    value="<?php echo esc_attr(get_option('flow_sub_api_key')); ?>" class="regular-text" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e('Secret Key', 'flow-sub'); ?></th>
                            <td><input type="text" name="flow_sub_secret_key"
                                    value="<?php echo esc_attr(get_option('flow_sub_secret_key')); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            <?php elseif ('subscriptions' === $active_tab): ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('flow_sub_subscriptions_options');
                    do_settings_sections('flow_sub_subscriptions_options');
                    ?>
                    <h2><?php esc_html_e('Contenido de Suscripciones', 'flow-sub'); ?></h2>
                    <p><?php esc_html_e('Este contenido se mostrará debajo de la tabla de suscripciones en la cuenta del usuario.', 'flow-sub'); ?>
                    </p>
                    <?php
                    $content = get_option('flow_sub_subscriptions_content', '');
                    wp_editor($content, 'flow_sub_subscriptions_content', array(
                        'textarea_name' => 'flow_sub_subscriptions_content',
                        'media_buttons' => true,
                        'textarea_rows' => 10,
                        'teeny' => false
                    ));
                    ?>

                    <h2><?php esc_html_e('Botón Subscripciones', 'flow-sub'); ?></h2>
                    <p><?php esc_html_e('Selecciona los planes que se mostrarán en el botón de suscripción (dropdown). Si no seleccionas ninguno, se mostrará el comportamiento por defecto.', 'flow-sub'); ?>
                    </p>
                    <?php
                    $api_key = get_option('flow_sub_api_key');
                    $secret_key = get_option('flow_sub_secret_key');

                    if ($api_key && $secret_key) {
                        require_once FLOW_SUB_PATH . 'includes/class-flow-sub-api.php';
                        $api = new Flow_Sub_API($api_key, $secret_key);
                        $plans_response = $api->get_plans();
                        $selected_plans = get_option('flow_sub_selected_plans', array());
                        if (!is_array($selected_plans)) {
                            $selected_plans = array();
                        }

                        if (is_wp_error($plans_response)) {
                            echo '<div class="notice notice-error"><p>' . esc_html($plans_response->get_error_message()) . '</p></div>';
                        } elseif (isset($plans_response['code']) && 200 !== (int) $plans_response['code']) {
                            echo '<div class="notice notice-error"><p>' . esc_html($plans_response['message'] ?? 'Unknown error') . '</p></div>';
                        } else {
                            $plans = $plans_response['data'] ?? array();
                            if (empty($plans)) {
                                echo '<p>' . esc_html__('No se encontraron planes en Flow.', 'flow-sub') . '</p>';
                            } else {
                                echo '<fieldset>';
                                foreach ($plans as $plan) {
                                    $plan_id = $plan['planId'];
                                    $plan_name = $plan['name'];
                                    $checked = in_array($plan_id, $selected_plans) ? 'checked' : '';
                                    echo '<label style="display: block; margin-bottom: 8px;">';
                                    echo '<input type="checkbox" name="flow_sub_selected_plans[]" value="' . esc_attr($plan_id) . '" ' . $checked . '> ';
                                    echo esc_html($plan_name . ' (' . $plan_id . ')');
                                    echo '</label>';
                                }
                                echo '</fieldset>';
                            }
                        }
                    } else {
                        echo '<p class="description">' . esc_html__('Configura tus credenciales de API primero.', 'flow-sub') . '</p>';
                    }
                    ?>
                    <?php submit_button(); ?>
                </form>
            <?php elseif ('api_tester' === $active_tab): ?>
                <h2><?php esc_html_e('API Tester', 'flow-sub'); ?></h2>
                <?php
                $api_key = get_option('flow_sub_api_key');
                $secret_key = get_option('flow_sub_secret_key');

                if (!$api_key || !$secret_key) {
                    echo '<div class="notice notice-error"><p>' . esc_html(__('Please save your API Key and Secret Key first.', 'flow-sub')) . '</p></div>';
                } else {
                    require_once FLOW_SUB_PATH . 'includes/class-flow-sub-api.php';
                    $api = new Flow_Sub_API($api_key, $secret_key);
                    $response = $api->get_plans();

                    if (is_wp_error($response)) {
                        echo '<div class="notice notice-error"><p>' . esc_html($response->get_error_message()) . '</p></div>';
                    } elseif (isset($response['code']) && 200 !== (int) $response['code']) {
                        echo '<div class="notice notice-error"><p>' . esc_html($response['message'] ?? 'Unknown error') . '</p></div>';
                    } else {
                        $plans = $response['data'] ?? array();
                        if (empty($plans)) {
                            echo '<div class="notice notice-warning"><p>' . esc_html(__('No plans found.', 'flow-sub')) . '</p></div>';
                        } else {
                            ?>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Name', 'flow-sub'); ?></th>
                                        <th><?php esc_html_e('ID', 'flow-sub'); ?></th>
                                        <th><?php esc_html_e('Amount', 'flow-sub'); ?></th>
                                        <th><?php esc_html_e('State', 'flow-sub'); ?></th>
                                        <th><?php esc_html_e('Shortcode', 'flow-sub'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($plans as $plan): ?>
                                        <tr>
                                            <td><?php echo esc_html($plan['name'] ?? '-'); ?></td>
                                            <td><?php echo esc_html($plan['planId'] ?? '-'); ?></td>
                                            <td><?php echo esc_html($plan['amount'] ?? '-'); ?></td>
                                            <td><?php echo esc_html(1 === (int) ($plan['status'] ?? 0) ? 'Active' : 'Inactive'); ?></td>
                                            <td><code>[flow_subscribe plan="<?php echo esc_attr($plan['planId'] ?? ''); ?>"]</code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php
                        }
                    }
                }
                ?>
            <?php elseif ('theme' === $active_tab): ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('flow_sub_theme_options');
                    do_settings_sections('flow_sub_theme_options');
                    ?>
                    <h2><?php esc_html_e('Configuración de Tema', 'flow-sub'); ?></h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e('Imagen de Fondo', 'flow-sub'); ?></th>
                            <td>
                                <?php
                                $background_image_id = get_option('flow_sub_background_image');
                                $background_image_url = $background_image_id ? wp_get_attachment_image_url($background_image_id, 'medium') : '';
                                ?>
                                <div class="flow-sub-image-upload">
                                    <input type="hidden" id="flow_sub_background_image" name="flow_sub_background_image"
                                        value="<?php echo esc_attr($background_image_id); ?>" />
                                    <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                                        <?php if ($background_image_url): ?>
                                            <img id="image-preview" src="<?php echo esc_url($background_image_url); ?>"
                                                style="max-width: 300px; height: auto; display: block; border: 1px solid #ddd; padding: 5px;" />
                                        <?php else: ?>
                                            <img id="image-preview" src=""
                                                style="max-width: 300px; height: auto; display: none; border: 1px solid #ddd; padding: 5px;" />
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button button-secondary" id="upload_image_button">
                                        <?php esc_html_e('Seleccionar Imagen', 'flow-sub'); ?>
                                    </button>
                                    <?php if ($background_image_url): ?>
                                        <button type="button" class="button button-secondary" id="remove_image_button">
                                            <?php esc_html_e('Eliminar Imagen', 'flow-sub'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="button button-secondary" id="remove_image_button"
                                            style="display: none;">
                                            <?php esc_html_e('Eliminar Imagen', 'flow-sub'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <p class="description">
                                        <?php esc_html_e('Esta imagen se mostrará como fondo en el feed de Flow Posts.', 'flow-sub'); ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            <?php elseif ('feed_settings' === $active_tab): ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('flow_sub_feed_options');
                    do_settings_sections('flow_sub_feed_options');
                    ?>
                    <h2><?php esc_html_e('Configuración del Feed', 'flow-sub'); ?></h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e('Título de la Página', 'flow-sub'); ?></th>
                            <td>
                                <?php
                                $current_title = get_option('flow_sub_feed_page_title', 'Feed Miembros');
                                ?>
                                <input type="text" name="flow_sub_feed_page_title" value="<?php echo esc_attr($current_title); ?>"
                                    class="regular-text" placeholder="Feed Miembros" />
                                <p class="description">
                                    <?php esc_html_e('El título que se mostrará en la página del feed.', 'flow-sub'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e('URL Slug del Feed', 'flow-sub'); ?></th>
                            <td>
                                <?php
                                $current_slug = get_option('flow_sub_feed_slug', 'flow-feed');
                                ?>
                                <input type="text" name="flow_sub_feed_slug" value="<?php echo esc_attr($current_slug); ?>"
                                    class="regular-text" placeholder="flow-feed" pattern="[a-z0-9-]+" />
                                <p class="description">
                                    <?php esc_html_e('Personaliza la URL del feed de Flow Posts. Por ejemplo, si ingresas "member-feed", la URL será yoursite.com/member-feed/', 'flow-sub'); ?>
                                    <br>
                                    <strong><?php esc_html_e('Importante:', 'flow-sub'); ?></strong>
                                    <?php esc_html_e('Después de guardar, debes ir a Ajustes → Enlaces permanentes y hacer clic en "Guardar cambios" para aplicar el nuevo slug.', 'flow-sub'); ?>
                                </p>
                                <?php if (isset($_GET['settings-updated'])): ?>
                                    <div class="notice notice-warning inline">
                                        <p>
                                            <strong><?php esc_html_e('¡Recuerda!', 'flow-sub'); ?></strong>
                                            <?php esc_html_e('Visita', 'flow-sub'); ?>
                                            <a
                                                href="<?php echo esc_url(admin_url('options-permalink.php')); ?>"><?php esc_html_e('Ajustes → Enlaces permanentes', 'flow-sub'); ?></a>
                                            <?php esc_html_e('y haz clic en "Guardar cambios" para aplicar el nuevo slug.', 'flow-sub'); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php esc_html_e('Logo del Feed', 'flow-sub'); ?></th>
                            <td>
                                <?php
                                $feed_logo_id = get_option('flow_sub_feed_logo');
                                $feed_logo_url = $feed_logo_id ? wp_get_attachment_image_url($feed_logo_id, 'full') : '';
                                ?>
                                <div class="flow-sub-logo-upload">
                                    <input type="hidden" name="flow_sub_feed_logo" id="flow_sub_feed_logo"
                                        value="<?php echo esc_attr($feed_logo_id); ?>" />
                                    <div id="flow-sub-logo-preview" style="margin-bottom: 10px;">
                                        <?php if ($feed_logo_url): ?>
                                            <img src="<?php echo esc_url($feed_logo_url); ?>"
                                                style="max-width: 200px; height: auto; display: block;" />
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button" id="flow_sub_feed_logo_button">
                                        <?php esc_html_e('Seleccionar Logo', 'flow-sub'); ?>
                                    </button>
                                    <?php if ($feed_logo_id): ?>
                                        <button type="button" class="button" id="flow_sub_feed_logo_remove">
                                            <?php esc_html_e('Remover Logo', 'flow-sub'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <p class="description">
                                        <?php esc_html_e('Logo personalizado que se mostrará solo en la página del feed.', 'flow-sub'); ?>
                                    </p>
                                </div>
                                <script>
                                    jQuery(document).ready(function ($) {
                                        var mediaUploader;

                                        $('#flow_sub_feed_logo_button').click(function (e) {
                                            e.preventDefault();
                                            if (mediaUploader) {
                                                mediaUploader.open();
                                                return;
                                            }
                                            mediaUploader = wp.media({
                                                title: '<?php esc_html_e('Seleccionar Logo', 'flow-sub'); ?>',
                                                button: {
                                                    text: '<?php esc_html_e('Usar este logo', 'flow-sub'); ?>'
                                                },
                                                multiple: false
                                            });
                                            mediaUploader.on('select', function () {
                                                var attachment = mediaUploader.state().get('selection').first().toJSON();
                                                $('#flow_sub_feed_logo').val(attachment.id);
                                                $('#flow-sub-logo-preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto; display: block;" />');
                                                if ($('#flow_sub_feed_logo_remove').length === 0) {
                                                    $('#flow_sub_feed_logo_button').after('<button type="button" class="button" id="flow_sub_feed_logo_remove"><?php esc_html_e('Remover Logo', 'flow-sub'); ?></button>');
                                                }
                                            });
                                            mediaUploader.open();
                                        });

                                        $(document).on('click', '#flow_sub_feed_logo_remove', function (e) {
                                            e.preventDefault();
                                            $('#flow_sub_feed_logo').val('');
                                            $('#flow-sub-logo-preview').html('');
                                            $(this).remove();
                                        });
                                    });
                                </script>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
