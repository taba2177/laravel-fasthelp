@if(config('fasthelp.widget.enabled'))
    <link rel="stylesheet" href="{{ asset('vendor/fasthelp/fasthelp.css') }}">

    {{-- Stable wrapper (outside Livewire's morph scope) so the resolved direction persists across updates. --}}
    <div id="fasthelp-root">
        @livewire('fasthelp-widget', ['pageUrl' => request()->fullUrl()])
    </div>

    <script>
        (function () {
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
        })();
    </script>

    <script src="{{ asset('vendor/fasthelp/fasthelp.js') }}"></script>
@endif
