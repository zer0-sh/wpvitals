<?php
/**
 * Gestión del escaneo programado mediante WP-Cron.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Programa y limpia el evento cron del escaneo según la frecuencia elegida.
 *
 * Cada reprogramación limpia antes el evento previo, de modo que nunca quedan
 * eventos duplicados. Las funciones de WP-Cron y el reloj se inyectan para
 * poder testearlo standalone sin WordPress.
 */
final class Cron {

	/**
	 * Hook del evento cron del escaneo.
	 *
	 * @var string
	 */
	const HOOK = 'wpvitals_scan_scheduled';

	/**
	 * Recurrencia nativa diaria de WordPress.
	 *
	 * @var string
	 */
	const RECURRENCE_DAILY = 'daily';

	/**
	 * Recurrencia semanal propia del plugin.
	 *
	 * @var string
	 */
	const RECURRENCE_WEEKLY = 'wpvitals_weekly';

	/**
	 * Recurrencia mensual propia del plugin.
	 *
	 * @var string
	 */
	const RECURRENCE_MONTHLY = 'wpvitals_monthly';

	/**
	 * Lector de la próxima ejecución inyectable.
	 *
	 * @var callable
	 */
	private $next;

	/**
	 * Programador de eventos inyectable.
	 *
	 * @var callable
	 */
	private $schedule;

	/**
	 * Limpiador de eventos inyectable.
	 *
	 * @var callable
	 */
	private $clear;

	/**
	 * Fuente del reloj inyectable.
	 *
	 * @var callable
	 */
	private $now;

	/**
	 * Constructor.
	 *
	 * @param callable|null $next    Callable(string $hook): int|false.
	 * @param callable|null $schedule Callable(int $timestamp, string $recurrence, string $hook): bool.
	 * @param callable|null $clear   Callable(string $hook): int.
	 * @param callable|null $now     Callable(): int.
	 */
	public function __construct(
		?callable $next = null,
		?callable $schedule = null,
		?callable $clear = null,
		?callable $now = null
	) {
		$this->next     = null !== $next ? $next : static function ( string $hook ) {
			return \wp_next_scheduled( $hook );
		};
		$this->schedule = null !== $schedule ? $schedule : static function ( int $timestamp, string $recurrence, string $hook ): bool {
			return (bool) \wp_schedule_event( $timestamp, $recurrence, $hook );
		};
		$this->clear    = null !== $clear ? $clear : static function ( string $hook ): int {
			return (int) \wp_clear_scheduled_hook( $hook );
		};
		$this->now      = null !== $now ? $now : static function (): int {
			return (int) time();
		};
	}

	/**
	 * Programa o limpia el escaneo según la frecuencia de los ajustes.
	 *
	 * El evento previo se limpia siempre antes para evitar duplicados; si la
	 * frecuencia está desactivada el resultado es simplemente no dejar evento.
	 *
	 * @param Settings $settings Ajustes del plugin.
	 *
	 * @return void
	 */
	public function schedule( Settings $settings ): void {
		call_user_func( $this->clear, self::HOOK );

		if ( $settings->is_disabled() ) {
			return;
		}

		call_user_func(
			$this->schedule,
			(int) call_user_func( $this->now ) + $settings->get_interval(),
			$settings->get_recurrence(),
			self::HOOK
		);
	}

	/**
	 * Elimina cualquier evento cron pendiente del plugin.
	 *
	 * @return void
	 */
	public function clear(): void {
		call_user_func( $this->clear, self::HOOK );
	}

	/**
	 * Devuelve la marca de tiempo de la próxima ejecución o null.
	 *
	 * @return int|null
	 */
	public function next_run(): ?int {
		$next = call_user_func( $this->next, self::HOOK );

		return false !== $next ? (int) $next : null;
	}

	/**
	 * Añade las recurrencias propias del plugin al registro de WP-Cron.
	 *
	 * La frecuencia diaria usa la recurrencia nativa 'daily' de WordPress.
	 *
	 * @param array $schedules Registro de recurrencias existente.
	 *
	 * @return array
	 */
	public static function add_schedules( array $schedules ): array {
		$schedules[ self::RECURRENCE_WEEKLY ] = array(
			'interval' => Settings::INTERVAL_WEEKLY,
			'display'  => __( 'Weekly', 'wpvitals' ),
		);

		$schedules[ self::RECURRENCE_MONTHLY ] = array(
			'interval' => Settings::INTERVAL_MONTHLY,
			'display'  => __( 'Monthly', 'wpvitals' ),
		);

		return $schedules;
	}
}
