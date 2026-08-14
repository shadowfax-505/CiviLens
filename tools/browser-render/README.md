# Browser render worker

Renders one allowlisted page with Playwright and prints its HTML, for publishers
whose document lists are built by JavaScript and are therefore absent from the
HTML the HTTP transport receives.

A browser bypasses the allowlist, the address pinning and the fail-closed egress
that every other fetch in this system is subject to, so those properties are
rebuilt here: every request the page makes is aborted unless its host was
allowlisted for that endpoint, downloads are refused, and the rendered document
is capped.

Requires the Playwright browser binary:

```bash
npx playwright install chromium
```

Configure `INGESTION_BROWSER_NODE` to point at a Node binary. Without it the
provider reports the renderer as unavailable and the connector fails closed,
exactly as it did before this existed.
