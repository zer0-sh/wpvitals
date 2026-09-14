<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checks\SecurityHeadersCheck;
use WPVitals\Result;

final class SecurityHeadersCheckTest extends TestCase {

	private function check( ?array $headers ): SecurityHeadersCheck {
		return new SecurityHeadersCheck(
			static function () use ( $headers ): ?array {
				return $headers;
			}
		);
	}

	public function test_ok_cuando_todas_las_cabeceras_presentes(): void {
		$check = $this->check(
			array(
				'x-content-type-options'   => 'nosniff',
				'x-frame-options'          => 'DENY',
				'content-security-policy'  => "default-src 'self'",
				'referrer-policy'          => 'strict-origin-when-cross-origin',
				'permissions-policy'       => 'geolocation=()',
				'strict-transport-security' => 'max-age=31536000',
			)
		);

		$results = $check->run_many();

		$this->assertCount( 6, $results );

		foreach ( $results as $result ) {
			$this->assertSame( Result::SEVERITY_OK, $result->get_severity() );
			$this->assertSame( 0, $result->get_points_deducted() );
		}

		$this->assertSame( Result::SEVERITY_OK, $check->run()->get_severity() );
	}

	public function test_puntua_cada_cabecera_ausente_y_normaliza_claves(): void {
		$check = $this->check(
			array(
				'x-frame-options' => 'SAMEORIGIN',
			)
		);

		$results = $check->run_many();
		$found   = array();

		foreach ( $results as $result ) {
			$found[ $result->get_id() ] = $result;
		}

		$this->assertSame( Result::SEVERITY_OK, $found['headers/x-frame-options']->get_severity() );
		$this->assertSame( 'SAMEORIGIN', $found['headers/x-frame-options']->get_value() );

		foreach ( array(
			'headers/x-content-type-options',
			'headers/content-security-policy',
			'headers/referrer-policy',
			'headers/permissions-policy',
			'headers/strict-transport-security',
		) as $id ) {
			$this->assertSame( Result::SEVERITY_WARNING, $found[ $id ]->get_severity() );
			$this->assertSame( SecurityHeadersCheck::HEADER_ISSUE_POINTS, $found[ $id ]->get_points_deducted() );
		}

		$run = $check->run();

		$this->assertSame( Result::SEVERITY_WARNING, $run->get_severity() );
		$this->assertSame( 5 * SecurityHeadersCheck::HEADER_ISSUE_POINTS, $run->get_points_deducted() );
	}

	public function test_peticion_fallida_devuelve_unico_error_sin_puntos(): void {
		$check   = $this->check( null );
		$results = $check->run_many();

		$this->assertCount( 1, $results );
		$this->assertSame( 'headers/security', $results[0]->get_id() );
		$this->assertSame( Result::SEVERITY_ERROR, $results[0]->get_severity() );
		$this->assertSame( 0, $results[0]->get_points_deducted() );
		$this->assertSame( Result::SEVERITY_ERROR, $check->run()->get_severity() );
	}
}