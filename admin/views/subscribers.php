<?php
/**
 * Suscriptores: filtros por estado y lista, buscador, alta a mano, importar,
 * exportar y gestionar las listas.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'active'; // phpcs:ignore
if ( ! in_array( $status, DXO_Subscribers::STATUSES, true ) ) $status = 'active';
$list_id = isset( $_GET['list'] ) ? (int) $_GET['list'] : 0; // phpcs:ignore
$search  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore
$page    = isset( $_GET['p'] ) ? max( 1, (int) $_GET['p'] ) : 1; // phpcs:ignore
$per     = 25;

$counts = DXO_Subscribers::counts();
$lists  = DXO_Lists::all();
$forms  = DXO_Forms::all();
$res    = DXO_Subscribers::query( [ 'status' => $status, 'list_id' => $list_id, 'search' => $search, 'page' => $page, 'per_page' => $per ] );
$pages  = max( 1, (int) ceil( $res['total'] / $per ) );

$filters = [
	'active'       => [ __( 'Active', 'dox-orbit' ), '#16A34A' ],
	'pending'      => [ __( 'Not confirmed', 'dox-orbit' ), '#D97706' ],
	'unsubscribed' => [ __( 'Unsubscribed', 'dox-orbit' ), '#A1A1AA' ],
	'bounced'      => [ __( 'Bounced', 'dox-orbit' ), '#DC2626' ],
];
$source = function ( $key ) use ( $forms ) {
	if ( isset( $forms[ $key ] ) ) return $forms[ $key ]['name'];
	$map = [ 'import' => __( 'Imported', 'dox-orbit' ), 'manual' => __( 'Added by hand', 'dox-orbit' ) ];
	return $map[ $key ] ?? ( $key !== '' ? $key : '–' );
};
$link = function ( array $args ) use ( $status, $list_id, $search ) {
	return DXO_Admin::url( 'subscribers', array_filter( array_merge( [ 'status' => $status, 'list' => $list_id ?: null, 'q' => $search !== '' ? $search : null ], $args ), function ( $v ) { return $v !== null && $v !== ''; } ) );
};
$export = wp_nonce_url( admin_url( 'admin-post.php?action=dxo_export&status=' . $status . '&list=' . $list_id ), 'dxo_export' );
?>
<div class="dxo-top">
	<div><div class="crumb">Orbit</div><h1><?php esc_html_e( 'Subscribers', 'dox-orbit' ); ?></h1></div>
	<div class="sp"></div>
	<button type="button" class="dxo-btn dxo-btn-gray dxo-hide-sm" data-sub="lists"><?php echo DXO_Admin::icon( 'list' ); // phpcs:ignore ?><?php esc_html_e( 'Lists', 'dox-orbit' ); ?></button>
	<button type="button" class="dxo-btn dxo-btn-gray" data-sub="import"><?php echo DXO_Admin::icon( 'download' ); // phpcs:ignore ?><?php esc_html_e( 'Import CSV', 'dox-orbit' ); ?></button>
	<button type="button" class="dxo-btn dxo-btn-dark" data-sub="add"><?php echo DXO_Admin::icon( 'plus' ); // phpcs:ignore ?><?php esc_html_e( 'Add', 'dox-orbit' ); ?></button>
</div>
<div class="dxo-page">
	<div class="dxo-toolbar" data-anim>
		<div class="dxo-filters">
			<?php foreach ( $filters as $k => $f ) : if ( $k !== 'active' && ! $counts[ $k ] && $status !== $k ) continue; ?>
				<a class="<?php echo $status === $k ? 'on' : ''; ?>" href="<?php echo esc_url( $link( [ 'status' => $k, 'p' => null ] ) ); ?>"><span class="dot" style="background:<?php echo esc_attr( $f[1] ); ?>"></span><?php echo esc_html( $f[0] ); ?> <em><?php echo esc_html( dxo_num( $counts[ $k ] ) ); ?></em></a>
			<?php endforeach; ?>
		</div>
		<?php if ( count( $lists ) > 1 ) : ?>
			<select class="dxo-select" onchange="location.href=this.value" aria-label="<?php esc_attr_e( 'List', 'dox-orbit' ); ?>">
				<option value="<?php echo esc_url( $link( [ 'list' => null, 'p' => null ] ) ); ?>"><?php esc_html_e( 'All lists', 'dox-orbit' ); ?></option>
				<?php foreach ( $lists as $l ) : ?>
					<option value="<?php echo esc_url( $link( [ 'list' => (int) $l['id'], 'p' => null ] ) ); ?>" <?php selected( $list_id, (int) $l['id'] ); ?>><?php echo esc_html( $l['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<form class="dxo-search" method="get">
			<input type="hidden" name="page" value="dox-orbit"><input type="hidden" name="view" value="subscribers"><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
			<?php if ( $list_id ) : ?><input type="hidden" name="list" value="<?php echo (int) $list_id; ?>"><?php endif; ?>
			<?php echo DXO_Admin::icon( 'search' ); // phpcs:ignore ?>
			<input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" data-ph="<?php echo esc_attr( implode( '|', [ __( 'Search by email', 'dox-orbit' ), __( 'Search by name', 'dox-orbit' ) ] ) ); ?>" placeholder="<?php esc_attr_e( 'Search by email', 'dox-orbit' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'dox-orbit' ); ?>"><span class="dxo-kbd">/</span>
		</form>
	</div>

	<div class="dxo-card" data-anim>
		<?php if ( $res['rows'] ) : ?>
			<div class="dxo-table-wrap"><table class="dxo-table">
				<thead><tr>
					<th><?php esc_html_e( 'Person', 'dox-orbit' ); ?></th>
					<th class="dxo-hide-sm"><?php esc_html_e( 'Lists', 'dox-orbit' ); ?></th>
					<th class="dxo-hide-sm"><?php esc_html_e( 'Came from', 'dox-orbit' ); ?></th>
					<th class="dxo-hide-sm"><?php esc_html_e( 'Interest', 'dox-orbit' ); ?></th>
					<th><?php echo $status === 'unsubscribed' ? esc_html__( 'Left', 'dox-orbit' ) : esc_html__( 'Joined', 'dox-orbit' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $res['rows'] as $r ) :
					$name = trim( $r['first_name'] . ' ' . $r['last_name'] );
					$ini  = $name !== '' ? mb_substr( $r['first_name'], 0, 1 ) . mb_substr( $r['last_name'], 0, 1 ) : mb_substr( $r['email'], 0, 1 );
					$json = [
						'id' => (int) $r['id'], 'email' => $r['email'], 'first_name' => $r['first_name'], 'last_name' => $r['last_name'],
						'status' => $r['status'], 'lists' => DXO_Subscribers::list_ids( (int) $r['id'] ),
						'joined' => dxo_date( $r['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
						'source' => $source( $r['source'] ),
					];
					?>
					<tr data-subrow="<?php echo esc_attr( wp_json_encode( $json ) ); ?>" style="cursor:pointer">
						<td><div class="dxo-who"><span class="dxo-av"><?php echo esc_html( $ini ); ?></span><div style="min-width:0"><div class="t"><?php echo esc_html( $name !== '' ? $name : $r['email'] ); ?></div><?php if ( $name !== '' ) : ?><div class="s"><?php echo esc_html( $r['email'] ); ?></div><?php endif; ?></div></div></td>
						<td class="dxo-hide-sm"><?php foreach ( $r['lists'] as $l ) : ?><span class="dxo-tag"><?php echo esc_html( $l ); ?></span><?php endforeach; ?></td>
						<td class="dxo-hide-sm s"><?php echo esc_html( $source( $r['source'] ) ); ?></td>
						<td class="dxo-hide-sm">
							<?php if ( $r['interest'] === null ) : ?><span class="s"><?php esc_html_e( 'New', 'dox-orbit' ); ?></span>
							<?php else : ?><span class="dxo-stars" title="<?php echo esc_attr( sprintf( __( '%d of 5', 'dox-orbit' ), $r['interest'] ) ); ?>"><?php for ( $i = 0; $i < 5; $i++ ) : ?><i class="<?php echo $i < $r['interest'] ? 'f' : ''; ?>"></i><?php endfor; ?></span><?php endif; ?>
						</td>
						<td class="s"><?php echo esc_html( dxo_ago( $status === 'unsubscribed' && $r['unsubscribed_at'] ? $r['unsubscribed_at'] : $r['created_at'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
			<div class="dxo-pager">
				<span><?php echo esc_html( sprintf( __( '%1$s–%2$s of %3$s', 'dox-orbit' ), dxo_num( ( $page - 1 ) * $per + 1 ), dxo_num( min( $page * $per, $res['total'] ) ), dxo_num( $res['total'] ) ) ); ?></span>
				<?php if ( $page > 1 ) : ?><a class="dxo-btn dxo-btn-gray dxo-btn-sm" href="<?php echo esc_url( $link( [ 'p' => $page - 1 ] ) ); ?>">&larr;</a><?php endif; ?>
				<?php if ( $page < $pages ) : ?><a class="dxo-btn dxo-btn-gray dxo-btn-sm" href="<?php echo esc_url( $link( [ 'p' => $page + 1 ] ) ); ?>">&rarr;</a><?php endif; ?>
				<a class="dxo-btn dxo-btn-link" href="<?php echo esc_url( $export ); ?>"><?php echo DXO_Admin::icon( 'upload' ); // phpcs:ignore ?><?php esc_html_e( 'Export CSV', 'dox-orbit' ); ?></a>
			</div>
		<?php elseif ( $search !== '' ) : ?>
			<div class="dxo-card-b muted"><?php echo esc_html( sprintf( __( 'Nobody matches «%s».', 'dox-orbit' ), $search ) ); ?> <a href="<?php echo esc_url( $link( [ 'q' => null ] ) ); ?>"><?php esc_html_e( 'Clear the search', 'dox-orbit' ); ?></a></div>
		<?php else : ?>
			<div class="dxo-empty">
				<div class="ic"><?php echo DXO_Admin::icon( 'users' ); // phpcs:ignore ?></div>
				<h3><?php echo $status === 'active' ? esc_html__( 'No subscribers yet', 'dox-orbit' ) : esc_html__( 'Nobody here', 'dox-orbit' ); ?></h3>
				<?php if ( $status === 'active' ) : ?>
					<p><?php esc_html_e( 'Bring your list in a CSV or put a form on the website. They will appear here.', 'dox-orbit' ); ?></p>
					<button type="button" class="dxo-btn dxo-btn-dark" data-sub="import"><?php echo DXO_Admin::icon( 'download' ); // phpcs:ignore ?><?php esc_html_e( 'Import CSV', 'dox-orbit' ); ?></button>
					<a class="dxo-btn dxo-btn-gray" href="<?php echo esc_url( DXO_Admin::url( 'forms' ) ); ?>"><?php esc_html_e( 'Set up the form', 'dox-orbit' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( $res['rows'] ) : ?>
		<div class="dxo-note"><?php echo DXO_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'Interest comes from their last 5 emails: an open adds a point, a click adds two.', 'dox-orbit' ); ?></span></div>
	<?php endif; ?>
</div>

<?php // Plantillas de los diálogos: el JS las copia dentro del <dialog>. ?>
<template id="dxo-tpl-sub">
	<div class="dxo-field"><label class="req" for="dxo-s-email"><?php esc_html_e( 'Email', 'dox-orbit' ); ?></label><input class="dxo-input" id="dxo-s-email" name="email" type="email" required></div>
	<div class="dxo-field"><label for="dxo-s-first"><?php esc_html_e( 'First name', 'dox-orbit' ); ?></label><input class="dxo-input" id="dxo-s-first" name="first_name"></div>
	<div class="dxo-field"><label for="dxo-s-last"><?php esc_html_e( 'Last name', 'dox-orbit' ); ?></label><input class="dxo-input" id="dxo-s-last" name="last_name"></div>
	<div class="dxo-field"><span class="lbl"><?php esc_html_e( 'Lists', 'dox-orbit' ); ?></span><div class="dxo-checks">
		<?php foreach ( $lists as $l ) : ?><label class="dxo-chip"><input type="checkbox" name="lists[]" value="<?php echo (int) $l['id']; ?>"><?php echo esc_html( $l['name'] ); ?></label><?php endforeach; ?>
	</div></div>
	<div class="dxo-field" data-only="new"><span class="lbl"></span><label class="dxo-chip" style="border-radius:10px;align-items:flex-start"><input type="checkbox" name="consent" value="1"><span><?php esc_html_e( 'This person agreed to receive my emails', 'dox-orbit' ); ?></span></label></div>
	<div class="dxo-field" data-only="edit"><span class="lbl"><?php esc_html_e( 'Status', 'dox-orbit' ); ?></span><div><span data-f="status"></span><div class="dxo-hint"><span data-f="joined"></span></div></div></div>
</template>

<template id="dxo-tpl-import">
	<p style="margin:0 0 12px"><?php esc_html_e( 'A CSV with a column for the email. If it has columns for the name (first name, last name or name), they are used too. Exports from Mailchimp, Excel or Google Sheets work as they are.', 'dox-orbit' ); ?></p>
	<div class="dxo-field"><label class="req" for="dxo-i-file"><?php esc_html_e( 'File', 'dox-orbit' ); ?></label><input class="dxo-input" id="dxo-i-file" type="file" name="file" accept=".csv,text/csv,.txt" style="padding-top:6px!important" required></div>
	<div class="dxo-field"><span class="lbl"><?php esc_html_e( 'To the lists', 'dox-orbit' ); ?></span><div class="dxo-checks">
		<?php foreach ( $lists as $i => $l ) : ?><label class="dxo-chip"><input type="checkbox" name="lists[]" value="<?php echo (int) $l['id']; ?>" <?php checked( $i, 0 ); ?>><?php echo esc_html( $l['name'] ); ?></label><?php endforeach; ?>
	</div></div>
	<div class="dxo-field"><span class="lbl"></span><label class="dxo-chip" style="border-radius:10px;align-items:flex-start"><input type="checkbox" name="consent" value="1"><span><?php esc_html_e( 'These people agreed to receive my emails', 'dox-orbit' ); ?></span></label></div>
	<p class="dxo-note"><?php echo DXO_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'They come in as active, without a confirmation email. Whoever is already unsubscribed or bounced is skipped.', 'dox-orbit' ); ?></span></p>
</template>

<template id="dxo-tpl-lists">
	<div data-lists>
		<?php foreach ( $lists as $l ) : ?>
			<div class="dxo-opt" data-list="<?php echo (int) $l['id']; ?>">
				<div class="tx"><input class="dxo-input" value="<?php echo esc_attr( $l['name'] ); ?>" aria-label="<?php esc_attr_e( 'List name', 'dox-orbit' ); ?>"><span style="display:block;margin-top:4px"><?php echo esc_html( sprintf( _n( '%s active subscriber', '%s active subscribers', (int) $l['active'], 'dox-orbit' ), dxo_num( $l['active'] ) ) ); ?></span></div>
				<button type="button" class="dxo-iconbtn" data-list-del aria-label="<?php esc_attr_e( 'Delete list', 'dox-orbit' ); ?>"><?php echo DXO_Admin::icon( 'trash' ); // phpcs:ignore ?></button>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="dxo-inline-input" style="margin-top:12px"><input class="dxo-input" data-list-new placeholder="<?php esc_attr_e( 'New list', 'dox-orbit' ); ?>"><button type="button" class="dxo-btn dxo-btn-gray" data-list-add><?php esc_html_e( 'Add', 'dox-orbit' ); ?></button></div>
	<p class="dxo-note"><?php echo DXO_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'Deleting a list does not delete the people: they stay in the other lists.', 'dox-orbit' ); ?></span></p>
</template>
<script type="application/json" id="dxo-sub-strings"><?php echo wp_json_encode( [
	'add'        => __( 'Add subscriber', 'dox-orbit' ),
	'edit'       => __( 'Subscriber', 'dox-orbit' ),
	'save'       => __( 'Save', 'dox-orbit' ),
	'import'     => __( 'Import subscribers', 'dox-orbit' ),
	'import_btn' => __( 'Import', 'dox-orbit' ),
	'imported'   => __( '%1$s new, %2$s already there, %3$s skipped, %4$s invalid', 'dox-orbit' ),
	'lists'      => __( 'Lists', 'dox-orbit' ),
	'done'       => __( 'Done', 'dox-orbit' ),
	'delete'     => __( 'Delete', 'dox-orbit' ),
	'del_q'      => __( 'Delete this subscriber completely? Their sends stay in the reports, without their email.', 'dox-orbit' ),
	'unsub'      => __( 'Unsubscribe', 'dox-orbit' ),
	'reactivate' => __( 'Mark as active', 'dox-orbit' ),
	'resend'     => __( 'Resend confirmation', 'dox-orbit' ),
	'resent'     => __( 'Confirmation sent', 'dox-orbit' ),
	'came'       => __( 'Joined %1$s · %2$s', 'dox-orbit' ),
	'list_del_q' => __( 'Delete this list? The people stay in the other lists.', 'dox-orbit' ),
	'statuses'   => [
		'active'       => __( 'Active', 'dox-orbit' ),
		'pending'      => __( 'Waiting for confirmation', 'dox-orbit' ),
		'unsubscribed' => __( 'Unsubscribed', 'dox-orbit' ),
		'bounced'      => __( 'Bounced', 'dox-orbit' ),
	],
	'openImport' => ! empty( $_GET['import'] ), // phpcs:ignore
] ); ?></script>
