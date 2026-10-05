<?php
/**
 * Pruebas del renderizador, sin WordPress: php tests/run.php
 * No viajan en el zip (el workflow excluye tests/).
 */
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../includes/class-dxo-renderer.php';

$fails = 0;
function check( $name, $ok ) {
	global $fails;
	echo ( $ok ? "  ok   " : "  FAIL " ) . $name . "\n";
	if ( ! $ok ) $fails++;
}

echo "Campos\n";
check( 'nombre', DXO_Renderer::merge( 'Hola, {first_name}', [ 'first_name' => 'Ana' ] ) === 'Hola, Ana' );
check( 'respaldo', DXO_Renderer::merge( 'Hola, {first_name|amigo}', [] ) === 'Hola, amigo' );
check( 'sin nombre se lleva la coma', DXO_Renderer::merge( 'Hola, {first_name}:', [] ) === 'Hola:' );
check( 'se escapa', DXO_Renderer::merge( '{first_name}', [ 'first_name' => '<b>' ] ) === '&lt;b&gt;' );
check( 'campo desconocido se queda', DXO_Renderer::merge( '{plan}', [] ) === '{plan}' );
check( 'unknown_tags', DXO_Renderer::unknown_tags( 'a {plan} {first_name} {x|y}' ) === [ '{plan}', '{x}' ] );

echo "Limpieza del texto\n";
$c = DXO_Renderer::clean_text_html( '<div style="color:red">Hola <b>tú</b><script>alert(1)</script></div><a href="javascript:x">mal</a><a href="https://a.com">bien</a>' );
check( 'div pasa a p', strpos( $c, '<p>Hola <strong>tú</strong>' ) === 0 );
check( 'sin script ni su texto', stripos( $c, 'script' ) === false && strpos( $c, 'alert' ) === false );
check( 'sin javascript:', strpos( $c, 'javascript' ) === false );
check( 'enlace bueno', strpos( $c, '<a href="https://a.com">bien</a>' ) !== false );

echo "Bloques\n";
$b = DXO_Renderer::normalize_blocks( [ [ 'type' => 'nada' ], [ 'type' => 'button', 'text' => 'Ir', 'url' => 'javascript:x' ], [ 'type' => 'spacer', 'height' => 500 ] ] );
check( 'tipo desconocido fuera', count( $b ) === 2 );
check( 'url peligrosa vacía', $b[0]['url'] === '' );
check( 'espacio con tope', $b[1]['height'] === 96 );

echo "Correo\n";
$r = DXO_Renderer::render_email( [
	'blocks'          => DXO_Renderer::normalize_blocks( [ [ 'type' => 'text', 'html' => '<p>Mira <a href="https://a.com/x">esto</a></p>' ], [ 'type' => 'button', 'text' => 'Ir', 'url' => 'https://a.com/y', 'style' => 'accent' ] ] ),
	'brand'           => [ 'company' => 'Dox & Co', 'address' => 'Riviera Beach, FL', 'accent' => '#ff8d27', 'why' => 'Te suscribiste.' ],
	'subject'         => 'Hola {first_name}',
	'preheader'       => 'Pre &copy;',
	'fields'          => [ 'first_name' => 'Ana' ],
	'unsubscribe_url' => 'https://web.com/?dxo=unsubscribe&t=1',
	'pixel_url'       => 'https://web.com/?dxo=open&t=1',
	'link'            => function ( $u ) { return 'https://web.com/?dxo=click&l=' . md5( $u ); },
] );
check( 'enlaces con seguimiento', substr_count( $r['html'], 'dxo=click' ) === 2 );
check( 'la baja no se sigue', strpos( $r['html'], 'href="https://web.com/?dxo=unsubscribe&amp;t=1"' ) !== false );
check( 'pixel', strpos( $r['html'], 'dxo=open' ) !== false );
check( 'empresa escapada', strpos( $r['html'], 'Dox &amp; Co' ) !== false );
check( 'preheader escapado', strpos( $r['html'], 'Pre &amp;copy;' ) !== false );
check( 'texto plano con enlace', strpos( $r['text'], 'esto (https://web.com/?dxo=click' ) !== false );
check( 'botón naranja con texto oscuro', strpos( $r['html'], 'color:#141313;text-decoration:none' ) !== false );
check( 'texto sobre negro es blanco', DXO_Renderer::text_on( '#141313' ) === '#ffffff' );
check( 'texto sobre amarillo es oscuro', DXO_Renderer::text_on( '#ffe066' ) === '#141313' );

echo $fails ? "\n$fails fallos\n" : "\nTodo bien\n";
exit( $fails ? 1 : 0 );
