<?php
/**
 * De los bloques del editor al correo que se envía.
 *
 * Portado de Dox Mail (Perfex). PHP puro, sin WordPress: la vista previa del
 * editor y el envío usan exactamente este código, así que lo que se ve es lo
 * que sale. tests/run.php lo prueba sin WordPress.
 *
 * El correo es de tablas y estilos en línea, que es lo único que respetan
 * Gmail, Outlook y Apple Mail a la vez.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DXN_Renderer {

	/** Los campos que se rellenan con los datos de cada persona. */
	const TAGS = [ 'first_name', 'last_name', 'email' ];

	const FONT = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

	const INK  = '#141313';
	const BODY = '#3f3a37';
	const SOFT = '#8a817b';
	const PAGE = '#f4f2f0';

	// ─── Campos ─────────────────────────────────────────────────────────────

	/**
	 * Rellena {first_name}... con los datos de la persona.
	 *
	 * {first_name|amigo} pone "amigo" si no hay nombre. Y si el campo queda vacío
	 * justo antes de un signo, se lleva también el separador que lo precede:
	 * "Hola, {first_name}:" sin nombre queda "Hola:" y no "Hola, :".
	 */
	public static function merge( $text, array $fields, $escape = true ) {
		return preg_replace_callback(
			'/(,\s*|\s+)?\{([a-z_]+)(?:\|([^}]*))?\}(?=([,:;.!?])?)/i',
			function ( $m ) use ( $fields, $escape ) {
				$name = strtolower( $m[2] );
				if ( ! in_array( $name, self::TAGS, true ) ) {
					return $m[0];
				}
				$value = trim( (string) ( $fields[ $name ] ?? '' ) );
				if ( $value === '' ) {
					$value = isset( $m[3] ) ? $m[3] : '';
				}
				$sep = $m[1] ?? '';
				if ( $value === '' && ! empty( $m[4] ) ) {
					return '';
				}
				if ( $value === '' && $sep !== '' && trim( $sep ) === '' ) {
					return '';
				}
				return $sep . ( $escape ? htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) : $value );
			},
			(string) $text
		);
	}

	/** Los {campos} que no se saben rellenar, de uno o varios textos. */
	public static function unknown_tags( ...$texts ) {
		$found = [];
		foreach ( $texts as $text ) {
			if ( preg_match_all( '/\{([a-z_]+)(?:\|[^}]*)?\}/i', (string) $text, $m ) ) {
				foreach ( $m[1] as $name ) {
					$name = strtolower( $name );
					if ( ! in_array( $name, self::TAGS, true ) ) {
						$found[ '{' . $name . '}' ] = true;
					}
				}
			}
		}
		return array_keys( $found );
	}

	// ─── Limpieza del texto de los bloques ──────────────────────────────────

	/**
	 * Deja en el HTML de un bloque de texto solo lo que el editor sabe hacer:
	 * párrafos, saltos, negrita, cursiva, subrayado, listas y enlaces. Lo que se
	 * pega de Word o de una web trae estilos que cada programa de correo pinta distinto.
	 */
	public static function clean_text_html( $html ) {
		$html = trim( (string) $html );
		if ( $html === '' ) return '';

		$allowed = [ 'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li' ];

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="dxn-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$root = $doc->getElementById( 'dxn-root' );
		if ( ! $root ) {
			return htmlspecialchars( strip_tags( $html ), ENT_QUOTES, 'UTF-8' );
		}

		$out = '';
		foreach ( iterator_to_array( $root->childNodes ) as $child ) {
			$out .= self::clean_node( $child, $allowed );
		}

		// Texto suelto sin párrafo: se envuelve para que tenga el mismo margen.
		if ( $out !== '' && ! preg_match( '/^\s*<(p|ul|ol)\b/i', $out ) ) {
			$out = '<p>' . $out . '</p>';
		}
		return $out;
	}

	protected static function clean_node( DOMNode $node, array $allowed ) {
		if ( $node->nodeType === XML_TEXT_NODE ) {
			return htmlspecialchars( $node->nodeValue, ENT_NOQUOTES, 'UTF-8' );
		}
		if ( $node->nodeType !== XML_ELEMENT_NODE ) return '';

		$tag = strtolower( $node->nodeName );
		// De un script o un estilo pegado no se queda ni su texto.
		if ( in_array( $tag, [ 'script', 'style', 'head', 'title', 'iframe', 'object' ], true ) ) return '';
		$inner = '';
		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			$inner .= self::clean_node( $child, $allowed );
		}

		// div y span (lo que mete el contenteditable) no se quedan, pero su texto sí.
		if ( $tag === 'div' ) return $inner === '' ? '' : '<p>' . $inner . '</p>';
		if ( ! in_array( $tag, $allowed, true ) ) return $inner;
		if ( $tag === 'b' ) $tag = 'strong';
		if ( $tag === 'i' ) $tag = 'em';
		if ( $tag === 'br' ) return '<br>';
		if ( $tag === 'a' ) {
			$href = trim( (string) $node->getAttribute( 'href' ) );
			if ( ! self::safe_url( $href ) ) return $inner;
			return '<a href="' . htmlspecialchars( $href, ENT_QUOTES, 'UTF-8' ) . '">' . $inner . '</a>';
		}
		return '<' . $tag . '>' . $inner . '</' . $tag . '>';
	}

	/** http, https, mailto, tel o un campo ({...}): nada de javascript: ni data:. */
	public static function safe_url( $url ) {
		$url = trim( (string) $url );
		return $url !== '' && (bool) preg_match( '#^(https?://|mailto:|tel:|\{)#i', $url );
	}

	// ─── Bloques ────────────────────────────────────────────────────────────

	/** Los tipos de bloque que entiende el editor. */
	const TYPES = [ 'heading', 'text', 'image', 'button', 'post', 'divider', 'spacer' ];

	/**
	 * Normaliza la lista de bloques que manda el editor: tipos conocidos, campos
	 * esperados y el texto ya limpio. Es lo que se guarda en la base.
	 */
	public static function normalize_blocks( $blocks ) {
		if ( is_string( $blocks ) ) $blocks = json_decode( $blocks, true );
		if ( ! is_array( $blocks ) ) return [];

		$out = [];
		foreach ( $blocks as $b ) {
			if ( ! is_array( $b ) || empty( $b['type'] ) ) continue;
			$align = ( ( $b['align'] ?? 'left' ) === 'center' ) ? 'center' : 'left';
			switch ( $b['type'] ) {
				case 'heading':
					$size  = in_array( $b['size'] ?? '', [ 'large', 'medium', 'small' ], true ) ? $b['size'] : 'large';
					$out[] = [ 'type' => 'heading', 'text' => trim( (string) ( $b['text'] ?? '' ) ), 'size' => $size, 'align' => $align ];
					break;
				case 'text':
					$out[] = [ 'type' => 'text', 'html' => self::clean_text_html( $b['html'] ?? '' ), 'align' => $align ];
					break;
				case 'image':
					$url   = trim( (string) ( $b['url'] ?? '' ) );
					$link  = trim( (string) ( $b['link'] ?? '' ) );
					$out[] = [
						'type'  => 'image',
						'url'   => self::safe_url( $url ) ? $url : '',
						'alt'   => trim( (string) ( $b['alt'] ?? '' ) ),
						'link'  => self::safe_url( $link ) ? $link : '',
						'align' => $align,
					];
					break;
				case 'button':
					$url   = trim( (string) ( $b['url'] ?? '' ) );
					$style = ( $b['style'] ?? '' ) === 'accent' ? 'accent' : 'dark';
					$out[] = [ 'type' => 'button', 'text' => trim( (string) ( $b['text'] ?? '' ) ), 'url' => self::safe_url( $url ) ? $url : '', 'style' => $style, 'align' => $align ];
					break;
				case 'post':
					// Una entrada del blog: se guarda ya resuelta (título, resumen, imagen,
					// enlace) para que el correo no cambie si luego se edita la entrada.
					$url   = trim( (string) ( $b['url'] ?? '' ) );
					$img   = trim( (string) ( $b['image'] ?? '' ) );
					$out[] = [
						'type'    => 'post',
						'post_id' => (int) ( $b['post_id'] ?? 0 ),
						'title'   => trim( (string) ( $b['title'] ?? '' ) ),
						'excerpt' => trim( (string) ( $b['excerpt'] ?? '' ) ),
						'image'   => self::safe_url( $img ) ? $img : '',
						'url'     => self::safe_url( $url ) ? $url : '',
						'cta'     => trim( (string) ( $b['cta'] ?? '' ) ),
					];
					break;
				case 'divider':
					$out[] = [ 'type' => 'divider' ];
					break;
				case 'spacer':
					$out[] = [ 'type' => 'spacer', 'height' => max( 8, min( 96, (int) ( $b['height'] ?? 24 ) ) ) ];
					break;
			}
		}
		return $out;
	}

	/** El contenido del correo: una fila de tabla por bloque. */
	public static function render_blocks( array $blocks, array $brand ) {
		$accent = self::color( $brand['accent'] ?? '#ff8d27' );
		$font   = self::FONT;
		$rows   = '';

		foreach ( $blocks as $b ) {
			$align = ( $b['align'] ?? 'left' ) === 'center' ? 'center' : 'left';
			switch ( $b['type'] ) {
				case 'heading':
					if ( $b['text'] === '' ) break;
					$sizes = [ 'large' => [ 28, 1.2 ], 'medium' => [ 21, 1.3 ], 'small' => [ 17, 1.4 ] ];
					list( $px, $lh ) = $sizes[ $b['size'] ] ?? $sizes['large'];
					$rows .= '<tr><td class="dxn-pad" style="padding:0 40px 14px 40px;font-family:' . $font . ';font-size:' . $px . 'px;line-height:' . $lh . ';font-weight:700;letter-spacing:-0.4px;color:' . self::INK . ';text-align:' . $align . ';">'
						. self::esc( $b['text'] ) . '</td></tr>';
					break;

				case 'text':
					if ( $b['html'] === '' ) break;
					$html  = self::inline_text_styles( $b['html'], $accent );
					$rows .= '<tr><td class="dxn-pad" style="padding:0 40px 6px 40px;font-family:' . $font . ';font-size:16px;line-height:1.7;color:' . self::BODY . ';text-align:' . $align . ';">' . $html . '</td></tr>';
					break;

				case 'image':
					if ( $b['url'] === '' ) break;
					$img = '<img src="' . self::esc( $b['url'] ) . '" alt="' . self::esc( $b['alt'] ) . '" width="520" style="display:block;width:100%;max-width:520px;height:auto;border:0;border-radius:12px;' . ( $align === 'center' ? 'margin:0 auto;' : '' ) . '">';
					if ( $b['link'] !== '' ) {
						$img = '<a href="' . self::esc( $b['link'] ) . '" style="text-decoration:none;">' . $img . '</a>';
					}
					$rows .= '<tr><td class="dxn-pad" align="' . $align . '" style="padding:4px 40px 22px 40px;">' . $img . '</td></tr>';
					break;

				case 'button':
					if ( $b['text'] === '' || $b['url'] === '' ) break;
					$rows .= '<tr><td class="dxn-pad" align="' . $align . '" style="padding:8px 40px 26px 40px;">' . self::button( $b['text'], $b['url'], $b['style'] ?? 'dark', $accent, $align ) . '</td></tr>';
					break;

				case 'post':
					if ( $b['title'] === '' || $b['url'] === '' ) break;
					$card  = '';
					if ( $b['image'] !== '' ) {
						$card .= '<tr><td style="padding:0;"><a href="' . self::esc( $b['url'] ) . '" style="text-decoration:none;"><img src="' . self::esc( $b['image'] ) . '" alt="' . self::esc( $b['title'] ) . '" width="520" style="display:block;width:100%;max-width:520px;height:auto;border:0;border-radius:12px 12px 0 0;"></a></td></tr>';
					}
					$card .= '<tr><td style="padding:20px 22px 6px 22px;font-family:' . $font . ';font-size:19px;line-height:1.3;font-weight:700;letter-spacing:-0.3px;color:' . self::INK . ';"><a href="' . self::esc( $b['url'] ) . '" style="color:' . self::INK . ';text-decoration:none;">' . self::esc( $b['title'] ) . '</a></td></tr>';
					if ( $b['excerpt'] !== '' ) {
						$card .= '<tr><td style="padding:0 22px 6px 22px;font-family:' . $font . ';font-size:15px;line-height:1.6;color:' . self::BODY . ';">' . self::esc( $b['excerpt'] ) . '</td></tr>';
					}
					$cta   = $b['cta'] !== '' ? $b['cta'] : 'Read more';
					$card .= '<tr><td style="padding:6px 22px 20px 22px;font-family:' . $font . ';font-size:15px;font-weight:700;"><a href="' . self::esc( $b['url'] ) . '" style="color:' . self::INK . ';text-decoration:underline;">' . self::esc( $cta ) . ' &rarr;</a></td></tr>';
					$rows .= '<tr><td class="dxn-pad" style="padding:4px 40px 24px 40px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #ebe6e2;border-radius:12px;">' . $card . '</table></td></tr>';
					break;

				case 'divider':
					$rows .= '<tr><td class="dxn-pad" style="padding:10px 40px 26px 40px;"><div style="height:1px;line-height:1px;font-size:1px;background:#ebe6e2;">&nbsp;</div></td></tr>';
					break;

				case 'spacer':
					$h     = (int) $b['height'];
					$rows .= '<tr><td style="height:' . $h . 'px;line-height:' . $h . 'px;font-size:1px;">&nbsp;</td></tr>';
					break;
			}
		}
		return $rows;
	}

	/** Márgenes, listas y enlaces con estilos en línea: cada programa de correo pone los suyos. */
	protected static function inline_text_styles( $html, $accent ) {
		$html = preg_replace( '/<p>/i', '<p style="margin:0 0 16px 0;">', $html );
		$html = preg_replace( '/<ul>/i', '<ul style="margin:0 0 16px 0;padding-left:22px;">', $html );
		$html = preg_replace( '/<ol>/i', '<ol style="margin:0 0 16px 0;padding-left:22px;">', $html );
		$html = preg_replace( '/<li>/i', '<li style="margin:0 0 6px 0;">', $html );
		$html = preg_replace( '/<a href=/i', '<a style="color:' . self::INK . ';text-decoration:underline;" href=', $html );
		return $html;
	}

	/**
	 * Botón "a prueba de Outlook": una tabla con el fondo en la celda. Oscuro
	 * (negro de la marca, texto blanco) o del color de acento, con el texto que
	 * mejor contraste sobre él.
	 */
	public static function button( $text, $url, $style, $accent, $align = 'left' ) {
		$bg = $style === 'accent' ? $accent : self::INK;
		$fg = self::text_on( $bg );
		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"' . ( $align === 'center' ? ' align="center"' : '' ) . '><tr>'
			. '<td bgcolor="' . $bg . '" style="border-radius:999px;background:' . $bg . ';">'
			. '<a href="' . self::esc( $url ) . '" style="display:inline-block;padding:14px 28px;font-family:' . self::FONT . ';font-size:15px;font-weight:700;color:' . $fg . ';text-decoration:none;border-radius:999px;">' . self::esc( $text ) . '</a>'
			. '</td></tr></table>';
	}

	/**
	 * El correo entero de una persona.
	 *
	 * $o: blocks, brand, subject, preheader, fields (los datos de la persona),
	 * unsubscribe_url, view_url, pixel_url (o null), link (callable que cambia
	 * cada URL por la de seguimiento, o null), strings (unsubscribe, view).
	 *
	 * @return array [ 'html' => ..., 'text' => ... ]
	 */
	public static function render_email( array $o ) {
		$brand  = $o['brand'] ?? [];
		$fields = $o['fields'] ?? [];
		$str    = wp_parse_args_lite( $o['strings'] ?? [], [ 'unsubscribe' => 'Unsubscribe', 'view' => 'View in browser', 'lang' => 'en' ] );
		$font   = self::FONT;

		$content = self::render_blocks( $o['blocks'] ?? [], $brand );
		$content = self::merge( $content, $fields, true );

		// Cabecera: el logo, o el nombre de la marca en texto si no hay logo.
		if ( ! empty( $brand['logo_url'] ) ) {
			$head = '<img src="' . self::esc( $brand['logo_url'] ) . '" height="32" alt="' . self::esc( $brand['company'] ?? '' ) . '" style="display:block;border:0;height:32px;width:auto;">';
		} else {
			$head = '<span style="font-family:' . $font . ';font-size:18px;font-weight:700;letter-spacing:-0.4px;color:' . self::INK . ';">' . self::esc( $brand['company'] ?? '' ) . '</span>';
		}
		$header = '<tr><td class="dxn-pad" style="padding:34px 40px 26px 40px;">' . $head . '</td></tr>';

		$unsubscribe = self::esc( (string) ( $o['unsubscribe_url'] ?? '#' ) );
		$view        = ! empty( $o['view_url'] ) ? ' &middot; <a href="' . self::esc( $o['view_url'] ) . '" style="color:' . self::SOFT . ';text-decoration:underline;">' . self::esc( $str['view'] ) . '</a>' : '';
		$address     = self::esc( (string) ( $brand['address'] ?? '' ) );
		$company     = self::esc( (string) ( $brand['company'] ?? '' ) );
		$why         = self::esc( (string) ( $brand['why'] ?? '' ) );

		// Se escapa entero y no solo los campos: es texto.
		$preheader = self::esc( trim( self::merge( (string) ( $o['preheader'] ?? '' ), $fields, false ) ) );
		// El relleno invisible impide que, después del texto, la vista previa de la
		// bandeja se llene con lo primero del cuerpo.
		$preheaderHtml = $preheader === '' ? '' : '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:' . self::PAGE . ';">' . $preheader
			. str_repeat( '&#847;&zwnj;&nbsp;', 40 ) . '</div>';

		$pixel = empty( $o['pixel_url'] ) ? '' : '<img src="' . self::esc( $o['pixel_url'] ) . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;">';

		// El único <style>: en el móvil, menos margen a los lados. Si un programa
		// de correo lo ignora, se ve igual que en el ordenador, que también vale.
		$css = '<style>@media (max-width:600px){.dxn-pad{padding-left:24px!important;padding-right:24px!important}.dxn-outer{padding:12px 8px!important}}</style>';

		$html = '<!doctype html><html lang="' . self::esc( $str['lang'] ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<meta name="x-apple-disable-message-reformatting"><title>' . self::esc( self::merge( (string) ( $o['subject'] ?? '' ), $fields, false ) ) . '</title>' . $css . '</head>'
			. '<body style="margin:0;padding:0;background:' . self::PAGE . ';-webkit-text-size-adjust:100%;">' . $preheaderHtml
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . self::PAGE . ';"><tr><td class="dxn-outer" align="center" style="padding:32px 16px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:16px;">'
			. $header . $content . '<tr><td style="height:16px;line-height:16px;font-size:1px;">&nbsp;</td></tr></table>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;"><tr><td align="center" style="padding:22px 24px 0 24px;font-family:' . $font . ';font-size:12px;line-height:1.7;color:' . self::SOFT . ';">'
			. ( $why !== '' ? $why . '<br>' : '' )
			. '<a href="' . $unsubscribe . '" style="color:' . self::SOFT . ';text-decoration:underline;">' . self::esc( $str['unsubscribe'] ) . '</a>' . $view . '<br>'
			. $company . ( $address !== '' ? ' &middot; ' . $address : '' )
			. '</td></tr></table>' . $pixel
			. '</td></tr></table></body></html>';

		// Los enlaces, cambiados por los de seguimiento. La baja y "ver en el
		// navegador" no: tienen que funcionar siempre y sin dar vueltas.
		if ( ! empty( $o['link'] ) && is_callable( $o['link'] ) ) {
			$html = self::rewrite_links( $html, $o['link'], [ (string) ( $o['unsubscribe_url'] ?? '' ), (string) ( $o['view_url'] ?? '' ) ] );
		}

		return [ 'html' => $html, 'text' => self::to_text( $html ) ];
	}

	// ─── Enlaces ────────────────────────────────────────────────────────────

	/** Las URL http(s) de los enlaces del correo, en orden y sin repetir. */
	public static function extract_links( $html ) {
		preg_match_all( '/<a\b[^>]*\bhref="(https?:\/\/[^"]+)"/i', (string) $html, $m );
		return array_values( array_unique( array_map( function ( $u ) {
			return html_entity_decode( $u, ENT_QUOTES, 'UTF-8' );
		}, $m[1] ) ) );
	}

	/**
	 * Cambia cada href http(s) por lo que devuelva $callback($url). Si devuelve
	 * null, el enlace se queda como estaba. $keep son URL que no se tocan nunca.
	 */
	public static function rewrite_links( $html, callable $callback, array $keep = [] ) {
		return preg_replace_callback( '/(<a\b[^>]*\bhref=")(https?:\/\/[^"]+)(")/i', function ( $m ) use ( $callback, $keep ) {
			$url = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
			if ( in_array( $url, $keep, true ) ) return $m[0];
			$new = $callback( $url );
			return $new === null ? $m[0] : $m[1] . htmlspecialchars( $new, ENT_QUOTES, 'UTF-8' ) . $m[3];
		}, (string) $html );
	}

	// ─── Texto plano ────────────────────────────────────────────────────────

	/**
	 * La versión de solo texto que va junto al HTML. Los filtros de spam miran que
	 * exista y que diga lo mismo; los enlaces van entre paréntesis.
	 */
	public static function to_text( $html ) {
		$html = preg_replace( '#<div style="display:none[^>]*>.*?</div>#is', '', (string) $html );
		$html = preg_replace( '#<(head|style|title)\b.*?</\1>#is', '', $html );
		$html = preg_replace_callback( '#<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', function ( $m ) {
			$text = trim( strip_tags( $m[2] ) );
			$url  = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
			return $text === '' || $text === $url ? $url : $text . ' (' . $url . ')';
		}, $html );
		$html = preg_replace( '#<li[^>]*>#i', "\n- ", $html );
		$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
		$html = preg_replace( '#</(p|tr|h[1-6]|ul|ol|table)>#i', "\n\n", $html );
		$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t\x{00A0}\x{034F}\x{200C}]+/u", ' ', $text );
		$text = preg_replace( "/ *\n */", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		return trim( $text );
	}

	// ─── Utilidades ─────────────────────────────────────────────────────────

	public static function esc( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}

	/** Un color hexadecimal válido o el naranja de marca. */
	public static function color( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^#[0-9a-f]{6}$/i', $value ) ? strtolower( $value ) : '#ff8d27';
	}

	/** Blanco o casi negro, el que más contraste dé sobre $bg (luminancia WCAG). */
	public static function text_on( $bg ) {
		$bg = self::color( $bg );
		$l  = function ( $c ) {
			$c = $c / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		$lum = 0.2126 * $l( hexdec( substr( $bg, 1, 2 ) ) ) + 0.7152 * $l( hexdec( substr( $bg, 3, 2 ) ) ) + 0.0722 * $l( hexdec( substr( $bg, 5, 2 ) ) );
		// Contraste con blanco (1) frente a contraste con el negro de marca (~0,007).
		return ( 1.05 / ( $lum + 0.05 ) ) >= ( ( $lum + 0.05 ) / 0.057 ) ? '#ffffff' : self::INK;
	}
}

/** wp_parse_args sin WordPress, para que el renderizador se pruebe solo. */
function wp_parse_args_lite( $args, $defaults ) {
	return array_merge( $defaults, is_array( $args ) ? $args : [] );
}
