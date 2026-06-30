<div class="fhc-root">
    <style>
        .fhc-root{--fhc-primary:#4f46e5;--fhc-line:#e5e7eb;--fhc-body:#f6f7f9;--fhc-muted:#6b7280;
            display:flex;flex-direction:column;height:600px;max-height:72vh;border:1px solid var(--fhc-line);border-radius:14px;overflow:hidden;
            background:#fff;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Arabic",sans-serif;}
        .fhc-root *{box-sizing:border-box;}
        .fhc-head{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--fhc-line);background:#fafbfc;flex-wrap:wrap;}
        .fhc-status{font-size:13px;color:#374151;display:flex;align-items:center;gap:7px;margin-inline-end:auto;}
        .fhc-led{width:9px;height:9px;border-radius:50%;background:#9ca3af;}
        .fhc-status.fhc-on .fhc-led{background:#34d399;box-shadow:0 0 0 3px rgba(52,211,153,.25);}
        .fhc-btns{display:flex;gap:6px;flex-wrap:wrap;}
        .fhc-pill{padding:5px 11px;font-size:12px;font-weight:600;border-radius:999px;cursor:pointer;border:1px solid transparent;transition:filter .15s;}
        .fhc-pill:hover{filter:brightness(.97);}
        .fhc-on-b{background:#ecfdf5;color:#047857;border-color:#a7f3d0;}
        .fhc-away-b{background:#fffbeb;color:#b45309;border-color:#fde68a;}
        .fhc-off-b{background:#f3f4f6;color:#374151;border-color:#d1d5db;}
        .fhc-res-b{background:var(--fhc-primary);color:#fff;}
        .fhc-body{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:var(--fhc-body);}
        .fhc-msg{display:flex;gap:8px;max-width:78%;align-items:flex-end;}
        .fhc-msg--agent{align-self:flex-end;flex-direction:row-reverse;}
        .fhc-msg--client,.fhc-msg--bot{align-self:flex-start;}
        .fhc-msg--system{align-self:center;max-width:100%;}
        .fhc-ava{width:28px;height:28px;border-radius:50%;flex:0 0 auto;display:flex;align-items:center;justify-content:center;color:#fff;background:#9ca3af;}
        .fhc-msg--client .fhc-ava{background:#0ea5e9;}
        .fhc-msg--bot .fhc-ava{background:#111827;}
        .fhc-ava svg{width:16px;height:16px;}
        .fhc-bub{padding:9px 13px;border-radius:14px;font-size:13.5px;line-height:1.45;overflow-wrap:anywhere;}
        .fhc-msg--agent .fhc-bub{background:var(--fhc-primary);color:#fff;border-end-end-radius:5px;}
        .fhc-msg--client .fhc-bub{background:#fff;color:#111827;border:1px solid var(--fhc-line);border-end-start-radius:5px;}
        .fhc-msg--bot .fhc-bub{background:#ecfeff;color:#155e75;border:1px solid #a5f3fc;border-end-start-radius:5px;}
        .fhc-msg--system .fhc-bub{background:transparent;color:var(--fhc-muted);font-style:italic;text-align:center;font-size:12px;}
        .fhc-label{font-size:10px;opacity:.7;text-transform:uppercase;margin-bottom:2px;letter-spacing:.03em;}
        .fhc-meta{font-size:10px;color:var(--fhc-muted);margin-top:3px;}
        .fhc-msg--agent .fhc-meta{text-align:end;}
        .fhc-empty{font-size:13px;color:#9ca3af;text-align:center;margin-top:32px;}
        .fhc-foot{display:flex;gap:8px;padding:12px;border-top:1px solid var(--fhc-line);background:#fff;}
        .fhc-input{flex:1;border:1px solid var(--fhc-line);background:var(--fhc-body);border-radius:999px;padding:10px 16px;font-size:13.5px;outline:none;color:#111827;transition:border-color .15s,box-shadow .15s;}
        .fhc-input:focus{border-color:var(--fhc-primary);box-shadow:0 0 0 3px color-mix(in srgb,var(--fhc-primary) 18%,transparent);}
        .fhc-send{flex:0 0 auto;width:42px;height:42px;border-radius:50%;border:none;cursor:pointer;background:var(--fhc-primary);color:#fff;display:flex;align-items:center;justify-content:center;transition:transform .15s,filter .15s;}
        .fhc-send:hover{filter:brightness(1.08);transform:scale(1.05);}
        .fhc-send svg{width:19px;height:19px;}
        .fhc-root:dir(rtl) .fhc-send svg{transform:scaleX(-1);}
        .fhc-card{display:flex;flex-direction:column;margin-top:6px;border:1px solid var(--fhc-line);border-radius:10px;background:#fff;overflow:hidden;text-decoration:none;color:inherit;transition:background .15s;}
        .fhc-card:hover{background:var(--fhc-body);}
        .fhc-card-inner{display:flex;align-items:flex-start;padding:8px 10px;}
        .fhc-card-thumb{width:56px;height:56px;object-fit:cover;border-radius:6px;flex:0 0 auto;margin-inline-end:10px;}
        .fhc-card-text{display:flex;flex-direction:column;gap:2px;min-width:0;flex:1;}
        .fhc-card .fhc-card-title{font-size:12.5px;font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .fhc-card .fhc-card-desc{font-size:11.5px;color:#4b5563;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
        .fhc-card .fhc-card-host{font-size:11px;color:var(--fhc-muted);margin-top:1px;}
    </style>

    <div class="fhc-head">
        <div class="fhc-status {{ $onlineCount > 0 ? 'fhc-on' : '' }}">
            <span class="fhc-led"></span>
            <strong>{{ __('fasthelp::fasthelp.online') }}:</strong> {{ $onlineCount }}
        </div>
        <div class="fhc-btns">
            <button type="button" class="fhc-pill fhc-on-b" wire:click="goOnline">Online</button>
            <button type="button" class="fhc-pill fhc-away-b" wire:click="goAway">Away</button>
            <button type="button" class="fhc-pill fhc-off-b" wire:click="goOffline">Offline</button>
            <button type="button" class="fhc-pill fhc-res-b" wire:click="markResolved">Resolve</button>
        </div>
    </div>

    <div class="fhc-body">
        @forelse ($messages as $message)
            @php $t = $message['sender_type'] ?? 'system'; @endphp
            <div class="fhc-msg fhc-msg--{{ $t }}" wire:key="agent-chat-message-{{ $message['id'] }}">
                @if($t !== 'agent' && $t !== 'system')
                    <span class="fhc-ava" aria-hidden="true">
                        @if($t === 'bot')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 8V5M9 13h.01M15 13h.01"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20a7 7 0 0 1 14 0"/></svg>
                        @endif
                    </span>
                @endif
                <div>
                    @unless ($t === 'system')
                        <div class="fhc-label">{{ $t }}</div>
                    @endunless
                    <div class="fhc-bub" dir="auto">{!! \Tabadev\FastHelp\Support\Linkify::toHtml($message['body']) !!}</div>
                    @if(!empty($message['previews']))
                        @foreach($message['previews'] as $p)
                            @php $host = parse_url($p['url'], PHP_URL_HOST); @endphp
                            <a class="fhc-card" href="{{ $p['url'] }}" target="_blank" rel="noopener noreferrer">
                                <div class="fhc-card-inner">
                                    @if(!empty($p['image']))
                                        <img class="fhc-card-thumb" src="{{ $p['image'] }}" alt="" loading="lazy">
                                    @endif
                                    <div class="fhc-card-text">
                                        <span class="fhc-card-title">{{ $p['title'] }}</span>
                                        @if(!empty($p['description']))
                                            <span class="fhc-card-desc">{{ \Illuminate\Support\Str::limit($p['description'], 120) }}</span>
                                        @endif
                                        <span class="fhc-card-host">{{ $host }}</span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    @endif
                    @if($t !== 'system' && !empty($message['created_at']))
                        <div class="fhc-meta">{{ \Illuminate\Support\Carbon::parse($message['created_at'])->format('H:i') }}</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="fhc-empty">{{ __('fasthelp::fasthelp.greeting') }}</div>
        @endforelse
    </div>

    <div class="fhc-foot">
        <input type="text" class="fhc-input" dir="auto" wire:model="body" wire:keydown.enter="send"
            placeholder="{{ __('fasthelp::fasthelp.placeholder') }}" />
        <button type="button" class="fhc-send" wire:click="send" aria-label="{{ __('fasthelp::fasthelp.send') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12 20 4l-6 16-2.5-6.5L4 12Z"/></svg>
        </button>
    </div>
</div>
