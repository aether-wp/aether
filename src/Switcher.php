<?php

namespace mklasen\Aether;

class Switcher
{
	private $current_environment;

	public function __construct()
	{
		$this->detect_current_environment();
		$this->hooks();
	}

	public function hooks()
	{
		add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 100);
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_admin_styles'));
	}

	public function enqueue_admin_styles()
	{
		wp_enqueue_style('aether-styles', plugins_url('assets/dist/css/style.css', dirname(__FILE__)));
		$current_color = $this->current_environment ? $this->current_environment['color'] : '#FFFFFF';
		wp_add_inline_style('aether-styles', sprintf(':root { --aether-switcher-current-color: %s; }', $current_color));
	}

	public function detect_current_environment()
	{
		$options = get_option('aether_switch_options');
		$environments = isset($options['environments']) ? $options['environments'] : array();
		$current_url = get_site_url();

		foreach ($environments as $env) {
			if (trailingslashit($env['url']) === trailingslashit($current_url)) {
				$this->current_environment = $env;
				return $env;
				break;
			}
		}
	}

	public function add_toolbar_items($admin_bar)
	{
		$options = get_option('aether_switch_options');
		$environments = isset($options['environments']) ? $options['environments'] : array();

		if (!empty($environments)) {
			$admin_bar->add_menu(array(
				'id'    => 'aether-switch',
				'title' => 'Switch Environment',
				'href'  => '#',
				'meta'  => array(
					'title' => __('Switch Environment'),
				),
			));

			// Get current path and query string
			$current_path = $_SERVER['REQUEST_URI'];

			foreach ($environments as $index => $env) {
				$is_current = $this->current_environment && $this->current_environment['url'] === $env['url'];
				
				// Build target URL with same path
				$target_url = trailingslashit($env['url']) . ltrim($current_path, '/');

				$admin_bar->add_menu(array(
					'id'     => 'aether-switch-env-' . $index,
					'parent' => 'aether-switch',
					'title'  => $env['label'] . ($is_current ? ' (Current)' : ''),
					'href'   => $target_url,
					'meta'   => array(
						'title' => __('Switch to ') . $env['label'],
						'class' => 'aether-switch-env' . ($is_current ? ' current-env' : ''),
						'style' => 'background-color: ' . esc_attr($env['color']) . ';',
					),
				));
			}
		}
	}
}
