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
 * Parte de 100 puntos y descuenta los puntos embebidos en cada Result. El
 * total se mantiene entre 0 y 100 y se clasifica en tres estados. La lista de
 * descuentos aplicados queda expuesta de forma transparente.
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

		foreach ( $results as $result ) {
			if ( ! $result instanceof Result ) {
				continue;
			}

			$points = $result->get_points_deducted();

			if ( $points < 1 ) {
				continue;
			}

			$deductions[] = array(
				'id'     => $result->get_id(),
				'title'  => $result->get_title(),
				'points' => $points,
			);
		}

		$total = self::MAX - array_sum( array_column( $deductions, 'points' ) );
		$total = max( 0, min( self::MAX, $total ) );

		return new self( $total, $deductions );
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
