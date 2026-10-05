<?php
/**
 * Informe de una campaña. Sin id: la lista de campañas enviadas para elegir.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
$c  = $id ? DXN_Campaigns::get( $id ) : null;

if ( ! $c || in_array( $c['status'], [ 'draft', 'scheduled' ], true ) ) :
	$sent = DXN_Campaigns::query( 'sent' );
	$live = DXN_Campaigns::query( 'sending' );
	$all  = array_merge( $live, $sent );
	?>
	<div class="dxn-top"><div><div class="crumb">Newsletter</div><h1><?php esc_html_e( 'Reports', 'dox-newsletter' ); ?></h1></div></div>
	<div class="dxn-page">
		<div class="dxn-card" data-anim>
			<?php if ( $all ) : ?>
				<div class="dxn-table-wrap"><table class="dxn-table">
					<thead><tr><th><?php esc_html_e( 'Campaign', 'dox-newsletter' ); ?></th><th class="dxn-hide-sm"><?php esc_html_e( 'Sent', 'dox-newsletter' ); ?></th><th><?php esc_html_e( 'Opens', 'dox-newsletter' ); ?></th><th class="dxn-hide-sm"><?php esc_html_e( 'Clicks', 'dox-newsletter' ); ?></th><th class="dxn-hide-sm"><?php esc_html_e( 'Unsubscribes', 'dox-newsletter' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $all as $x ) : $st = DXN_Campaigns::stats( $x['id'] ); ?>
						<tr data-href="<?php echo esc_url( DXN_Admin::url( 'report', [ 'id' => $x['id'] ] ) ); ?>">
							<td><div class="t"><?php echo esc_html( $x['subject'] ); ?></div><div class="s"><?php echo esc_html( dxn_date( $x['started_at'], 'j M Y' ) . ' · ' . DXN_Campaigns::audience_label( $x ) ); ?></div></td>
							<td class="dxn-hide-sm num"><?php echo esc_html( dxn_num( $st['sent'] ) ); ?></td>
							<td><div class="dxn-rate"><div class="dxn-meter"><i class="acc" style="width:<?php echo $st['sent'] ? round( $st['opened'] * 100 / $st['sent'] ) : 0; ?>%"></i></div><b><?php echo esc_html( dxn_pct( $st['opened'], $st['sent'] ) ); ?></b></div></td>
							<td class="dxn-hide-sm num"><?php echo esc_html( dxn_pct( $st['clicked'], $st['sent'] ) ); ?></td>
							<td class="dxn-hide-sm num"><?php echo esc_html( dxn_num( $st['unsubscribed'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
			<?php else : ?>
				<div class="dxn-empty"><div class="ic"><?php echo DXN_Admin::icon( 'chart' ); // phpcs:ignore ?></div><h3><?php esc_html_e( 'No reports yet', 'dox-newsletter' ); ?></h3><p><?php esc_html_e( 'When a campaign goes out, its opens, clicks and unsubscribes appear here.', 'dox-newsletter' ); ?></p></div>
			<?php endif; ?>
		</div>
	</div>
	<?php return;
endif;

$st     = DXN_Campaigns::stats( $c['id'] );
$hours  = DXN_Campaigns::hourly_opens( $c );
$links  = DXN_Campaigns::link_clicks( $c['id'] );
$provs  = DXN_Campaigns::providers( $c['id'] );
$avg    = DXN_Stats::averages( 6 );
$welcome = $c['type'] === 'welcome';
$first3 = array_sum( array_slice( $hours, 0, 3 ) );
$opened24 = array_sum( $hours );

// Hora del día en que más se abrió (en la zona del sitio).
$peak = null;
if ( $opened24 ) {
	global $wpdb;
	$byhour = array_fill( 0, 24, 0 );
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT opened_at FROM ' . DXN_Install::table( 'recipients' ) . ' WHERE campaign_id = %d AND opened_at IS NOT NULL', $c['id'] ) ) as $o ) {
		$byhour[ (int) wp_date( 'G', strtotime( $o . ' UTC' ) ) ]++;
	}
	$peak = array_search( max( $byhour ), $byhour, true );
}

$labels = [];
if ( $c['started_at'] ) {
	$start = strtotime( $c['started_at'] . ' UTC' );
	foreach ( [ 0, 6, 12, 18, 23 ] as $h ) $labels[ $h ] = wp_date( get_option( 'time_format' ), $start + $h * HOUR_IN_SECONDS );
}
$top_links = $links ? max( 1, max( array_map( 'intval', array_column( $links, 'clicks' ) ) ) ) : 1;
$not_open  = max( 0, $st['sent'] - $st['opened'] );
$not_click = max( 0, $st['sent'] - $st['clicked'] );

// Diferencia con la media de las otras campañas.
$vs = '';
if ( ! $welcome && $avg && $avg['campaigns'] > 1 && $st['sent'] && $avg['sent'] > $st['sent'] ) {
	$others_rate = ( $avg['opened'] - $st['opened'] ) / max( 1, $avg['sent'] - $st['sent'] ) * 100;
	$diff        = $st['opened'] * 100 / $st['sent'] - $others_rate;
	if ( abs( $diff ) >= 1 ) {
		$vs = sprintf( $diff > 0 ? __( '%s points above your average', 'dox-newsletter' ) : __( '%s points below your average', 'dox-newsletter' ), number_format_i18n( abs( $diff ), 0 ) );
	}
}
?>
<div class="dxn-top">
	<div style="min-width:0"><div class="crumb"><a href="<?php echo esc_url( DXN_Admin::url( 'report' ) ); ?>"><?php esc_html_e( 'Reports', 'dox-newsletter' ); ?></a></div><h1><?php echo esc_html( $c['subject'] ); ?></h1></div>
	<?php echo DXN_Admin::status_pill( $c, $st ); // phpcs:ignore ?>
	<div class="sp"></div>
	<?php if ( ! $welcome && in_array( $c['status'], [ 'sent', 'cancelled' ], true ) && $st['sent'] ) : ?>
		<div class="dxn-menu">
			<button type="button" class="dxn-btn dxn-btn-dark" data-menu><?php echo DXN_Admin::icon( 'refresh' ); // phpcs:ignore ?><?php esc_html_e( 'Resend', 'dox-newsletter' ); ?></button>
			<div class="dxn-menu-list">
				<button type="button" data-act="resend" data-who="not_opened" data-id="<?php echo (int) $c['id']; ?>"><?php echo esc_html( sprintf( __( 'To who did not open it (%s)', 'dox-newsletter' ), dxn_num( $not_open ) ) ); ?></button>
				<button type="button" data-act="resend" data-who="not_clicked" data-id="<?php echo (int) $c['id']; ?>"><?php echo esc_html( sprintf( __( 'To who did not click (%s)', 'dox-newsletter' ), dxn_num( $not_click ) ) ); ?></button>
			</div>
		</div>
	<?php endif; ?>
	<button type="button" class="dxn-btn dxn-btn-gray" data-act="duplicate" data-id="<?php echo (int) $c['id']; ?>"><?php echo DXN_Admin::icon( 'copy' ); // phpcs:ignore ?><span class="dxn-hide-sm"><?php esc_html_e( 'Duplicate', 'dox-newsletter' ); ?></span></button>
</div>
<div class="dxn-page">
	<?php if ( in_array( $c['status'], [ 'sending', 'paused' ], true ) ) include DXN_PATH . 'admin/views/_status.php'; ?>

	<div class="dxn-stats" data-anim>
		<div class="lead">
			<div class="n"><?php echo esc_html( dxn_pct( $st['opened'], $st['sent'] ) ); ?></div>
			<div class="l"><?php esc_html_e( 'opened it', 'dox-newsletter' ); ?></div>
			<div class="d"><?php echo esc_html( sprintf( __( '%1$s of %2$s', 'dox-newsletter' ), dxn_num( $st['opened'] ), dxn_num( $st['sent'] ) ) . ( $vs ? ' · ' . $vs : '' ) ); ?></div>
		</div>
		<div><div class="n"><?php echo esc_html( dxn_pct( $st['clicked'], $st['sent'] ) ); ?></div><div class="l"><?php esc_html_e( 'clicked', 'dox-newsletter' ); ?></div><div class="d"><?php echo esc_html( sprintf( _n( '%s person', '%s people', $st['clicked'], 'dox-newsletter' ), dxn_num( $st['clicked'] ) ) ); ?></div></div>
		<div><div class="n"><?php echo esc_html( dxn_num( $st['sent'] ) ); ?></div><div class="l"><?php esc_html_e( 'delivered', 'dox-newsletter' ); ?></div><div class="d"><?php echo esc_html( $st['failed'] ? sprintf( _n( '%s failed', '%s failed', $st['failed'], 'dox-newsletter' ), dxn_num( $st['failed'] ) ) : __( 'No failures', 'dox-newsletter' ) ); ?></div></div>
		<div><div class="n"><?php echo esc_html( dxn_num( $st['unsubscribed'] ) ); ?></div><div class="l"><?php esc_html_e( 'unsubscribed', 'dox-newsletter' ); ?></div><div class="d"><?php echo esc_html( dxn_pct( $st['unsubscribed'], $st['sent'], 2 ) ); ?></div></div>
	</div>

	<div class="dxn-row" style="margin-top:20px">
		<div class="dxn-col" style="width:62%">
			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'First 24 hours', 'dox-newsletter' ); ?></h3><div class="sp"></div><span class="small muted"><?php esc_html_e( 'Opens per hour', 'dox-newsletter' ); ?></span></div>
				<div class="dxn-card-b">
					<?php if ( $opened24 ) : ?>
						<?php echo DXN_Admin::area_chart( $hours, $labels, '#141313', true ); // phpcs:ignore ?>
						<div class="dxn-note"><?php echo DXN_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php
							echo esc_html( sprintf( __( '%s of the opens came in the first 3 hours.', 'dox-newsletter' ), dxn_pct( $first3, $opened24, 0 ) ) );
							if ( $peak !== null ) echo ' ' . esc_html( sprintf( __( 'Your readers open most around %s.', 'dox-newsletter' ), wp_date( get_option( 'time_format' ), gmmktime( $peak, 0, 0 ), new DateTimeZone( 'UTC' ) ) ) );
						?></span></div>
					<?php else : ?>
						<p class="muted" style="margin:0"><?php esc_html_e( 'No opens yet. They usually start arriving a few minutes after sending.', 'dox-newsletter' ); ?></p>
					<?php endif; ?>
					<div class="dxn-note"><?php echo DXN_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'Gmail and the iPhone Mail app load the images by themselves when the email arrives, so some opens are not a person reading. Clicks are the reliable figure.', 'dox-newsletter' ); ?></span></div>
				</div>
			</div>
		</div>
		<div class="dxn-col" style="width:38%">
			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Where they clicked', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b dxn-links">
					<?php if ( $links && array_sum( array_map( 'intval', array_column( $links, 'clicks' ) ) ) ) : ?>
						<?php foreach ( array_slice( $links, 0, 8 ) as $l ) :
							$u     = wp_parse_url( $l['url'] );
							$label = ( $u['host'] ?? '' ) . ( isset( $u['path'] ) && $u['path'] !== '/' ? untrailingslashit( $u['path'] ) : '' );
							?>
							<div class="lk"><b title="<?php echo esc_attr( $l['url'] ); ?>"><?php echo esc_html( preg_replace( '/^www\./', '', $label ) ); ?></b><span class="small muted"><?php echo esc_html( dxn_num( $l['clicks'] ) ); ?></span><div class="dxn-meter"><i class="ink" style="width:<?php echo round( (int) $l['clicks'] * 100 / $top_links ); ?>%"></i></div></div>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="muted" style="margin:0"><?php esc_html_e( 'No clicks yet.', 'dox-newsletter' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<?php if ( $provs ) : ?>
		<div class="dxn-stats" data-anim style="margin-top:20px">
			<?php foreach ( $provs as $p ) : ?>
				<div><div class="n"><?php echo esc_html( dxn_pct( $p['opened'], $p['sent'], 0 ) ); ?></div><div class="l"><?php echo esc_html( $p['name'] ); ?></div><div class="d"><?php echo esc_html( sprintf( _n( '%s person', '%s people', $p['sent'], 'dox-newsletter' ), dxn_num( $p['sent'] ) ) ); ?></div></div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $st['failed'] ) :
		global $wpdb;
		$errors = $wpdb->get_results( $wpdb->prepare( 'SELECT error, COUNT(*) n FROM ' . DXN_Install::table( 'recipients' ) . " WHERE campaign_id = %d AND status = 'failed' GROUP BY error ORDER BY n DESC LIMIT 5", $c['id'] ), ARRAY_A );
		?>
		<div class="dxn-card" data-anim style="margin-top:20px">
			<div class="dxn-card-h"><h3><?php esc_html_e( 'Why some failed', 'dox-newsletter' ); ?></h3></div>
			<div class="dxn-card-b dxn-src">
				<?php foreach ( $errors as $e ) : ?>
					<div class="s"><b title="<?php echo esc_attr( $e['error'] ); ?>"><?php echo esc_html( $e['error'] ?: __( 'No reason given', 'dox-newsletter' ) ); ?></b><span class="small muted"><?php echo esc_html( dxn_num( $e['n'] ) ); ?></span></div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
