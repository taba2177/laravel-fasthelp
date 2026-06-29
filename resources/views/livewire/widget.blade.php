@php
    $isBottomRight = ($widgetPosition ?? 'bottom-right') !== 'bottom-left';
    $primary = $widgetColors['primary'] ?? '#4f46e5';
    $onPrimary = $widgetColors['on_primary'] ?? '#ffffff';
    $sideStyle = $isBottomRight ? 'right: 24px;' : 'left: 24px;';
@endphp
<div>
    @if($widgetEnabled ?? true)
        <div style="position: fixed; bottom: 24px; {{ $sideStyle }} z-index: 9999; font-family: sans-serif;">
            @if($open)
                <div style="width: 320px; max-height: 480px; display: flex; flex-direction: column; background: #ffffff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); overflow: hidden; margin-bottom: 12px;">
                    <div style="background: {{ $primary }}; color: {{ $onPrimary }}; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;">
                        <strong>{{ $widgetTitle ?? __('fasthelp::fasthelp.title') }}</strong>
                        <button type="button" wire:click="toggle" style="background: transparent; border: none; color: {{ $onPrimary }}; cursor: pointer; font-size: 16px;">&times;</button>
                    </div>

                    <div style="padding: 8px 12px; font-size: 12px; color: #555; border-bottom: 1px solid #eee;">
                        {{ $onlineCount > 0 ? __('fasthelp::fasthelp.online') : __('fasthelp::fasthelp.offline') }}
                        ({{ $onlineCount }})
                    </div>

                    <div style="flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; min-height: 200px;">
                        <div style="font-size: 13px; color: #444; margin-bottom: 4px;">
                            {{ $widgetGreeting ?? __('fasthelp::fasthelp.greeting') }}
                        </div>

                        @foreach($messages as $message)
                            <div style="display: flex; {{ $message['sender_type'] === 'client' ? 'justify-content: flex-end;' : 'justify-content: flex-start;' }}">
                                <div style="
                                    max-width: 80%;
                                    padding: 8px 12px;
                                    border-radius: 10px;
                                    font-size: 13px;
                                    {{ $message['sender_type'] === 'client'
                                        ? 'background: '.$primary.'; color: '.$onPrimary.';'
                                        : 'background: #f1f1f1; color: #222;' }}
                                ">
                                    {{ $message['body'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($status === 'open')
                        <div style="padding: 8px 12px; border-top: 1px solid #eee;">
                            <button type="button" wire:click="requestHuman" style="width: 100%; padding: 6px; font-size: 12px; background: #f1f1f1; border: none; border-radius: 6px; cursor: pointer;">
                                {{ __('fasthelp::fasthelp.talk_to_human') }}
                            </button>
                        </div>
                    @endif

                    <div style="display: flex; gap: 8px; padding: 12px; border-top: 1px solid #eee;">
                        <input
                            type="text"
                            wire:model="body"
                            wire:keydown.enter="sendMessage"
                            placeholder="{{ __('fasthelp::fasthelp.placeholder') }}"
                            style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;"
                        />
                        <button type="button" wire:click="sendMessage" style="padding: 8px 12px; background: {{ $primary }}; color: {{ $onPrimary }}; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">
                            {{ __('fasthelp::fasthelp.send') }}
                        </button>
                    </div>
                </div>
            @endif

            <button
                type="button"
                wire:click="toggle"
                style="width: 56px; height: 56px; border-radius: 50%; background: {{ $primary }}; color: {{ $onPrimary }}; border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.25); cursor: pointer; font-size: 22px; display: flex; align-items: center; justify-content: center; margin-left: auto;"
            >
                &#128172;
            </button>
        </div>
    @else
        <div></div>
    @endif
</div>
