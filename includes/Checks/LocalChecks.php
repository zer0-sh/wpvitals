<?php
/**
 * Registro de todos los checks locales implementados.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

/**
 * Enumeración de los checks locales del plugin.
 *
 * Es el punto único donde se registran los checks: el flujo de escaneo
 * (todo.md, sección 7) construye su Checker a partir de all().
 */
final class LocalChecks {

	/**
	 * Devuelve una instancia de cada check local implementado.
	 *
	 * @return CheckInterface[]
	 */
	public static function all(): array {
		return array(
			new WordPressVersionCheck(),
			new PhpVersionCheck(),
			new PhpMemoryCheck(),
			new PhpDisplayErrorsCheck(),
			new PhpExtensionsCheck(),
			new HttpsCheck(),
			new DebugCheck(),
			new DebugLogCheck(),
			new XmlRpcCheck(),
			new FileEditCheck(),
			new CronCheck(),
			new LoopbackCheck(),
			new PluginsUpdatesCheck(),
			new ThemesUpdatesCheck(),
		);
	}
}
