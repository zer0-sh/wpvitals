<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Admin\DashboardData;
use WPVitals\Result;
use WPVitals\ScanOutcome;
use WPVitals\Score;

final class DashboardDataTest extends TestCase {

	private function busy_outcome(): ScanOutcome {
		return ScanOutcome::from_results(
			array(
				new Result( 'core/version', 'WordPress activo', Result::SEVERITY_OK, '6.5.2' ),
				new Result( 'security/https', 'El sitio no usa HTTPS', Result::SEVERITY_WARNING, null, 'Activa HTTPS', 5 ),
				new Result( 'plugin/akismet', 'Plugin desactualizado', Result::SEVERITY_WARNING, array( 'new_version' => '1.2' ), 'Actualiza', 5 ),
				new Result( 'vuln/plugin/contact-form-7/p1', 'XSS crítica', Result::SEVERITY_CRITICAL, array( 'screen' => 'plugins', 'link' => 'https://example.com/cve' ), 'Actualiza el componente', 15 ),
				new Result( 'test/broken', 'Check roto', Result::SEVERITY_ERROR, null, 'Error interno del check', 0 ),
			),
			ScanOutcome::ORIGIN_SCHEDULED,
			1700000000
		);
	}

	public function test_sin_resultado_devuelve_estado_vacio(): void {
		$data = DashboardData::build( null );

		$this->assertFalse( $data['has_result'] );
		$this->assertNull( $data['score_total'] );
		$this->assertSame( 0, $data['vulnerabilities_count'] );
		$this->assertSame( 0, $data['pending_updates_count'] );
		$this->assertSame( array(), $data['categories'] );
		$this->assertSame( array(), $data['ignored'] );
		$this->assertSame( 0, $data['ignored_count'] );
	}

	public function test_agrega_score_conteos_y_datos_del_escaneo(): void {
		$data = DashboardData::build( $this->busy_outcome() );

		$this->assertTrue( $data['has_result'] );
		$this->assertSame( 75, $data['score_total'] );
		$this->assertSame( Score::STATE_ATTENTION, $data['score_state'] );
		$this->assertSame( 'Requiere atención', $data['score_label'] );
		$this->assertSame( 1700000000, $data['completed_at'] );
		$this->assertSame( ScanOutcome::ORIGIN_SCHEDULED, $data['origin'] );
		$this->assertSame( 'Programado', $data['origin_label'] );
		$this->assertSame( 1, $data['vulnerabilities_count'] );
		$this->assertSame( 1, $data['pending_updates_count'] );
	}

	public function test_agrupa_hallazgos_por_categoria_en_orden(): void {
		$data   = DashboardData::build( $this->busy_outcome() );
		$groups = $data['categories'];

		$this->assertSame( array( 'security', 'plugin', 'vuln', 'test' ), array_column( $groups, 'key' ) );
		$this->assertCount( 1, $groups[0]['findings'] );
		$this->assertSame( 'Seguridad', $groups[0]['label'] );
	}

	public function test_excluye_los_resultados_ok(): void {
		$data = DashboardData::build( $this->busy_outcome() );

		foreach ( $data['categories'] as $group ) {
			foreach ( $group['findings'] as $finding ) {
				$this->assertNotSame( 'core/version', $finding['id'] );
			}
		}
	}

	public function test_hallazgo_vuln_expone_link_y_pantalla(): void {
		$data = DashboardData::build( $this->busy_outcome() );

		$vuln = null;

		foreach ( $data['categories'] as $group ) {
			foreach ( $group['findings'] as $finding ) {
				if ( 0 === strpos( $finding['id'], 'vuln/' ) ) {
					$vuln = $finding;
				}
			}
		}

		$this->assertNotNull( $vuln );
		$this->assertSame( 'plugins', $vuln['screen'] );
		$this->assertSame( 'https://example.com/cve', $vuln['link'] );
		$this->assertSame( 'Crítico', $vuln['severity_label'] );
		$this->assertSame( 15, $vuln['points'] );
		$this->assertStringContainsString( 'WPScan', $vuln['description'] );
	}

	public function test_varias_cves_del_mismo_componente_se_agrupan_y_descuentan_una_vez(): void {
		$outcome = ScanOutcome::from_results(
			array(
				new Result( 'vuln/plugin/contact-form-7/CVE-1', 'XSS crítica', Result::SEVERITY_CRITICAL, array( 'screen' => 'plugins', 'link' => 'https://example.com/cve1' ), 'Actualiza el componente', 20 ),
				new Result( 'vuln/plugin/contact-form-7/CVE-2', 'SSRF media', Result::SEVERITY_WARNING, array( 'screen' => 'plugins', 'link' => 'https://example.com/cve2' ), 'Actualiza el componente', 5 ),
			),
			ScanOutcome::ORIGIN_MANUAL,
			1700000000
		);

		$data = DashboardData::build( $outcome );

		$this->assertSame( 80, $data['score_total'] );

		$vuln_group = null;

		foreach ( $data['categories'] as $group ) {
			if ( 'vuln' === $group['key'] ) {
				$vuln_group = $group;
			}
		}

		$this->assertNotNull( $vuln_group );
		$this->assertCount( 1, $vuln_group['findings'] );

		$finding = $vuln_group['findings'][0];

		$this->assertSame( 'vuln/plugin/contact-form-7', $finding['id'] );
		$this->assertSame( 20, $finding['points'] );
		$this->assertSame( '', $finding['link'] );
		$this->assertSame( 'plugins', $finding['screen'] );
		$this->assertStringContainsString( '2 CVEs', $finding['recommendation'] );
		$this->assertStringContainsString( 'contact-form-7', $finding['recommendation'] );
		$this->assertCount( 2, $finding['vulnerabilities'] );
		$this->assertSame( 'XSS crítica', $finding['vulnerabilities'][0]['title'] );
		$this->assertSame( 'vuln/plugin/contact-form-7/CVE-2', $finding['vulnerabilities'][1]['id'] );
	}

	public function test_hallazgo_sin_pantalla_ni_link_queda_manejable(): void {
		$data = DashboardData::build( $this->busy_outcome() );

		$broken = null;

		foreach ( $data['categories'] as $group ) {
			foreach ( $group['findings'] as $finding ) {
				if ( 'test/broken' === $finding['id'] ) {
					$broken = $finding;
				}
			}
		}

		$this->assertNotNull( $broken );
		$this->assertNull( $broken['screen'] );
		$this->assertSame( '', $broken['link'] );
		$this->assertSame( 'Error', $broken['severity_label'] );
	}

	public function test_ignorados_se_excluyen_del_score_conteos_y_categorias(): void {
		$data = DashboardData::build( $this->busy_outcome(), array( 'security/https' ) );

		$this->assertSame( 80, $data['score_total'] );
		$this->assertSame( 1, $data['vulnerabilities_count'] );
		$this->assertSame( 1, $data['pending_updates_count'] );
		$this->assertSame( array( 'plugin', 'vuln', 'test' ), array_column( $data['categories'], 'key' ) );
		$this->assertSame( 1, $data['ignored_count'] );
		$this->assertSame( 'security/https', $data['ignored'][0]['id'] );
	}

	public function test_ignorados_de_check_ok_no_engañan_al_conteo(): void {
		$data = DashboardData::build( $this->busy_outcome(), array( 'core/version' ) );

		$this->assertSame( 75, $data['score_total'] );
		$this->assertSame( 0, $data['ignored_count'] );
	}

	public function test_categoria_de_cabeceras_se_etiqueta_y_agrupa(): void {
		$outcome = ScanOutcome::from_results(
			array(
				new Result( 'headers/x-frame-options', 'Cabecera X-Frame-Options', Result::SEVERITY_WARNING, null, 'Previene clickjacking', 2 ),
			),
			ScanOutcome::ORIGIN_MANUAL,
			1700000000
		);

		$data = DashboardData::build( $outcome );

		$this->assertSame( array( 'headers' ), array_column( $data['categories'], 'key' ) );
		$this->assertSame( 'Cabeceras de seguridad', $data['categories'][0]['label'] );
		$this->assertFalse( $data['categories'][0]['findings'][0]['is_ok'] );
	}

	public function test_abajo_cabeceras_ausentes_se_muestran_las_presentes_con_valor(): void {
		$outcome = ScanOutcome::from_results(
			array(
				new Result( 'headers/x-frame-options', 'Cabecera X-Frame-Options', Result::SEVERITY_WARNING, null, 'Previene clickjacking', 2 ),
				new Result( 'headers/x-content-type-options', 'Cabecera X-Content-Type-Options', Result::SEVERITY_OK, 'nosniff' ),
				new Result( 'front/ok', 'Chequeo sin problemas', Result::SEVERITY_OK, null ),
			),
			ScanOutcome::ORIGIN_MANUAL,
			1700000000
		);

		$data   = DashboardData::build( $outcome );
		$remote = array_column( $data['categories'][0]['findings'], null, 'id' );

		$this->assertArrayHasKey( 'headers/x-frame-options', $remote );
		$this->assertArrayHasKey( 'headers/x-content-type-options', $remote );
		$this->assertTrue( $remote['headers/x-content-type-options']['is_ok'] );
		$this->assertSame( 'nosniff', $remote['headers/x-content-type-options']['value'] );
	}

	public function test_cabeceras_todas_presentes_no_generan_categoria(): void {
		$outcome = ScanOutcome::from_results(
			array(
				new Result( 'headers/x-frame-options', 'Cabecera X-Frame-Options', Result::SEVERITY_OK, 'SAMEORIGIN' ),
				new Result( 'front/ok', 'Chequeo sin problemas', Result::SEVERITY_OK, null ),
			),
			ScanOutcome::ORIGIN_SCHEDULED,
			1700000000
		);

		$data = DashboardData::build( $outcome );

		$this->assertSame( array(), $data['categories'] );
	}

	public function test_etiquetas_de_estados_y_severidades(): void {
		$this->assertSame( 'Saludable', DashboardData::score_label( Score::STATE_HEALTHY ) );
		$this->assertSame( 'Crítico', DashboardData::score_label( Score::STATE_CRITICAL ) );
		$this->assertSame( 'Manual', DashboardData::origin_label( ScanOutcome::ORIGIN_MANUAL ) );
		$this->assertSame( 'Aviso', DashboardData::severity_label( Result::SEVERITY_WARNING ) );
		$this->assertSame( 'Núcleo de WordPress', DashboardData::category_label( 'core' ) );
		$this->assertSame( 'Cabeceras de seguridad', DashboardData::category_label( 'headers' ) );
	}
}