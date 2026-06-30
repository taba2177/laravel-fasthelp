@if(config('fasthelp.widget.enabled'))
    <link rel="stylesheet" href="{{ asset('vendor/fasthelp/fasthelp.css') }}">

    {{-- Stable wrapper (outside Livewire's morph scope) so the resolved direction persists across updates. --}}
    <div id="fasthelp-root">
        @livewire('fasthelp-widget', ['pageUrl' => request()->fullUrl()])
    </div>

    <script>
        (function () {
            try {
                // ── Direction detection ──────────────────────────────────────────────
                // Mirror the host page's direction onto the widget — explicit dir wins, otherwise infer from <html lang>.
                var html = document.documentElement;
                var rtl = ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ug', 'dv', 'yi', 'ckb'];
                var lang = (html.getAttribute('lang') || '').toLowerCase().split('-')[0];
                var dir = html.getAttribute('dir') || (rtl.indexOf(lang) !== -1 ? 'rtl' : 'ltr');
                var apply = function () {
                    var el = document.getElementById('fasthelp-root');
                    if (el) { el.setAttribute('dir', dir); }
                };
                apply();
                // Re-apply after Livewire (re)mounts / SPA navigations.
                document.addEventListener('livewire:navigated', apply);
                document.addEventListener('livewire:initialized', apply);

                // ── Auto-theme ───────────────────────────────────────────────────────
                var fhAuto = @json(app(\Tabadev\FastHelp\Support\Settings::class)->get('widget.auto_theme', true));

                function toRgb(color) {
                    try {
                        var span = document.createElement('span');
                        span.style.color = color;
                        span.style.position = 'absolute';
                        span.style.visibility = 'hidden';
                        document.body.appendChild(span);
                        var computed = getComputedStyle(span).color;
                        document.body.removeChild(span);
                        var m = computed.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
                        if (!m) { return null; }
                        return { r: parseInt(m[1], 10), g: parseInt(m[2], 10), b: parseInt(m[3], 10) };
                    } catch (e) { return null; }
                }

                function readableOn(color) {
                    try {
                        var rgb = toRgb(color);
                        if (!rgb) { return null; }
                        var L = (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b) / 255;
                        return L > 0.6 ? '#111827' : '#ffffff';
                    } catch (e) { return null; }
                }

                function detectPrimary() {
                    try {
                        var meta = document.querySelector('meta[name="theme-color"]');
                        if (meta) {
                            var c = (meta.getAttribute('content') || '').trim();
                            if (c) { return c; }
                        }
                        var vars = ['--primary', '--color-primary', '--brand', '--brand-color', '--bs-primary', '--p', '--accent', '--theme-primary', '--tw-color-primary'];
                        var rootStyle = getComputedStyle(document.documentElement);
                        for (var i = 0; i < vars.length; i++) {
                            var v = rootStyle.getPropertyValue(vars[i]).trim();
                            if (v) { return v; }
                        }
                    } catch (e) {}
                    return null;
                }

                function applyTheme() {
                    try {
                        if (!fhAuto) { return; }
                        var font = getComputedStyle(document.body).fontFamily;
                        var primary = detectPrimary();
                        var roots = document.querySelectorAll('.fh-root');
                        for (var i = 0; i < roots.length; i++) {
                            var el = roots[i];
                            if (font) { el.style.setProperty('--fh-font', font); }
                            if (primary) {
                                el.style.setProperty('--fh-primary', primary);
                                var on = readableOn(primary);
                                if (on) { el.style.setProperty('--fh-on', on); }
                            }
                        }
                    } catch (e) {}
                }

                applyTheme();
                document.addEventListener('livewire:navigated', applyTheme);
                document.addEventListener('livewire:initialized', applyTheme);

                // Survive Livewire morph updates (component re-renders reset inline styles).
                document.addEventListener('livewire:init', function () {
                    try {
                        if (window.Livewire && Livewire.hook) {
                            Livewire.hook('morph.updated', function () { applyTheme(); });
                        }
                    } catch (e) {}
                });
            } catch (e) {}
        })();
    </script>

    <script src="{{ asset('vendor/fasthelp/fasthelp.js') }}"></script>
@endif
