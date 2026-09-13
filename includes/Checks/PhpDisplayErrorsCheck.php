<?php
/**
 * Check de display_errors de PHP.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Revisa si display_errors está activado, algo inseguro en producción.
 */
final class PhpDisplayErrorsCheck extends AbstractCheck {

	/**
	 * Fuente del valor ini de display_errors.
	 *
	 * @var callable
	 */
	private $display_source;

	/**
	 * Constructor con fuente inyectable para tests.
	 *
	 * @param callable|null $display_source Devuelve el valor de display_errors (p. ej. "1", "Off").
	 */
	public function __construct( ?callable $display_source = null ) {
		$this->display_source = $display_source ?? static function (): string {
			return (string) \ini_get( 'display_errors' );
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'php/display_errors';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Visualización de errores PHP', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$enabled = (string) call_user_func( $this->display_source );

		if ( ! \filter_var( $enabled, \FILTER_VALIDATE_BOOLEAN ) ) {
			return $this->result( Result::SEVERITY_OK, $enabled );
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			$enabled,
			__( 'display_errors está activado y muestra errores PHP a los visitantes. Desactívalo en producción con display_errors = Off.', 'wpvitals' ),
			ScoreDiscounts::MINOR
		);
	}
}
