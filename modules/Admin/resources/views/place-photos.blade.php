<x-filament-panels::page>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem">
        @forelse ($this->photos() as $i => $photo)
            <x-filament::section wire:key="{{ $photo['hash'] }}">
                <img src="{{ $photo['url'] }}" alt="" style="width:100%;border-radius:.5rem">
                <div style="display:flex;gap:.5rem;margin-top:.5rem;flex-wrap:wrap">
                    @if ($i === 0)<x-filament::badge>Cover</x-filament::badge>@endif
                    <x-filament::button size="xs" color="gray" wire:click="move('{{ $photo['hash'] }}', -1)" :disabled="$i === 0">Earlier</x-filament::button>
                    <x-filament::button size="xs" color="gray" wire:click="move('{{ $photo['hash'] }}', 1)" :disabled="$loop->last">Later</x-filament::button>
                    <x-filament::button size="xs" color="danger" wire:click="remove('{{ $photo['hash'] }}')" wire:confirm="Remove this photo?">Remove</x-filament::button>
                </div>
            </x-filament::section>
        @empty
            <p>No photos yet. The first one you add becomes the cover.</p>
        @endforelse
    </div>
</x-filament-panels::page>
