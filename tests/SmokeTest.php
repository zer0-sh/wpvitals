<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase {

	public function test_composer_autoload_loaded(): void {
		$this->assertTrue( class_exists( 'Composer\\InstalledVersions' ) );
	}

	public function test_wpvitals_abspath_defined(): void {
		$this->assertTrue( defined( 'ABSPATH' ) );
	}
}