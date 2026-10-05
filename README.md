# Dox Orbit

Email marketing desde el propio WordPress, "tipo Mailchimp pero local" y
orientado a tiendas WooCommerce (como Klaviyo). Hasta la 0.1.1 se llamó Dox
Newsletter (prefijo `dxn`); desde la 0.2.0 todo es `dxo` y el shortcode es
`[dox_orbit]`. Lo pidió David el 04/10/2026 como plugin propio, igual que hizo Dox Mail
en Perfex, y con una interfaz al estilo de pixfort. Gran parte de la lógica viene
de Dox Mail (`Projects/Perfex CRM/Perfex Modules/dox_mail`): el renderizador del
correo, el envío por tandas, el seguimiento y el informe.

## Qué hay en cada archivo

| Archivo | Qué hace |
|---|---|
| `dox-orbit.php` | Arranque: constantes, dox-core, carga de clases, Plugin Update Checker |
| `includes/class-dxo-install.php` | Las 7 tablas `wp_dxo_*` con dbDelta. Se ponen al día solas al cambiar `DB_VERSION` |
| `includes/class-dxo-settings.php` | Ajustes (opción `dxo_settings`), marca del correo, cifrado de la contraseña de SES y utilidades de fecha y número |
| `includes/class-dxo-renderer.php` | De los bloques al HTML del correo. PHP puro: lo prueba `tests/run.php` sin WordPress |
| `includes/class-dxo-subscribers.php` | Suscriptores y listas: alta con doble confirmación, baja, interés, importar CSV |
| `includes/class-dxo-campaigns.php` | Campañas: guardar, lanzar o programar, audiencia, reenviar, bienvenida, números del informe |
| `includes/class-dxo-mailer.php` | Envía por `wp_mail` o por Amazon SES (PHPMailer propio) |
| `includes/class-dxo-sender.php` | La cola: tandas por WP-Cron con tope por hora y candado |
| `includes/class-dxo-public.php` | Lo que llega de fuera: suscribirse, confirmar, baja, pixel, clic, ver en el navegador |
| `includes/class-dxo-forms.php` | Formularios (opción `dxo_forms`): shortcode, ventana emergente y barra |
| `includes/class-dxo-stats.php` | Los números del Resumen |
| `includes/class-dxo-admin.php` | El panel: menú, recursos, iconos, gráfica y todas las acciones AJAX `dxo_*` |
| `includes/class-dxo-elementor.php` | Widget de Elementor (pinta el shortcode) |
| `admin/views/*.php` | Las pantallas: Resumen, Campañas, Editor, Suscriptores, Formularios, Informe, Ajustes |
| `assets/admin.*` | CSS y JS del panel |
| `assets/public.*` | CSS y JS de los formularios en la web |
| `tools/i18n.py`, `tools/es.py` | Saca los textos y genera el `.pot`, `.po` y `.mo` en español. No viaja en el zip |
| `tests/run.php` | Pruebas del renderizador. No viaja en el zip; el workflow las corre antes de cada release |
| `maqueta/panel.html` | La maqueta que aprobó David el 04/10/2026. No viaja en el zip |

## Cómo sale un correo

1. **Lanzar** congela la audiencia en `wp_dxo_recipients` (una fila por persona,
   cada una con su token) y registra los enlaces del correo en `wp_dxo_links`.
2. **Cada minuto** (WP-Cron, hook `dxo_send`) sale una tanda: como mucho 25
   correos y 20 segundos, y nunca más de lo que deje el **tope por hora** de
   Ajustes contando los enviados en los últimos 60 minutos (no por pasadas: aunque
   el cron se salte minutos, nunca salen más de los que tocan).
3. Antes de cada correo se mira que la persona siga activa: si se dio de baja
   entre medias, se salta.
4. **Con el panel abierto**, la franja de estado pregunta cada 20 segundos y de
   paso ejecuta una pasada (`ajax_status`). Así el envío no depende de que alguien
   visite la web para que corra WP-Cron.

**Por qué el tope:** en un hosting compartido el servidor deja salir un número de
correos por hora por dominio (en el de Dox Studio, 200). El boletín sale a 150 por
defecto para dejar sitio a pedidos, contraseñas y formularios. Y la IP del
servidor la comparten todas las webs: una lista sucia puede llevar el correo de
todas a spam, por eso la doble confirmación viene encendida.

**Si la web tiene un plugin de SMTP** (GoSMTP, WP Mail SMTP...), `wp_mail` sale
por él, y si ese plugin fuerza el remitente, manda el suyo sobre el de Ajustes.

## Lo que llega de fuera

Todo por la portada con `?dxo=<acción>&t=<token>`, no por la API REST: Hide My WP
puede cerrar `wp-json` a los visitantes. Se atiende en `init` y se marca como no
cacheable (`DONOTCACHEPAGE`, `litespeed_control_set_nocache`, `nocache_headers`).
Nunca dentro de wp-admin ni de admin-ajax (`is_admin()`).

- `subscribe` (POST): sin nonce a propósito, porque la página del formulario
  puede estar en la caché y el nonce caducaría. Lo frenan un campo trampa
  (`dxo_hp`), un mínimo de 2 segundos desde que se pinta el formulario y 5
  intentos cada 10 minutos por IP. Con `dxo_ajax=1` responde JSON; sin JS
  redirige a la página con `?dxo_s=<estado>&dxo_f=<formulario>`.
- `confirm`: pasa de `pending` a `active` y pone en cola la bienvenida si está encendida.
- `unsubscribe`: por GET enseña un botón (los antivirus del correo abren todos
  los enlaces solos y darían de baja a la gente); por POST da de baja. Gmail y
  Yahoo mandan un POST con `List-Unsubscribe=One-Click` (RFC 8058) sin cookies.
- `open`: el pixel. **Gmail y Mail del iPhone lo piden solos** al llegar el
  correo, así que la apertura es una cifra al alza; el informe lo dice.
- `click`: redirige a la URL guardada al lanzar la campaña, nunca a una que venga
  en la petición. Un clic sin apertura cuenta también como apertura.
- `view`: el correo de esa persona en el navegador, sin seguimiento.

## Trampas que ya conté

- **Un `<form>` dentro de otro** cierra el de fuera antes de tiempo. La vista
  previa del formulario en el panel va dentro del formulario de edición: por eso
  en modo `preview` se pinta con `<div>` y sin `name` en los campos. Con `name`,
  el campo oculto `dxo=subscribe` viajaba con cada guardado.
- **Los scripts del pie se imprimen en `wp_footer` con prioridad 20.** La ventana
  emergente y la barra se pintan en `wp_footer` con prioridad 5; a la 20, el JS
  se encolaba tarde y la ventana no salía nunca.
- **`.dxo-app svg { width: 16px }`** es para los iconos: la gráfica necesita su
  regla con más peso (`.dxo-app svg.dxo-chart`).
- **Un `display: grid` gana al atributo `hidden`.** El panel lleva
  `.dxo-app [hidden] { display: none !important }`.
- **Las reglas de párrafo del tema (o de wp-admin) ganan a `.dxo-title`.** En
  `public.css` los textos del formulario van con dos clases (`.dxo-box .dxo-title`).
- **El editor redibuja los bloques** al abrir uno: el campo al que dar el foco
  está en el elemento nuevo, no en el que recibió el clic.
- **Las fechas de la gráfica van en HTML** debajo del SVG: el SVG se estira a lo
  ancho (`preserveAspectRatio="none"`) y el texto dentro salía deformado en el móvil.
- `__( '%s person' )` no encuentra la traducción si ese texto existe también como
  plural (`_n`): el `.mo` lo guarda junto a su plural. Para esos, siempre `_n()`.

## Traducción

El código está en inglés y trae el español (`languages/dox-orbit-es_ES.*`).
WordPress no pasa de es_CO o es_MX a es_ES solo: el filtro de
`load_textdomain_mofile` del archivo principal lo hace. Para un texto nuevo,
añadir su traducción en `tools/es.py` y correr `python3 tools/i18n.py`.

## Probar en local

Funciona en un WordPress con SQLite (el plugin oficial *SQLite Database
Integration*) y `php -S`, sin MySQL. Con un mu-plugin que enganche `pre_wp_mail`
y guarde cada correo en un archivo se ve exactamente lo que recibiría la gente sin
enviar nada. El servidor de PHP atiende de una en una, así que el editor va algo
lento ahí.

## Desinstalar

Borrar el plugin **no borra** la lista ni las campañas (como Dox POS): solo quita
la tarea programada. Las tablas `wp_dxo_*` y las opciones `dxo_*` se borran a mano.

## Pendiente

- Casilla "Quiero recibir novedades" en el checkout de WooCommerce.
- Rebotes y quejas de Amazon SES por SNS (como `acg_mail`).
- Filtro de bots en los clics (los antivirus de Outlook abren todos los enlaces).
