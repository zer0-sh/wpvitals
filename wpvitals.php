<?php
/**
 * Plugin Name: WPVitals
 * Plugin URI:  https://github.com/zer0-sh/wpvitals
 * Description: Ultra lightweight WordPress plugin to monitor your website's performance and uptime.
 * Version:     0.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author:      WPVitals
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpvitals
 * Domain Path: /languages
 *
 * @package WPVitals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpvitals_autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $wpvitals_autoload ) ) {
	require_once $wpvitals_autoload;
}

unset( $wpvitals_autoload );
