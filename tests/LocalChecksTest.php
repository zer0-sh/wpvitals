<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checker;
use WPVitals\Checks\LocalChecks;
use WPVitals\ScoreDiscounts;

final class LocalChecksTest extends TestCase {

	public function test_registra_todos_los_checks_locales(): void {
		$checker = new Checker();

		foreach ( LocalChecks::all() as $check ) {
			$checker->add_check( $check );
		}

		$this->assertSame( 14, $checker->count() );
	}

	public function test_ids_estables_por_categoria(): void {
		$ids = array();

		foreach ( LocalChecks::all() as $check ) {
			$ids[] = $check->get_id();
		}

		$this->assertContains( 'core/version', $ids );
		$this->assertContains( 'php/version', $ids );
		$this->assertContains( 'security/https', $ids );
		$this->assertContains( 'system/cron', $ids );
		$this->assertContains( 'components/plugins', $ids );
		$this->assertContains( 'components/themes', $ids );
	}

	public function test_descuentos_base_en_rango(): void {
		$this->assertSame( 5, ScoreDiscounts::MINOR );
		$this->assertSame( 10, ScoreDiscounts::MAJOR );
	}
}