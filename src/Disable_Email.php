<?php

namespace mklasen\Aether;

class Disable_Email
{
    public function __construct()
    {
        $this->hooks();
    }

    public function hooks()
    {
        add_filter('wp_mail', array($this, 'disable_email'), 10, 1);
        add_filter('pre_wp_mail', array($this, 'disable_email'), 10, 1);
    }

    public function disable_email($args)
    {
        // Log that email was blocked
        error_log('Aether: Email sending disabled. Would have sent email with subject: ' . 
            (isset($args['subject']) ? $args['subject'] : 'No subject'));
        
        // Return false to prevent email from being sent
        return false;
    }
}
