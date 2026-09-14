<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Result;
use WPVitals\ScanOutcome;
use WPVitals\ScanStore;

final class ScanStoreTest extends TestCase {

	private $options = array();

	private function make_store(): ScanStore {
		$this->options = array();

		return new ScanStore(
			function ( string $key ) {
				return array_key_exists( $key, $this->options ) ? $this->options[ $key ] : null;
			},
			function ( string $key, array $value ): void {
				$this->options[ $key ] = $value;
			}
		);
	}

	public function test_save_y_get_reconstruye_el_outcome(): void {
		$store = $this->make_store();

		$original = ScanOutcome::from_results(
			array(
				new Result( 'vuln/plugin/cf7/p1', 'XSS', Result::SEVERITY_CRITICAL, array( 'screen' => 'plugins' ), 'Actualiza', 15 ),
				new Result( 'test/ok', 'Bien', Result::SEVERITY_OK, true ),
			),
			ScanOutcome::ORIGIN_MANUAL,
			1700000000
		);

		$store->save( $original );
		$rebuilt = $store->get();

		$this->assertInstanceOf( ScanOutcome::class, $rebuilt );
		$this->assertSame( $original->to_array(), $rebuilt->to_array() );
		$this->assertSame( ScanOutcome::ORIGIN_MANUAL, $rebuilt->get_origin() );
		$this->assertSame( 1, $rebuilt->count_vulnerabilities() );
	}

	public function test_guarda_bajo_el_nombre_de_opcion_estable(): void {
		$store = $this->make_store();

		$store->save( ScanOutcome::from_results( array(), ScanOutcome::ORIGIN_SCHEDULED ) );

		$this->assertArrayHasKey( ScanStore::OPTION_NAME, $this->options );
	}

	public function test_sin_resultado_devuelve_null(): void {
		$store = $this->make_store();

		$this->assertNull( $store->get() );
	}

	public function test_opcion_corrupta_devuelve_null(): void {
		$this->options = array( ScanStore::OPTION_NAME => 'esto-no-es-un-escaneo' );
		$store         = new ScanStore(
			function ( string $key ) {
				return isset( $this->options[ $key ] ) ? $this->options[ $key ] : null;
			},
			function ( string $key, array $value ): void {
				$this->options[ $key ] = $value;
			}
		);

		$this->assertNull( $store->get() );
	}

	public function test_origen_invalido_persistido_devuelve_null(): void {
		$store = $this->make_store();
		$store->save( ScanOutcome::from_results( array(), ScanOutcome::ORIGIN_SCHEDULED ) );
		$this->options[ ScanStore::OPTION_NAME ]['origin'] = 'raro';

		$this->assertNull( $store->get() );
	}
}