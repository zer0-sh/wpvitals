<?php
/**
 * Check de la versión de PHP activa y su estado de soporte.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Evalúa la versión de PHP activa frente a su ciclo de vida (EOL).
 */
final class PhpVersionCheck extends AbstractCheck {

	/**
	 * Fechas de fin de soporte de seguridad por versión mayor.menor de PHP.
	 *
	 * Fuente curada: https://www.php.net/supported-versions.php
	 * La tabla se actualiza manualmente en cada release de PHP cuyo EOL
	 * expire. A futuro se prevé un workflow que compruebe y actualice estas
	 * fechas automáticamente (ver todo.md, sección 3.3).
	 */
	const PHP_EOL_DATES = array(
		'7.2' => '2019-11-30',
		'7.3' => '2021-12-06',
		'7.4' => '2022-11-28',
		'8.0' => '2023-11-26',
		'8.1' => '2025-12-31',
		'8.2' => '2026-12-31',
		'8.3' => '2027-12-31',
		'8.4' => '2028-12-31',
		'8.5' => '2029-12-31',
	);

	/**
	 * Fuente de la versión de PHP activa.
	 *
	 * @var callable
	 */
	private $php_version_source;

	/**
	 * Fuente de la fecha actual (Y-m-d).
	 *
	 * @var callable
	 */
	private $today_source;

	/**
	 * Constructor con fuentes inyectables para tests.
	 *
	 * @param callable|null $php_version_source Devuelve la versión PHP (p. ej. "8.2").
	 * @param callable|null $today_source       Devuelve la fecha actual en formato Y-m-d.
	 */
	public function __construct( ?callable $php_version_source = null, ?callable $today_source = null ) {
		$this->php_version_source = $php_version_source ?? static function (): string {
			return \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;
		};
		$this->today_source       = $today_source ?? static function (): string {
			return \gmdate( 'Y-m-d' );
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'php/version';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Versión de PHP', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$version = (string) call_user_func( $this->php_version_source );
		$today   = (string) call_user_func( $this->today_source );

		if ( ! isset( self::PHP_EOL_DATES[ $version ] ) ) {
			return $this->result(
				Result::SEVERITY_INFO,
				array(
					'version'        => $version,
					'status'         => 'unknown',
					'security_until' => null,
				),
				sprintf(
					/* translators: %s: versión de PHP detectada. */
					__( 'No se dispone de datos del ciclo de vida para PHP %s; verifica su estado de soporte oficial.', 'wpvitals' ),
					$version
				)
			);
		}

		$security_until = self::PHP_EOL_DATES[ $version ];
		$today_dt       = new \DateTimeImmutable( $today );
		$eol            = new \DateTimeImmutable( $security_until );
		$eol_soon       = $eol->modify( '-6 months' );

		if ( $today_dt >= $eol ) {
			return $this->result(
				Result::SEVERITY_CRITICAL,
				array(
					'version'        => $version,
					'status'         => 'eol',
					'security_until' => $security_until,
				),
				sprintf(
					/* translators: 1: versión de PHP, 2: fecha EOL. */
					__( 'PHP %1$s finalizó su soporte de seguridad el %2$s; actualiza a una versión soportada.', 'wpvitals' ),
					$version,
					$security_until
				),
				ScoreDiscounts::MAJOR
			);
		}

		if ( $today_dt >= $eol_soon ) {
			return $this->result(
				Result::SEVERITY_WARNING,
				array(
					'version'        => $version,
					'status'         => 'eol_soon',
					'security_until' => $security_until,
				),
				sprintf(
					/* translators: 1: versión de PHP, 2: fecha EOL. */
					__( 'PHP %1$s dejará de recibir soporte de seguridad el %2$s; planifica la actualización.', 'wpvitals' ),
					$version,
					$security_until
				),
				ScoreDiscounts::MINOR
			);
		}

		return $this->result(
			Result::SEVERITY_OK,
			array(
				'version'        => $version,
				'status'         => 'supported',
				'security_until' => $security_until,
			)
		);
	}
}
