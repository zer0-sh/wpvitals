<?php
/**
 * Pantallas de administración del plugin.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Admin;

use WPVitals\Cron;
use WPVitals\IgnoredStore;
use WPVitals\ScanOutcome;
use WPVitals\ScanRunner;
use WPVitals\ScanStore;
use WPVitals\Settings;
use WPVitals\SettingsStore;

/**
 * Registra el panel y la pantalla de ajustes y atiende sus formularios.
 *
 * El escaneo manual es síncrono: protege su formulario con nonce, valida la
 * capacidad del usuario y bloquea ejecuciones simultáneas con una transient.
 * Los ajustes se sanan con Settings::from_unsafe() y reprograman el cron.
 */
final class AdminPage {

	/**
	 * Slug de la pantalla del plugin.
	 *
	 * @var string
	 */
	const SLUG = 'wpvitals';

	/**
	 * Slug de la pantalla de ajustes.
	 *
	 * @var string
	 */
	const SLUG_SETTINGS = 'wpvitals-settings';

	/**
	 * Acción admin_post del escaneo manual.
	 *
	 * @var string
	 */
	const SCAN_ACTION = 'wpvitals_scan';

	/**
	 * Acción admin_post de guardado de ajustes.
	 *
	 * @var string
	 */
	const SETTINGS_ACTION = 'wpvitals_settings';

	/**
	 * Acción admin_post para ignorar un hallazgo.
	 *
	 * @var string
	 */
	const IGNORE_ACTION = 'wpvitals_ignore';

	/**
	 * Acción admin_post para restaurar un hallazgo ignorado.
	 *
	 * @var string
	 */
	const UNIGNORE_ACTION = 'wpvitals_unignore';

	/**
	 * Acción admin_post para restaurar todos los ignorados.
	 *
	 * @var string
	 */
	const RESTORE_ALL_ACTION = 'wpvitals_restore_all';

	/**
	 * Clave de la transient que bloquea escaneos simultáneos.
	 *
	 * @var string
	 */
	const LOCK_KEY = 'wpvitals_scan_lock';

	/**
	 * Segundos de vigencia del bloqueo del escaneo.
	 *
	 * @var int
	 */
	const LOCK_TTL = 60;

	/**
	 * Hooks en los que debe cargarse el CSS del panel.
	 *
	 * @var string[]
	 */
	const SCREENS = array(
		'toplevel_page_wpvitals',
		'wpvitals_page_wpvitals-settings',
	);

	/**
	 * Orquestador del escaneo.
	 *
	 * @var ScanRunner
	 */
	private $runner;

	/**
	 * Almacén del último resultado.
	 *
	 * @var ScanStore
	 */
	private $store;

	/**
	 * Gestor del escaneo programado.
	 *
	 * @var Cron
	 */
	private $cron;

	/**
	 * Almacén de los ajustes del plugin.
	 *
	 * @var SettingsStore
	 */
	private $settings_store;

	/**
	 * Almacén de los hallazgos ignorados.
	 *
	 * @var IgnoredStore
	 */
	private $ignored_store;

	/**
	 * Constructor.
	 *
	 * @param ScanRunner    $runner         Orquestador del escaneo.
	 * @param ScanStore     $store          Almacén del último resultado.
	 * @param Cron          $cron           Gestor del escaneo programado.
	 * @param SettingsStore $settings_store Almacén de los ajustes.
	 * @param IgnoredStore  $ignored_store  Almacén de los hallazgos ignorados.
	 */
	public function __construct( ScanRunner $runner, ScanStore $store, Cron $cron, SettingsStore $settings_store, IgnoredStore $ignored_store ) {
		$this->runner         = $runner;
		$this->store          = $store;
		$this->cron           = $cron;
		$this->settings_store = $settings_store;
		$this->ignored_store  = $ignored_store;
	}

	/**
	 * Registra las acciones de WordPress del panel.
	 *
	 * @return void
	 */
	public function register(): void {
		\add_action( 'admin_menu', array( $this, 'register_menu' ) );
		\add_action( 'admin_post_' . self::SCAN_ACTION, array( $this, 'handle_scan' ) );
		\add_action( 'admin_post_' . self::SETTINGS_ACTION, array( $this, 'handle_settings' ) );
		\add_action( 'admin_post_' . self::IGNORE_ACTION, array( $this, 'handle_ignore' ) );
		\add_action( 'admin_post_' . self::UNIGNORE_ACTION, array( $this, 'handle_unignore' ) );
		\add_action( 'admin_post_' . self::RESTORE_ALL_ACTION, array( $this, 'handle_restore_all' ) );
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Añade las entradas del plugin al menú de administración.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		\add_menu_page(
			__( 'Vitals', 'wpvitals' ),
			__( 'Vitals', 'wpvitals' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-heart',
			80
		);

		\add_submenu_page(
			self::SLUG,
			__( 'Ajustes', 'wpvitals' ),
			__( 'Ajustes', 'wpvitals' ),
			'manage_options',
			self::SLUG_SETTINGS,
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Procesa el escaneo manual enviado por el formulario.
	 *
	 * @return void
	 */
	public function handle_scan(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'No tienes permisos para ejecutar un escaneo.', 'wpvitals' ) );
		}

		\check_admin_referer( self::SCAN_ACTION );

		if ( false !== \get_transient( self::LOCK_KEY ) ) {
			\wp_safe_redirect(
				\add_query_arg( 'wpvitals_scan', 'busy', \admin_url( 'admin.php?page=' . self::SLUG ) )
			);
			exit;
		}

		\set_transient( self::LOCK_KEY, 1, self::LOCK_TTL );

		try {
			$this->runner->run( ScanOutcome::ORIGIN_MANUAL, $this->settings_store->get() );
			$status = 'ok';
		} catch ( \Throwable $e ) {
			$status = 'error';
		}

		\delete_transient( self::LOCK_KEY );

		\wp_safe_redirect(
			\add_query_arg( 'wpvitals_scan', $status, \admin_url( 'admin.php?page=' . self::SLUG ) )
		);
		exit;
	}

	/**
	 * Procesa el formulario de ajustes enviado por el panel.
	 *
	 * @return void
	 */
	public function handle_settings(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'No tienes permisos para modificar los ajustes.', 'wpvitals' ) );
		}

		\check_admin_referer( self::SETTINGS_ACTION );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Los campos crudos del formulario se sanean en Settings::from_unsafe() con sanitize_email/is_email; la existencia se comprueba dentro del propio helper.
		$raw = isset( $_POST['wpvitals'] ) && is_array( $_POST['wpvitals'] ) ? $_POST['wpvitals'] : array();
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing

		$settings = Settings::from_unsafe( $raw );

		$this->settings_store->save( $settings );
		$this->cron->schedule( $settings );

		\wp_safe_redirect(
			\add_query_arg( 'wpvitals_settings', 'updated', \admin_url( 'admin.php?page=' . self::SLUG_SETTINGS ) )
		);
		exit;
	}

	/**
	 * Procesa la petición de ignorar un hallazgo.
	 *
	 * @return void
	 */
	public function handle_ignore(): void {
		$this->toggle_ignored( self::IGNORE_ACTION, 'ignored' );
	}

	/**
	 * Procesa la petición de restaurar un hallazgo ignorado.
	 *
	 * @return void
	 */
	public function handle_unignore(): void {
		$this->toggle_ignored( self::UNIGNORE_ACTION, 'restored' );
	}

	/**
	 * Procesa la petición de restaurar todos los hallazgos ignorados.
	 *
	 * @return void
	 */
	public function handle_restore_all(): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'No tienes permisos para modificar los hallazgos ignorados.', 'wpvitals' ) );
		}

		\check_admin_referer( self::RESTORE_ALL_ACTION );

		$this->ignored_store->clear();

		\wp_safe_redirect(
			\add_query_arg( 'wpvitals_scan', 'restored_all', \admin_url( 'admin.php?page=' . self::SLUG ) )
		);
		exit;
	}

	/**
	 * Atiende las peticiones de marcar o desmarcar un hallazgo como ignorado.
	 *
	 * @param string $action Acción admin_post (self::IGNORE_ACTION|self::UNIGNORE_ACTION).
	 * @param string $status Estado mostrado tras el redirect.
	 *
	 * @return void
	 */
	private function toggle_ignored( string $action, string $status ): void {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'No tienes permisos para modificar los hallazgos ignorados.', 'wpvitals' ) );
		}

		\check_admin_referer( $action );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- El identificador se valida contra una lista blanca de caracteres en IgnoredStore::sanitize_id(); la petición está protegida por nonce y capacidad.
		$raw = isset( $_GET['check'] ) ? $_GET['check'] : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$id = IgnoredStore::sanitize_id( $raw );

		if ( '' !== $id ) {
			if ( self::IGNORE_ACTION === $action ) {
				$this->ignored_store->add( $id );
			} else {
				$this->ignored_store->remove( $id );
			}
		}

		if ( '' === $id ) {
			$status = 'error';
		}

		\wp_safe_redirect(
			\add_query_arg( 'wpvitals_scan', $status, \admin_url( 'admin.php?page=' . self::SLUG ) )
		);
		exit;
	}

	/**
	 * Carga el CSS del panel y de la pantalla de ajustes.
	 *
	 * @param string $hook_suffix Sufijo de la pantalla actual.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, self::SCREENS, true ) ) {
			return;
		}

		\wp_enqueue_style(
			'wpvitals-admin',
			\plugin_dir_url( \WPVITALS_PLUGIN_FILE ) . 'assets/css/admin.css',
			array(),
			'0.1.0'
		);
	}

	/**
	 * Renderiza la vista principal del panel.
	 *
	 * @return void
	 */
	public function render(): void {
		$data                 = DashboardData::build( $this->store->get(), $this->ignored_store->get() );
		$data['notice']       = self::query_status( 'wpvitals_scan' );
		$data['settings']     = $this->settings_store->get();
		$data['next_run']     = $this->cron->next_run();
		$data['site']         = SiteInfo::build();
		$data['settings_url'] = \admin_url( 'admin.php?page=' . self::SLUG_SETTINGS );

		include __DIR__ . '/views/dashboard.php';
	}

	/**
	 * Renderiza la vista de ajustes del plugin.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		$data             = array();
		$data['notice']   = self::query_status( 'wpvitals_settings' );
		$data['settings'] = $this->settings_store->get();
		$data['next_run'] = $this->cron->next_run();

		include __DIR__ . '/views/settings.php';
	}

	/**
	 * Lee un parámetro de estado propio del plugin de la querystring.
	 *
	 * Los estados solo llegan en los redirects posteriores a acciones
	 * protegidas con nonce y capacidad; el valor se sanea antes de usarlo.
	 *
	 * @param string $key Clave del parámetro de estado.
	 *
	 * @return string
	 */
	private static function query_status( string $key ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Únicamente lectura de un estado de redirect propio tras acciones con nonce y capacidad; el valor se sanea con sanitize_key.
		$raw = isset( $_GET[ $key ] ) ? \wp_unslash( $_GET[ $key ] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return is_string( $raw ) ? \sanitize_key( $raw ) : '';
	}

	/**
	 * Construye la URL firmada para marcar o restaurar un hallazgo.
	 *
	 * @param string $action Acción admin_post (self::IGNORE_ACTION|self::UNIGNORE_ACTION).
	 * @param string $id     Identificador del hallazgo.
	 *
	 * @return string
	 */
	public static function toggle_ignored_url( string $action, string $id ): string {
		return \wp_nonce_url(
			\add_query_arg(
				array(
					'action' => $action,
					'check'  => $id,
				),
				\admin_url( 'admin-post.php' )
			),
			$action
		);
	}

	/**
	 * Construye la URL firmada para restaurar todos los ignorados.
	 *
	 * @return string
	 */
	public static function restore_all_url(): string {
		return \wp_nonce_url(
			\add_query_arg( 'action', self::RESTORE_ALL_ACTION, \admin_url( 'admin-post.php' ) ),
			self::RESTORE_ALL_ACTION
		);
	}

	/**
	 * Construye una pantalla nativa de administración.
	 *
	 * @param string|null $screen Clave de pantalla nativa.
	 *
	 * @return string
	 */
	public static function screen_url( ?string $screen ): string {
		switch ( $screen ) {
			case 'plugins':
				return \admin_url( 'plugins.php' );

			case 'themes':
				return \admin_url( 'themes.php' );

			case 'update-core':
				return \admin_url( 'update-core.php' );

			case 'options-general':
				return \admin_url( 'options-general.php' );

			case 'site-health':
				return \admin_url( 'site-health.php' );

			default:
				return '';
		}
	}

	/**
	 * Devuelve el título legible de una pantalla nativa.
	 *
	 * @param string|null $screen Clave de pantalla nativa.
	 *
	 * @return string
	 */
	public static function screen_label( ?string $screen ): string {
		switch ( $screen ) {
			case 'plugins':
				return __( 'Plugins', 'wpvitals' );

			case 'themes':
				return __( 'Temas', 'wpvitals' );

			case 'update-core':
				return __( 'Actualizaciones', 'wpvitals' );

			case 'options-general':
				return __( 'Ajustes generales', 'wpvitals' );

			case 'site-health':
				return __( 'Salud del sitio', 'wpvitals' );

			default:
				return '';
		}
	}
}
