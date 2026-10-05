<?php
/**
 * La franja negra del envío en curso. El JS la pone al día cada 20 segundos.
 *
 * @var array|null $status DXO_Sender::status()
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$status = $status ?? DXO_Sender::status();
$pct    = $status && $status['total'] ? round( $status['done'] * 100 / $status['total'] ) : 0;
?>
<div class="dxo-status<?php echo $status && $status['status'] === 'paused' ? ' is-paused' : ''; ?>" id="dxo-status" data-anim <?php echo $status ? '' : 'hidden'; ?> data-id="<?php echo $status ? (int) $status['id'] : 0; ?>">
	<div class="live"><i></i></div>
	<div style="flex:1;min-width:0">
		<div><b data-s="title"><?php echo $status ? esc_html( sprintf( $status['status'] === 'paused' ? __( 'Paused «%s»', 'dox-orbit' ) : __( 'Sending «%s»', 'dox-orbit' ), $status['subject'] ) ) : ''; ?></b> · <span data-s="count"><?php echo $status ? esc_html( sprintf( __( '%1$s of %2$s', 'dox-orbit' ), dxo_num( $status['done'] ), dxo_num( $status['total'] ) ) ) : ''; ?></span></div>
		<div class="bar"><span data-s="bar" style="width:<?php echo (int) $pct; ?>%"></span></div>
		<div class="meta" data-s="meta"><?php echo $status ? esc_html( DXO_Admin::status_meta( $status ) ) : ''; ?></div>
	</div>
	<button type="button" class="dxo-btn dxo-btn-gray dxo-btn-sm" data-s="toggle" data-action="<?php echo $status && $status['status'] === 'paused' ? 'resume' : 'pause'; ?>">
		<?php echo DXO_Admin::icon( $status && $status['status'] === 'paused' ? 'play' : 'pause' ); // phpcs:ignore ?><span><?php echo $status && $status['status'] === 'paused' ? esc_html__( 'Resume', 'dox-orbit' ) : esc_html__( 'Pause', 'dox-orbit' ); ?></span>
	</button>
</div>
