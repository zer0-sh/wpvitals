<?php
/**
 * Base compartida para los checks locales.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;

/**
 * Clase base que centraliza la construcción de resultados.
 */
abstract class AbstractCheck implements CheckInterface {

	/**
	 * Construye un Result utilizando id y título propios del check.
	 *
	 * @param string $severity        Severidad (Result::SEVERITY_*).
	 * @param mixed  $value           Valor detectado.
	 * @param string $recommendation  Recomendación accionable.
	 * @param int    $points_deducted Puntos descontados, entre 0 y Result::POINTS_MAX.
	 *
	 * @return Result
	 */
	protected function result(
		string $severity,
		$value,
		string $recommendation = '',
		int $points_deducted = 0
	): Result {
		return new Result(
			$this->get_id(),
			$this->get_title(),
			$severity,
			$value,
			$recommendation,
			$points_deducted
		);
	}
}
