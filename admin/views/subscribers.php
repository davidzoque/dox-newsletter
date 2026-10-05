<?php
/**
 * Suscriptores: filtros por estado y lista, buscador, alta a mano, importar,
 * exportar y gestionar las listas.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'active'; // phpcs:ignore
if ( ! in_array( $status, DXN_Subscribers::STATUSES, true ) ) $status = 'active';
$list_id = isset( $_GET['list'] ) ? (int) $_GET['list'] : 0; // phpcs:ignore
$search  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore
$page    = isset( $_GET['p'] ) ? max( 1, (int) $_GET['p'] ) : 1; // phpcs:ignore
$per     = 25;

$counts = DXN_Subscribers::counts();
$lists  = DXN_Lists::all();
$forms  = DXN_Forms::all();
$res    = DXN_Subscribers::query( [ 'status' => $status, 'list_id' => $list_id, 'search' => $search, 'page' => $page, 'per_page' => $per ] );
$pages  = max( 1, (int) ceil( $res['total'] / $per ) );

$filters = [
	'active'       => [ __( 'Active', 'dox-newsletter' ), '#16A34A' ],
	'pending'      => [ __( 'Not confirmed', 'dox-newsletter' ), '#D97706' ],
	'unsubscribed' => [ __( 'Unsubscribed', 'dox-newsletter' ), '#A1A1AA' ],
	'bounced'      => [ __( 'Bounced', 'dox-newsletter' ), '#DC2626' ],
];
$source = function ( $key ) use ( $forms ) {
	if ( isset( $forms[ $key ] ) ) return $forms[ $key ]['name'];
	$map = [ 'import' => __( 'Imported', 'dox-newsletter' ), 'manual' => __( 'Added by hand', 'dox-newsletter' ) ];
	return $map[ $key ] ?? ( $key !== '' ? $key : '–' );
};
$link = function ( array $args ) use ( $status, $list_id, $search ) {
	return DXN_Admin::url( 'subscribers', array_filter( array_merge( [ 'status' => $status, 'list' => $list_id ?: null, 'q' => $search !== '' ? $search : null ], $args ), function ( $v ) { return $v !== null && $v !== ''; } ) );
};
$export = wp_nonce_url( admin_url( 'admin-post.php?action=dxn_export&status=' . $status . '&list=' . $list_id ), 'dxn_export' );
?>
<div class="dxn-top">
	<div><div class="crumb">Newsletter</div><h1><?php esc_html_e( 'Subscribers', 'dox-newsletter' ); ?></h1></div>
	<div class="sp"></div>
	<button type="button" class="dxn-btn dxn-btn-gray dxn-hide-sm" data-sub="lists"><?php echo DXN_Admin::icon( 'list' ); // phpcs:ignore ?><?php esc_html_e( 'Lists', 'dox-newsletter' ); ?></button>
	<button type="button" class="dxn-btn dxn-btn-gray" data-sub="import"><?php echo DXN_Admin::icon( 'download' ); // phpcs:ignore ?><?php esc_html_e( 'Import CSV', 'dox-newsletter' ); ?></button>
	<button type="button" class="dxn-btn dxn-btn-dark" data-sub="add"><?php echo DXN_Admin::icon( 'plus' ); // phpcs:ignore ?><?php esc_html_e( 'Add', 'dox-newsletter' ); ?></button>
</div>
<div class="dxn-page">
	<div class="dxn-toolbar" data-anim>
		<div class="dxn-filters">
			<?php foreach ( $filters as $k => $f ) : if ( $k !== 'active' && ! $counts[ $k ] && $status !== $k ) continue; ?>
				<a class="<?php echo $status === $k ? 'on' : ''; ?>" href="<?php echo esc_url( $link( [ 'status' => $k, 'p' => null ] ) ); ?>"><span class="dot" style="background:<?php echo esc_attr( $f[1] ); ?>"></span><?php echo esc_html( $f[0] ); ?> <em><?php echo esc_html( dxn_num( $counts[ $k ] ) ); ?></em></a>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $lists ) > 1 ) : ?>
			<select class="dxn-select" onchange="location.href=this.value" aria-label="<?php esc_attr_e( 'List', 'dox-newsletter' ); ?>">
				<option value="<?php echo esc_url( $link( [ 'list' => null, 'p' => null ] ) ); ?>"><?php esc_html_e( 'All lists', 'dox-newsletter' ); ?></option>
				<?php foreach ( $lists as $l ) : ?>
					<option value="<?php echo esc_url( $link( [ 'list' => (int) $l['id'], 'p' => null ] ) ); ?>" <?php selected( $list_id, (int) $l['id'] ); ?>><?php echo esc_html( $l['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<form class="dxn-search" method="get">
			<input type="hidden" name="page" value="dox-newsletter"><input type="hidden" name="view" value="subscribers"><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
			<?php if ( $list_id ) : ?><input type="hidden" name="list" value="<?php echo (int) $list_id; ?>"><?php endif; ?>
			<?php echo DXN_Admin::icon( 'search' ); // phpcs:ignore ?>
			<input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" data-ph="<?php echo esc_attr( implode( '|', [ __( 'Search by email', 'dox-newsletter' ), __( 'Search by name', 'dox-newsletter' ) ] ) ); ?>" placeholder="<?php esc_attr_e( 'Search by email', 'dox-newsletter' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'dox-newsletter' ); ?>"><span class="dxn-kbd">/</span>
		</form>
	</div>

	<div class="dxn-card" data-anim>
		<?php if ( $res['rows'] ) : ?>
			<div class="dxn-table-wrap"><table class="dxn-table">
				<thead><tr>
					<th><?php esc_html_e( 'Person', 'dox-newsletter' ); ?></th>
					<th class="dxn-hide-sm"><?php esc_html_e( 'Lists', 'dox-newsletter' ); ?></th>
					<th class="dxn-hide-sm"><?php esc_html_e( 'Came from', 'dox-newsletter' ); ?></th>
					<th class="dxn-hide-sm"><?php esc_html_e( 'Interest', 'dox-newsletter' ); ?></th>
					<th><?php echo $status === 'unsubscribed' ? esc_html__( 'Left', 'dox-newsletter' ) : esc_html__( 'Joined', 'dox-newsletter' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $res['rows'] as $r ) :
					$name = trim( $r['first_name'] . ' ' . $r['last_name'] );
					$ini  = $name !== '' ? mb_substr( $r['first_name'], 0, 1 ) . mb_substr( $r['last_name'], 0, 1 ) : mb_substr( $r['email'], 0, 1 );
					$json = [
						'id' => (int) $r['id'], 'email' => $r['email'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'],
						'status' => $r['status'], 'lists' => DXN_Subscribers::list_ids( (int) $r['id'] ),
						'joined' => dxn_date( $r['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
						'source' => $source( $r['source'] ),
					];
					?>
					<tr data-subrow="<?php echo esc_attr( wp_json_encode( $json ) ); ?>" style="cursor:pointer">
						<td><div class="dxn-who"><span class="dxn-av"><?php echo esc_html( $ini ); ?></span><div style="min-width:0"><div class="t"><?php echo esc_html( $name !== '' ? $name : $r['email'] ); ?></div><?php if ( $name !== '' ) : ?><div class="s"><?php echo esc_html( $r['email'] ); ?></div><?php endif; ?></div></div></td>
						<td class="dxn-hide-sm"><?php foreach ( $r['lists'] as $l ) : ?><span class="dxn-tag"><?php echo esc_html( $l ); ?></span><?php endforeach; ?></td>
						<td class="dxn-hide-sm s"><?php echo esc_html( $source( $r['source'] ) ); ?></td>
						<td class="dxn-hide-sm">
							<?php if ( $r['interest'] === null ) : ?><span class="s"><?php esc_html_e( 'New', 'dox-newsletter' ); ?></span>
							<?php else : ?><span class="dxn-stars" title="<?php echo esc_attr( sprintf( __( '%d of 5', 'dox-newsletter' ), $r['interest'] ) ); ?>"><?php for ( $i = 0; $i < 5; $i++ ) : ?><i class="<?php echo $i < $r['interest'] ? 'f' : ''; ?>"></i><?php endfor; ?></span><?php endif; ?>
						</td>
						<td class="s"><?php echo esc_html( dxn_ago( $status === 'unsubscribed' && $r['unsubscribed_at'] ? $r['unsubscribed_at'] : $r['created_at'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
			<div class="dxn-pager">
				<span><?php echo esc_html( sprintf( __( '%1$s–%2$s of %3$s', 'dox-newsletter' ), dxn_num( ( $page - 1 ) * $per + 1 ), dxn_num( min( $page * $per, $res['total'] ) ), dxn_num( $res['total'] ) ) ); ?></span>
				<?php if ( $page > 1 ) : ?><a class="dxn-btn dxn-btn-gray dxn-btn-sm" href="<?php echo esc_url( $link( [ 'p' => $page - 1 ] ) ); ?>">&larr;</a><?php endif; ?>
				<?php if ( $page < $pages ) : ?><a class="dxn-btn dxn-btn-gray dxn-btn-sm" href="<?php echo esc_url( $link( [ 'p' => $page + 1 ] ) ); ?>">&rarr;</a><?php endif; ?>
				<a class="dxn-btn dxn-btn-link" href="<?php echo esc_url( $export ); ?>"><?php echo DXN_Admin::icon( 'upload' ); // phpcs:ignore ?><?php esc_html_e( 'Export CSV', 'dox-newsletter' ); ?></a>
			</div>
		<?php elseif ( $search !== '' ) : ?>
			<div class="dxn-card-b muted"><?php echo esc_html( sprintf( __( 'Nobody matches «%s».', 'dox-newsletter' ), $search ) ); ?> <a href="<?php echo esc_url( $link( [ 'q' => null ] ) ); ?>"><?php esc_html_e( 'Clear the search', 'dox-newsletter' ); ?></a></div>
		<?php else : ?>
			<div class="dxn-empty">
				<div class="ic"><?php echo DXN_Admin::icon( 'users' ); // phpcs:ignore ?></div>
				<h3><?php echo $status === 'active' ? esc_html__( 'No subscribers yet', 'dox-newsletter' ) : esc_html__( 'Nobody here', 'dox-newsletter' ); ?></h3>
				<?php if ( $status === 'active' ) : ?>
					<p><?php esc_html_e( 'Bring your list in a CSV or put a form on the website. They will appear here.', 'dox-newsletter' ); ?></p>
					<button type="button" class="dxn-btn dxn-btn-dark" data-sub="import"><?php echo DXN_Admin::icon( 'download' ); // phpcs:ignore ?><?php esc_html_e( 'Import CSV', 'dox-newsletter' ); ?></button>
					<a class="dxn-btn dxn-btn-gray" href="<?php echo esc_url( DXN_Admin::url( 'forms' ) ); ?>"><?php esc_html_e( 'Set up the form', 'dox-newsletter' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( $res['rows'] ) : ?>
		<div class="dxn-note"><?php echo DXN_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'Interest comes from their last 5 emails: an open adds a point, a click adds two.', 'dox-newsletter' ); ?></span></div>
	<?php endif; ?>
</div>

<?php // Plantillas de los diálogos: el JS las copia dentro del <dialog>. ?>
<template id="dxn-tpl-sub">
	<div class="dxn-field"><label class="req" for="dxn-s-email"><?php esc_html_e( 'Email', 'dox-newsletter' ); ?></label><input class="dxn-input" id="dxn-s-email" name="email" type="email" required></div>
	<div class="dxn-field"><label for="dxn-s-first"><?php esc_html_e( 'First name', 'dox-newsletter' ); ?></label><input class="dxn-input" id="dxn-s-first" name="first_name"></div>
	<div class="dxn-field"><label for="dxn-s-last"><?php esc_html_e( 'Last name', 'dox-newsletter' ); ?></label><input class="dxn-input" id="dxn-s-last" name="last_name"></div>
	<div class="dxn-field"><span class="lbl"><?php esc_html_e( 'Lists', 'dox-newsletter' ); ?></span><div class="dxn-checks">
		<?php foreach ( $lists as $l ) : ?><label class="dxn-chip"><input type="checkbox" name="lists[]" value="<?php echo (int) $l['id']; ?>"><?php echo esc_html( $l['name'] ); ?></label><?php endforeach; ?>
	</div></div>
	<div class="dxn-field" data-only="new"><span class="lbl"></span><label class="dxn-chip" style="border-radius:10px;align-items:flex-start"><input type="checkbox" name="consent" value="1"><span><?php esc_html_e( 'This person agreed to receive my emails', 'dox-newsletter' ); ?></span></label></div>
	<div class="dxn-field" data-only="edit"><span class="lbl"><?php esc_html_e( 'Status', 'dox-newsletter' ); ?></span><div><span data-f="status"></span><div class="dxn-hint"><span data-f="joined"></span></div></div></div>
</template>

<template id="dxn-tpl-import">
	<p style="margin:0 0 12px"><?php esc_html_e( 'A CSV with a column for the email. If it has columns for the name (first name, last name or name), they are used too. Exports from Mailchimp, Excel or Google Sheets work as they are.', 'dox-newsletter' ); ?></p>
	<div class="dxn-field"><label class="req" for="dxn-i-file"><?php esc_html_e( 'File', 'dox-newsletter' ); ?></label><input class="dxn-input" id="dxn-i-file" type="file" name="file" accept=".csv,text/csv,.txt" style="padding-top:6px!important" required></div>
	<div class="dxn-field"><span class="lbl"><?php esc_html_e( 'To the lists', 'dox-newsletter' ); ?></span><div class="dxn-checks">
		<?php foreach ( $lists as $i => $l ) : ?><label class="dxn-chip"><input type="checkbox" name="lists[]" value="<?php echo (int) $l['id']; ?>" <?php checked( $i, 0 ); ?>><?php echo esc_html( $l['name'] ); ?></label><?php endforeach; ?>
	</div></div>
	<div class="dxn-field"><span class="lbl"></span><label class="dxn-chip" style="border-radius:10px;align-items:flex-start"><input type="checkbox" name="consent" value="1"><span><?php esc_html_e( 'These people agreed to receive my emails', 'dox-newsletter' ); ?></span></label></div>
	<p class="dxn-note"><?php echo DXN_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'They come in as active, without a confirmation email. Whoever is already unsubscribed or bounced is skipped.', 'dox-newsletter' ); ?></span></p>
</template>

<template id="dxn-tpl-lists">
	<div data-lists>
		<?php foreach ( $lists as $l ) : ?>
			<div class="dxn-opt" data-list="<?php echo (int) $l['id']; ?>">
				<div class="tx"><input class="dxn-input" value="<?php echo esc_attr( $l['name'] ); ?>" aria-label="<?php esc_attr_e( 'List name', 'dox-newsletter' ); ?>"><span style="display:block;margin-top:4px"><?php echo esc_html( sprintf( _n( '%s active subscriber', '%s active subscribers', (int) $l['active'], 'dox-newsletter' ), dxn_num( $l['active'] ) ) ); ?></span></div>
				<button type="button" class="dxn-iconbtn" data-list-del aria-label="<?php esc_attr_e( 'Delete list', 'dox-newsletter' ); ?>"><?php echo DXN_Admin::icon( 'trash' ); // phpcs:ignore ?></button>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="dxn-inline-input" style="margin-top:12px"><input class="dxn-input" data-list-new placeholder="<?php esc_attr_e( 'New list', 'dox-newsletter' ); ?>"><button type="button" class="dxn-btn dxn-btn-gray" data-list-add><?php esc_html_e( 'Add', 'dox-newsletter' ); ?></button></div>
	<p class="dxn-note"><?php echo DXN_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'Deleting a list does not delete the people: they stay in the other lists.', 'dox-newsletter' ); ?></span></p>
</template>
<script type="application/json" id="dxn-sub-strings"><?php echo wp_json_encode( [
	'add'        => __( 'Add subscriber', 'dox-newsletter' ),
	'edit'       => __( 'Subscriber', 'dox-newsletter' ),
	'save'       => __( 'Save', 'dox-newsletter' ),
	'import'     => __( 'Import subscribers', 'dox-newsletter' ),
	'import_btn' => __( 'Import', 'dox-newsletter' ),
	'imported'   => __( '%1$s new, %2$s already there, %3$s skipped, %4$s invalid', 'dox-newsletter' ),
	'lists'      => __( 'Lists', 'dox-newsletter' ),
	'done'       => __( 'Done', 'dox-newsletter' ),
	'delete'     => __( 'Delete', 'dox-newsletter' ),
	'del_q'      => __( 'Delete this subscriber completely? Their sends stay in the reports, without their email.', 'dox-newsletter' ),
	'unsub'      => __( 'Unsubscribe', 'dox-newsletter' ),
	'reactivate' => __( 'Mark as active', 'dox-newsletter' ),
	'resend'     => __( 'Resend confirmation', 'dox-newsletter' ),
	'resent'     => __( 'Confirmation sent', 'dox-newsletter' ),
	'came'       => __( 'Joined %1$s · %2$s', 'dox-newsletter' ),
	'list_del_q' => __( 'Delete this list? The people stay in the other lists.', 'dox-newsletter' ),
	'statuses'   => [
		'active'       => __( 'Active', 'dox-newsletter' ),
		'pending'      => __( 'Waiting for confirmation', 'dox-newsletter' ),
		'unsubscribed' => __( 'Unsubscribed', 'dox-newsletter' ),
		'bounced'      => __( 'Bounced', 'dox-newsletter' ),
	],
	'openImport' => ! empty( $_GET['import'] ), // phpcs:ignore
] ); ?></script>
