<?php
/**
 * Resultado inmutable de un check.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Valor de retorno de un check.
 *
 * Es un objeto value inmutable: una vez construido no expone setters ni estado
 * compartido. Las vistas del admin deben escapar los campos antes de mostrarlos.
 */
final class Result {

	const SEVERITY_OK       = 'ok';
	const SEVERITY_INFO     = 'info';
	const SEVERITY_WARNING  = 'warning';
	const SEVERITY_CRITICAL = 'critical';
	const SEVERITY_ERROR    = 'error';

	const ALLOWED_SEVERITIES = array(
		self::SEVERITY_OK,
		self::SEVERITY_INFO,
		self::SEVERITY_WARNING,
		self::SEVERITY_CRITICAL,
		self::SEVERITY_ERROR,
	);

	const POINTS_MAX = 100;

	/**
	 * Identificador estable del check.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Título legible del resultado.
	 *
	 * @var string
	 */
	private $title;

	/**
	 * Severidad (self::SEVERITY_*).
	 *
	 * @var string
	 */
	private $severity;

	/**
	 * Valor detectado.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * Recomendación accionable.
	 *
	 * @var string
	 */
	private $recommendation;

	/**
	 * Puntos descontados del Health Score.
	 *
	 * @var int
	 */
	private $points_deducted;

	/**
	 * Constructor.
	 *
	 * @param string $id              Identificador estable del check.
	 * @param string $title           Título legible del resultado.
	 * @param string $severity        Severidad (self::SEVERITY_*).
	 * @param mixed  $value           Valor detectado.
	 * @param string $recommendation  Recomendación accionable.
	 * @param int    $points_deducted Puntos descontados, entre 0 y self::POINTS_MAX.
	 *
	 * @throws \InvalidArgumentException Si la severidad no existe o los puntos están fuera de rango.
	 */
	public function __construct(
		string $id,
		string $title,
		string $severity,
		$value,
		string $recommendation = '',
		int $points_deducted = 0
	) {
		if ( ! in_array( $severity, self::ALLOWED_SEVERITIES, true ) ) {
			throw new \InvalidArgumentException( 'Severidad de resultado desconocida: ' . esc_html( $severity ) );
		}

		if ( $points_deducted < 0 || $points_deducted > self::POINTS_MAX ) {
			throw new \InvalidArgumentException( 'Puntos descontados fuera de rango: ' . esc_html( (string) $points_deducted ) );
		}

		$this->id              = $id;
		$this->title           = $title;
		$this->severity        = $severity;
		$this->value           = $value;
		$this->recommendation  = $recommendation;
		$this->points_deducted = $points_deducted;
	}

	/**
	 * Devuelve el identificador estable.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Devuelve el título legible.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Devuelve la severidad.
	 *
	 * @return string
	 */
	public function get_severity(): string {
		return $this->severity;
	}

	/**
	 * Devuelve el valor detectado.
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Devuelve la recomendación.
	 *
	 * @return string
	 */
	public function get_recommendation(): string {
		return $this->recommendation;
	}

	/**
	 * Devuelve los puntos descontados del Health Score.
	 *
	 * @return int
	 */
	public function get_points_deducted(): int {
		return $this->points_deducted;
	}

	/**
	 * Indica si el resultado representa un estado correcto.
	 *
	 * @return bool
	 */
	public function is_ok(): bool {
		return self::SEVERITY_OK === $this->severity
			|| self::SEVERITY_INFO === $this->severity;
	}

	/**
	 * Convierte el resultado a un array plano persistible.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'              => $this->id,
			'title'           => $this->title,
			'severity'        => $this->severity,
			'value'           => $this->value,
			'recommendation'  => $this->recommendation,
			'points_deducted' => $this->points_deducted,
		);
	}

	/**
	 * Reconstruye un resultado desde un array plano.
	 *
	 * @param array $data Array generado por to_array().
	 *
	 * @return self
	 *
	 * @throws \InvalidArgumentException Si faltan campos obligatorios.
	 */
	public static function from_array( array $data ): self {
		if ( ! isset( $data['id'] ) || ! isset( $data['title'] ) || ! isset( $data['severity'] ) ) {
			throw new \InvalidArgumentException( 'Resultado persistido incompleto.' );
		}

		return new self(
			(string) $data['id'],
			(string) $data['title'],
			(string) $data['severity'],
			array_key_exists( 'value', $data ) ? $data['value'] : null,
			isset( $data['recommendation'] ) ? (string) $data['recommendation'] : '',
			isset( $data['points_deducted'] ) ? (int) $data['points_deducted'] : 0
		);
	}
}
