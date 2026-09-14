<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Result;
use WPVitals\Score;

final class ScoreTest extends TestCase {

	private function result( string $id, int $points ): Result {
		return new Result(
			$id,
			'Sin usar',
			Result::SEVERITY_WARNING,
			null,
			'',
			$points
		);
	}

	public function test_score_perfecto_sin_hallazgos(): void {
		$score = Score::from_results( array() );

		$this->assertSame( 100, $score->get_total() );
		$this->assertSame( Score::STATE_HEALTHY, $score->get_state() );
		$this->assertSame( array(), $score->get_deductions() );
	}

	public function test_score_perfecto_con_hallazgos_ok(): void {
		$score = Score::from_results(
			array(
				new Result( 'core/version', 'OK', Result::SEVERITY_OK, null ),
				new Result( 'security/https', 'Info', Result::SEVERITY_INFO, null ),
				'no soy un result',
				42,
			)
		);

		$this->assertSame( 100, $score->get_total() );
		$this->assertSame( Score::STATE_HEALTHY, $score->get_state() );
		$this->assertSame( array(), $score->get_deductions() );
	}

	public function test_descuentos_acumulados(): void {
		$score = Score::from_results(
			array(
				$this->result( 'php/version', 10 ),
				$this->result( 'components/plugins', 5 ),
			)
		);

		$this->assertSame( 85, $score->get_total() );
		$this->assertSame( Score::STATE_ATTENTION, $score->get_state() );
		$this->assertCount( 2, $score->get_deductions() );
	}

	public function test_descuentos_transparentes(): void {
		$score = Score::from_results( array( $this->result( 'security/debug', 5 ) ) );

		$this->assertSame(
			array(
				array(
					'id'     => 'security/debug',
					'title'  => 'Sin usar',
					'points' => 5,
				),
			),
			$score->get_deductions()
		);
	}

	public function test_limite_inferior_se_mantiene_en_cero(): void {
		$score = Score::from_results(
			array(
				$this->result( 'a', 40 ),
				$this->result( 'b', 40 ),
				$this->result( 'c', 40 ),
			)
		);

		$this->assertSame( 0, $score->get_total() );
		$this->assertSame( Score::STATE_CRITICAL, $score->get_state() );
	}

	public function test_vulnerabilidades_del_mismo_componente_descuentan_una_vez(): void {
		$score = Score::from_results(
			array(
				$this->result( 'vuln/plugin/contact-form-7/CVE-1', 20 ),
				$this->result( 'vuln/plugin/contact-form-7/CVE-2', 5 ),
			)
		);

		$this->assertSame( 80, $score->get_total() );
		$this->assertCount( 1, $score->get_deductions() );
		$this->assertSame( 'vuln/plugin/contact-form-7/CVE-1', $score->get_deductions()[0]['id'] );
		$this->assertSame( 20, $score->get_deductions()[0]['points'] );
	}

	public function test_vulnerabilidades_de_distintos_componentes_descuentan_cada_una(): void {
		$score = Score::from_results(
			array(
				$this->result( 'vuln/plugin/contact-form-7/CVE-1', 20 ),
				$this->result( 'vuln/plugin/akismet/CVE-2', 5 ),
			)
		);

		$this->assertSame( 75, $score->get_total() );
		$this->assertCount( 2, $score->get_deductions() );
	}

	public function test_vulnerabilidades_del_mismo_componente_toman_la_peor(): void {
		$score = Score::from_results(
			array(
				$this->result( 'vuln/plugin/contact-form-7/CVE-1', 5 ),
				$this->result( 'vuln/plugin/contact-form-7/CVE-2', 20 ),
			)
		);

		$this->assertSame( 80, $score->get_total() );
		$this->assertSame( 'vuln/plugin/contact-form-7/CVE-2', $score->get_deductions()[0]['id'] );
	}

	/**
	 * @dataProvider provider_limites_de_estado
	 */
	public function test_limites_de_cada_estado( int $points, int $expected_total, string $expected_state ): void {
		$score = Score::from_results( array( $this->result( 'check', $points ) ) );

		$this->assertSame( $expected_total, $score->get_total() );
		$this->assertSame( $expected_state, $score->get_state() );
	}

	public function provider_limites_de_estado(): array {
		return array(
			'90 falla a aware'    => array( 10, 90, Score::STATE_HEALTHY ),
			'89 falla a attention' => array( 11, 89, Score::STATE_ATTENTION ),
			'70 es attention'      => array( 30, 70, Score::STATE_ATTENTION ),
			'69 es critical'       => array( 31, 69, Score::STATE_CRITICAL ),
		);
	}
}