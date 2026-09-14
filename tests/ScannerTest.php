<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checker;
use WPVitals\Result;
use WPVitals\ScanOutcome;
use WPVitals\Scanner;
use WPVitals\ScoreDiscounts;
use WPVitals\Tests\Checks\MultiOkCheck;
use WPVitals\Tests\Checks\OkCheck;
use WPVitals\Vulnerability;
use WPVitals\VulnerabilityClient;

final class ScannerTest extends TestCase {

	private $requests = array();

	private $store = array();

	private function plugin_body(): string {
		return '{
			"error":0,"message":null,
			"data":{"name":"Contact Form 7","plugin":"contact-form-7",
				"vulnerability":[
					{"uuid":"p1","name":"XSS critica","description":null,"source":[{"id":"CVE-X","name":"CVE-X","link":"https://example.com/x","date":"2024-01-01"}],"operator":{"min_version":null,"min_operator":null,"max_version":"5.9","max_operator":"lt"},"impact":{"cvss3":{"severity":"critical"}}},
					{"uuid":"p2","name":"Baja","description":null,"source":[{"id":"CVE-Y","name":"CVE-Y","link":"https://example.com/y","date":"2024-01-01"}],"operator":{"min_version":null,"min_operator":null,"max_version":"5.9","max_operator":"lt"},"impact":{"cvss3":{"severity":"low"}}}
				]},
			"updated":1234567890
		}';
	}

	private function make_client( string $body, bool $throw = false ): VulnerabilityClient {
		$this->requests = array();
		$this->store    = array();

		return new VulnerabilityClient(
			function ( string $url ) use ( $body, $throw ): array {
				$this->requests[] = $url;

				if ( $throw ) {
					throw new \RuntimeException( 'timeout' );
				}

				return array(
					'code' => 200,
					'body' => $body,
				);
			},
			function ( string $key ) {
				return isset( $this->store[ $key ] ) ? $this->store[ $key ] : false;
			},
			function ( string $key, array $value ): void {
				$this->store[ $key ] = $value;
			}
		);
	}

	private function request_count(): int {
		return count( $this->requests );
	}

	private function make_scanner( VulnerabilityClient $client, array $plugins = array(), string $core = '' ): Scanner {
		$checker = new Checker();
		$checker->add_check( new OkCheck() );
		$checker->add_check( new MultiOkCheck() );

		return new Scanner(
			$checker,
			$client,
			static function () use ( $core ): string {
				return $core;
			},
			static function () use ( $plugins ): array {
				return $plugins;
			},
			static function (): array {
				return array();
			},
			static function (): int {
				return 1700000000;
			}
		);
	}

	public function test_escaneo_completo_combina_locales_y_vulnerabilidades(): void {
		$client  = $this->make_client( $this->plugin_body() );
		$scanner = $this->make_scanner( $client, array( 'contact-form-7' => '5.0.0' ) );

		$outcome = $scanner->scan( ScanOutcome::ORIGIN_SCHEDULED );

		$this->assertCount( 5, $outcome->get_results() );
		$this->assertSame( ScanOutcome::ORIGIN_SCHEDULED, $outcome->get_origin() );
		$this->assertSame( 1700000000, $outcome->get_completed_at() );
		$this->assertSame( 2, $outcome->count_vulnerabilities() );
		$this->assertSame( 0, $outcome->count_pending_updates() );
		$this->assertSame( 85, $outcome->get_total() );

		$ids = array_map( static function ( Result $result ): string {
			return $result->get_id();
		}, $outcome->get_results() );

		$this->assertContains( 'test/ok', $ids );
		$this->assertContains( 'vuln/plugin/contact-form-7/p1', $ids );
	}

	public function test_vulnerabilidad_critica_se_mapea_a_critical(): void {
		$client  = $this->make_client( $this->plugin_body() );
		$scanner = $this->make_scanner( $client, array( 'contact-form-7' => '5.0.0' ) );

		$outcome = $scanner->scan( ScanOutcome::ORIGIN_SCHEDULED );

		foreach ( $outcome->get_results() as $result ) {
			if ( 'vuln/plugin/contact-form-7/p1' === $result->get_id() ) {
				$this->assertSame( Result::SEVERITY_CRITICAL, $result->get_severity() );
				$this->assertSame( ScoreDiscounts::VULN_CRITICAL, $result->get_points_deducted() );
				$this->assertSame( 'plugins', $result->get_value()['screen'] );
				$this->assertSame( 'https://example.com/x', $result->get_value()['link'] );
			}

			if ( 'vuln/plugin/contact-form-7/p2' === $result->get_id() ) {
				$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
				$this->assertSame( ScoreDiscounts::VULN_LOW, $result->get_points_deducted() );
			}
		}
	}

	public function test_escaneo_manual_fuerza_refresco_de_cache(): void {
		$client  = $this->make_client( $this->plugin_body() );
		$scanner = $this->make_scanner( $client, array( 'contact-form-7' => '5.0.0' ) );

		$scanner->scan( ScanOutcome::ORIGIN_SCHEDULED );
		$scanner->scan( ScanOutcome::ORIGIN_MANUAL );

		$this->assertSame( 2, $this->request_count() );
	}

	public function test_escaneo_programado_respeta_cache(): void {
		$client  = $this->make_client( $this->plugin_body() );
		$scanner = $this->make_scanner( $client, array( 'contact-form-7' => '5.0.0' ) );

		$scanner->scan( ScanOutcome::ORIGIN_SCHEDULED );
		$outcome = $scanner->scan( ScanOutcome::ORIGIN_SCHEDULED );

		$this->assertSame( 1, $this->request_count() );
		$this->assertSame( 2, $outcome->count_vulnerabilities() );
	}

	public function test_fallo_de_api_no_rompe_el_escaneo(): void {
		$client  = $this->make_client( $this->plugin_body(), true );
		$scanner = $this->make_scanner( $client, array( 'contact-form-7' => '5.0.0' ) );

		$outcome = $scanner->scan( ScanOutcome::ORIGIN_SCHEDULED );

		$this->assertCount( 3, $outcome->get_results() );
		$this->assertSame( 0, $outcome->count_vulnerabilities() );
	}

	public function test_componentes_sin_version_no_generan_consultas(): void {
		$client  = $this->make_client( $this->plugin_body() );
		$scanner = $this->make_scanner( $client, array( 'contact-form-7' => '' ) );

		$outcome = $scanner->scan( ScanOutcome::ORIGIN_SCHEDULED );

		$this->assertCount( 3, $outcome->get_results() );
		$this->assertSame( 0, $this->request_count() );
	}

	public function test_origen_desconocido_lanza_excepcion(): void {
		$client  = $this->make_client( $this->plugin_body() );
		$scanner = $this->make_scanner( $client );

		$this->expectException( \InvalidArgumentException::class );

		$scanner->scan( 'cron' );
	}
}