<?php
/**
 * Tabla base de descuentos del Health Score.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Descuentos por nivel de hallazgo.
 *
 * La tabla por tipo de hallazgo se definirá por completo en la fase del
 * Health Score (todo.md, sección 6); por ahora los checks usan estos dos
 * niveles base para no dispersar números mágicos.
 */
final class ScoreDiscounts {

	/**
	 * Deducción de un hallazgo menor (warning).
	 *
	 * @var int
	 */
	const MINOR = 5;

	/**
	 * Deducción de un hallazgo mayor (crítico).
	 *
	 * @var int
	 */
	const MAJOR = 10;

	/**
	 * Deducción de una vulnerabilidad clasificada como crítica.
	 *
	 * @var int
	 */
	const VULN_CRITICAL = 15;

	/**
	 * Deducción de una vulnerabilidad clasificada como alta.
	 *
	 * @var int
	 */
	const VULN_HIGH = 10;

	/**
	 * Deducción de una vulnerabilidad clasificada como media.
	 *
	 * @var int
	 */
	const VULN_MEDIUM = 5;

	/**
	 * Deducción de una vulnerabilidad clasificada como baja.
	 *
	 * @var int
	 */
	const VULN_LOW = 2;

	/**
	 * Devuelve el descuento correspondiente a una severidad de vulnerabilidad.
	 *
	 * El descuento de media se usa como valor por defecto (y para severidades
	 * que la API no llega a clasificar).
	 *
	 * @param string $severity Severidad (Vulnerability::SEVERITY_*).
	 *
	 * @return int
	 */
	public static function for_vulnerability_severity( string $severity ): int {
		switch ( $severity ) {
			case Vulnerability::SEVERITY_CRITICAL:
				return self::VULN_CRITICAL;

			case Vulnerability::SEVERITY_HIGH:
				return self::VULN_HIGH;

			case Vulnerability::SEVERITY_LOW:
				return self::VULN_LOW;

			case Vulnerability::SEVERITY_MEDIUM:
			default:
				return self::VULN_MEDIUM;
		}
	}
}
