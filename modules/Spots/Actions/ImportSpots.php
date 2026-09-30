<?php

namespace Modules\Spots\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Shared\Support\Governorates;
use Modules\Spots\Models\Spot;

/**
 * Upserts spots by id. Only rows whose content changed get a new updated_at,
 * each one millisecond apart, so the sync cursor never splits a tie.
 */
final class ImportSpots
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function __invoke(array $rows): array
    {
        Validator::make(['rows' => $rows], $this->rules())->validate();

        return DB::transaction(function () use ($rows) {
            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
            $stamp = now();

            foreach ($rows as $row) {
                $spot = Spot::query()->find($row['id']) ?? new Spot;
                $attributes = collect($row)->except('id')->all();

                if ($spot->exists && ! $this->differs($spot, $attributes)) {
                    $counts['unchanged']++;

                    continue;
                }

                $spot->id = $row['id'];
                $spot->fill($attributes);

                $counts[$spot->exists ? 'updated' : 'created']++;
                $stamp = $stamp->addMillisecond();
                $spot->setUpdatedAt($stamp);
                if (! $spot->exists) {
                    $spot->setCreatedAt($stamp);
                }
                $spot->save();
            }

            return $counts;
        });
    }

    /**
     * Compares by value, ignoring key order: MySQL stores JSON objects with its
     * own key order, so Eloquent's isDirty() would call every row changed.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function differs(Spot $spot, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if (self::canonical($spot->getAttribute($key)) != self::canonical($value)) {
                return true;
            }
        }

        return false;
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        ksort($value);

        return array_map(self::canonical(...), $value);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $text = fn (int $max) => ['required', 'string', 'max:'.$max];

        return [
            'rows' => ['required', 'array'],
            'rows.*' => ['array:id,name,description,latitude,longitude,governorate,type,access,water,coverage,permit'],
            'rows.*.id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', 'distinct'],
            'rows.*.name' => ['required', 'array:ar,fr,en'],
            'rows.*.name.ar' => $text(120),
            'rows.*.name.fr' => $text(120),
            'rows.*.name.en' => $text(120),
            'rows.*.description' => ['required', 'array:ar,fr,en'],
            'rows.*.description.ar' => $text(2000),
            'rows.*.description.fr' => $text(2000),
            'rows.*.description.en' => $text(2000),
            'rows.*.latitude' => ['required', 'numeric', 'between:30,38'],
            'rows.*.longitude' => ['required', 'numeric', 'between:7,12'],
            'rows.*.governorate' => ['required', Rule::in(Governorates::ALL)],
            'rows.*.type' => ['required', Rule::in(Spot::TYPES)],
            'rows.*.access' => ['required', Rule::in(Spot::ACCESS)],
            'rows.*.water' => ['required', 'boolean'],
            'rows.*.coverage' => ['required', Rule::in(Spot::COVERAGE)],
            'rows.*.permit' => ['required', Rule::in(Spot::PERMIT)],
        ];
    }
}
