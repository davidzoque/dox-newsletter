<?php
/**
 * Plugin Name:       Dox Newsletter
 * Plugin URI:        https://doxstudio.com
 * Description:       Newsletters and email campaigns from your own WordPress: subscription forms with double opt-in, a block editor, sending in batches through the site's email, and opens, clicks and unsubscribes in every report.
 * Version:           0.1.0
 * Author:            Dox Studio
 * Author URI:        https://doxstudio.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dox-newsletter
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Update URI:        https://github.com/davidzoque/dox-newsletter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'DXN_VERSION', '0.1.0' );
define( 'DXN_FILE', __FILE__ );
define( 'DXN_PATH', plugin_dir_path( __FILE__ ) );
define( 'DXN_URL', plugin_dir_url( __FILE__ ) );

/** Versión de un archivo de assets/ para la URL: cambia en cuanto cambia el archivo. */
function dxn_asset_ver( $file ) {
	$path = DXN_PATH . 'assets/' . $file;
	return DXN_VERSION . ( file_exists( $path ) ? '.' . filemtime( $path ) : '' );
}

// ─── Traducciones ─────────────────────────────────────────────────────────────
// El plugin está escrito en inglés y trae el español (es_ES). WordPress no pasa de
// es_CO, es_MX o es_AR a es_ES por su cuenta: sin esto, una web en español de
// Colombia lo vería en inglés. Una traducción propia (Loco Translate) sigue mandando.
add_action( 'init', function () {
	load_plugin_textdomain( 'dox-newsletter', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}, 1 );

add_filter( 'load_textdomain_mofile', function ( $mofile, $domain ) {
	if ( 'dox-newsletter' !== $domain ) return $mofile;
	if ( ! preg_match( '/dox-newsletter-es(_[A-Za-z]+)?\.mo$/', (string) $mofile ) ) return $mofile;
	if ( is_readable( $mofile ) || is_readable( substr( $mofile, 0, -3 ) . '.l10n.php' ) ) return $mofile;

	$es = DXN_PATH . 'languages/dox-newsletter-es_ES.mo';
	return is_readable( $es ) ? $es : $mofile;
}, 10, 2 );

// ─── Menú común de los plugins de Dox Studio ──────────────────────────────────
// Con file_exists: si la carpeta llegara a medias (una subida cortada), el plugin
// sigue en pie y usa su propio menú (ver DXN_Admin::fallback_menu).
if ( file_exists( DXN_PATH . 'dox-core/loader.php' ) && file_exists( DXN_PATH . 'dox-core/version.php' ) ) {
	require_once DXN_PATH . 'dox-core/loader.php';
	if ( class_exists( 'Dox_Core_Loader' ) ) {
		Dox_Core_Loader::register( require DXN_PATH . 'dox-core/version.php', DXN_PATH . 'dox-core/dox-core.php' );
	}
}

// ─── Cargar archivos ───────────────────────────────────────────────────────────
require_once DXN_PATH . 'includes/class-dxn-install.php';
require_once DXN_PATH . 'includes/class-dxn-settings.php';
require_once DXN_PATH . 'includes/class-dxn-renderer.php';
require_once DXN_PATH . 'includes/class-dxn-subscribers.php';
require_once DXN_PATH . 'includes/class-dxn-campaigns.php';
require_once DXN_PATH . 'includes/class-dxn-mailer.php';
require_once DXN_PATH . 'includes/class-dxn-sender.php';
require_once DXN_PATH . 'includes/class-dxn-forms.php';
require_once DXN_PATH . 'includes/class-dxn-public.php';
require_once DXN_PATH . 'includes/class-dxn-stats.php';
if ( is_admin() ) {
	require_once DXN_PATH . 'includes/class-dxn-admin.php';
}

register_activation_hook( __FILE__, [ 'DXN_Install', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'DXN_Install', 'deactivate' ] );

// Las tablas se ponen al día solas cuando cambia la versión: una actualización
// desde GitHub no pasa por la activación.
add_action( 'plugins_loaded', [ 'DXN_Install', 'maybe_upgrade' ] );

DXN_Sender::init();
DXN_Public::init();
DXN_Forms::init();
if ( is_admin() ) {
	DXN_Admin::init();
}

// ─── Widget de Elementor ───────────────────────────────────────────────────────
add_action( 'elementor/widgets/register', function ( $manager ) {
	require_once DXN_PATH . 'includes/class-dxn-elementor.php';
	$manager->register( new DXN_Elementor_Widget() );
} );

// ─── Auto-actualizaciones desde GitHub (Plugin Update Checker) ────────────────
// El plugin se actualiza desde las releases del repo, no desde WordPress.org.
$dxn_puc = DXN_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $dxn_puc ) ) {
	require_once $dxn_puc;
	$dxn_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/davidzoque/dox-newsletter/',
		__FILE__,
		'dox-newsletter'
	);
	$dxn_update_checker->setBranch( 'main' );
	// Usa el ZIP limpio que el workflow de GitHub Actions adjunta a cada release.
	$dxn_update_checker->getVcsApi()->enableReleaseAssets();
	if ( defined( 'DXN_GITHUB_TOKEN' ) && DXN_GITHUB_TOKEN ) {
		$dxn_update_checker->setAuthentication( DXN_GITHUB_TOKEN );
	}
}
