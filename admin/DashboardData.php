<?php
/**
 * Presentación de los datos del último escaneo para el panel.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Admin;

use WPVitals\Result;
use WPVitals\ScanOutcome;
use WPVitals\Score;

/**
 * Convierte un ScanOutcome en un array plano pensado para la vista.
 *
 * Es un transformador puro (sin salida) para poder testearlo de forma
 * aislada; la vista se encarga de escapar cada campo antes de imprimirlo.
 */
final class DashboardData {

	/**
	 * Orden de presentación de las categorías de hallazgos.
	 *
	 * @var string[]
	 */
	const CATEGORY_ORDER = array(
		'core',
		'php',
		'security',
		'headers',
		'system',
		'plugin',
		'theme',
		'vuln',
	);

	/**
	 * Convierte un escaneo en los datos planos del panel.
	 *
	 * Los identificadores de la lista $ignored se excluyen del score, de los
	 * conteos y de los hallazgos visibles, y se devuelven en el grupo
	 * 'ignored' para la sección desplegable correspondiente.
	 *
	 * @param ScanOutcome|null $outcome Último resultado, o null si no existe.
	 * @param string[]         $ignored Identificadores de hallazgos ignorados.
	 *
	 * @return array
	 */
	public static function build( ?ScanOutcome $outcome, array $ignored = array() ): array {
		if ( null === $outcome ) {
			return array(
				'has_result'            => false,
				'score_total'           => null,
				'score_state'           => null,
				'score_label'           => null,
				'completed_at'          => null,
				'origin'                => null,
				'origin_label'          => null,
				'vulnerabilities_count' => 0,
				'pending_updates_count' => 0,
				'categories'            => array(),
				'ignored'               => array(),
				'ignored_count'         => 0,
			);
		}

		$ignored_set = array_flip( $ignored );
		$visible     = array();
		$hidden      = array();

		foreach ( $outcome->get_results() as $result ) {
			if ( isset( $ignored_set[ $result->get_id() ] ) ) {
				$hidden[] = $result;
			} else {
				$visible[] = $result;
			}
		}

		$score     = Score::from_results( $visible );
		$findings  = array();
		$has_issue = array();

		foreach ( $visible as $result ) {
			if ( $result->is_ok() ) {
				continue;
			}

			$has_issue[ strtok( $result->get_id(), '/' ) ] = true;
			$findings[]                                    = self::finding( $result );
		}

		if ( isset( $has_issue['headers'] ) ) {
			foreach ( $visible as $result ) {
				if ( ! $result->is_ok() || 0 !== strpos( $result->get_id(), 'headers/' ) ) {
					continue;
				}

				$findings[] = self::finding( $result );
			}
		}

		$findings = self::group_vulnerabilities( $findings );

		$ignored_findings = array();

		foreach ( $hidden as $result ) {
			if ( $result->is_ok() ) {
				continue;
			}

			$ignored_findings[] = self::finding( $result );
		}

		return array(
			'has_result'            => true,
			'score_total'           => $score->get_total(),
			'score_state'           => $score->get_state(),
			'score_label'           => self::score_label( $score->get_state() ),
			'completed_at'          => $outcome->get_completed_at(),
			'origin'                => $outcome->get_origin(),
			'origin_label'          => self::origin_label( $outcome->get_origin() ),
			'vulnerabilities_count' => self::count_ids( $visible, 'vuln/' ),
			'pending_updates_count' => self::count_ids( $visible, 'plugin/' ) + self::count_ids( $visible, 'theme/' ),
			'categories'            => self::group_findings( $findings ),
			'ignored'               => $ignored_findings,
			'ignored_count'         => count( $ignored_findings ),
		);
	}

	/**
	 * Cuenta los resultados cuyo identificador comienza por un prefijo.
	 *
	 * @param Result[] $results Resultados del escaneo.
	 * @param string   $prefix  Prefijo de id.
	 *
	 * @return int
	 */
	private static function count_ids( array $results, string $prefix ): int {
		$count = 0;

		foreach ( $results as $result ) {
			if ( 0 === strpos( $result->get_id(), $prefix ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Transforma un hallazgo en un array plano para la vista.
	 *
	 * @param Result $result Hallazgo del escaneo.
	 *
	 * @return array
	 */
	private static function finding( Result $result ): array {
		$value  = $result->get_value();
		$screen = is_array( $value ) && isset( $value['screen'] )
			? (string) $value['screen']
			: self::screen_for_id( $result->get_id() );

		return array(
			'id'             => $result->get_id(),
			'title'          => $result->get_title(),
			'severity'       => $result->get_severity(),
			'severity_label' => self::severity_label( $result->get_severity() ),
			'description'    => self::check_description( $result->get_id() ),
			'recommendation' => $result->get_recommendation(),
			'points'         => $result->get_points_deducted(),
			'value'          => is_string( $value ) ? $value : '',
			'is_ok'          => $result->is_ok(),
			'screen'         => $screen,
			'link'           => self::link_for( $result ),
		);
	}

	/**
	 * Devuelve el componente (type/slug) de un id de vulnerabilidad.
	 *
	 * @param string $id Identificador del hallazgo.
	 *
	 * @return string
	 */
	private static function vulnerability_component( string $id ): string {
		$parts = explode( '/', $id );

		return isset( $parts[1] ) && isset( $parts[2] ) ? $parts[1] . '/' . $parts[2] : $id;
	}

	/**
	 * Agrupa las vulnerabilidades del mismo componente en una sola fila.
	 *
	 * Un componente con varias CVEs relacionadas se muestra como una fila con
	 * su sub-lista expandible; una sola CVE conserva su fila individual.
	 *
	 * @param array $findings Hallazgos planos.
	 *
	 * @return array
	 */
	private static function group_vulnerabilities( array $findings ): array {
		$groups = array();

		foreach ( $findings as $finding ) {
			if ( 0 === strpos( (string) $finding['id'], 'vuln/' ) ) {
				$key              = self::vulnerability_component( (string) $finding['id'] );
				$groups[ $key ][] = $finding;
			}
		}

		$merged  = array();
		$emitted = array();

		foreach ( $findings as $finding ) {
			if ( 0 !== strpos( (string) $finding['id'], 'vuln/' ) ) {
				$merged[] = $finding;
				continue;
			}

			$key   = self::vulnerability_component( (string) $finding['id'] );
			$count = isset( $groups[ $key ] ) ? count( $groups[ $key ] ) : 0;

			if ( $count < 2 ) {
				$merged[] = $finding;
				continue;
			}

			if ( isset( $emitted[ $key ] ) ) {
				continue;
			}

			$emitted[ $key ] = true;
			$merged[]        = self::vulnerability_group( $groups[ $key ] );
		}

		return $merged;
	}

	/**
	 * Construye la fila agrupada de un componente con varias CVEs.
	 *
	 * La fila toma la severidad, puntos y enlace de la CVE más grave; la
	 * sub-lista 'vulnerabilities' mantiene cada CVE individual para la vista.
	 *
	 * @param array $items CVEs del componente (formato de finding()).
	 *
	 * @return array
	 */
	private static function vulnerability_group( array $items ): array {
		$top = $items[0];

		foreach ( $items as $item ) {
			if ( (int) $item['points'] > (int) $top['points'] ) {
				$top = $item;
			}
		}

		$parts = explode( '/', (string) $top['id'] );
		$type  = isset( $parts[1] ) ? $parts[1] : '';
		$slug  = isset( $parts[2] ) ? $parts[2] : '';
		$title = 'core' === $type ? __( 'WordPress', 'wpvitals' ) : $slug;

		return array(
			'id'              => 'vuln/' . $type . '/' . $slug,
			'title'           => $title,
			'severity'        => $top['severity'],
			'severity_label'  => $top['severity_label'],
			'description'     => $top['description'],
			'recommendation'  => sprintf(
				/* translators: 1: número de CVEs, 2: componente afectado. */
				__( 'Se detectaron %1$d CVEs relacionadas a %2$s. Actualiza pronto.', 'wpvitals' ),
				count( $items ),
				$title
			),
			'points'          => $top['points'],
			'value'           => '',
			'is_ok'           => false,
			'screen'          => self::component_screen( $type, $top['screen'] ),
			'link'            => '',
			'vulnerabilities' => $items,
		);
	}

	/**
	 * Devuelve la pantalla nativa donde actualizar el componente afectado.
	 *
	 * @param string      $type     Tipo de componente (core, plugin, theme).
	 * @param string|null $fallback Pantalla original del hallazgo si el tipo no se reconoce.
	 *
	 * @return string|null
	 */
	private static function component_screen( string $type, ?string $fallback ): ?string {
		switch ( $type ) {
			case 'core':
				return 'update-core';

			case 'plugin':
				return 'plugins';

			case 'theme':
				return 'themes';

			default:
				return $fallback;
		}
	}

	/**
	 * Devuelve una breve descripción de qué verifica el check del hallazgo.
	 *
	 * @param string $id Identificador del hallazgo.
	 *
	 * @return string
	 */
	private static function check_description( string $id ): string {
		switch ( $id ) {
			case 'core/version':
				return __( 'Versión del núcleo de WordPress instalada y las actualizaciones disponibles.', 'wpvitals' );

			case 'php/version':
				return __( 'Versión de PHP del servidor y su estado dentro del ciclo de soporte.', 'wpvitals' );

			case 'php/memory':
				return __( 'Límite de memoria configurado para PHP (memory_limit).', 'wpvitals' );

			case 'php/display_errors':
				return __( 'Si los errores PHP son visibles en el sitio (display_errors).', 'wpvitals' );

			case 'php/extensions':
				return __( 'Extensiones de PHP que WordPress necesita y su disponibilidad.', 'wpvitals' );

			case 'security/https':
				return __( 'HTTPS: protocolo seguro que cifra la comunicación entre el visitante y tu sitio.', 'wpvitals' );

			case 'security/xmlrpc':
				return __( 'XML-RPC: interfaz para publicar contenido desde clientes externos; suele ser objetivo de fuerza bruta.', 'wpvitals' );

			case 'security/debug':
				return __( 'Modo de depuración de WordPress (WP_DEBUG) y si es seguro en producción.', 'wpvitals' );

			case 'security/debug_log':
				return __( 'Registro de errores PHP de WordPress (WP_DEBUG_LOG) y su exposición.', 'wpvitals' );

			case 'security/file_edit':
				return __( 'Si se permite editar archivos de plugins y temas desde el escritorio (DISALLOW_FILE_EDIT).', 'wpvitals' );

			case 'system/cron':
				return __( 'WP-Cron: programador interno de tareas periódicas de WordPress.', 'wpvitals' );

			case 'system/loopback':
				return __( 'Loopback: si WordPress puede comunicarse consigo mismo, requisito de WP-Cron y Site Health.', 'wpvitals' );

			case 'components/plugins':
				return __( 'Plugins instalados y las actualizaciones pendientes de cada uno.', 'wpvitals' );

			case 'components/themes':
				return __( 'Temas instalados y las actualizaciones pendientes de cada uno.', 'wpvitals' );

			case 'headers/x-content-type-options':
				return __( 'Cabecera que evita que el navegador interprete archivos con un tipo MIME distinto al declarado.', 'wpvitals' );

			case 'headers/x-frame-options':
				return __( 'Cabecera que impide mostrar tu sitio dentro de iframes de terceros (anti-clickjacking).', 'wpvitals' );

			case 'headers/content-security-policy':
				return __( 'Cabecera que restringe los orígenes y recursos que el navegador puede cargar.', 'wpvitals' );

			case 'headers/referrer-policy':
				return __( 'Cabecera que controla la información de referencia que se filtra a otros sitios.', 'wpvitals' );

			case 'headers/permissions-policy':
				return __( 'Cabecera que limita qué APIs del navegador pueden usar tus páginas.', 'wpvitals' );

			case 'headers/strict-transport-security':
				return __( 'Cabecera que obliga al navegador a conectarse siempre por HTTPS.', 'wpvitals' );
		}

		if ( 0 === strpos( $id, 'headers/' ) ) {
			return __( 'Cabecera HTTP de seguridad enviada por el servidor con cada respuesta.', 'wpvitals' );
		}

		if ( 0 === strpos( $id, 'plugin/' ) ) {
			return __( 'Plugins instalados y las actualizaciones pendientes de cada uno.', 'wpvitals' );
		}

		if ( 0 === strpos( $id, 'theme/' ) ) {
			return __( 'Temas instalados y las actualizaciones pendientes de cada uno.', 'wpvitals' );
		}

		if ( 0 === strpos( $id, 'vuln/' ) ) {
			return __( 'Vulnerabilidad publicada en el registro de seguridad de WordPress (WPScan).', 'wpvitals' );
		}

		return __( 'Comprobación del diagnóstico de salud, seguridad y rendimiento del sitio.', 'wpvitals' );
	}

	/**
	 * Devuelve el enlace externo de referencia si existe.
	 *
	 * @param Result $result Hallazgo del escaneo.
	 *
	 * @return string
	 */
	private static function link_for( Result $result ): string {
		if ( 0 !== strpos( $result->get_id(), 'vuln/' ) ) {
			return '';
		}

		$value = $result->get_value();

		return is_array( $value ) && isset( $value['link'] ) ? (string) $value['link'] : '';
	}

	/**
	 * Resuelve la pantalla nativa por prefijo de id.
	 *
	 * @param string $id Identificador del hallazgo.
	 *
	 * @return string|null
	 */
	private static function screen_for_id( string $id ): ?string {
		if ( 0 === strpos( $id, 'plugin/' ) ) {
			return 'plugins';
		}

		if ( 0 === strpos( $id, 'theme/' ) ) {
			return 'themes';
		}

		if ( 0 === strpos( $id, 'core/' ) ) {
			return 'update-core';
		}

		if ( 0 === strpos( $id, 'php/' ) || 0 === strpos( $id, 'system/' ) ) {
			return 'site-health';
		}

		if ( 0 === strpos( $id, 'security/' ) ) {
			return 'options-general';
		}

		return null;
	}

	/**
	 * Agrupa los hallazgos por categoría con un orden estable.
	 *
	 * @param array $findings Hallazgos planos.
	 *
	 * @return array
	 */
	private static function group_findings( array $findings ): array {
		$buckets = array();

		foreach ( self::CATEGORY_ORDER as $key ) {
			$buckets[ $key ] = array();
		}

		foreach ( $findings as $finding ) {
			$key = strtok( (string) $finding['id'], '/' );
			$key = false !== $key ? $key : 'otros';

			if ( ! isset( $buckets[ $key ] ) ) {
				$buckets[ $key ] = array();
			}

			$buckets[ $key ][] = $finding;
		}

		return self::ordered_groups( $buckets );
	}

	/**
	 * Ordena los grupos por categoría conocida primero y el resto alfabético.
	 *
	 * @param array $buckets Claves de categoría con sus hallazgos.
	 *
	 * @return array
	 */
	private static function ordered_groups( array $buckets ): array {
		$groups = array();

		foreach ( self::CATEGORY_ORDER as $key ) {
			if ( array() !== $buckets[ $key ] ) {
				$groups[] = array(
					'key'      => $key,
					'label'    => self::category_label( $key ),
					'findings' => $buckets[ $key ],
				);
			}
		}

		foreach ( $buckets as $key => $items ) {
			if ( in_array( $key, self::CATEGORY_ORDER, true ) || array() === $items ) {
				continue;
			}

			$groups[] = array(
				'key'      => $key,
				'label'    => $key,
				'findings' => $items,
			);
		}

		return $groups;
	}

	/**
	 * Devuelve la etiqueta textual del estado del score.
	 *
	 * @param string $state Estado (Score::STATE_*).
	 *
	 * @return string
	 */
	public static function score_label( string $state ): string {
		switch ( $state ) {
			case Score::STATE_HEALTHY:
				return __( 'Saludable', 'wpvitals' );

			case Score::STATE_ATTENTION:
				return __( 'Requiere atención', 'wpvitals' );

			case Score::STATE_CRITICAL:
			default:
				return __( 'Crítico', 'wpvitals' );
		}
	}

	/**
	 * Devuelve la etiqueta textual de un origen de escaneo.
	 *
	 * @param string $origin Origen (ScanOutcome::ORIGIN_*).
	 *
	 * @return string
	 */
	public static function origin_label( string $origin ): string {
		if ( ScanOutcome::ORIGIN_MANUAL === $origin ) {
			return __( 'Manual', 'wpvitals' );
		}

		return __( 'Programado', 'wpvitals' );
	}

	/**
	 * Devuelve la etiqueta textual de una severidad.
	 *
	 * @param string $severity Severidad (Result::SEVERITY_*).
	 *
	 * @return string
	 */
	public static function severity_label( string $severity ): string {
		switch ( $severity ) {
			case Result::SEVERITY_OK:
				return __( 'Bien', 'wpvitals' );

			case Result::SEVERITY_INFO:
				return __( 'Información', 'wpvitals' );

			case Result::SEVERITY_WARNING:
				return __( 'Aviso', 'wpvitals' );

			case Result::SEVERITY_CRITICAL:
				return __( 'Crítico', 'wpvitals' );

			case Result::SEVERITY_ERROR:
			default:
				return __( 'Error', 'wpvitals' );
		}
	}

	/**
	 * Devuelve la etiqueta textual de una categoría de hallazgos.
	 *
	 * @param string $key Clave de categoría.
	 *
	 * @return string
	 */
	public static function category_label( string $key ): string {
		switch ( $key ) {
			case 'core':
				return __( 'Núcleo de WordPress', 'wpvitals' );

			case 'php':
				return __( 'PHP', 'wpvitals' );

			case 'security':
				return __( 'Seguridad', 'wpvitals' );

			case 'system':
				return __( 'Sistema', 'wpvitals' );

			case 'headers':
				return __( 'Cabeceras de seguridad', 'wpvitals' );

			case 'plugin':
				return __( 'Plugins', 'wpvitals' );

			case 'theme':
				return __( 'Temas', 'wpvitals' );

			case 'vuln':
				return __( 'Vulnerabilidades', 'wpvitals' );

			default:
				return $key;
		}
	}
}
