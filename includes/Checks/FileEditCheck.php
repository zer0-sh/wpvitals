<?php
/**
 * Check de DISALLOW_FILE_EDIT.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Comprueba si la edición de archivos desde WP-Admin está permitida
 * (DISALLOW_FILE_EDIT no definido o false).
 */
final class FileEditCheck extends AbstractCheck {

	/**
	 * Fuente que indica si la edición de archivos está bloqueada.
	 *
	 * @var callable
	 */
	private $disabled_source;

	/**
	 * Constructor con fuente inyectable para tests.
	 *
	 * @param callable|null $disabled_source Devuelve bool: DISALLOW_FILE_EDIT activo.
	 */
	public function __construct( ?callable $disabled_source = null ) {
		$this->disabled_source = $disabled_source ?? static function (): bool {
			return \defined( 'DISALLOW_FILE_EDIT' ) && \DISALLOW_FILE_EDIT;
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'security/file_edit';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Edición de archivos desde el admin', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$disabled = (bool) call_user_func( $this->disabled_source );

		if ( $disabled ) {
			return $this->result( Result::SEVERITY_OK, $disabled );
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			$disabled,
			__( 'La edición de archivos desde WP-Admin está permitida; define DISALLOW_FILE_EDIT como true en wp-config.php.', 'wpvitals' ),
			ScoreDiscounts::MINOR
		);
	}
}
