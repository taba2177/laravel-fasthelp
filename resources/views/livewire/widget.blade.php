@php
    $primary = $widgetColors['primary'] ?? '#4f46e5';
    $onPrimary = $widgetColors['on_primary'] ?? '#ffffff';
    $posStart = ($widgetPosition ?? 'bottom-right') === 'bottom-left';
@endphp
<div class="fh-root {{ ($widgetEnabled ?? true) ? ($posStart ? 'fh-start' : 'fh-end') : '' }}"
     style="--fh-primary: {{ $primary }}; --fh-on: {{ $onPrimary }};">
    @if($widgetEnabled ?? true)
        <style>
            .fh-root{--fh-bg:#fff;--fh-muted:#6b7280;--fh-line:#eceef2;--fh-body:#f6f7f9;
                position:fixed;inset-block-end:24px;z-index:2147483000;display:flex;flex-direction:column;gap:14px;align-items:flex-end;
                font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Arabic",sans-serif;}
            .fh-root.fh-end{inset-inline-end:24px;}
            .fh-root.fh-start{inset-inline-start:24px;align-items:flex-start;}
            .fh-root *{box-sizing:border-box;}
            .fh-launcher{width:60px;height:60px;border-radius:50%;border:none;cursor:pointer;position:relative;color:var(--fh-on);
                background:radial-gradient(circle at 30% 30%, color-mix(in srgb, var(--fh-primary) 82%, #fff), var(--fh-primary));
                box-shadow:0 10px 25px -5px color-mix(in srgb, var(--fh-primary) 55%, transparent),0 8px 10px -6px rgba(0,0,0,.25);
                display:flex;align-items:center;justify-content:center;transition:transform .18s ease;}
            .fh-launcher:hover{transform:translateY(-2px) scale(1.05);}
            .fh-launcher:active{transform:scale(.96);}
            .fh-launcher svg{width:28px;height:28px;}
            .fh-dot{position:absolute;inset-block-start:2px;inset-inline-end:2px;width:13px;height:13px;border-radius:50%;background:#22c55e;border:2px solid var(--fh-primary);}
            .fh-dot::after{content:"";position:absolute;inset:-3px;border-radius:50%;background:#22c55e;opacity:.6;animation:fh-pulse 1.8s ease-out infinite;}
            @keyframes fh-pulse{0%{transform:scale(.7);opacity:.6}70%{transform:scale(2.2);opacity:0}100%{opacity:0}}
            .fh-panel{width:370px;max-width:calc(100vw - 32px);height:560px;max-height:calc(100vh - 130px);background:var(--fh-bg);
                border-radius:18px;overflow:hidden;display:flex;flex-direction:column;order:-1;
                box-shadow:0 24px 48px -12px rgba(0,0,0,.32),0 0 0 1px rgba(0,0,0,.04);
                animation:fh-pop .2s cubic-bezier(.21,1.02,.73,1);}
            @keyframes fh-pop{from{opacity:0;transform:translateY(16px) scale(.96)}to{opacity:1;transform:none}}
            .fh-header{background:linear-gradient(135deg,var(--fh-primary),color-mix(in srgb,var(--fh-primary) 65%,#000));color:var(--fh-on);
                padding:16px 18px;display:flex;align-items:center;gap:12px;}
            .fh-ava{width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
            .fh-ava svg{width:22px;height:22px;}
            .fh-htxt{flex:1;min-width:0;}
            .fh-title{font-weight:700;font-size:15px;line-height:1.2;}
            .fh-sub{font-size:12px;opacity:.9;display:flex;align-items:center;gap:6px;margin-top:2px;}
            .fh-led{width:8px;height:8px;border-radius:50%;background:#9ca3af;}
            .fh-sub.fh-on .fh-led{background:#34d399;box-shadow:0 0 0 3px rgba(52,211,153,.25);}
            .fh-x{background:rgba(255,255,255,.15);border:none;color:var(--fh-on);cursor:pointer;width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;transition:background .15s;}
            .fh-x:hover{background:rgba(255,255,255,.3);}
            .fh-x svg{width:17px;height:17px;}
            .fh-bodyw{flex:1;overflow-y:auto;background:var(--fh-body);padding:16px;display:flex;flex-direction:column;gap:10px;}
            .fh-note{align-self:center;background:#fff;border:1px solid var(--fh-line);color:var(--fh-muted);font-size:12px;padding:6px 12px;border-radius:999px;text-align:center;}
            .fh-msg{display:flex;gap:8px;max-width:85%;align-items:flex-end;}
            .fh-msg--client{align-self:flex-end;flex-direction:row-reverse;}
            .fh-msg--agent,.fh-msg--bot{align-self:flex-start;}
            .fh-msg--system{align-self:center;max-width:100%;}
            .fh-mava{width:26px;height:26px;border-radius:50%;flex:0 0 auto;background:var(--fh-primary);color:var(--fh-on);display:flex;align-items:center;justify-content:center;}
            .fh-msg--bot .fh-mava{background:#111827;}
            .fh-mava svg{width:15px;height:15px;}
            .fh-bub{padding:9px 13px;border-radius:16px;font-size:13.5px;line-height:1.45;overflow-wrap:anywhere;}
            .fh-msg--client .fh-bub{background:var(--fh-primary);color:var(--fh-on);border-end-end-radius:5px;}
            .fh-msg--agent .fh-bub,.fh-msg--bot .fh-bub{background:#fff;color:#111827;border:1px solid var(--fh-line);border-end-start-radius:5px;}
            .fh-msg--system .fh-bub{background:transparent;color:var(--fh-muted);font-size:12px;text-align:center;}
            .fh-meta{font-size:10px;color:var(--fh-muted);margin-top:3px;}
            .fh-msg--client .fh-meta{text-align:end;}
            .fh-handoff{padding:10px 16px;}
            .fh-handoff button{width:100%;border:1px dashed color-mix(in srgb,var(--fh-primary) 45%,#ccc);background:#fff;color:color-mix(in srgb,var(--fh-primary) 75%,#000);
                font-size:12.5px;font-weight:600;padding:9px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:background .15s;}
            .fh-handoff button:hover{background:var(--fh-body);}
            .fh-handoff svg{width:15px;height:15px;}
            .fh-footer{display:flex;align-items:center;gap:8px;padding:12px;border-top:1px solid var(--fh-line);background:#fff;}
            .fh-input{flex:1;border:1px solid var(--fh-line);background:var(--fh-body);border-radius:999px;padding:10px 16px;font-size:13.5px;outline:none;color:#111827;transition:border-color .15s,box-shadow .15s;}
            .fh-input:focus{border-color:var(--fh-primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--fh-primary) 18%,transparent);}
            .fh-send{flex:0 0 auto;width:42px;height:42px;border-radius:50%;border:none;cursor:pointer;background:var(--fh-primary);color:var(--fh-on);display:flex;align-items:center;justify-content:center;transition:transform .15s,filter .15s;}
            .fh-send:hover{filter:brightness(1.08);transform:scale(1.05);}
            .fh-send svg{width:19px;height:19px;}
            .fh-root:dir(rtl) .fh-send svg,.fh-root:dir(rtl) .fh-handoff svg{transform:scaleX(-1);}
            .fh-bodyw::-webkit-scrollbar{width:7px;}
            .fh-bodyw::-webkit-scrollbar-thumb{background:#d3d7de;border-radius:99px;}
        </style>

        @if($open)
            <div class="fh-panel" role="dialog" aria-label="{{ $widgetTitle ?? __('fasthelp::fasthelp.title') }}">
                <div class="fh-header">
                    <span class="fh-ava" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 8.5h9M7.5 12h6"/><path d="M21 12a8.5 8.5 0 0 1-12.3 7.6L3 21l1.4-4.2A8.5 8.5 0 1 1 21 12Z"/></svg>
                    </span>
                    <div class="fh-htxt">
                        <div class="fh-title">{{ $widgetTitle ?? __('fasthelp::fasthelp.title') }}</div>
                        <div class="fh-sub {{ $onlineCount > 0 ? 'fh-on' : '' }}">
                            <span class="fh-led"></span>
                            {{ $onlineCount > 0 ? __('fasthelp::fasthelp.online') : __('fasthelp::fasthelp.offline') }}
                        </div>
                    </div>
                    <button type="button" class="fh-x" wire:click="toggle" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="fh-bodyw">
                    <div class="fh-note">{{ $widgetGreeting ?? __('fasthelp::fasthelp.greeting') }}</div>

                    @foreach($messages as $message)
                        @php $t = $message['sender_type']; @endphp
                        <div class="fh-msg fh-msg--{{ $t }}">
                            @if($t === 'agent' || $t === 'bot')
                                <span class="fh-mava" aria-hidden="true">
                                    @if($t === 'bot')
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 8V5M9 13h.01M15 13h.01"/></svg>
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20a7 7 0 0 1 14 0"/></svg>
                                    @endif
                                </span>
                            @endif
                            <div>
                                <div class="fh-bub" dir="auto">{{ $message['body'] }}</div>
                                @if($t !== 'system' && !empty($message['created_at']))
                                    <div class="fh-meta">{{ \Illuminate\Support\Carbon::parse($message['created_at'])->format('H:i') }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($status === 'open')
                    <div class="fh-handoff">
                        <button type="button" wire:click="requestHuman">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20a7 7 0 0 1 14 0"/></svg>
                            {{ __('fasthelp::fasthelp.talk_to_human') }}
                        </button>
                    </div>
                @endif

                <div class="fh-footer">
                    <input type="text" class="fh-input" dir="auto" wire:model="body" wire:keydown.enter="sendMessage"
                        placeholder="{{ __('fasthelp::fasthelp.placeholder') }}" />
                    <button type="button" class="fh-send" wire:click="sendMessage" aria-label="{{ __('fasthelp::fasthelp.send') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12 20 4l-6 16-2.5-6.5L4 12Z"/></svg>
                    </button>
                </div>
            </div>
        @endif

        <button type="button" class="fh-launcher" wire:click="toggle" aria-label="{{ $widgetTitle ?? __('fasthelp::fasthelp.title') }}">
            @if($open)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 8.5h9M7.5 12h6"/><path d="M21 12a8.5 8.5 0 0 1-12.3 7.6L3 21l1.4-4.2A8.5 8.5 0 1 1 21 12Z"/></svg>
                @if($onlineCount > 0)<span class="fh-dot"></span>@endif
            @endif
        </button>
    @endif
</div>
