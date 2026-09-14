<?php
/**
 * Persistencia de la configuración del plugin.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Guarda y recupera los ajustes de WPVitals.
 *
 * Usa una opción de WordPress cuyas funciones get/update se inyectan para
 * poder testearlo standalone. Un valor guardado corrupto se trata como
 * ausente y devuelve los ajustes por defecto en lugar de romper el panel.
 */
final class SettingsStore {

	/**
	 * Nombre de la opción donde se persisten los ajustes.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wpvitals_settings';

	/**
	 * Lector de opciones inyectable.
	 *
	 * @var callable
	 */
	private $get;

	/**
	 * Escritor de opciones inyectable.
	 *
	 * @var callable
	 */
	private $update;

	/**
	 * Constructor.
	 *
	 * @param callable|null $get    Callable(string $key): mixed.
	 * @param callable|null $update Callable(string $key, array $value): void.
	 */
	public function __construct( ?callable $get = null, ?callable $update = null ) {
		$this->get = null !== $get ? $get : static function ( string $key ) {
			return \get_option( $key, null );
		};

		$this->update = null !== $update ? $update : static function ( string $key, array $value ): void {
			\update_option( $key, $value, false );
		};
	}

	/**
	 * Persiste los ajustes del plugin.
	 *
	 * @param Settings $settings Ajustes a guardar.
	 *
	 * @return void
	 */
	public function save( Settings $settings ): void {
		call_user_func( $this->update, self::OPTION_NAME, $settings->to_array() );
	}

	/**
	 * Recupera los ajustes persistidos o los por defecto.
	 *
	 * @return Settings
	 */
	public function get(): Settings {
		$data = call_user_func( $this->get, self::OPTION_NAME );

		if ( ! is_array( $data ) ) {
			return Settings::defaults();
		}

		try {
			return Settings::from_array( $data );
		} catch ( \Throwable $e ) {
			return Settings::defaults();
		}
	}
}
