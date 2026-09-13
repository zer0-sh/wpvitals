<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Checks\HttpsCheck;
use WPVitals\Checks\DebugCheck;
use WPVitals\Checks\DebugLogCheck;
use WPVitals\Checks\XmlRpcCheck;
use WPVitals\Checks\FileEditCheck;
use WPVitals\Result;

final class SecurityChecksTest extends TestCase {

	public function test_https_activo(): void {
		$result = $this->https_check( true, array( 'force_ssl' => false, 'force_ssl_admin' => false ) )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'security/https', $result->get_id() );
	}

	public function test_https_configurado_pero_peticion_no_segura_info(): void {
		$result = $this->https_check( false, array( 'force_ssl' => false, 'force_ssl_admin' => true ) )->run();

		$this->assertSame( Result::SEVERITY_INFO, $result->get_severity() );
	}

	public function test_https_configurado_por_siteurl_info(): void {
		$result = $this->https_check(
			false,
			array(
				'force_ssl'       => false,
				'force_ssl_admin' => false,
				'siteurl_https'   => true,
			)
		)->run();

		$this->assertSame( Result::SEVERITY_INFO, $result->get_severity() );
	}

	public function test_https_inactivo_avisa(): void {
		$result = $this->https_check( false, array( 'force_ssl' => false, 'force_ssl_admin' => false ) )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	public function test_debug_desactivado_ok(): void {
		$result = $this->debug_check( false, false )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'security/debug', $result->get_id() );
	}

	public function test_debug_activado_avisa(): void {
		$result = $this->debug_check( true, true )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	public function test_debug_log_desactivado_ok(): void {
		$result = $this->debug_log_check( false )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'security/debug_log', $result->get_id() );
	}

	public function test_debug_log_activado_avisa(): void {
		$result = $this->debug_log_check( true )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
	}

	public function test_xmlrpc_desactivado_ok(): void {
		$result = $this->xmlrpc_check( false )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'security/xmlrpc', $result->get_id() );
	}

	public function test_xmlrpc_activado_avisa(): void {
		$result = $this->xmlrpc_check( true )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	public function test_edicion_archivos_bloqueada_ok(): void {
		$result = $this->file_edit_check( true )->run();

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 'security/file_edit', $result->get_id() );
	}

	public function test_edicion_archivos_permitida_avisa(): void {
		$result = $this->file_edit_check( false )->run();

		$this->assertSame( Result::SEVERITY_WARNING, $result->get_severity() );
		$this->assertSame( 5, $result->get_points_deducted() );
	}

	private function https_check( bool $ssl, array $config ): HttpsCheck {
		return new HttpsCheck(
			static function () use ( $ssl ): bool {
				return $ssl;
			},
			static function () use ( $config ): array {
				return $config;
			}
		);
	}

	private function debug_check( bool $debug, bool $display ): DebugCheck {
		return new DebugCheck(
			static function () use ( $debug, $display ): array {
				return array(
					'wp_debug'         => $debug,
					'wp_debug_display' => $display,
				);
			}
		);
	}

	private function debug_log_check( bool $logging ): DebugLogCheck {
		return new DebugLogCheck(
			static function () use ( $logging ): bool {
				return $logging;
			}
		);
	}

	private function xmlrpc_check( bool $enabled ): XmlRpcCheck {
		return new XmlRpcCheck(
			static function () use ( $enabled ): bool {
				return $enabled;
			}
		);
	}

	private function file_edit_check( bool $disabled ): FileEditCheck {
		return new FileEditCheck(
			static function () use ( $disabled ): bool {
				return $disabled;
			}
		);
	}
}