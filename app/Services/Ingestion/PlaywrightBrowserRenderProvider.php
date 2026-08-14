<?php

namespace App\Services\Ingestion;

use App\Contracts\Ingestion\BrowserRenderProvider;
use App\Exceptions\Ingestion\AcquisitionFailed;
use App\Models\SourceEndpoint;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Render a publisher's page in a real browser, for the ones that need it.
 *
 * Several ministries serve a shell whose document list is built by JavaScript:
 * IMED's annual reports page carries forty-four links and no documents to the
 * HTTP transport, and its actual PDFs appear only once the page has run. Static
 * discovery cannot see them and no amount of guessing URLs would find them.
 *
 * A browser is also a hole in every guarantee the rest of acquisition provides.
 * It fetches whatever a page asks for, from hosts nobody allowlisted, which is
 * what the address pinning and fail-closed egress exist to prevent. So the
 * properties are rebuilt rather than waived: robots.txt is checked here before
 * the browser is launched, and the worker aborts every request the page makes to
 * a host outside this endpoint's allowlist — eleven of them on the first page
 * rendered.
 *
 * Optional at runtime. Without a configured Node binary this reports the
 * renderer as unavailable and the connector fails closed, exactly as it did
 * before.
 */
class PlaywrightBrowserRenderProvider implements BrowserRenderProvider
{
    public function __construct(private readonly SafeHttpTransport $transport) {}

    public function render(SourceEndpoint $endpoint): string
    {
        $node = (string) config('civiclens.ingestion.browser.node', '');
        $script = (string) config('civiclens.ingestion.browser.script', base_path('tools/browser-render/render.cjs'));

        if ($node === '' || ! is_executable($node) || ! is_readable($script)) {
            throw new AcquisitionFailed('The isolated browser-render worker is not configured.');
        }

        // Before the browser exists, not after. The worker does not speak to the
        // transport, so this is the only place the publisher's own rules can be
        // applied to a rendered fetch.
        $this->transport->assertMayFetch($endpoint->base_url, $endpoint);

        $hosts = collect(is_array($endpoint->allowed_hosts) ? $endpoint->allowed_hosts : [])
            ->filter(fn (mixed $host): bool => is_string($host) && $host !== '')
            ->map(fn (string $host): string => strtolower($host))
            ->unique()
            ->implode(',');

        if ($hosts === '') {
            throw new AcquisitionFailed('Refusing to render a page for an endpoint with no allowlisted host.');
        }

        $timeout = (int) config('civiclens.ingestion.browser.timeout_seconds', 60);

        $process = new Process(
            [
                $node,
                $script,
                $endpoint->base_url,
                $hosts,
                (string) ($timeout * 1000),
                (string) $endpoint->max_content_bytes,
            ],
            null,
            ['CIVICLENS_USER_AGENT' => (string) config('civiclens.ingestion.user_agent')],
        );

        // Longer than the worker's own budget, so a page that runs over is
        // reported by the worker rather than killed halfway and reported as
        // nothing.
        $process->setTimeout($timeout + 30);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new AcquisitionFailed('The browser-render worker timed out.');
        }

        if (! $process->isSuccessful()) {
            throw new AcquisitionFailed('The browser-render worker failed: '.trim($process->getErrorOutput()));
        }

        $html = $process->getOutput();

        if (trim($html) === '') {
            throw new AcquisitionFailed('The browser-render worker returned an empty page.');
        }

        return $html;
    }
}
