<?php

/**
 * Show_Hooks class for displaying WordPress hooks and filters
 * 
 * Provides visual debugging to inspect hooks and actions during page load.
 * Shows hooks inline after <body> tag and in a side panel for pre-body hooks.
 *
 * Features:
 * - Toggle via admin bar
 * - Persists preference via cookie 
 * - Shows hook details, callbacks, timing and memory usage
 * - File locations and line numbers
 * - Configurable ignore list
 */

namespace mklasen\Aether;

defined('ABSPATH') or die('No direct script access allowed');

class Show_Hooks
{
    private $show_hooks = false;
    private $hook_storage = array();
    private $ignore_hooks = array(); 
    private $cookie_name = 'aether_show_hooks';
    private $rendered_hooks = array();
    private $body_started = false;
    private $pre_body_hooks = array();
    private $execution_times = array();
    private $last_hook_start = 0;
    private $memory_usage = array();
    private $hook_order = array();
    private $hook_counter = 0;
    private $hook_instance_counts = array();

    /**
     * Initialize hook display functionality
     */
    public function __construct()
    {
        add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 100);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));

        if (isset($_GET['aether-show-hooks'])) {
            $this->show_hooks = $_GET['aether-show-hooks'] === '1';
            setcookie($this->cookie_name, $this->show_hooks ? '1' : '0', time() + (86400 * 30), '/');
        } else if (isset($_COOKIE[$this->cookie_name])) {
            $this->show_hooks = $_COOKIE[$this->cookie_name] === '1';
        }

        if ($this->show_hooks) {
            add_action('all', array($this, 'pre_hook_callback'), -999999999);
            add_action('all', array($this, 'capture_hook'), -9999999);
            add_action('all', array($this, 'post_hook_callback'), 999999999);
            add_action('all', array($this, 'maybe_render_hook'), 9999999);
            add_action('wp_footer', array($this, 'render_hooks'), 9999999);
            add_action('admin_footer', array($this, 'render_hooks'), 9999999);
            add_action('wp_body_open', array($this, 'set_body_started'), 1);
        }
    }

    public function pre_hook_callback()
    {
        $hook = current_filter();
        $this->last_hook_start = microtime(true);
        $this->memory_usage[$hook] = memory_get_usage();
        $this->hook_order[$hook] = ++$this->hook_counter;
    }

    public function post_hook_callback()
    {
        $hook = current_filter();
        if ($this->last_hook_start > 0) {
            $this->execution_times[$hook] = microtime(true) - $this->last_hook_start;
            $this->memory_usage[$hook] = memory_get_usage() - $this->memory_usage[$hook];
        }
    }

    public function set_body_started()
    {
        $this->body_started = true;
        $this->pre_body_hooks = $this->hook_storage;
        $this->hook_storage = array();
    }

    public function enqueue_styles()
    {
        if ($this->show_hooks) {
            wp_enqueue_style('aether-admin', plugins_url('assets/dist/css/style.css', dirname(__FILE__)));
        }
    }

    public function add_toolbar_items($admin_bar)
    {
        $admin_bar->add_menu(array(
            'id'    => 'aether-hooks',
            'title' => '<span class="ab-icon dashicons dashicons-visibility"></span><span class="ab-label">' . __('Show Hooks', 'aether') . '</span>',
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

    public function capture_hook()
    {
        $current_hook = current_filter();
        if (!isset($this->hook_storage[$current_hook]) && did_action($current_hook)) {
            global $wp_filter;

            if (defined('DOING_AJAX') || in_array($current_hook, $this->ignore_hooks)) {
                return;
            }

            $do_action_location = $this->get_do_action_location();
            $callbacks = $this->get_hook_callbacks($current_hook, $wp_filter);

            $this->hook_storage[$current_hook] = array(
                'type' => 'action',
                'callbacks' => $callbacks,
                'callback_count' => count($callbacks),
                'execution_order' => $this->hook_order[$current_hook] ?? 0,
                'do_action_file' => $do_action_location['file'],
                'do_action_line' => $do_action_location['line']
            );
        }
    }

    private function get_do_action_location()
    {
        $location = array('file' => '', 'line' => '');
        
        if (function_exists('debug_backtrace')) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            foreach ($trace as $item) {
                if (!empty($item['function']) && in_array($item['function'], array('do_action', 'apply_filters'))) {
                    $location = array(
                        'file' => $item['file'] ?? '',
                        'line' => $item['line'] ?? ''
                    );
                    break;
                }
            }
        }
        
        return $location;
    }

    private function get_hook_callbacks($hook, $wp_filter)
    {
        $callbacks = array();
        
        if (isset($wp_filter[$hook])) {
            foreach ($wp_filter[$hook]->callbacks as $priority => $hook_callbacks) {
                foreach ($hook_callbacks as $callback) {
                    $callback_details = $this->get_callback_details($callback['function']);
                    $registration_location = $this->get_registration_location();
                    
                    $callbacks[] = array(
                        'priority' => $priority,
                        'callback' => $callback_details['name'],
                        'file' => $callback_details['file'],
                        'line' => $callback_details['line'],
                        'registered_file' => $registration_location['file'],
                        'registered_line' => $registration_location['line']
                    );
                }
            }
        }
        
        return $callbacks;
    }

    private function get_callback_details($callback)
    {
        $details = array(
            'name' => '',
            'file' => '',
            'line' => ''
        );

        try {
            if (is_string($callback)) {
                $details['name'] = $callback;
                if (function_exists($callback)) {
                    $ref = new \ReflectionFunction($callback);
                    $details['file'] = $ref->getFileName();
                    $details['line'] = $ref->getStartLine();
                }
            } elseif (is_array($callback)) {
                if (is_object($callback[0])) {
                    $details['name'] = get_class($callback[0]) . '->' . $callback[1];
                    $ref = new \ReflectionMethod(get_class($callback[0]), $callback[1]);
                } else {
                    $details['name'] = $callback[0] . '::' . $callback[1];
                    $ref = new \ReflectionMethod($callback[0], $callback[1]);
                }
                $details['file'] = $ref->getFileName();
                $details['line'] = $ref->getStartLine();
            } elseif ($callback instanceof \Closure) {
                $details['name'] = 'closure';
                $ref = new \ReflectionFunction($callback);
                $details['file'] = $ref->getFileName();
                $details['line'] = $ref->getStartLine();
            }
        } catch (\Exception $e) {
            // Silently fail if reflection fails
        }

        return $details;
    }

    private function get_registration_location()
    {
        $location = array('file' => '', 'line' => '');
        
        if (function_exists('debug_backtrace')) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            foreach ($trace as $item) {
                if (!empty($item['function']) && in_array($item['function'], array('add_action', 'add_filter'))) {
                    $location = array(
                        'file' => $item['file'] ?? '',
                        'line' => $item['line'] ?? ''
                    );
                    break;
                }
            }
        }
        
        return $location;
    }

    public function maybe_render_hook()
    {
        if (!$this->body_started) {
            return;
        }

        $current_hook = current_filter();

        if (!isset($this->hook_storage[$current_hook])) {
            return;
        }

        if (isset($this->hook_storage[$current_hook])) {
            if (!isset($this->hook_instance_counts[$current_hook])) {
                $this->hook_instance_counts[$current_hook] = 0;
            }
            $this->hook_instance_counts[$current_hook]++;
            
            echo $this->generate_hook_html($current_hook, $this->hook_storage[$current_hook], true);
        }
    }

    private function generate_hook_html($hook, $info, $inline = false)
    {

        $execution_time = isset($this->execution_times[$hook]) ? $this->execution_times[$hook] * 1000 : 0;
        $memory_used = isset($this->memory_usage[$hook]) ? $this->memory_usage[$hook] / 1024 : 0;

        if ($inline) {
            $html = '<span class="aether-hook-indicator">';
            $html .= $info['callback_count'];
            $html .= '<div class="aether-hook-details">';
        } else {
            $html = '<div class="aether-hook-entry">';
        }

        $html .= '<div class="aether-hook-name">' . esc_html($hook) . '</div>';
        $html .= sprintf(
            '<div class="aether-hook-stats">Callbacks: %d | Time: %.2fms | Memory: %.2fKB</div>',
            $info['callback_count'],
            $execution_time,
            $memory_used
        );
        
        if (!empty($info['callbacks'])) {
            $html .= '<div class="aether-hook-callbacks">';
            foreach ($info['callbacks'] as $callback) {
                $html .= $this->generate_callback_html($callback);
            }
            $html .= '</div>';
        }

        if (!empty($info['do_action_file']) && !empty($info['do_action_line'])) {
            $html .= '<div class="aether-hook-callback">Called from: ';
            $html .= $this->generate_file_link($info['do_action_file'], $info['do_action_line']);
            $html .= '</div>';
        }

        if ($inline) {
            $html .= '</div></span>';
        } else {
            $html .= '</div>';
        }
        
        return $html;
    }

    private function generate_callback_html($callback)
    {
        $html = '<div class="aether-hook-callback">';
        $html .= sprintf(
            '%s (Priority: %d)',
            esc_html($callback['callback']),
            $callback['priority']
        );
        
        if (!empty($callback['file']) && !empty($callback['line'])) {
            $html .= $this->generate_file_link($callback['file'], $callback['line']);
        }
        
        $html .= '</div>';
        return $html;
    }

    private function generate_file_link($file, $line)
    {
        return sprintf(
            '<a href="cursor://file/%s:%d" class="aether-file-link">%s:%d</a>',
            esc_attr($file),
            intval($line),
            esc_html(basename($file)),
            intval($line)
        );
    }

    public function render_hooks()
    {
        echo '<div class="aether-hooks-panel">';
        echo '<h3>Pre-Body Hooks</h3>';
        foreach ($this->pre_body_hooks as $hook => $info) {
            echo $this->generate_hook_html($hook, $info, false);
        }
        echo '</div>';
    }
}
