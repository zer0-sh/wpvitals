<?php
/**
 * Health Score del sitio.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Puntuación de salud calculada a partir de los resultados de un escaneo.
 *
 * Parte de 100 puntos y descuenta los puntos embebidos en cada Result. Las
 * vulnerabilidades del mismo componente solo descuentan una vez: se aplica la
 * deducción más alta del grupo. El total se mantiene entre 0 y 100 y se
 * clasifica en tres estados. La lista de descuentos quedan transparentes.
 */
final class Score {

	/**
	 * Máxima puntuación posible.
	 *
	 * @var int
	 */
	const MAX = 100;

	/**
	 * Estado saludable: 90-100 puntos.
	 *
	 * @var string
	 */
	const STATE_HEALTHY = 'healthy';

	/**
	 * Estado de atención: 70-89 puntos.
	 *
	 * @var string
	 */
	const STATE_ATTENTION = 'attention';

	/**
	 * Estado crítico: 0-69 puntos.
	 *
	 * @var string
	 */
	const STATE_CRITICAL = 'critical';

	/**
	 * Puntuación final.
	 *
	 * @var int
	 */
	private $total;

	/**
	 * Descuentos aplicados (id, title, points).
	 *
	 * @var array
	 */
	private $deductions;

	/**
	 * Constructor privado (acceso vía factory).
	 *
	 * @param int   $total      Puntuación final.
	 * @param array $deductions Descuentos aplicados.
	 */
	private function __construct( int $total, array $deductions ) {
		$this->total      = $total;
		$this->deductions = $deductions;
	}

	/**
	 * Calcula el score a partir de los resultados de un escaneo.
	 *
	 * Los elementos que no son Result y los resultados sin puntos se ignoran
	 * (un hallazgo severidad error tampoco describe penalización).
	 *
	 * @param Result[] $results Resultados del escaneo.
	 *
	 * @return self
	 */
	public static function from_results( array $results ): self {
		$deductions = array();
		$vuln_worst = array();

		foreach ( $results as $result ) {
			if ( ! $result instanceof Result ) {
				continue;
			}

			$points = $result->get_points_deducted();

			if ( $points < 1 ) {
				continue;
			}

			$id = $result->get_id();

			if ( 0 === strpos( $id, 'vuln/' ) ) {
				$component = self::vulnerability_component( $id );

				if ( isset( $vuln_worst[ $component ] ) ) {
					if ( $points > $vuln_worst[ $component ]['points'] ) {
						$vuln_worst[ $component ] = array(
							'id'     => $id,
							'title'  => $result->get_title(),
							'points' => $points,
						);
					}
					continue;
				}

				$vuln_worst[ $component ] = array(
					'id'     => $id,
					'title'  => $result->get_title(),
					'points' => $points,
				);
				continue;
			}

			$deductions[] = array(
				'id'     => $id,
				'title'  => $result->get_title(),
				'points' => $points,
			);
		}

		foreach ( $vuln_worst as $worst ) {
			$deductions[] = $worst;
		}

		$total = self::MAX - array_sum( array_column( $deductions, 'points' ) );
		$total = max( 0, min( self::MAX, $total ) );

		return new self( $total, $deductions );
	}

	/**
	 * Devuelve el componente (type/slug) de un id de vulnerabilidad.
	 *
	 * Los ids tienen la forma `vuln/{type}/{slug}/{vulnerabilidad}`; para
	 * agrupar por componente se ignoran la parte `vuln` y el id del CVE.
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
	 * Devuelve la puntuación final (entre 0 y 100).
	 *
	 * @return int
	 */
	public function get_total(): int {
		return $this->total;
	}

	/**
	 * Devuelve el estado de salud del sitio.
	 *
	 * @return string
	 */
	public function get_state(): string {
		if ( $this->total >= 90 ) {
			return self::STATE_HEALTHY;
		}

		if ( $this->total >= 70 ) {
			return self::STATE_ATTENTION;
		}

		return self::STATE_CRITICAL;
	}

	/**
	 * Devuelve la lista transparente de descuentos aplicados.
	 *
	 * Cada entrada contiene `id`, `title` y `points` del hallazgo.
	 *
	 * @return array
	 */
	public function get_deductions(): array {
		return $this->deductions;
	}
}
