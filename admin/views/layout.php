<?php
/**
 * El marco del panel: navegación a la izquierda y la vista a la derecha.
 *
 * @var string $view
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$counts_c = DXN_Campaigns::counts();
$active   = DXN_Stats::active_count();
$settings = DXN_Settings::all();

$nav = [
	'dashboard'   => [ __( 'Overview', 'dox-newsletter' ), 'pulse', '' ],
	'campaigns'   => [ __( 'Campaigns', 'dox-newsletter' ), 'send', $counts_c['all'] ? dxn_num( $counts_c['all'] ) : '' ],
	'subscribers' => [ __( 'Subscribers', 'dox-newsletter' ), 'users', $active ? dxn_num( $active ) : '' ],
	'forms'       => [ __( 'Forms', 'dox-newsletter' ), 'form', '' ],
	'report'      => [ __( 'Reports', 'dox-newsletter' ), 'chart', '' ],
];
$current = $view === 'edit' ? 'campaigns' : $view;

// Primeros pasos: se tachan solos y la caja desaparece cuando están todos.
$steps = [
	[ __( 'Sender and address', 'dox-newsletter' ), $settings['address'] !== '', DXN_Admin::url( 'settings' ) ],
	[ __( 'A form on the website', 'dox-newsletter' ), (bool) array_diff_key( DXN_Forms::signups( 3650 ), [ 'import' => 1, 'manual' => 1 ] ) || (bool) array_filter( DXN_Forms::all(), function ( $f ) { return $f['placement'] !== 'inline' && $f['enabled']; } ), DXN_Admin::url( 'forms' ) ],
	[ __( 'First subscribers', 'dox-newsletter' ), $active > 0, DXN_Admin::url( 'subscribers' ) ],
	[ __( 'First campaign', 'dox-newsletter' ), (bool) DXN_Stats::recent( 1 ), DXN_Admin::url( 'campaigns' ) ],
];
$steps_left = count( array_filter( $steps, function ( $s ) { return ! $s[1]; } ) );
?>
<div class="dxn-app" id="dxn-app" data-view="<?php echo esc_attr( $view ); ?>">
	<script>document.getElementById('dxn-app').classList.add('dxn-js');</script>
	<nav class="dxn-nav" aria-label="Dox Newsletter">
		<div class="dxn-brand">
			<div class="mark"><?php echo DXN_Admin::icon( 'mail' ); // phpcs:ignore ?></div>
			<div><b>Newsletter</b><small>Dox Plugins · <?php echo esc_html( DXN_VERSION ); ?></small></div>
		</div>
		<?php foreach ( $nav as $key => $n ) : ?>
			<a href="<?php echo esc_url( DXN_Admin::url( $key ) ); ?>" class="<?php echo $current === $key ? 'on' : ''; ?>">
				<?php echo DXN_Admin::icon( $n[1] ); // phpcs:ignore ?><?php echo esc_html( $n[0] ); ?>
				<?php if ( $n[2] !== '' ) : ?><span class="count"><?php echo esc_html( $n[2] ); ?></span><?php endif; ?>
			</a>
			<?php if ( $key === 'campaigns' ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dxn_new_campaign' ), 'dxn_new' ) ); ?>" class="<?php echo $view === 'edit' && empty( $_GET['id'] ) ? 'on' : ''; // phpcs:ignore ?>"><?php echo DXN_Admin::icon( 'pen' ); // phpcs:ignore ?><?php esc_html_e( 'New campaign', 'dox-newsletter' ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
		<div class="sep"></div>
		<a href="<?php echo esc_url( DXN_Admin::url( 'settings' ) ); ?>" class="<?php echo $current === 'settings' ? 'on' : ''; ?>"><?php echo DXN_Admin::icon( 'gear' ); // phpcs:ignore ?><?php esc_html_e( 'Settings', 'dox-newsletter' ); ?></a>

		<?php if ( $steps_left ) : ?>
			<div class="help">
				<b><?php esc_html_e( 'First steps', 'dox-newsletter' ); ?></b>
				<ol>
					<?php foreach ( $steps as $s ) : ?>
						<li class="<?php echo $s[1] ? 'done' : ''; ?>"><?php if ( ! $s[1] ) : ?><a href="<?php echo esc_url( $s[2] ); ?>"><?php endif; ?><?php echo esc_html( $s[0] ); ?><?php if ( ! $s[1] ) : ?></a><?php endif; ?></li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php else : ?>
			<div class="help">
				<b><?php echo $settings['transport'] === 'ses' ? 'Amazon SES' : esc_html__( 'WordPress email', 'dox-newsletter' ); ?></b>
				<p><?php echo esc_html( sprintf( __( 'Sending at up to %s per hour.', 'dox-newsletter' ), dxn_num( $settings['rate_per_hour'] ) ) ); ?></p>
				<a class="dxn-btn dxn-btn-link small" href="<?php echo esc_url( DXN_Admin::url( 'settings' ) ); ?>"><?php esc_html_e( 'Change', 'dox-newsletter' ); ?> <?php echo DXN_Admin::icon( 'arrow' ); // phpcs:ignore ?></a>
			</div>
		<?php endif; ?>
	</nav>

	<main class="dxn-main">
		<div class="dxn-mnav">
			<?php foreach ( $nav + [ 'settings' => [ __( 'Settings', 'dox-newsletter' ) ] ] as $key => $n ) : ?>
				<a href="<?php echo esc_url( DXN_Admin::url( $key ) ); ?>" class="<?php echo $current === $key ? 'on' : ''; ?>"><?php echo esc_html( $n[0] ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php include DXN_PATH . 'admin/views/' . $view . '.php'; ?>
	</main>
</div>

<dialog class="dxn-modal" id="dxn-modal"><form method="dialog"><div class="hd"><h3></h3></div><div class="bd"></div><div class="ft"></div></form></dialog>
<div class="dxn-toast" id="dxn-toast" role="status" aria-live="polite"></div>
