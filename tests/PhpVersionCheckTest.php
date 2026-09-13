<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checks\PhpVersionCheck;
use WPVitals\Result;

final class PhpVersionCheckTest extends TestCase {

	private function check( string $version, string $today ): PhpVersionCheck {
		return new PhpVersionCheck(
			static function () use ( $version ): string {
				return $version;
			},
			static function () use ( $today ): string {
				return $today;
			}
		);
	}

	public function test_php_soportado(): void {
		$result = $this->check( '8.2', '2026-01-01' )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 0, $result->get_points_deducted() );
		$this->assertSame( 'php/version', $result->get_id() );
	}

	public function test_php_fuera_de_soporte(): void {
		$result = $this->check( '7.4', '2026-01-01' )->run();

		$this->assertSame( Result::SEVERITY_CRITICAL, $result->get_severity() );
		$this->assertSame( 10, $result->get_points_deducted() );
		$this->assertStringContainsString( '2022-11-28', $result->get_recommendation() );
	}

	public function test_php_con_eol_proximo(): void {
		$result = $this->check( '8.2', '2026-09-01' )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	public function test_php_desconocido_info(): void {
		$result = $this->check( '9.0', '2026-09-01' )->run();

		$this->assertSame( Result::SEVERITY_INFO, $result->get_severity() );
		$this->assertSame( 0, $result->get_points_deducted() );
	}
}