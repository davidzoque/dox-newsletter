<?php
/**
 * Al borrar el plugin desde WordPress no se borra la lista ni las campañas:
 * los suscriptores son lo más valioso y volver a instalarlo los recupera tal
 * cual. Solo se quita la tarea programada. Para borrarlo todo de verdad, las
 * tablas wp_dxn_* y las opciones dxn_* se eliminan a mano.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

wp_clear_scheduled_hook( 'dxn_send' );
wp_clear_scheduled_hook( 'dxn_send_now' );
delete_option( 'dxn_lock' );
