<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checks\PluginsUpdatesCheck;
use WPVitals\Checks\ThemesUpdatesCheck;
use WPVitals\Result;

final class UpdatesCheckTest extends TestCase {

	const UPDATES_URL = 'https://example.test/wp-admin/update-core.php';

	public function test_lista_de_plugins_vacia_ok(): void {
		$result = $this->plugins_check( array() )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'components/plugins', $result->get_id() );
	}

	public function test_plugins_actualizados_ok(): void {
		$results = $this->plugins_check( array() )->run_many();

		$this->assertCount( 1, $results );
		$this->assertTrue( $results[0]->is_ok() );
	}

	public function test_plugins_desactualizados_genera_un_resultado_por_plugin(): void {
		$results = $this->plugins_check(
			array(
				'akismet/akismet.php'  => '5.3',
				'hello.php'            => '1.8.0',
			)
		)->run_many();

		$this->assertCount( 2, $results );

		$this->assertSame( 'plugin/akismet', $results[0]->get_id() );
		$this->assertSame( Result::SEVERITY_WARNING, $results[0]->get_severity() );
		$this->assertSame( 5, $results[0]->get_points_deducted() );
		$this->assertStringContainsString( '5.3', $results[0]->get_recommendation() );
		$this->assertStringContainsString( self::UPDATES_URL, $results[0]->get_recommendation() );

		$this->assertSame( 'plugin/hello', $results[1]->get_id() );
		$this->assertSame( Result::SEVERITY_WARNING, $results[1]->get_severity() );
	}

	public function test_lista_de_temas_vacia_ok(): void {
		$result = $this->themes_check( array() )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'components/themes', $result->get_id() );
	}

	public function test_temas_actualizados_ok(): void {
		$results = $this->themes_check( array() )->run_many();

		$this->assertCount( 1, $results );
		$this->assertTrue( $results[0]->is_ok() );
	}

	public function test_temas_desactualizados_genera_un_resultado_por_tema(): void {
		$results = $this->themes_check( array( 'twentytwentyone' => '1.9' ) )->run_many();

		$this->assertCount( 1, $results );
		$this->assertSame( 'theme/twentytwentyone', $results[0]->get_id() );
		$this->assertSame( Result::SEVERITY_WARNING, $results[0]->get_severity() );
		$this->assertSame( 5, $results[0]->get_points_deducted() );
		$this->assertStringContainsString( '1.9', $results[0]->get_recommendation() );
		$this->assertStringContainsString( self::UPDATES_URL, $results[0]->get_recommendation() );
	}

	private function plugins_check( array $outdated ): PluginsUpdatesCheck {
		return new PluginsUpdatesCheck(
			static function () use ( $outdated ): array {
				return array(
					'outdated'    => $outdated,
					'updates_url' => self::UPDATES_URL,
				);
			}
		);
	}

	private function themes_check( array $outdated ): ThemesUpdatesCheck {
		return new ThemesUpdatesCheck(
			static function () use ( $outdated ): array {
				return array(
					'outdated'    => $outdated,
					'updates_url' => self::UPDATES_URL,
				);
			}
		);
	}
}