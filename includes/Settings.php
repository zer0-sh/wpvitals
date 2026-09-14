<?php
/**
 * Configuración del plugin.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Snapshot inmutable de la configuración de WPVitals.
 *
 * Recoge la frecuencia del escaneo programado, el envío de informes por
 * correo y el destinatario opcional. La construcción valida de forma estricta;
 * los datos crudos de formulario se sanean antes con from_unsafe().
 */
final class Settings {

	const FREQUENCY_DISABLED = 'disabled';
	const FREQUENCY_DAILY    = 'daily';
	const FREQUENCY_WEEKLY   = 'weekly';
	const FREQUENCY_MONTHLY  = 'monthly';

	const FREQUENCIES = array(
		self::FREQUENCY_DISABLED,
		self::FREQUENCY_DAILY,
		self::FREQUENCY_WEEKLY,
		self::FREQUENCY_MONTHLY,
	);

	const DEFAULT_FREQUENCY    = self::FREQUENCY_DAILY;
	const DEFAULT_MAIL_ENABLED = true;
	const DEFAULT_TIME_24H     = false;

	const INTERVAL_DAILY   = 86400;
	const INTERVAL_WEEKLY  = 604800;
	const INTERVAL_MONTHLY = 2592000;

	/**
	 * Frecuencia del escaneo programado.
	 *
	 * @var string
	 */
	private $frequency;

	/**
	 * Indica si se envían informes por correo.
	 *
	 * @var bool
	 */
	private $mail_enabled;

	/**
	 * Destinatario personalizado; vacío = correo del administrador.
	 *
	 * @var string
	 */
	private $recipient;

	/**
	 * Indica si las horas se muestran en formato de 24 horas.
	 *
	 * @var bool
	 */
	private $time_24h;

	/**
	 * Constructor.
	 *
	 * @param string $frequency    Frecuencia (self::FREQUENCY_*).
	 * @param bool   $mail_enabled Si se envían informes por correo.
	 * @param string $recipient    Destinatario personalizado o ''.
	 * @param bool   $time_24h     Si las horas se muestran en formato de 24 horas.
	 *
	 * @throws \InvalidArgumentException Si la frecuencia o el destinatario no son válidos.
	 */
	public function __construct( string $frequency, bool $mail_enabled, string $recipient, bool $time_24h = false ) {
		if ( ! in_array( $frequency, self::FREQUENCIES, true ) ) {
			throw new \InvalidArgumentException( 'Frecuencia de escaneo desconocida: ' . esc_html( $frequency ) );
		}

		if ( '' !== $recipient && ! filter_var( $recipient, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException( 'Destinatario de correo no válido: ' . esc_html( $recipient ) );
		}

		$this->frequency    = $frequency;
		$this->mail_enabled = $mail_enabled;
		$this->recipient    = $recipient;
		$this->time_24h     = $time_24h;
	}

	/**
	 * Devuelve los ajustes por defecto.
	 *
	 * @return self
	 */
	public static function defaults(): self {
		return new self( self::DEFAULT_FREQUENCY, self::DEFAULT_MAIL_ENABLED, '', self::DEFAULT_TIME_24H );
	}

	/**
	 * Reconstruye los ajustes desde un array plano persistido.
	 *
	 * @param array $data Array generado por to_array().
	 *
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self(
			isset( $data['frequency'] ) ? (string) $data['frequency'] : self::DEFAULT_FREQUENCY,
			isset( $data['mail_enabled'] ) ? (bool) $data['mail_enabled'] : self::DEFAULT_MAIL_ENABLED,
			isset( $data['recipient'] ) ? (string) $data['recipient'] : '',
			isset( $data['time_24h'] ) ? (bool) $data['time_24h'] : self::DEFAULT_TIME_24H
		);
	}

	/**
	 * Construye los ajustes desde datos crudos de formulario.
	 *
	 * El destinatario se sanea primero; si no pasa la validación se descarta.
	 * La frecuencia fuera de la lista y los valores ausentes caen al defecto.
	 *
	 * @param array         $raw            Datos sin sanear.
	 * @param callable|null $sanitize_email Callable(string $value): string.
	 * @param callable|null $validate_email Callable(string $value): bool.
	 *
	 * @return self
	 */
	public static function from_unsafe( array $raw, ?callable $sanitize_email = null, ?callable $validate_email = null ): self {
		$sanitize = null !== $sanitize_email ? $sanitize_email : static function ( string $value ): string {
			return (string) \sanitize_email( $value );
		};

		$validate = null !== $validate_email ? $validate_email : static function ( string $value ): bool {
			return (bool) \is_email( $value );
		};

		$frequency = isset( $raw['frequency'] ) ? (string) $raw['frequency'] : '';
		$recipient = isset( $raw['recipient'] ) ? (string) $raw['recipient'] : '';
		$recipient = (string) call_user_func( $sanitize, $recipient );

		if ( '' !== $recipient && ! call_user_func( $validate, $recipient ) ) {
			$recipient = '';
		}

		return new self(
			in_array( $frequency, self::FREQUENCIES, true ) ? $frequency : self::DEFAULT_FREQUENCY,
			isset( $raw['mail_enabled'] ) && '1' === (string) $raw['mail_enabled'],
			$recipient,
			isset( $raw['time_24h'] ) && '1' === (string) $raw['time_24h']
		);
	}

	/**
	 * Devuelve la frecuencia del escaneo programado.
	 *
	 * @return string
	 */
	public function get_frequency(): string {
		return $this->frequency;
	}

	/**
	 * Indica si el escaneo programado está desactivado.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return self::FREQUENCY_DISABLED === $this->frequency;
	}

	/**
	 * Indica si se envían informes por correo.
	 *
	 * @return bool
	 */
	public function is_mail_enabled(): bool {
		return $this->mail_enabled;
	}

	/**
	 * Devuelve el destinatario personalizado o ''.
	 *
	 * @return string
	 */
	public function get_recipient(): string {
		return $this->recipient;
	}

	/**
	 * Indica si las horas se muestran en formato de 24 horas.
	 *
	 * @return bool
	 */
	public function is_time_24h(): bool {
		return $this->time_24h;
	}

	/**
	 * Devuelve el intervalo en segundos de la frecuencia seleccionada.
	 *
	 * @return int
	 */
	public function get_interval(): int {
		switch ( $this->frequency ) {
			case self::FREQUENCY_WEEKLY:
				return self::INTERVAL_WEEKLY;

			case self::FREQUENCY_MONTHLY:
				return self::INTERVAL_MONTHLY;

			case self::FREQUENCY_DAILY:
				return self::INTERVAL_DAILY;

			case self::FREQUENCY_DISABLED:
			default:
				return 0;
		}
	}

	/**
	 * Devuelve el nombre de recurrencia de WP-Cron asociado.
	 *
	 * @return string
	 */
	public function get_recurrence(): string {
		switch ( $this->frequency ) {
			case self::FREQUENCY_WEEKLY:
				return Cron::RECURRENCE_WEEKLY;

			case self::FREQUENCY_MONTHLY:
				return Cron::RECURRENCE_MONTHLY;

			case self::FREQUENCY_DAILY:
				return Cron::RECURRENCE_DAILY;

			case self::FREQUENCY_DISABLED:
			default:
				return '';
		}
	}

	/**
	 * Convierte los ajustes a un array plano persistible.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'frequency'    => $this->frequency,
			'mail_enabled' => $this->mail_enabled,
			'recipient'    => $this->recipient,
			'time_24h'     => $this->time_24h,
		);
	}
}
