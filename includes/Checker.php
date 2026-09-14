<?php
/**
 * Ejecutor principal de los checks locales.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

use WPVitals\Checks\CheckInterface;
use WPVitals\Checks\MultiCheckInterface;

/**
 * Registra y ejecuta los checks locales de forma aislada.
 *
 * Un fallo individual de un check nunca interrumpe el escaneo completo:
 * el checker lo convierte en un Result con severidad SEVERITY_ERROR.
 */
final class Checker {

	/**
	 * Checks registrados, indexados por identificador.
	 *
	 * @var CheckInterface[]
	 */
	private $checks = array();

	/**
	 * Registra un check en el runner.
	 *
	 * @param CheckInterface $check Check a registrar.
	 *
	 * @throws \LogicException Si el identificador del check ya está registrado.
	 */
	public function add_check( CheckInterface $check ): void {
		$id = $check->get_id();

		if ( isset( $this->checks[ $id ] ) ) {
			throw new \LogicException( 'El check con id "' . esc_html( $id ) . '" ya está registrado.' );
		}

		$this->checks[ $id ] = $check;
	}

	/**
	 * Devuelve el número de checks registrados.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->checks );
	}

	/**
	 * Ejecuta todos los checks registrados y devuelve sus resultados.
	 *
	 * @return Result[]
	 */
	public function run_all(): array {
		$results = array();

		foreach ( $this->checks as $check ) {
			if ( $check instanceof MultiCheckInterface ) {
				$results = array_merge( $results, $this->run_many_safe( $check ) );
				continue;
			}

			$results[] = $this->run_single( $check );
		}

		return $results;
	}

	/**
	 * Ejecuta un check capturando cualquier error que pueda producirse.
	 *
	 * @param CheckInterface $check Check a ejecutar.
	 *
	 * @return Result
	 */
	private function run_single( CheckInterface $check ): Result {
		try {
			return $check->run();
		} catch ( \Throwable $e ) {
			return $this->error_result( $check, $e );
		}
	}

	/**
	 * Ejecuta un check multi-resultado y valida que sus hallazgos sean Results.
	 *
	 * @param MultiCheckInterface $check Check a ejecutar.
	 *
	 * @return Result[]
	 */
	private function run_many_safe( MultiCheckInterface $check ): array {
		try {
			$results = $check->run_many();

			return array_map(
				function ( $result ) use ( $check ): Result {
					if ( ! $result instanceof Result ) {
						return new Result(
							$check->get_id(),
							$check->get_title(),
							Result::SEVERITY_ERROR,
							null,
							__( 'The check returned an invalid result.', 'wpvitals' ),
							0
						);
					}

					return $result;
				},
				array_values( $results )
			);
		} catch ( \Throwable $e ) {
			return array( $this->error_result( $check, $e ) );
		}
	}

	/**
	 * Construye un Result de error a partir de la excepción capturada.
	 *
	 * @param CheckInterface $check Check que falló.
	 * @param \Throwable     $e     Excepción capturada.
	 *
	 * @return Result
	 */
	private function error_result( CheckInterface $check, \Throwable $e ): Result {
		return new Result(
			$check->get_id(),
			$check->get_title(),
			Result::SEVERITY_ERROR,
			null,
			sprintf(
				/* translators: %s: internal check error message. */
				__( 'Internal check error: %s', 'wpvitals' ),
				$e->getMessage()
			),
			0
		);
	}
}
