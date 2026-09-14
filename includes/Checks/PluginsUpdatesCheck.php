<?php
/**
 * Check de actualizaciones de plugins.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Detecta plugins con actualizaciones pendientes a partir de la transient
 * nativa update_plugins y genera un resultado por plugin desactualizado.
 */
final class PluginsUpdatesCheck extends AbstractCheck implements MultiCheckInterface {

	/**
	 * Fuente de actualizaciones de plugins.
	 *
	 * @var callable
	 */
	private $updates_source;

	/**
	 * Constructor con fuente inyectable para tests.
	 *
	 * @param callable|null $updates_source Devuelve array con 'outdated' (archivo => nueva versión)
	 *                                      y 'updates_url' (pantalla nativa de actualizaciones).
	 */
	public function __construct( ?callable $updates_source = null ) {
		$this->updates_source = $updates_source ?? static function (): array {
			$transient = \get_site_transient( 'update_plugins' );
			$outdated  = array();

			if ( \is_object( $transient ) && \is_array( $transient->response ) ) {
				foreach ( $transient->response as $plugin_file => $update ) {
					if ( \is_object( $update ) && isset( $update->new_version ) ) {
						$outdated[ $plugin_file ] = (string) $update->new_version;
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
		return 'components/plugins';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Outdated plugins', 'wpvitals' );
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

		foreach ( $outdated as $plugin_file => $new_version ) {
			$results[] = new Result(
				'plugin/' . $this->plugin_slug( (string) $plugin_file ),
				__( 'Outdated plugin', 'wpvitals' ),
				Result::SEVERITY_WARNING,
				array(
					'plugin'      => (string) $plugin_file,
					'new_version' => (string) $new_version,
				),
				sprintf(
					/* translators: 1: plugin file, 2: new version, 3: updates URL. */
					__( 'Plugin %1$s has version %2$s available. Update it from the native updates screen: %3$s', 'wpvitals' ),
					(string) $plugin_file,
					(string) $new_version,
					$url
				),
				ScoreDiscounts::MINOR
			);
		}

		return $results;
	}

	/**
	 * Deriva un slug estable a partir del archivo del plugin.
	 *
	 * @param string $plugin_file Ruta del plugin (p. ej. "akismet/akismet.php").
	 *
	 * @return string
	 */
	private function plugin_slug( string $plugin_file ): string {
		$slug = \dirname( $plugin_file );

		if ( '.' === $slug ) {
			$slug = \basename( $plugin_file, '.php' );
		}

		return $slug;
	}
}
