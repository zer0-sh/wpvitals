<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checker;
use WPVitals\MailReport;
use WPVitals\Result;
use WPVitals\ScanOutcome;
use WPVitals\Scanner;
use WPVitals\ScanRunner;
use WPVitals\ScanStore;
use WPVitals\Settings;
use WPVitals\Tests\Checks\OkCheck;
use WPVitals\VulnerabilityClient;

final class ScanRunnerTest extends TestCase {

	private $options = array();

	private $sent = array();

	private function make_scanner(): Scanner {
		$checker = new Checker();
		$checker->add_check( new OkCheck() );

		return new Scanner(
			$checker,
			new VulnerabilityClient(
				static function (): array {
					return array( 'code' => 200, 'body' => '{}' );
				},
				static function (): bool {
					return false;
				},
				static function (): void {}
			),
			static function (): string {
				return '';
			},
			static function (): array {
				return array();
			},
			static function (): array {
				return array();
			},
			static function (): int {
				return 1700000000;
			}
		);
	}

	private function make_store(): ScanStore {
		$this->options = array();

		return new ScanStore(
			function ( string $key ) {
				return isset( $this->options[ $key ] ) ? $this->options[ $key ] : null;
			},
			function ( string $key, array $value ): void {
				$this->options[ $key ] = $value;
			}
		);
	}

	private function make_mail( bool $sends ): MailReport {
		$this->sent = array();

		return new MailReport(
			function ( string $to, string $subject, string $body ) use ( $sends ): bool {
				$this->sent[] = array( 'to' => $to, 'subject' => $subject, 'body' => $body );

				return $sends;
			},
			function (): string {
				return 'admin@example.com';
			}
		);
	}

	private function settings(): Settings {
		return new Settings( Settings::FREQUENCY_DAILY, true, '' );
	}

	public function test_run_guarda_el_resultado_y_lo_devuelve(): void {
		$store  = $this->make_store();
		$runner = new ScanRunner( $this->make_scanner(), $store, $this->make_mail( true ) );

		$outcome = $runner->run( ScanOutcome::ORIGIN_SCHEDULED, $this->settings() );

		$this->assertSame( ScanOutcome::ORIGIN_SCHEDULED, $outcome->get_origin() );
		$this->assertSame( $outcome->to_array(), $store->get()->to_array() );
	}

	public function test_run_programado_sin_previo_envia_informe(): void {
		$store = $this->make_store();
		$mail  = $this->make_mail( true );
		$runner = new ScanRunner( $this->make_scanner(), $store, $mail );

		$runner->run( ScanOutcome::ORIGIN_SCHEDULED, $this->settings() );

		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'admin@example.com', $this->sent[0]['to'] );
	}

	public function test_run_programado_sin_cambios_no_envia_repetidos(): void {
		$store  = $this->make_store();
		$mail   = $this->make_mail( true );
		$runner = new ScanRunner( $this->make_scanner(), $store, $mail );

		$runner->run( ScanOutcome::ORIGIN_SCHEDULED, $this->settings() );
		$runner->run( ScanOutcome::ORIGIN_SCHEDULED, $this->settings() );

		$this->assertCount( 1, $this->sent );
	}

	public function test_run_manual_siempre_envia_informe(): void {
		$store  = $this->make_store();
		$mail   = $this->make_mail( true );
		$runner = new ScanRunner( $this->make_scanner(), $store, $mail );

		$runner->run( ScanOutcome::ORIGIN_MANUAL, $this->settings() );
		$runner->run( ScanOutcome::ORIGIN_MANUAL, $this->settings() );

		$this->assertCount( 2, $this->sent );
	}

	public function test_run_con_mail_desactivado_no_envia(): void {
		$store  = $this->make_store();
		$mail   = $this->make_mail( true );
		$runner = new ScanRunner( $this->make_scanner(), $store, $mail );
		$settings = new Settings( Settings::FREQUENCY_DAILY, false, '' );

		$runner->run( ScanOutcome::ORIGIN_MANUAL, $settings );

		$this->assertSame( array(), $this->sent );
		$this->assertTrue( array_key_exists( ScanStore::OPTION_NAME, $this->options ) );
	}
}