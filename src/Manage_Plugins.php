<?php

namespace mklasen\Aether;

/**
 * Manage Plugins
 * 
 * Activates and deactivates plugins for a site or network-wide based on the current environment.
 */
class Manage_Plugins {

	/**
	 * A summary of plugins to be (network-)(de-)activated.
	 *
	 * @var array An array of plugins
	 */
	private $plugins = array(
		'enabled'          => array(),
		'disabled'         => array(),
		'network_enabled'  => array(),
		'network_disabled' => array(),
	);

	/**
	 * Current environment
	 * 
	 * @var string
	 */
	private $current_environment;

	/**
	 * Switcher instance
	 * 
	 * @var Switcher
	 */
	private $switcher;

	/**
	 * Handle the newly created class and it's arguments.
	 *
	 * @param array $plugins An array of plugins directly taken from class initiaton.
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_aether_managed_plugins', array( $this, 'handle_plugin_settings_update' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		$this->switcher = new Switcher();
		$this->current_environment = $this->get_current_environment();
		
		$this->set_plugins();
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts() {
		if ( get_current_screen()->id === 'aether_page_aether-manage-plugins' ) {
			wp_enqueue_script( 'aether-manage-plugins', plugins_url( 'assets/dist/js/main.js', dirname( __FILE__ ) ), array( 'jquery' ), '1.0', true );
		}
	}

	/**
	 * Get current environment from Switcher
	 * 
	 * @return string
	 */
	private function get_current_environment() {
		$current_env = $this->switcher->detect_current_environment();
		return $current_env ? $current_env['label'] : 'production';
	}

	/**
	 * Add the admin menu item
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'aether',
			'Manage Environment Plugins',
			'Plugins',
			'manage_options',
			'aether-manage-plugins',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'aether_manage_plugins', 'aether_managed_plugins', array(
			'sanitize_callback' => array( $this, 'sanitize_plugin_settings' )
		));
	}

	/**
	 * Sanitize plugin settings before saving
	 * 
	 * @param array $input The input array to sanitize
	 * @return array The sanitized input
	 */
	public function sanitize_plugin_settings( $input ) {
		$sanitized = array();
		
		if ( !is_array( $input ) ) {
			return $sanitized;
		}

		foreach ( $input as $plugin_file => $settings ) {
			if ( strpos( $plugin_file, 'aether/' ) === 0 ) {
				continue;
			}

			$sanitized[$plugin_file] = array();

			// Handle enable_on setting
			if ( isset( $settings['enable_on'] ) && is_array( $settings['enable_on'] ) ) {
				$sanitized[$plugin_file]['enable_on'] = array_map('sanitize_text_field', $settings['enable_on']);
			}

			// Handle disable_on setting
			if ( isset( $settings['disable_on'] ) && is_array( $settings['disable_on'] ) ) {
				$sanitized[$plugin_file]['disable_on'] = array_map('sanitize_text_field', $settings['disable_on']);
			}

			// Handle network setting
			if ( isset( $settings['network'] ) ) {
				$sanitized[$plugin_file]['network'] = '1';
			}

			// Handle track_todo setting
			if ( isset( $settings['track_todo'] ) ) {
				$sanitized[$plugin_file]['track_todo'] = '1';
			}
		}

		return $sanitized;
	}

	/**
	 * Handle plugin settings updates
	 * 
	 * @param mixed $old_value The old option value
	 * @param mixed $new_value The new option value
	 */
	public function handle_plugin_settings_update( $old_value, $new_value ) {
		foreach ($new_value as $plugin_name => $args) {
			// Skip if this is the Aether plugin
			if (strpos($plugin_name, 'aether/') === 0) {
				continue;
			}

			if (isset($args['enable_on']) && in_array($this->current_environment, $args['enable_on'])) {
				if (isset($args['network']) && $args['network'] === '1') {
					$this->manage_plugin($plugin_name, 'network_enabled');
				} else {
					$this->manage_plugin($plugin_name, 'enabled');
				}
			}
			if (isset($args['disable_on']) && in_array($this->current_environment, $args['disable_on'])) {
				if (isset($args['network']) && $args['network'] === '1') {
					$this->manage_plugin($plugin_name, 'network_disabled');
				} else {
					$this->manage_plugin($plugin_name, 'disabled');
				}
			}
		}
	}

	/**
	 * Render the admin page
	 */
	public function render_admin_page() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$saved_plugins = get_option( 'aether_managed_plugins', array() );
		$options = get_option('aether_switch_options');
		$environments = isset($options['environments']) ? array_map(function($env) { return $env['label']; }, $options['environments']) : array('production');

		?>
		<div class="wrap">
			<h1>Manage Environment Plugins</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'aether_manage_plugins' ); ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Plugin</th>
							<th>Enable On</th>
							<th>Disable On</th>
							<?php if (is_multisite()) : ?>
								<th>Network Wide</th>
							<?php endif; ?>
							<th>Track Todo</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_plugins as $plugin_file => $plugin_data ) : 
							// Skip if this is the Aether plugin
							if (strpos($plugin_file, 'aether/') === 0) {
								continue;
							}
							$plugin_settings = isset( $saved_plugins[$plugin_file] ) ? $saved_plugins[$plugin_file] : array();
							$is_network_active = is_multisite() && is_plugin_active_for_network($plugin_file);
							$is_active = is_plugin_active($plugin_file);
							?>
							<tr>
								<td><?php echo esc_html( $plugin_data['Name'] ); ?></td>
								<td>
									<?php foreach ( $environments as $env ) : ?>
										<label>
											<input type="checkbox" 
												class="enable-checkbox"
												data-plugin="<?php echo esc_attr( $plugin_file ); ?>"
												data-env="<?php echo esc_attr( $env ); ?>"
												name="aether_managed_plugins[<?php echo esc_attr( $plugin_file ); ?>][enable_on][]" 
												value="<?php echo esc_attr( $env ); ?>"
												<?php checked( isset( $plugin_settings['enable_on'] ) && in_array($env, (array)$plugin_settings['enable_on']) ); ?>>
											<?php echo esc_html( ucfirst( $env ) ); ?>
										</label><br>
									<?php endforeach; ?>
								</td>
								<td>
									<?php foreach ( $environments as $env ) : ?>
										<label>
											<input type="checkbox" 
												class="disable-checkbox"
												data-plugin="<?php echo esc_attr( $plugin_file ); ?>"
												data-env="<?php echo esc_attr( $env ); ?>"
												name="aether_managed_plugins[<?php echo esc_attr( $plugin_file ); ?>][disable_on][]" 
												value="<?php echo esc_attr( $env ); ?>"
												<?php checked( isset( $plugin_settings['disable_on'] ) && in_array($env, (array)$plugin_settings['disable_on']) ); ?>>
											<?php echo esc_html( ucfirst( $env ) ); ?>
										</label><br>
									<?php endforeach; ?>
								</td>
								<?php if (is_multisite()) : ?>
								<td>
									<input type="checkbox" 
										name="aether_managed_plugins[<?php echo esc_attr( $plugin_file ); ?>][network]" 
										value="1" 
											<?php checked( isset( $plugin_settings['network'] ) ? $plugin_settings['network'] : false, true ); ?>>
									</td>
								<?php endif; ?>
								<td>
									<input type="checkbox"
										name="aether_managed_plugins[<?php echo esc_attr( $plugin_file ); ?>][track_todo]"
										value="1"
										<?php checked( isset( $plugin_settings['track_todo'] ) ? $plugin_settings['track_todo'] : false, true ); ?>>
								</td>
								<td>
									<?php 
									if (is_multisite() && $is_network_active) {
										echo '<span style="color: green;">Network Active</span>';
									} elseif ($is_active) {
										echo '<span style="color: green;">Active</span>';
									} else {
										echo '<span style="color: red;">Inactive</span>';
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Defines the filters that will be used to manage the plugins.
	 *
	 * @return void
	 */
	public function set_plugins() {
		add_action('admin_init', array($this, 'manage_plugins'));
		add_action('admin_init', array($this, 'manage_network_plugins'));

		// Process existing settings on load
		$saved_plugins = get_option('aether_managed_plugins', array());

		foreach ($saved_plugins as $plugin_name => $args) {
			// Skip if this is the Aether plugin
			if (strpos($plugin_name, 'aether/') === 0) {
				continue;
			}


			if (isset($args['enable_on']) && in_array($this->current_environment, (array)$args['enable_on'])) {
				if (isset($args['network']) && $args['network'] === '1') {
					$this->manage_plugin($plugin_name, 'network_enabled');
				} else {
					$this->manage_plugin($plugin_name, 'enabled');
				}
			}
			if (isset($args['disable_on']) && in_array($this->current_environment, (array)$args['disable_on'])) {
				if (isset($args['network']) && $args['network'] === '1') {
					$this->manage_plugin($plugin_name, 'network_disabled');
				} else {
					$this->manage_plugin($plugin_name, 'disabled');
				}
			}
		}
	}

	/**
	 * Build the array of plugins that will be (network-)(de-)activated.
	 *
	 * @param string $plugin_name The name and file of the plugin.
	 * @param string $type of action: enabled, disabled, network_enabled, network_disabled.
	 * @return void
	 */
	public function manage_plugin( $plugin_name, $type ) {
		// Skip if this is the Aether plugin
		if (strpos($plugin_name, 'aether/') === 0) {
			return;
		}
		$this->plugins[ $type ][] = $plugin_name;
	}

	/**
	 * Activate or deactivate non-network plugins.
	 */
	public function manage_plugins() {
		if (!function_exists('is_plugin_active')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ($this->plugins['enabled'] as $plugin) {
			if (!is_plugin_active($plugin)) {
				activate_plugin($plugin);
			}
		}

		foreach ($this->plugins['disabled'] as $plugin) {
			if (is_plugin_active($plugin)) {
				deactivate_plugins($plugin);
			}
		}
	}

	/**
	 * Activate or deactivate network plugins.
	 */
	public function manage_network_plugins() {
		if (!is_multisite()) {
			return;
		}

		if (!function_exists('is_plugin_active_for_network')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ($this->plugins['network_enabled'] as $plugin) {
			if (!is_plugin_active_for_network($plugin)) {
				activate_plugin($plugin, '', true);
			}
		}

		foreach ($this->plugins['network_disabled'] as $plugin) {
			if (is_plugin_active_for_network($plugin)) {
				deactivate_plugins($plugin, true);
			}
		}
	}
}
