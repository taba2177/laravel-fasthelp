@if(config('fasthelp.widget.enabled'))
    <link rel="stylesheet" href="{{ asset('vendor/fasthelp/fasthelp.css') }}">

    @livewire('fasthelp-widget', ['pageUrl' => request()->fullUrl()])

    <script src="{{ asset('vendor/fasthelp/fasthelp.js') }}"></script>
@endif
