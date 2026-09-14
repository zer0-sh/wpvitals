<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Admin\SiteInfo;

final class SiteInfoTest extends TestCase {

	public function test_usa_las_fuentes_inyectadas(): void {
		$data = SiteInfo::build(
			static function (): string {
				return 'https://example.com/';
			},
			static function (): string {
				return '8.2';
			},
			static function (): string {
				return '6.5.4';
			},
			static function (): string {
				return 'nginx/1.24.0';
			}
		);

		$this->assertSame( 'https://example.com/', $data['url'] );
		$this->assertSame( '8.2', $data['php'] );
		$this->assertSame( '6.5.4', $data['core'] );
		$this->assertSame( 'nginx/1.24.0', $data['server'] );
	}

	public function test_devuelve_cadenas_si_la_fuente_es_null(): void {
		$data = SiteInfo::build(
			static function (): ?string {
				return null;
			},
			static function (): ?string {
				return null;
			},
			static function (): ?string {
				return null;
			},
			static function (): ?string {
				return null;
			}
		);

		$this->assertSame( '', $data['url'] );
		$this->assertSame( '', $data['php'] );
		$this->assertSame( '', $data['core'] );
		$this->assertSame( '', $data['server'] );
	}
}