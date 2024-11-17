<?php

namespace mklasen\Aether;

class Settings
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings')); 
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function enqueue_admin_styles($hook)
    {
        if (strpos($hook, 'aether') !== false) {
            wp_enqueue_style('aether-admin', plugins_url('assets/dist/css/admin.css', dirname(__FILE__)));
            wp_enqueue_script('jquery');
        }
    }

    public function add_admin_menu()
    {
        $icon = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 4L8 2H2V8L4 10L2 12V18H8L10 16L12 18H18V12L16 10L18 8V2H12L10 4ZM10 13.17L7.41 15.76L6 14.34L8.59 11.75L6 9.16L7.41 7.75L10 10.34L12.59 7.75L14 9.16L11.41 11.75L14 14.34L12.59 15.76L10 13.17Z" fill="black"/></svg>';

        add_menu_page('Aether', 'Aether', 'manage_options', 'aether', array($this, 'render_aether_page'), 'data:image/svg+xml;base64,' . base64_encode($icon));
    }

    public function render_aether_page()
    {
?>
        <div class="wrap aether-settings">
            <div class="aether-content">
                <div class="aether-header aether-section">
                    <h1><span class="dashicons dashicons-admin-settings"></span> Aether Settings</h1>
                </div>

                <form id="aether-settings-form">
                    <?php wp_nonce_field('wp_rest', '_wpnonce'); ?>

                    <div class="aether-sections">
                        <?php $this->render_section('Environment Switcher', 'admin-site', 'aether-switch'); ?>
                        <?php $this->render_section('Mail Settings', 'email', 'aether-mail'); ?>
                        <?php $this->render_section('Editor Settings', 'editor-code', 'aether-editor'); ?>
                    </div>
                </form>
            </div>
        </div>
    <?php
    }

    private function render_section($title, $icon, $section)
    {
    ?>
        <div class="aether-section">
            <div class="aether-section-header">
                <h2><span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span> <?php echo esc_html($title); ?></h2>
            </div>
            <div class="aether-section-content">
                <?php do_settings_sections($section); ?>
            </div>
        </div>
    <?php
    }

    public function register_settings()
    {
        register_setting('aether_options', 'aether_switch_options', array($this, 'sanitize_options'));
        register_setting('aether_options', 'aether_herd_enabled', array(
            'type' => 'boolean',
            'default' => false
        ));
        register_setting('aether_options', 'aether_disable_email', array(
            'type' => 'boolean',
            'default' => false
        ));

        $this->add_settings_sections();
    }

    private function add_settings_sections()
    {
        add_settings_section('aether_switch_section', '', array($this, 'render_section_info'), 'aether-switch');
        add_settings_field('aether_switch_environments', 'Environments', array($this, 'render_environments_field'), 'aether-switch', 'aether_switch_section');

        add_settings_section('aether_mail_section', '', array($this, 'render_mail_section_info'), 'aether-mail');
        add_settings_field('aether_herd_enabled', 'Enable Herd Integration', array($this, 'render_toggle_field'), 'aether-mail', 'aether_mail_section', array('option' => 'aether_herd_enabled'));
        add_settings_field('aether_disable_email', 'Disable Email', array($this, 'render_toggle_field'), 'aether-mail', 'aether_mail_section', array('option' => 'aether_disable_email'));
        add_settings_field('aether_test_email', 'Test Email', array($this, 'render_test_email_button'), 'aether-mail', 'aether_mail_section');

        add_settings_section('aether_editor_section', '', array($this, 'render_editor_section_info'), 'aether-editor');
        add_settings_field('aether_clear_editor', 'Clear Editor Preference', array($this, 'render_clear_editor_button'), 'aether-editor', 'aether_editor_section');
    }

    public function render_section_info()
    {
        echo '<p class="description">Configure and manage your different environments below.</p>';
    }

    public function render_mail_section_info()
    {
        echo '<p class="description">Configure email and Herd integration settings.</p>';
    }

    public function render_editor_section_info() 
    {
        echo '<p class="description">Manage your editor preferences.</p>';
    }

    public function render_test_email_button()
    {
        ?>
        <button type="button" id="send-test-email" class="button button-secondary">
            Send Test Email
        </button>
        <script>
            jQuery(document).ready(function($) {
                $('#send-test-email').on('click', function() {
                    $.ajax({
                        url: '<?php echo esc_url_raw(rest_url('aether/v1/test-email')); ?>',
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', $('#_wpnonce').val());
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Test email sent successfully');
                            } else {
                                alert('Failed to send test email');
                            }
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function render_clear_editor_button()
    {
        ?>
        <button type="button" id="clear-editor-preference" class="button button-secondary">
            Clear Editor Preference
        </button>
        <script>
            jQuery(document).ready(function($) {
                $('#clear-editor-preference').on('click', function() {
                    $.ajax({
                        url: '<?php echo esc_url_raw(rest_url('aether/v1/clear-editor')); ?>',
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', $('#_wpnonce').val());
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Editor preference cleared successfully');
                            }
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function render_toggle_field($args)
    {
        $enabled = get_option($args['option'], false);
    ?>
        <label class="aether-toggle">
            <input type="checkbox" class="auto-save" name="<?php echo esc_attr($args['option']); ?>" value="1" <?php checked(1, $enabled); ?> />
            <span class="aether-toggle-slider"></span>
        </label>
    <?php
    }

    public function render_environments_field()
    {
        $options = get_option('aether_switch_options');
        $environments = isset($options['environments']) ? $options['environments'] : array();
        $current_url = get_site_url();
    ?>
        <div id="environment-list" class="aether-environments">
            <?php foreach ($environments as $index => $env): ?>
                <?php $this->render_environment_entry($index, $env); ?>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-environment" class="button button-secondary">
            <span class="dashicons dashicons-plus-alt2"></span> Add Environment
        </button>
    <?php
        $this->render_environments_script($current_url);
    }

    private function render_environment_entry($index, $env)
    {
    ?>
        <div class="environment-entry">
            <div class="environment-inputs">
                <div class="aether-environment-input">
                    <input type="text" class="regular-text auto-save" name="aether_switch_options[environments][<?php echo $index; ?>][label]" value="<?php echo esc_attr($env['label']); ?>" placeholder="Environment Label" />
                    <input type="text" class="regular-text auto-save" name="aether_switch_options[environments][<?php echo $index; ?>][url]" value="<?php echo esc_attr($env['url']); ?>" placeholder="Environment URL" />
                </div>
                <input type="color" class="aether-color-picker auto-save" name="aether_switch_options[environments][<?php echo $index; ?>][color]" value="<?php echo esc_attr($env['color'] ?? '#FFFFFF'); ?>" />
                <div class="aether-enable-media">
                    <label class="aether-toggle">
                        <input type="checkbox" class="auto-save" name="aether_switch_options[environments][<?php echo $index; ?>][load_media]" value="1" <?php checked(1, isset($env['load_media']) ? $env['load_media'] : false); ?> />
                        <span class="aether-toggle-slider"></span>
                    </label>
                    <span class="aether-toggle-text">Load media from this environment</span>
                </div>
                <button type="button" class="button button-secondary remove-environment">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
    <?php
    }

    private function render_environments_script($current_url)
    {
    ?>
        <script>
            jQuery(document).ready(function($) {
                var currentUrl = '<?php echo esc_js($current_url); ?>';
                var saveTimeout;

                function saveSettings() {
                    var formData = $('#aether-settings-form').serialize();
                    $.ajax({
                        url: '<?php echo esc_url_raw(rest_url('aether/v1/settings')); ?>',
                        method: 'POST',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', $('#_wpnonce').val());
                        },
                        data: formData,
                        success: function(response) {
                            if (!response.success) {
                                console.error('Error saving settings');
                            }
                        },
                        error: function() {
                            console.error('Error saving settings');
                        }
                    });
                }

                function debouncedSave() {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(saveSettings, 500);
                }

                $('#aether-settings-form').on('change', '.auto-save', debouncedSave);

                $('#add-environment').on('click', function() {
                    var index = $('#environment-list .environment-entry').length;
                    var template = `
                        <div class="environment-entry">
                            <div class="environment-inputs">
                                <input type="text" class="regular-text auto-save" name="aether_switch_options[environments][${index}][label]" value="" placeholder="Environment Label" />
                                <input type="text" class="regular-text auto-save" name="aether_switch_options[environments][${index}][url]" value="${currentUrl}" placeholder="Environment URL" />
                                <input type="color" class="aether-color-picker auto-save" name="aether_switch_options[environments][${index}][color]" value="#FFFFFF" />
                                <label class="aether-toggle">
                                    <input type="checkbox" class="auto-save" name="aether_switch_options[environments][${index}][load_media]" value="1" />
                                    <span class="aether-toggle-slider"></span>
                                    Load media from this environment
                                </label>
                                <button type="button" class="button button-secondary remove-environment">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    `;
                    $('#environment-list').append(template);
                    debouncedSave();
                });

                $('#environment-list').on('click', '.remove-environment', function() {
                    $(this).closest('.environment-entry').fadeOut(300, function() {
                        $(this).remove();
                        debouncedSave();
                    });
                });
            });
        </script>
<?php
    }

    public function register_rest_routes() {
        register_rest_route('aether/v1', '/settings', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_settings_update'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));

        register_rest_route('aether/v1', '/clear-editor', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_clear_editor'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));

        register_rest_route('aether/v1', '/test-email', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_test_email'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));
    }

    public function handle_test_email() {
        $to = wp_get_current_user()->user_email;
        $subject = 'Aether Test Email';
        $message = 'This is a test email sent from Aether.';
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $sent = wp_mail($to, $subject, $message, $headers);

        return rest_ensure_response(array('success' => $sent));
    }

    public function handle_clear_editor() {
        $user_id = get_current_user_id();
        delete_user_meta($user_id, 'aether_default_editor');
        return rest_ensure_response(array('success' => true));
    }

    public function handle_settings_update($request) {
        $settings = array();
        parse_str($request->get_body(), $settings);
        
        if (isset($settings['aether_switch_options'])) {
            update_option('aether_switch_options', $this->sanitize_options($settings['aether_switch_options']));
        }
        
        // Handle boolean options properly by setting to false if not present in request
        update_option('aether_herd_enabled', isset($settings['aether_herd_enabled']) ? true : false);
        update_option('aether_disable_email', isset($settings['aether_disable_email']) ? true : false);

        return rest_ensure_response(array('success' => true));
    }

    public function sanitize_options($input)
    {
        $new_input = array();
        if (isset($input['environments']) && is_array($input['environments'])) {
            foreach ($input['environments'] as $env) {
                if (!empty($env['label']) && !empty($env['url'])) {
                    $new_input['environments'][] = array(
                        'label' => sanitize_text_field($env['label']),
                        'url' => esc_url_raw($env['url']),
                        'color' => sanitize_hex_color($env['color']),
                        'load_media' => boolval($env['load_media'])
                    );
                }
            }
        }
        return $new_input;
    }
}
