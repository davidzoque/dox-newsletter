<?php
/**
 * Formularios: a la izquierda cómo se verá en la web (en vivo) y dónde sale;
 * a la derecha los textos y el comportamiento.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$forms = DXN_Forms::all();
$slug  = isset( $_GET['form'] ) ? sanitize_key( $_GET['form'] ) : ''; // phpcs:ignore
$is_new = $slug === 'new';
$f     = $is_new ? array_merge( DXN_Forms::defaults(), [ 'slug' => '', 'name' => __( 'New form', 'dox-newsletter' ), 'list_id' => DXN_Lists::default_id() ] ) : ( $forms[ $slug ] ?? reset( $forms ) );
$lists = DXN_Lists::all();
$signups = DXN_Forms::signups( 30 );
$host  = wp_parse_url( home_url(), PHP_URL_HOST );
?>
<div class="dxn-top">
	<div><div class="crumb">Newsletter</div><h1><?php esc_html_e( 'Forms', 'dox-newsletter' ); ?></h1></div>
	<div class="sp"></div>
	<?php if ( ! $is_new && count( $forms ) > 1 ) : ?>
		<button type="button" class="dxn-btn dxn-btn-danger dxn-btn-sm" data-form-del="<?php echo esc_attr( $f['slug'] ); ?>"><?php echo DXN_Admin::icon( 'trash' ); // phpcs:ignore ?><?php esc_html_e( 'Delete', 'dox-newsletter' ); ?></button>
	<?php endif; ?>
	<button type="button" class="dxn-btn dxn-btn-dark" data-form-save><?php esc_html_e( 'Save form', 'dox-newsletter' ); ?></button>
</div>
<div class="dxn-page">
	<div class="dxn-toolbar" data-anim>
		<div class="dxn-filters">
			<?php foreach ( $forms as $k => $x ) : ?>
				<a class="<?php echo ! $is_new && $k === $f['slug'] ? 'on' : ''; ?>" href="<?php echo esc_url( DXN_Admin::url( 'forms', [ 'form' => $k ] ) ); ?>">
					<span class="dot" style="background:<?php echo $x['placement'] === 'inline' || $x['enabled'] ? '#16A34A' : '#A1A1AA'; ?>"></span><?php echo esc_html( $x['name'] ); ?>
					<?php if ( ! empty( $signups[ $k ] ) ) : ?><em><?php echo esc_html( sprintf( __( '+%s', 'dox-newsletter' ), dxn_num( $signups[ $k ] ) ) ); ?></em><?php endif; ?>
				</a>
			<?php endforeach; ?>
			<a class="<?php echo $is_new ? 'on' : ''; ?>" href="<?php echo esc_url( DXN_Admin::url( 'forms', [ 'form' => 'new' ] ) ); ?>">+ <?php esc_html_e( 'New form', 'dox-newsletter' ); ?></a>
		</div>
		<span class="small muted" style="margin-left:auto"><?php esc_html_e( 'The number is who joined through each form in 30 days', 'dox-newsletter' ); ?></span>
	</div>

	<form id="dxn-form-edit" class="dxn-row" onsubmit="return false">
		<input type="hidden" name="slug" value="<?php echo esc_attr( $f['slug'] ); ?>">
		<div class="dxn-col" style="width:58%">
			<div class="dxn-site" data-anim>
				<div class="chrome"><i></i><i></i><i></i><span><?php echo esc_html( $host . ( $f['where'] === 'posts' ? '/blog' : '' ) ); ?></span></div>
				<div class="body<?php echo $f['placement'] === 'bar' ? ' is-bar' : ''; ?>" id="dxn-form-preview"><?php echo DXN_Forms::render( $f, $f['placement'] === 'bar' ? 'preview-bar' : 'preview' ); // phpcs:ignore ?></div>
			</div>

			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Where it appears', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b">
					<div class="dxn-choices">
						<label class="dxn-choice"><input type="radio" name="placement" value="inline" <?php checked( $f['placement'], 'inline' ); ?>>
							<div class="sk"><i style="left:10%;top:18%;width:80%;height:10px"></i><i style="left:10%;top:42%;width:60%;height:6px"></i><i style="left:20%;top:64%;width:60%;height:14px;background:var(--accent)"></i></div>
							<b><?php esc_html_e( 'In a page', 'dox-newsletter' ); ?></b><span class="sub"><?php esc_html_e( 'Shortcode or Elementor widget', 'dox-newsletter' ); ?></span></label>
						<label class="dxn-choice"><input type="radio" name="placement" value="popup" <?php checked( $f['placement'], 'popup' ); ?>>
							<div class="sk"><i style="left:25%;top:15%;width:50%;height:70%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.12)"></i></div>
							<b><?php esc_html_e( 'Pop-up', 'dox-newsletter' ); ?></b><span class="sub"><?php esc_html_e( 'When scrolling or after a few seconds', 'dox-newsletter' ); ?></span></label>
						<label class="dxn-choice"><input type="radio" name="placement" value="bar" <?php checked( $f['placement'], 'bar' ); ?>>
							<div class="sk"><i style="left:0;bottom:0;width:100%;height:14px;background:var(--ink)"></i></div>
							<b><?php esc_html_e( 'Bottom bar', 'dox-newsletter' ); ?></b><span class="sub"><?php esc_html_e( 'Fixed, can be closed', 'dox-newsletter' ); ?></span></label>
					</div>

					<div data-place="inline" style="margin-top:16px" <?php echo $f['placement'] !== 'inline' ? 'hidden' : ''; ?>>
						<?php if ( $is_new ) : ?>
							<p class="small muted" style="margin:0"><?php esc_html_e( 'Save the form to get its shortcode.', 'dox-newsletter' ); ?></p>
						<?php else : ?>
							<div class="dxn-code"><span>[dox_newsletter form="<?php echo esc_html( $f['slug'] ); ?>"]</span><button type="button" class="dxn-btn dxn-btn-gray dxn-btn-sm" data-copy='[dox_newsletter form="<?php echo esc_attr( $f['slug'] ); ?>"]'><?php echo DXN_Admin::icon( 'copy' ); // phpcs:ignore ?><?php esc_html_e( 'Copy', 'dox-newsletter' ); ?></button></div>
							<p class="dxn-note"><?php echo DXN_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'Paste it in any page or post. In Elementor, look for the «Dox Newsletter» widget.', 'dox-newsletter' ); ?></span></p>
						<?php endif; ?>
					</div>
					<div data-place="popup bar" style="margin-top:6px" <?php echo $f['placement'] === 'inline' ? 'hidden' : ''; ?>>
						<div class="dxn-opt">
							<div class="tx"><b><?php esc_html_e( 'Show it on the website', 'dox-newsletter' ); ?></b><span><?php esc_html_e( 'Off, it is saved but nobody sees it.', 'dox-newsletter' ); ?></span></div>
							<label class="dxn-sw"><input type="checkbox" name="enabled" value="1" <?php checked( $f['enabled'] ); ?>><span></span></label>
						</div>
						<div class="dxn-field">
							<span class="lbl"><?php esc_html_e( 'Pages', 'dox-newsletter' ); ?></span>
							<select class="dxn-input" name="where"><option value="posts" <?php selected( $f['where'], 'posts' ); ?>><?php esc_html_e( 'Only blog posts', 'dox-newsletter' ); ?></option><option value="all" <?php selected( $f['where'], 'all' ); ?>><?php esc_html_e( 'The whole website', 'dox-newsletter' ); ?></option></select>
						</div>
						<div class="dxn-field" data-place="popup" <?php echo $f['placement'] !== 'popup' ? 'hidden' : ''; ?>>
							<span class="lbl"><?php esc_html_e( 'Opens', 'dox-newsletter' ); ?></span>
							<div class="dxn-inline-input">
								<select class="dxn-input" name="trigger" style="max-width:220px"><option value="scroll" <?php selected( $f['trigger'], 'scroll' ); ?>><?php esc_html_e( 'When scrolling down', 'dox-newsletter' ); ?></option><option value="delay" <?php selected( $f['trigger'], 'delay' ); ?>><?php esc_html_e( 'After some seconds', 'dox-newsletter' ); ?></option></select>
								<input class="dxn-input" type="number" name="trigger_value" min="1" max="300" value="<?php echo (int) $f['trigger_value']; ?>" style="max-width:90px">
								<span class="unit" data-unit><?php echo $f['trigger'] === 'scroll' ? '%' : esc_html__( 'seconds', 'dox-newsletter' ); ?></span>
							</div>
						</div>
						<p class="dxn-note"><?php echo DXN_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'Whoever closes it does not see it again for 14 days, and whoever subscribes does not see it again.', 'dox-newsletter' ); ?></span></p>
					</div>
				</div>
			</div>
		</div>

		<div class="dxn-col" style="width:42%">
			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Texts', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b tight">
					<div class="dxn-field"><label class="req" for="ff-name"><?php esc_html_e( 'Name', 'dox-newsletter' ); ?></label><div><input class="dxn-input" id="ff-name" name="name" value="<?php echo esc_attr( $f['name'] ); ?>"><div class="dxn-hint"><span><?php esc_html_e( 'Only you see it, to tell the forms apart.', 'dox-newsletter' ); ?></span></div></div></div>
					<div class="dxn-field"><label for="ff-title"><?php esc_html_e( 'Title', 'dox-newsletter' ); ?></label><input class="dxn-input" id="ff-title" name="title" value="<?php echo esc_attr( $f['title'] ); ?>"></div>
					<div class="dxn-field"><label for="ff-text"><?php esc_html_e( 'Text', 'dox-newsletter' ); ?></label><textarea class="dxn-input" id="ff-text" name="text" rows="3"><?php echo esc_textarea( $f['text'] ); ?></textarea></div>
					<div class="dxn-field"><label class="req" for="ff-button"><?php esc_html_e( 'Button', 'dox-newsletter' ); ?></label><input class="dxn-input" id="ff-button" name="button" value="<?php echo esc_attr( $f['button'] ); ?>"></div>
					<div class="dxn-field"><label for="ff-ph"><?php esc_html_e( 'Email field', 'dox-newsletter' ); ?></label><input class="dxn-input" id="ff-ph" name="placeholder" value="<?php echo esc_attr( $f['placeholder'] ); ?>"></div>
					<div class="dxn-field"><label for="ff-list"><?php esc_html_e( 'List', 'dox-newsletter' ); ?></label>
						<select class="dxn-input" id="ff-list" name="list_id"><?php foreach ( $lists as $l ) : ?><option value="<?php echo (int) $l['id']; ?>" <?php selected( (int) $f['list_id'], (int) $l['id'] ); ?>><?php echo esc_html( $l['name'] ); ?></option><?php endforeach; ?></select></div>
					<div class="dxn-field"><label for="ff-ok"><?php esc_html_e( 'After sending', 'dox-newsletter' ); ?></label><div><textarea class="dxn-input" id="ff-ok" name="success_text" rows="2"><?php echo esc_textarea( $f['success_text'] ); ?></textarea><div class="dxn-hint"><span><?php esc_html_e( 'What they see while the confirmation email is on its way.', 'dox-newsletter' ); ?></span></div></div></div>
				</div>
			</div>
			<div class="dxn-card" data-anim>
				<div class="dxn-card-h"><h3><?php esc_html_e( 'Look and behavior', 'dox-newsletter' ); ?></h3></div>
				<div class="dxn-card-b" style="padding-top:4px;padding-bottom:4px">
					<div class="dxn-opt"><div class="tx"><b><?php esc_html_e( 'Style', 'dox-newsletter' ); ?></b><span><?php esc_html_e( 'Dark with the brand glow, or light with a fine border.', 'dox-newsletter' ); ?></span></div>
						<div class="dxn-seg" data-radio="style">
							<button type="button" data-v="dark" class="<?php echo $f['style'] === 'dark' ? 'on' : ''; ?>"><?php esc_html_e( 'Dark', 'dox-newsletter' ); ?></button>
							<button type="button" data-v="light" class="<?php echo $f['style'] === 'light' ? 'on' : ''; ?>"><?php esc_html_e( 'Light', 'dox-newsletter' ); ?></button>
						</div><input type="hidden" name="style" value="<?php echo esc_attr( $f['style'] ); ?>"></div>
					<div class="dxn-opt"><div class="tx"><b><?php esc_html_e( 'Ask for the name', 'dox-newsletter' ); ?></b><span><?php esc_html_e( 'To write «Hi, Ana» in the emails.', 'dox-newsletter' ); ?></span></div><label class="dxn-sw"><input type="checkbox" name="ask_name" value="1" <?php checked( $f['ask_name'] ); ?>><span></span></label></div>
					<div class="dxn-opt"><div class="tx"><b><?php esc_html_e( 'Show how many we are', 'dox-newsletter' ); ?></b><span><?php echo esc_html( sprintf( __( 'Only from 100 subscribers on (now %s).', 'dox-newsletter' ), dxn_num( DXN_Stats::active_count() ) ) ); ?></span></div><label class="dxn-sw"><input type="checkbox" name="show_count" value="1" <?php checked( $f['show_count'] ); ?>><span></span></label></div>
					<div class="dxn-opt"><div class="tx"><b><?php esc_html_e( 'Double confirmation', 'dox-newsletter' ); ?></b><span><?php echo (int) DXN_Settings::get( 'double_optin' ) ? esc_html__( 'On for every form. It avoids fake addresses.', 'dox-newsletter' ) : esc_html__( 'Off. People come in without confirming.', 'dox-newsletter' ); ?></span></div><a class="dxn-btn dxn-btn-link" href="<?php echo esc_url( DXN_Admin::url( 'settings', [ 'tab' => 'confirm' ] ) ); ?>"><?php esc_html_e( 'Settings', 'dox-newsletter' ); ?> <?php echo DXN_Admin::icon( 'arrow' ); // phpcs:ignore ?></a></div>
				</div>
			</div>
		</div>
	</form>
</div>
