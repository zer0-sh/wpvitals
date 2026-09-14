<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Result;
use WPVitals\ScanOutcome;
use WPVitals\Score;

final class ScanOutcomeTest extends TestCase {

	public function test_crea_outcome_y_calcula_score(): void {
		$outcome = ScanOutcome::from_results(
			array( new Result( 'ok', 'Bien', Result::SEVERITY_OK, null ) ),
			ScanOutcome::ORIGIN_SCHEDULED,
			1700000000
		);

		$this->assertSame( ScanOutcome::ORIGIN_SCHEDULED, $outcome->get_origin() );
		$this->assertSame( 1700000000, $outcome->get_completed_at() );
		$this->assertInstanceOf( Score::class, $outcome->get_score() );
		$this->assertSame( 100, $outcome->get_total() );
		$this->assertSame( Score::STATE_HEALTHY, $outcome->get_state() );
	}

	public function test_origen_invalido_lanza_excepcion(): void {
		$this->expectException( \InvalidArgumentException::class );

		ScanOutcome::from_results( array(), 'cron' );
	}

	public function test_descarta_elementos_que_no_son_results(): void {
		$outcome = ScanOutcome::from_results(
			array(
				new Result( 'a', 'A', Result::SEVERITY_OK, null ),
				'no-es-un-result',
			),
			ScanOutcome::ORIGIN_MANUAL
		);

		$this->assertCount( 1, $outcome->get_results() );
	}

	public function test_cuenta_vulnerabilidades_y_actualizaciones_pendientes(): void {
		$outcome = ScanOutcome::from_results(
			array(
				new Result( 'vuln/plugin/cf7/p1', 'XSS', Result::SEVERITY_WARNING, null ),
				new Result( 'plugin/akismet', 'Desactualizado', Result::SEVERITY_WARNING, null ),
				new Result( 'theme/twenty', 'Desactualizado', Result::SEVERITY_WARNING, null ),
				new Result( 'components/plugins', 'OK', Result::SEVERITY_OK, null ),
			),
			ScanOutcome::ORIGIN_SCHEDULED
		);

		$this->assertSame( 1, $outcome->count_vulnerabilities() );
		$this->assertSame( 2, $outcome->count_pending_updates() );
	}

	public function test_roundtrip_plano_persiste_resultados(): void {
		$outcome = ScanOutcome::from_results(
			array(
				new Result( 'vuln/plugin/cf7/p1', 'XSS', Result::SEVERITY_CRITICAL, array( 'screen' => 'plugins' ), 'Actualiza', 15 ),
				new Result( 'core/version', 'HTTP', Result::SEVERITY_OK, '6.5.2' ),
			),
			ScanOutcome::ORIGIN_MANUAL,
			1700000000
		);

		$rebuilt = ScanOutcome::from_array( $outcome->to_array() );

		$this->assertSame( $outcome->to_array(), $rebuilt->to_array() );
		$this->assertSame( 85, $rebuilt->get_total() );
	}

	public function test_from_array_descarta_resultados_corruptos(): void {
		$outcome = ScanOutcome::from_array(
			array(
				'completed_at' => 1700000000,
				'origin'       => ScanOutcome::ORIGIN_MANUAL,
				'results'      => array(
					array( 'id' => 'a', 'title' => 'Bien', 'severity' => Result::SEVERITY_OK ),
					array( 'sin_campos' => true ),
				),
			)
		);

		$this->assertCount( 1, $outcome->get_results() );
		$this->assertSame( 'a', $outcome->get_results()[0]->get_id() );
	}

	public function test_from_array_origen_por_defecto_programado(): void {
		$outcome = ScanOutcome::from_array( array( 'completed_at' => 1700000000, 'results' => array() ) );

		$this->assertSame( ScanOutcome::ORIGIN_SCHEDULED, $outcome->get_origin() );
		$this->assertSame( 1700000000, $outcome->get_completed_at() );
	}

	public function test_signature_ignora_metadatos_y_orden(): void {
		$first = ScanOutcome::from_results(
			array(
				new Result( 'b', 'B', Result::SEVERITY_WARNING, null, '', 5 ),
				new Result( 'a', 'A', Result::SEVERITY_OK, null ),
			),
			ScanOutcome::ORIGIN_SCHEDULED,
			1700000000
		);

		$reordered = ScanOutcome::from_results(
			array(
				new Result( 'a', 'A', Result::SEVERITY_OK, null ),
				new Result( 'b', 'B', Result::SEVERITY_WARNING, null, '', 5 ),
			),
			ScanOutcome::ORIGIN_MANUAL,
			1700000100
		);

		$this->assertSame( $first->signature(), $reordered->signature() );
	}

	public function test_signature_cambia_al_cambiar_la_severidad(): void {
		$base = ScanOutcome::from_results(
			array( new Result( 'a', 'A', Result::SEVERITY_WARNING, null ) ),
			ScanOutcome::ORIGIN_SCHEDULED
		);

		$critical = ScanOutcome::from_results(
			array( new Result( 'a', 'A', Result::SEVERITY_CRITICAL, null ) ),
			ScanOutcome::ORIGIN_SCHEDULED
		);

		$this->assertNotSame( $base->signature(), $critical->signature() );
	}

	public function test_signature_cambia_con_diferente_conjunto_de_resultados(): void {
		$one = ScanOutcome::from_results(
			array( new Result( 'a', 'A', Result::SEVERITY_OK, null ) ),
			ScanOutcome::ORIGIN_SCHEDULED
		);

		$two = ScanOutcome::from_results(
			array(
				new Result( 'a', 'A', Result::SEVERITY_OK, null ),
				new Result( 'b', 'B', Result::SEVERITY_WARNING, null, '', 5 ),
			),
			ScanOutcome::ORIGIN_SCHEDULED
		);

		$this->assertNotSame( $one->signature(), $two->signature() );
	}

	public function test_signature_estable_para_escaneos_identicos(): void {
		$first = ScanOutcome::from_results(
			array( new Result( 'a', 'A', Result::SEVERITY_OK, null ) ),
			ScanOutcome::ORIGIN_SCHEDULED,
			1700000000
		);

		$second = ScanOutcome::from_results(
			array( new Result( 'a', 'A', Result::SEVERITY_OK, null ) ),
			ScanOutcome::ORIGIN_SCHEDULED,
			1800000000
		);

		$this->assertSame( $first->signature(), $second->signature() );
	}
}