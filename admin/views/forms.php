<?php
/**
 * Formularios: a la izquierda cómo se verá en la web (en vivo) y dónde sale;
 * a la derecha los textos y el comportamiento.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$forms = DXO_Forms::all();
$slug  = isset( $_GET['form'] ) ? sanitize_key( $_GET['form'] ) : ''; // phpcs:ignore
$is_new = $slug === 'new';
$f     = $is_new ? array_merge( DXO_Forms::defaults(), [ 'slug' => '', 'name' => __( 'New form', 'dox-orbit' ), 'list_id' => DXO_Lists::default_id() ] ) : ( $forms[ $slug ] ?? reset( $forms ) );
$lists = DXO_Lists::all();
$signups = DXO_Forms::signups( 30 );
$host  = wp_parse_url( home_url(), PHP_URL_HOST );
?>
<div class="dxo-top">
	<div><div class="crumb">Orbit</div><h1><?php esc_html_e( 'Forms', 'dox-orbit' ); ?></h1></div>
	<div class="sp"></div>
	<?php if ( ! $is_new && count( $forms ) > 1 ) : ?>
		<button type="button" class="dxo-btn dxo-btn-danger dxo-btn-sm" data-form-del="<?php echo esc_attr( $f['slug'] ); ?>"><?php echo DXO_Admin::icon( 'trash' ); // phpcs:ignore ?><?php esc_html_e( 'Delete', 'dox-orbit' ); ?></button>
	<?php endif; ?>
	<button type="button" class="dxo-btn dxo-btn-dark" data-form-save><?php esc_html_e( 'Save form', 'dox-orbit' ); ?></button>
</div>
<div class="dxo-page">
	<div class="dxo-toolbar" data-anim>
		<div class="dxo-filters">
			<?php foreach ( $forms as $k => $x ) : ?>
				<a class="<?php echo ! $is_new && $k === $f['slug'] ? 'on' : ''; ?>" href="<?php echo esc_url( DXO_Admin::url( 'forms', [ 'form' => $k ] ) ); ?>">
					<span class="dot" style="background:<?php echo $x['placement'] === 'inline' || $x['enabled'] ? '#16A34A' : '#A1A1AA'; ?>"></span><?php echo esc_html( $x['name'] ); ?>
					<?php if ( ! empty( $signups[ $k ] ) ) : ?><em><?php echo esc_html( sprintf( __( '+%s', 'dox-orbit' ), dxo_num( $signups[ $k ] ) ) ); ?></em><?php endif; ?>
				</a>
			<?php endforeach; ?>
			<a class="<?php echo $is_new ? 'on' : ''; ?>" href="<?php echo esc_url( DXO_Admin::url( 'forms', [ 'form' => 'new' ] ) ); ?>">+ <?php esc_html_e( 'New form', 'dox-orbit' ); ?></a>
		</div>
		<span class="small muted" style="margin-left:auto"><?php esc_html_e( 'The number is who joined through each form in 30 days', 'dox-orbit' ); ?></span>
	</div>

	<form id="dxo-form-edit" class="dxo-row" onsubmit="return false">
		<input type="hidden" name="slug" value="<?php echo esc_attr( $f['slug'] ); ?>">
		<div class="dxo-col" style="width:58%">
			<div class="dxo-site" data-anim>
				<div class="chrome"><i></i><i></i><i></i><span><?php echo esc_html( $host . ( $f['where'] === 'posts' ? '/blog' : '' ) ); ?></span></div>
				<div class="body<?php echo $f['placement'] === 'bar' ? ' is-bar' : ''; ?>" id="dxo-form-preview"><?php echo DXO_Forms::render( $f, $f['placement'] === 'bar' ? 'preview-bar' : 'preview' ); // phpcs:ignore ?></div>
			</div>

			<div class="dxo-card" data-anim>
				<div class="dxo-card-h"><h3><?php esc_html_e( 'Where it appears', 'dox-orbit' ); ?></h3></div>
				<div class="dxo-card-b">
					<div class="dxo-choices">
						<label class="dxo-choice"><input type="radio" name="placement" value="inline" <?php checked( $f['placement'], 'inline' ); ?>>
							<div class="sk"><i style="left:10%;top:18%;width:80%;height:10px"></i><i style="left:10%;top:42%;width:60%;height:6px"></i><i style="left:20%;top:64%;width:60%;height:14px;background:var(--accent)"></i></div>
							<b><?php esc_html_e( 'In a page', 'dox-orbit' ); ?></b><span class="sub"><?php esc_html_e( 'Shortcode or Elementor widget', 'dox-orbit' ); ?></span></label>
						<label class="dxo-choice"><input type="radio" name="placement" value="popup" <?php checked( $f['placement'], 'popup' ); ?>>
							<div class="sk"><i style="left:25%;top:15%;width:50%;height:70%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.12)"></i></div>
							<b><?php esc_html_e( 'Pop-up', 'dox-orbit' ); ?></b><span class="sub"><?php esc_html_e( 'When scrolling or after a few seconds', 'dox-orbit' ); ?></span></label>
						<label class="dxo-choice"><input type="radio" name="placement" value="bar" <?php checked( $f['placement'], 'bar' ); ?>>
							<div class="sk"><i style="left:0;bottom:0;width:100%;height:14px;background:var(--ink)"></i></div>
							<b><?php esc_html_e( 'Bottom bar', 'dox-orbit' ); ?></b><span class="sub"><?php esc_html_e( 'Fixed, can be closed', 'dox-orbit' ); ?></span></label>
					</div>

					<div data-place="inline" style="margin-top:16px" <?php echo $f['placement'] !== 'inline' ? 'hidden' : ''; ?>>
						<?php if ( $is_new ) : ?>
							<p class="small muted" style="margin:0"><?php esc_html_e( 'Save the form to get its shortcode.', 'dox-orbit' ); ?></p>
						<?php else : ?>
							<div class="dxo-code"><span>[dox_orbit form="<?php echo esc_html( $f['slug'] ); ?>"]</span><button type="button" class="dxo-btn dxo-btn-gray dxo-btn-sm" data-copy='[dox_orbit form="<?php echo esc_attr( $f['slug'] ); ?>"]'><?php echo DXO_Admin::icon( 'copy' ); // phpcs:ignore ?><?php esc_html_e( 'Copy', 'dox-orbit' ); ?></button></div>
							<p class="dxo-note"><?php echo DXO_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'Paste it in any page or post. In Elementor, look for the «Dox Orbit» widget.', 'dox-orbit' ); ?></span></p>
						<?php endif; ?>
					</div>
					<div data-place="popup bar" style="margin-top:6px" <?php echo $f['placement'] === 'inline' ? 'hidden' : ''; ?>>
						<div class="dxo-opt">
							<div class="tx"><b><?php esc_html_e( 'Show it on the website', 'dox-orbit' ); ?></b><span><?php esc_html_e( 'Off, it is saved but nobody sees it.', 'dox-orbit' ); ?></span></div>
							<label class="dxo-sw"><input type="checkbox" name="enabled" value="1" <?php checked( $f['enabled'] ); ?>><span></span></label>
						</div>
						<div class="dxo-field">
							<span class="lbl"><?php esc_html_e( 'Pages', 'dox-orbit' ); ?></span>
							<select class="dxo-input" name="where"><option value="posts" <?php selected( $f['where'], 'posts' ); ?>><?php esc_html_e( 'Only blog posts', 'dox-orbit' ); ?></option><option value="all" <?php selected( $f['where'], 'all' ); ?>><?php esc_html_e( 'The whole website', 'dox-orbit' ); ?></option></select>
						</div>
						<div class="dxo-field" data-place="popup" <?php echo $f['placement'] !== 'popup' ? 'hidden' : ''; ?>>
							<span class="lbl"><?php esc_html_e( 'Opens', 'dox-orbit' ); ?></span>
							<div class="dxo-inline-input">
								<select class="dxo-input" name="trigger" style="max-width:220px"><option value="scroll" <?php selected( $f['trigger'], 'scroll' ); ?>><?php esc_html_e( 'When scrolling down', 'dox-orbit' ); ?></option><option value="delay" <?php selected( $f['trigger'], 'delay' ); ?>><?php esc_html_e( 'After some seconds', 'dox-orbit' ); ?></option></select>
								<input class="dxo-input" type="number" name="trigger_value" min="1" max="300" value="<?php echo (int) $f['trigger_value']; ?>" style="max-width:90px">
								<span class="unit" data-unit><?php echo $f['trigger'] === 'scroll' ? '%' : esc_html__( 'seconds', 'dox-orbit' ); ?></span>
							</div>
						</div>
						<p class="dxo-note"><?php echo DXO_Admin::icon( 'info' ); // phpcs:ignore ?><span><?php esc_html_e( 'Whoever closes it does not see it again for 14 days, and whoever subscribes does not see it again.', 'dox-orbit' ); ?></span></p>
					</div>
				</div>
			</div>
		</div>

		<div class="dxo-col" style="width:42%">
			<div class="dxo-card" data-anim>
				<div class="dxo-card-h"><h3><?php esc_html_e( 'Texts', 'dox-orbit' ); ?></h3></div>
				<div class="dxo-card-b tight">
					<div class="dxo-field"><label class="req" for="ff-name"><?php esc_html_e( 'Name', 'dox-orbit' ); ?></label><div><input class="dxo-input" id="ff-name" name="name" value="<?php echo esc_attr( $f['name'] ); ?>"><div class="dxo-hint"><span><?php esc_html_e( 'Only you see it, to tell the forms apart.', 'dox-orbit' ); ?></span></div></div></div>
					<div class="dxo-field"><label for="ff-title"><?php esc_html_e( 'Title', 'dox-orbit' ); ?></label><input class="dxo-input" id="ff-title" name="title" value="<?php echo esc_attr( $f['title'] ); ?>"></div>
					<div class="dxo-field"><label for="ff-text"><?php esc_html_e( 'Text', 'dox-orbit' ); ?></label><textarea class="dxo-input" id="ff-text" name="text" rows="3"><?php echo esc_textarea( $f['text'] ); ?></textarea></div>
					<div class="dxo-field"><label class="req" for="ff-button"><?php esc_html_e( 'Button', 'dox-orbit' ); ?></label><input class="dxo-input" id="ff-button" name="button" value="<?php echo esc_attr( $f['button'] ); ?>"></div>
					<div class="dxo-field"><label for="ff-ph"><?php esc_html_e( 'Email field', 'dox-orbit' ); ?></label><input class="dxo-input" id="ff-ph" name="placeholder" value="<?php echo esc_attr( $f['placeholder'] ); ?>"></div>
					<div class="dxo-field"><label for="ff-list"><?php esc_html_e( 'List', 'dox-orbit' ); ?></label>
						<select class="dxo-input" id="ff-list" name="list_id"><?php foreach ( $lists as $l ) : ?><option value="<?php echo (int) $l['id']; ?>" <?php selected( (int) $f['list_id'], (int) $l['id'] ); ?>><?php echo esc_html( $l['name'] ); ?></option><?php endforeach; ?></select></div>
					<div class="dxo-field"><label for="ff-ok"><?php esc_html_e( 'After sending', 'dox-orbit' ); ?></label><div><textarea class="dxo-input" id="ff-ok" name="success_text" rows="2"><?php echo esc_textarea( $f['success_text'] ); ?></textarea><div class="dxo-hint"><span><?php esc_html_e( 'What they see while the confirmation email is on its way.', 'dox-orbit' ); ?></span></div></div></div>
				</div>
			</div>
			<div class="dxo-card" data-anim>
				<div class="dxo-card-h"><h3><?php esc_html_e( 'Look and behavior', 'dox-orbit' ); ?></h3></div>
				<div class="dxo-card-b" style="padding-top:4px;padding-bottom:4px">
					<div class="dxo-opt"><div class="tx"><b><?php esc_html_e( 'Style', 'dox-orbit' ); ?></b><span><?php esc_html_e( 'Dark with the brand glow, or light with a fine border.', 'dox-orbit' ); ?></span></div>
						<div class="dxo-seg" data-radio="style">
							<button type="button" data-v="dark" class="<?php echo $f['style'] === 'dark' ? 'on' : ''; ?>"><?php esc_html_e( 'Dark', 'dox-orbit' ); ?></button>
							<button type="button" data-v="light" class="<?php echo $f['style'] === 'light' ? 'on' : ''; ?>"><?php esc_html_e( 'Light', 'dox-orbit' ); ?></button>
						</div><input type="hidden" name="style" value="<?php echo esc_attr( $f['style'] ); ?>"></div>
					<div class="dxo-opt"><div class="tx"><b><?php esc_html_e( 'Ask for the name', 'dox-orbit' ); ?></b><span><?php esc_html_e( 'To write «Hi, Ana» in the emails.', 'dox-orbit' ); ?></span></div><label class="dxo-sw"><input type="checkbox" name="ask_name" value="1" <?php checked( $f['ask_name'] ); ?>><span></span></label></div>
					<div class="dxo-opt"><div class="tx"><b><?php esc_html_e( 'Show how many we are', 'dox-orbit' ); ?></b><span><?php echo esc_html( sprintf( __( 'Only from 100 subscribers on (now %s).', 'dox-orbit' ), dxo_num( DXO_Stats::active_count() ) ) ); ?></span></div><label class="dxo-sw"><input type="checkbox" name="show_count" value="1" <?php checked( $f['show_count'] ); ?>><span></span></label></div>
					<div class="dxo-opt"><div class="tx"><b><?php esc_html_e( 'Double confirmation', 'dox-orbit' ); ?></b><span><?php echo (int) DXO_Settings::get( 'double_optin' ) ? esc_html__( 'On for every form. It avoids fake addresses.', 'dox-orbit' ) : esc_html__( 'Off. People come in without confirming.', 'dox-orbit' ); ?></span></div><a class="dxo-btn dxo-btn-link" href="<?php echo esc_url( DXO_Admin::url( 'settings', [ 'tab' => 'confirm' ] ) ); ?>"><?php esc_html_e( 'Settings', 'dox-orbit' ); ?> <?php echo DXO_Admin::icon( 'arrow' ); // phpcs:ignore ?></a></div>
				</div>
			</div>
		</div>
	</form>
</div>
