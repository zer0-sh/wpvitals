<?php
/**
 * Check de WP_DEBUG.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Comprueba si el modo debug de WordPress está activado, lo que puede
 * exponer información sensible en producción.
 */
final class DebugCheck extends AbstractCheck {

	/**
	 * Fuente de las constantes de debug de WordPress.
	 *
	 * @var callable
	 */
	private $config_source;

	/**
	 * Constructor con fuente inyectable para tests.
	 *
	 * @param callable|null $config_source Devuelve array 'wp_debug' y 'wp_debug_display'.
	 */
	public function __construct( ?callable $config_source = null ) {
		$this->config_source = $config_source ?? static function (): array {
			return array(
				'wp_debug'         => \defined( 'WP_DEBUG' ) && \WP_DEBUG,
				'wp_debug_display' => \defined( 'WP_DEBUG_DISPLAY' ) ? (bool) \WP_DEBUG_DISPLAY : true,
			);
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'security/debug';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'WordPress debug mode', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$config  = call_user_func( $this->config_source );
		$debug   = (bool) ( $config['wp_debug'] ?? false );
		$display = (bool) ( $config['wp_debug_display'] ?? false );

		if ( ! $debug ) {
			return $this->result( Result::SEVERITY_OK, $config );
		}

		if ( $display ) {
			return $this->result(
				Result::SEVERITY_WARNING,
				$config,
				__( 'WP_DEBUG and WP_DEBUG_DISPLAY are enabled; PHP errors can be shown in production. Disable them in wp-config.php.', 'wpvitals' ),
				5
			);
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			$config,
			__( 'WP_DEBUG is enabled in production; disable it in wp-config.php.', 'wpvitals' ),
			ScoreDiscounts::MINOR
		);
	}
}
