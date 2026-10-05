<?php
/**
 * El envío por tandas.
 *
 * Cada minuto (WP-Cron) sale una tanda, sin pasar del tope por hora de Ajustes.
 * El tope se cuenta sobre la última hora real (los enviados en los últimos 60
 * minutos), no por pasadas: aunque el cron se salte minutos porque nadie
 * visitó la web, nunca salen más de los que tocan.
 *
 * Por qué hay tope: en un hosting compartido el servidor deja salir un número
 * de correos por hora por web (en el de Dox Studio, 200). Lo que no use el boletín queda
 * libre para pedidos, contraseñas y formularios.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DXN_Sender {

	const HOOK = 'dxn_send';

	/** Correos como máximo por pasada, aunque el tope por hora deje más. */
	const BATCH = 25;

	/** Segundos como máximo por pasada, para no pisar la siguiente ni agotar PHP. */
	const BUDGET = 20;

	/** Un candado más viejo que esto se da por colgado. */
	const LOCK_SECONDS = 120;

	public static function init() {
		add_filter( 'cron_schedules', [ __CLASS__, 'schedules' ] );
		add_action( self::HOOK, [ __CLASS__, 'run' ] );
		add_action( 'dxn_send_now', [ __CLASS__, 'run' ] );
		add_action( 'init', [ __CLASS__, 'schedule' ] );
	}

	public static function schedules( $s ) {
		$s['dxn_minute'] = [ 'interval' => 60, 'display' => 'Dox Newsletter (every minute)' ];
		return $s;
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 30, 'dxn_minute', self::HOOK );
		}
	}

	/**
	 * Hay algo nuevo que mandar (una campaña lanzada, una bienvenida): que el
	 * cron pase cuanto antes en vez de esperar al minuto.
	 */
	public static function kick() {
		if ( ! wp_next_scheduled( 'dxn_send_now' ) ) {
			wp_schedule_single_event( time(), 'dxn_send_now' );
		}
		if ( function_exists( 'spawn_cron' ) ) spawn_cron();
	}

	// ═══ Una pasada ═════════════════════════════════════════════════════════

	/** @return int cuántos correos salieron en esta pasada */
	public static function run() {
		global $wpdb;
		$c = DXN_Install::table( 'campaigns' );

		$pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $c WHERE status IN ('scheduled','sending') OR (type = 'welcome' AND status = 'active')" );
		if ( ! $pending ) return 0;
		if ( ! self::lock() ) return 0;

		$sent = 0;
		try {
			// Las programadas cuya hora ya llegó.
			$due = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $c WHERE status = 'scheduled' AND scheduled_at <= %s", dxn_now() ) );
			foreach ( $due as $id ) {
				$camp = DXN_Campaigns::get( $id );
				if ( $camp ) DXN_Campaigns::start( $camp );
			}

			$allowance = min( self::BATCH, self::allowance() );
			$started   = time();

			// La bienvenida primero: quien se acaba de apuntar la está esperando.
			$ids = $wpdb->get_col( "SELECT id FROM $c WHERE (type = 'welcome' AND status = 'active') OR status = 'sending' ORDER BY (type = 'welcome') DESC, started_at ASC" );

			foreach ( $ids as $id ) {
				$camp  = DXN_Campaigns::get( $id );
				$links = DXN_Campaigns::link_map( $id );

				while ( $allowance > 0 && time() - $started < self::BUDGET ) {
					$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . DXN_Install::table( 'recipients' ) . " WHERE campaign_id = %d AND status = 'queued' ORDER BY id ASC LIMIT 1", $id ), ARRAY_A );
					if ( ! $r ) break;
					if ( self::send_one( $camp, $r, $links ) !== 'skipped' ) {
						$allowance--;
						$sent++;
					}
				}

				if ( $camp['type'] === 'regular' && ! self::queued( $id ) ) {
					DXN_Campaigns::set_status( $id, 'sent' );
				}
				if ( $allowance <= 0 || time() - $started >= self::BUDGET ) break;
			}
		} finally {
			self::unlock();
		}
		return $sent;
	}

	/** Lo que queda del tope de la última hora. */
	public static function allowance() {
		return max( 0, (int) DXN_Settings::get( 'rate_per_hour' ) - self::sent_last_hour() );
	}

	public static function sent_last_hour() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . DXN_Install::table( 'recipients' ) . " WHERE status IN ('sent','failed') AND sent_at >= %s", gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
	}

	public static function queued( $campaign_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . DXN_Install::table( 'recipients' ) . " WHERE campaign_id = %d AND status = 'queued'", $campaign_id ) );
	}

	// ═══ Un correo ══════════════════════════════════════════════════════════

	/** @return string sent|failed|skipped */
	public static function send_one( array $c, array $r, array $links ) {
		global $wpdb;
		$rt  = DXN_Install::table( 'recipients' );
		$sub = DXN_Subscribers::get( $r['subscriber_id'] );

		// Si se dio de baja (o se borró) entre que se lanzó y le toca, no se le manda.
		if ( ! $sub || $sub['status'] !== 'active' ) {
			$wpdb->update( $rt, [ 'status' => 'skipped', 'error' => $sub ? $sub['status'] : 'deleted' ], [ 'id' => $r['id'] ] );
			return 'skipped';
		}

		$email = self::build( $c, $r, $sub, $links );
		$error = DXN_Mailer::send( $sub['email'], $email['subject'], $email['html'], $email['text'], [
			// Baja con un clic (RFC 8058): Gmail y Yahoo la exigen a quien manda en
			// cantidad, y ponen el enlace "Cancelar suscripción" junto al remitente.
			'List-Unsubscribe'      => '<' . $email['unsubscribe_url'] . '>',
			'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
			'X-Dox-Newsletter'      => $c['id'] . '.' . $r['id'],
		] );

		$ct = DXN_Install::table( 'campaigns' );
		if ( $error === null ) {
			$wpdb->update( $rt, [ 'status' => 'sent', 'sent_at' => dxn_now(), 'error' => '' ], [ 'id' => $r['id'] ] );
			$wpdb->query( $wpdb->prepare( "UPDATE $ct SET sent = sent + 1 WHERE id = %d", $c['id'] ) );
			return 'sent';
		}
		$wpdb->update( $rt, [ 'status' => 'failed', 'sent_at' => dxn_now(), 'error' => mb_substr( $error, 0, 250 ) ], [ 'id' => $r['id'] ] );
		$wpdb->query( $wpdb->prepare( "UPDATE $ct SET failed = failed + 1 WHERE id = %d", $c['id'] ) );
		return 'failed';
	}

	/**
	 * El correo de una persona: asunto, HTML y texto, con sus datos y sus enlaces.
	 *
	 * @param array|null $links URL => id del enlace; null = sin seguimiento (prueba y "ver en el navegador")
	 */
	public static function build( array $c, array $r, array $sub, $links ) {
		$s      = DXN_Settings::all();
		$token  = $r['token'];
		$fields = [ 'first_name' => $sub['first_name'], 'last_name' => $sub['last_name'], 'email' => $sub['email'] ];
		$unsub  = DXN_Public::url( 'unsubscribe', $token );
		$track  = $links !== null;

		$render = DXN_Renderer::render_email( [
			'blocks'          => $c['blocks'],
			'brand'           => DXN_Settings::brand(),
			'subject'         => $c['subject'],
			'preheader'       => $c['preheader'],
			'fields'          => $fields,
			'unsubscribe_url' => $unsub,
			'view_url'        => $track ? DXN_Public::url( 'view', $token ) : '',
			'pixel_url'       => $track && $s['track_opens'] ? DXN_Public::url( 'open', $token ) : null,
			'link'            => $track && $s['track_clicks'] ? function ( $url ) use ( $links, $token ) {
				return isset( $links[ $url ] ) ? DXN_Public::url( 'click', $token, $links[ $url ] ) : null;
			} : null,
			'strings'         => DXN_Public::email_strings(),
		] );

		return [
			'subject'         => DXN_Renderer::merge( $c['subject'], $fields, false ),
			'html'            => $render['html'],
			'text'            => $render['text'],
			'unsubscribe_url' => $unsub,
		];
	}

	/**
	 * Una prueba a un correo cualquiera: con el nombre de quien la pide, sin
	 * seguimiento y con "[Prueba]" delante del asunto. No cuenta en la campaña.
	 */
	public static function send_test( array $c, $to ) {
		$user  = wp_get_current_user();
		$fake  = [ 'first_name' => $user && $user->first_name ? $user->first_name : 'Ana', 'last_name' => '', 'email' => $to ];
		$email = self::build( $c, [ 'token' => str_repeat( '0', 32 ) ], $fake, null );
		return DXN_Mailer::send( $to, '[' . __( 'Test', 'dox-newsletter' ) . '] ' . $email['subject'], $email['html'], $email['text'] );
	}

	// ═══ Estado para el panel ═══════════════════════════════════════════════

	/** La campaña que está saliendo, su progreso y cuándo terminará. */
	public static function status() {
		global $wpdb;
		$row = $wpdb->get_row( 'SELECT id FROM ' . DXN_Install::table( 'campaigns' ) . " WHERE type = 'regular' AND status IN ('sending','paused') ORDER BY started_at ASC LIMIT 1", ARRAY_A );
		if ( ! $row ) return null;
		$c     = DXN_Campaigns::get( $row['id'] );
		$st    = DXN_Campaigns::stats( $c['id'] );
		$rate  = max( 1, (int) DXN_Settings::get( 'rate_per_hour' ) );
		$left  = $st['queued'];
		// Lo que falta, al ritmo del tope, contando con lo que ya se gastó esta hora.
		$hours = $left / $rate;
		$eta   = time() + (int) round( $hours * HOUR_IN_SECONDS );
		return [
			'id'       => (int) $c['id'],
			'subject'  => $c['subject'],
			'status'   => $c['status'],
			'done'     => $st['sent'] + $st['failed'],
			'total'    => $st['total'],
			'left'     => $left,
			'rate'     => $rate,
			'eta'      => $left ? wp_date( get_option( 'time_format' ), $eta ) : '',
			'eta_day'  => $left ? ( wp_date( 'Ymd', $eta ) === wp_date( 'Ymd' ) ? 'today' : wp_date( 'l j', $eta ) ) : '',
		];
	}

	// ═══ Candado ════════════════════════════════════════════════════════════
	// Si una pasada tarda, la siguiente no debe mandar los mismos correos. Una
	// opción con la hora, tomada con un UPDATE condicional (atómico en MySQL).

	private static function lock() {
		global $wpdb;
		if ( false === get_option( 'dxn_lock' ) ) add_option( 'dxn_lock', '0', '', false );
		$now = time();
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = 'dxn_lock' AND (option_value = '0' OR option_value < %s)",
			(string) $now, (string) ( $now - self::LOCK_SECONDS )
		) );
		wp_cache_delete( 'dxn_lock', 'options' );
		return $wpdb->rows_affected > 0;
	}

	private static function unlock() {
		global $wpdb;
		$wpdb->query( "UPDATE {$wpdb->options} SET option_value = '0' WHERE option_name = 'dxn_lock'" );
		wp_cache_delete( 'dxn_lock', 'options' );
	}
}
