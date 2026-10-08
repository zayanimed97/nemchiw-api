<?php

namespace Modules\Admin\Places;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Set;
use Modules\Shared\Places\PlaceFacts;
use Modules\Shared\Places\PlaceStatus;
use Modules\Shared\Support\Governorates;

final class PlaceForm
{
    /** @return list<mixed> id, names, descriptions, coordinates, governorate, status */
    public static function common(): array
    {
        $languages = ['ar' => 'Darija', 'fr' => 'Français', 'en' => 'English'];

        return [
            TextInput::make('id')->required()->maxLength(64)->regex('/^(?!example-)[a-z0-9-]+$/')
                ->unique(ignoreRecord: true)->disabledOn('edit')
                ->helperText('Lowercase letters, digits and dashes, e.g. zaghouan-aqueduct. Never changes.'),
            Fieldset::make('Name')->schema(collect($languages)->map(
                fn ($label, $code) => TextInput::make("name.{$code}")->label($label)->required()->maxLength(120),
            )->values()->all()),
            Fieldset::make('Description')->schema(collect($languages)->map(
                fn ($label, $code) => Textarea::make("description.{$code}")->label($label)->required()->maxLength(2000)->rows(4),
            )->values()->all()),
            TextInput::make('coordinates')->dehydrated(false)->live(onBlur: true)
                ->helperText('Paste "36.77, 8.68" or a Google Maps link to fill latitude and longitude.')
                ->afterStateUpdated(function (?string $state, Set $set) {
                    if ($state !== null && ($pair = self::parseCoordinates($state)) !== null) {
                        $set('latitude', $pair[0]);
                        $set('longitude', $pair[1]);
                    }
                }),
            TextInput::make('latitude')->numeric()->required()->minValue(30)->maxValue(38),
            TextInput::make('longitude')->numeric()->required()->minValue(7)->maxValue(12),
            Select::make('governorate')->required()->options(array_combine(Governorates::ALL, Governorates::ALL)),
            Select::make('status')->required()->default(PlaceStatus::LIVE)
                ->options(array_combine(PlaceStatus::ALL, PlaceStatus::ALL)),
        ];
    }

    /** @return list<mixed> */
    public static function signalAndPermit(): array
    {
        return [
            Select::make('coverage')->required()->options(array_combine(PlaceFacts::COVERAGE, PlaceFacts::COVERAGE)),
            Select::make('permit')->required()->options(array_combine(PlaceFacts::PERMIT, PlaceFacts::PERMIT)),
        ];
    }

    /** "36.77, 8.68", ".../@36.77,8.68,15z" or "...?q=36.77,8.68" → [lat, lng]. */
    public static function parseCoordinates(string $input): ?array
    {
        if (preg_match('/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/', $input, $m) !== 1) {
            return null;
        }

        return [(float) $m[1], (float) $m[2]];
    }
}
