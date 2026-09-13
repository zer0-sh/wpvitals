<?php
declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( '__' ) ) {
	/**
	 * Stub de i18n para tests standalone (sin WordPress cargado).
	 *
	 * @param string $text   Texto original.
	 * @param string $domain Text domain.
	 *
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Stub de escape para tests standalone (sin WordPress cargado).
	 *
	 * @param mixed $text Texto a escapar.
	 *
	 * @return string
	 */
	function esc_html( $text ) {
		return (string) $text;
	}
}