<?php
/**
 * Persistencia del último resultado de escaneo.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Guarda y recupera el último resultado completo de escaneo.
 *
 * Usa una opción de WordPress cuyas funciones get/update se inyectan para
 * poder testearlo standalone. Un resultado guardado corrupto se trata como
 * inexistente en lugar de romper el panel.
 */
final class ScanStore {

	/**
	 * Nombre de la opción donde se persiste el último escaneo.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wpvitals_last_scan';

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
	 * Persiste un resultado de escaneo completo.
	 *
	 * @param ScanOutcome $outcome Resultado a guardar.
	 *
	 * @return void
	 */
	public function save( ScanOutcome $outcome ): void {
		call_user_func( $this->update, self::OPTION_NAME, $outcome->to_array() );
	}

	/**
	 * Recupera el último resultado persistido.
	 *
	 * Devuelve null cuando no existe resultado o cuando el valor guardado no
	 * puede reconstruirse (opción corrupta o de una versión anterior).
	 *
	 * @return ScanOutcome|null
	 */
	public function get(): ?ScanOutcome {
		$data = call_user_func( $this->get, self::OPTION_NAME );

		if ( ! is_array( $data ) ) {
			return null;
		}

		try {
			return ScanOutcome::from_array( $data );
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
