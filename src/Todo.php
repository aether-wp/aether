<?php

namespace mklasen\Aether;

class Todo
{
    public function __construct()
    {
        $this->hooks();
    }

    public function hooks()
    {
        add_action('admin_menu', array($this, 'add_todo_submenu'));
    }

    public function add_todo_submenu()
    {
        add_submenu_page(
            'aether',
            'Todo',
            'Todo',
            'manage_options',
            'aether-todo',
            array($this, 'render_todo_page')
        );
    }

    public function render_todo_page()
    {
        $todos = $this->scan_for_todos();
        ?>
        <div class="wrap">
            <h1>Aether Todo List</h1>
            
            <?php foreach ($todos as $location => $items): ?>
                <?php if (!empty($items)): ?>
                    <h2><?php echo esc_html($location); ?> (<?php echo count($items); ?> items)</h2>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Line</th>
                                <th>Todo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['file']); ?></td>
                                    <td><?php echo esc_html($item['line']); ?></td>
                                    <td><?php echo esc_html($item['todo']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function scan_for_todos()
    {
        $todos = array();
        $managed_plugins = get_option('aether_managed_plugins', array());

        // Check if any plugins are set to track todos
        $has_tracked_plugins = false;
        foreach ($managed_plugins as $settings) {
            if (isset($settings['track_todo']) && $settings['track_todo']) {
                $has_tracked_plugins = true;
                break;
            }
        }

        // Scan plugins
        $plugins_dir = WP_PLUGIN_DIR;
        $plugin_directories = array_diff(scandir($plugins_dir), array('..', '.'));
        
        foreach ($plugin_directories as $plugin_dir) {
            $plugin_path = $plugins_dir . '/' . $plugin_dir;
            if (!is_dir($plugin_path)) {
                continue;
            }

            // If no plugins are tracked, scan all plugins
            if (!$has_tracked_plugins) {
                $todos['Plugin: ' . $plugin_dir] = $this->scan_directory($plugin_path);
                continue;
            }

            // Find if any managed plugin is in this directory
            $found_managed = false;
            foreach ($managed_plugins as $plugin_file => $settings) {
                if (strpos($plugin_file, $plugin_dir . '/') === 0 && 
                    isset($settings['track_todo']) && 
                    $settings['track_todo']) {
                    $found_managed = true;
                    break;
                }
            }

            if ($found_managed) {
                $todos['Plugin: ' . $plugin_dir] = $this->scan_directory($plugin_path);
            }
        }

        // Scan themes
        $themes_dir = get_theme_root();
        $theme_directories = array_diff(scandir($themes_dir), array('..', '.'));
        
        foreach ($theme_directories as $theme) {
            $theme_path = $themes_dir . '/' . $theme;
            if (is_dir($theme_path)) {
                $todos['Theme: ' . $theme] = $this->scan_directory($theme_path);
            }
        }

        return $todos;
    }

    private function scan_directory($directory)
    {
        $todos = array();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            // Skip node_modules and vendor directories
            if (strpos($file->getPathname(), 'node_modules') !== false || 
                strpos($file->getPathname(), 'vendor') !== false) {
                continue;
            }

            if ($file->isFile() && in_array($file->getExtension(), array('php', 'js', 'css'))) {
                $content = file_get_contents($file->getPathname());
                $lines = file($file->getPathname());
                
                if (preg_match_all('/(?:@todo|\/\/\s*@todo:?)\s+(.+)$/im', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[1] as $index => $match) {
                        $position = $matches[0][$index][1];
                        $line_number = count(explode("\n", substr($content, 0, $position)));
                        
                        $todos[] = array(
                            'file' => str_replace($directory . '/', '', $file->getPathname()),
                            'line' => $line_number,
                            'todo' => trim($match[0])
                        );
                    }
                }
            }
        }
        
        return $todos;
    }
}