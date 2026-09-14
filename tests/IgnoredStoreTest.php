<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\IgnoredStore;

final class IgnoredStoreTest extends TestCase {

	private $storage;

	private function store(): IgnoredStore {
		$this->storage = array();

		return new IgnoredStore(
			function ( string $key ) {
				return isset( $this->storage[ $key ] ) ? $this->storage[ $key ] : null;
			},
			function ( string $key, array $value ): void {
				$this->storage[ $key ] = $value;
			}
		);
	}

	public function test_devuelve_lista_vacia_sin_datos(): void {
		$this->assertSame( array(), $this->store()->get() );
	}

	public function test_ignora_opcion_con_formato_incorrecto(): void {
		$store = new IgnoredStore(
			function ( string $key ): string {
				unset( $key );
				return 'esto-no-es-array';
			},
			function ( string $key, array $value ): void {
				unset( $key, $value );
			}
		);

		$this->assertSame( array(), $store->get() );
	}

	public function test_add_y_remove_actualizan_lista_unica(): void {
		$store = $this->store();

		$store->add( 'security/https' );
		$store->add( 'plugin/akismet' );
		$store->add( 'security/https' );

		$this->assertSame( array( 'security/https', 'plugin/akismet' ), $store->get() );

		$store->remove( 'security/https' );

		$this->assertSame( array( 'plugin/akismet' ), $store->get() );

		$store->remove( 'plugin/akismet' );

		$this->assertSame( array(), $store->get() );
	}

	public function test_clear_vacia_la_lista_por_completo(): void {
		$store = $this->store();

		$store->add( 'security/https' );
		$store->add( 'plugin/akismet' );
		$store->clear();

		$this->assertSame( array(), $store->get() );
	}

	public function test_sanitize_id_acepta_ids_validos(): void {
		$this->assertSame( 'security/https', IgnoredStore::sanitize_id( ' security/https ' ) );
		$this->assertSame( 'vuln/plugin/a/b/c', IgnoredStore::sanitize_id( 'vuln/plugin/a/b/c' ) );
		$this->assertSame( '', IgnoredStore::sanitize_id( '  ../evil ' ) );
		$this->assertSame( '', IgnoredStore::sanitize_id( 42 ) );
		$this->assertSame( '', IgnoredStore::sanitize_id( '' ) );
	}
}