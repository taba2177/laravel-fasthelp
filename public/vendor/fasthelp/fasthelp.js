/*!
 * FastHelp widget bootstrap shim.
 *
 * This file does NOT bundle Laravel Echo or Pusher. For full real-time
 * delivery, hosts should configure their own `window.Echo` instance
 * (e.g. via laravel-echo + pusher-js / Reverb) before this script runs.
 *
 * If the host has already set up `window.Echo`, this script does nothing.
 * Otherwise, it makes a best-effort attempt to construct a Reverb-backed
 * Echo instance using <meta> tags for configuration, but ONLY if the
 * `Echo` and `Pusher` constructors are already present on `window`
 * (e.g. loaded via host's own <script> tags or bundle). If those globals
 * are missing, this script does nothing and the widget falls back to its
 * polling mechanism. This function must never throw.
 */
(function () {
    'use strict';

    function debugLog(message) {
        if (window.console && typeof window.console.debug === 'function') {
            window.console.debug('[FastHelp] ' + message);
        }
    }

    function metaContent(name) {
        var el = document.querySelector('meta[name="' + name + '"]');

        return el ? el.getAttribute('content') : null;
    }

    function tryInitEcho() {
        if (window.Echo) {
            debugLog('window.Echo already present; skipping bootstrap.');

            return;
        }

        if (typeof window.Echo === 'undefined' && typeof window.EchoConstructor !== 'function') {
            // No Echo constructor available on the page (host did not load
            // laravel-echo). Nothing we can safely do without bundling it.
            if (typeof window.Pusher !== 'function') {
                return;
            }
        }

        var EchoCtor = window.EchoConstructor;

        if (typeof EchoCtor !== 'function' || typeof window.Pusher !== 'function') {
            // Required globals are not present; do nothing.
            return;
        }

        var key = metaContent('fasthelp-reverb-key');
        var host = metaContent('fasthelp-reverb-host');
        var port = metaContent('fasthelp-reverb-port');
        var scheme = metaContent('fasthelp-reverb-scheme');

        if (!key || !host) {
            // Insufficient configuration; skip silently.
            return;
        }

        try {
            window.Echo = new EchoCtor({
                broadcaster: 'reverb',
                key: key,
                wsHost: host,
                wsPort: port ? parseInt(port, 10) : 80,
                wssPort: port ? parseInt(port, 10) : 443,
                forceTLS: (scheme || 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
            });

            debugLog('Initialized a best-effort Reverb Echo instance.');
        } catch (error) {
            // Never throw from this shim; the widget degrades gracefully.
            debugLog('Failed to initialize Echo: ' + (error && error.message ? error.message : error));
        }
    }

    try {
        tryInitEcho();
    } catch (error) {
        debugLog('Unexpected error during bootstrap: ' + (error && error.message ? error.message : error));
    }
})();
