<?php
/**
 * Show_Hooks class for displaying WordPress hooks and filters
 *
 * @package           Aether
 * @author            mklasen
 * @copyright         2023 mklasen
 * @license           GPL-2.0-or-later
 */

namespace mklasen\Aether;

defined('ABSPATH') or die('No direct script access allowed');

class Show_Hooks {
    private $show_hooks = false;
    private $hook_info = array();
    private $ignore_hooks = array();
    private $hook_container = '';
    private $cookie_name = 'aether_show_hooks';

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize ignore hooks
        $this->ignore_hooks = apply_filters('aether_show_hooks_ignore', array(
            'attribute_escape',
            'body_class',
            'the_post',
            'post_edit_form_tag',
            'plugin_loaded',
            'parse_tax_query',
            'parse_query',
            'pre_get_posts',
            'posts_selection',
        ));

        // Add admin bar menu
        add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 100);

        add_action('wp_footer', array($this, 'render_hook_container'), 0);

        // Add styles
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        }

        // Check URL parameters and set cookie
        if (isset($_GET['aether-show-hooks'])) {
            $this->show_hooks = $_GET['aether-show-hooks'] === '1';
            setcookie($this->cookie_name, $this->show_hooks ? '1' : '0', time() + (86400 * 30), '/'); // 30 days
        } else if (isset($_COOKIE[$this->cookie_name])) {
            $this->show_hooks = $_COOKIE[$this->cookie_name] === '1';
        }

        // Hook into 'all' if showing hooks
        if ($this->show_hooks) {
            add_action('all', array($this, 'gather_hook_info'), -999999);
            add_action('all', array($this, 'show_hook'), 999999);
        }
    }

    public function render_hook_container() {
        echo '<div id="aether-hooks-container">';
        echo $this->hook_container;
        echo '</div>';
    }

    /**
     * Enqueue required styles
     */
    public function enqueue_styles() {
        if ($this->show_hooks) {
            wp_add_inline_style('admin-bar', '
                body {
                    margin-top: 332px;
                }
                #aether-hooks-container {
                    position: absolute;
                    top: 32px;
                    right: 0;
                    height: 300px;
                    overflow-y: auto;
                    width: 100%;
                }
                .aether-hook-container {
                    position: relative;
                    display: block;
                    width: 100%;
                    height: 20px;
                    margin: 5px 0;
                    overflow: visible;
                }
                .aether-hook-label {
                    position: absolute;
                    padding: 2px 5px;
                    margin: 2px 0;
                    background: rgba(241, 241, 241, 0.3);
                    border: 1px solid rgba(204, 204, 204, 0.3);
                    border-radius: 3px;
                    color: rgba(51, 51, 51, 0.3);
                    font-family: monospace;
                    font-size: 12px;
                    z-index: 9998;
                    pointer-events: auto;
                    transition: all 0.2s ease;
                    left: 0;
                }
                .aether-hook-label:hover {
                    background: rgba(241, 241, 241, 1);
                    border: 1px solid rgba(204, 204, 204, 1);
                    color: rgba(51, 51, 51, 1);
                }
                .aether-hook-info {
                    display: none;
                    position: absolute;
                    left: 100%;
                    top: 0;
                    margin-left: 5px;
                    background: #fff;
                    border: 1px solid #ccc;
                    border-radius: 3px;
                    padding: 5px;
                    min-width: 200px;
                    z-index: 9999;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                }
                .aether-hook-label:hover .aether-hook-info {
                    display: block;
                }
                .aether-hook-callback {
                    display: block;
                    padding: 2px 0;
                    border-bottom: 1px solid #eee;
                }
                .aether-hook-callback:last-child {
                    border-bottom: none;
                }
            ');
        }
    }

    /**
     * Add toolbar menu items
     */
    public function add_toolbar_items($admin_bar) {
        $admin_bar->add_menu(array(
            'id'    => 'aether-hooks',
            'title' => '<span class="ab-icon"></span><span class="ab-label">' . __('Show Hooks', 'aether') . '</span>',
            'href'  => '#',
        ));

        $admin_bar->add_menu(array(
            'id'     => 'aether-show-hooks',
            'parent' => 'aether-hooks',
            'title'  => $this->show_hooks ? __('Hide Actions', 'aether') : __('Show Actions', 'aether'),
            'href'   => add_query_arg('aether-show-hooks', $this->show_hooks ? '0' : '1'),
            'meta'   => array(
                'title' => $this->show_hooks ? __('Hide Actions', 'aether') : __('Show Actions', 'aether'),
            ),
        ));
    }

    /**
     * Gather information about current hook
     */
    public function gather_hook_info() {
        $current_hook = current_filter();
        if (!isset($this->hook_info[$current_hook]) && did_action($current_hook)) {
            global $wp_filter;
            $this->hook_info[$current_hook] = array(
                'type' => 'action',
                'callbacks' => array()
            );

            if (isset($wp_filter[$current_hook])) {
                foreach ($wp_filter[$current_hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        $callback_name = $this->get_callback_name($callback['function']);
                        $this->hook_info[$current_hook]['callbacks'][] = array(
                            'priority' => $priority,
                            'callback' => $callback_name
                        );
                    }
                }
            }
        }
    }

    /**
     * Get callback name
     */
    private function get_callback_name($callback) {
        if (is_string($callback)) {
            return $callback;
        } elseif (is_array($callback)) {
            if (is_object($callback[0])) {
                return get_class($callback[0]) . '->' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        }
        return 'closure';
    }

    /**
     * Display hook information
     */
    public function show_hook() {
        $current_hook = current_filter();

        ob_start();
        
        if (!defined('DOING_AJAX') && did_action($current_hook) && !in_array($current_hook, $this->ignore_hooks) && $this->hook_info[$current_hook]['type'] === 'action') {
            $callback_count = !empty($this->hook_info[$current_hook]['callbacks']) ? count($this->hook_info[$current_hook]['callbacks']) : 0;
            
            echo '<span class="aether-hook-container">';
            echo '<span class="aether-hook-label">';
            echo esc_html($current_hook) . ' (' . $callback_count . ')';
            if (!empty($this->hook_info[$current_hook])) {
                echo '<span class="aether-hook-info">';
                foreach ($this->hook_info[$current_hook]['callbacks'] as $info) {
                    echo '<span class="aether-hook-callback">';
                    printf('%s (Priority: %d)', esc_html($info['callback']), $info['priority']);
                    echo '</span>';
                }
                echo '</span>';
            }
            echo '</span>';
            echo '</span>';
        }

        // Naahh, separating top hooks and later ones didn't work.
        echo ob_get_clean();

        // if (!did_action('wp_before_admin_bar_render')) {
        //     $this->hook_container .= ob_get_clean();
        // } else {
        //     echo ob_get_clean();
        // }
    }
}
