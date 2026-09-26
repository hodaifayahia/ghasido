// Lists interface text in Vue templates that is not wrapped in $t()/t()
// (I18N-02). Usage: node scripts/i18n/untranslated.mjs [--fix] [paths...]
// Prints `file:line  kind  text`; exits 1 when anything is found.
// --fix wraps the safe cases in place: a static attribute, and a text node
// that is its element's only content. Mixed text (text beside elements or
// {{ }}), script strings and plurals stay for a person to word properly.
import { parse as parseSfc } from '@vue/compiler-sfc';
import fs from 'node:fs';
import path from 'node:path';

const ATTRS = new Set([
    'aria-label',
    'alt',
    'title',
    'placeholder',
    'label',
    'description',
    'hint',
    'empty-text',
    'empty-title',
    'empty-description',
    'subtitle',
    'heading',
    'tooltip',
    'confirm-label',
    'cancel-label',
    'text',
    'button-text',
    'eyebrow',
    'caption',
    'aria-description',
]);
// Text that is not language: brand, units, symbols, numbers, codes.
const IGNORE =
    /^(GHASIDO|Guesvia|EN|AR|DZD|USD|AI|CSV|PDF|XLSX|OK|ID|URL|WhatsApp|[\d\s.,:;%/+×x#·•\-–—→←↑↓()[\]|*&$€£@!?"'’“”…]+)$/;
const SKIP = ['/components/ui/', '/routes/', '/actions/', '/wayfinder/'];

function files(args) {
    const out = [];
    const walk = (p) => {
        const stat = fs.statSync(p);
        if (stat.isDirectory()) {
            for (const f of fs.readdirSync(p)) walk(path.join(p, f));
        } else if (p.endsWith('.vue') && !SKIP.some((s) => p.includes(s))) {
            out.push(p);
        }
    };
    (args.length ? args : ['resources/js']).forEach(walk);
    return out;
}

function hasLetters(text) {
    const trimmed = text.trim();
    return (
        /[A-Za-z]{2,}/.test(trimmed) &&
        !IGNORE.test(trimmed) &&
        // e-mail addresses, usernames, file names, CSS-ish identifiers
        !/^[\w.+-]+@[\w.-]+$/.test(trimmed) &&
        !/^[a-z0-9]+([._-][a-z0-9]+)+$/.test(trimmed)
    );
}

const FIX = process.argv.includes('--fix');

function quote(text) {
    return "'" + text.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

let found = 0;
for (const file of files(process.argv.slice(2).filter((a) => a !== '--fix'))) {
    const source = fs.readFileSync(file, 'utf8');
    const { descriptor } = parseSfc(source, { filename: file });
    const edits = [];
    if (!descriptor.template?.ast) continue;
    const report = (node, kind, text) => {
        found++;
        console.log(
            `${file}:${node.loc.start.line}\t${kind}\t${text.replace(/\s+/g, ' ').trim().slice(0, 100)}`,
        );
    };
    const alone = (parent, node) =>
        (parent?.children ?? []).filter(
            (c) =>
                c !== node &&
                c.type !== 3 &&
                !(c.type === 2 && !c.content.trim()),
        ).length === 0;
    const visit = (node, inSkip, parent) => {
        if (!node) return;
        const skipHere =
            inSkip ||
            (node.props ?? []).some(
                (p) =>
                    (p.type === 6 &&
                        p.name === 'lang' &&
                        p.value?.content !== 'en') ||
                    (p.type === 6 && p.name === 'data-i18n-skip'),
            ) ||
            node.tag === 'code' ||
            node.tag === 'pre';
        if (node.type === 2 && !inSkip && hasLetters(node.content)) {
            if (FIX && alone(parent, node) && !/[{}<>]/.test(node.content)) {
                const text = node.content.trim().replace(/\s+/g, ' ');
                edits.push([
                    node.loc.start.offset,
                    node.loc.end.offset,
                    node.content.replace(
                        node.content.trim(),
                        `{{ $t(${quote(text)}) }}`,
                    ),
                ]);
            } else {
                report(node, 'text', node.content);
            }
        }
        if (node.type === 1 && !skipHere) {
            for (const prop of node.props) {
                // <TransText text="..."> takes the English key itself.
                if (
                    prop.type === 6 &&
                    ATTRS.has(prop.name) &&
                    prop.value &&
                    hasLetters(prop.value.content) &&
                    !(node.tag === 'TransText' && prop.name === 'text')
                ) {
                    if (FIX && !prop.value.content.includes('"')) {
                        const text = prop.value.content
                            .trim()
                            .replace(/\s+/g, ' ');
                        edits.push([
                            prop.loc.start.offset,
                            prop.loc.end.offset,
                            `:${prop.name}="$t(${quote(text)})"`,
                        ]);
                    } else {
                        report(prop, `@${prop.name}`, prop.value.content);
                    }
                }
                // :attr="'Literal'" with a bare string literal.
                if (
                    prop.type === 7 &&
                    prop.name === 'bind' &&
                    prop.arg?.content &&
                    ATTRS.has(prop.arg.content)
                ) {
                    const exp = prop.exp?.content?.trim() ?? '';
                    const literal = /^(['"`])([^'"`$]*)\1$/.exec(exp);
                    if (literal && hasLetters(literal[2]))
                        report(prop, `:${prop.arg.content}`, literal[2]);
                }
            }
        }
        // String literals rendered in interpolations: {{ ok ? 'Saved' : 'Save' }}
        if (node.type === 5 && !skipHere) {
            const exp = node.content?.content ?? '';
            const stripped = exp.replace(
                /\$?t[ck]?\(\s*(['"`])(?:\\.|(?!\1).)*\1/g,
                '',
            );
            for (const m of stripped.matchAll(/(['"`])((?:\\.|(?!\1).)*)\1/g)) {
                if (hasLetters(m[2]) && !/^[a-z0-9_.-]+$/.test(m[2]))
                    report(node, 'expr', m[2]);
            }
        }
        for (const child of node.children ?? []) visit(child, skipHere, node);
        if (node.branches)
            node.branches.forEach((b) => visit(b, skipHere, parent));
    };
    visit(descriptor.template.ast, false, null);

    if (FIX && edits.length) {
        let out = source;
        for (const [start, end, text] of edits.sort((a, b) => b[0] - a[0])) {
            out = out.slice(0, start) + text + out.slice(end);
        }
        fs.writeFileSync(file, out);
        console.error(`fixed ${edits.length} in ${file}`);
    }

    // Script strings that read like interface copy: a capital letter and a
    // space ("On request"), not already inside t()/tc()/tk(), not a comment.
    for (const block of [descriptor.script, descriptor.scriptSetup]) {
        if (!block) continue;
        const offset = block.loc.start.line - 1;
        block.content.split('\n').forEach((line, index) => {
            const code = line.replace(/\/\/.*$/, '');
            if (/^\s*(\*|\/\*|import\b)/.test(code)) return;
            const stripped = code.replace(
                /\b(t|tc|tk|__)\(\s*(['"`])(?:\\.|(?!\2).)*\2/g,
                '',
            );
            for (const m of stripped.matchAll(/(['"`])((?:\\.|(?!\1).)*)\1/g)) {
                const text = m[2];
                if (
                    /^[A-Z][A-Za-z’']*(\s|,|\.|…|!|\?)/.test(text) &&
                    /[a-z]{2,}/.test(text) &&
                    !/[{}<>=]|\$\{/.test(text) &&
                    !IGNORE.test(text)
                ) {
                    found++;
                    console.log(
                        `${file}:${offset + index + 1}\tscript\t${text.slice(0, 100)}`,
                    );
                }
            }
        });
    }
}
process.exit(found ? 1 : 0);
