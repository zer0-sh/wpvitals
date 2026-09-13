<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checks\WordPressVersionCheck;
use WPVitals\Result;

final class WordPressVersionCheckTest extends TestCase {

	private function check( string $installed, ?array $available, string $mode ): WordPressVersionCheck {
		return new WordPressVersionCheck(
			static function () use ( $installed ): string {
				return $installed;
			},
			static function () use ( $available ): ?array {
				return $available;
			},
			static function () use ( $mode ): string {
				return $mode;
			}
		);
	}

	public function test_versiones_iguales_con_autoupdates_ok(): void {
		$result = $this->check( '6.8.1', array( 'current' => '6.8.1', 'response' => 'latest' ), 'minor' )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 0, $result->get_points_deducted() );
		$this->assertSame( 'core/version', $result->get_id() );
	}

	public function test_versiones_iguales_sin_autoupdates_info(): void {
		$result = $this->check( '6.8.1', array( 'current' => '6.8.1', 'response' => 'latest' ), 'disabled' )->run();

		$this->assertSame( Result::SEVERITY_INFO, $result->get_severity() );
		$this->assertSame( 0, $result->get_points_deducted() );
		$this->assertStringContainsString( 'deshabilitadas', $result->get_recommendation() );
	}

	public function test_version_instalada_antigua_avisa(): void {
		$result = $this->check( '6.5.0', array( 'current' => '6.8.1', 'response' => 'upgrade' ), 'disabled' )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
		$this->assertStringContainsString( '6.8.1', $result->get_recommendation() );
	}

	public function test_version_instalada_antigua_incluso_con_autoupdates(): void {
		$result = $this->check( '6.5.0', array( 'current' => '6.8.1', 'response' => 'upgrade' ), 'major' )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	public function test_sin_datos_de_version_estable_info(): void {
		$result = $this->check( '6.8.1', null, 'minor' )->run();

		$this->assertSame( Result::SEVERITY_INFO, $result->get_severity() );
		$this->assertSame( 0, $result->get_points_deducted() );
	}
}