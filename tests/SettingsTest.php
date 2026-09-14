<?php
declare( strict_types=1 );

namespace WPVitals\Tests;

use PHPUnit\Framework\TestCase;
use WPVitals\Settings;

final class SettingsTest extends TestCase {

	public function test_defaults_usa_valores_por_defecto(): void {
		$settings = Settings::defaults();

		$this->assertSame( Settings::FREQUENCY_DAILY, $settings->get_frequency() );
		$this->assertTrue( $settings->is_mail_enabled() );
		$this->assertSame( '', $settings->get_recipient() );
		$this->assertFalse( $settings->is_time_24h() );
	}

	public function test_from_array_preserva_formato_24h(): void {
		$settings = Settings::from_array(
			array(
				'frequency'    => Settings::FREQUENCY_DAILY,
				'mail_enabled' => true,
				'recipient'    => '',
				'time_24h'     => true,
			)
		);

		$this->assertTrue( $settings->is_time_24h() );
	}

	public function test_from_array_con_datos_validos(): void {
		$settings = Settings::from_array(
			array(
				'frequency'    => Settings::FREQUENCY_WEEKLY,
				'mail_enabled' => false,
				'recipient'    => 'ops@example.com',
			)
		);

		$this->assertSame( Settings::FREQUENCY_WEEKLY, $settings->get_frequency() );
		$this->assertFalse( $settings->is_mail_enabled() );
		$this->assertSame( 'ops@example.com', $settings->get_recipient() );
	}

	public function test_from_array_completa_campos_ausentes(): void {
		$settings = Settings::from_array( array() );

		$this->assertSame( Settings::FREQUENCY_DAILY, $settings->get_frequency() );
		$this->assertTrue( $settings->is_mail_enabled() );
	}

	public function test_from_unsafe_sanea_y_valida_el_destinatario(): void {
		$raw = array(
			'frequency'    => Settings::FREQUENCY_MONTHLY,
			'mail_enabled' => '1',
			'recipient'    => '  ops@example.com  ',
		);

		$settings = Settings::from_unsafe(
			$raw,
			function ( string $value ): string {
				return trim( strtolower( $value ) );
			},
			function ( string $value ): bool {
				return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL );
			}
		);

		$this->assertSame( Settings::FREQUENCY_MONTHLY, $settings->get_frequency() );
		$this->assertTrue( $settings->is_mail_enabled() );
		$this->assertSame( 'ops@example.com', $settings->get_recipient() );
	}

	public function test_from_unsafe_descarta_destinatario_invalido(): void {
		$settings = Settings::from_unsafe(
			array( 'recipient' => 'no-es-un-correo' ),
			static function ( string $value ): string {
				return $value;
			},
			static function ( string $value ): bool {
				unset( $value );
				return false;
			}
		);

		$this->assertSame( '', $settings->get_recipient() );
	}

	public function test_from_unsafe_formato_24h_solo_con_checkbox_marcado(): void {
		$settings = Settings::from_unsafe(
			array( 'time_24h' => '1' ),
			static function ( string $value ): string {
				return $value;
			},
			static function ( string $value ): bool {
				unset( $value );
				return true;
			}
		);

		$this->assertTrue( $settings->is_time_24h() );

		$settings = Settings::from_unsafe(
			array( 'time_24h' => '' ),
			static function ( string $value ): string {
				return $value;
			},
			static function ( string $value ): bool {
				unset( $value );
				return true;
			}
		);

		$this->assertFalse( $settings->is_time_24h() );
	}

	public function test_from_unsafe_frecuencia_desconocida_cae_al_defecto(): void {
		$settings = Settings::from_unsafe(
			array( 'frequency' => 'hourly' ),
			static function ( string $value ): string {
				return $value;
			},
			static function ( string $value ): bool {
				unset( $value );
				return true;
			}
		);

		$this->assertSame( Settings::FREQUENCY_DAILY, $settings->get_frequency() );
		$this->assertFalse( $settings->is_mail_enabled() );
	}

	public function test_frecuencia_invalida_lanza_excepcion(): void {
		$this->expectException( \InvalidArgumentException::class );

		new Settings( 'hourly', true, '' );
	}

	public function test_destinatario_invalido_lanza_excepcion(): void {
		$this->expectException( \InvalidArgumentException::class );

		new Settings( Settings::FREQUENCY_DAILY, true, 'esto-no-es-un-correo' );
	}

	public function test_get_interval_segun_frecuencia(): void {
		$this->assertSame( 86400, ( new Settings( Settings::FREQUENCY_DAILY, true, '' ) )->get_interval() );
		$this->assertSame( 604800, ( new Settings( Settings::FREQUENCY_WEEKLY, true, '' ) )->get_interval() );
		$this->assertSame( 2592000, ( new Settings( Settings::FREQUENCY_MONTHLY, true, '' ) )->get_interval() );
		$this->assertSame( 0, ( new Settings( Settings::FREQUENCY_DISABLED, true, '' ) )->get_interval() );
	}

	public function test_get_recurrence_segun_frecuencia(): void {
		$this->assertSame( 'daily', ( new Settings( Settings::FREQUENCY_DAILY, true, '' ) )->get_recurrence() );
		$this->assertSame( 'wpvitals_weekly', ( new Settings( Settings::FREQUENCY_WEEKLY, true, '' ) )->get_recurrence() );
		$this->assertSame( 'wpvitals_monthly', ( new Settings( Settings::FREQUENCY_MONTHLY, true, '' ) )->get_recurrence() );
		$this->assertSame( '', ( new Settings( Settings::FREQUENCY_DISABLED, true, '' ) )->get_recurrence() );
	}

	public function test_to_array_roundtrip(): void {
		$settings = new Settings( Settings::FREQUENCY_WEEKLY, false, 'ops@example.com', true );

		$this->assertSame( $settings->to_array(), Settings::from_array( $settings->to_array() )->to_array() );
		$this->assertTrue( $settings->is_time_24h() );
	}

	public function test_is_disabled(): void {
		$this->assertTrue( ( new Settings( Settings::FREQUENCY_DISABLED, true, '' ) )->is_disabled() );
		$this->assertFalse( Settings::defaults()->is_disabled() );
	}
}