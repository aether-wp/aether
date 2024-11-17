<?php

namespace mklasen\Aether;

/**
 * What_File class for displaying template and file information in the WordPress admin bar
 * 
 * Features:
 * - Detects and displays all template files used to render the current page
 * - Shows template hierarchy and included template parts
 * - Displays file paths, load order and template context in admin toolbar
 * - Quick links to open files in configured code editor
 * - Performance impact tracking for template loading
 * - Filter hooks to customize displayed information
 * - Debug mode to show additional template data
 * 
 * Filters:
 * - aether_what_file_display: Filter the files/templates shown
 * - aether_what_file_editors: Customize available editor quick links
 */
class What_File {
    
    private $template_files = [];
    private $template_parts = [];
    private $load_times = [];
    
    public function __construct() {
        $this->hooks();
    }
    
    public function hooks() {
        // Only run on frontend
        if (is_admin()) {
            return;
        }

        add_action('template_include', [$this, 'track_template'], 999);
        add_action('get_template_part', [$this, 'track_template_part'], 10, 3);
        add_action('admin_bar_menu', [$this, 'add_toolbar_item'], 100);
    }

    public function track_template($template) {
        $start_time = microtime(true);
        $this->template_files[] = [
            'file' => $template,
            'type' => 'main',
            'start_time' => $start_time
        ];
        return $template;
    }

    public function track_template_part($slug, $name, $templates) {
        $this->template_parts[] = [
            'slug' => $slug,
            'name' => $name,
            'templates' => $templates,
            'time' => microtime(true)
        ];
    }

    public function add_toolbar_item($wp_admin_bar) {
        if (!is_admin_bar_showing()) {
            return;
        }

        // Add main node
        $wp_admin_bar->add_node([
            'id' => 'aether-what-file',
            'title' => 'Template Files',
            'href' => '#'
        ]);

        // Add template information
        foreach ($this->template_files as $template) {
            $editor_link = $this->get_editor_link($template['file']);
            $wp_admin_bar->add_node([
                'id' => 'template-' . md5($template['file']),
                'parent' => 'aether-what-file',
                'title' => basename($template['file']),
                'href' => '#',
                'meta' => [
                    'onclick' => 'window.location.href = "' . esc_js($editor_link) . '"; return false;'
                ]
            ]);
        }

        // Add template parts
        if (!empty($this->template_parts)) {
            $wp_admin_bar->add_node([
                'id' => 'template-parts',
                'parent' => 'aether-what-file',
                'title' => 'Template Parts'
            ]);

            foreach ($this->template_parts as $part) {
                $wp_admin_bar->add_node([
                    'id' => 'part-' . md5($part['slug'] . $part['name']),
                    'parent' => 'template-parts',
                    'title' => $part['slug'] . ($part['name'] ? '-' . $part['name'] : ''),
                    'href' => '#'
                ]);
            }
        }
    }

    private function get_editor_link($file) {
        $default_editor = get_user_meta(get_current_user_id(), 'aether_default_editor', true);
        $file_path = wp_normalize_path($file);
        
        switch ($default_editor) {
            case 'vscode':
                return 'vscode://file/' . $file_path;
            case 'phpstorm':
                return 'phpstorm://open?file=' . $file_path;
            case 'sublime':
                return 'sublime://open?url=file://' . $file_path;
            case 'atom':
                return 'atom://core/open/file?filename=' . $file_path;
            case 'cursor':
                return 'cursor://file/' . $file_path;
            default:
                return '#';
        }
    }
}