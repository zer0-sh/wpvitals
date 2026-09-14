<?php
/**
 * Informe por correo del resultado de un escaneo.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Compone y envía el resumen por correo tras un escaneo.
 *
 * Las funciones de WordPress (wp_mail y correo del administrador) se inyectan
 * para poder testearlo standalone. Los fallos de wp_mail no se propagan: el
 * escaneo nunca debe romperse por un envío fallido.
 */
final class MailReport {

	/**
	 * Número máximo de hallazgos detallados en el cuerpo del correo.
	 *
	 * @var int
	 */
	const MAX_FINDINGS = 15;

	/**
	 * Enviador de correo inyectable.
	 *
	 * @var callable
	 */
	private $wp_mail;

	/**
	 * Fuente del correo del administrador inyectable.
	 *
	 * @var callable
	 */
	private $admin_email;

	/**
	 * Constructor.
	 *
	 * @param callable|null $wp_mail     Callable(string $to, string $subject, string $body): bool.
	 * @param callable|null $admin_email Callable(): string.
	 */
	public function __construct( ?callable $wp_mail = null, ?callable $admin_email = null ) {
		$this->wp_mail     = null !== $wp_mail ? $wp_mail : static function ( string $to, string $subject, string $body ): bool {
			return (bool) \wp_mail( $to, $subject, $body );
		};
		$this->admin_email = null !== $admin_email ? $admin_email : static function (): string {
			return (string) \get_option( 'admin_email', '' );
		};
	}

	/**
	 * Decide si debe enviarse el informe de un escaneo.
	 *
	 * El envío se omite si los correos están desactivados o el escaneo no
	 * está habilitado. Los escaneos manuales siempre informan; los
	 * programados solo cuando el diagnóstico cambia o es el primero.
	 *
	 * @param ScanOutcome      $outcome  Resultado del escaneo.
	 * @param Settings         $settings Ajustes del plugin.
	 * @param ScanOutcome|null $previous Resultado previo, o null si no existe.
	 *
	 * @return bool
	 */
	public function should_notify( ScanOutcome $outcome, Settings $settings, ?ScanOutcome $previous ): bool {
		if ( $settings->is_disabled() || ! $settings->is_mail_enabled() ) {
			return false;
		}

		if ( ScanOutcome::ORIGIN_MANUAL === $outcome->get_origin() ) {
			return true;
		}

		return null === $previous || $previous->signature() !== $outcome->signature();
	}

	/**
	 * Envía el informe si corresponde según las condiciones de notificación.
	 *
	 * @param ScanOutcome      $outcome  Resultado del escaneo.
	 * @param Settings         $settings Ajustes del plugin.
	 * @param ScanOutcome|null $previous Resultado previo, o null si no existe.
	 *
	 * @return bool
	 */
	public function send_if_due( ScanOutcome $outcome, Settings $settings, ?ScanOutcome $previous ): bool {
		if ( ! $this->should_notify( $outcome, $settings, $previous ) ) {
			return false;
		}

		return $this->send( $outcome, $settings );
	}

	/**
	 * Envía el informe por correo.
	 *
	 * @param ScanOutcome $outcome  Resultado del escaneo.
	 * @param Settings    $settings Ajustes del plugin.
	 *
	 * @return bool
	 */
	public function send( ScanOutcome $outcome, Settings $settings ): bool {
		$to = $settings->get_recipient();

		if ( '' === $to ) {
			$to = (string) call_user_func( $this->admin_email );
		}

		if ( '' === $to ) {
			return false;
		}

		try {
			return (bool) call_user_func(
				$this->wp_mail,
				$to,
				$this->subject( $outcome ),
				$this->body( $outcome )
			);
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Asunto identificable del informe.
	 *
	 * @param ScanOutcome $outcome Resultado del escaneo.
	 *
	 * @return string
	 */
	public function subject( ScanOutcome $outcome ): string {
		return sprintf(
			'[WPVitals] %s %d/100 — %s',
			__( 'Health Score', 'wpvitals' ),
			$outcome->get_total(),
			$this->state_label( $outcome->get_state() )
		);
	}

	/**
	 * Cuerpo en texto plano con el score y un resumen del diagnóstico.
	 *
	 * @param ScanOutcome $outcome Resultado del escaneo.
	 *
	 * @return string
	 */
	public function body( ScanOutcome $outcome ): string {
		$lines = array(
			sprintf(
				'%s: %d/100 (%s)',
				__( 'Health Score', 'wpvitals' ),
				$outcome->get_total(),
				$this->state_label( $outcome->get_state() )
			),
			'',
			sprintf( '%s: %d', __( 'Vulnerabilidades activas', 'wpvitals' ), $outcome->count_vulnerabilities() ),
			sprintf( '%s: %d', __( 'Actualizaciones pendientes', 'wpvitals' ), $outcome->count_pending_updates() ),
			'',
			__( 'Hallazgos:', 'wpvitals' ),
		);

		$added = 0;
		$total = 0;

		foreach ( $outcome->get_results() as $result ) {
			if ( ! $result->is_ok() ) {
				++$total;
			}
		}

		foreach ( $outcome->get_results() as $result ) {
			if ( $result->is_ok() ) {
				continue;
			}

			if ( self::MAX_FINDINGS === $added ) {
				$lines[] = sprintf(
					/* translators: %d: número de hallazgos adicionales. */
					__( '…y %d hallazgos más.', 'wpvitals' ),
					$total - $added
				);
				break;
			}

			$lines[] = sprintf(
				'- [%s] %s — %s',
				$this->severity_label( $result->get_severity() ),
				$result->get_title(),
				$result->get_recommendation()
			);

			++$added;
		}

		if ( 0 === $added ) {
			$lines[] = __( 'No se han detectado hallazgos relevantes.', 'wpvitals' );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Etiqueta textual del estado del score.
	 *
	 * @param string $state Estado (Score::STATE_*).
	 *
	 * @return string
	 */
	private function state_label( string $state ): string {
		switch ( $state ) {
			case Score::STATE_HEALTHY:
				return __( 'Saludable', 'wpvitals' );

			case Score::STATE_CRITICAL:
				return __( 'Crítico', 'wpvitals' );

			case Score::STATE_ATTENTION:
			default:
				return __( 'Requiere atención', 'wpvitals' );
		}
	}

	/**
	 * Etiqueta textual de una severidad.
	 *
	 * @param string $severity Severidad (Result::SEVERITY_*).
	 *
	 * @return string
	 */
	private function severity_label( string $severity ): string {
		switch ( $severity ) {
			case Result::SEVERITY_INFO:
				return __( 'Información', 'wpvitals' );

			case Result::SEVERITY_WARNING:
				return __( 'Aviso', 'wpvitals' );

			case Result::SEVERITY_CRITICAL:
				return __( 'Crítico', 'wpvitals' );

			case Result::SEVERITY_ERROR:
				return __( 'Error', 'wpvitals' );

			case Result::SEVERITY_OK:
			default:
				return __( 'Bien', 'wpvitals' );
		}
	}
}
