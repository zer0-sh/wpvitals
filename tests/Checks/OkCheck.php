<?php
declare( strict_types=1 );

namespace WPVitals\Tests\Checks;

use WPVitals\Checks\AbstractCheck;
use WPVitals\Result;

final class OkCheck extends AbstractCheck {

	public function get_id(): string {
		return 'test/ok';
	}

	public function get_title(): string {
		return 'Check que pasa';
	}

	public function run(): Result {
		return $this->result( Result::SEVERITY_OK, true, 'Nada que hacer', 0 );
	}
}