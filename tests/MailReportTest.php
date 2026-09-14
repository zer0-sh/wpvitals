<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\MailReport;
use WPVitals\Result;
use WPVitals\ScanOutcome;
use WPVitals\Settings;

final class MailReportTest extends TestCase {

	private function outcome( string $origin, int $completed_at = 1700000000 ): ScanOutcome {
		return ScanOutcome::from_results(
			array(
				new Result( 'vuln/plugin/cf7/p1', 'XSS en formulario', Result::SEVERITY_CRITICAL, null, 'Actualiza a una versión segura.', 15 ),
				new Result( 'plugin/akismet', 'Desactualizado', Result::SEVERITY_WARNING, null, 'Ejecuta la actualización pendiente.', 5 ),
				new Result( 'components/ok', 'Componentes al día', Result::SEVERITY_OK, null ),
			),
			$origin,
			$completed_at
		);
	}

	private function settings( string $frequency = Settings::FREQUENCY_DAILY, bool $mail_enabled = true, string $recipient = '' ): Settings {
		return new Settings( $frequency, $mail_enabled, $recipient );
	}

	private function make_report( ?callable $wp_mail = null, ?callable $admin_email = null ): MailReport {
		return new MailReport( $wp_mail, $admin_email );
	}

	public function test_should_notify_manual_siempre_informa(): void {
		$report = $this->make_report();
		$previous = $this->outcome( ScanOutcome::ORIGIN_SCHEDULED );

		$this->assertTrue( $report->should_notify( $this->outcome( ScanOutcome::ORIGIN_MANUAL ), $this->settings(), $previous ) );
	}

	public function test_should_notify_no_informa_si_mail_desactivado(): void {
		$report = $this->make_report();

		$this->assertFalse(
			$report->should_notify( $this->outcome( ScanOutcome::ORIGIN_MANUAL ), $this->settings( Settings::FREQUENCY_DAILY, false ), null )
		);
	}

	public function test_should_notify_no_informa_si_frecuencia_desactivada(): void {
		$report = $this->make_report();

		$this->assertFalse(
			$report->should_notify( $this->outcome( ScanOutcome::ORIGIN_SCHEDULED ), $this->settings( Settings::FREQUENCY_DISABLED ), null )
		);
	}

	public function test_should_notify_programado_sin_previo_informa(): void {
		$report = $this->make_report();

		$this->assertTrue(
			$report->should_notify( $this->outcome( ScanOutcome::ORIGIN_SCHEDULED ), $this->settings(), null )
		);
	}

	public function test_should_notify_programado_igual_previo_no_informa(): void {
		$report = $this->make_report();
		$previous = $this->outcome( ScanOutcome::ORIGIN_SCHEDULED, 1700000001 );

		$this->assertFalse(
			$report->should_notify( $this->outcome( ScanOutcome::ORIGIN_SCHEDULED, 1700000002 ), $this->settings(), $previous )
		);
	}

	public function test_should_notify_programado_distinto_previo_informa(): void {
		$report = $this->make_report();
		$previous = ScanOutcome::from_results(
			array( new Result( 'vuln/plugin/cf7/p1', 'XSS en formulario', Result::SEVERITY_CRITICAL, null, 'Actualiza.', 15 ) ),
			ScanOutcome::ORIGIN_SCHEDULED
		);

		$this->assertTrue(
			$report->should_notify( $this->outcome( ScanOutcome::ORIGIN_SCHEDULED ), $this->settings(), $previous )
		);
	}

	public function test_send_usa_destinatario_personalizado(): void {
		$sent = array();

		$report = $this->make_report(
			function ( string $to, string $subject, string $body ) use ( &$sent ): bool {
				$sent = array( 'to' => $to, 'subject' => $subject, 'body' => $body );

				return true;
			},
			function (): string {
				return 'admin@example.com';
			}
		);

		$result = $report->send( $this->outcome( ScanOutcome::ORIGIN_MANUAL ), $this->settings( Settings::FREQUENCY_DAILY, true, 'ops@example.com' ) );

		$this->assertTrue( $result );
		$this->assertSame( 'ops@example.com', $sent['to'] );
		$this->assertStringContainsString( 'WPVitals', $sent['subject'] );
		$this->assertStringContainsString( '80/100', $sent['body'] );
	}

	public function test_send_usa_correo_del_administrador_si_no_hay_personalizado(): void {
		$sent = array();

		$report = $this->make_report(
			function ( string $to ) use ( &$sent ): bool {
				$sent['to'] = $to;

				return true;
			},
			function (): string {
				return 'admin@example.com';
			}
		);

		$report->send( $this->outcome( ScanOutcome::ORIGIN_MANUAL ), $this->settings() );

		$this->assertSame( 'admin@example.com', $sent['to'] );
	}

	public function test_send_sin_destinatario_disponible_devuelve_false(): void {
		$report = $this->make_report(
			function (): bool {
				return true;
			},
			function (): string {
				return '';
			}
		);

		$this->assertFalse( $report->send( $this->outcome( ScanOutcome::ORIGIN_MANUAL ), $this->settings() ) );
	}

	public function test_send_devuelve_el_resultado_de_wp_mail(): void {
		$report = $this->make_report(
			function (): bool {
				return false;
			},
			function (): string {
				return 'admin@example.com';
			}
		);

		$this->assertFalse( $report->send( $this->outcome( ScanOutcome::ORIGIN_MANUAL ), $this->settings() ) );
	}

	public function test_send_fallo_de_wp_mail_no_se_propaga(): void {
		$report = $this->make_report(
			function (): bool {
				throw new \RuntimeException( 'SMTP caído' );
			},
			function (): string {
				return 'admin@example.com';
			}
		);

		$this->assertFalse( $report->send( $this->outcome( ScanOutcome::ORIGIN_MANUAL ), $this->settings() ) );
	}

	public function test_send_if_due_no_envia_si_no_corresponde(): void {
		$called = false;

		$report = $this->make_report(
			function () use ( &$called ): bool {
				$called = true;

				return true;
			},
			function (): string {
				return 'admin@example.com';
			}
		);

		$this->assertFalse(
			$report->send_if_due( $this->outcome( ScanOutcome::ORIGIN_SCHEDULED ), $this->settings( Settings::FREQUENCY_DAILY, false ), null )
		);
		$this->assertFalse( $called );
	}

	public function test_body_incluye_score_y_resumen(): void {
		$report = $this->make_report();
		$body   = $report->body( $this->outcome( ScanOutcome::ORIGIN_SCHEDULED ) );

		$this->assertStringContainsString( '80/100', $body );
		$this->assertStringContainsString( 'Vulnerabilidades activas: 1', $body );
		$this->assertStringContainsString( 'Actualizaciones pendientes: 1', $body );
		$this->assertStringContainsString( 'XSS en formulario', $body );
		$this->assertStringContainsString( 'Actualiza a una versión segura.', $body );
	}

	public function test_body_sin_hallazgos_informa_limpieza(): void {
		$report = $this->make_report();
		$outcome = ScanOutcome::from_results(
			array( new Result( 'all/ok', 'Todo correcto', Result::SEVERITY_OK, null ) ),
			ScanOutcome::ORIGIN_SCHEDULED
		);

		$this->assertStringContainsString( 'No se han detectado hallazgos relevantes.', $report->body( $outcome ) );
		$this->assertStringNotContainsString( '- [', $report->body( $outcome ) );
	}

	public function test_body_recorta_hallazgos_al_maximo(): void {
		$findings = array();
		$amount   = 5;

		for ( $i = 0; $i < $amount; $i++ ) {
			$findings[] = new Result( 'plugin/p' . $i, 'Hallazgo ' . $i, Result::SEVERITY_WARNING, null, 'Revisa.', 1 );
		}

		$outcome = ScanOutcome::from_results( $findings, ScanOutcome::ORIGIN_SCHEDULED );
		$body    = $this->make_report()->body( $outcome );

		$this->assertStringContainsString( '- [', $body );
		$this->assertNotSame( '', $body );
	}
}