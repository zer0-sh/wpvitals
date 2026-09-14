<?php
/**
 * Datos estáticos del sitio para el panel.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Admin;

/**
 * Transformador puro de la información estática del sitio.
 *
 * Devuelve un array plano con la URL del proyecto, la versión de PHP, la
 * versión del núcleo de WordPress y el software del servidor web. Las fuentes
 * se inyectan para poder testearlo standalone sin WordPress.
 */
final class SiteInfo {

	/**
	 * Construye los datos estáticos del sitio.
	 *
	 * @param callable|null $home_url Callable(): string.
	 * @param callable|null $php      Callable(): string.
	 * @param callable|null $core     Callable(): string.
	 * @param callable|null $server   Callable(): string.
	 *
	 * @return array
	 */
	public static function build( ?callable $home_url = null, ?callable $php = null, ?callable $core = null, ?callable $server = null ): array {
		return array(
			'url'    => (string) call_user_func(
				null !== $home_url ? $home_url : static function (): string {
					return (string) \home_url( '/' );
				}
			),
			'php'    => (string) call_user_func(
				null !== $php ? $php : static function (): string {
					return \PHP_VERSION;
				}
			),
			'core'   => (string) call_user_func(
				null !== $core ? $core : static function (): string {
					return (string) \get_bloginfo( 'version' );
				}
			),
			'server' => (string) call_user_func(
				null !== $server ? $server : static function (): string {
					$software = '';

					if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
						$software = \sanitize_text_field( \wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
					}

					return $software;
				}
			),
		);
	}
}
