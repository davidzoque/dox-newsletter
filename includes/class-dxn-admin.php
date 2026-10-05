<?php
/**
 * El panel en wp-admin.
 *
 * Una sola página (Dox Plugins > Newsletter) con su propia navegación:
 * ?page=dox-newsletter&view=dashboard|campaigns|edit|subscribers|forms|report|settings.
 * Las acciones van por admin-ajax (dxn_*), todas con nonce y manage_options.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DXN_Admin {

	const SLUG = 'dox-newsletter';
	const CAP  = 'manage_options';

	const VIEWS = [ 'dashboard', 'campaigns', 'edit', 'subscribers', 'forms', 'report', 'settings' ];

	public static function init() {
		add_action( 'dox_core_register', [ __CLASS__, 'register_in_core' ] );
		add_action( 'admin_menu', [ __CLASS__, 'fallback_menu' ], 20 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_filter( 'admin_body_class', [ __CLASS__, 'body_class' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( DXN_FILE ), [ __CLASS__, 'action_links' ] );

		$ajax = [
			'save_campaign', 'preview', 'send_test', 'launch', 'unschedule', 'pause', 'resume', 'cancel',
			'delete_campaign', 'duplicate', 'resend', 'welcome_toggle', 'status',
			'save_subscriber', 'delete_subscriber', 'subscriber_status', 'import', 'resend_confirm',
			'save_list', 'delete_list', 'save_form', 'delete_form', 'form_preview',
			'save_settings', 'settings_test', 'posts',
		];
		foreach ( $ajax as $a ) {
			add_action( 'wp_ajax_dxn_' . $a, [ __CLASS__, 'ajax_' . $a ] );
		}
		add_action( 'admin_post_dxn_export', [ __CLASS__, 'export' ] );
		add_action( 'admin_post_dxn_new_campaign', [ __CLASS__, 'new_campaign' ] );
	}

	// ═══ Menú ═══════════════════════════════════════════════════════════════

	public static function register_in_core( $core ) {
		$core->register_plugin( [
			'slug'    => self::SLUG,
			'name'    => 'Newsletter',
			'version' => DXN_VERSION,
			'summary' => __( 'Newsletters and email campaigns from your own WordPress.', 'dox-newsletter' ),
			'page'    => [
				'menu_title' => 'Newsletter',
				'page_title' => 'Dox Newsletter',
				'callback'   => [ __CLASS__, 'render' ],
			],
		] );
	}

	/** Sin dox-core (carpeta a medias), el plugin cuelga su propio menú. */
	public static function fallback_menu() {
		if ( function_exists( 'dox_core' ) ) return;
		add_menu_page( 'Dox Newsletter', 'Newsletter', self::CAP, self::SLUG, [ __CLASS__, 'render' ], 'dashicons-email-alt', 58.95 );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Open', 'dox-newsletter' ) . '</a>' );
		return $links;
	}

	public static function url( $view = 'dashboard', array $args = [] ) {
		$base = function_exists( 'dox_core' ) ? 'admin.php' : 'admin.php';
		return add_query_arg( array_merge( [ 'page' => self::SLUG, 'view' => $view ], $args ), admin_url( $base ) );
	}

	private static function is_our_page() {
		return isset( $_GET['page'] ) && $_GET['page'] === self::SLUG; // phpcs:ignore
	}

	public static function body_class( $classes ) {
		return self::is_our_page() ? $classes . ' dxn-screen' : $classes;
	}

	public static function current_view() {
		$v = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'dashboard'; // phpcs:ignore
		return in_array( $v, self::VIEWS, true ) ? $v : 'dashboard';
	}

	// ═══ Recursos ═══════════════════════════════════════════════════════════

	public static function assets() {
		if ( ! self::is_our_page() ) return;
		wp_enqueue_style( 'dxn-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', [], null );
		wp_enqueue_style( 'dxn-admin', DXN_URL . 'assets/admin.css', [], dxn_asset_ver( 'admin.css' ) );
		wp_enqueue_script( 'dxn-admin', DXN_URL . 'assets/admin.js', [], dxn_asset_ver( 'admin.js' ), true );

		$view = self::current_view();
		if ( in_array( $view, [ 'edit', 'settings' ], true ) ) wp_enqueue_media();
		if ( $view === 'forms' ) {
			wp_enqueue_style( 'dxn-public', DXN_URL . 'assets/public.css', [], dxn_asset_ver( 'public.css' ) );
		}

		wp_localize_script( 'dxn-admin', 'DXN', [
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'dxn' ),
			'view'  => $view,
			'rate'  => (int) DXN_Settings::get( 'rate_per_hour' ),
			'urls'  => [
				'campaigns' => self::url( 'campaigns' ),
				'edit'      => self::url( 'edit' ),
				'report'    => self::url( 'report' ),
				'dashboard' => self::url( 'dashboard' ),
			],
			'i18n'  => [
				'saved'        => __( 'Saved', 'dox-newsletter' ),
				'saving'       => __( 'Saving…', 'dox-newsletter' ),
				'unsaved'      => __( 'Unsaved changes', 'dox-newsletter' ),
				'error'        => __( 'Something went wrong. Try again.', 'dox-newsletter' ),
				'sent_test'    => __( 'Test sent to %s', 'dox-newsletter' ),
				'copied'       => __( 'Copied', 'dox-newsletter' ),
				'confirm'      => __( 'Confirm', 'dox-newsletter' ),
				'cancel'       => __( 'Cancel', 'dox-newsletter' ),
				'delete'       => __( 'Delete', 'dox-newsletter' ),
				'choose_image' => __( 'Choose an image', 'dox-newsletter' ),
				'use_image'    => __( 'Use this image', 'dox-newsletter' ),
				'leave'        => __( 'There are unsaved changes.', 'dox-newsletter' ),
				'chars'        => __( '%d characters', 'dox-newsletter' ),
				'block'        => [
					'heading' => __( 'Title', 'dox-newsletter' ),
					'text'    => __( 'Text', 'dox-newsletter' ),
					'image'   => __( 'Image', 'dox-newsletter' ),
					'button'  => __( 'Button', 'dox-newsletter' ),
					'post'    => __( 'Blog post', 'dox-newsletter' ),
					'divider' => __( 'Divider', 'dox-newsletter' ),
					'spacer'  => __( 'Space', 'dox-newsletter' ),
				],
				'read_more'    => __( 'Read more', 'dox-newsletter' ),
				'no_posts'     => __( 'No posts found', 'dox-newsletter' ),
				'empty_block'  => __( '(empty)', 'dox-newsletter' ),
			],
		] );
	}

	// ═══ Pintar ═════════════════════════════════════════════════════════════

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) return;
		$view = self::current_view();
		include DXN_PATH . 'admin/views/layout.php';
	}

	/** Un icono de línea (los de la maqueta), por nombre. */
	public static function icon( $name ) {
		$p = [
			'pulse'    => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
			'send'     => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
			'pen'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
			'users'    => '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M17 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/>',
			'form'     => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7 9h10M7 13h6"/>',
			'chart'    => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
			'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
			'plus'     => '<path d="M12 5v14M5 12h14"/>',
			'download' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/>',
			'upload'   => '<path d="M12 15V3M7 8l5-5 5 5"/><path d="M5 21h14"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'pause'    => '<path d="M8 5v14M16 5v14"/>',
			'play'     => '<path d="M7 4v16l13-8z"/>',
			'more'     => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
			'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
			'check'    => '<path d="m5 12 5 5 9-10"/>',
			'warn'     => '<path d="M12 8v5M12 17h.01"/><circle cx="12" cy="12" r="9"/>',
			'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'cloud'    => '<path d="M17.5 19a4.5 4.5 0 1 0-1.4-8.8A6 6 0 1 0 6 16.5"/><path d="M6 19h11.5"/>',
			'refresh'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
			'trash'    => '<path d="M4 7h16M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13M9 7V4h6v3"/>',
			'copy'     => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
			'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
			'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
			'grip'     => '<circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/>',
			'up'       => '<path d="m6 15 6-6 6 6"/>',
			'down'     => '<path d="m6 9 6 6 6-6"/>',
			'heading'  => '<path d="M4 7V5h16v2M9 19h6M12 5v14"/>',
			'text'     => '<path d="M4 6h16M4 11h16M4 16h10"/>',
			'image'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 8"/>',
			'button'   => '<rect x="4" y="8" width="16" height="8" rx="4"/>',
			'post'     => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
			'divider'  => '<path d="M4 12h16"/>',
			'spacer'   => '<path d="M12 4v16M8 8l4-4 4 4M8 16l4 4 4-4"/>',
		];
		return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}

	/**
	 * Gráfica de área en SVG, pintada en el servidor: sale aunque el JS no cargue.
	 *
	 * @param array  $values números
	 * @param array  $labels [ índice => texto ] para el eje de abajo
	 * @param string $color  color de la línea
	 * @param bool   $zero   el eje empieza en 0 (si no, se ajusta a los datos)
	 */
	public static function area_chart( array $values, array $labels, $color, $zero = false ) {
		$values = array_values( $values );
		$n      = count( $values );
		if ( $n < 2 ) return '';
		$W = 700; $H = 190; $p = 8;
		$hi  = max( $values );
		$lo  = min( $values );
		$pad = max( 1, ( $hi - $lo ) * .25 );
		$max = $hi + $pad;
		$min = $zero ? 0 : max( 0, $lo - $pad );
		if ( $max <= $min ) $max = $min + 1;
		$x = function ( $i ) use ( $W, $p, $n ) { return $p + $i * ( $W - 2 * $p ) / ( $n - 1 ); };
		$y = function ( $v ) use ( $H, $min, $max ) { return $H - 8 - ( $v - $min ) / ( $max - $min ) * ( $H - 24 ); };

		$d = '';
		foreach ( $values as $i => $v ) $d .= ( $i ? 'L' : 'M' ) . round( $x( $i ), 1 ) . ' ' . round( $y( $v ), 1 ) . ' ';
		$id   = 'g' . wp_rand( 1000, 99999 );
		$grid = '';
		for ( $k = 0; $k < 4; $k++ ) {
			$yy    = 16 + $k * ( $H - 40 ) / 3;
			$grid .= '<line x1="0" x2="' . $W . '" y1="' . $yy . '" y2="' . $yy . '" stroke="#F4F4F5"/>';
		}
		// Las fechas van en HTML debajo y no dentro del SVG: el SVG se estira a lo ancho
		// (preserveAspectRatio none) y el texto saldría deformado en el móvil.
		$axis = '';
		foreach ( $labels as $i => $l ) {
			$pos   = round( $x( $i ) / $W * 100, 2 );
			$shift = $i === 0 ? '0' : ( $i === $n - 1 ? '-100%' : '-50%' );
			$axis .= '<span style="left:' . $pos . '%;transform:translateX(' . $shift . ')">' . esc_html( $l ) . '</span>';
		}
		$last = $n - 1;
		return '<svg class="dxn-chart" viewBox="0 0 ' . $W . ' ' . $H . '" preserveAspectRatio="none" role="img">'
			. '<defs><linearGradient id="' . $id . '" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="' . esc_attr( $color ) . '" stop-opacity=".22"/><stop offset="1" stop-color="' . esc_attr( $color ) . '" stop-opacity="0"/></linearGradient></defs>'
			. $grid
			. '<path d="' . $d . 'L' . round( $x( $last ), 1 ) . ' ' . ( $H - 8 ) . ' L' . round( $x( 0 ), 1 ) . ' ' . ( $H - 8 ) . 'Z" fill="url(#' . $id . ')"/>'
			. '<path class="dxn-line" d="' . trim( $d ) . '" fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" pathLength="1"/>'
			. '<circle cx="' . round( $x( $last ), 1 ) . '" cy="' . round( $y( $values[ $last ] ), 1 ) . '" r="4.5" fill="#fff" stroke="' . esc_attr( $color ) . '" stroke-width="2.4" vector-effect="non-scaling-stroke"/>'
			. '</svg><div class="dxn-axis">' . $axis . '</div>';
	}

	/** Lo que necesita la franja de estado para ponerse al día. */
	public static function status_payload() {
		$st = DXN_Sender::status();
		if ( ! $st ) return [ 'active' => false ];
		return [
			'active' => true,
			'id'     => $st['id'],
			'paused' => $st['status'] === 'paused',
			'title'  => sprintf( $st['status'] === 'paused' ? __( 'Paused «%s»', 'dox-newsletter' ) : __( 'Sending «%s»', 'dox-newsletter' ), $st['subject'] ),
			'count'  => sprintf( __( '%1$s of %2$s', 'dox-newsletter' ), dxn_num( $st['done'] ), dxn_num( $st['total'] ) ),
			'pct'    => $st['total'] ? round( $st['done'] * 100 / $st['total'] ) : 0,
			'meta'   => self::status_meta( $st ),
			'label'  => $st['status'] === 'paused' ? __( 'Resume', 'dox-newsletter' ) : __( 'Pause', 'dox-newsletter' ),
		];
	}

	/** "Sale a 150 por hora para dejar sitio a los correos de la web · termina hoy hacia las 18:40" */
	public static function status_meta( array $st ) {
		if ( $st['status'] === 'paused' ) {
			return sprintf( __( '%s left. Nothing goes out until you resume it.', 'dox-newsletter' ), dxn_num( $st['left'] ) );
		}
		$rate = sprintf( __( 'Going out at %s per hour to leave room for the website emails', 'dox-newsletter' ), dxn_num( $st['rate'] ) );
		if ( ! $st['left'] ) return $rate;
		$when = $st['eta_day'] === 'today'
			? sprintf( __( 'finishes today around %s', 'dox-newsletter' ), $st['eta'] )
			: sprintf( __( 'finishes %1$s around %2$s', 'dox-newsletter' ), $st['eta_day'], $st['eta'] );
		return $rate . ' · ' . $when;
	}

	/** La pastilla de estado de una campaña. */
	public static function status_pill( array $c, $st = null ) {
		$map = [
			'draft'     => [ 'p-gray', __( 'Draft', 'dox-newsletter' ) ],
			'scheduled' => [ 'p-info', __( 'Scheduled', 'dox-newsletter' ) ],
			'sending'   => [ 'p-acc', __( 'Sending', 'dox-newsletter' ) ],
			'paused'    => [ 'p-warn', __( 'Paused', 'dox-newsletter' ) ],
			'sent'      => [ 'p-ok', __( 'Sent', 'dox-newsletter' ) ],
			'cancelled' => [ 'p-gray', __( 'Cancelled', 'dox-newsletter' ) ],
			'active'    => [ 'p-ok', __( 'On', 'dox-newsletter' ) ],
			'inactive'  => [ 'p-gray', __( 'Off', 'dox-newsletter' ) ],
		];
		$m     = $map[ $c['status'] ] ?? [ 'p-gray', $c['status'] ];
		$label = $m[1];
		if ( $c['status'] === 'sending' && $st && $st['total'] ) {
			$label .= ' ' . round( ( $st['sent'] + $st['failed'] ) * 100 / $st['total'] ) . ' %';
		}
		return '<span class="dxn-pill ' . $m[0] . '"><span class="dot"></span>' . esc_html( $label ) . '</span>';
	}

	// ═══ AJAX: utilidades ═══════════════════════════════════════════════════

	private static function guard() {
		if ( ! current_user_can( self::CAP ) || ! check_ajax_referer( 'dxn', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Your session expired. Reload the page.', 'dox-newsletter' ) ], 403 );
		}
	}

	private static function in( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore
	}

	private static function fail( $message, $extra = [] ) {
		wp_send_json_error( array_merge( [ 'message' => $message ], $extra ), 400 );
	}

	// ═══ AJAX: campañas ═════════════════════════════════════════════════════

	public static function new_campaign() {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'dxn_new' ) ) wp_die( 'Forbidden' );
		$id = DXN_Campaigns::create();
		wp_safe_redirect( self::url( 'edit', [ 'id' => $id ] ) );
		exit;
	}

	public static function ajax_save_campaign() {
		self::guard();
		$data = [
			'subject'   => self::in( 'subject' ),
			'preheader' => self::in( 'preheader' ),
			'blocks'    => json_decode( (string) self::in( 'blocks', '[]' ), true ) ?: [],
		];
		if ( isset( $_POST['lists'] ) ) $data['lists'] = (array) self::in( 'lists', [] );
		$c = DXN_Campaigns::save( (int) self::in( 'id' ), $data );
		if ( is_wp_error( $c ) ) self::fail( $c->get_error_message() );
		wp_send_json_success( [ 'audience' => count( DXN_Campaigns::audience_ids( $c ) ), 'problems' => DXN_Campaigns::problems( $c ) ] );
	}

	/** La vista previa: el mismo HTML que se envía, con los datos de quien la mira. */
	public static function ajax_preview() {
		self::guard();
		$user   = wp_get_current_user();
		$blocks = DXN_Renderer::normalize_blocks( json_decode( (string) self::in( 'blocks', '[]' ), true ) ?: [] );
		$render = DXN_Renderer::render_email( [
			'blocks'          => $blocks,
			'brand'           => DXN_Settings::brand(),
			'subject'         => (string) self::in( 'subject' ),
			'preheader'       => (string) self::in( 'preheader' ),
			'fields'          => [ 'first_name' => $user->first_name ?: 'Ana', 'last_name' => $user->last_name, 'email' => $user->user_email ],
			'unsubscribe_url' => '#',
			'view_url'        => '#',
			'strings'         => DXN_Public::email_strings(),
		] );
		wp_send_json_success( [ 'html' => $render['html'] ] );
	}

	public static function ajax_send_test() {
		self::guard();
		$c  = DXN_Campaigns::get( (int) self::in( 'id' ) );
		$to = sanitize_email( self::in( 'to' ) );
		if ( ! $c ) self::fail( __( 'That campaign no longer exists.', 'dox-newsletter' ) );
		if ( ! is_email( $to ) ) self::fail( __( 'Write a valid email for the test.', 'dox-newsletter' ) );
		update_user_meta( get_current_user_id(), 'dxn_test_to', $to );
		$err = DXN_Sender::send_test( $c, $to );
		if ( $err !== null ) self::fail( $err );
		global $wpdb;
		$wpdb->update( DXN_Install::table( 'campaigns' ), [ 'test_sent_at' => dxn_now() ], [ 'id' => $c['id'] ] );
		wp_send_json_success();
	}

	public static function ajax_launch() {
		self::guard();
		$when = (string) self::in( 'when' );
		$utc  = null;
		if ( $when !== '' ) {
			// Llega en la hora del sitio (input datetime-local): se pasa a UTC.
			try {
				$dt  = new DateTime( $when, wp_timezone() );
				$utc = $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			} catch ( Exception $e ) {
				self::fail( __( 'That date is not valid.', 'dox-newsletter' ) );
			}
			if ( strtotime( $utc . ' UTC' ) < time() ) self::fail( __( 'That time has already passed.', 'dox-newsletter' ) );
		}
		$c = DXN_Campaigns::launch( (int) self::in( 'id' ), $utc );
		if ( is_wp_error( $c ) ) self::fail( $c->get_error_message(), [ 'problems' => $c->get_error_data() ] );
		wp_send_json_success( [ 'status' => $c['status'], 'redirect' => $c['status'] === 'scheduled' ? self::url( 'campaigns' ) : self::url( 'dashboard' ) ] );
	}

	public static function ajax_unschedule() {
		self::guard();
		DXN_Campaigns::unschedule( (int) self::in( 'id' ) );
		wp_send_json_success();
	}

	public static function ajax_pause() {
		self::guard();
		global $wpdb;
		$wpdb->update( DXN_Install::table( 'campaigns' ), [ 'status' => 'paused' ], [ 'id' => (int) self::in( 'id' ), 'status' => 'sending' ] );
		wp_send_json_success( self::status_payload() );
	}

	public static function ajax_resume() {
		self::guard();
		global $wpdb;
		$wpdb->update( DXN_Install::table( 'campaigns' ), [ 'status' => 'sending' ], [ 'id' => (int) self::in( 'id' ), 'status' => 'paused' ] );
		DXN_Sender::kick();
		wp_send_json_success( self::status_payload() );
	}

	public static function ajax_cancel() {
		self::guard();
		DXN_Campaigns::cancel( (int) self::in( 'id' ) );
		wp_send_json_success();
	}

	public static function ajax_delete_campaign() {
		self::guard();
		if ( ! DXN_Campaigns::delete( (int) self::in( 'id' ) ) ) self::fail( __( 'This campaign cannot be deleted while it is sending.', 'dox-newsletter' ) );
		wp_send_json_success();
	}

	public static function ajax_duplicate() {
		self::guard();
		$id = DXN_Campaigns::duplicate( (int) self::in( 'id' ) );
		wp_send_json_success( [ 'redirect' => self::url( 'edit', [ 'id' => $id ] ) ] );
	}

	public static function ajax_resend() {
		self::guard();
		$id = DXN_Campaigns::resend( (int) self::in( 'id' ), (string) self::in( 'who', 'not_opened' ) );
		if ( ! $id ) self::fail( __( 'Only sent campaigns can be resent.', 'dox-newsletter' ) );
		wp_send_json_success( [ 'redirect' => self::url( 'edit', [ 'id' => $id ] ) ] );
	}

	public static function ajax_welcome_toggle() {
		self::guard();
		$w  = DXN_Campaigns::welcome();
		$on = (int) self::in( 'on' ) === 1;
		if ( $on ) {
			$p = DXN_Campaigns::problems( $w );
			if ( $p ) self::fail( implode( "\n", $p ) );
		}
		DXN_Campaigns::set_status( $w['id'], $on ? 'active' : 'inactive' );
		global $wpdb;
		$wpdb->update( DXN_Install::table( 'campaigns' ), [ 'finished_at' => null ], [ 'id' => $w['id'] ] );
		wp_send_json_success();
	}

	/**
	 * El panel pregunta cada 20 segundos cómo va el envío. De paso, si toca,
	 * manda una tanda: con el panel abierto el envío no depende de que alguien
	 * visite la web para que corra el cron.
	 */
	public static function ajax_status() {
		self::guard();
		DXN_Sender::run();
		wp_send_json_success( self::status_payload() );
	}

	// ═══ AJAX: suscriptores y listas ════════════════════════════════════════

	public static function ajax_save_subscriber() {
		self::guard();
		global $wpdb;
		$id    = (int) self::in( 'id' );
		$email = sanitize_email( self::in( 'email' ) );
		$first = sanitize_text_field( self::in( 'first_name' ) );
		$last  = sanitize_text_field( self::in( 'last_name' ) );
		$lists = array_map( 'intval', (array) self::in( 'lists', [] ) );
		if ( ! is_email( $email ) ) self::fail( __( 'That email does not look right.', 'dox-newsletter' ) );

		$other = DXN_Subscribers::get_by_email( $email );
		if ( $other && (int) $other['id'] !== $id ) self::fail( __( 'There is already a subscriber with that email.', 'dox-newsletter' ) );

		if ( $id ) {
			$wpdb->update( DXN_Install::table( 'subscribers' ), [ 'email' => strtolower( $email ), 'first_name' => $first, 'last_name' => $last ], [ 'id' => $id ] );
		} else {
			if ( ! self::in( 'consent' ) ) self::fail( __( 'Confirm that this person agreed to receive your emails.', 'dox-newsletter' ) );
			$id = DXN_Subscribers::add_imported( strtolower( $email ), $first, $last, $lists, 'manual' );
		}
		DXN_Subscribers::set_lists( $id, $lists ?: [ DXN_Lists::default_id() ] );
		wp_send_json_success( [ 'id' => $id ] );
	}

	public static function ajax_delete_subscriber() {
		self::guard();
		DXN_Subscribers::delete( (int) self::in( 'id' ) );
		wp_send_json_success();
	}

	public static function ajax_subscriber_status() {
		self::guard();
		$status = sanitize_key( self::in( 'status' ) );
		// A mano solo se puede dar de baja o reactivar a quien rebotó: volver a
		// activar a alguien que se dio de baja él mismo no es nuestro.
		$sub = DXN_Subscribers::get( (int) self::in( 'id' ) );
		if ( ! $sub ) self::fail( __( 'That subscriber no longer exists.', 'dox-newsletter' ) );
		if ( $status === 'active' && $sub['status'] === 'unsubscribed' ) {
			self::fail( __( 'This person unsubscribed. Only they can subscribe again, from a form.', 'dox-newsletter' ) );
		}
		DXN_Subscribers::set_status( (int) $sub['id'], $status );
		wp_send_json_success();
	}

	public static function ajax_resend_confirm() {
		self::guard();
		$sub = DXN_Subscribers::get( (int) self::in( 'id' ) );
		if ( ! $sub || $sub['status'] !== 'pending' ) self::fail( __( 'This person is not waiting for confirmation.', 'dox-newsletter' ) );
		DXN_Subscribers::send_confirmation( $sub, true ) ? wp_send_json_success() : self::fail( __( 'The email could not be sent.', 'dox-newsletter' ) );
	}

	public static function ajax_import() {
		self::guard();
		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) self::fail( __( 'Choose a CSV file.', 'dox-newsletter' ) );
		if ( ! self::in( 'consent' ) ) self::fail( __( 'Confirm that these people agreed to receive your emails.', 'dox-newsletter' ) );
		$lists = array_map( 'intval', (array) self::in( 'lists', [] ) ) ?: [ DXN_Lists::default_id() ];
		$res   = DXN_Subscribers::import_csv( $_FILES['file']['tmp_name'], $lists );
		if ( $res['invalid'] === -1 ) self::fail( __( 'No email column was found in the file.', 'dox-newsletter' ) );
		wp_send_json_success( $res );
	}

	public static function ajax_save_list() {
		self::guard();
		$name = sanitize_text_field( self::in( 'name' ) );
		if ( $name === '' ) self::fail( __( 'Give the list a name.', 'dox-newsletter' ) );
		$id = (int) self::in( 'id' );
		$id ? DXN_Lists::rename( $id, $name ) : ( $id = DXN_Lists::create( $name ) );
		wp_send_json_success( [ 'id' => $id ] );
	}

	public static function ajax_delete_list() {
		self::guard();
		if ( count( DXN_Lists::all() ) < 2 ) self::fail( __( 'There must be at least one list.', 'dox-newsletter' ) );
		DXN_Lists::delete( (int) self::in( 'id' ) );
		wp_send_json_success();
	}

	/** CSV con los suscriptores del filtro actual. BOM para que Excel lea las tildes. */
	public static function export() {
		if ( ! current_user_can( self::CAP ) || ! check_admin_referer( 'dxn_export' ) ) wp_die( 'Forbidden' );
		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'active';
		$list   = isset( $_GET['list'] ) ? (int) $_GET['list'] : 0;
		$res    = DXN_Subscribers::query( [ 'status' => $status, 'list_id' => $list, 'per_page' => 200, 'page' => 1 ] );
		$pages  = (int) ceil( $res['total'] / 200 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="subscribers-' . $status . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, [ 'email', 'first_name', 'last_name', 'status', 'lists', 'source', 'created_at' ] );
		for ( $p = 1; $p <= max( 1, $pages ); $p++ ) {
			if ( $p > 1 ) $res = DXN_Subscribers::query( [ 'status' => $status, 'list_id' => $list, 'per_page' => 200, 'page' => $p ] );
			foreach ( $res['rows'] as $r ) {
				fputcsv( $out, [ $r['email'], $r['first_name'], $r['last_name'], $r['status'], implode( ' | ', $r['lists'] ), $r['source'], dxn_date( $r['created_at'], 'Y-m-d H:i' ) ] );
			}
		}
		fclose( $out );
		exit;
	}

	// ═══ AJAX: formularios ══════════════════════════════════════════════════

	private static function form_from_post() {
		$keys = array_keys( DXN_Forms::defaults() );
		$f    = [];
		foreach ( $keys as $k ) {
			if ( isset( $_POST[ $k ] ) ) $f[ $k ] = self::in( $k ); // phpcs:ignore
		}
		foreach ( [ 'ask_name', 'show_count', 'enabled' ] as $b ) {
			$f[ $b ] = ! empty( $_POST[ $b ] ) ? 1 : 0; // phpcs:ignore
		}
		return $f;
	}

	public static function ajax_save_form() {
		self::guard();
		$f = DXN_Forms::save( self::form_from_post() );
		wp_send_json_success( [ 'slug' => $f['slug'], 'redirect' => self::url( 'forms', [ 'form' => $f['slug'] ] ) ] );
	}

	public static function ajax_delete_form() {
		self::guard();
		if ( ! DXN_Forms::delete( sanitize_key( self::in( 'slug' ) ) ) ) self::fail( __( 'There must be at least one form.', 'dox-newsletter' ) );
		wp_send_json_success( [ 'redirect' => self::url( 'forms' ) ] );
	}

	/** El formulario tal y como saldrá en la web, sin guardar. */
	public static function ajax_form_preview() {
		self::guard();
		$f = wp_parse_args( self::form_from_post(), DXN_Forms::defaults() );
		$f = array_map( function ( $v ) { return is_string( $v ) ? sanitize_text_field( $v ) : $v; }, $f );
		$f['slug'] = sanitize_key( $f['slug'] ) ?: 'preview';
		wp_send_json_success( [ 'html' => DXN_Forms::render( $f, $f['placement'] === 'bar' ? 'preview-bar' : 'preview' ) ] );
	}

	// ═══ AJAX: ajustes ══════════════════════════════════════════════════════

	public static function ajax_save_settings() {
		self::guard();
		$s   = DXN_Settings::all();
		$new = [];
		foreach ( [ 'from_name', 'company', 'address', 'confirm_subject', 'confirm_heading', 'confirm_button', 'confirmed_title', 'footer_why', 'ses_host', 'ses_user' ] as $k ) {
			if ( isset( $_POST[ $k ] ) ) $new[ $k ] = sanitize_text_field( self::in( $k ) );
		}
		foreach ( [ 'confirm_text', 'confirmed_text' ] as $k ) {
			if ( isset( $_POST[ $k ] ) ) $new[ $k ] = sanitize_textarea_field( self::in( $k ) );
		}
		foreach ( [ 'from_email', 'reply_to' ] as $k ) {
			if ( ! isset( $_POST[ $k ] ) ) continue;
			$v = sanitize_email( self::in( $k ) );
			if ( $k === 'from_email' && ! is_email( $v ) ) self::fail( __( 'The sender email is not valid.', 'dox-newsletter' ), [ 'field' => $k ] );
			if ( $k === 'reply_to' && self::in( $k ) !== '' && ! is_email( $v ) ) self::fail( __( 'The reply-to email is not valid.', 'dox-newsletter' ), [ 'field' => $k ] );
			$new[ $k ] = $v;
		}
		if ( isset( $_POST['accent'] ) ) $new['accent'] = DXN_Renderer::color( self::in( 'accent' ) );
		if ( isset( $_POST['logo_id'] ) ) $new['logo_id'] = (int) self::in( 'logo_id' );
		foreach ( [ 'rate_per_hour' => [ 10, 2000 ], 'server_limit' => [ 10, 100000 ], 'ses_port' => [ 1, 65535 ] ] as $k => $r ) {
			if ( isset( $_POST[ $k ] ) ) $new[ $k ] = max( $r[0], min( $r[1], (int) self::in( $k ) ) );
		}
		foreach ( [ 'track_opens', 'track_clicks', 'double_optin' ] as $k ) {
			if ( isset( $_POST[ $k ] ) ) $new[ $k ] = (int) self::in( $k ) ? 1 : 0;
		}
		if ( isset( $_POST['transport'] ) ) $new['transport'] = self::in( 'transport' ) === 'ses' ? 'ses' : 'wp';

		// La contraseña de SES: vacía quiere decir "no la cambies". Nunca vuelve a la pantalla.
		$pass = (string) self::in( 'ses_pass' );
		if ( $pass !== '' ) $new['ses_pass'] = DXN_Settings::encrypt( $pass );

		// Con SES elegido, se comprueba contra Amazon antes de guardar: un usuario
		// o una contraseña mal puestos harían fallar a todos los de una campaña.
		$merged = array_merge( $s, $new );
		if ( $merged['transport'] === 'ses' ) {
			$err = DXN_Mailer::check_ses( DXN_Mailer::ses_config( $merged ) );
			if ( $err !== null ) self::fail( sprintf( __( 'Amazon SES did not accept the connection: %s', 'dox-newsletter' ), $err ), [ 'field' => 'ses_user' ] );
		}

		DXN_Settings::update( $new );
		wp_send_json_success();
	}

	/** Correo de prueba con los ajustes del formulario, aunque no estén guardados. */
	public static function ajax_settings_test() {
		self::guard();
		$to = sanitize_email( self::in( 'to' ) );
		if ( ! is_email( $to ) ) self::fail( __( 'Write a valid email for the test.', 'dox-newsletter' ) );
		update_user_meta( get_current_user_id(), 'dxn_test_to', $to );
		$err = DXN_Subscribers::send_confirmation( [
			'id' => 0, 'email' => $to, 'first_name' => wp_get_current_user()->first_name, 'token' => str_repeat( '0', 32 ), 'confirm_sent_at' => null,
		], true ) ? null : __( 'The email could not be sent. Check the sending settings.', 'dox-newsletter' );
		if ( $err ) self::fail( $err );
		wp_send_json_success();
	}

	/** Buscador de entradas para el bloque "Entrada del blog". */
	public static function ajax_posts() {
		self::guard();
		$q = new WP_Query( [
			's'              => sanitize_text_field( self::in( 'q' ) ),
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 8,
			'no_found_rows'  => true,
			'lang'           => '', // Polylang: todas las lenguas
		] );
		$out = [];
		foreach ( $q->posts as $p ) {
			$excerpt = has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 28, '…' );
			$out[]   = [
				'post_id' => $p->ID,
				'title'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
				'excerpt' => html_entity_decode( $excerpt, ENT_QUOTES, 'UTF-8' ),
				'image'   => (string) get_the_post_thumbnail_url( $p, 'large' ),
				'url'     => get_permalink( $p ),
				'date'    => get_the_date( '', $p ),
			];
		}
		wp_send_json_success( $out );
	}
}
