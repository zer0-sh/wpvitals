<?php
/**
 * Check de actualizaciones de temas.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Detecta temas con actualizaciones pendientes a partir de la transient
 * nativa update_themes y genera un resultado por tema desactualizado.
 */
final class ThemesUpdatesCheck extends AbstractCheck implements MultiCheckInterface {

	/**
	 * Fuente de actualizaciones de temas.
	 *
	 * @var callable
	 */
	private $updates_source;

	/**
	 * Constructor con fuente inyectable para tests.
	 *
	 * @param callable|null $updates_source Devuelve array con 'outdated' (slug => nueva versión)
	 *                                      y 'updates_url' (pantalla nativa de actualizaciones).
	 */
	public function __construct( ?callable $updates_source = null ) {
		$this->updates_source = $updates_source ?? static function (): array {
			$transient = \get_site_transient( 'update_themes' );
			$outdated  = array();

			if ( \is_object( $transient ) && \is_array( $transient->response ) ) {
				foreach ( $transient->response as $theme_slug => $update ) {
					$version = \is_array( $update ) ? ( $update['new_version'] ?? '' ) : '';

					if ( '' !== $version ) {
						$outdated[ $theme_slug ] = (string) $version;
					}
				}
			}

			return array(
				'outdated'    => $outdated,
				'updates_url' => (string) \admin_url( 'update-core.php' ),
			);
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'components/themes';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Outdated themes', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$results = $this->run_many();

		return $results[0];
	}

	/**
	 * {@inheritDoc}
	 */
	public function run_many(): array {
		$data     = call_user_func( $this->updates_source );
		$outdated = (array) ( $data['outdated'] ?? array() );
		$url      = (string) ( $data['updates_url'] ?? '' );

		if ( array() === $outdated ) {
			return array( $this->result( Result::SEVERITY_OK, $outdated ) );
		}

		$results = array();

		foreach ( $outdated as $theme_slug => $new_version ) {
			$results[] = new Result(
				'theme/' . (string) $theme_slug,
				__( 'Outdated theme', 'wpvitals' ),
				Result::SEVERITY_WARNING,
				array(
					'theme'       => (string) $theme_slug,
					'new_version' => (string) $new_version,
				),
				sprintf(
					/* translators: 1: theme slug, 2: new version, 3: updates URL. */
					__( 'Theme %1$s has version %2$s available. Update it from the native updates screen: %3$s', 'wpvitals' ),
					(string) $theme_slug,
					(string) $new_version,
					$url
				),
				ScoreDiscounts::MINOR
			);
		}

		return $results;
	}
}
