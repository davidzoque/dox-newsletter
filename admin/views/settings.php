<?php
/**
 * Ajustes: envío, remitente y marca, confirmación, seguimiento y pie.
 * Una barra flotante abajo avisa de los cambios sin guardar y guarda.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$s       = DXN_Settings::all();
$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'sending'; // phpcs:ignore
$tabs    = [
	'sending' => __( 'Sending', 'dox-newsletter' ),
	'sender'  => __( 'Sender and brand', 'dox-newsletter' ),
	'confirm' => __( 'Confirmation', 'dox-newsletter' ),
	'footer'  => __( 'Tracking and footer', 'dox-newsletter' ),
];
if ( ! isset( $tabs[ $tab ] ) ) $tab = 'sending';
$test_to = get_user_meta( get_current_user_id(), 'dxn_test_to', true ) ?: wp_get_current_user()->user_email;
$logo    = $s['logo_id'] ? wp_get_attachment_image_url( (int) $s['logo_id'], 'medium' ) : '';

// Qué plugin de correo usa la web, para decir por dónde sale de verdad.
$smtp = '';
foreach ( [ 'gosmtp/gosmtp.php' => 'GoSMTP', 'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP', 'fluent-smtp/fluent-smtp.php' => 'FluentSMTP', 'post-smtp/postman-smtp.php' => 'Post SMTP', 'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP' ] as $file => $name ) {
	if ( is_plugin_active( $file ) ) { $smtp = $name; break; }
}
$rate  = (int) $s['rate_per_hour'];
$limit = max( 1, (int) $s['server_limit'] );
$left  = max( 0, $limit - $rate );
?>
<div class="dxn-top">
	<div><div class="crumb">Newsletter</div><h1><?php esc_html_e( 'Settings', 'dox-newsletter' ); ?></h1></div>
</div>
<div class="dxn-page narrow">
	<div class="dxn-tabs" data-anim role="tablist">
		<?php foreach ( $tabs as $k => $t ) : ?>
			<button type="button" role="tab" data-tab="<?php echo esc_attr( $k ); ?>" class="<?php echo $tab === $k ? 'on' : ''; ?>"><?php echo esc_html( $t ); ?></button>
		<?php endforeach; ?>
	</div>

	<form id="dxn-settings" onsubmit="return false" autocomplete="off">

		<!-- ═══ Envío ═══ -->
		<section class="dxn-tab" data-tab-panel="sending" <?php echo $tab !== 'sending' ? 'hidden' : ''; ?>>
			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'How the emails go out', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b">
					<div class="dxn-choices">
						<label class="dxn-choice"><input type="radio" name="transport" value="wp" <?php checked( $s['transport'], 'wp' ); ?>>
							<div class="ic"><?php echo DXN_Admin::icon( 'mail' ); // phpcs:ignore ?></div>
							<b><?php esc_html_e( 'The WordPress email', 'dox-newsletter' ); ?></b>
							<p><?php esc_html_e( 'The same one WooCommerce and the forms use. No cost and no new accounts. For lists of up to a few thousand.', 'dox-newsletter' ); ?></p>
							<span class="dxn-pill p-ok"><span class="dot"></span><?php echo $smtp ? esc_html( sprintf( __( 'Now goes out through %s', 'dox-newsletter' ), $smtp ) ) : esc_html__( 'Goes out through the server', 'dox-newsletter' ); ?></span>
						</label>
						<label class="dxn-choice"><input type="radio" name="transport" value="ses" <?php checked( $s['transport'], 'ses' ); ?>>
							<div class="ic"><?php echo DXN_Admin::icon( 'cloud' ); // phpcs:ignore ?></div>
							<b>Amazon SES</b>
							<p><?php esc_html_e( 'For big lists. About 0.10 USD per 1,000 emails, and it does not use up the server quota.', 'dox-newsletter' ); ?></p>
							<span class="dxn-pill <?php echo $s['ses_user'] ? 'p-ok' : 'p-gray'; ?>"><?php echo $s['ses_user'] ? esc_html__( 'Configured', 'dox-newsletter' ) : esc_html__( 'Not configured', 'dox-newsletter' ); ?></span>
						</label>
					</div>
					<div data-ses <?php echo $s['transport'] !== 'ses' ? 'hidden' : ''; ?> style="margin-top:14px">
						<div class="dxn-field"><label for="st-host"><?php esc_html_e( 'SMTP server', 'dox-newsletter' ); ?></label><div><input class="dxn-input" id="st-host" name="ses_host" value="<?php echo esc_attr( $s['ses_host'] ); ?>"><div class="dxn-hint"><span><?php esc_html_e( 'In the SES console, «SMTP settings». It depends on the region: email-smtp.us-east-2.amazonaws.com is Ohio.', 'dox-newsletter' ); ?></span></div></div></div>
						<div class="dxn-field"><label for="st-port"><?php esc_html_e( 'Port', 'dox-newsletter' ); ?></label><input class="dxn-input" id="st-port" type="number" name="ses_port" value="<?php echo (int) $s['ses_port']; ?>" style="max-width:120px"></div>
						<div class="dxn-field"><label for="st-user"><?php esc_html_e( 'SMTP user', 'dox-newsletter' ); ?></label><input class="dxn-input" id="st-user" name="ses_user" value="<?php echo esc_attr( $s['ses_user'] ); ?>" autocomplete="off"></div>
						<div class="dxn-field"><label for="st-pass"><?php esc_html_e( 'SMTP password', 'dox-newsletter' ); ?></label><div><input class="dxn-input" id="st-pass" type="password" name="ses_pass" value="" autocomplete="new-password" placeholder="<?php echo $s['ses_pass'] ? esc_attr__( 'Saved. Leave it empty to keep it.', 'dox-newsletter' ) : ''; ?>"><div class="dxn-hint"><span><?php esc_html_e( 'It is saved encrypted and never shown again. When saving, the connection with Amazon is checked.', 'dox-newsletter' ); ?></span></div></div></div>
					</div>
				</div>
			</div>

			<div class="dxn-gap"></div>
			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Sending speed', 'dox-newsletter' ); ?></h3><div class="sp"></div><span class="dxn-pill p-acc" id="dxn-speed-pill"><?php echo esc_html( sprintf( __( '%s per hour', 'dox-newsletter' ), dxn_num( $rate ) ) ); ?></span></div>
				<div class="dxn-card-b">
					<p style="margin:0 0 4px" id="dxn-speed-intro"><?php echo wp_kses( sprintf( __( 'Your server lets out <b>%s emails per hour</b> per website. Whatever the newsletter does not use stays free for orders, passwords and forms.', 'dox-newsletter' ), '<span data-limit>' . dxn_num( $limit ) . '</span>' ), [ 'b' => [], 'span' => [ 'data-limit' => [] ] ] ); ?></p>
					<input type="range" min="10" max="<?php echo (int) max( $limit, $rate ); ?>" step="10" name="rate_per_hour" value="<?php echo (int) $rate; ?>" id="dxn-speed" aria-label="<?php esc_attr_e( 'Emails per hour', 'dox-newsletter' ); ?>">
					<div class="dxn-quota">
						<div class="track"><div class="a" id="dxn-qa" style="width:<?php echo min( 100, $rate * 100 / $limit ); ?>%"></div><div class="b" id="dxn-qb"></div></div>
						<div class="legend"><span id="dxn-eta"></span><span><?php esc_html_e( 'Server limit:', 'dox-newsletter' ); ?> <input type="number" name="server_limit" value="<?php echo (int) $limit; ?>" min="10" class="dxn-input" style="width:90px;height:26px!important;min-height:26px;display:inline-block;padding:0 6px!important;font-size:12px!important"> / <?php esc_html_e( 'hour', 'dox-newsletter' ); ?></span></div>
					</div>
					<div class="dxn-note" id="dxn-speed-warn"><?php echo DXN_Admin::icon( 'info' ); // phpcs:ignore ?><span></span></div>
				</div>
			</div>

			<div class="dxn-gap"></div>
			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Test', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b">
					<p style="margin:0 0 12px"><?php esc_html_e( 'Sends the confirmation email with these settings, even if they are not saved yet.', 'dox-newsletter' ); ?></p>
					<div class="dxn-inline-input" style="flex-wrap:wrap">
						<input class="dxn-input" style="flex:1;min-width:220px" type="email" id="dxn-test-to" value="<?php echo esc_attr( $test_to ); ?>" aria-label="<?php esc_attr_e( 'Test email', 'dox-newsletter' ); ?>">
						<button type="button" class="dxn-btn dxn-btn-gray" data-settings-test><?php esc_html_e( 'Send test email', 'dox-newsletter' ); ?></button>
					</div>
				</div>
			</div>
		</section>

		<!-- ═══ Remitente y marca ═══ -->
		<section class="dxn-tab" data-tab-panel="sender" <?php echo $tab !== 'sender' ? 'hidden' : ''; ?>>
			<div class="dxn-card">
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Who it comes from', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b tight">
					<div class="dxn-field"><label class="req" for="st-fn"><?php esc_html_e( 'Name', 'dox-newsletter' ); ?></label><div><input class="dxn-input" id="st-fn" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>"><div class="dxn-hint"><span><?php esc_html_e( 'A person\'s name is opened more than a company\'s: «Ana from Acme» better than «Acme Newsletter».', 'dox-newsletter' ); ?></span></div></div></div>
					<div class="dxn-field"><label class="req" for="st-fe"><?php esc_html_e( 'Email', 'dox-newsletter' ); ?></label><div><input class="dxn-input" id="st-fe" type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>"><div class="dxn-hint"><span><?php echo esc_html( sprintf( __( 'Better an address @%s: Gmail checks that the sender matches the domain.', 'dox-newsletter' ), preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) ); ?></span></div></div></div>
					<div class="dxn-field"><label for="st-rt"><?php esc_html_e( 'Reply to', 'dox-newsletter' ); ?></label><div><input class="dxn-input" id="st-rt" type="email" name="reply_to" value="<?php echo esc_attr( $s['reply_to'] ); ?>" placeholder="<?php esc_attr_e( 'The same email', 'dox-newsletter' ); ?>"><div class="dxn-hint"><span><?php esc_html_e( 'Where replies arrive, if it is a different address.', 'dox-newsletter' ); ?></span></div></div></div>
				</div>
			</div>
			<div class="dxn-gap"></div>
			<div class="dxn-card">
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Brand in the email', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b tight">
					<div class="dxn-field"><span class="lbl"><?php esc_html_e( 'Logo', 'dox-newsletter' ); ?></span><div class="dxn-imgpick" data-logo>
						<img src="<?php echo esc_url( $logo ?: '' ); ?>" alt="" <?php echo $logo ? '' : 'hidden'; ?> style="width:auto;max-width:160px;height:40px;object-fit:contain;padding:4px">
						<input type="hidden" name="logo_id" value="<?php echo (int) $s['logo_id']; ?>">
						<button type="button" class="dxn-btn dxn-btn-gray dxn-btn-sm" data-logo-pick><?php echo $logo ? esc_html__( 'Change', 'dox-newsletter' ) : esc_html__( 'Choose', 'dox-newsletter' ); ?></button>
						<button type="button" class="dxn-btn dxn-btn-link" data-logo-clear <?php echo $logo ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'dox-newsletter' ); ?></button>
					</div></div>
					<div class="dxn-field"><label for="st-co"><?php esc_html_e( 'Company', 'dox-newsletter' ); ?></label><div><input class="dxn-input" id="st-co" name="company" value="<?php echo esc_attr( $s['company'] ); ?>"><div class="dxn-hint"><span><?php esc_html_e( 'At the top if there is no logo, and in the footer.', 'dox-newsletter' ); ?></span></div></div></div>
					<div class="dxn-field"><label class="req" for="st-ad"><?php esc_html_e( 'Postal address', 'dox-newsletter' ); ?></label><div><input class="dxn-input" id="st-ad" name="address" value="<?php echo esc_attr( $s['address'] ); ?>" placeholder="<?php esc_attr_e( 'City, state, country', 'dox-newsletter' ); ?>"><div class="dxn-hint"><span><?php esc_html_e( 'The CAN-SPAM law in the US requires it in every marketing email. City and state is enough for many.', 'dox-newsletter' ); ?></span></div></div></div>
					<div class="dxn-field"><label for="st-ac"><?php esc_html_e( 'Brand color', 'dox-newsletter' ); ?></label><div class="dxn-inline-input"><input type="color" name="accent" value="<?php echo esc_attr( $s['accent'] ); ?>" style="width:44px;height:36px;border:1px solid var(--line);border-radius:10px;padding:2px;background:#fff" id="st-ac"><span class="unit"><?php esc_html_e( 'For the «brand color» buttons and the glow of the forms.', 'dox-newsletter' ); ?></span></div></div>
				</div>
			</div>
		</section>

		<!-- ═══ Confirmación ═══ -->
		<section class="dxn-tab" data-tab-panel="confirm" <?php echo $tab !== 'confirm' ? 'hidden' : ''; ?>>
			<div class="dxn-card">
				<div class="dxn-card-b" style="padding-top:4px;padding-bottom:4px">
					<div class="dxn-opt"><div class="tx"><b><?php esc_html_e( 'Double confirmation', 'dox-newsletter' ); ?></b><span><?php esc_html_e( 'Whoever subscribes receives an email to confirm. It keeps fake and mistyped addresses out, which is what protects your reputation with Gmail. Recommended.', 'dox-newsletter' ); ?></span></div>
						<label class="dxn-sw"><input type="checkbox" name="double_optin" value="1" <?php checked( $s['double_optin'] ); ?>><span></span></label></div>
				</div>
			</div>
			<div class="dxn-gap"></div>
			<div class="dxn-card">
				<div class="dxn-card-h"><h3><?php esc_html_e( 'The confirmation email', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b tight">
					<div class="dxn-field"><label for="st-cs"><?php esc_html_e( 'Subject', 'dox-newsletter' ); ?></label><input class="dxn-input" id="st-cs" name="confirm_subject" value="<?php echo esc_attr( $s['confirm_subject'] ); ?>"></div>
					<div class="dxn-field"><label for="st-ch"><?php esc_html_e( 'Title', 'dox-newsletter' ); ?></label><input class="dxn-input" id="st-ch" name="confirm_heading" value="<?php echo esc_attr( $s['confirm_heading'] ); ?>"></div>
					<div class="dxn-field"><label for="st-ct"><?php esc_html_e( 'Text', 'dox-newsletter' ); ?></label><textarea class="dxn-input" id="st-ct" name="confirm_text" rows="3"><?php echo esc_textarea( $s['confirm_text'] ); ?></textarea></div>
					<div class="dxn-field"><label for="st-cb"><?php esc_html_e( 'Button', 'dox-newsletter' ); ?></label><input class="dxn-input" id="st-cb" name="confirm_button" value="<?php echo esc_attr( $s['confirm_button'] ); ?>"></div>
				</div>
			</div>
			<div class="dxn-gap"></div>
			<div class="dxn-card">
				<div class="dxn-card-h"><h3><?php esc_html_e( 'The page after confirming', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b tight">
					<div class="dxn-field"><label for="st-pt"><?php esc_html_e( 'Title', 'dox-newsletter' ); ?></label><input class="dxn-input" id="st-pt" name="confirmed_title" value="<?php echo esc_attr( $s['confirmed_title'] ); ?>"></div>
					<div class="dxn-field"><label for="st-px"><?php esc_html_e( 'Text', 'dox-newsletter' ); ?></label><textarea class="dxn-input" id="st-px" name="confirmed_text" rows="2"><?php echo esc_textarea( $s['confirmed_text'] ); ?></textarea></div>
				</div>
			</div>
		</section>

		<!-- ═══ Seguimiento y pie ═══ -->
		<section class="dxn-tab" data-tab-panel="footer" <?php echo $tab !== 'footer' ? 'hidden' : ''; ?>>
			<div class="dxn-card">
				<div class="dxn-card-b" style="padding-top:4px;padding-bottom:4px">
					<div class="dxn-opt"><div class="tx"><b><?php esc_html_e( 'Count opens', 'dox-newsletter' ); ?></b><span><?php esc_html_e( 'With an invisible image. Gmail and the iPhone load it by themselves, so it is a figure on the high side.', 'dox-newsletter' ); ?></span></div><label class="dxn-sw"><input type="checkbox" name="track_opens" value="1" <?php checked( $s['track_opens'] ); ?>><span></span></label></div>
					<div class="dxn-opt"><div class="tx"><b><?php esc_html_e( 'Count clicks', 'dox-newsletter' ); ?></b><span><?php esc_html_e( 'The links go through your website for an instant before reaching their destination.', 'dox-newsletter' ); ?></span></div><label class="dxn-sw"><input type="checkbox" name="track_clicks" value="1" <?php checked( $s['track_clicks'] ); ?>><span></span></label></div>
				</div>
			</div>
			<div class="dxn-gap"></div>
			<div class="dxn-card">
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Footer of the emails', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b tight">
					<div class="dxn-field"><label for="st-fw"><?php esc_html_e( 'Why they receive it', 'dox-newsletter' ); ?></label><div><input class="dxn-input" id="st-fw" name="footer_why" value="<?php echo esc_attr( $s['footer_why'] ); ?>"><div class="dxn-hint"><span><?php esc_html_e( 'Below it always go the unsubscribe link and the postal address.', 'dox-newsletter' ); ?></span></div></div></div>
				</div>
			</div>
		</section>

		<div class="dxn-savebar">
			<span class="state" id="dxn-settings-state"><?php esc_html_e( 'Everything saved', 'dox-newsletter' ); ?></span>
			<span class="sp"></span>
			<button type="button" class="dxn-btn dxn-btn-gray" data-settings-reset hidden><?php esc_html_e( 'Discard', 'dox-newsletter' ); ?></button>
			<button type="button" class="dxn-btn dxn-btn-dark" data-settings-save><?php esc_html_e( 'Save', 'dox-newsletter' ); ?></button>
		</div>
	</form>
</div>
<script type="application/json" id="dxn-set-strings"><?php echo wp_json_encode( [
	'perHour'  => __( '%s per hour', 'dox-newsletter' ),
	'news'     => __( 'Newsletter %s', 'dox-newsletter' ),
	'rest'     => __( 'Rest of the website %s', 'dox-newsletter' ),
	'eta'      => __( '%1$s people take about %2$s hours', 'dox-newsletter' ),
	'eta_min'  => __( '%1$s people take about %2$s minutes', 'dox-newsletter' ),
	'active'   => DXN_Stats::active_count(),
	'okWarn'   => __( 'Recommended: leave at least 50 free per hour.', 'dox-newsletter' ),
	'badWarn'  => __( 'With less than 50 free, order emails may have to wait.', 'dox-newsletter' ),
	'sesWarn'  => __( 'With Amazon SES the server quota is not used: the speed only spreads the send.', 'dox-newsletter' ),
	'unsaved'  => __( 'Unsaved changes', 'dox-newsletter' ),
	'saved'    => __( 'Everything saved', 'dox-newsletter' ),
	'checking' => __( 'Checking with Amazon…', 'dox-newsletter' ),
	'choose'   => __( 'Choose', 'dox-newsletter' ),
	'change'   => __( 'Change', 'dox-newsletter' ),
	'logo'     => __( 'Logo for the emails', 'dox-newsletter' ),
	'test_ok'  => __( 'Test sent to %s. Check the inbox (and spam).', 'dox-newsletter' ),
] ); ?></script>
