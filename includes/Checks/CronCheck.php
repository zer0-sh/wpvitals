<?php
/**
 * Check de WP-Cron.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;
use WPVitals\ScoreDiscounts;

/**
 * Comprueba el estado de WP-Cron: si está deshabilitado vía
 * DISABLE_WP_CRON y si existen eventos relevantes programados.
 */
final class CronCheck extends AbstractCheck {

	/**
	 * Eventos del Core cuya presencia indica que WP-Cron está activo.
	 *
	 * @var string[]
	 */
	const CORE_EVENTS = array(
		'wp_version_check',
		'wp_update_plugins',
		'wp_update_themes',
		'wp_scheduled_delete',
	);

	/**
	 * Fuente que indica si WP-Cron está deshabilitado.
	 *
	 * @var callable
	 */
	private $disabled_source;

	/**
	 * Fuente que indica si existen eventos cron relevantes.
	 *
	 * @var callable
	 */
	private $has_events_source;

	/**
	 * Constructor con fuentes inyectables para tests.
	 *
	 * @param callable|null $disabled_source   Devuelve bool: DISABLE_WP_CRON activo.
	 * @param callable|null $has_events_source Devuelve bool: hay eventos del Core programados.
	 */
	public function __construct( ?callable $disabled_source = null, ?callable $has_events_source = null ) {
		$this->disabled_source   = $disabled_source ?? static function (): bool {
			return \defined( 'DISABLE_WP_CRON' ) && \DISABLE_WP_CRON;
		};
		$this->has_events_source = $has_events_source ?? static function (): bool {
			foreach ( self::CORE_EVENTS as $event ) {
				if ( \wp_get_scheduled_event( $event ) ) {
					return true;
				}
			}

			return false;
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'system/cron';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'WP-Cron', 'wpvitals' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run(): Result {
		$disabled   = (bool) call_user_func( $this->disabled_source );
		$has_events = (bool) call_user_func( $this->has_events_source );

		$value = array(
			'disabled'   => $disabled,
			'has_events' => $has_events,
		);

		if ( $disabled && $has_events ) {
			return $this->result(
				Result::SEVERITY_INFO,
				$value,
				__( 'WP-Cron is disabled; make sure a system cron runs wp-cron.php instead of relying on visits.', 'wpvitals' )
			);
		}

		if ( $disabled ) {
			return $this->result(
				Result::SEVERITY_WARNING,
				$value,
				__( 'WP-Cron is disabled and no relevant events are detected; set up an external cron calling wp-cron.php or re-enable WP-Cron.', 'wpvitals' ),
				5
			);
		}

		if ( $has_events ) {
			return $this->result( Result::SEVERITY_OK, $value );
		}

		return $this->result(
			Result::SEVERITY_WARNING,
			$value,
			__( 'WP-Cron is active but no relevant events are detected; make sure the site receives traffic or run a scan to trigger scheduled tasks.', 'wpvitals' ),
			ScoreDiscounts::MINOR
		);
	}
}
