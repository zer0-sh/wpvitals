<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Cron;
use WPVitals\Settings;

final class CronTest extends TestCase {

	private $events = array();

	private $now = 1700000000;

	private function make_cron(): Cron {
		$this->events = array();

		return new Cron(
			function ( string $hook ) {
				if ( ! isset( $this->events[ $hook ] ) ) {
					return false;
				}

				return $this->events[ $hook ]['timestamp'];
			},
			function ( int $timestamp, string $recurrence, string $hook ): bool {
				$this->events[ $hook ] = array(
					'timestamp'  => $timestamp,
					'recurrence' => $recurrence,
				);

				return true;
			},
			function ( string $hook ): int {
				if ( ! isset( $this->events[ $hook ] ) ) {
					return 0;
				}

				unset( $this->events[ $hook ] );

				return 1;
			},
			function (): int {
				return $this->now;
			}
		);
	}

	private function events_for( string $hook ): array {
		return isset( $this->events[ $hook ] ) ? $this->events[ $hook ] : array();
	}

	public function test_add_schedules_registra_semanal_y_mensual(): void {
		$schedules = Cron::add_schedules( array( 'daily' => array( 'interval' => 86400, 'display' => 'A diario' ) ) );

		$this->assertSame( Settings::INTERVAL_WEEKLY, $schedules[ Cron::RECURRENCE_WEEKLY ]['interval'] );
		$this->assertSame( 'Semanalmente', $schedules[ Cron::RECURRENCE_WEEKLY ]['display'] );
		$this->assertSame( Settings::INTERVAL_MONTHLY, $schedules[ Cron::RECURRENCE_MONTHLY ]['interval'] );
		$this->assertSame( 'Mensualmente', $schedules[ Cron::RECURRENCE_MONTHLY ]['display'] );
		$this->assertSame( 86400, $schedules['daily']['interval'] );
	}

	public function test_schedule_programa_con_la_recurrencia_de_frecuencia(): void {
		$cron = $this->make_cron();

		$cron->schedule( new Settings( Settings::FREQUENCY_WEEKLY, true, '' ) );

		$event = $this->events_for( Cron::HOOK );

		$this->assertSame( Cron::RECURRENCE_WEEKLY, $event['recurrence'] );
		$this->assertSame( $this->now + Settings::INTERVAL_WEEKLY, $event['timestamp'] );
	}

	public function test_schedule_con_frecuencia_desactivada_limpia_el_evento(): void {
		$cron = $this->make_cron();
		$cron->schedule( new Settings( Settings::FREQUENCY_DAILY, true, '' ) );

		$cron->schedule( new Settings( Settings::FREQUENCY_DISABLED, true, '' ) );

		$this->assertFalse( array_key_exists( Cron::HOOK, $this->events ) );
		$this->assertNull( $cron->next_run() );
	}

	public function test_schedule_evita_eventos_duplicados(): void {
		$cron = $this->make_cron();
		$settings = new Settings( Settings::FREQUENCY_DAILY, true, '' );

		$cron->schedule( $settings );
		$cron->schedule( $settings );

		$this->assertCount( 1, $this->events );
	}

	public function test_schedule_reprograma_al_cambiar_la_frecuencia(): void {
		$cron = $this->make_cron();

		$cron->schedule( new Settings( Settings::FREQUENCY_DAILY, true, '' ) );
		$cron->schedule( new Settings( Settings::FREQUENCY_MONTHLY, true, '' ) );

		$event = $this->events_for( Cron::HOOK );

		$this->assertSame( Cron::RECURRENCE_MONTHLY, $event['recurrence'] );
		$this->assertSame( $this->now + Settings::INTERVAL_MONTHLY, $event['timestamp'] );
	}

	public function test_clear_elimina_el_evento_programado(): void {
		$cron = $this->make_cron();
		$cron->schedule( new Settings( Settings::FREQUENCY_DAILY, true, '' ) );

		$cron->clear();

		$this->assertFalse( array_key_exists( Cron::HOOK, $this->events ) );
	}

	public function test_next_run_devuelve_null_sin_evento(): void {
		$cron = $this->make_cron();

		$this->assertNull( $cron->next_run() );
	}
}