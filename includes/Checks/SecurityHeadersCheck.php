<?php
/**
 * Check de cabeceras de seguridad del sitio.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;

/**
 * Comprueba la presencia de las cabeceras de seguridad básicas de la portada
 * y devuelve un resultado por cada una.
 *
 * La medición se realiza sobre la portada pública sin autenticación; cabeceras
 * aplicadas únicamente en wp-admin o en la API no se detectan y no cuentan como
 * error. Las cabeceras se consultan en una única petición HTTP cuyo transporte
 * se inyecta para poder testearlo standalone; si la consulta falla se devuelve
 * un único Result de error en lugar de penalizar cabeceras no verificadas.
 */
final class SecurityHeadersCheck extends AbstractCheck implements MultiCheckInterface {

	/**
	 * Timeout en segundos de la petición de cabeceras.
	 *
	 * @var int
	 */
	const REQUEST_TIMEOUT = 5;

	/**
	 * Descuento por cabecera de seguridad ausente.
	 *
	 * @var int
	 */
	const HEADER_ISSUE_POINTS = 2;

	/**
	 * Fuente de las cabeceras de la portada.
	 *
	 * @var callable
	 */
	private $headers_source;

	/**
	 * Constructor con fuente inyectable para tests.
	 *
	 * @param callable|null $headers_source Devuelve array<string,string> con cabeceras en minúsculas, o null si falla la consulta.
	 */
	public function __construct( ?callable $headers_source = null ) {
		$this->headers_source = null !== $headers_source ? $headers_source : static function (): ?array {
			$response = \wp_remote_get(
				\home_url( '/' ),
				array(
					'timeout'     => self::REQUEST_TIMEOUT,
					'blocking'    => true,
					'redirection' => 2,
				)
			);

			if ( \is_wp_error( $response ) ) {
				return null;
			}

			$headers = \wp_remote_retrieve_headers( $response );

			if ( ! $headers ) {
				return array();
			}

			$normalized = array();

			foreach ( $headers as $header => $value ) {
				$normalized[ strtolower( (string) $header ) ] = is_array( $value )
					? implode( ', ', array_map( 'strval', $value ) )
					: (string) $value;
			}

			return $normalized;
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'headers/security';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Security headers', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Resumen agregado para el contrato simple; el flujo real usa run_many().
	 */
	public function run(): Result {
		$results = $this->run_many();

		$missing = 0;

		foreach ( $results as $result ) {
			if ( Result::SEVERITY_ERROR === $result->get_severity() ) {
				return $this->result(
					Result::SEVERITY_ERROR,
					$results,
					__( 'Could not fetch the site headers; check the HTTP request.', 'wpvitals' ),
					0
				);
			}

			if ( Result::SEVERITY_WARNING === $result->get_severity() ) {
				++$missing;
			}
		}

		if ( 0 === $missing ) {
			return $this->result( Result::SEVERITY_OK, $results );
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			$results,
			sprintf(
				/* translators: %d: number of missing headers. */
				__( '%d security headers missing on the public homepage.', 'wpvitals' ),
				$missing
			),
			self::HEADER_ISSUE_POINTS * $missing
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return Result[]
	 */
	public function run_many(): array {
		$headers = call_user_func( $this->headers_source );

		if ( ! is_array( $headers ) ) {
			return array(
				new Result(
					$this->get_id(),
					$this->get_title(),
					Result::SEVERITY_ERROR,
					null,
					__( 'Could not fetch the site headers; check the HTTP request.', 'wpvitals' ),
					0
				),
			);
		}

		$results = array();

		foreach ( self::headers_meta() as $name => $meta ) {
			$value = isset( $headers[ $name ] ) ? (string) $headers[ $name ] : '';

			if ( '' !== $value ) {
				$results[] = new Result(
					'headers/' . $name,
					$meta['title'],
					Result::SEVERITY_OK,
					$value
				);
				continue;
			}

			$results[] = new Result(
				'headers/' . $name,
				$meta['title'],
				Result::SEVERITY_WARNING,
				null,
				$meta['recommendation'],
				self::HEADER_ISSUE_POINTS
			);
		}

		return $results;
	}

	/**
	 * Devuelve los metadatos de las cabeceras a verificar.
	 *
	 * @return array
	 */
	private static function headers_meta(): array {
		return array(
			'x-content-type-options'    => array(
				'title'          => __( 'X-Content-Type-Options header', 'wpvitals' ),
				'recommendation' => __( 'X-Content-Type-Options: nosniff is missing; add it to prevent browser MIME sniffing.', 'wpvitals' ),
			),
			'x-frame-options'           => array(
				'title'          => __( 'X-Frame-Options header', 'wpvitals' ),
				'recommendation' => __( 'X-Frame-Options is missing (and no frame-ancestors in the CSP); it prevents clickjacking of your site.', 'wpvitals' ),
			),
			'content-security-policy'   => array(
				'title'          => __( 'Content-Security-Policy header', 'wpvitals' ),
				'recommendation' => __( 'Content-Security-Policy is missing; define a policy that limits allowed origins and resources.', 'wpvitals' ),
			),
			'referrer-policy'           => array(
				'title'          => __( 'Referrer-Policy header', 'wpvitals' ),
				'recommendation' => __( 'Referrer-Policy is missing; configure how much referrer information is shared with other sites.', 'wpvitals' ),
			),
			'permissions-policy'        => array(
				'title'          => __( 'Permissions-Policy header', 'wpvitals' ),
				'recommendation' => __( 'Permissions-Policy is missing; restrict the browser APIs (camera, microphone, geolocation…) available on your pages.', 'wpvitals' ),
			),
			'strict-transport-security' => array(
				'title'          => __( 'Strict-Transport-Security header', 'wpvitals' ),
				'recommendation' => __( 'Strict-Transport-Security is missing; over HTTPS it forces secure connections and prevents downgrade attacks.', 'wpvitals' ),
			),
		);
	}
}
