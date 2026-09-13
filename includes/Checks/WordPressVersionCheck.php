<?php
/**
 * Check de la versión de WordPress instalada vs. la estable disponible.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Compara la versión de WordPress instalada con la estable disponible y
 * evalúa si las actualizaciones automáticas del Core están habilitadas.
 *
 * La versión estable proviene de la transient nativa update_core de WP
 * (get_preferred_from_update_core), que ya está cacheada por el propio
 * core, evitando peticiones HTTP extra del plugin.
 */
final class WordPressVersionCheck extends AbstractCheck {

	/**
	 * Fuente de la versión instalada.
	 *
	 * @var callable
	 */
	private $installed_version_source;

	/**
	 * Fuente de la versión estable disponible (array o null).
	 *
	 * @var callable
	 */
	private $available_version_source;

	/**
	 * Fuente del modo de actualizaciones automáticas del Core.
	 *
	 * @var callable
	 */
	private $auto_update_mode_source;

	/**
	 * Constructor con fuentes inyectables para tests.
	 *
	 * @param callable|null $installed_version_source Devuelve la versión instalada.
	 * @param callable|null $available_version_source Devuelve array con 'current' o null.
	 * @param callable|null $auto_update_mode_source  Devuelve el modo de auto-updates ('minor', 'major', 'disabled').
	 */
	public function __construct(
		?callable $installed_version_source = null,
		?callable $available_version_source = null,
		?callable $auto_update_mode_source = null
	) {
		$this->installed_version_source = $installed_version_source ?? static function (): string {
			return (string) \get_bloginfo( 'version' );
		};
		$this->available_version_source = $available_version_source ?? static function (): ?array {
			$update = \get_preferred_from_update_core();

			if ( ! \is_object( $update ) || empty( $update->current ) ) {
				return null;
			}

			return array(
				'current'  => (string) $update->current,
				'response' => isset( $update->response ) ? (string) $update->response : 'latest',
			);
		};
		$this->auto_update_mode_source  = $auto_update_mode_source ?? static function (): string {
			if ( \function_exists( 'get_auto_update_core_mode' ) ) {
				return (string) \get_auto_update_core_mode();
			}

			return 'disabled';
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'core/version';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Versión de WordPress', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$installed = (string) call_user_func( $this->installed_version_source );
		$available = call_user_func( $this->available_version_source );
		$mode      = (string) call_user_func( $this->auto_update_mode_source );

		if ( null === $available ) {
			return $this->result(
				Result::SEVERITY_INFO,
				array(
					'installed'        => $installed,
					'available'        => null,
					'auto_update_mode' => $mode,
				),
				__( 'No se pudo obtener la versión estable disponible de WordPress; revisa la caché de actualizaciones del sitio.', 'wpvitals' )
			);
		}

		$value = array(
			'installed'        => $installed,
			'available'        => $available['current'],
			'auto_update_mode' => $mode,
		);

		$auto_updates_enabled = 'disabled' !== $mode;

		if ( ! \version_compare( $installed, $available['current'], '<' ) ) {
			if ( ! $auto_updates_enabled ) {
				return $this->result(
					Result::SEVERITY_INFO,
					$value,
					__( 'WordPress está actualizado, pero las actualizaciones automáticas están deshabilitadas; actívalas para recibir parches de seguridad.', 'wpvitals' )
				);
			}

			return $this->result( Result::SEVERITY_OK, $value );
		}

		if ( $auto_updates_enabled ) {
			return $this->result(
				Result::SEVERITY_WARNING,
				$value,
				sprintf(
					/* translators: 1: versión instalada, 2: versión estable disponible. */
					__( 'Hay una actualización de WordPress pendiente (instalado %1$s, disponible %2$s). Ejecuta la actualización desde el admin.', 'wpvitals' ),
					$installed,
					$available['current']
				),
				5
			);
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			$value,
			sprintf(
				/* translators: 1: versión instalada, 2: versión estable disponible. */
				__( 'WordPress %1$s está desactualizado y las actualizaciones automáticas están deshabilitadas; actualiza a %2$s y activa los auto-updates.', 'wpvitals' ),
				$installed,
				$available['current']
			),
			ScoreDiscounts::MINOR
		);
	}
}
