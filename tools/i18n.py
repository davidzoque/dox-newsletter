#!/usr/bin/env python3
"""
Traducción del plugin. No viaja en el zip (el workflow excluye tools/).

    python3 tools/i18n.py

1. Saca de los PHP todos los textos con el dominio dox-orbit.
2. Avisa de los que no tienen traducción en tools/es.py (y de los que sobran).
3. Escribe languages/dox-orbit.pot y el .po y .mo de es_ES (necesita msgfmt).

Para un texto nuevo: escribirlo en inglés en el código, añadir su traducción a
tools/es.py (ES para los normales, PL para los de _n) y volver a correr esto.
"""
import os, re, subprocess, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from es import ES, PL  # noqa: E402

S = r"'((?:[^'\\]|\\.)*)'"
single = re.compile(r"\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*" + S + r"\s*,\s*'dox-orbit'\s*\)")
plural = re.compile(r"\b_n\(\s*" + S + r"\s*,\s*" + S + r"\s*,[^,]+,\s*'dox-orbit'\s*\)")

strings, plurals = set(), {}
for dp, dn, fn in os.walk(ROOT):
    if any(x in dp for x in ('vendor', 'dox-core', '.git', 'tools', 'maqueta')):
        continue
    for f in fn:
        if f.endswith('.php'):
            t = open(os.path.join(dp, f), encoding='utf-8').read()
            strings.update(m.group(1).replace("\\'", "'") for m in single.finditer(t))
            for m in plural.finditer(t):
                plurals[m.group(1).replace("\\'", "'")] = m.group(2).replace("\\'", "'")
strings -= set(plurals)

missing = sorted(s for s in strings if s not in ES) + sorted(p for p in plurals if p not in PL)
unused = sorted(s for s in ES if s not in strings and s not in plurals)
if missing:
    print('Sin traducir:')
    for m in missing:
        print('  ' + m)
if unused:
    print('Sobran en es.py:')
    for u in unused:
        print('  ' + u)
emdash = [k for k, v in ES.items() if '—' in v]
if emdash:
    print('Con guion largo (no se permite):', emdash)


def q(s):
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"') + '"'


head = ('msgid ""\nmsgstr ""\n"Project-Id-Version: Dox Orbit\\n"\n"Language: {lang}\\n"\n"MIME-Version: 1.0\\n"\n'
        '"Content-Type: text/plain; charset=UTF-8\\n"\n"Content-Transfer-Encoding: 8bit\\n"\n'
        '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n"X-Domain: dox-orbit\\n"\n\n')
pot, po = head.format(lang=''), head.format(lang='es_ES')
for s in sorted(strings):
    pot += f'msgid {q(s)}\nmsgstr ""\n\n'
    po += f'msgid {q(s)}\nmsgstr {q(ES.get(s, ""))}\n\n'
for a, b in sorted(plurals.items()):
    tr = PL.get(a, ['', ''])
    pot += f'msgid {q(a)}\nmsgid_plural {q(b)}\nmsgstr[0] ""\nmsgstr[1] ""\n\n'
    po += f'msgid {q(a)}\nmsgid_plural {q(b)}\nmsgstr[0] {q(tr[0])}\nmsgstr[1] {q(tr[1])}\n\n'

lang = os.path.join(ROOT, 'languages')
open(os.path.join(lang, 'dox-orbit.pot'), 'w', encoding='utf-8').write(pot)
open(os.path.join(lang, 'dox-orbit-es_ES.po'), 'w', encoding='utf-8').write(po)
subprocess.run(['msgfmt', '-o', os.path.join(lang, 'dox-orbit-es_ES.mo'), os.path.join(lang, 'dox-orbit-es_ES.po')], check=True)
print(f'{len(strings)} textos y {len(plurals)} plurales. Listo.')
