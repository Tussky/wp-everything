#!/usr/bin/env python3
"""Extract WordPress i18n strings from PHP files and write a .pot template.

This is a lightweight fallback when WP-CLI `wp i18n make-pot` is not available.
It scans for __( ..., 'domain' ), _e( ..., 'domain' ), esc_html__( ..., 'domain' ),
esc_attr__( ..., 'domain' ), esc_attr_e( ..., 'domain' ), _n( ... 'domain' ),
and _x( ..., 'domain' ) calls.
"""

import argparse
import os
import re
import sys
from datetime import datetime, timezone


DOMAIN = 'site-map-redirects'

# Regex for WordPress i18n functions. We capture the first string argument(s).
PATTERNS = [
    # translators: comment preceding the call.
    re.compile(r'//?\s*translators:\s*(?P<comment>.*)\n\s*(?P<fn>__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_ex)\s*\(\s*(?P<text>\'(?:[^\'\\]|\\.)*\'|"(?:[^"\\]|\\.)*")\s*,\s*\'?(?P<domain>[^\'"\)]+)\'?\s*\)', re.IGNORECASE | re.MULTILINE),
    re.compile(r'(?P<fn>__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*\(\s*(?P<text>\'(?:[^\'\\]|\\.)*\'|"(?:[^"\\]|\\.)*")\s*,\s*\'?(?P<domain>[^\'"\)]+)\'?\s*\)', re.IGNORECASE),
    # _n( single, plural, count, domain )
    re.compile(r'(?P<fn>_n)\s*\(\s*(?P<singular>\'(?:[^\'\\]|\\.)*\'|"(?:[^"\\]|\\.)*")\s*,\s*(?P<plural>\'(?:[^\'\\]|\\.)*\'|"(?:[^"\\]|\\.)*")\s*,\s*[^,\)]+\s*,\s*\'?(?P<domain>[^\'"\)]+)\'?\s*\)', re.IGNORECASE),
    # _x( text, context, domain )
    re.compile(r'(?P<fn>_x|_ex)\s*\(\s*(?P<text>\'(?:[^\'\\]|\\.)*\'|"(?:[^"\\]|\\.)*")\s*,\s*(?P<context>\'(?:[^\'\\]|\\.)*\'|"(?:[^"\\]|\\.)*")\s*,\s*\'?(?P<domain>[^\'"\)]+)\'?\s*\)', re.IGNORECASE),
    # translators: followed by sprintf( __( ..., 'domain' ), ... )
    re.compile(r'translators:\s*(?P<comment>.*)\n\s*[^\n]*?(?P<fn>__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*\(\s*(?P<text>\'(?:[^\'\\]|\\.)*\'|"(?:[^"\\]|\\.)*")\s*,\s*\'?(?P<domain>[^\'"\)]+)\'?\s*\)', re.IGNORECASE | re.MULTILINE),
]


def strip_quotes(s: str) -> str:
    """Strip surrounding single or double quotes and unescape."""
    if not s:
        return ''
    s = s[1:-1]  # remove outer quotes
    # PHP escape sequences don't matter for printable ASCII; just handle \\ and \\'
    s = s.replace('\\\\', '\\').replace("\\'", "'").replace('\\"', '"')
    return s


def find_php_files(root):
    for dirpath, _dirnames, filenames in os.walk(root):
        for name in filenames:
            if name.endswith('.php'):
                yield os.path.join(dirpath, name)


def extract_strings(root):
    """Return a dict mapping msgid -> {ref, comment, context, plural}."""
    results = {}

    for path in find_php_files(root):
        rel = os.path.relpath(path, root)
        with open(path, 'r', encoding='utf-8', errors='replace') as f:
            content = f.read()

        for pattern in PATTERNS:
            for m in pattern.finditer(content):
                domain_arg = m.group('domain')
                # Allow quotes around domain.
                d = domain_arg.strip("'\"")
                if d != DOMAIN:
                    continue

                fn = m.group('fn').lower()
                if fn == '_n':
                    key = strip_quotes(m.group('singular'))
                    plural = strip_quotes(m.group('plural'))
                else:
                    key = strip_quotes(m.group('text'))
                    plural = None

                context = strip_quotes(m.group('context')) if 'context' in m.groupdict() and m.group('context') else ''
                comment = m.group('comment').strip() if 'comment' in m.groupdict() and m.group('comment') else ''

                line = content[:m.start()].count('\n') + 1
                ref = f'{rel}:{line}'

                entry = results.setdefault(
                    (key, context),
                    {'msgid': key, 'refs': [], 'comment': set(), 'plural': plural, 'context': context}
                )
                entry['refs'].append(ref)
                if comment:
                    entry['comment'].add(comment)

    return results


def escape_pot_string(s: str) -> str:
    """Escape a string for .pot output."""
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\r', '\\r').replace('\t', '\\t')


def write_pot(results, out_path):
    refs_by_entry = list(results.values())
    refs_by_entry.sort(key=lambda e: e['refs'][0])

    date = datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M+0000')

    lines = [
        '# Copyright (C) 2026 Isaac Anderson',
        '# This file is distributed under the same license as the SiteMap Redirects package.',
        f'# SiteMap Redirects POT file',
        '#',
        f'msgid ""',
        f'msgstr ""',
        f'"Project-Id-Version: SiteMap Redirects 1.0.0\\n"',
        f'"Report-Msgid-Bugs-To: https://github.com/Tussky/paperclip-trial/issues\\n"',
        f'"POT-Creation-Date: {date}\\n"',
        f'"PO-Revision-Date: {date}\\n"',
        f'"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"',
        f'"Language-Team: LANGUAGE <LL@li.org>\\n"',
        f'"Language: \\n"',
        f'"MIME-Version: 1.0\\n"',
        f'"Content-Type: text/plain; charset=UTF-8\\n"',
        f'"Content-Transfer-Encoding: 8bit\\n"',
        '',
    ]

    for entry in refs_by_entry:
        for r in sorted(set(entry['refs'])):
            lines.append(f'#: {r}')
        for c in sorted(entry['comment']):
            lines.append(f'#. translators: {c}')
        if entry['context']:
            lines.append(f'msgctxt "{escape_pot_string(entry["context"])}"')
        lines.append(f'msgid "{escape_pot_string(entry["msgid"])}"')
        if entry['plural']:
            lines.append(f'msgid_plural "{escape_pot_string(entry["plural"])}"')
            lines.append('msgstr[0] ""')
            lines.append('msgstr[1] ""')
        else:
            lines.append('msgstr ""')
        lines.append('')

    os.makedirs(os.path.dirname(os.path.abspath(out_path)), exist_ok=True)
    with open(out_path, 'w', encoding='utf-8') as f:
        f.write('\n'.join(lines))


def main():
    parser = argparse.ArgumentParser(description='Extract WordPress i18n strings to a .pot file')
    parser.add_argument('--dir', default='.', help='Root directory to scan for .php files')
    parser.add_argument('--out', default=f'languages/{DOMAIN}.pot', help='Output .pot path')
    args = parser.parse_args()

    root = os.path.abspath(args.dir)
    out_path = os.path.abspath(args.out)

    results = extract_strings(root)
    write_pot(results, out_path)
    print(f'Extracted {len(results)} string(s) to {out_path}')


if __name__ == '__main__':
    main()
