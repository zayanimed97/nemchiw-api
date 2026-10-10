<x-filament-panels::page>
    @forelse ($this->getPending() as $place)
        <x-filament::section wire:key="{{ $place['kind'] }}-{{ $place['id'] }}">
            <x-slot name="heading">{{ $place['name'] }}</x-slot>
            <x-slot name="description">
                {{ $place['kind'] }} · {{ $place['id'] }} · {{ $place['createdAt'] }}
                @if ($place['waitingTrip']) · waiting trip: {{ $place['waitingTrip'] }} @endif
            </x-slot>
            <div style="display:flex;gap:.5rem">
                {{ ($this->approveAction)(['kind' => $place['kind'], 'id' => $place['id']]) }}
                {{ ($this->rejectAction)(['kind' => $place['kind'], 'id' => $place['id']]) }}
            </div>
        </x-filament::section>
    @empty
        <p>Nothing to review.</p>
    @endforelse
</x-filament-panels::page>
