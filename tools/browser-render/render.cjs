/**
 * Render one allowlisted page and print its HTML.
 *
 * A browser is a hole in every guarantee the HTTP transport provides. It will
 * fetch whatever a page asks for — analytics, fonts, third-party scripts — from
 * hosts nobody authorised, which is precisely what the allowlist, the address
 * pinning and the fail-closed egress exist to prevent. So the guard is rebuilt
 * inside the browser: every request the page makes is inspected and aborted
 * unless its host was allowlisted for this endpoint.
 *
 * Used only where a publisher renders its document list with JavaScript. The
 * gazette, IMED and parliament all serve a shell whose links are not in the
 * HTML, so static discovery finds forty links and no documents.
 *
 * Usage: render.js <url> <comma-separated-allowed-hosts> [timeoutMs] [maxBytes]
 * Output: rendered HTML on stdout; a reason on stderr with a non-zero exit.
 */

const { chromium } = require('playwright-core');

async function main() {
  const [url, allowedHosts, timeoutArg, maxBytesArg] = process.argv.slice(2);

  if (!url || !allowedHosts) {
    process.stderr.write('expected a url and an allowlist\n');
    return 1;
  }

  const allowed = new Set(
    allowedHosts.split(',').map((host) => host.trim().toLowerCase()).filter(Boolean),
  );
  const timeout = Number.parseInt(timeoutArg || '30000', 10);
  const maxBytes = Number.parseInt(maxBytesArg || '5242880', 10);

  const browser = await chromium.launch({
    headless: true,
    // No sandbox concessions and no remote debugging: this renders untrusted
    // pages and should be able to do nothing but read them.
    args: ['--disable-dev-shm-usage', '--disable-extensions', '--disable-plugins'],
  });

  let blocked = 0;

  try {
    const context = await browser.newContext({
      userAgent: process.env.CIVICLENS_USER_AGENT || 'CivicLensBot/2.0',
      javaScriptEnabled: true,
      acceptDownloads: false,
      bypassCSP: false,
    });

    // The allowlist, enforced per request rather than per navigation. A page
    // that pulls a script from an unauthorised host simply does not get it.
    await context.route('**/*', (route) => {
      let host = '';

      try {
        host = new URL(route.request().url()).hostname.toLowerCase();
      } catch {
        return route.abort();
      }

      if (!allowed.has(host)) {
        blocked += 1;
        return route.abort();
      }

      return route.continue();
    });

    const page = await context.newPage();
    page.setDefaultTimeout(timeout);

    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout });

    if (!response) {
      process.stderr.write('the page did not respond\n');
      return 1;
    }

    if (response.status() >= 400) {
      process.stderr.write(`the page returned HTTP ${response.status()}\n`);
      return 1;
    }

    // Settle rather than networkidle: a page holding a long poll open never
    // goes idle, and waiting for that would hang every crawl of it.
    await page.waitForTimeout(Math.min(3000, timeout / 4));

    const html = await page.content();

    if (Buffer.byteLength(html, 'utf8') > maxBytes) {
      process.stderr.write(`the rendered page exceeded ${maxBytes} bytes\n`);
      return 1;
    }

    process.stdout.write(html);
    process.stderr.write(`blocked ${blocked} off-allowlist request(s)\n`);

    return 0;
  } finally {
    await browser.close();
  }
}

main()
  .then((code) => process.exit(code))
  .catch((error) => {
    process.stderr.write(`${error && error.message ? error.message : error}\n`);
    process.exit(1);
  });
