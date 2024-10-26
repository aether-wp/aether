<?php

namespace mklasen\Aether;

class Herd {
	public function __construct() {
		$this->hooks();
	}

	public function hooks() {
		add_action('phpmailer_init', [$this, 'setup_mail']);
	}

	public function setup_mail($phpmailer) {
		$phpmailer->isSMTP();
		$phpmailer->Host = '127.0.0.1';
		$phpmailer->Port = 2525;
		$phpmailer->Username = 'test';
	}
}