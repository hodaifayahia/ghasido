// Generic screenshot runner for the harness server (spec 0003, Part I).
//
//   node storage/harness/shoot.mjs storage/harness/jobs/<name>.json
//
// Job file shape:
// {
//   "base": "http://127.0.0.1:8090",            // optional
//   "out": "storage/harness/shots",             // optional
//   "sessions": [
//     {
//       "login": "samira", "password": "password",   // posted to the login form's `email` + `password` fields
//       "jobs": [
//         { "name": "home-1280x853", "path": "/learn", "size": [1280, 853] },
//         { "name": "step-2", "path": "/learn/lessons/1/steps/2", "size": [1280, 853], "click": "text=Next", "wait": 800, "fullPage": false }
//       ]
//     }
//   ]
// }
// Every job prints the HTTP status, the page title, the document scroll size
// (to spot horizontal overflow) and any console errors.
import { chromium } from 'playwright-core';
import { mkdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const jobFile = process.argv[2];
if (!jobFile) {
    console.error('usage: node shoot.mjs <jobs.json>');
    process.exit(1);
}
const spec = JSON.parse(readFileSync(jobFile, 'utf8'));
const BASE = spec.base ?? 'http://127.0.0.1:8090';
const OUT = resolve(spec.out ?? 'storage/harness/shots');
mkdirSync(OUT, { recursive: true });

const EXE =
    process.env.PW_CHROME ??
    `${process.env.HOME}/.cache/ms-playwright/chromium-1243/chrome-linux64/chrome`;

const browser = await chromium.launch({
    executablePath: EXE,
    args: [
        '--no-sandbox',
        '--use-fake-ui-for-media-stream',
        '--use-fake-device-for-media-stream',
    ],
});

for (const session of spec.sessions) {
    const context = await browser.newContext({
        viewport: { width: 1280, height: 853 },
        deviceScaleFactor: session.scale ?? 1,
        permissions: ['microphone'],
    });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (m) => {
        if (m.type() === 'error') errors.push(m.text());
    });
    page.on('pageerror', (e) => errors.push(String(e)));

    if (session.login) {
        await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
        await page.fill('input[name="email"]', session.login);
        await page.fill(
            'input[name="password"]',
            session.password ?? 'password',
        );
        await Promise.all([
            page.waitForURL((u) => !u.pathname.includes('/login'), {
                timeout: 30000,
            }),
            page.click('button[type="submit"]'),
        ]);
    }

    for (const job of session.jobs) {
        const [w, h] = job.size ?? [1280, 853];
        await page.setViewportSize({ width: w, height: h });
        errors.length = 0;
        let status = '-';
        if (job.path) {
            const res = await page.goto(`${BASE}${job.path}`, {
                waitUntil: 'networkidle',
            });
            status = res?.status() ?? '-';
        }
        for (const step of job.steps ?? []) {
            if (step.click) await page.click(step.click);
            if (step.fill) await page.fill(step.fill[0], step.fill[1]);
            if (step.press) await page.keyboard.press(step.press);
            if (step.eval) await page.evaluate(step.eval);
            if (step.focus) await page.focus(step.focus);
            if (step.wait) await page.waitForTimeout(step.wait);
            if (step.waitFor)
                await page.waitForSelector(step.waitFor, { timeout: 20000 });
        }
        if (job.click) await page.click(job.click);
        await page.waitForTimeout(job.wait ?? 700);
        await page.screenshot({
            path: `${OUT}/${job.name}.png`,
            fullPage: Boolean(job.fullPage),
        });
        const dims = await page.evaluate(() => ({
            scrollW: document.documentElement.scrollWidth,
            clientW: document.documentElement.clientWidth,
            scrollH: document.documentElement.scrollHeight,
            clientH: document.documentElement.clientHeight,
            title: document.title,
            url: location.pathname,
        }));
        const overflow =
            dims.scrollW > dims.clientW ? ' HORIZONTAL-OVERFLOW' : '';
        console.log(
            `${job.name} -> ${status} ${dims.url} "${dims.title}" ${dims.scrollW}x${dims.scrollH} (view ${dims.clientW}x${dims.clientH})${overflow}` +
                (errors.length
                    ? `\n   console: ${errors.slice(0, 5).join(' || ')}`
                    : ''),
        );
    }
    await context.close();
}

await browser.close();
