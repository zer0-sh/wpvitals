<?php
/**
 * Resultado completo de un escaneo del sitio.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Resultado inmutable de un escaneo completo.
 *
 * Agrega los resultados crudos, el Health Score calculado sobre ellos y los
 * metadatos del escaneo (fecha y origen). Es serializable a array plano para
 * poder persistirse en una opción de WordPress.
 */
final class ScanOutcome {

	/**
	 * Origen manual (botón del panel).
	 *
	 * @var string
	 */
	const ORIGIN_MANUAL = 'manual';

	/**
	 * Origen programado (WP-Cron).
	 *
	 * @var string
	 */
	const ORIGIN_SCHEDULED = 'scheduled';

	/**
	 * Orígenes de escaneo aceptados.
	 *
	 * @var string[]
	 */
	const ALLOWED_ORIGINS = array(
		self::ORIGIN_MANUAL,
		self::ORIGIN_SCHEDULED,
	);

	/**
	 * Resultados del escaneo.
	 *
	 * @var Result[]
	 */
	private $results;

	/**
	 * Origen del escaneo.
	 *
	 * @var string
	 */
	private $origin;

	/**
	 * Marca de tiempo de finalización (segundos epoch).
	 *
	 * @var int
	 */
	private $completed_at;

	/**
	 * Score calculado sobre los resultados.
	 *
	 * @var Score
	 */
	private $score;

	/**
	 * Constructor.
	 *
	 * @param Result[] $results      Resultados del escaneo.
	 * @param string   $origin       Origen (self::ORIGIN_*).
	 * @param int|null $completed_at Marca de tiempo de finalización.
	 *
	 * @throws \InvalidArgumentException Si el origen es desconocido.
	 */
	public function __construct( array $results, string $origin, ?int $completed_at = null ) {
		if ( ! in_array( $origin, self::ALLOWED_ORIGINS, true ) ) {
			throw new \InvalidArgumentException( 'Origen de escaneo desconocido: ' . esc_html( $origin ) );
		}

		$valid = array();

		foreach ( $results as $result ) {
			if ( $result instanceof Result ) {
				$valid[] = $result;
			}
		}

		$this->results      = $valid;
		$this->origin       = $origin;
		$this->completed_at = null !== $completed_at ? $completed_at : (int) time();
		$this->score        = Score::from_results( $this->results );
	}

	/**
	 * Factory equivalente al constructor para composición explícita.
	 *
	 * @param Result[] $results      Resultados del escaneo.
	 * @param string   $origin       Origen (self::ORIGIN_*).
	 * @param int|null $completed_at Marca de tiempo de finalización.
	 *
	 * @return self
	 */
	public static function from_results( array $results, string $origin, ?int $completed_at = null ): self {
		return new self( $results, $origin, $completed_at );
	}

	/**
	 * Devuelve los resultados del escaneo.
	 *
	 * @return Result[]
	 */
	public function get_results(): array {
		return $this->results;
	}

	/**
	 * Devuelve el origen del escaneo.
	 *
	 * @return string
	 */
	public function get_origin(): string {
		return $this->origin;
	}

	/**
	 * Devuelve la marca de tiempo de finalización.
	 *
	 * @return int
	 */
	public function get_completed_at(): int {
		return $this->completed_at;
	}

	/**
	 * Devuelve el Health Score calculado.
	 *
	 * @return Score
	 */
	public function get_score(): Score {
		return $this->score;
	}

	/**
	 * Devuelve la puntuación total (entre 0 y 100).
	 *
	 * @return int
	 */
	public function get_total(): int {
		return $this->score->get_total();
	}

	/**
	 * Devuelve el estado textual del score.
	 *
	 * @return string
	 */
	public function get_state(): string {
		return $this->score->get_state();
	}

	/**
	 * Cuenta las vulnerabilidades activas detectadas.
	 *
	 * @return int
	 */
	public function count_vulnerabilities(): int {
		return $this->count_ids( 'vuln/' );
	}

	/**
	 * Cuenta las actualizaciones pendientes de plugins y temas.
	 *
	 * @return int
	 */
	public function count_pending_updates(): int {
		return $this->count_ids( 'plugin/' ) + $this->count_ids( 'theme/' );
	}

	/**
	 * Cuenta los resultados cuyo identificador comienza por un prefijo.
	 *
	 * @param string $prefix Prefijo de id.
	 *
	 * @return int
	 */
	private function count_ids( string $prefix ): int {
		$count = 0;

		foreach ( $this->results as $result ) {
			if ( 0 === strpos( $result->get_id(), $prefix ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Devuelve una firma estable del contenido del diagnóstico.
	 *
	 * Identifica los resultados por id, severidad y puntos descontados, sin
	 * depender del orden ni de los metadatos del escaneo. Permite detectar si
	 * un escaneo programado introdujo cambios frente al anterior.
	 *
	 * @return string
	 */
	public function signature(): string {
		$parts = array();

		foreach ( $this->results as $result ) {
			$parts[] = implode(
				'|',
				array(
					$result->get_id(),
					$result->get_severity(),
					(string) $result->get_points_deducted(),
				)
			);
		}

		sort( $parts, SORT_STRING );

		return md5( implode( "\n", $parts ) );
	}

	/**
	 * Convierte el escaneo a un array plano persistible.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$results = array();

		foreach ( $this->results as $result ) {
			$results[] = $result->to_array();
		}

		return array(
			'completed_at' => $this->completed_at,
			'origin'       => $this->origin,
			'results'      => $results,
		);
	}

	/**
	 * Reconstruye un escaneo desde un array plano.
	 *
	 * Los resultados corruptos se descartan sin romper el resto, de modo que
	 * el último resultado válido siempre pueda recuperarse.
	 *
	 * @param array $data Array generado por to_array().
	 *
	 * @return self
	 *
	 * @throws \InvalidArgumentException Si el origen es desconocido.
	 */
	public static function from_array( array $data ): self {
		$results = array();

		foreach ( (array) ( isset( $data['results'] ) ? $data['results'] : array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			try {
				$results[] = Result::from_array( $item );
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		return new self(
			$results,
			isset( $data['origin'] ) ? (string) $data['origin'] : self::ORIGIN_SCHEDULED,
			isset( $data['completed_at'] ) ? (int) $data['completed_at'] : null
		);
	}
}
