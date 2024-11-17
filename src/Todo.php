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
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes() 
    {
        register_rest_route('aether/v1', '/set-default-editor', array(
            'methods' => 'POST',
            'callback' => array($this, 'set_default_editor'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'args' => array(
                'editor' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));
    }

    public function set_default_editor($request)
    {
        $editor = $request->get_param('editor');
        $user_id = get_current_user_id();
        update_user_meta($user_id, 'aether_default_editor', $editor);
        return rest_ensure_response(array('success' => true));
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
        $default_editor = get_user_meta(get_current_user_id(), 'aether_default_editor', true);
        ?>
        <div class="wrap">
            <h1>Aether Todo List</h1>

            <script>
                let gitActivityChartId = false;
                let ctx;
                let gitData;
                let changesByDateAndAuthor;
                let dates;
                let authors;
                let datasets;
                let dailyTotalChanges;
            </script>
            
            <?php foreach ($todos as $location => $data): ?>

                <?php if (!empty($data['todos'])): ?>
                    <h2><?php echo esc_html($location); ?> (<?php echo count($data['todos']); ?> items)</h2>

                    <?php $data['git_data'] = array_values($data['git_data']); ?>
                    
                    <?php if (!empty($data['git_data'])): ?>
                        <div class="git-activity-graph">
                            <h3>Git Activity</h3>
                            <div class="graph-container">
                                <canvas id="gitActivityChart" style="height: 300px;"></canvas>
                            </div>
                            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                            <script>
                                gitActivityChartId = 'gitActivityChart-' + Math.random().toString(36).substr(2, 9);
                                document.querySelector('#gitActivityChart').id = gitActivityChartId;
                                ctx = document.getElementById(gitActivityChartId);
                                gitData = <?php echo json_encode($data['git_data']); ?>;
                                
                                // Group changes by date and author
                                changesByDateAndAuthor = gitData.reduce((acc, commit) => {
                                    const date = new Date(commit.date).toLocaleDateString('en-GB', {
                                        day: '2-digit',
                                        month: '2-digit',
                                        year: '2-digit'
                                    });
                                    
                                    if (!acc[date]) {
                                        acc[date] = {};
                                    }
                                    if (!acc[date][commit.author]) {
                                        acc[date][commit.author] = 0;
                                    }
                                    acc[date][commit.author] += commit.changes.additions + commit.changes.deletions;
                                    return acc;
                                }, {});

                                // Get unique dates and authors
                                dates = Object.keys(changesByDateAndAuthor).sort((a, b) => {
                                    const [dayA, monthA, yearA] = a.split('/');
                                    const [dayB, monthB, yearB] = b.split('/');
                                    return new Date(`20${yearA}`, monthA - 1, dayA) - new Date(`20${yearB}`, monthB - 1, dayB);
                                });
                                authors = [...new Set(gitData.map(commit => commit.author))];

                                // Calculate daily total changes
                                dailyTotalChanges = dates.map(date => 
                                    Object.values(changesByDateAndAuthor[date]).reduce((sum, val) => sum + val, 0)
                                );

                                // Create datasets for each author
                                datasets = authors.map((author, index) => ({
                                    label: author,
                                    data: dates.map(date => changesByDateAndAuthor[date][author] || 0),
                                    backgroundColor: `hsl(${(index * 360/authors.length)}, 70%, 50%)`,
                                    type: 'bar'
                                }));

                                // Add daily total changes area dataset
                                datasets.unshift({
                                    label: 'Total Daily Changes',
                                    data: dailyTotalChanges,
                                    type: 'line',
                                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                                    borderColor: 'rgba(0, 123, 255, 0.5)',
                                    borderWidth: 2,
                                    fill: 'start',
                                    yAxisID: 'daily',
                                    order: -1
                                });

                                new Chart(ctx, {
                                    data: {
                                        labels: dates,
                                        datasets: datasets
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        height: 400,
                                        scales: {
                                            x: {
                                                stacked: true
                                            },
                                            y: {
                                                stacked: true,
                                                beginAtZero: true,
                                                ticks: {
                                                    stepSize: 1
                                                }
                                            },
                                            daily: {
                                                position: 'right',
                                                beginAtZero: true,
                                                grid: {
                                                    drawOnChartArea: false
                                                }
                                            }
                                        },
                                        plugins: {
                                            tooltip: {
                                                callbacks: {
                                                    title: (tooltipItems) => {
                                                        const date = tooltipItems[0].label;
                                                        const commits = gitData.filter(commit => {
                                                            return new Date(commit.date).toLocaleDateString('en-GB', {
                                                                day: '2-digit',
                                                                month: '2-digit',
                                                                year: '2-digit'
                                                            }) === date;
                                                        });
                                                        const firstCommit = commits[0];
                                                        return new Date(firstCommit.date).toLocaleDateString('en-GB', {
                                                            year: 'numeric',
                                                            month: 'long',
                                                            day: 'numeric'
                                                        });
                                                    },
                                                    label: (context) => {
                                                        if (context.dataset.type === 'line') {
                                                            return `Total changes this day: ${context.raw}`;
                                                        }
                                                        return `${context.dataset.label}: ${context.raw} changes`;
                                                    }
                                                }
                                            }
                                        },
                                        interaction: {
                                            intersect: false,
                                            mode: 'index'
                                        }
                                    }
                                });
                            </script>
                        </div>
                    <?php endif; ?>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Line</th>
                                <th>Todo</th>
                                <th>Open in IDE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['todos'] as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['file']); ?></td>
                                    <td><?php echo esc_html($item['line']); ?></td>
                                    <td><?php echo esc_html($item['todo']); ?></td>
                                    <td>
                                        <div class="editor-links" data-default="<?php echo esc_attr($default_editor); ?>">
                                            <a href="vscode://file/<?php echo esc_attr($item['full_path']); ?>:<?php echo esc_attr($item['line']); ?>:0" 
                                               class="button button-small editor-link" 
                                               data-editor="vscode"
                                               <?php echo $default_editor && $default_editor !== 'vscode' ? 'style="display:none;"' : ''; ?>>
                                                VS Code
                                            </a>
                                            <a href="phpstorm://open?file=<?php echo esc_attr($item['full_path']); ?>&line=<?php echo esc_attr($item['line']); ?>&column=0" 
                                               class="button button-small editor-link" 
                                               data-editor="phpstorm"
                                               <?php echo $default_editor && $default_editor !== 'phpstorm' ? 'style="display:none;"' : ''; ?>>
                                                PhpStorm
                                            </a>
                                            <a href="sublime://open?url=file://<?php echo esc_attr($item['full_path']); ?>:<?php echo esc_attr($item['line']); ?>:0" 
                                               class="button button-small editor-link" 
                                               data-editor="sublime"
                                               <?php echo $default_editor && $default_editor !== 'sublime' ? 'style="display:none;"' : ''; ?>>
                                                Sublime
                                            </a>
                                            <a href="atom://core/open/file?filename=<?php echo esc_attr($item['full_path']); ?>&line=<?php echo esc_attr($item['line']); ?>&column=0" 
                                               class="button button-small editor-link" 
                                               data-editor="atom"
                                               <?php echo $default_editor && $default_editor !== 'atom' ? 'style="display:none;"' : ''; ?>>
                                                Atom
                                            </a>
                                            <a href="cursor://file/<?php echo esc_attr($item['full_path']); ?>:<?php echo esc_attr($item['line']); ?>:0" 
                                               class="button button-small editor-link" 
                                               data-editor="cursor"
                                               <?php echo $default_editor && $default_editor !== 'cursor' ? 'style="display:none;"' : ''; ?>>
                                                Cursor
                                            </a>
                                        </div>
                                    </td>
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

            // Skip if plugin should not be tracked
            if ($has_tracked_plugins) {
                $found_managed = false;
                foreach ($managed_plugins as $plugin_file => $settings) {
                    if (strpos($plugin_file, $plugin_dir . '/') === 0 && 
                        isset($settings['track_todo']) && 
                        $settings['track_todo']) {
                        $found_managed = true;
                        break;
                    }
                }
                if (!$found_managed) {
                    continue;
                }
            }

            $git_data = array();
            if (is_dir($plugin_path . '/.git')) {
                $git_data = $this->get_git_activity($plugin_path);
            }

            $todos['Plugin: ' . $plugin_dir] = array(
                'todos' => $this->scan_directory($plugin_path),
                'git_data' => $git_data
            );
        }

        // Scan themes
        $themes_dir = get_theme_root();
        $theme_directories = array_diff(scandir($themes_dir), array('..', '.'));
        
        foreach ($theme_directories as $theme) {
            $theme_path = $themes_dir . '/' . $theme;
            if (is_dir($theme_path)) {
                $git_data = array();
                if (is_dir($theme_path . '/.git')) {
                    $git_data = $this->get_git_activity($theme_path);
                }

                $todos['Theme: ' . $theme] = array(
                    'todos' => $this->scan_directory($theme_path),
                    'git_data' => $git_data
                );
            }
        }

        return $todos;
    }

    private function get_git_activity($path) 
    {

        
        // Get git log for the last 90 days with stats
        $command = sprintf(
            'cd %s && git log --since="90 days ago" --pretty=format:"%%H|%%an|%%ai|" --numstat --no-merges',
            escapeshellarg($path)
        );

        $output = shell_exec($command);

        if (!$output) {
            return array();
        }

        $lines = explode("\n", trim($output));
        $json = array();
        $current_commit = null;
        $current_line = 0;

        foreach ($lines as $key => $line) {
            if (strpos($line, '|') !== false) {
                $current_line = $key;
                // This is a commit line
                list($hash, $author, $date) = explode('|', $line);
                $current_commit = array(
                    'commit' => $hash,
                    'author' => $author,
                    'date' => $date,
                    'changes' => array(
                        'additions' => 0,
                        'deletions' => 0
                    )
                );
                $json[$current_line] = $current_commit;
            } else if (preg_match('/^(\d+)\s+(\d+)\s+/', $line, $matches)) {
                // This is a stats line
                if ($json[$current_line]) {
                    $json[$current_line]['changes']['additions'] += (int)$matches[1];
                    $json[$current_line]['changes']['deletions'] += (int)$matches[2];
                }
            }
        }

        // dd($json, 5);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Git log JSON decode error: ' . json_last_error_msg());
            error_log('Raw output: ' . $output);
            return array();
        }
        
        return $json;
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
                            'todo' => trim($match[0]),
                            'full_path' => $file->getPathname()
                        );
                    }
                }
            }
        }
        
        return $todos;
    }
}