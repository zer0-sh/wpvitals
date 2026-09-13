<?php
/**
 * Check del memory_limit de PHP.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Revisa el límite de memoria de PHP frente al mínimo recomendado por
 * WordPress (128M).
 */
final class PhpMemoryCheck extends AbstractCheck {

	const MIN_RECOMMENDED = '128M';

	/**
	 * Fuente del valor ini de memory_limit.
	 *
	 * @var callable
	 */
	private $memory_source;

	/**
	 * Constructor con fuente inyectable para tests.
	 *
	 * @param callable|null $memory_source Devuelve el valor de memory_limit (p. ej. "128M").
	 */
	public function __construct( ?callable $memory_source = null ) {
		$this->memory_source = $memory_source ?? static function (): string {
			return (string) \ini_get( 'memory_limit' );
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'php/memory';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Límite de memoria de PHP', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$limit = (string) call_user_func( $this->memory_source );
		$bytes = $this->parse_bytes( $limit );

		if ( -1 === $bytes ) {
			return $this->result(
				Result::SEVERITY_OK,
				$limit,
				__( 'memory_limit está configurado sin límite (ilimitado).', 'wpvitals' )
			);
		}

		if ( $bytes >= $this->bytes_from_ini( self::MIN_RECOMMENDED ) ) {
			return $this->result( Result::SEVERITY_OK, $limit );
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			$limit,
			sprintf(
				/* translators: 1: límite actual, 2: límite recomendado. */
				__( 'memory_limit está en %1$s; WordPress recomienda al menos %2$s. Aumenta el límite en php.ini.', 'wpvitals' ),
				$limit,
				self::MIN_RECOMMENDED
			),
			ScoreDiscounts::MINOR
		);
	}

	/**
	 * Convierte un valor ini ("128M", "1G", "512K") a bytes.
	 *
	 * @param string $value Valor de memory_limit.
	 *
	 * @return int Bytes o -1 si no hay límite.
	 */
	private function parse_bytes( string $value ): int {
		$value = \strtolower( \trim( $value ) );

		if ( '-1' === $value ) {
			return -1;
		}

		$units = array(
			'g' => 1024 * 1024 * 1024,
			'm' => 1024 * 1024,
			'k' => 1024,
		);

		$unit = \substr( $value, -1 );
		$size = (int) \rtrim( $value, 'gmk' );

		return $size * ( $units[ $unit ] ?? 1 );
	}

	/**
	 * Devuelve los bytes equivalentes a un valor en megabytes ("128M").
	 *
	 * @param string $value Valor con sufijo de unidad.
	 *
	 * @return int
	 */
	private function bytes_from_ini( string $value ): int {
		return (int) $value * 1024 * 1024;
	}
}
