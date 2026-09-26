// Load test (client request 2026-09-26): N different employees sign in at the same time and each browses
// the learner pages R rounds. Reports throughput, latency percentiles and
// errors per page. Usage: node load.mjs <users> <rounds>
import fs from 'node:fs';

const base = process.env.BASE ?? 'http://127.0.0.1:8000';
const USERS = Number(process.argv[2] ?? 50);
const ROUNDS = Number(process.argv[3] ?? 5);
const accounts = JSON.parse(
    fs.readFileSync(process.env.USERS_FILE ?? 'load-users.json', 'utf8'),
).slice(
    Number(process.env.OFFSET ?? 0),
    Number(process.env.OFFSET ?? 0) + USERS,
);
const pages = [
    '/learn',
    '/learn/lessons',
    '/learn/progress',
    '/learn/phrasebook',
    '/learn/messages',
];

const stats = {};
let version = null;

function note(key, ms, ok) {
    stats[key] ??= { times: [], errors: 0 };
    stats[key].times.push(ms);
    if (!ok) stats[key].errors++;
}

class Client {
    cookies = new Map();
    header() {
        return [...this.cookies].map(([k, v]) => `${k}=${v}`).join('; ');
    }
    keep(res) {
        for (const c of res.headers.getSetCookie?.() ?? []) {
            const [pair] = c.split(';');
            const i = pair.indexOf('=');
            this.cookies.set(pair.slice(0, i), pair.slice(i + 1));
        }
    }
    async req(key, path, opts = {}) {
        const t = performance.now();
        let ok = false;
        try {
            const res = await fetch(base + path, {
                redirect: 'manual',
                ...opts,
                headers: { Cookie: this.header(), ...opts.headers },
            });
            this.keep(res);
            await res.arrayBuffer();
            ok = res.status < 400;
            if (!ok) note('status:' + res.status, 0, false);
            return res;
        } catch {
            note('network', 0, false);
        } finally {
            note(key, performance.now() - t, ok);
        }
    }
    xsrf() {
        return decodeURIComponent(this.cookies.get('XSRF-TOKEN') ?? '');
    }
}

async function user(account) {
    const c = new Client();
    await c.req('GET /login', '/login');
    const res = await c.req('POST /login', '/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/html',
            'X-XSRF-TOKEN': c.xsrf(),
        },
        body: JSON.stringify({ email: account, password: 'password' }),
    });
    if (
        !res ||
        res.status !== 302 ||
        String(res.headers.get('location')).includes('/login')
    ) {
        note('login failed', 0, false);
        return;
    }
    for (let r = 0; r < ROUNDS; r++) {
        for (const p of pages) {
            // Full page loads (first visit), then Inertia XHR visits like the SPA does.
            const inertia = r > 0 && version;
            await c.req(
                (inertia ? 'XHR ' : 'GET ') + p,
                p,
                inertia
                    ? {
                          headers: {
                              'X-Inertia': 'true',
                              'X-Inertia-Version': version,
                              'X-Requested-With': 'XMLHttpRequest',
                              Accept: 'text/html, application/xhtml+xml',
                          },
                      }
                    : {},
            );
        }
        await c.req('POST /meaning', '/meaning', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': c.xsrf(),
            },
            body: JSON.stringify({
                text: 'Welcome to the hotel, how can I help you?',
            }),
        });
    }
}

// The Inertia asset version, so XHR visits are real SPA visits.
{
    const html = await (await fetch(base + '/login')).text();
    const m =
        html.match(/&quot;version&quot;:&quot;([^&]*)&quot;/) ??
        html.match(/"version":"([^"]*)"/);
    version = m ? m[1] : '';
}

const started = performance.now();
await Promise.all(accounts.map(user));
const seconds = (performance.now() - started) / 1000;

const pct = (a, p) =>
    a[Math.min(a.length - 1, Math.floor((p / 100) * a.length))];
let total = 0;
let errors = 0;
const rows = Object.entries(stats)
    .map(([k, v]) => {
        const t = v.times.filter((x) => x > 0).sort((a, b) => a - b);
        total += v.times.length;
        errors += v.errors;
        return {
            page: k,
            n: v.times.length,
            errors: v.errors,
            p50: Math.round(pct(t, 50) ?? 0),
            p95: Math.round(pct(t, 95) ?? 0),
            max: Math.round(t.at(-1) ?? 0),
        };
    })
    .sort((a, b) => a.page.localeCompare(b.page));
console.table(rows);
console.log(
    JSON.stringify({
        users: accounts.length,
        rounds: ROUNDS,
        requests: total,
        errors,
        seconds: +seconds.toFixed(1),
        reqPerSec: +(total / seconds).toFixed(1),
    }),
);
