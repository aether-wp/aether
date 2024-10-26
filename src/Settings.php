<?php

namespace mklasen\Aether;

class Settings
{

    public function __construct()
    {
        $this->hooks();
    }

    public function hooks()
    {

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu()
    {
        $icon_svg = '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 4L8 2H2V8L4 10L2 12V18H8L10 16L12 18H18V12L16 10L18 8V2H12L10 4ZM10 13.17L7.41 15.76L6 14.34L8.59 11.75L6 9.16L7.41 7.75L10 10.34L12.59 7.75L14 9.16L11.41 11.75L14 14.34L12.59 15.76L10 13.17Z" fill="black"/>
        </svg>';

        add_menu_page(
            'Aether',
            'Aether',
            'manage_options',
            'aether',
            array($this, 'render_aether_page'),
            'data:image/svg+xml;base64,' . base64_encode($icon_svg)
        );
    }

    public function render_aether_page()
    {
?>
        <div class="wrap">
            <h1>Aether</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('aether_options');
                ?>
                <?php
                do_settings_sections('aether-switch');
                ?>
                <?php
                do_settings_sections('aether-mail');
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }

    public function register_settings()
    {
        register_setting('aether_options', 'aether_switch_options', array($this, 'sanitize_options'));
        register_setting('aether_options', 'aether_herd_enabled');
        register_setting('aether_options', 'aether_disable_email');

        add_settings_section('aether_switch_section', 'Environment Switcher Settings', array($this, 'render_section_info'), 'aether-switch');
        add_settings_field('aether_switch_environments', 'Environments', array($this, 'render_environments_field'), 'aether-switch', 'aether_switch_section');

        add_settings_section('aether_mail_section', 'Settings', array($this, 'render_mail_section_info'), 'aether-mail');
        add_settings_field('aether_herd_enabled', 'Enable Herd Integration', array($this, 'render_herd_enabled_field'), 'aether-mail', 'aether_mail_section');
        add_settings_field('aether_disable_email', 'Disable Email', array($this, 'render_disable_email_field'), 'aether-mail', 'aether_mail_section');
    }

    public function render_section_info()
    {
        echo '<p>Manage your different environments.</p>';
    }

    public function render_mail_section_info()
    {
        echo '<p>Configure aether settings.</p>';
    }

    public function render_herd_enabled_field()
    {
        $enabled = get_option('aether_herd_enabled', false);
        echo '<input type="checkbox" name="aether_herd_enabled" value="1" ' . checked(1, $enabled, false) . '/>';
    }

    public function render_disable_email_field()
    {
        $disabled = get_option('aether_disable_email', true);
        echo '<input type="checkbox" name="aether_disable_email" value="1" ' . checked(1, $disabled, false) . '/>';
    }

    public function render_environments_field()
    {
        $options = get_option('aether_switch_options');
        $environments = isset($options['environments']) ? $options['environments'] : array();
        $current_url = get_site_url();
    ?>
        <div id="environment-list">
            <?php foreach ($environments as $index => $env): ?>
                <div class="environment-entry">
                    <input type="text" name="aether_switch_options[environments][<?php echo $index; ?>][label]" value="<?php echo esc_attr($env['label']); ?>" placeholder="Environment Label" />
                    <input type="text" name="aether_switch_options[environments][<?php echo $index; ?>][url]" value="<?php echo esc_attr($env['url']); ?>" placeholder="Environment URL" />
                    <input type="color" name="aether_switch_options[environments][<?php echo $index; ?>][color]" value="<?php echo esc_attr($env['color'] ?? '#FFFFFF'); ?>" />
                    <button type="button" class="remove-environment">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-environment">Add Environment</button>
        <script>
            jQuery(document).ready(function($) {
                var currentUrl = '<?php echo esc_js($current_url); ?>';
                $('#add-environment').on('click', function() {
                    var index = $('#environment-list .environment-entry').length;
                    $('#environment-list').append('<div class="environment-entry">' +
                        '<input type="text" name="aether_switch_options[environments][' + index + '][label]" value="" placeholder="Environment Label" />' +
                        '<input type="text" name="aether_switch_options[environments][' + index + '][url]" value="' + currentUrl + '" placeholder="Environment URL" />' +
                        '<input type="color" name="aether_switch_options[environments][' + index + '][color]" value="#FFFFFF" />' +
                        '<button type="button" class="remove-environment">Remove</button>' +
                        '</div>');
                });
                $('#environment-list').on('click', '.remove-environment', function() {
                    $(this).parent().remove();
                });
            });
        </script>
<?php
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
                        'color' => sanitize_hex_color($env['color'])
                    );
                }
            }
        }
        return $new_input;
    }
}
