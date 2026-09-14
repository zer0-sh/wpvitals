<?php
/**
 * Persistencia de los hallazgos ignorados por el usuario.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Guarda y recupera la lista de identificadores de hallazgos ignorados.
 *
 * Usa una opción de WordPress cuyas funciones get/update se inyectan para
 * poder testearlo standalone. Un valor no accesible se trata como lista
 * vacía; los identificadores se normalizan antes de persistirse.
 */
final class IgnoredStore {

	/**
	 * Nombre de la opción donde se persisten los hallazgos ignorados.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wpvitals_ignored';

	/**
	 * Caracteres permitidos en un identificador de hallazgo.
	 *
	 * @var string
	 */
	const ID_PATTERN = '/^(?!.*\.\.)[a-z0-9\-_.\/]{1,120}$/';

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
	 * Devuelve la lista de hallazgos ignorados.
	 *
	 * @return string[]
	 */
	public function get(): array {
		$data = call_user_func( $this->get, self::OPTION_NAME );

		if ( ! is_array( $data ) ) {
			return array();
		}

		$ignored = array();

		foreach ( $data as $id ) {
			if ( is_string( $id ) && '' !== $id ) {
				$ignored[] = $id;
			}
		}

		return array_values( array_unique( $ignored ) );
	}

	/**
	 * Añade un hallazgo a la lista de ignorados.
	 *
	 * @param string $id Identificador del hallazgo.
	 *
	 * @return void
	 */
	public function add( string $id ): void {
		$ignored   = $this->get();
		$ignored[] = $id;

		call_user_func( $this->update, self::OPTION_NAME, array_values( array_unique( $ignored ) ) );
	}

	/**
	 * Elimina un hallazgo de la lista de ignorados.
	 *
	 * @param string $id Identificador del hallazgo.
	 *
	 * @return void
	 */
	public function remove( string $id ): void {
		$ignored = array();

		foreach ( $this->get() as $candidate ) {
			if ( $candidate !== $id ) {
				$ignored[] = $candidate;
			}
		}

		call_user_func( $this->update, self::OPTION_NAME, $ignored );
	}

	/**
	 * Vacía por completo la lista de ignorados.
	 *
	 * @return void
	 */
	public function clear(): void {
		call_user_func( $this->update, self::OPTION_NAME, array() );
	}

	/**
	 * Normaliza y valida un identificador de hallazgo recibido del exterior.
	 *
	 * Devuelve '' cuando el valor no es un identificador aceptable.
	 *
	 * @param mixed $id Valor crudo a normalizar.
	 *
	 * @return string
	 */
	public static function sanitize_id( $id ): string {
		if ( ! is_string( $id ) ) {
			return '';
		}

		$id = strtolower( trim( $id ) );

		return preg_match( self::ID_PATTERN, $id ) ? $id : '';
	}
}
