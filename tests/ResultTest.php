<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Result;

final class ResultTest extends TestCase {

	public function test_crea_resultado_completo(): void {
		$result = new Result( 'test/check', 'Check de prueba', Result::SEVERITY_WARNING, 42, 'Haz algo', 5 );

		$this->assertSame( 'test/check', $result->get_id() );
		$this->assertSame( 'Check de prueba', $result->get_title() );
		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 42, $result->get_value() );
		$this->assertSame( 'Haz algo', $result->get_recommendation() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	public function test_por_defecto_sin_recomendacion_ni_puntos(): void {
		$result = new Result( 'test/check', 'Check OK', Result::SEVERITY_OK, true );

		$this->assertSame( '', $result->get_recommendation() );
		$this->assertSame( 0, $result->get_points_deducted() );
		$this->assertTrue( $result->is_ok() );
	}

	public function test_severidad_invalida_lanza_excepcion(): void {
		$this->expectException( \InvalidArgumentException::class );

		new Result( 'test/check', 'Check', 'inexistente', null );
	}

	public function test_puntos_negativos_lanzan_excepcion(): void {
		$this->expectException( \InvalidArgumentException::class );

		new Result( 'test/check', 'Check', Result::SEVERITY_OK, null, '', -1 );
	}

	public function test_puntos_mayores_al_maximo_lanzan_excepcion(): void {
		$this->expectException( \InvalidArgumentException::class );

		new Result( 'test/check', 'Check', Result::SEVERITY_OK, null, '', Result::POINTS_MAX + 1 );
	}

	public function test_severidad_ok_e_info_son_estado_correcto(): void {
		$ok   = new Result( 'a', 'A', Result::SEVERITY_OK, null );
		$info = new Result( 'b', 'B', Result::SEVERITY_INFO, null );
		$warn = new Result( 'c', 'C', Result::SEVERITY_WARNING, null );

		$this->assertTrue( $ok->is_ok() );
		$this->assertTrue( $info->is_ok() );
		$this->assertFalse( $warn->is_ok() );
	}
}