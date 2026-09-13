<?php
/**
 * Check de Loopback Requests.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Ejecuta una petición loopback segura a la propia instalación.
 *
 * La petición usa un timeout corto y blocking=true: si el servidor no
 * responde, WP-Cron, actualizaciones y correos internos pueden fallar.
 */
final class LoopbackCheck extends AbstractCheck {

	const LOOPBACK_TIMEOUT = 5;

	/**
	 * Fuente de la comprobación de loopback.
	 *
	 * @var callable
	 */
	private $loopback_source;

	/**
	 * Constructor con fuente inyectable para tests.
	 *
	 * @param callable|null $loopback_source Devuelve bool: la petición loopback respondió correctamente.
	 */
	public function __construct( ?callable $loopback_source = null ) {
		$this->loopback_source = $loopback_source ?? static function (): bool {
			$response = \wp_remote_get(
				\home_url( '/' ),
				array(
					'timeout'  => self::LOOPBACK_TIMEOUT,
					'blocking' => true,
				)
			);

			if ( \is_wp_error( $response ) ) {
				return false;
			}

			$code = (int) \wp_remote_retrieve_response_code( $response );

			return $code >= 200 && $code < 500;
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'system/loopback';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Peticiones loopback', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$ok = (bool) call_user_func( $this->loopback_source );

		if ( $ok ) {
			return $this->result( Result::SEVERITY_OK, $ok );
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			$ok,
			__( 'La petición loopback falló (timeout o conexión bloqueada); procesos como cron, actualizaciones y envíos de correo dependen de ella. Revisa firewalls, reglas del servidor y DNS.', 'wpvitals' ),
			ScoreDiscounts::MINOR
		);
	}
}
