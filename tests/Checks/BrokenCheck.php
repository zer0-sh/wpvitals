<?php
declare( strict_types=1 );

namespace WPVitals\Tests\Checks;

use WPVitals\Checks\AbstractCheck;
use WPVitals\Result;

final class BrokenCheck extends AbstractCheck {

	public function get_id(): string {
		return 'test/broken';
	}

	public function get_title(): string {
		return 'Check que falla';
	}

	public function run(): Result {
		throw new \RuntimeException( 'Fallo simulado' );
	}
}