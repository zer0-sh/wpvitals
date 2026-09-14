<?php
/**
 * Orquestador del flujo completo de un escaneo.
 *
 * @package WPVitals
 */

declare( strict_types=1 );

namespace WPVitals;

/**
 * Coordina escaneo, persistencia y notificación por correo.
 *
 * Lee el resultado previo antes de escanear para que el envío programado
 * pueda comparar la firma del diagnóstico, guarda el nuevo resultado y
 * decide si corresponde notificar.
 */
final class ScanRunner {

	/**
	 * Ejecutor del escaneo.
	 *
	 * @var Scanner
	 */
	private $scanner;

	/**
	 * Almacén del último resultado.
	 *
	 * @var ScanStore
	 */
	private $store;

	/**
	 * Enviador del informe por correo.
	 *
	 * @var MailReport
	 */
	private $mail;

	/**
	 * Constructor.
	 *
	 * @param Scanner    $scanner Ejecutor del escaneo.
	 * @param ScanStore  $store   Almacén del último resultado.
	 * @param MailReport $mail    Enviador del informe por correo.
	 */
	public function __construct( Scanner $scanner, ScanStore $store, MailReport $mail ) {
		$this->scanner = $scanner;
		$this->store   = $store;
		$this->mail    = $mail;
	}

	/**
	 * Ejecuta un escaneo y lo persiste notificando si corresponde.
	 *
	 * @param string   $origin   Origen del escaneo (ScanOutcome::ORIGIN_*).
	 * @param Settings $settings Ajustes del plugin.
	 * @param bool     $refresh  Si true, ignora la caché de vulnerabilidades.
	 *
	 * @return ScanOutcome
	 */
	public function run( string $origin, Settings $settings, bool $refresh = false ): ScanOutcome {
		$previous = $this->store->get();
		$outcome  = $this->scanner->scan( $origin, $refresh );

		$this->store->save( $outcome );
		$this->mail->send_if_due( $outcome, $settings, $previous );

		return $outcome;
	}
}
