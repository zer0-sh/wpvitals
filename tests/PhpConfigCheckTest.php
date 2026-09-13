<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checks\PhpMemoryCheck;
use WPVitals\Checks\PhpDisplayErrorsCheck;
use WPVitals\Result;

final class PhpConfigCheckTest extends TestCase {

	public function test_memoria_suficiente(): void {
		$result = $this->memory_check( '256M' )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'php/memory', $result->get_id() );
	}

	public function test_memoria_sin_limite(): void {
		$result = $this->memory_check( '-1' )->run();

		$this->assertTrue( $result->is_ok() );
	}

	public function test_memoria_limitada_warning(): void {
		$result = $this->memory_check( '64M' )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	public function test_memoria_gigabyte_superada(): void {
		$result = $this->memory_check( '1G' )->run();

		$this->assertTrue( $result->is_ok() );
	}

	public function test_display_errors_activo_avisa(): void {
		$result = $this->display_check( '1' )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 'php/display_errors', $result->get_id() );
	}

	public function test_display_errors_activo_string_on(): void {
		$result = $this->display_check( 'On' )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
	}

	public function test_display_errors_desactivado(): void {
		$result = $this->display_check( 'Off' )->run();

		$this->assertTrue( $result->is_ok() );
	}

	public function test_display_errors_a_stderr_ok(): void {
		$result = $this->display_check( 'stderr' )->run();

		$this->assertTrue( $result->is_ok() );
	}

	private function memory_check( string $limit ): PhpMemoryCheck {
		return new PhpMemoryCheck(
			static function () use ( $limit ): string {
				return $limit;
			}
		);
	}

	private function display_check( string $value ): PhpDisplayErrorsCheck {
		return new PhpDisplayErrorsCheck(
			static function () use ( $value ): string {
				return $value;
			}
		);
	}
}