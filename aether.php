<?php

/**
 * Plugin Name: Aether
 * Description: WordPress Dev Suite
 */

namespace mklasen\Aether;

class Aether
{
    private $herd_enabled;

    public function __construct()
    {
        require_once __DIR__ . '/vendor/autoload.php';
        $this->herd_enabled = get_option('aether_herd_enabled', false);
        if ($this->herd_enabled && $this->is_herd()) {
            new Herd();
        }
        if (get_option('aether_disable_email', true)) {
            new Disable_Email();
        }
        new Switcher();
        new Settings();
        new Manage_Plugins();
        new Notes();
        new Todo();
        new Show_Hooks();
    }

    private function is_herd()
    {
        return is_array($_SERVER) && array_filter(array_keys($_SERVER), fn($key) => is_string($key) && str_contains($key, 'HERD_'));
    }
}

new Aether();
