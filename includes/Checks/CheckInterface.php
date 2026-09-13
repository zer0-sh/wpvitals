<?php
/**
 * Contrato común de los checks locales.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals\Checks;

use WPVitals\Result;

/**
 * Contrato de un check de solo lectura.
 *
 * Los checks:
 * - NO modifican archivos, base de datos ni configuración (read-only).
 * - Devuelven siempre un Result, incluso cuando no pueden medir el estado.
 */
interface CheckInterface {

	/**
	 * Devuelve el identificador estable del check.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Devuelve el título legible del check.
	 *
	 * @return string
	 */
	public function get_title(): string;

	/**
	 * Ejecuta el check y devuelve su resultado.
	 *
	 * @return Result
	 */
	public function run(): Result;
}
