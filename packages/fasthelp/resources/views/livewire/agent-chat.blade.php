<div style="display: flex; flex-direction: column; height: 100%; font-family: sans-serif; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
        <div style="font-size: 13px; color: #374151;">
            <strong>{{ __('fasthelp::fasthelp.online') }}:</strong> {{ $onlineCount }}
        </div>

        <div style="display: flex; gap: 6px;">
            <button type="button" wire:click="goOnline" style="padding: 4px 8px; font-size: 12px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; border-radius: 6px; cursor: pointer;">
                Online
            </button>
            <button type="button" wire:click="goAway" style="padding: 4px 8px; font-size: 12px; background: #fffbeb; color: #b45309; border: 1px solid #fde68a; border-radius: 6px; cursor: pointer;">
                Away
            </button>
            <button type="button" wire:click="goOffline" style="padding: 4px 8px; font-size: 12px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer;">
                Offline
            </button>
            <button type="button" wire:click="markResolved" style="padding: 4px 8px; font-size: 12px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; cursor: pointer;">
                Resolve
            </button>
        </div>
    </div>

    <div style="flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; min-height: 240px;">
        @forelse ($messages as $message)
            @php
                $senderType = $message['sender_type'] ?? 'system';
                $isAgent = $senderType === 'agent';
                $isClient = $senderType === 'client';
                $isBot = $senderType === 'bot';
                $isSystem = $senderType === 'system';

                $bubbleStyle = match (true) {
                    $isAgent => 'background: #4f46e5; color: #ffffff;',
                    $isClient => 'background: #f1f1f1; color: #222222;',
                    $isBot => 'background: #ecfeff; color: #155e75; border: 1px solid #a5f3fc;',
                    default => 'background: transparent; color: #6b7280; font-style: italic;',
                };

                $justify = $isAgent ? 'flex-end' : 'flex-start';
            @endphp

            <div style="display: flex; justify-content: {{ $isSystem ? 'center' : $justify }};" wire:key="agent-chat-message-{{ $message['id'] }}">
                <div style="max-width: 80%; padding: 8px 12px; border-radius: 10px; font-size: 13px; {{ $bubbleStyle }}">
                    @unless ($isSystem)
                        <div style="font-size: 10px; opacity: 0.7; margin-bottom: 2px; text-transform: uppercase;">
                            {{ $senderType }}
                        </div>
                    @endunless
                    <div>{{ $message['body'] }}</div>
                </div>
            </div>
        @empty
            <div style="font-size: 13px; color: #9ca3af; text-align: center; margin-top: 24px;">
                No messages yet.
            </div>
        @endforelse
    </div>

    <div style="display: flex; gap: 8px; padding: 12px; border-top: 1px solid #e5e7eb;">
        <input
            type="text"
            wire:model="body"
            wire:keydown.enter="send"
            placeholder="{{ __('fasthelp::fasthelp.placeholder') }}"
            style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;"
        />
        <button type="button" wire:click="send" style="padding: 8px 12px; background: #4f46e5; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">
            {{ __('fasthelp::fasthelp.send') }}
        </button>
    </div>
</div>
