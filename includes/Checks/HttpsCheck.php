<?php
/**
 * Check de HTTPS.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Comprueba si la conexión usa HTTPS o si WordPress fuerza HTTPS por
 * configuración, recomendando asegurar la conexión si no es el caso.
 */
final class HttpsCheck extends AbstractCheck {

	/**
	 * Fuente que indica si la petición actual usa HTTPS.
	 *
	 * @var callable
	 */
	private $is_ssl_source;

	/**
	 * Fuente de las configuraciones de WordPress que fuerzan HTTPS.
	 *
	 * @var callable
	 */
	private $config_source;

	/**
	 * Constructor con fuentes inyectables para tests.
	 *
	 * @param callable|null $is_ssl_source Devuelve bool: la petición actual usa HTTPS.
	 * @param callable|null $config_source Devuelve array 'force_ssl', 'force_ssl_admin' y 'siteurl_https'.
	 */
	public function __construct( ?callable $is_ssl_source = null, ?callable $config_source = null ) {
		$this->is_ssl_source = $is_ssl_source ?? static function (): bool {
			return \is_ssl();
		};
		$this->config_source = $config_source ?? static function (): array {
			return array(
				'force_ssl'       => \defined( 'FORCE_SSL' ) && \FORCE_SSL,
				'force_ssl_admin' => \defined( 'FORCE_SSL_ADMIN' ) && \FORCE_SSL_ADMIN,
				'siteurl_https'   => 'https' === \strtolower( (string) \wp_parse_url( (string) \get_option( 'siteurl' ), \PHP_URL_SCHEME ) ),
			);
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'security/https';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'HTTPS connection', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$ssl    = (bool) call_user_func( $this->is_ssl_source );
		$config = call_user_func( $this->config_source );

		if ( $ssl ) {
			return $this->result(
				Result::SEVERITY_OK,
				array(
					'active' => true,
					'forced' => false,
				),
				__( 'The current connection uses HTTPS.', 'wpvitals' )
			);
		}

		$forced = (bool) ( ( $config['force_ssl'] ?? false ) || ( $config['force_ssl_admin'] ?? false ) || ( $config['siteurl_https'] ?? false ) );

		if ( $forced ) {
			return $this->result(
				Result::SEVERITY_INFO,
				array(
					'active'        => false,
					'forced'        => true,
					'siteurl_https' => (bool) ( $config['siteurl_https'] ?? false ),
				),
				__( 'HTTPS is configured or enforced, but the current request is not detected as secure (possible reverse proxy or CDN); check the redirect and X-Forwarded-Proto headers.', 'wpvitals' )
			);
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			array(
				'active' => false,
				'forced' => false,
			),
			__( 'The site does not enforce HTTPS; redirect all traffic to https and update the site URLs in WordPress settings.', 'wpvitals' ),
			ScoreDiscounts::MINOR
		);
	}
}
