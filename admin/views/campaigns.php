<?php
/**
 * Campañas: filtros con contador, buscador y la de bienvenida aparte.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$filter  = isset( $_GET['f'] ) ? sanitize_key( $_GET['f'] ) : 'all'; // phpcs:ignore
$search  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore
$counts  = DXN_Campaigns::counts();
$rows    = DXN_Campaigns::query( $filter, $search );
$welcome = DXN_Campaigns::welcome();
$wst     = DXN_Campaigns::stats( $welcome['id'] );
$new_url = wp_nonce_url( admin_url( 'admin-post.php?action=dxn_new_campaign' ), 'dxn_new' );

$filters = [
	'all'       => [ __( 'All', 'dox-newsletter' ), '' ],
	'sending'   => [ __( 'Sending', 'dox-newsletter' ), 'var(--accent)' ],
	'scheduled' => [ __( 'Scheduled', 'dox-newsletter' ), '#2563EB' ],
	'draft'     => [ __( 'Drafts', 'dox-newsletter' ), '#A1A1AA' ],
	'sent'      => [ __( 'Sent', 'dox-newsletter' ), '#16A34A' ],
];
?>
<div class="dxn-top">
	<div><div class="crumb">Newsletter</div><h1><?php esc_html_e( 'Campaigns', 'dox-newsletter' ); ?></h1></div>
	<div class="sp"></div>
	<a class="dxn-btn dxn-btn-dark" href="<?php echo esc_url( $new_url ); ?>"><?php echo DXN_Admin::icon( 'plus' ); // phpcs:ignore ?><?php esc_html_e( 'New campaign', 'dox-newsletter' ); ?></a>
</div>
<div class="dxn-page">
	<?php include DXN_PATH . 'admin/views/_status.php'; ?>

	<div class="dxn-toolbar" data-anim>
		<div class="dxn-filters">
			<?php foreach ( $filters as $k => $f ) : if ( $k !== 'all' && ! $counts[ $k ] ) continue; ?>
				<a class="<?php echo $filter === $k ? 'on' : ''; ?>" href="<?php echo esc_url( DXN_Admin::url( 'campaigns', [ 'f' => $k ] ) ); ?>">
					<?php if ( $f[1] ) : ?><span class="dot" style="background:<?php echo esc_attr( $f[1] ); ?>"></span><?php endif; ?>
					<?php echo esc_html( $f[0] ); ?> <em><?php echo esc_html( dxn_num( $counts[ $k ] ) ); ?></em>
				</a>
			<?php endforeach; ?>
		</div>
		<?php if ( $counts['all'] > 2 ) : ?>
			<form class="dxn-search" method="get">
				<input type="hidden" name="page" value="dox-newsletter"><input type="hidden" name="view" value="campaigns"><input type="hidden" name="f" value="<?php echo esc_attr( $filter ); ?>">
				<?php echo DXN_Admin::icon( 'search' ); // phpcs:ignore ?>
				<input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" data-ph="<?php esc_attr_e( 'Search by subject', 'dox-newsletter' ); ?>" placeholder="<?php esc_attr_e( 'Search by subject', 'dox-newsletter' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'dox-newsletter' ); ?>"><span class="dxn-kbd">/</span>
			</form>
		<?php endif; ?>
	</div>

	<div class="dxn-card" data-anim>
		<?php if ( $rows ) : ?>
			<div class="dxn-table-wrap"><table class="dxn-table">
				<thead><tr>
					<th><?php esc_html_e( 'Campaign', 'dox-newsletter' ); ?></th>
					<th><?php esc_html_e( 'Status', 'dox-newsletter' ); ?></th>
					<th class="dxn-hide-sm"><?php esc_html_e( 'Recipients', 'dox-newsletter' ); ?></th>
					<th class="dxn-hide-sm"><?php esc_html_e( 'Opens', 'dox-newsletter' ); ?></th>
					<th class="dxn-hide-sm"><?php esc_html_e( 'Clicks', 'dox-newsletter' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $c ) :
					$st   = in_array( $c['status'], [ 'draft', 'scheduled' ], true ) ? null : DXN_Campaigns::stats( $c['id'] );
					$href = $st ? DXN_Admin::url( 'report', [ 'id' => $c['id'] ] ) : DXN_Admin::url( 'edit', [ 'id' => $c['id'] ] );
					if ( $c['status'] === 'scheduled' ) {
						$when = dxn_date( $c['scheduled_at'], 'D j M, ' . get_option( 'time_format' ) );
					} elseif ( $c['status'] === 'draft' ) {
						$when = sprintf( __( 'Edited %s', 'dox-newsletter' ), dxn_ago( $c['updated_at'] ) );
					} else {
						$when = dxn_date( $c['started_at'], 'j M Y' );
					}
					?>
					<tr data-href="<?php echo esc_url( $href ); ?>">
						<td><div class="t"><?php echo esc_html( $c['subject'] !== '' ? $c['subject'] : __( '(no subject)', 'dox-newsletter' ) ); ?></div><div class="s"><?php echo esc_html( DXN_Campaigns::audience_label( $c ) . ' · ' . $when ); ?></div></td>
						<td><?php echo DXN_Admin::status_pill( $c, $st ); // phpcs:ignore ?></td>
						<td class="dxn-hide-sm num"><?php echo $st ? esc_html( $c['status'] === 'sending' ? dxn_num( $st['sent'] + $st['failed'] ) . ' / ' . dxn_num( $st['total'] ) : dxn_num( $st['sent'] ) ) : '<span class="muted">' . esc_html( dxn_num( count( DXN_Campaigns::audience_ids( $c ) ) ) ) . '</span>'; ?></td>
						<td class="dxn-hide-sm"><?php if ( $st && $st['sent'] ) : ?><div class="dxn-rate"><div class="dxn-meter"><i class="acc" style="width:<?php echo round( $st['opened'] * 100 / $st['sent'] ); ?>%"></i></div><b><?php echo esc_html( dxn_pct( $st['opened'], $st['sent'] ) ); ?></b></div><?php else : ?><span class="muted">–</span><?php endif; ?></td>
						<td class="dxn-hide-sm num"><?php echo $st && $st['sent'] ? esc_html( dxn_pct( $st['clicked'], $st['sent'] ) ) : '<span class="muted">–</span>'; ?></td>
						<td style="text-align:right">
							<div class="dxn-menu">
								<button type="button" class="dxn-iconbtn" data-menu aria-label="<?php esc_attr_e( 'Actions', 'dox-newsletter' ); ?>"><?php echo DXN_Admin::icon( 'more' ); // phpcs:ignore ?></button>
								<div class="dxn-menu-list">
									<?php if ( in_array( $c['status'], [ 'draft', 'scheduled' ], true ) ) : ?>
										<a href="<?php echo esc_url( DXN_Admin::url( 'edit', [ 'id' => $c['id'] ] ) ); ?>"><?php echo DXN_Admin::icon( 'pen' ); // phpcs:ignore ?><?php esc_html_e( 'Edit', 'dox-newsletter' ); ?></a>
									<?php else : ?>
										<a href="<?php echo esc_url( DXN_Admin::url( 'report', [ 'id' => $c['id'] ] ) ); ?>"><?php echo DXN_Admin::icon( 'chart' ); // phpcs:ignore ?><?php esc_html_e( 'Report', 'dox-newsletter' ); ?></a>
									<?php endif; ?>
									<?php if ( $c['status'] === 'scheduled' ) : ?>
										<button type="button" data-act="unschedule" data-id="<?php echo (int) $c['id']; ?>"><?php echo DXN_Admin::icon( 'clock' ); // phpcs:ignore ?><?php esc_html_e( 'Back to draft', 'dox-newsletter' ); ?></button>
									<?php endif; ?>
									<button type="button" data-act="duplicate" data-id="<?php echo (int) $c['id']; ?>"><?php echo DXN_Admin::icon( 'copy' ); // phpcs:ignore ?><?php esc_html_e( 'Duplicate', 'dox-newsletter' ); ?></button>
									<?php if ( in_array( $c['status'], [ 'sending', 'paused' ], true ) ) : ?>
										<hr><button type="button" class="danger" data-act="cancel" data-id="<?php echo (int) $c['id']; ?>" data-confirm="<?php esc_attr_e( 'Whoever has not received it yet will not get it. This cannot be undone.', 'dox-newsletter' ); ?>"><?php echo DXN_Admin::icon( 'x' ); // phpcs:ignore ?><?php esc_html_e( 'Cancel sending', 'dox-newsletter' ); ?></button>
									<?php else : ?>
										<hr><button type="button" class="danger" data-act="delete_campaign" data-id="<?php echo (int) $c['id']; ?>" data-confirm="<?php echo esc_attr( $st ? __( 'The campaign and its report will be deleted. This cannot be undone.', 'dox-newsletter' ) : __( 'The draft will be deleted. This cannot be undone.', 'dox-newsletter' ) ); ?>"><?php echo DXN_Admin::icon( 'trash' ); // phpcs:ignore ?><?php esc_html_e( 'Delete', 'dox-newsletter' ); ?></button>
									<?php endif; ?>
								</div>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
		<?php elseif ( $search !== '' || $filter !== 'all' ) : ?>
			<div class="dxn-card-b muted"><?php esc_html_e( 'No campaign matches.', 'dox-newsletter' ); ?> <a href="<?php echo esc_url( DXN_Admin::url( 'campaigns' ) ); ?>"><?php esc_html_e( 'See all', 'dox-newsletter' ); ?></a></div>
		<?php else : ?>
			<div class="dxn-empty">
				<div class="ic"><?php echo DXN_Admin::icon( 'send' ); // phpcs:ignore ?></div>
				<h3><?php esc_html_e( 'No campaigns yet', 'dox-newsletter' ); ?></h3>
				<p><?php esc_html_e( 'Write the first one. It saves by itself as you type, and you can send yourself a test before it goes out.', 'dox-newsletter' ); ?></p>
				<a class="dxn-btn dxn-btn-dark" href="<?php echo esc_url( $new_url ); ?>"><?php echo DXN_Admin::icon( 'plus' ); // phpcs:ignore ?><?php esc_html_e( 'New campaign', 'dox-newsletter' ); ?></a>
			</div>
		<?php endif; ?>
	</div>

	<div class="dxn-gap"></div>
	<div class="dxn-card" data-anim>
		<div class="dxn-card-h">
			<h3><?php esc_html_e( 'Welcome email', 'dox-newsletter' ); ?></h3>
			<?php echo DXN_Admin::status_pill( $welcome ); // phpcs:ignore ?>
			<div class="sp"></div>
			<a class="dxn-btn dxn-btn-gray dxn-btn-sm" href="<?php echo esc_url( DXN_Admin::url( 'edit', [ 'id' => $welcome['id'] ] ) ); ?>"><?php echo DXN_Admin::icon( 'pen' ); // phpcs:ignore ?><?php esc_html_e( 'Edit', 'dox-newsletter' ); ?></a>
			<label class="dxn-sw" title="<?php esc_attr_e( 'On / off', 'dox-newsletter' ); ?>"><input type="checkbox" data-welcome <?php checked( $welcome['status'], 'active' ); ?> aria-label="<?php esc_attr_e( 'Send the welcome email', 'dox-newsletter' ); ?>"><span></span></label>
		</div>
		<div class="dxn-card-b" style="display:flex;gap:24px;flex-wrap:wrap;align-items:center">
			<div style="flex:1 1 260px"><div class="t" style="color:var(--t1);font-weight:600"><?php echo esc_html( $welcome['subject'] ); ?></div><div class="small muted"><?php esc_html_e( 'Goes out by itself when someone confirms their subscription.', 'dox-newsletter' ); ?></div></div>
			<?php if ( $wst['sent'] ) : ?>
				<div class="small"><b style="color:var(--t1)"><?php echo esc_html( dxn_num( $wst['sent'] ) ); ?></b> <?php esc_html_e( 'sent', 'dox-newsletter' ); ?> · <b style="color:var(--t1)"><?php echo esc_html( dxn_pct( $wst['opened'], $wst['sent'] ) ); ?></b> <?php esc_html_e( 'opened', 'dox-newsletter' ); ?> · <a href="<?php echo esc_url( DXN_Admin::url( 'report', [ 'id' => $welcome['id'] ] ) ); ?>"><?php esc_html_e( 'Report', 'dox-newsletter' ); ?></a></div>
			<?php endif; ?>
		</div>
	</div>
</div>
