<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checks\CronCheck;
use WPVitals\Checks\LoopbackCheck;
use WPVitals\Result;

final class ReliabilityChecksTest extends TestCase {

	public function test_cron_habilitado_con_eventos_ok(): void {
		$result = $this->cron_check( false, true )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'system/cron', $result->get_id() );
	}

	public function test_cron_deshabilitado_con_eventos_info(): void {
		$result = $this->cron_check( true, true )->run();

		$this->assertSame( Result::SEVERITY_INFO, $result->get_severity() );
		$this->assertSame( 0, $result->get_points_deducted() );
	}

	public function test_cron_deshabilitado_sin_eventos_avisa(): void {
		$result = $this->cron_check( true, false )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
		$this->assertStringContainsString( 'wp-cron.php', $result->get_recommendation() );
	}

	public function test_cron_habilitado_sin_eventos_avisa(): void {
		$result = $this->cron_check( false, false )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	public function test_loopback_correcto_ok(): void {
		$result = $this->loopback_check( true )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'system/loopback', $result->get_id() );
	}

	public function test_loopback_fallido_avisa(): void {
		$result = $this->loopback_check( false )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	private function cron_check( bool $disabled, bool $has_events ): CronCheck {
		return new CronCheck(
			static function () use ( $disabled ): bool {
				return $disabled;
			},
			static function () use ( $has_events ): bool {
				return $has_events;
			}
		);
	}

	private function loopback_check( bool $ok ): LoopbackCheck {
		return new LoopbackCheck(
			static function () use ( $ok ): bool {
				return $ok;
			}
		);
	}
}