/* Dox Orbit: envío del formulario sin recargar, ventana emergente y barra.
   Sin dependencias. Si este archivo no carga, el formulario funciona igual:
   envía por POST y la página vuelve con el resultado. */
(function () {
	'use strict';
	var cfg = window.DXO || {};
	var DAYS = 14; // tras cerrar una ventana o barra, no vuelve a salir en 14 días

	function store(key, val) {
		try {
			if (val === undefined) return window.localStorage.getItem(key);
			window.localStorage.setItem(key, val);
		} catch (e) { return null; }
	}
	function dismissed(slug) {
		var t = parseInt(store('dxo_closed_' + slug) || '0', 10);
		return t && Date.now() - t < DAYS * 864e5;
	}
	function subscribed() { return store('dxo_subscribed') === '1'; }

	// ─── Envío ───
	function bind(form) {
		form.addEventListener('submit', function (e) {
			if (!window.fetch || !window.FormData) return; // sin fetch, envío normal
			e.preventDefault();
			var msg = form.querySelector('.dxo-msg');
			var email = form.querySelector('input[name="email"]');
			if (email && !email.checkValidity()) {
				show(msg, email.validationMessage || cfg.error, false);
				email.focus();
				return;
			}
			var data = new FormData(form);
			data.append('dxo_ajax', '1');
			form.classList.add('is-busy');
			fetch(cfg.url || form.action, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					form.classList.remove('is-busy');
					show(msg, res.message || cfg.error, !!res.ok);
					if (res.ok) {
						form.classList.add('is-done');
						store('dxo_subscribed', '1');
					}
				})
				.catch(function () {
					form.classList.remove('is-busy');
					show(msg, cfg.error, false);
				});
		});
	}
	function show(el, text, ok) {
		if (!el) return;
		el.hidden = false;
		el.textContent = text;
		el.classList.toggle('is-ok', ok);
		el.classList.toggle('is-error', !ok);
	}

	// ─── Ventana emergente ───
	function popup(el) {
		var slug = el.getAttribute('data-dxo-slug');
		if (dismissed(slug) || subscribed()) return;
		var opened = false;
		function open() {
			if (opened) return;
			opened = true;
			el.hidden = false;
			requestAnimationFrame(function () { requestAnimationFrame(function () { el.classList.add('is-open'); }); });
			var input = el.querySelector('input[name="email"]');
			if (input) setTimeout(function () { input.focus({ preventScroll: true }); }, 320);
			document.addEventListener('keydown', esc);
		}
		function close() {
			el.classList.add('is-closing');
			el.classList.remove('is-open');
			store('dxo_closed_' + slug, String(Date.now()));
			document.removeEventListener('keydown', esc);
			setTimeout(function () { el.hidden = true; el.classList.remove('is-closing'); }, 220);
		}
		function esc(e) { if (e.key === 'Escape') close(); }
		el.querySelectorAll('[data-dxo-close]').forEach(function (b) { b.addEventListener('click', close); });

		var trigger = el.getAttribute('data-trigger');
		var value = parseInt(el.getAttribute('data-value') || '60', 10);
		if (trigger === 'delay') {
			setTimeout(open, value * 1000);
		} else {
			var onScroll = function () {
				var h = document.documentElement.scrollHeight - window.innerHeight;
				if (h <= 0 || (window.scrollY / h) * 100 >= value) {
					window.removeEventListener('scroll', onScroll);
					open();
				}
			};
			window.addEventListener('scroll', onScroll, { passive: true });
		}
	}

	// ─── Barra inferior ───
	function bar(el) {
		var slug = el.getAttribute('data-dxo-slug');
		if (dismissed(slug) || subscribed()) return;
		el.hidden = false;
		setTimeout(function () { el.classList.add('is-open'); }, 1200);
		el.querySelectorAll('[data-dxo-close]').forEach(function (b) {
			b.addEventListener('click', function () {
				el.classList.remove('is-open');
				store('dxo_closed_' + slug, String(Date.now()));
				setTimeout(function () { el.hidden = true; }, 500);
			});
		});
	}

	function boot() {
		document.querySelectorAll('.dxo-form').forEach(bind);
		document.querySelectorAll('.dxo-popup').forEach(popup);
		document.querySelectorAll('.dxo-bar').forEach(bar);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
