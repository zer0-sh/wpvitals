<?php
/**
 * Orquestador del escaneo completo del sitio.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Ejecuta los checks locales y las consultas de vulnerabilidades y agrega
 * ambos en un único ScanOutcome con su Health Score.
 *
 * Las fuentes de datos (versiones instaladas, fecha) y el cliente de la API
 * se inyectan para poder testearlo standalone sin WordPress.
 */
final class Scanner {

	/**
	 * Ejecutor de los checks locales.
	 *
	 * @var Checker
	 */
	private $checker;

	/**
	 * Cliente de la WPVulnerability API.
	 *
	 * @var VulnerabilityClient
	 */
	private $client;

	/**
	 * Fuente de la versión de WordPress instalada.
	 *
	 * @var callable
	 */
	private $get_core_version;

	/**
	 * Fuente de los plugins instalados con su versión.
	 *
	 * @var callable
	 */
	private $get_plugins;

	/**
	 * Fuente de los temas instalados con su versión.
	 *
	 * @var callable
	 */
	private $get_themes;

	/**
	 * Fuente del reloj (segundos epoch).
	 *
	 * @var callable
	 */
	private $now;

	/**
	 * Constructor.
	 *
	 * @param Checker             $checker          Ejecutor de checks locales.
	 * @param VulnerabilityClient $client           Cliente de la API de vulnerabilidades.
	 * @param callable|null       $get_core_version Callable(): string.
	 * @param callable|null       $get_plugins      Callable(): array{slug: version}.
	 * @param callable|null       $get_themes       Callable(): array{slug: version}.
	 * @param callable|null       $now              Callable(): int.
	 */
	public function __construct(
		Checker $checker,
		VulnerabilityClient $client,
		?callable $get_core_version = null,
		?callable $get_plugins = null,
		?callable $get_themes = null,
		?callable $now = null
	) {
		$this->checker          = $checker;
		$this->client           = $client;
		$this->get_core_version = null !== $get_core_version ? $get_core_version : static function (): string {
			return (string) \get_bloginfo( 'version' );
		};
		$this->get_plugins      = null !== $get_plugins ? $get_plugins : function (): array {
			$versions = array();

			foreach ( \get_plugins() as $file => $data ) {
				if ( is_array( $data ) && isset( $data['Version'] ) ) {
					$versions[ $this->slug_from_file( (string) $file ) ] = (string) $data['Version'];
				}
			}

			return $versions;
		};
		$this->get_themes       = null !== $get_themes ? $get_themes : static function (): array {
			$versions = array();

			foreach ( \wp_get_themes() as $slug => $theme ) {
				$versions[ (string) $slug ] = (string) $theme->get( 'Version' );
			}

			return $versions;
		};
		$this->now              = null !== $now ? $now : static function (): int {
			return (int) time();
		};
	}

	/**
	 * Ejecuta un escaneo completo del sitio.
	 *
	 * @param string $origin  Origen del escaneo (ScanOutcome::ORIGIN_*).
	 * @param bool   $refresh Si true, ignora la caché de vulnerabilidades.
	 *
	 * @return ScanOutcome
	 *
	 * @throws \InvalidArgumentException Si el origen es desconocido.
	 */
	public function scan( string $origin, bool $refresh = false ): ScanOutcome {
		$results = $this->checker->run_all();
		$refresh = $refresh || ScanOutcome::ORIGIN_MANUAL === $origin;

		$results = array_merge( $results, $this->vulnerability_results( $refresh ) );

		return ScanOutcome::from_results(
			$results,
			$origin,
			(int) call_user_func( $this->now )
		);
	}

	/**
	 * Ejecuta las consultas de vulnerabilidades de todos los componentes.
	 *
	 * Los fallos de la API no interrumpen el escaneo: un componente que falla
	 * simplemente no aporta hallazgos.
	 *
	 * @param bool $refresh Si true, ignora la caché.
	 *
	 * @return Result[]
	 */
	private function vulnerability_results( bool $refresh ): array {
		$results = array_merge( array(), $this->core_results( $refresh ) );

		foreach ( call_user_func( $this->get_plugins ) as $slug => $version ) {
			$results = array_merge( $results, $this->component_results( 'plugin', (string) $slug, (string) $version, $refresh ) );
		}

		foreach ( call_user_func( $this->get_themes ) as $slug => $version ) {
			$results = array_merge( $results, $this->component_results( 'theme', (string) $slug, (string) $version, $refresh ) );
		}

		return $results;
	}

	/**
	 * Consulta las vulnerabilidades del Core activo.
	 *
	 * @param bool $refresh Si true, ignora la caché.
	 *
	 * @return Result[]
	 */
	private function core_results( bool $refresh ): array {
		$version = trim( (string) call_user_func( $this->get_core_version ) );

		if ( '' === $version ) {
			return array();
		}

		$results = array();

		foreach ( $this->client->check_core( $version, $refresh )->get_vulnerabilities() as $vulnerability ) {
			$results[] = $this->vulnerability_result( $vulnerability, 'core', 'WordPress' );
		}

		return $results;
	}

	/**
	 * Consulta las vulnerabilidades de un componente con versión.
	 *
	 * @param string $type    Tipo de componente (plugin|theme).
	 * @param string $slug    Slug del componente.
	 * @param string $version Versión instalada.
	 * @param bool   $refresh Si true, ignora la caché.
	 *
	 * @return Result[]
	 */
	private function component_results( string $type, string $slug, string $version, bool $refresh ): array {
		if ( '' === $version ) {
			return array();
		}

		$query = 'plugin' === $type
			? $this->client->check_plugin( $slug, $version, $refresh )
			: $this->client->check_theme( $slug, $version, $refresh );

		$results = array();

		foreach ( $query->get_vulnerabilities() as $vulnerability ) {
			$results[] = $this->vulnerability_result( $vulnerability, $type, $slug );
		}

		return $results;
	}

	/**
	 * Convierte una vulnerabilidad en un Result de diagnóstico.
	 *
	 * La severidad critical se mapea a critical; el resto a warning. En el
	 * valor se guarda la pantalla nativa pertinente para que la vista enlace.
	 *
	 * @param Vulnerability $vulnerability Vulnerabilidad detectada.
	 * @param string        $type          Tipo de componente (core|plugin|theme).
	 * @param string        $slug          Slug del componente.
	 *
	 * @return Result
	 */
	private function vulnerability_result( Vulnerability $vulnerability, string $type, string $slug ): Result {
		$is_critical = Vulnerability::SEVERITY_CRITICAL === $vulnerability->get_severity();

		return new Result(
			'vuln/' . $type . '/' . $slug . '/' . $vulnerability->get_id(),
			$vulnerability->get_title(),
			$is_critical ? Result::SEVERITY_CRITICAL : Result::SEVERITY_WARNING,
			array(
				'severity' => $vulnerability->get_severity(),
				'link'     => $vulnerability->get_source_link(),
				'screen'   => $this->screen_for( $type ),
			),
			$this->recommendation_for( $vulnerability ),
			$vulnerability->get_points()
		);
	}

	/**
	 * Construye la recomendación de una vulnerabilidad.
	 *
	 * @param Vulnerability $vulnerability Vulnerabilidad detectada.
	 *
	 * @return string
	 */
	private function recommendation_for( Vulnerability $vulnerability ): string {
		$label = sprintf(
			/* translators: 1: nombre de la fuente (p. ej. CVE-2024-12345), 2: severidad. */
			__( 'Vulnerabilidad %1$s de severidad %2$s. Actualiza el componente a una versión segura.', 'wpvitals' ),
			$vulnerability->get_source_name(),
			$vulnerability->get_severity()
		);

		if ( '' === $vulnerability->get_description() ) {
			return $label;
		}

		return $label . ' ' . $vulnerability->get_description();
	}

	/**
	 * Devuelve la pantalla nativa pertinente para un componente.
	 *
	 * @param string $type Tipo de componente (plugin|theme|core).
	 *
	 * @return string
	 */
	private function screen_for( string $type ): string {
		switch ( $type ) {
			case 'plugin':
				return 'plugins';

			case 'theme':
				return 'themes';

			case 'core':
			default:
				return 'update-core';
		}
	}

	/**
	 * Deriva el slug de un archivo de plugin.
	 *
	 * @param string $plugin_file Ruta del plugin (p. ej. "akismet/akismet.php").
	 *
	 * @return string
	 */
	private function slug_from_file( string $plugin_file ): string {
		$slug = \dirname( $plugin_file );

		if ( '.' === $slug ) {
			$slug = \basename( $plugin_file, '.php' );
		}

		return $slug;
	}
}
