<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checker;
use WPVitals\Result;
use WPVitals\Tests\Checks\BrokenCheck;
use WPVitals\Tests\Checks\DuplicateIdCheck;
use WPVitals\Tests\Checks\OkCheck;

final class CheckerTest extends TestCase {

	public function test_ejecuta_todos_los_checks_registrados(): void {
		$checker = new Checker();

		$checker->add_check( new OkCheck() );
		$checker->add_check( new DuplicateIdCheck() );

		$results = $checker->run_all();

		$this->assertCount( 2, $results );
		foreach ( $results as $result ) {
			$this->assertInstanceOf( Result::class, $result );
			$this->assertTrue( $result->is_ok() );
		}
	}

	public function test_error_individual_no_interrumpe_el_escaneo(): void {
		$checker = new Checker();

		$checker->add_check( new OkCheck() );
		$checker->add_check( new BrokenCheck() );
		$checker->add_check( new DuplicateIdCheck() );

		$results = $checker->run_all();

		$this->assertCount( 3, $results );
		$this->assertSame( Result::SEVERITY_OK, $results[0]->get_severity() );
		$this->assertSame( Result::SEVERITY_ERROR, $results[1]->get_severity() );
		$this->assertSame( 0, $results[1]->get_points_deducted() );
		$this->assertStringContainsString( 'Fallo simulado', $results[1]->get_recommendation() );
		$this->assertSame( Result::SEVERITY_OK, $results[2]->get_severity() );
	}

	public function test_id_duplicado_lanza_excepcion(): void {
		$checker = new Checker();

		$checker->add_check( new DuplicateIdCheck() );

		$this->expectException( \LogicException::class );

		$checker->add_check( new DuplicateIdCheck() );
	}

	public function test_sin_checks_devuelve_lista_vacia(): void {
		$checker = new Checker();

		$this->assertCount( 0, $checker->run_all() );
		$this->assertSame( 0, $checker->count() );
	}

	public function test_revisa_que_los_results_son_inmutables_por_contrato(): void {
		$checker = new Checker();

		$checker->add_check( new OkCheck() );

		$result = $checker->run_all()[0];

		$this->assertSame( 'test/ok', $result->get_id() );
		$this->assertSame( 'Check que pasa', $result->get_title() );
	}
}