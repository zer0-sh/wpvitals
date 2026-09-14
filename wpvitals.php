<?php
/**
 * Plugin Name: WPVitals
 * Plugin URI:  https://github.com/zer0-sh/wpvitals
 * Description: Ultra lightweight WordPress plugin to monitor your website's performance and uptime.
 * Version:     0.1.1
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author:      WPVitals
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpvitals
 * Domain Path: /languages
 *
 * @package WPVitals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPVITALS_PLUGIN_FILE' ) ) {
	define( 'WPVITALS_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WPVITALS_VERSION' ) ) {
	define( 'WPVITALS_VERSION', '0.1.1' );
}

$wpvitals_autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpvitals_autoload ) ) {
	require_once $wpvitals_autoload;
}

unset( $wpvitals_autoload );

add_action( \WPVitals\Cron::HOOK, 'wpvitals_run_scheduled_scan' );
add_filter( 'cron_schedules', 'wpvitals_cron_schedules' );

register_activation_hook( __FILE__, 'wpvitals_activate' );
register_deactivation_hook( __FILE__, 'wpvitals_deactivate' );

if ( is_admin() ) {
	wpvitals_init_admin();
}

/**
 * Carga las traducciones del plugin según el idioma del sitio.
 *
 * @return void
 */
function wpvitals_load_textdomain(): void {
	load_plugin_textdomain(
		'wpvitals',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

add_action( 'init', 'wpvitals_load_textdomain' );

/**
 * Redirige los archivos `.mo` de cualquier locale regional de español
 * (`es_CO`, `es_MX`, `es_AR`…) al genérico `es_ES` cuando no existe un
 * `.mo` específico para esa región.
 *
 * Con la carga just-in-time de traducciones (WordPress 6.7+), el registro de
 * textdomains (`WP_Textdomain_Registry`) resuelve el `.mo` por locale exacto
 * (`wpvitals-es_CO.mo`) y no aplica fallback regional ni pasa por el filtro
 * `plugin_locale`. Este filtro `load_textdomain_mofile` (aplicado siempre,
 * dentro de `load_textdomain()`) sustituye la región por `es_ES` y mantiene
 * el resto de idiomas intactos.
 *
 * @param string $mofile Ruta del archivo .mo que WordPress va a cargar.
 * @param string $domain Text domain del filtro.
 *
 * @return string Ruta .mo ajustada.
 */
function wpvitals_mofile_spanish_fallback( string $mofile, string $domain ): string {
	if ( 'wpvitals' !== $domain || ! preg_match( '/wpvitals-es_[A-Z]{2}\.mo$/i', $mofile ) ) {
		return $mofile;
	}

	if ( is_readable( $mofile ) ) {
		return $mofile;
	}

	return preg_replace( '/wpvitals-es_[A-Z]{2}\.mo$/i', 'wpvitals-es_ES.mo', $mofile );
}

add_filter( 'load_textdomain_mofile', 'wpvitals_mofile_spanish_fallback', 10, 2 );

/**
 * Inicializa el panel de administración de WPVitals.
 *
 * Se ejecuta al cargar el plugin dentro del panel: el menú se registra en
 * `admin_menu`, que en admin.php ya se ha disparado cuando corre admin_init.
 *
 * @return void
 */
function wpvitals_init_admin(): void {
	$wpvitals_admin = new \WPVitals\Admin\AdminPage(
		wpvitals_runner(),
		wpvitals_scan_store(),
		wpvitals_cron(),
		wpvitals_settings_store(),
		wpvitals_ignored_store()
	);

	$wpvitals_admin->register();
}

/**
 * Devuelve el checker con todos los checks locales registrados.
 *
 * @return \WPVitals\Checker
 */
function wpvitals_checker(): \WPVitals\Checker {
	static $wpvitals_checker = null;

	if ( null === $wpvitals_checker ) {
		$wpvitals_checker = new \WPVitals\Checker();

		foreach ( \WPVitals\Checks\LocalChecks::all() as $wpvitals_check ) {
			$wpvitals_checker->add_check( $wpvitals_check );
		}
	}

	return $wpvitals_checker;
}

/**
 * Devuelve el escáner del sitio.
 *
 * @return \WPVitals\Scanner
 */
function wpvitals_scanner(): \WPVitals\Scanner {
	static $wpvitals_scanner = null;

	if ( null === $wpvitals_scanner ) {
		$wpvitals_scanner = new \WPVitals\Scanner( wpvitals_checker(), new \WPVitals\VulnerabilityClient() );
	}

	return $wpvitals_scanner;
}

/**
 * Devuelve el almacén del último resultado.
 *
 * @return \WPVitals\ScanStore
 */
function wpvitals_scan_store(): \WPVitals\ScanStore {
	static $wpvitals_scan_store = null;

	if ( null === $wpvitals_scan_store ) {
		$wpvitals_scan_store = new \WPVitals\ScanStore();
	}

	return $wpvitals_scan_store;
}

/**
 * Devuelve el almacén de los ajustes del plugin.
 *
 * @return \WPVitals\SettingsStore
 */
function wpvitals_settings_store(): \WPVitals\SettingsStore {
	static $wpvitals_settings_store = null;

	if ( null === $wpvitals_settings_store ) {
		$wpvitals_settings_store = new \WPVitals\SettingsStore();
	}

	return $wpvitals_settings_store;
}

/**
 * Devuelve el almacén de los hallazgos ignorados.
 *
 * @return \WPVitals\IgnoredStore
 */
function wpvitals_ignored_store(): \WPVitals\IgnoredStore {
	static $wpvitals_ignored_store = null;

	if ( null === $wpvitals_ignored_store ) {
		$wpvitals_ignored_store = new \WPVitals\IgnoredStore();
	}

	return $wpvitals_ignored_store;
}

/**
 * Devuelve el enviador de informes por correo.
 *
 * @return \WPVitals\MailReport
 */
function wpvitals_mailer(): \WPVitals\MailReport {
	static $wpvitals_mailer = null;

	if ( null === $wpvitals_mailer ) {
		$wpvitals_mailer = new \WPVitals\MailReport();
	}

	return $wpvitals_mailer;
}

/**
 * Devuelve el gestor del escaneo programado.
 *
 * @return \WPVitals\Cron
 */
function wpvitals_cron(): \WPVitals\Cron {
	static $wpvitals_cron = null;

	if ( null === $wpvitals_cron ) {
		$wpvitals_cron = new \WPVitals\Cron();
	}

	return $wpvitals_cron;
}

/**
 * Devuelve el orquestador del flujo de escaneo.
 *
 * @return \WPVitals\ScanRunner
 */
function wpvitals_runner(): \WPVitals\ScanRunner {
	static $wpvitals_runner = null;

	if ( null === $wpvitals_runner ) {
		$wpvitals_runner = new \WPVitals\ScanRunner(
			wpvitals_scanner(),
			wpvitals_scan_store(),
			wpvitals_mailer()
		);
	}

	return $wpvitals_runner;
}

/**
 * Ejecuta el escaneo programado por WP-Cron.
 *
 * @return void
 */
function wpvitals_run_scheduled_scan(): void {
	wpvitals_runner()->run( \WPVitals\ScanOutcome::ORIGIN_SCHEDULED, wpvitals_settings_store()->get() );
}

/**
 * Registra las recurrencias propias del plugin en WP-Cron.
 *
 * @param array $schedules Registro de recurrencias existente.
 *
 * @return array
 */
function wpvitals_cron_schedules( array $schedules ): array {
	return \WPVitals\Cron::add_schedules( $schedules );
}

/**
 * Programa el escaneo al activar el plugin.
 *
 * @return void
 */
function wpvitals_activate(): void {
	wpvitals_cron()->schedule( wpvitals_settings_store()->get() );
}

/**
 * Limpia el evento cron al desactivar el plugin.
 *
 * @return void
 */
function wpvitals_deactivate(): void {
	wpvitals_cron()->clear();
}
