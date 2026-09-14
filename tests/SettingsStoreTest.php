<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Settings;
use WPVitals\SettingsStore;

final class SettingsStoreTest extends TestCase {

	private $options = array();

	private function make_store(): SettingsStore {
		$this->options = array();

		return new SettingsStore(
			function ( string $key ) {
				return array_key_exists( $key, $this->options ) ? $this->options[ $key ] : null;
			},
			function ( string $key, array $value ): void {
				$this->options[ $key ] = $value;
			}
		);
	}

	public function test_sin_opcion_devuelve_defaults(): void {
		$settings = $this->make_store()->get();

		$this->assertSame( Settings::FREQUENCY_DAILY, $settings->get_frequency() );
		$this->assertTrue( $settings->is_mail_enabled() );
	}

	public function test_save_y_get_reconstruye_los_ajustes(): void {
		$store    = $this->make_store();
		$original = new Settings( Settings::FREQUENCY_WEEKLY, false, 'ops@example.com' );

		$store->save( $original );

		$this->assertSame( $original->to_array(), $store->get()->to_array() );
	}

	public function test_guarda_bajo_el_nombre_de_opcion_estable(): void {
		$store = $this->make_store();
		$store->save( Settings::defaults() );

		$this->assertArrayHasKey( SettingsStore::OPTION_NAME, $this->options );
	}

	public function test_opcion_corrupta_devuelve_defaults(): void {
		$this->options = array( SettingsStore::OPTION_NAME => array( 'frequency' => 'hourly' ) );
		$store         = new SettingsStore(
			function ( string $key ) {
				return isset( $this->options[ $key ] ) ? $this->options[ $key ] : null;
			},
			function ( string $key, array $value ): void {
				$this->options[ $key ] = $value;
			}
		);

		$this->assertSame( Settings::FREQUENCY_DAILY, $store->get()->get_frequency() );
	}

	public function test_opcion_no_array_devuelve_defaults(): void {
		$this->options = array( SettingsStore::OPTION_NAME => 'cacharro' );
		$store         = $this->make_store();

		$this->assertSame( Settings::defaults()->to_array(), $store->get()->to_array() );
	}
}