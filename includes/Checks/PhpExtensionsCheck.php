<?php
/**
 * Check de las extensiones PHP requeridas por WordPress.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Comprueba que están cargadas las extensiones PHP que WordPress necesita
 * para funcionar con todas sus capacidades.
 */
final class PhpExtensionsCheck extends AbstractCheck {

	const REQUIRED_EXTENSIONS = array(
		'ctype',
		'curl',
		'dom',
		'fileinfo',
		'hash',
		'mbstring',
		'mysqli',
		'openssl',
		'pcre',
		'simplexml',
		'xml',
		'zlib',
	);

	/**
	 * Comprobador inyectable de extensiones cargadas.
	 *
	 * @var callable
	 */
	private $loader;

	/**
	 * Constructor con comprobador inyectable para tests.
	 *
	 * @param callable|null $loader Función (string): bool que indica si una extensión está cargada.
	 */
	public function __construct( ?callable $loader = null ) {
		$this->loader = $loader ?? static function ( string $extension ): bool {
			return \extension_loaded( $extension );
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'php/extensions';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Extensiones PHP requeridas', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$missing = array();

		foreach ( self::REQUIRED_EXTENSIONS as $extension ) {
			if ( ! call_user_func( $this->loader, $extension ) ) {
				$missing[] = $extension;
			}
		}

		if ( array() === $missing ) {
			return $this->result( Result::SEVERITY_OK, $missing );
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			$missing,
			sprintf(
				/* translators: %s: lista de extensiones PHP requeridas no encontradas. */
				__( 'Faltan extensiones PHP requeridas por WordPress: %s. Instálalas para un funcionamiento completo.', 'wpvitals' ),
				\implode( ', ', $missing )
			),
			ScoreDiscounts::MINOR
		);
	}
}
