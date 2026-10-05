<?php
/**
 * Editor de una campaña: a la izquierda el asunto, a quién y los bloques; a la
 * derecha la vista previa en vivo (el mismo HTML que se envía). Se guarda solo.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
$c  = $id ? DXN_Campaigns::get( $id ) : null;

if ( ! $c ) : ?>
	<div class="dxn-top"><div><h1><?php esc_html_e( 'Campaign not found', 'dox-newsletter' ); ?></h1></div></div>
	<div class="dxn-page"><a class="dxn-btn dxn-btn-gray" href="<?php echo esc_url( DXN_Admin::url( 'campaigns' ) ); ?>"><?php esc_html_e( 'Back to campaigns', 'dox-newsletter' ); ?></a></div>
	<?php return;
endif;

$editable = in_array( $c['status'], [ 'draft', 'scheduled', 'active', 'inactive' ], true );
if ( ! $editable ) : ?>
	<div class="dxn-top"><div><div class="crumb"><a href="<?php echo esc_url( DXN_Admin::url( 'campaigns' ) ); ?>"><?php esc_html_e( 'Campaigns', 'dox-newsletter' ); ?></a></div><h1><?php echo esc_html( $c['subject'] ); ?></h1></div></div>
	<div class="dxn-page"><div class="dxn-card"><div class="dxn-card-b"><?php esc_html_e( 'This campaign has already gone out and cannot be edited. You can duplicate it to send a new version.', 'dox-newsletter' ); ?> <a href="<?php echo esc_url( DXN_Admin::url( 'report', [ 'id' => $c['id'] ] ) ); ?>"><?php esc_html_e( 'See the report', 'dox-newsletter' ); ?></a></div></div></div>
	<?php return;
endif;

$s        = DXN_Settings::all();
$welcome  = $c['type'] === 'welcome';
$lists    = DXN_Lists::all();
$resend   = ! empty( $c['audience']['resend'] );
$test_to  = get_user_meta( get_current_user_id(), 'dxn_test_to', true ) ?: wp_get_current_user()->user_email;
$audience = $welcome ? 0 : count( DXN_Campaigns::audience_ids( $c ) );
$brand    = DXN_Settings::brand();
$initials = strtoupper( mb_substr( preg_replace( '/[^\p{L}\s]/u', '', $s['from_name'] ), 0, 1 ) . mb_substr( (string) strstr( trim( $s['from_name'] ), ' ' ), 1, 1 ) );

$data = [
	'id'        => (int) $c['id'],
	'type'      => $c['type'],
	'status'    => $c['status'],
	'subject'   => $c['subject'],
	'preheader' => $c['preheader'],
	'blocks'    => $c['blocks'],
	'lists'     => array_map( 'intval', $c['audience']['lists'] ?? [] ),
	'resend'    => $resend,
	'testTo'    => $test_to,
	'tested'    => (bool) $c['test_sent_at'],
	'audience'  => $audience,
	'scheduled' => $c['scheduled_at'] ? dxn_date( $c['scheduled_at'], 'Y-m-d\TH:i' ) : '',
	'accent'    => $brand['accent'],
	'problems'  => DXN_Campaigns::problems( $c ),
	'strings'   => [
		'people'      => _n( '%s person', '%s people', 2, 'dox-newsletter' ),
		'person'      => _n( '%s person', '%s people', 1, 'dox-newsletter' ),
		'send_now'    => __( 'Send now', 'dox-newsletter' ),
		'schedule'    => __( 'Schedule', 'dox-newsletter' ),
		'send_title'  => __( 'Ready to send?', 'dox-newsletter' ),
		'fix_title'   => __( 'Before sending', 'dox-newsletter' ),
		'when'        => __( 'When', 'dox-newsletter' ),
		'now'         => __( 'Now', 'dox-newsletter' ),
		'later'       => __( 'Choose a date', 'dox-newsletter' ),
		'goes_to'     => __( 'It will go to %1$s. At %2$s per hour, it takes about %3$s.', 'dox-newsletter' ),
		'hours'       => __( '%s hours', 'dox-newsletter' ),
		'minutes'     => __( '%s minutes', 'dox-newsletter' ),
		'no_test'     => __( 'You have not sent yourself a test yet.', 'dox-newsletter' ),
		'test_title'  => __( 'Send a test', 'dox-newsletter' ),
		'test_text'   => __( 'With your name in the fields and «[Test]» in the subject. It does not count in the report.', 'dox-newsletter' ),
		'send_test'   => __( 'Send test', 'dox-newsletter' ),
		'all_lists'   => __( 'All subscribers', 'dox-newsletter' ),
		'checks_ok'   => [
			'unsub'   => __( '<b>Unsubscribe link</b> and the one-click unsubscribe header that Gmail and Yahoo require', 'dox-newsletter' ),
			'address' => __( '<b>Postal address</b> in the footer, as required by CAN-SPAM', 'dox-newsletter' ),
			'alt'     => __( '<b>All images</b> have alternative text', 'dox-newsletter' ),
			'test'    => __( '<b>Test sent.</b> Check it on your phone too.', 'dox-newsletter' ),
		],
		'checks_bad'  => [
			'address' => __( '<b>No postal address.</b> Add it in Settings: CAN-SPAM requires it.', 'dox-newsletter' ),
			'alt'     => __( '<b>Some image has no alternative text.</b> It is what is read when images are blocked.', 'dox-newsletter' ),
			'test'    => __( '<b>No test sent yet.</b> Send yourself one before scheduling it.', 'dox-newsletter' ),
		],
		'hasAddress'  => $s['address'] !== '',
		'merge_help'  => __( 'Fields: {first_name}, {last_name}, {email}. With a fallback: {first_name|friend}.', 'dox-newsletter' ),
		'labels'      => [
			'text'     => __( 'Text', 'dox-newsletter' ),
			'size'     => __( 'Size', 'dox-newsletter' ),
			'large'    => __( 'Large', 'dox-newsletter' ),
			'medium'   => __( 'Medium', 'dox-newsletter' ),
			'small'    => __( 'Small', 'dox-newsletter' ),
			'align'    => __( 'Align', 'dox-newsletter' ),
			'left'     => __( 'Left', 'dox-newsletter' ),
			'center'   => __( 'Center', 'dox-newsletter' ),
			'link'     => __( 'Link', 'dox-newsletter' ),
			'url'      => __( 'Address', 'dox-newsletter' ),
			'alt'      => __( 'Alt text', 'dox-newsletter' ),
			'alt_hint' => __( 'What the image shows, in a few words.', 'dox-newsletter' ),
			'image'    => __( 'Image', 'dox-newsletter' ),
			'choose'   => __( 'Choose', 'dox-newsletter' ),
			'change'   => __( 'Change', 'dox-newsletter' ),
			'style'    => __( 'Color', 'dox-newsletter' ),
			'dark'     => __( 'Black', 'dox-newsletter' ),
			'accent'   => __( 'Brand color', 'dox-newsletter' ),
			'height'   => __( 'Height', 'dox-newsletter' ),
			'post'     => __( 'Post', 'dox-newsletter' ),
			'search'   => __( 'Search posts…', 'dox-newsletter' ),
			'cta'      => __( 'Link text', 'dox-newsletter' ),
			'move_up'  => __( 'Move up', 'dox-newsletter' ),
			'move_dn'  => __( 'Move down', 'dox-newsletter' ),
			'remove'   => __( 'Remove', 'dox-newsletter' ),
			'bold'     => __( 'Bold', 'dox-newsletter' ),
			'italic'   => __( 'Italic', 'dox-newsletter' ),
			'list'     => __( 'List', 'dox-newsletter' ),
			'add_link' => __( 'Link', 'dox-newsletter' ),
			'link_prompt' => __( 'Link address (https://…)', 'dox-newsletter' ),
			'apply'    => __( 'Apply', 'dox-newsletter' ),
		],
	],
];
?>
<div class="dxn-top">
	<div style="min-width:0">
		<div class="crumb"><a href="<?php echo esc_url( DXN_Admin::url( 'campaigns' ) ); ?>"><?php esc_html_e( 'Campaigns', 'dox-newsletter' ); ?></a></div>
		<h1 id="dxn-title"><?php echo esc_html( $c['subject'] !== '' ? $c['subject'] : ( $welcome ? __( 'Welcome email', 'dox-newsletter' ) : __( 'New campaign', 'dox-newsletter' ) ) ); ?></h1>
	</div>
	<?php if ( $c['status'] === 'scheduled' ) : ?>
		<span class="dxn-pill p-info"><span class="dot"></span><?php echo esc_html( sprintf( __( 'Scheduled for %s', 'dox-newsletter' ), dxn_date( $c['scheduled_at'], 'D j M, ' . get_option( 'time_format' ) ) ) ); ?></span>
	<?php endif; ?>
	<div class="sp"></div>
	<span class="small muted dxn-hide-sm" id="dxn-save-state" style="white-space:nowrap"><?php esc_html_e( 'Saved', 'dox-newsletter' ); ?></span>
	<button type="button" class="dxn-btn dxn-btn-gray" data-ed="test"><?php echo DXN_Admin::icon( 'mail' ); // phpcs:ignore ?><?php esc_html_e( 'Send me a test', 'dox-newsletter' ); ?></button>
	<?php if ( $welcome ) : ?>
		<a class="dxn-btn dxn-btn-dark" href="<?php echo esc_url( DXN_Admin::url( 'campaigns' ) ); ?>"><?php esc_html_e( 'Done', 'dox-newsletter' ); ?></a>
	<?php else : ?>
		<button type="button" class="dxn-btn dxn-btn-dark" data-ed="launch"><?php echo $c['status'] === 'scheduled' ? esc_html__( 'Change date', 'dox-newsletter' ) : esc_html__( 'Send or schedule', 'dox-newsletter' ); ?> <?php echo DXN_Admin::icon( 'arrow' ); // phpcs:ignore ?></button>
	<?php endif; ?>
</div>

<div class="dxn-page wide">
	<div class="dxn-ed" id="dxn-editor">
		<div class="dxn-col">
			<div class="dxn-card" data-anim>
				<div class="dxn-card-b tight">
					<div class="dxn-field">
						<label class="req" for="dxn-subject"><?php esc_html_e( 'Subject', 'dox-newsletter' ); ?></label>
						<div><input class="dxn-input" id="dxn-subject" maxlength="255" value="<?php echo esc_attr( $c['subject'] ); ?>" placeholder="<?php esc_attr_e( 'What is this email about?', 'dox-newsletter' ); ?>">
							<div class="dxn-hint"><span id="dxn-subject-hint"><?php esc_html_e( 'Between 30 and 50 characters is read in full on a phone', 'dox-newsletter' ); ?></span><span id="dxn-subject-count"></span></div></div>
					</div>
					<div class="dxn-field">
						<label for="dxn-preheader"><?php esc_html_e( 'Preview text', 'dox-newsletter' ); ?></label>
						<div><input class="dxn-input" id="dxn-preheader" maxlength="255" value="<?php echo esc_attr( $c['preheader'] ); ?>" placeholder="<?php esc_attr_e( 'A line that invites to open it', 'dox-newsletter' ); ?>">
							<div class="dxn-hint"><span><?php esc_html_e( 'What is seen next to the subject in the inbox', 'dox-newsletter' ); ?></span></div></div>
					</div>
					<div class="dxn-field">
						<span class="lbl"><?php esc_html_e( 'From', 'dox-newsletter' ); ?></span>
						<div><input class="dxn-input" readonly value="<?php echo esc_attr( $s['from_name'] . ' · ' . $s['from_email'] ); ?>">
							<div class="dxn-hint"><a href="<?php echo esc_url( DXN_Admin::url( 'settings', [ 'tab' => 'sender' ] ) ); ?>"><?php esc_html_e( 'Change it in Settings', 'dox-newsletter' ); ?></a></div></div>
					</div>
				</div>
			</div>

			<?php if ( ! $welcome ) : ?>
				<div class="dxn-card" data-anim>
					<div class="dxn-card-h"><h3><?php esc_html_e( 'Who receives it', 'dox-newsletter' ); ?></h3><div class="sp"></div><span class="dxn-pill p-acc" id="dxn-audience"><?php echo esc_html( sprintf( _n( '%s person', '%s people', $audience, 'dox-newsletter' ), dxn_num( $audience ) ) ); ?></span></div>
					<div class="dxn-card-b">
						<?php if ( $resend ) : ?>
							<p style="margin:0"><?php echo esc_html( DXN_Campaigns::audience_label( $c ) ); ?>.</p>
							<p class="small muted" style="margin:6px 0 0"><?php esc_html_e( 'Counted again when it goes out: whoever opens the original in the meantime will not receive it.', 'dox-newsletter' ); ?></p>
						<?php else : ?>
							<div class="dxn-checks" id="dxn-lists">
								<?php foreach ( $lists as $l ) : ?>
									<label class="dxn-chip"><input type="checkbox" value="<?php echo (int) $l['id']; ?>" <?php checked( in_array( (int) $l['id'], $data['lists'], true ) ); ?>><?php echo esc_html( $l['name'] ); ?> <em><?php echo esc_html( dxn_num( $l['active'] ) ); ?></em></label>
								<?php endforeach; ?>
							</div>
							<p class="small muted" style="margin:10px 0 0"><?php esc_html_e( 'Only active subscribers. Whoever is on two lists receives it once. With no list checked, it goes to everyone.', 'dox-newsletter' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Blocks', 'dox-newsletter' ); ?></h3><div class="sp"></div><span class="small muted"><?php esc_html_e( 'Drag to reorder', 'dox-newsletter' ); ?></span></div>
				<div class="dxn-card-b">
					<div class="dxn-blocks" id="dxn-blocks"></div>
					<div class="dxn-add" id="dxn-add">
						<?php foreach ( [ 'heading', 'text', 'image', 'button', 'post', 'divider', 'spacer' ] as $t ) : ?>
							<button type="button" data-add="<?php echo esc_attr( $t ); ?>">+ <span data-label="<?php echo esc_attr( $t ); ?>"></span></button>
						<?php endforeach; ?>
					</div>
					<p class="dxn-note"><?php echo DXN_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php echo esc_html( $data['strings']['merge_help'] ); ?></span></p>
				</div>
			</div>

			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Before sending', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b dxn-checklist" id="dxn-checklist"></div>
			</div>
		</div>

		<div class="dxn-stage" id="dxn-stage" data-anim>
			<div class="dxn-stage-top">
				<div class="dxn-seg" id="dxn-device"><button type="button" class="on" data-d="desk"><?php esc_html_e( 'Desktop', 'dox-newsletter' ); ?></button><button type="button" data-d="mobile"><?php esc_html_e( 'Phone', 'dox-newsletter' ); ?></button></div>
				<span class="small muted" style="margin-left:auto"><?php esc_html_e( 'Live preview, with your name in the fields', 'dox-newsletter' ); ?></span>
			</div>
			<div class="dxn-inbox">
				<div class="dxn-av"><?php echo esc_html( $initials ?: 'N' ); ?></div>
				<div style="flex:1;min-width:0">
					<div class="l1"><b><?php echo esc_html( $s['from_name'] ); ?></b><span><?php echo esc_html( wp_date( get_option( 'time_format' ) ) ); ?></span></div>
					<div class="subj" id="dxn-inbox-subject"></div>
					<div class="pre" id="dxn-inbox-pre"></div>
				</div>
			</div>
			<div class="dxn-frame-wrap"><iframe class="dxn-frame" id="dxn-frame" title="<?php esc_attr_e( 'Email preview', 'dox-newsletter' ); ?>" sandbox="allow-same-origin"></iframe></div>
		</div>
	</div>
</div>
<script type="application/json" id="dxn-data"><?php echo wp_json_encode( $data ); ?></script>
