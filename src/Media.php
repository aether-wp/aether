<?php

namespace mklasen\Aether;

/**
 * Media handling for Aether plugin
 * 
 * Features:
 * - Loads media files from a remote environment
 * - Replaces local upload URLs with remote environment URLs
 * - Prevents local disk space usage from media uploads
 * 
 * Goal:
 * - Keep local development environments lean by serving media from remote environments
 * - Avoid duplicating large media files across environments
 * 
 * Implementation Details:
 * - Uses aether_switch_options containing environment configs:
 *   - Array of environments with 'label', 'url' and 'load_media' parameters
 * - Loads media from the first environment which has 'load_media' enabled
 */
class Media {
    private $remote_url;
    private $local_url;

    public function __construct() {
        $this->setup_urls();
        $this->hooks();
    }

    private function setup_urls() {
        $options = get_option('aether_switch_options');
        $environments = isset($options['environments']) ? $options['environments'] : array();
        
        // Get first environment with load_media enabled
        foreach ($environments as $env) {
            if (isset($env['load_media']) && $env['load_media']) {
                $this->remote_url = trailingslashit($env['url']);
                break;
            }
        }

        $this->local_url = trailingslashit(get_site_url());
    }

    public function hooks() {
        if (!$this->remote_url) {
            return;
        }

        add_filter('upload_dir', array($this, 'modify_upload_dir'));
        add_filter('wp_get_attachment_url', array($this, 'modify_attachment_url'));
    }

    public function modify_upload_dir($uploads) {
        $uploads['url'] = str_replace($this->local_url, $this->remote_url, $uploads['url']);
        $uploads['baseurl'] = str_replace($this->local_url, $this->remote_url, $uploads['baseurl']);
        return $uploads;
    }

    public function modify_attachment_url($url) {
        return str_replace($this->local_url, $this->remote_url, $url);
    }
}