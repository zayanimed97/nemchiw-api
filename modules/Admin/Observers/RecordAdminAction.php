<?php

namespace Modules\Admin\Observers;

use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Models\AdminAction;
use Modules\Admin\Places\PlaceModels;

/** Every change an admin makes to a place, so a hidden or rejected one can be traced. */
final class RecordAdminAction
{
    public function created(Model $place): void
    {
        $this->record('created', $place, null);
    }

    public function updated(Model $place): void
    {
        $changes = collect($place->getChanges())->except(['updated_at'])->keys()->all();
        if ($changes !== []) {
            $this->record('updated', $place, array_fill_keys($changes, true));
        }
    }

    public function deleted(Model $place): void
    {
        $this->record('deleted', $place, null);
    }

    public function restored(Model $place): void
    {
        $this->record('restored', $place, null);
    }

    /** @param array<string, true>|null $changes the changed fields (names only: values may be long) */
    private function record(string $action, Model $place, ?array $changes): void
    {
        $admin = auth('admin')->user();
        if ($admin === null) {
            return;
        }
        AdminAction::query()->create([
            'admin_id' => $admin->getAuthIdentifier(),
            'action' => $action,
            'target_kind' => PlaceModels::kindOf($place),
            'target_id' => $place->getKey(),
            'changes' => $changes,
        ]);
    }
}
